<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Gallery;

use SeKultur\ContaoKulturnetzBundle\Service\Gallery\Value\GallerySlide;
use SeKultur\ContaoKulturnetzBundle\Service\Gallery\Value\GalleryView;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Image\ImageSize;
use SeKultur\ContaoKulturnetzBundle\Service\Media\RecordMediaRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Uid\Uuid;

/**
 * Baut die Galerie der Detailansichten (Profile und Veranstaltungen) aus
 * multiSRC (Entscheidung E25) und ersetzt die fünffach kopierte Logik der
 * Legacy-Templates (mod_artist, mod_host, mod_location, mod_event, mod_sekevent).
 *
 * Verhalten wie bisher:
 *  - Bilder in der Reihenfolge von multiSRC, nur vorhandene Dateien; Bild in der
 *    Größe kulturnetz_gallery (620 x 465, box), Lightbox kulturnetz_lightbox
 *    (Breite 1400), Bildbeschreibung und Copyright aus tl_files.meta ("de").
 *  - Wiederholung für das Karussell (Swiper mit Loop): Bei 5 und mehr Bildern
 *    jedes Bild einmal; bei weniger Bildern werden die Bilder so oft wiederholt
 *    (1 Bild 1-mal, 2 Bilder 3-mal, 3 und 4 Bilder 2-mal), höchstens aber 5 Folien
 *    ausgegeben.
 *  - Besitzer ohne eigene Bilder sehen eine Beispielgalerie mit 5 Folien
 *    ("Beispiel eines Fototitels #1" bis "#5", Copyright "Fotograf").
 */
final class GalleryViewBuilder
{
    /**
     * Wiederholungen bei weniger als MIN_SLIDES Bildern (Bildanzahl => Durchläufe).
     */
    private const LOOPS = [1 => 1, 2 => 3, 3 => 2, 4 => 2];

    private const MIN_SLIDES = 5;

    private const EXAMPLE_SLIDES = 5;

    /**
     * @param string $exampleImage Bild der Beispielgalerie, relativ zum Projektverzeichnis
     */
    public function __construct(
        private readonly RecordMediaRepository $records,
        private readonly FigureFactory $figureFactory,
        private readonly TextNormalizer $textNormalizer,
        private readonly Packages $packages,
        private readonly string $exampleImage = 'files/se-kultur.de/img/beispielfoto.jpg',
    ) {
    }

    /**
     * Galerie eines Datensatzes (Typschlüssel und ID).
     */
    public function buildForRecord(string $type, int $recordId, bool $isOwner): GalleryView
    {
        return $this->build($this->records->findRecord($type, $recordId)?->gallery ?? [], $isOwner);
    }

    /**
     * Galerie aus einer Liste von UUIDs (binär oder Textform) in Anzeigereihenfolge,
     * z. B. der deserialisierte Wert von multiSRC aus einem bereits geladenen
     * Datensatz.
     *
     * @param list<string> $uuids
     */
    public function build(array $uuids, bool $isOwner): GalleryView
    {
        $images = [];

        foreach ($uuids as $uuid) {
            if (null !== ($slide = $this->createSlide($uuid))) {
                $images[] = $slide;
            }
        }

        if ([] === $images) {
            return $isOwner && [] === $uuids
                ? new GalleryView($this->createExampleSlides(), true, 0)
                : new GalleryView([], false, 0);
        }

        return new GalleryView($this->repeat($images), false, \count($images));
    }

    /**
     * @param list<GallerySlide> $images
     *
     * @return list<GallerySlide>
     */
    private function repeat(array $images): array
    {
        $count = \count($images);

        if ($count >= self::MIN_SLIDES) {
            return $images;
        }

        $slides = [];

        for ($i = 0; $i < self::LOOPS[$count]; ++$i) {
            foreach ($images as $image) {
                if (\count($slides) < self::MIN_SLIDES) {
                    $slides[] = $image;
                }
            }
        }

        return $slides;
    }

    private function createSlide(string $uuid): GallerySlide|null
    {
        $figure = $this->figureFactory->createFigure($uuid, ImageSize::Gallery, false);
        $imageUrl = $figure?->getImage()->getImageSrc();

        if (null === $figure || null === $imageUrl || '' === $imageUrl) {
            return null;
        }

        $metadata = $this->figureFactory->getMetadata($uuid);

        return new GallerySlide(
            $imageUrl,
            $this->figureFactory->getImageUrl($uuid, ImageSize::Lightbox, false) ?? $imageUrl,
            $this->textNormalizer->toPlainText($metadata?->getTitle() ?? ''),
            $this->textNormalizer->toPlainText($metadata?->getCaption() ?? ''),
            $figure,
            $this->toUuidString($uuid),
        );
    }

    private function toUuidString(string $uuid): string|null
    {
        try {
            return (16 === \strlen($uuid) ? Uuid::fromBinary($uuid) : Uuid::fromString($uuid))->toRfc4122();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<GallerySlide>
     */
    private function createExampleSlides(): array
    {
        $url = $this->packages->getUrl($this->exampleImage);
        $slides = [];

        for ($i = 1; $i <= self::EXAMPLE_SLIDES; ++$i) {
            $slides[] = new GallerySlide($url, $url, 'Beispiel eines Fototitels #'.$i, 'Fotograf');
        }

        return $slides;
    }
}
