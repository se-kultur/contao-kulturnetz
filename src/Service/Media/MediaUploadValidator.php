<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use Imagine\Image\ImagineInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\ValidatedImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Prüft die Eingaben der Uploads nach Inhalt statt nach Angaben des Clients.
 *
 * - Original: erfolgreich hochgeladen, Größe innerhalb der Grenze aus der
 *   Bundle-Konfiguration (kulturnetz.upload.max_file_size, 0 = nur PHP-Grenzen),
 *   MIME-Typ am Inhalt erkannt (fileinfo) und in kulturnetz.upload.mime_types
 *   enthalten, Bildkopf lesbar (getimagesize) und vom selben Typ. Die Endung der
 *   gespeicherten Datei folgt dem erkannten Typ, nicht dem Dateinamen.
 * - Zuschnitt (Titelbilder): Data-URL "data:image/png;base64,…", gültiges
 *   Base64, ein vollständig lesbares PNG mit genau CROP_SIZE x CROP_SIZE Pixeln
 *   (vom Contao-Bilddienst geöffnet).
 * - Texte: Copyright ist Pflicht. Beide Texte höchstens TEXT_MAX_LENGTH Zeichen;
 *   die engere Grenze des Formulars (maxlength 50 bei der Bildbeschreibung) gilt
 *   nur im Browser, damit ältere, längere Bestandswerte weiter speicherbar sind.
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

    public function __construct(
        private readonly KulturnetzConfig $config,
        #[Autowire(service: 'contao.image.imagine')]
        private readonly ImagineInterface $imagine,
    ) {
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
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => MediaException::MESSAGE_FILE_TOO_LARGE,
                \UPLOAD_ERR_NO_FILE => MediaException::MESSAGE_NO_FILE,
                default => MediaException::MESSAGE_INVALID_INPUT,
            });
        }

        $maxSize = $this->config->getUploadMaxFileSize();
        $size = $file->getSize();

        if (null !== $maxSize && (false === $size || $size > $maxSize)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_FILE_TOO_LARGE);
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

        return trim($copyright);
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

    private function isAllowedMimeType(string $mimeType): bool
    {
        return isset(self::KNOWN_TYPES[$mimeType]) && \in_array($mimeType, $this->config->getUploadMimeTypes(), true);
    }
}
