<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media\Value;

/**
 * Datensatz (Profil oder Veranstaltung) mit den Feldern, die die Datei-Endpunkte
 * brauchen: Besitzer, Schreibschutz, Titelbild und Galerie.
 *
 * Die Galerie ist die Liste der binären UUIDs aus multiSRC in gespeicherter
 * Reihenfolge (Entscheidung E25: multiSRC ist die einzige Quelle der Reihenfolge).
 */
final class MediaRecord
{
    /**
     * @param list<string> $gallery binäre UUIDs (je 16 Byte)
     */
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly int $memberId,
        public readonly bool $readonly,
        public readonly string|null $avatar,
        public readonly array $gallery,
    ) {
    }

    public function table(): string
    {
        return 'tl_'.$this->type;
    }

    public function isOwnedBy(int $memberId): bool
    {
        return $memberId > 0 && $this->memberId === $memberId;
    }

    public function hasAvatar(string $binaryUuid): bool
    {
        return null !== $this->avatar && $this->avatar === $binaryUuid;
    }

    public function hasGalleryImage(string $binaryUuid): bool
    {
        return \in_array($binaryUuid, $this->gallery, true);
    }
}
