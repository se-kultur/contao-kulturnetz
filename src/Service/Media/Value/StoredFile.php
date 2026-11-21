<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media\Value;

/**
 * Ergebnis eines Uploads: UUID (Textform mit Bindestrichen, klein geschrieben) und
 * Pfad relativ zum Projektverzeichnis ("files/Mitglieder/user_<id>/<name>").
 *
 * toArray() liefert genau das Element der bisherigen Antwort von upload.php.
 */
final class StoredFile
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $path,
    ) {
    }

    /**
     * @return array{uuid: string, path: string}
     */
    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'path' => $this->path];
    }
}
