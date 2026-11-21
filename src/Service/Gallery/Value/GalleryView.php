<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Gallery\Value;

/**
 * Darstellung einer Profil- oder Veranstaltungsgalerie für das Partial
 * @Contao/kulturnetz/media/_gallery.html.twig.
 *
 * slides enthält die Folien bereits in der Wiederholung, die das Karussell
 * braucht (siehe GalleryViewBuilder). isExample kennzeichnet die Beispielgalerie
 * für Besitzer ohne eigene Bilder; imageCount ist die Zahl der echten Bilder.
 */
final class GalleryView
{
    /**
     * @param list<GallerySlide> $slides
     */
    public function __construct(
        public readonly array $slides,
        public readonly bool $isExample,
        public readonly int $imageCount,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->slides;
    }
}
