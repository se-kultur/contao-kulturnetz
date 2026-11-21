<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Art eines Mitglieder-Uploads (Feld "upload" der Uploader-Formulare).
 *
 * - avatar: Profilbild der Profile (artists, hosts, locations), quadratisch
 *   zugeschnitten.
 * - event: Titelbild der Veranstaltungen (events, sekevents), quadratisch
 *   zugeschnitten.
 * - gallery: Bild der Galerie eines Datensatzes (alle Typen), ohne Zuschnitt.
 *
 * Der Wert ist zugleich das Präfix des Dateinamens (avatar_<hash>.png,
 * event_<hash>.png, gallery_<hash>_ori.<endung>).
 */
enum MediaType: string
{
    case Avatar = 'avatar';
    case Event = 'event';
    case Gallery = 'gallery';

    /**
     * True für die zugeschnittenen Titelbilder (avatar, event): Neben dem
     * Original wird der quadratische Zuschnitt als eigentliche Datei gespeichert.
     */
    public function isCropped(): bool
    {
        return self::Gallery !== $this;
    }

    /**
     * Datensatztypen, zu denen diese Upload-Art passt.
     *
     * @return list<string>
     */
    public function allowedRecordTypes(): array
    {
        return match ($this) {
            self::Avatar => KulturnetzConfig::PROFILE_TYPES,
            self::Event => ['events', 'sekevents'],
            self::Gallery => KulturnetzConfig::TYPES,
        };
    }
}
