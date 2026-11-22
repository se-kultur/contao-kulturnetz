<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\Image\DeferredImageInterface;
use Contao\Image\DeferredResizerInterface;
use Contao\Image\ImageInterface;
use Contao\Image\ResizeConfiguration;
use Contao\Image\ResizeOptions;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Bereitet Bilder für den PDF-Export auf: verkleinert sie auf die Druckgröße
 * und liefert sie als JPEG.
 *
 * Bisher wurden die Originale eingebettet (Titelbilder der Veranstaltungen:
 * 800 x 800 Pixel PNG mit Alphakanal). Die PDF-Engine trennt den Alphakanal
 * Pixel für Pixel in PHP ab und speichert Bild und Maske verlustfrei; das PDF der
 * SE-KulturTage wurde dadurch rund 61 MB groß und brauchte über 30 Sekunden.
 *
 * Ablauf je Bild:
 *  1. Verkleinern über die Bildverarbeitung von Contao (contao.image.factory,
 *     Modus "box", kein Zuschnitt). Das Ergebnis liegt im Bild-Cache von Contao
 *     (assets/images) und wird bei weiteren Exporten wiederverwendet.
 *  2. Ist das Ergebnis kein JPEG, wird es auf weißen Grund gelegt (wie auf der
 *     weißen PDF-Seite) und als JPEG in das Arbeitsverzeichnis des Exports
 *     geschrieben. JPEG kennt keine Transparenz; ohne weißen Grund erschienen
 *     transparente Bereiche je nach Bildbibliothek schwarz.
 *
 * Vektorgrafiken (SVG) und Bilder, die sich nicht verarbeiten lassen, werden
 * unverändert eingebettet wie bisher; Letzteres wird im Contao-Systemlog
 * vermerkt.
 */
final class PdfImageProcessor
{
    private const JPEG_QUALITY = 90;

    /**
     * Kompression der verkleinerten PNG-Zwischenstufe im Bild-Cache (0 bis 9).
     * Sie wird nur hier gelesen; Stufe 4 ist verlustfrei wie jede Stufe, rund
     * fünfmal schneller als die Voreinstellung von ImageMagick und nur etwa 5 %
     * größer (gemessen an einem Titelbild 800 x 800 auf 700 x 700 Pixel).
     */
    private const PNG_COMPRESSION_LEVEL = 4;

    private const JPEG_EXTENSIONS = ['jpg', 'jpeg'];

    private const UNSCALED_EXTENSIONS = ['svg', 'svgz'];

    public function __construct(
        #[Autowire(service: 'contao.image.factory')]
        private readonly ImageFactoryInterface $imageFactory,
        #[Autowire(service: 'contao.image.resizer')]
        private readonly DeferredResizerInterface $resizer,
        #[Autowire(param: 'contao.image.target_dir')]
        private readonly string $imageCacheDir,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(service: 'monolog.logger.contao.error')]
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Verzeichnisse, aus denen die PDF-Engine Bilder lesen darf, die diese Klasse
     * liefert (Bild-Cache von Contao und das Arbeitsverzeichnis des Exports).
     *
     * @return list<string>
     */
    public function getReadablePaths(string $workDir): array
    {
        return [$this->imageCacheDir, $workDir];
    }

    /**
     * Legt ein leeres Arbeitsverzeichnis für einen Export an.
     */
    public function createWorkDir(): string
    {
        $dir = Path::join(sys_get_temp_dir(), 'kulturnetz-pdf-'.bin2hex(random_bytes(8)));
        $this->filesystem->mkdir($dir, 0o700);

        return $dir;
    }

    public function removeWorkDir(string $workDir): void
    {
        $this->filesystem->remove($workDir);
    }

    /**
     * Absoluter Pfad des aufbereiteten Bildes (höchstens $pixels x $pixels Pixel,
     * Seitenverhältnis erhalten), bei Vektorgrafiken oder Fehlern der Pfad des
     * Originals.
     */
    public function prepare(string $absolutePath, int $pixels, string $workDir): string
    {
        if (\in_array(strtolower(Path::getExtension($absolutePath)), self::UNSCALED_EXTENSIONS, true)) {
            return $absolutePath;
        }

        try {
            $image = $this->resize($absolutePath, $pixels);

            if (\in_array(strtolower(Path::getExtension($image->getPath())), self::JPEG_EXTENSIONS, true)) {
                return $image->getPath();
            }

            return $this->flattenToJpeg($image, $workDir);
        } catch (\Throwable $exception) {
            $this->logger->warning(\sprintf('PDF-Export: Bild "%s" konnte nicht verkleinert werden und wird unverändert eingebettet (%s).', Path::makeRelative($absolutePath, $this->projectDir), $exception->getMessage()));

            return $absolutePath;
        }
    }

    private function resize(string $absolutePath, int $pixels): ImageInterface
    {
        $config = (new ResizeConfiguration())
            ->setWidth($pixels)
            ->setHeight($pixels)
            ->setMode(ResizeConfiguration::MODE_BOX)
        ;

        $options = (new ResizeOptions())->setImagineOptions([
            'jpeg_quality' => self::JPEG_QUALITY,
            'png_compression_level' => self::PNG_COMPRESSION_LEVEL,
        ]);

        $image = $this->imageFactory->create($absolutePath, $config, $options);

        // Contao verkleinert verzögert (erst beim Abruf der Bild-URL); die PDF-Engine
        // braucht die Datei sofort.
        if ($image instanceof DeferredImageInterface) {
            $image = $this->resizer->resizeDeferredImage($image) ?? throw new \RuntimeException('Das verkleinerte Bild wurde nicht erzeugt.');
        }

        return $image;
    }

    private function flattenToJpeg(ImageInterface $image, string $workDir): string
    {
        $imagine = $image->getImagine();
        $source = $imagine->open($image->getPath());

        // Kein usePalette() für Graustufenbilder: Imagine rechnet dabei über
        // ICC-Profile um, das hellt die Bilder sichtbar auf (gemessen an einem
        // Schwarz-Weiß-Foto: mittlere Helligkeit 0,60 statt 0,50). Beim Einfügen in
        // die RGB-Fläche übernimmt die Bildbibliothek die Grauwerte unverändert.
        $canvas = $imagine->create($source->getSize(), (new RGB())->color('#ffffff', 100));
        $canvas->paste($source, new Point(0, 0));

        $target = Path::join($workDir, hash('xxh128', $image->getPath()).'.jpg');
        $canvas->save($target, ['format' => 'jpeg', 'jpeg_quality' => self::JPEG_QUALITY]);

        return $target;
    }
}
