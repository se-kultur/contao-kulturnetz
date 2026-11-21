<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Image;

/**
 * Die benannten Bildgrößen des Bundles (config/prepend/image_sizes.yaml,
 * Contao-Konfiguration contao.image.sizes).
 *
 * Abgeleitet aus allen bisherigen Aufrufen von Helper\ImageHelper::resize() in den
 * Bundle-Templates: gleiche Maße und gleicher Modus ("proportional" rechnete
 * ImageHelper bereits als "box"), Ausgabe immer als WebP mit Qualität 80.
 */
enum ImageSize: string
{
    /** 272 × 272, crop: Vorschaubilder in Listen und Verzeichnissen (Profile, Veranstaltungen). */
    case List = 'kulturnetz_list';

    /** 700 × 700, crop: Titelbild in der Seitenleiste der Detailansichten. */
    case Detail = 'kulturnetz_detail';

    /** Breite 1400, box: Bild in der Lightbox (Titelbild und Galerie). */
    case Lightbox = 'kulturnetz_lightbox';

    /** 620 × 465, box: Galeriebilder im Slider der Detailansichten. */
    case Gallery = 'kulturnetz_gallery';

    /** 120 × 120, crop: kleine Profilbilder (Beiträge, Follower, verknüpfte Profile). */
    case Avatar = 'kulturnetz_avatar';

    /**
     * Größenangabe für Contao (Studio, Twig-Funktion figure()): Namen aus der
     * Konfiguration werden mit führendem Unterstrich referenziert.
     */
    public function reference(): string
    {
        return '_'.$this->value;
    }
}
