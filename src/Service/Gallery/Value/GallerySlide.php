<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Gallery\Value;

use Contao\CoreBundle\Image\Studio\Figure;

/**
 * Eine Folie des Galerie-Karussells.
 *
 * - figure: Bild in der Größe kulturnetz_gallery (bei der Beispielgalerie null),
 * - imageUrl: Quelle des sichtbaren Bildes (img src),
 * - lightboxUrl: Ziel der Lightbox (Größe kulturnetz_lightbox bzw. Beispielbild),
 * - title, caption: Bildbeschreibung und Copyright im Contao-Eingabeformat
 *   (Service\Text\TemplateTextEncoder: Sonderzeichen und Insert-Tag-Klammern als
 *   Entities); das Template maskiert sie mit dem Contao-Escaper ohne
 *   Doppelkodierung.
 */
final class GallerySlide
{
    public function __construct(
        public readonly string $imageUrl,
        public readonly string $lightboxUrl,
        public readonly string $title,
        public readonly string $caption,
        public readonly Figure|null $figure = null,
        public readonly string|null $uuid = null,
    ) {
    }
}
