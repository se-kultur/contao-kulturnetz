<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use Imagine\Image\ImagineInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\ValidatedImage;
use SeKultur\ContaoKulturnetzBundle\Service\Write\FormMessages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Prüft die Eingaben der Uploads nach Inhalt statt nach Angaben des Clients.
 *
 * - Original: erfolgreich hochgeladen, Größe innerhalb der wirksamen Grenze
 *   (getMaxFileSize(): kleinster Wert aus kulturnetz.upload.max_file_size,
 *   upload_max_filesize und post_max_size; Meldung nennt die Grenze),
 *   MIME-Typ am Inhalt erkannt (fileinfo) und in kulturnetz.upload.mime_types
 *   enthalten, Bildkopf lesbar (getimagesize) und vom selben Typ. Die Endung der
 *   gespeicherten Datei folgt dem erkannten Typ, nicht dem Dateinamen. Daten
 *   hinter dem Bildende (JPEG EOI, PNG IEND, GIF-Trailer, WebP-RIFF-Länge) werden
 *   ohne Neukodierung abgeschnitten; fehlt das Ende, wird die Datei abgelehnt.
 * - Zuschnitt (Titelbilder): Data-URL "data:image/png;base64,…", gültiges
 *   Base64, ein vollständig lesbares PNG mit genau CROP_SIZE x CROP_SIZE Pixeln
 *   (vom Contao-Bilddienst geöffnet).
 * - Texte: Copyright ist Pflicht. Beide Texte höchstens TEXT_MAX_LENGTH Zeichen;
 *   die engere Grenze des Formulars (maxlength 50 bei der Bildbeschreibung) gilt
 *   nur im Browser, damit ältere, längere Bestandswerte weiter speicherbar sind.
 *   Beide Texte sind Klartext (die Ausgabe maskiert sie): HTML-Tags wie
 *   <a href="…"> werden mit Meldung abgelehnt, statt später als sichtbare Tags zu
 *   erscheinen. Ein einzelnes "<" ohne Tag (z. B. "<3") bleibt zulässig.
 */
final class MediaUploadValidator
{
    /**
     * Kantenlänge des quadratischen Zuschnitts in Pixeln (wie bisher Croppie).
     */
    public const CROP_SIZE = 800;

    public const TEXT_MAX_LENGTH = 255;

    /**
     * Am Inhalt erkannter Typ => Dateiendung und Bildtyp von getimagesize().
     */
    private const KNOWN_TYPES = [
        'image/jpeg' => ['jpg', \IMAGETYPE_JPEG],
        'image/png' => ['png', \IMAGETYPE_PNG],
        'image/gif' => ['gif', \IMAGETYPE_GIF],
        'image/webp' => ['webp', \IMAGETYPE_WEBP],
    ];

    private const CROP_DATA_URL_PREFIX = 'data:image/png;base64,';

    /**
     * Öffnendes oder schließendes HTML-Tag (wie es ein Browser als Tag liest).
     */
    private const HTML_TAG_PATTERN = '#</?[a-z][^>]*>#i';

    private const BYTES_PER_MEGABYTE = 1024 * 1024;

    /**
     * Bildende fehlt oder Aufbau defekt (Stil der übrigen Upload-Meldungen).
     */
    public const MESSAGE_DAMAGED = 'Die gewählte Datei ist beschädigt oder unvollständig. Bitte wählen Sie eine andere Datei aus.';

    public function __construct(
        private readonly KulturnetzConfig $config,
        #[Autowire(service: 'contao.image.imagine')]
        private readonly ImagineInterface $imagine,
        private readonly FormMessages $messages,
    ) {
    }

    /**
     * Wirksame Größengrenze einer Uploaddatei in Byte: die Bundle-Grenze, höchstens
     * aber die PHP-Grenzen upload_max_filesize und post_max_size (größere Dateien
     * erreichen den Server gar nicht).
     */
    public function getMaxFileSize(): int
    {
        $phpLimit = (int) min(UploadedFile::getMaxFilesize(), \PHP_INT_MAX);

        return min($this->config->getUploadMaxFileSize() ?? $phpLimit, $phpLimit);
    }

    /**
     * Größengrenze für Formular und Meldungen, z. B. "20 MB".
     */
    public function getMaxFileSizeLabel(): string
    {
        $bytes = $this->getMaxFileSize();

        if (0 === $bytes % self::BYTES_PER_MEGABYTE) {
            return number_format(intdiv($bytes, self::BYTES_PER_MEGABYTE), 0, ',', '.').' MB';
        }

        return number_format($bytes / self::BYTES_PER_MEGABYTE, 1, ',', '.').' MB';
    }

    /**
     * Meldung "Datei zu groß" mit der wirksamen Grenze.
     */
    public function getFileTooLargeMessage(): string
    {
        return $this->messages->get('kulturnetz_file_too_large', [$this->getMaxFileSizeLabel()]);
    }

    /**
     * Prüft ein hochgeladenes Original.
     *
     * @throws MediaException 422 bei fehlender, zu großer oder unzulässiger Datei
     */
    public function validateImage(mixed $file): ValidatedImage
    {
        if (!$file instanceof UploadedFile) {
            throw MediaException::unprocessable(MediaException::MESSAGE_NO_FILE);
        }

        if (!$file->isValid()) {
            throw MediaException::unprocessable(match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => $this->getFileTooLargeMessage(),
                \UPLOAD_ERR_NO_FILE => MediaException::MESSAGE_NO_FILE,
                default => MediaException::MESSAGE_INVALID_INPUT,
            });
        }

        $size = $file->getSize();

        if (false === $size || $size > $this->getMaxFileSize()) {
            throw MediaException::unprocessable($this->getFileTooLargeMessage());
        }

        $path = $file->getPathname();
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($path);

        if (!\is_string($mimeType) || !$this->isAllowedMimeType($mimeType)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_FILE_TYPE);
        }

        [$extension, $imageType] = self::KNOWN_TYPES[$mimeType];
        $info = @getimagesize($path);

        if (false === $info || $info[2] !== $imageType || $info[0] < 1 || $info[1] < 1) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_FILE_TYPE);
        }

        $this->truncateTrailingData($path, $mimeType);

        return new ValidatedImage($path, $mimeType, $extension);
    }

    /**
     * Dekodiert und prüft den Zuschnitt eines Titelbildes; liefert die PNG-Daten.
     *
     * @throws MediaException 422 bei fehlendem oder ungültigem Zuschnitt
     */
    public function decodeCroppedImage(mixed $dataUrl): string
    {
        if (!\is_string($dataUrl) || !str_starts_with($dataUrl, self::CROP_DATA_URL_PREFIX)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $binary = base64_decode(substr($dataUrl, \strlen(self::CROP_DATA_URL_PREFIX)), true);

        if (false === $binary || '' === $binary) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $info = @getimagesizefromstring($binary);

        if (false === $info || \IMAGETYPE_PNG !== $info[2] || self::CROP_SIZE !== $info[0] || self::CROP_SIZE !== $info[1]) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        // Daten hinter dem PNG-Ende gehören nicht zum Bild.
        $binary = substr($binary, 0, $this->findImageEnd($binary, 'image/png'));

        // Vollständig lesbar? (Kopf allein beweist kein gültiges Bild.)
        try {
            $size = $this->imagine->load($binary)->getSize();
        } catch (\Throwable) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        if (self::CROP_SIZE !== $size->getWidth() || self::CROP_SIZE !== $size->getHeight()) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        return $binary;
    }

    /**
     * Bildbeschreibung (optional) als getrimmter Klartext.
     */
    public function validateTitle(mixed $title): string
    {
        if (null === $title) {
            return '';
        }

        if (!\is_string($title) || mb_strlen(trim($title)) > self::TEXT_MAX_LENGTH) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $this->assertPlainText($title);

        return trim($title);
    }

    /**
     * Copyright (Pflichtfeld) als getrimmter Klartext.
     */
    public function validateCopyright(mixed $copyright): string
    {
        if (!\is_string($copyright) || '' === trim($copyright)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_COPYRIGHT_MISSING);
        }

        if (mb_strlen(trim($copyright)) > self::TEXT_MAX_LENGTH) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $this->assertPlainText($copyright);

        return trim($copyright);
    }

    /**
     * @throws MediaException 422, wenn der Text ein HTML-Tag enthält
     */
    private function assertPlainText(string $text): void
    {
        if (1 === preg_match(self::HTML_TAG_PATTERN, $text)) {
            throw MediaException::unprocessable($this->messages->get('kulturnetz_media_plain_text'));
        }
    }

    /**
     * Zulässige MIME-Typen für die Prüfung im Browser (gleiche Liste wie hier).
     *
     * @return list<string>
     */
    public function getAllowedMimeTypes(): array
    {
        return array_values(array_filter($this->config->getUploadMimeTypes(), $this->isAllowedMimeType(...)));
    }

    /**
     * Schneidet Daten hinter dem Ende des Bildes ab (z. B. angehängter PHP-Code
     * oder ein Archiv). Das Bild selbst bleibt Byte für Byte erhalten, also auch
     * Pixel, EXIF-Daten und Orientierung; neu kodiert wird nichts.
     *
     * Abschneiden statt Ablehnen, weil auch harmlose Dateien Anhänge tragen
     * (Bewegungsfotos mancher Smartphones hängen ein Video an, MPO-Dateien ein
     * zweites Bild); Mitglieder sollen solche Fotos weiter hochladen können.
     *
     * @throws MediaException 422, wenn das Bildende fehlt oder der Aufbau defekt ist
     */
    private function truncateTrailingData(string $path, string $mimeType): void
    {
        $data = file_get_contents($path);

        if (false === $data) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $end = $this->findImageEnd($data, $mimeType);

        if ($end === \strlen($data)) {
            return;
        }

        $handle = fopen($path, 'r+b');

        if (false === $handle) {
            throw new \RuntimeException(\sprintf('Upload "%s" konnte nicht zum Kürzen geöffnet werden.', $path));
        }

        try {
            if (!ftruncate($handle, $end)) {
                throw new \RuntimeException(\sprintf('Upload "%s" konnte nicht gekürzt werden.', $path));
            }
        } finally {
            fclose($handle);
            clearstatcache(true, $path);
        }
    }

    /**
     * Länge des Bildes in Byte bis einschließlich seiner Endmarke.
     *
     * @throws MediaException 422, wenn das Bildende fehlt oder der Aufbau defekt ist
     */
    private function findImageEnd(string $data, string $mimeType): int
    {
        $end = match ($mimeType) {
            'image/jpeg' => $this->findJpegEnd($data),
            'image/png' => $this->findPngEnd($data),
            'image/gif' => $this->findGifEnd($data),
            'image/webp' => $this->findWebpEnd($data),
            default => null,
        };

        if (null === $end) {
            throw MediaException::unprocessable(self::MESSAGE_DAMAGED);
        }

        return $end;
    }

    /**
     * JPEG: Segmente ab SOI bis EOI (FFD9). Nach SOS folgen Bilddaten, in denen FF
     * nur maskiert (FF00) oder als Restart-Marke (FFD0 bis FFD7) vorkommt; die
     * nächste andere Marke beendet sie. Eingebettete Vorschaubilder liegen in
     * APP-Segmenten mit Längenangabe und werden übersprungen.
     */
    private function findJpegEnd(string $data): int|null
    {
        $length = \strlen($data);

        if ($length < 4 || "\xFF\xD8" !== substr($data, 0, 2)) {
            return null;
        }

        $pos = 2;

        while ($pos < $length) {
            if ("\xFF" !== $data[$pos]) {
                return null;
            }

            // Füllbytes FF vor der Marke
            while ($pos + 1 < $length && "\xFF" === $data[$pos + 1]) {
                ++$pos;
            }

            if ($pos + 1 >= $length) {
                return null;
            }

            $marker = \ord($data[$pos + 1]);

            if (0xD9 === $marker) {
                return $pos + 2;
            }

            if (0x01 === $marker || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $pos += 2;

                continue;
            }

            if (0x00 === $marker || 0xD8 === $marker || $pos + 4 > $length) {
                return null;
            }

            $segmentLength = unpack('n', $data, $pos + 2)[1];

            if ($segmentLength < 2 || $pos + 2 + $segmentLength > $length) {
                return null;
            }

            $pos += 2 + $segmentLength;

            if (0xDA !== $marker) {
                continue;
            }

            // Bilddaten nach SOS bis zur nächsten Marke
            while (true) {
                $next = strpos($data, "\xFF", $pos);

                if (false === $next || $next + 1 >= $length) {
                    return null;
                }

                $byte = \ord($data[$next + 1]);

                if (0x00 === $byte || ($byte >= 0xD0 && $byte <= 0xD7)) {
                    $pos = $next + 2;

                    continue;
                }

                if (0xFF === $byte) {
                    $pos = $next + 1;

                    continue;
                }

                $pos = $next;

                break;
            }
        }

        return null;
    }

    /**
     * PNG: Signatur, dann Chunks (Länge, Typ, Daten, CRC) bis einschließlich IEND.
     */
    private function findPngEnd(string $data): int|null
    {
        $length = \strlen($data);

        if ("\x89PNG\r\n\x1A\n" !== substr($data, 0, 8)) {
            return null;
        }

        $pos = 8;

        while ($pos + 12 <= $length) {
            $chunkLength = unpack('N', $data, $pos)[1];
            $chunkEnd = $pos + 12 + $chunkLength;

            if ($chunkLength > 0x7FFFFFFF || $chunkEnd > $length) {
                return null;
            }

            if ('IEND' === substr($data, $pos + 4, 4)) {
                return $chunkEnd;
            }

            $pos = $chunkEnd;
        }

        return null;
    }

    /**
     * GIF: Kopf, globale Farbtabelle, dann Bild- und Erweiterungsblöcke bis zum
     * Trailer (3B).
     */
    private function findGifEnd(string $data): int|null
    {
        $length = \strlen($data);

        if ($length < 13 || !\in_array(substr($data, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }

        $pos = 13 + $this->gifColorTableSize(\ord($data[10]));

        while ($pos < $length) {
            $block = \ord($data[$pos]);

            if (0x3B === $block) {
                return $pos + 1;
            }

            if (0x2C === $block) {
                if ($pos + 11 > $length) {
                    return null;
                }

                // Bildbeschreibung (10 Byte), lokale Farbtabelle, LZW-Mindestcodegröße
                $pos += 10 + $this->gifColorTableSize(\ord($data[$pos + 9])) + 1;
            } elseif (0x21 === $block) {
                // Erweiterung: Einleitung und Kennung, dann Unterblöcke
                $pos += 2;
            } else {
                return null;
            }

            // Unterblöcke bis zum Block der Länge 0
            while (true) {
                if ($pos >= $length) {
                    return null;
                }

                $size = \ord($data[$pos]);
                ++$pos;

                if (0 === $size) {
                    break;
                }

                $pos += $size;
            }
        }

        return null;
    }

    private function gifColorTableSize(int $flags): int
    {
        return 0x80 === ($flags & 0x80) ? 3 * (2 ** (($flags & 0x07) + 1)) : 0;
    }

    /**
     * WebP: RIFF-Container, dessen Längenangabe das Ende bestimmt.
     */
    private function findWebpEnd(string $data): int|null
    {
        if (\strlen($data) < 12 || 'RIFF' !== substr($data, 0, 4) || 'WEBP' !== substr($data, 8, 4)) {
            return null;
        }

        $end = 8 + unpack('V', $data, 4)[1];

        return $end <= \strlen($data) ? $end : null;
    }

    private function isAllowedMimeType(string $mimeType): bool
    {
        return isset(self::KNOWN_TYPES[$mimeType]) && \in_array($mimeType, $this->config->getUploadMimeTypes(), true);
    }
}
