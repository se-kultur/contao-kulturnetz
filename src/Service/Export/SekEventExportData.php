<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventFormatter;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Gemeinsame Datengrundlage der Exporte der SE-KulturTage (CSV und PDF, bisher
 * csvEvents.php und pdfEvents.php): alle Datensätze aus tl_sekevents, auch
 * gesperrte, formatiert mit dem EventFormatter (Datumsstrategie ohne Jahr).
 *
 * Reihenfolge wie bisher nach dem nächsten Termin: zuerst Veranstaltungen ohne
 * künftigen Termin, dann aufsteigend nach dem nächsten Termin, bei Gleichstand
 * nach ID. Die Altskripte sortierten über einen aus Zeitstempel und ID
 * zusammengesetzten Schlüssel; bei IDs unterschiedlicher Stellenzahl ergab das
 * eine falsche Reihenfolge (z. B. ID 100 nach allen zweistelligen IDs). Hier
 * wird nach Termin und ID getrennt sortiert.
 */
final class SekEventExportData
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventFormatter $formatter,
        private readonly TextNormalizer $textNormalizer,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<array<string, mixed>> formatierte Veranstaltungen (siehe EventFormatter)
     */
    public function load(int|null $now = null): array
    {
        $now ??= $this->clock->now()->getTimestamp();

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM tl_sekevents');
        $events = $this->formatter->formatMany(EventType::SekEvents, $rows, $now);

        usort($events, static function (array $a, array $b): int {
            $nextA = false === $a['nextdate'] ? 0 : (int) $a['nextdate'];
            $nextB = false === $b['nextdate'] ? 0 : (int) $b['nextdate'];

            return [$nextA, (int) $a['id']] <=> [$nextB, (int) $b['id']];
        });

        return $events;
    }

    /**
     * Termine in gespeicherter Reihenfolge, wie in den Altskripten: Datum (TT.MM.)
     * plus laufendes Jahr, Beginn, Ende und Kommentar als Klartext.
     *
     * @return list<array{date: string, start: string, end: string, comment: string}>
     */
    public function getDates(array $event, int|null $now = null): array
    {
        $year = date('Y', $now ?? $this->clock->now()->getTimestamp());
        $dates = [];

        foreach ($this->decodeList($event['dates'] ?? null) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $dates[] = [
                'date' => $this->scalar($entry['date'] ?? '').$year,
                'start' => $this->scalar($entry['start'] ?? ''),
                'end' => $this->scalar($entry['end'] ?? ''),
                'comment' => trim($this->textNormalizer->toPlainText($this->scalar($entry['comment'] ?? ''))),
            ];
        }

        return $dates;
    }

    /**
     * Preiszeilen als Klartext ("Bezeichnung = Preis" bzw. nur "Bezeichnung").
     *
     * @return list<string>
     */
    public function getPriceLines(array $event): array
    {
        $lines = [];

        foreach ($event['prices_formatted'] ?? [] as $price) {
            $tag = $this->textNormalizer->toPlainText($this->scalar($price['pricetag'] ?? ''));
            $amount = $this->textNormalizer->toPlainText($this->scalar($price['price'] ?? ''));

            $lines[] = '' !== $amount ? $tag.' = '.$amount : $tag;
        }

        return $lines;
    }

    /**
     * Namen der verknüpften Profile (artists, hosts, locations) als Klartext,
     * durch Komma getrennt. Gelöschte Profile entfallen.
     */
    public function getLinkedNames(array $event, string $type): string
    {
        $names = [];

        foreach ($event['linked_'.$type.'_data'] ?? [] as $profile) {
            $names[] = $this->text($profile['name'] ?? '');
        }

        return implode(', ', $names);
    }

    /**
     * Gespeicherter Text als Klartext (Entities dekodiert, Entscheidung E21).
     */
    public function text(mixed $value): string
    {
        return $this->textNormalizer->toPlainText($this->scalar($value));
    }

    private function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<mixed>
     */
    private function decodeList(mixed $json): array
    {
        if (!\is_string($json) || '' === $json) {
            return [];
        }

        $data = json_decode($json, true);

        return \is_array($data) ? array_values($data) : [];
    }
}
