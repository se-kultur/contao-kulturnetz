<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\File\MetadataBag;
use Contao\CoreBundle\Filesystem\ExtraMetadata;
use Contao\CoreBundle\Filesystem\FilesystemItem;
use Contao\CoreBundle\Filesystem\VirtualFilesystemInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\MediaFileInfo;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\StoredFile;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\ValidatedImage;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Uid\Uuid;

/**
 * Dateien der Mitglieder im Contao-Dateisystem (VFS "contao.filesystem.virtual.files").
 *
 * Jeder Schreib- und Löschvorgang läuft über das VFS; Contao gleicht dabei die
 * Dateiverwaltung (tl_files) selbst ab (UUID, Hash, Ordner). Eigene tl_files-
 * Einträge, eigene UUIDs und unlink() der früheren Skripte entfallen.
 *
 * Namensschema wie bisher (Entscheidung E33, die PDF-Ausgabe leitet das Original
 * aus dem Namen ab):
 *  - Titelbilder: <art>_<hash>.png (Zuschnitt, mit Metadaten) und
 *    <art>_<hash>_ori.<echte Endung> (Original),
 *  - Galerie: gallery_<hash>_ori.<echte Endung> (mit Metadaten).
 * Eine verkleinerte _sm-Kopie wird nicht mehr erzeugt (Bildgrößen erzeugt Contao).
 *
 * Metadaten: tl_files.meta in der Sprache "de" mit den Schlüsseln title, alt,
 * link, caption (Copyright) und license wie bisher. Texte werden im Contao-5.7-
 * Format gespeichert (TextNormalizer, Restpunkt R19).
 *
 * Hinweis: Die VFS-API ist in Contao 5.7 als @experimental markiert (Doku
 * "Experimental features"); sie wird deshalb nur in dieser Klasse benutzt
 * (Restpunkt R20).
 */
final class MemberMediaStorage
{
    private const LOCALE = 'de';

    private const SUFFIX_ORIGINAL = '_ori';

    /**
     * Verkleinerte Kopie der früheren Galerie-Uploads (wird nur noch beim Löschen
     * mit entfernt).
     */
    private const SUFFIX_LEGACY_SMALL = '_sm';

    public function __construct(
        #[Autowire(service: 'contao.filesystem.virtual.files')]
        private readonly VirtualFilesystemInterface $filesystem,
        #[Autowire(param: 'contao.upload_path')]
        private readonly string $uploadPath,
        private readonly TextNormalizer $textNormalizer,
    ) {
    }

    /**
     * Speichert ein zugeschnittenes Titelbild (avatar, event): Zuschnitt als PNG
     * mit Metadaten und das Original mit seiner echten Endung.
     *
     * @param string $homeDir Mitgliedsordner relativ zum Projektverzeichnis
     */
    public function storeCroppedImage(string $homeDir, MediaType $type, ValidatedImage $original, string $croppedPng, string $title, string $copyright): StoredFile
    {
        $directory = $this->toStoragePath($homeDir);
        $base = $this->createUniqueBaseName($directory, $type->value, ['.png', self::SUFFIX_ORIGINAL.'.'.$original->extension]);

        $croppedPath = $directory.'/'.$base.'.png';
        $originalPath = $directory.'/'.$base.self::SUFFIX_ORIGINAL.'.'.$original->extension;

        $this->writeOriginal($originalPath, $original);

        try {
            $this->filesystem->write($croppedPath, $croppedPng);
            $this->filesystem->setExtraMetadata($croppedPath, $this->createMetadata($croppedPath, $title, $copyright));
        } catch (\Throwable $exception) {
            $this->deleteIfExists($croppedPath);
            $this->deleteIfExists($originalPath);

            throw $exception;
        }

        return $this->createStoredFile($croppedPath);
    }

    /**
     * Speichert ein Galeriebild (Original mit echter Endung und Metadaten).
     *
     * @param string $homeDir Mitgliedsordner relativ zum Projektverzeichnis
     */
    public function storeGalleryImage(string $homeDir, ValidatedImage $original, string $title, string $copyright): StoredFile
    {
        $directory = $this->toStoragePath($homeDir);
        $suffix = self::SUFFIX_ORIGINAL.'.'.$original->extension;
        $path = $directory.'/'.$this->createUniqueBaseName($directory, MediaType::Gallery->value, [$suffix]).$suffix;

        $this->writeOriginal($path, $original);

        try {
            $this->filesystem->setExtraMetadata($path, $this->createMetadata($path, $title, $copyright));
        } catch (\Throwable $exception) {
            $this->deleteIfExists($path);

            throw $exception;
        }

        return $this->createStoredFile($path);
    }

    /**
     * Datei mit Metadaten ("de"); null, wenn die UUID keine Datei bezeichnet.
     */
    public function getFileInfo(string $binaryUuid): MediaFileInfo|null
    {
        $item = $this->getFileItem($binaryUuid);

        if (null === $item) {
            return null;
        }

        $values = $this->getLocalizedValues($item);

        return new MediaFileInfo(
            $this->toProjectPath($item->getPath()),
            $this->toUuidString($binaryUuid),
            $this->textNormalizer->toPlainText($values[Metadata::VALUE_TITLE]),
            $this->textNormalizer->toPlainText($values[Metadata::VALUE_ALT]),
            $this->textNormalizer->toPlainText($values[Metadata::VALUE_URL]),
            $this->textNormalizer->toPlainText($values[Metadata::VALUE_CAPTION]),
            $this->textNormalizer->toPlainText($values[Metadata::VALUE_LICENSE]),
        );
    }

    /**
     * Pfad der Datei relativ zum Projektverzeichnis; null, wenn es sie nicht gibt.
     */
    public function getProjectPath(string $binaryUuid): string|null
    {
        $item = $this->getFileItem($binaryUuid);

        return null === $item ? null : $this->toProjectPath($item->getPath());
    }

    /**
     * Setzt Bildbeschreibung (title) und Copyright (caption) in der Sprache "de".
     * Alternativtext, Link und Lizenz bleiben erhalten (bisher wurden sie geleert),
     * ebenso die Metadaten anderer Sprachen.
     */
    public function updateMetadata(string $binaryUuid, string $title, string $copyright): void
    {
        $item = $this->getFileItem($binaryUuid);

        if (null === $item) {
            throw MediaException::notFound();
        }

        $path = $item->getPath();
        $extra = $this->filesystem->getExtraMetadata($path);
        $existing = $extra->getLocalized()?->all() ?? [];
        $values = $this->getLocalizedValues($item);

        $existing[self::LOCALE] = new Metadata([
            ...$values,
            Metadata::VALUE_TITLE => $this->textNormalizer->normalize($title),
            Metadata::VALUE_CAPTION => $this->textNormalizer->normalize($copyright),
        ]);

        $extra->setLocalized(new MetadataBag($existing, [self::LOCALE]));
        $this->filesystem->setExtraMetadata($path, $extra);
    }

    /**
     * Löscht die Datei samt Begleitdateien desselben Uploads: Original (_ori) zum
     * Zuschnitt bzw. die frühere verkleinerte Kopie (_sm) einer Galerie-Datei.
     * Die Dateiverwaltung wird dabei vom VFS abgeglichen.
     */
    public function deleteFile(string $binaryUuid): void
    {
        $item = $this->getFileItem($binaryUuid);

        if (null === $item) {
            throw MediaException::notFound();
        }

        $path = $item->getPath();
        $companions = $this->findCompanions($path);

        $this->filesystem->delete($path);

        foreach ($companions as $companion) {
            $this->deleteIfExists($companion);
        }
    }

    /**
     * Begleitdateien im selben Ordner, gleicher Name bis auf Endung und Suffix:
     * zu "x.png" die Dateien "x_ori.*" und "x_sm.*", zu "x_ori.jpg" die Dateien
     * "x_sm.*". Gelesen wird der tatsächliche Ordnerinhalt (auch Dateien, die die
     * Dateiverwaltung noch nicht kennt).
     *
     * @return list<string>
     */
    private function findCompanions(string $path): array
    {
        $directory = Path::getDirectory($path);
        $stem = Path::getFilenameWithoutExtension($path);

        if (str_ends_with($stem, self::SUFFIX_ORIGINAL)) {
            $wanted = [substr($stem, 0, -\strlen(self::SUFFIX_ORIGINAL)).self::SUFFIX_LEGACY_SMALL];
        } else {
            $wanted = [$stem.self::SUFFIX_ORIGINAL, $stem.self::SUFFIX_LEGACY_SMALL];
        }

        $companions = [];

        foreach ($this->filesystem->listContents($directory, false, VirtualFilesystemInterface::BYPASS_DBAFS) as $item) {
            if ($item->isFile() && \in_array(Path::getFilenameWithoutExtension($item->getPath()), $wanted, true)) {
                $companions[] = $item->getPath();
            }
        }

        return $companions;
    }

    private function writeOriginal(string $path, ValidatedImage $original): void
    {
        $stream = fopen($original->temporaryPath, 'r');

        if (false === $stream) {
            throw new \RuntimeException('Die hochgeladene Datei ist nicht lesbar.');
        }

        try {
            $this->filesystem->writeStream($path, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function createMetadata(string $path, string $title, string $copyright): ExtraMetadata
    {
        $extra = $this->filesystem->getExtraMetadata($path);
        $extra->setLocalized(new MetadataBag([
            self::LOCALE => new Metadata([
                Metadata::VALUE_TITLE => $this->textNormalizer->normalize($title),
                Metadata::VALUE_ALT => '',
                Metadata::VALUE_URL => '',
                Metadata::VALUE_CAPTION => $this->textNormalizer->normalize($copyright),
                Metadata::VALUE_LICENSE => '',
            ]),
        ], [self::LOCALE]));

        return $extra;
    }

    /**
     * Metadaten "de" in der bisherigen Schlüsselreihenfolge, fehlende Schlüssel
     * leer; weitere Schlüssel (außer der UUID) bleiben erhalten.
     *
     * @return array<string, string>
     */
    private function getLocalizedValues(FilesystemItem $item): array
    {
        $values = $item->getExtraMetadata()->getLocalized()?->get(self::LOCALE)?->all() ?? [];
        unset($values[Metadata::VALUE_UUID]);

        $result = [];

        foreach ([Metadata::VALUE_TITLE, Metadata::VALUE_ALT, Metadata::VALUE_URL, Metadata::VALUE_CAPTION, Metadata::VALUE_LICENSE] as $key) {
            $result[$key] = \is_scalar($values[$key] ?? null) ? (string) $values[$key] : '';
        }

        foreach ($values as $key => $value) {
            if (!isset($result[$key]) && \is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    private function getFileItem(string $binaryUuid): FilesystemItem|null
    {
        try {
            $uuid = Uuid::fromBinary($binaryUuid);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (!$this->filesystem->fileExists($uuid)) {
            return null;
        }

        $item = $this->filesystem->get($uuid);

        return null !== $item && $item->isFile() ? $item : null;
    }

    /**
     * Name "<präfix>_<40 Hex-Zeichen>", unter dem keine der Dateien mit den
     * angegebenen Endungen existiert (das VFS überschreibt sonst stillschweigend).
     *
     * @param list<string> $suffixes
     */
    private function createUniqueBaseName(string $directory, string $prefix, array $suffixes): string
    {
        do {
            $base = $prefix.'_'.bin2hex(random_bytes(20));
            $taken = false;

            foreach ($suffixes as $suffix) {
                if ($this->filesystem->fileExists($directory.'/'.$base.$suffix, VirtualFilesystemInterface::BYPASS_DBAFS)) {
                    $taken = true;
                }
            }
        } while ($taken);

        return $base;
    }

    private function createStoredFile(string $path): StoredFile
    {
        $uuid = $this->filesystem->get($path)?->getUuid();

        if (null === $uuid) {
            throw new \RuntimeException(\sprintf('Die Datei "%s" fehlt nach dem Speichern in der Dateiverwaltung.', $path));
        }

        return new StoredFile($uuid->toRfc4122(), $this->toProjectPath($path));
    }

    private function deleteIfExists(string $path): void
    {
        if ($this->filesystem->fileExists($path, VirtualFilesystemInterface::BYPASS_DBAFS)) {
            $this->filesystem->delete($path);
        }
    }

    /**
     * Pfad relativ zum Projektverzeichnis ("files/…") in den Pfad innerhalb des
     * VFS umrechnen; nur Pfade unterhalb des Upload-Ordners sind zulässig.
     */
    private function toStoragePath(string $projectPath): string
    {
        $projectPath = Path::canonicalize($projectPath);

        if (!Path::isBasePath($this->uploadPath, $projectPath) || $projectPath === $this->uploadPath) {
            throw MediaException::forbidden(MediaException::MESSAGE_NO_HOME_DIR);
        }

        return Path::makeRelative($projectPath, $this->uploadPath);
    }

    private function toProjectPath(string $storagePath): string
    {
        return Path::join($this->uploadPath, $storagePath);
    }

    private function toUuidString(string $binaryUuid): string
    {
        return Uuid::fromBinary($binaryUuid)->toRfc4122();
    }
}
