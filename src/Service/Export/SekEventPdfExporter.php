<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Com\Tecnick\Pdf\Tcpdf;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF-Export der SE-KulturTage-Veranstaltungen (bisher pdfEvents.php, Button
 * "PDF-Download" im Backend-Modul "Events"), eine Seite je Veranstaltung.
 *
 * Aufbau wie bisher: links Titelbild, Bildtitel, Copyright, Links
 * (Veranstaltung, Bild, Original), Termine und Status ("Nicht freigeschaltet"
 * in Rot), rechts Name, Künstler:innen, Reihe, Kulturform, Zielgruppe,
 * Anmeldung, Eintritt, Location, Veranstalter, Kurzbeschreibung, Kooperation.
 * Schrift DejaVu Sans 11 pt, A4 hoch, Ränder 10 mm, unten 25 mm.
 *
 * Unterschiede zu pdfEvents.php:
 *  - PDF-Engine tecnickcom/tc-lib-pdf (Nachfolger von TCPDF, Entscheidung E23),
 *    Schriftdaten liegen im Bundle (Resources/fonts).
 *  - Bilder werden aus dem lokalen Dateisystem gelesen statt per HTTP von
 *    https://se-kultur.de; Links zeigen auf die aktuelle Website.
 *  - Der Link "Original downloaden" führt auf die tatsächlich vorhandene
 *    Originaldatei (<name>_ori mit echter Endung, bei Altbeständen meist .png);
 *    fehlt sie, entfällt der Link.
 *  - Texte werden escaped ausgegeben (kein HTML aus Nutzereingaben im PDF).
 *    Kodiert wird nach HTML 4.01 ("&#039;" statt "&apos;"), weil die PDF-Engine
 *    Entities mit html_entity_decode(..., ENT_QUOTES) auflöst und "&apos;" sonst
 *    als Text erschiene.
 *  - Titelbilder werden auf die Druckgröße verkleinert und als JPEG eingebettet
 *    (PdfImageProcessor) statt im Original; Links "Bild downloaden" und
 *    "Original downloaden" führen weiter auf die Originaldateien.
 *  - Ohne Veranstaltungen entsteht eine Seite mit Hinweis statt eines Fehlers.
 */
final class SekEventPdfExporter
{
    private const FONT = 'dejavusans';

    private const FONT_SIZE = 11;

    /**
     * Kantenlänge des Titelbildes in Pixeln bei 96 dpi: 224 px entsprechen den
     * 59,3 mm, die TCPDF bisher aus "210" mit Bildskalierung 1,25 errechnete.
     */
    private const IMAGE_SIZE = 224;

    /**
     * Auflösung der eingebetteten Titelbilder: 224 px bei 96 dpi ergeben 59,3 mm,
     * bei 300 dpi Druckauflösung sind das 700 Pixel Kantenlänge.
     */
    private const PRINT_DPI = 300;

    public function __construct(
        private readonly SekEventExportData $data,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly FigureFactory $figureFactory,
        private readonly PdfImageProcessor $imageProcessor,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function createResponse(): Response
    {
        $now = $this->clock->now()->getTimestamp();
        $filename = date('Ymd', $now).'_SE-KulturTage-Events.pdf';

        $response = new Response($this->render($now));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function render(int|null $now = null): string
    {
        $now ??= $this->clock->now()->getTimestamp();
        $this->defineFontPath();

        $workDir = $this->imageProcessor->createWorkDir();

        try {
            return $this->renderPdf($now, $workDir);
        } finally {
            $this->imageProcessor->removeWorkDir($workDir);
        }
    }

    private function renderPdf(int $now, string $workDir): string
    {
        $pdf = new Tcpdf('mm', true, true, true, '', null, [
            'allowedPaths' => [$this->fontPath()],
            'markupAllowedPaths' => [Path::join($this->projectDir, 'files'), ...$this->imageProcessor->getReadablePaths($workDir)],
        ]);

        $pdf->setCreator('SE-KulturNetz');
        $pdf->setAuthor('SE-KulturNetz');
        $pdf->setTitle('SE-KulturTage Events');
        $pdf->setSubject('SE-KulturTage Events');

        // Abstand vor Zwischenüberschriften wie im früheren TCPDF-Layout (eine Zeile)
        $pdf->setHtmlVSpace(['h4' => [['n' => 1], ['n' => 0]]]);

        $font = $pdf->font->insert($pdf->pon, self::FONT, '', self::FONT_SIZE);
        $events = $this->data->load($now);

        if ([] === $events) {
            $pdf->addPage($this->pageSettings());
            $pdf->page->addContent($font['out']);
            $pdf->addHTMLCell('<p>Keine SE-KulturTage Events vorhanden.</p>', 10, 10, 190);
        }

        foreach ($events as $event) {
            $pdf->addPage($this->pageSettings());
            $pdf->page->addContent($font['out']);
            $pdf->addHTMLCell($this->eventHtml($event, $now, $workDir), 10, 10, 190);
        }

        return $pdf->getOutPDFString();
    }

    private function eventHtml(array $event, int $now, string $workDir): string
    {
        $text = fn (mixed $value): string => $this->escape($this->data->text($value));
        $orDash = static fn (string $html): string => '' !== $html ? $html : '&mdash;';

        $image = $this->findImage($event);
        $eventUrl = $this->pageUrlResolver->getDetailUrl('sekevents', (string) $event['alias']) ?? '';

        $termine = '';

        foreach ($this->data->getDates($event, $now) as $date) {
            if ('' !== $date['comment']) {
                $termine .= '<span style="font-size: 10px; line-height: 10px">'.$this->escape($date['comment']).':</span><br>';
            }

            $termine .= $this->escape($date['date'].', '.$date['start'].' - '.$date['end'].' Uhr').'<br>';
        }

        $eintritt = 'Eintritt frei';

        if ('1' !== (string) $event['eintrittfrei']) {
            $eintritt = '';

            foreach ($this->data->getPriceLines($event) as $line) {
                $eintritt .= $this->escape($line).'<br>';
            }
        }

        $status = '1' === (string) $event['disable'] ? '<span style="color:red">Nicht freigeschaltet</span>' : 'Freigeschaltet';

        $links = '<a href="'.$this->escape($eventUrl).'">Veranstaltungslink</a><br>';

        if (null !== $image['url']) {
            $links .= '<a href="'.$this->escape($image['url']).'">Bild downloaden</a><br>';
        }

        if (null !== $image['originalUrl']) {
            $links .= '<a href="'.$this->escape($image['originalUrl']).'">Original downloaden</a>';
        }

        return '<table><tr>'
            .'<td width="33%">'
            .'<img src="'.$this->escape($this->imageProcessor->prepare($image['file'], $this->printPixels(), $workDir)).'" width="'.self::IMAGE_SIZE.'" height="'.self::IMAGE_SIZE.'">'
            .'<h4>Bildtitel:</h4>'.$this->metaHtml($image['title']).'<br>'
            .'<h4>Copyright:</h4>'.$this->metaHtml($image['copyright']).'<br>'
            .'<br>'.$links.'<br><br>'
            .'<h4>Termin-Daten</h4><br>'.$termine.'<br><br>'
            .'<h4>Status</h4><br>'.$status.'<br>'
            .'</td>'
            .'<td width="4%"></td>'
            .'<td width="63%">'
            .'<h2>'.$text($event['name']).'</h2>'
            .'<h4>'.$this->escape($this->joinNames($this->data->text($event['artists']), $this->data->getLinkedNames($event, 'artists'))).'</h4>'
            .'<table>'
            .'<tr><td width="120">Reihe:</td><td>'.$text($event['sparten']).'</td></tr>'
            .'<tr><td>Kulturform:</td><td>'.$text($event['kulturform']).'</td></tr>'
            .'<tr><td>Zielgruppe:</td><td>'.$text($event['zielgruppen']).'</td></tr>'
            .'<tr><td>Anmeldung?</td><td>'.('1' === (string) $event['anmeldung'] ? 'Ja' : 'Nein').'</td></tr>'
            .'<tr><td>Anmeldestelle:</td><td>'.$orDash($text($event['anmeldestelle'])).'</td></tr>'
            .'<tr><td colspan="2"></td></tr>'
            .'</table>'
            .'<h4>Eintritt</h4><br>'.$eintritt.'<br>'
            .'<h4>Location</h4><br>'
            .$this->escape($this->joinNames($this->data->text($event['locations']), $this->data->getLinkedNames($event, 'locations'))).'<br>'
            .$text($event['location_adresse']).'<br>'
            .$text($event['location_plz']).' '.$text($event['location_ort']).'<br>'
            .$text($event['location_telefon']).'<br>'
            .$text($event['location_email']).'<br>'
            .$text($event['location_website']).'<br>'
            .'<h4>Veranstalter</h4><br>'
            .$this->escape($this->joinNames($this->data->text($event['hosts']), $this->data->getLinkedNames($event, 'hosts'))).'<br>'
            .$text($event['host_adresse']).'<br>'
            .$text($event['host_plz']).' '.$text($event['host_ort']).'<br>'
            .$text($event['host_telefon']).'<br>'
            .$text($event['host_email']).'<br>'
            .$text($event['host_website']).'<br>'
            .'<br>'
            .'<h4>Kurzbeschreibung</h4><br>'.$text($event['kurzbeschreibung']).'<br>'
            .'<h4>Kooperation</h4><br>'.$orDash($text($event['kooperationspartner']))
            .'</td>'
            .'</tr></table>';
    }

    /**
     * Bildtitel bzw. Copyright wie bisher: vorhandene Metadaten (auch leer)
     * erscheinen unverändert, ohne Bild bzw. ohne Metadaten steht ein Gedankenstrich.
     */
    private function metaHtml(string|null $value): string
    {
        return null === $value ? '&mdash;' : $this->escape($value);
    }

    /**
     * Titelbild der Veranstaltung: Datei, Links und Metadaten (Bildtitel,
     * Copyright). Ohne Titelbild oder bei fehlender Datei das Ersatzbild, ohne
     * Links und ohne Metadaten.
     *
     * @return array{file: string, url: string|null, originalUrl: string|null, title: string|null, copyright: string|null}
     */
    private function findImage(array $event): array
    {
        $placeholder = [
            'file' => Path::join($this->projectDir, $this->figureFactory->getPlaceholderPath()),
            'url' => null,
            'originalUrl' => null,
            'title' => null,
            'copyright' => null,
        ];

        $file = $event['avatar_file'] ?? null;

        if (!\is_array($file) || !\is_string($file['path'] ?? null)) {
            return $placeholder;
        }

        $path = $file['path'];
        $absolute = Path::join($this->projectDir, $path);

        if (!is_file($absolute)) {
            return $placeholder;
        }

        $meta = $this->figureFactory->getMetadata((string) $file['uuid']);
        $original = $this->findOriginal($path);

        return [
            'file' => $absolute,
            'url' => $this->fileUrl($path),
            'originalUrl' => null === $original ? null : $this->fileUrl($original),
            'title' => null === $meta ? null : $this->data->text($meta->getTitle()),
            'copyright' => null === $meta ? null : $this->data->text($meta->getCaption()),
        ];
    }

    /**
     * Originaldatei zum Titelbild: "<name>_ori.<Endung>" im selben Ordner, bevorzugt
     * mit gleicher Endung (Altbestand: immer .png), sonst mit der echten Endung des
     * Uploads.
     */
    private function findOriginal(string $path): string|null
    {
        $directory = Path::getDirectory($path);
        $stem = Path::getFilenameWithoutExtension($path).'_ori';
        $extension = Path::getExtension($path);

        if (is_file(Path::join($this->projectDir, $directory, $stem.'.'.$extension))) {
            return $directory.'/'.$stem.'.'.$extension;
        }

        $candidates = [];

        foreach (scandir(Path::join($this->projectDir, $directory)) ?: [] as $entry) {
            if (Path::getFilenameWithoutExtension($entry) === $stem && is_file(Path::join($this->projectDir, $directory, $entry))) {
                $candidates[] = $entry;
            }
        }

        sort($candidates);

        return [] === $candidates ? null : $directory.'/'.$candidates[0];
    }

    private function fileUrl(string $path): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $base = null === $request ? '' : $request->getSchemeAndHttpHost().$request->getBasePath();

        return $base.'/'.implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    private function joinNames(string $freeText, string $linkedNames): string
    {
        return implode(', ', array_filter([$freeText, $linkedNames], static fn (string $part): bool => '' !== $part));
    }

    /**
     * Kantenlänge der eingebetteten Titelbilder in Pixeln.
     */
    private function printPixels(): int
    {
        return (int) round(self::IMAGE_SIZE * self::PRINT_DPI / 96);
    }

    /**
     * Kodiert Text für das HTML der PDF-Engine. HTML 4.01 statt HTML5: Die Engine
     * löst Entities mit html_entity_decode(..., ENT_QUOTES) auf, kennt also
     * "&#039;", aber nicht "&apos;".
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageSettings(): array
    {
        return [
            'format' => 'A4',
            'orientation' => 'P',
            'margin' => ['PL' => 10, 'PR' => 10, 'PT' => 10, 'CT' => 10, 'CB' => 25, 'PB' => 25],
        ];
    }

    /**
     * Ordner mit den Schriftdaten (DejaVu Sans, umgewandelt für tc-lib-pdf).
     */
    private function fontPath(): string
    {
        return Path::join(__DIR__, 'Resources/fonts');
    }

    /**
     * tc-lib-pdf sucht Schriften im Ordner aus der Konstante K_PATH_FONTS. Sie wird
     * einmalig gesetzt; ist sie bereits anders belegt, müssen die Schriftdaten
     * dort liegen.
     */
    private function defineFontPath(): void
    {
        if (!\defined('K_PATH_FONTS')) {
            \define('K_PATH_FONTS', $this->fontPath());

            return;
        }

        if (!is_file(Path::join((string) \constant('K_PATH_FONTS'), self::FONT.'.json'))) {
            throw new \RuntimeException(\sprintf('K_PATH_FONTS zeigt auf "%s", dort fehlt die Schrift "%s".', \constant('K_PATH_FONTS'), self::FONT));
        }
    }
}
