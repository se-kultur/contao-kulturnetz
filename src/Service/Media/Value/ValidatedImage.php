<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media\Value;

/**
 * Hochgeladenes Originalbild nach der Inhaltsprüfung: temporärer Pfad, am Inhalt
 * erkannter MIME-Typ und die dazu passende Dateiendung (nicht die des Clients).
 */
final class ValidatedImage
{
    public function __construct(
        public readonly string $temporaryPath,
        public readonly string $mimeType,
        public readonly string $extension,
    ) {
    }
}
