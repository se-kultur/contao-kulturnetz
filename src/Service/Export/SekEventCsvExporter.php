<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV-Export der SE-KulturTage-Veranstaltungen (bisher csvEvents.php, Button
 * "CSV-Download" im Backend-Modul "Events").
 *
 * Gleiche 47 Spalten mit denselben deutschen Überschriften und Inhalten wie
 * bisher, Dateiname "<JJJJMMTT>_SE-KulturTage-Events.csv". Die Spalte "Link" ist
 * eine HYPERLINK-Formel für Excel/LibreOffice (bleibt als einzige Formel
 * erhalten). Abweichungen:
 *  - Link und URL zeigen auf die Detailseite der aktuellen Website (Host aus
 *    dem Seitenkontext) statt fest auf https://se-kultur.de.
 *  - Texte als Klartext (E21), Formelinjektion entschärft.
 *  - "Erster Termin" bleibt leer, wenn kein künftiger Termin existiert (bisher
 *    "01.01.1970 01:00").
 *  - Verknüpfte, inzwischen gelöschte Profile erscheinen nicht mehr als leerer
 *    Eintrag in den Listen der verlinkten Profile.
 */
final class SekEventCsvExporter
{
    public const HEADER = [
        'Eventname',
        'Alias',
        'Link',
        'URL',
        'Plakatentwurf?',
        'Programmhefte',
        'A4-Plakate',
        'A3-Plakate',
        'A1-Plakate',
        'A0-Plakate',
        'Wimpelketten',
        'Sparten',
        'Kulturform',
        'Zielgruppen',
        'Erster Termin',
        'Termine',
        'Eintritt',
        'Eintritt-Kommentar',
        'Spenden erbeten?',
        'Anmeldung notwendig?',
        'Anmeldestelle',
        'Künstler:innen',
        'Verlinkte Künstler:innen',
        'Locations',
        'Verlinkte Locations',
        'Location Adresse',
        'Location PLZ',
        'Location Ort',
        'Location Telefon',
        'Location Email',
        'Location Website',
        'Veranstalter:innen',
        'Verlinkte Veranstalter:innen',
        'Veranstalter Adresse',
        'Veranstalter PLZ',
        'Veranstalter Ort',
        'Veranstalter Telefon',
        'Veranstalter Email',
        'Veranstalter Website',
        'Kooperationspartner',
        'Event-ID',
        'Member-ID',
        'Readonly',
        'Disabled',
        'Comment',
        'Erstellt',
        'Bearbeitet',
    ];

    /**
     * Spalte "Link" (0-basiert): gewollte HYPERLINK-Formel.
     */
    private const LINK_COLUMN = 2;

    public function __construct(
        private readonly SekEventExportData $data,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly CsvResponseFactory $responseFactory,
        private readonly ClockInterface $clock,
    ) {
    }

    public function createResponse(): StreamedResponse
    {
        $now = $this->clock->now()->getTimestamp();
        $rows = array_map(fn (array $event): array => $this->row($event, $now), $this->data->load($now));
        $filename = date('Ymd', $now).'_SE-KulturTage-Events.csv';

        return $this->responseFactory->create($filename, self::HEADER, $rows, [self::LINK_COLUMN]);
    }

    /**
     * @return list<string>
     */
    private function row(array $event, int $now): array
    {
        $url = $this->pageUrlResolver->getDetailUrl('sekevents', (string) $event['alias']) ?? '';

        $termine = '';

        foreach ($this->data->getDates($event, $now) as $date) {
            $termine .= $date['date'].', '.$date['start'].' - '.$date['end'].' Uhr';

            if ('' !== $date['comment']) {
                $termine .= ' ('.$date['comment'].')';
            }

            $termine .= "\n";
        }

        $eintritt = 'Eintritt frei';

        if ('1' !== (string) $event['eintrittfrei']) {
            $eintritt = '';

            foreach ($this->data->getPriceLines($event) as $line) {
                $eintritt .= $line."\n";
            }
        }

        $text = $this->data->text(...);

        return [
            $text($event['name']),
            (string) $event['alias'],
            '' === $url ? '' : '=HYPERLINK("'.str_replace('"', '""', $url).'";"Link öffnen")',
            $url,
            (string) $event['plakatentwurf'],
            (string) $event['programmhefte'],
            (string) $event['werbeplakate_a4'],
            (string) $event['werbeplakate_a3'],
            (string) $event['werbeplakate_a1'],
            (string) $event['werbeplakate_a0'],
            (string) $event['wimpelketten'],
            $text($event['sparten']),
            $text($event['kulturform']),
            $text($event['zielgruppen']),
            false === $event['nextdate'] ? '' : date('d.m.Y H:i', (int) $event['nextdate']),
            $termine,
            $eintritt,
            $text($event['pricecomment']),
            '1' === (string) $event['spenden'] ? 'Ja' : 'Nein',
            '1' === (string) $event['anmeldung'] ? 'Ja' : 'Nein',
            $text($event['anmeldestelle']),
            $text($event['artists']),
            $this->data->getLinkedNames($event, 'artists'),
            $text($event['locations']),
            $this->data->getLinkedNames($event, 'locations'),
            $text($event['location_adresse']),
            $text($event['location_plz']),
            $text($event['location_ort']),
            $text($event['location_telefon']),
            $text($event['location_email']),
            $text($event['location_website']),
            $text($event['hosts']),
            $this->data->getLinkedNames($event, 'hosts'),
            $text($event['host_adresse']),
            $text($event['host_plz']),
            $text($event['host_ort']),
            $text($event['host_telefon']),
            $text($event['host_email']),
            $text($event['host_website']),
            $text($event['kooperationspartner']),
            (string) $event['id'],
            (string) $event['member_id'],
            '1' === (string) $event['readonly'] ? 'Gesperrt' : 'Bearbeitbar',
            '1' === (string) $event['disable'] ? 'Unsichtbar' : 'Veröffentlicht',
            $text($event['comment']),
            date('d.m.Y H:i:s', (int) $event['created']),
            date('d.m.Y H:i:s', (int) $event['tstamp']),
        ];
    }
}
