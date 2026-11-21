<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media\Value;

/**
 * Datei mit ihren Metadaten in der Sprache "de" (tl_files.meta), wie der
 * Abrufen-Endpunkt sie ausgibt.
 *
 * Die Texte sind Klartext (ohne HTML-Entities); das Frontend setzt sie per DOM
 * als Feldwerte ein. "caption" ist das Copyright.
 */
final class MediaFileInfo
{
    public function __construct(
        public readonly string $path,
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $alt,
        public readonly string $link,
        public readonly string $caption,
        public readonly string $license,
    ) {
    }

    /**
     * Element der Antwort des Abrufen-Endpunkts (Schlüssel und Reihenfolge wie
     * bisher get.php, ohne den überflüssigen serialisierten Wert "meta").
     *
     * @return array{path: string, uuid: string, title: string, alt: string, link: string, caption: string, license: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'alt' => $this->alt,
            'link' => $this->link,
            'caption' => $this->caption,
            'license' => $this->license,
        ];
    }
}
