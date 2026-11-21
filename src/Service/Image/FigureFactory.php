<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Image;

use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Figure;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\FilesModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Bilder der Profile und Veranstaltungen über das Contao Image Studio mit den
 * benannten Größen des Bundles (ImageSize).
 *
 * Ersetzt Helper\ImageHelper (resize(), avatarPath()). Sonderfälle wie bisher:
 *  - SVG-Dateien werden nicht skaliert, die Originaldatei wird ausgegeben.
 *  - Ohne Bild oder bei fehlender Datei wird auf Wunsch das Ersatzbild
 *    (Parameter kulturnetz.image.placeholder, heute
 *    files/se-kultur.de/img/noavatar.jpg) in derselben Größe ausgegeben.
 *  - Metadaten (Titel, Alternativtext, Bildunterschrift = Copyright) kommen aus
 *    tl_files.meta in der Sprache "de", wie die Templates sie bisher gelesen haben.
 *
 * Quellen sind eine UUID (binär oder als Text, jede UUID-Version), ein Pfad
 * relativ zum Projektverzeichnis oder ein FilesModel.
 */
final class FigureFactory
{
    private const METADATA_LOCALE = 'de';

    private const UNSCALED_EXTENSIONS = ['svg', 'svgz'];

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly Studio $studio,
        private readonly ContaoFramework $framework,
        #[Autowire(param: 'kulturnetz.image.placeholder')]
        private readonly string $placeholderPath,
    ) {
    }

    /**
     * Figure eines Bildes in einer benannten Größe; null, wenn es kein Bild gibt
     * und kein Ersatzbild gewünscht ist (oder auch das Ersatzbild fehlt).
     */
    public function createFigure(FilesModel|string|null $source, ImageSize $size, bool $usePlaceholder = true): Figure|null
    {
        $figure = null;
        $filesModel = $this->resolveFilesModel($source);

        if (null !== $filesModel) {
            $figure = $this->buildFigure($filesModel, $filesModel->path, $size);
        } elseif (\is_string($source) && '' !== $source && !$this->isUuid($source)) {
            // Pfad ohne Eintrag in der Dateiverwaltung (z. B. Theme-Bilder)
            $figure = $this->buildFigure($source, $source, $size);
        }

        if (null === $figure && $usePlaceholder) {
            $figure = $this->buildFigure($this->placeholderPath, $this->placeholderPath, $size);
        }

        return $figure;
    }

    /**
     * Figure des Ersatzbildes in einer benannten Größe.
     */
    public function createPlaceholderFigure(ImageSize $size): Figure|null
    {
        return $this->buildFigure($this->placeholderPath, $this->placeholderPath, $size);
    }

    /**
     * URL (absoluter Pfad, z. B. "/assets/images/...webp") des Bildes in einer
     * benannten Größe, etwa für das href der Lightbox; Regeln wie createFigure().
     */
    public function getImageUrl(FilesModel|string|null $source, ImageSize $size, bool $usePlaceholder = true): string|null
    {
        $src = $this->createFigure($source, $size, $usePlaceholder)?->getImage()->getImageSrc();

        return null === $src || '' === $src ? null : $src;
    }

    /**
     * Metadaten eines Bildes aus tl_files.meta (Sprache "de"); null ohne
     * Dateieintrag oder ohne Metadaten in dieser Sprache.
     */
    public function getMetadata(FilesModel|string|null $source): Metadata|null
    {
        return $this->resolveFilesModel($source)?->getMetadata(self::METADATA_LOCALE);
    }

    /**
     * Pfad des Ersatzbildes relativ zum Projektverzeichnis.
     */
    public function getPlaceholderPath(): string
    {
        return $this->placeholderPath;
    }

    private function buildFigure(FilesModel|string $resource, string $path, ImageSize $size): Figure|null
    {
        $builder = $this->studio
            ->createFigureBuilder()
            ->setLocale(self::METADATA_LOCALE)
        ;

        $builder = $resource instanceof FilesModel ? $builder->fromFilesModel($resource) : $builder->fromPath($resource);

        // SVG wie bisher in Originalgröße ausgeben (ImageHelper hat SVG nicht skaliert).
        $builder->setSize($this->isUnscaled($path) ? null : $size->reference());

        return $builder->buildIfResourceExists();
    }

    private function resolveFilesModel(FilesModel|string|null $source): FilesModel|null
    {
        if (null === $source || '' === $source) {
            return null;
        }

        if ($source instanceof FilesModel) {
            return $source;
        }

        $this->framework->initialize();

        $adapter = $this->framework->getAdapter(FilesModel::class);

        if ($this->isUuid($source)) {
            $model = $adapter->findByUuid($source);
        } else {
            $model = $adapter->findByPath($source);
        }

        return $model instanceof FilesModel && 'file' === $model->type ? $model : null;
    }

    /**
     * UUID binär (16 Byte) oder als Text. Contao\Validator::isUuid() akzeptiert nur
     * UUIDs der Version 1; die früheren Upload-Skripte haben aber Version-4-UUIDs in
     * tl_files geschrieben, deshalb hier jede Version.
     */
    private function isUuid(string $value): bool
    {
        // Binär: 16 Byte, die kein druckbarer Pfad sind (ein Pfad mit genau 16 Zeichen
        // bleibt ein Pfad).
        if (16 === \strlen($value) && 1 !== preg_match('/^[\x20-\x7E]{16}$/', $value)) {
            return true;
        }

        return 1 === preg_match(self::UUID_PATTERN, $value);
    }

    private function isUnscaled(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::UNSCALED_EXTENSIONS, true);
    }
}
