<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Date\EventDatesStrategy;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Date\WithYearDates;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Date\YearlessDates;

/**
 * Bereitet Veranstaltungsdatensätze für die Ausgabe auf (bisher
 * EventsModel::formatData() und SekEventsModel::formatData()).
 *
 * Der Formatter arbeitet auf Datenbankzeilen (Arrays) und liefert Arrays. Je nach
 * Veranstaltungsart gilt eine von zwei Datumsstrategien: "mit Jahr" für events,
 * "ohne Jahr" für sekevents (siehe EventType::datesStoredWithoutYear()).
 *
 * Ergebnis von format(): alle Spalten der Zeile unverändert, dazu
 *
 * - avatar_file: Zeile aus tl_files zum Titelbild oder null,
 * - linked_artists_data, linked_hosts_data, linked_locations_data: die
 *   verknüpften Profilzeilen in der Reihenfolge der gespeicherten ID-Liste, je
 *   mit avatar_file (tl_files-Zeile oder null); gelöschte Profile entfallen,
 * - dates_formatted: Termine, Schlüssel "<Zeitstempel>_<Index>", Wert der
 *   Termineintrag (date, start, end, comment) mit text,
 * - nextdate: Zeitstempel des nächsten künftigen Termins oder false,
 * - prices_formatted: Preiszeilen (pricetag, price) aus der JSON-Spalte prices,
 *   unverändert (die Ersetzung "u20ac" zu "€" entfällt, die Bestandsdaten
 *   bereinigt die Textmigration),
 * - kulturroute: Nummer der KulturRoute aus KulturnetzConfig (nur sekevents,
 *   sonst null).
 *
 * Die verknüpften Datensätze und Dateien werden für alle übergebenen Zeilen
 * gemeinsam in wenigen Abfragen geladen.
 */
final class EventFormatter
{
    private const PROFILE_TABLES = [
        'linked_artists' => ['tl_artists', 'linked_artists_data'],
        'linked_hosts' => ['tl_hosts', 'linked_hosts_data'],
        'linked_locations' => ['tl_locations', 'linked_locations_data'],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly KulturnetzConfig $config,
        private readonly WithYearDates $withYearDates,
        private readonly YearlessDates $yearlessDates,
    ) {
    }

    /**
     * Datumsstrategie der Veranstaltungsart.
     */
    public function getDatesStrategy(EventType $type): EventDatesStrategy
    {
        return $type->datesStoredWithoutYear() ? $this->yearlessDates : $this->withYearDates;
    }

    /**
     * @param array<string, mixed> $row Zeile aus der Veranstaltungstabelle
     *
     * @return array<string, mixed>
     */
    public function format(EventType $type, array $row, int|null $now = null): array
    {
        return $this->formatMany($type, [$row], $now)[0];
    }

    /**
     * @param list<array<string, mixed>> $rows Zeilen aus der Veranstaltungstabelle
     *
     * @return list<array<string, mixed>> Dieselbe Reihenfolge wie die Eingabe
     */
    public function formatMany(EventType $type, array $rows, int|null $now = null): array
    {
        $now ??= $this->clock->now()->getTimestamp();
        $strategy = $this->getDatesStrategy($type);
        $relations = $this->loadRelations($rows);
        $formatted = [];

        foreach ($rows as $row) {
            $row['avatar_file'] = $relations['files'][$this->avatarKey($row['avatar'] ?? null)] ?? null;

            foreach (self::PROFILE_TABLES as $column => [$table, $target]) {
                $profiles = [];

                foreach ($this->decodeIds($row[$column] ?? null) as $id) {
                    if (isset($relations['profiles'][$table][$id])) {
                        $profile = $relations['profiles'][$table][$id];
                        $profile['avatar_file'] = $relations['files'][$this->avatarKey($profile['avatar'] ?? null)] ?? null;
                        $profiles[] = $profile;
                    }
                }

                $row[$target] = $profiles;
            }

            $row['dates_formatted'] = $strategy->formatDates($this->decodeList($row['dates'] ?? null), $now);
            $row['nextdate'] = $this->findNextDate($row['dates_formatted'], $now);
            $row['prices_formatted'] = $this->getPrices($row);
            $row['kulturroute'] = EventType::SekEvents === $type && isset($row['id'])
                ? $this->config->getKulturrouteForEvent((int) $row['id'])
                : null;

            $formatted[] = $row;
        }

        return $formatted;
    }

    /**
     * Preiszeilen einer Veranstaltung aus der JSON-Spalte "prices".
     *
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    public function getPrices(array $row): array
    {
        return array_values(array_filter($this->decodeList($row['prices'] ?? null), \is_array(...)));
    }

    /**
     * Werte für die Bearbeiten-Formulare: Termine und Preise als Zeilen.
     *
     * Die Termine stehen in der gespeicherten Reihenfolge (nicht nach Zeit
     * sortiert), jede Zeile mit date, start, end und comment. Ohne gespeicherte
     * Preise (leere Spalte) liefert die Methode die Vorgabezeile "Regulärer
     * Eintritt" ohne Preis; eine gespeicherte leere Liste bleibt leer.
     * Ohne Datensatz (neu oder fremd) gibt es keine Terminzeilen.
     *
     * @param array<string, mixed>|null $row
     *
     * @return array{
     *     dates: list<array{date: string, start: string, end: string, comment: string}>,
     *     prices: list<array{pricetag: string, price: string}>
     * }
     */
    public function getFormValues(array|null $row): array
    {
        $dates = [];

        foreach ($this->decodeList($row['dates'] ?? null) as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $dates[] = [
                'date' => $this->toText($entry['date'] ?? null),
                'start' => $this->toText($entry['start'] ?? null),
                'end' => $this->toText($entry['end'] ?? null),
                'comment' => $this->toText($entry['comment'] ?? null),
            ];
        }

        $rawPrices = $row['prices'] ?? null;

        if (null === $rawPrices || '' === $rawPrices) {
            $prices = [['pricetag' => 'Regulärer Eintritt', 'price' => '']];
        } else {
            $prices = [];

            foreach ($this->getPrices($row) as $entry) {
                $prices[] = [
                    'pricetag' => $this->toText($entry['pricetag'] ?? null),
                    'price' => $this->toText($entry['price'] ?? null),
                ];
            }
        }

        return ['dates' => $dates, 'prices' => $prices];
    }

    /**
     * Erster Termin nach $now (strikt später) oder false.
     *
     * @param array<string, array<string, mixed>> $dates dates_formatted
     */
    private function findNextDate(array $dates, int $now): int|false
    {
        foreach ($dates as $key => $unused) {
            $tstamp = (int) explode('_', (string) $key)[0];

            if ($tstamp > $now) {
                return $tstamp;
            }
        }

        return false;
    }

    /**
     * Lädt Profile und Dateien aller Zeilen gemeinsam.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{
     *     profiles: array<string, array<int, array<string, mixed>>>,
     *     files: array<string, array<string, mixed>>
     * }
     */
    private function loadRelations(array $rows): array
    {
        $profiles = [];
        $avatars = [];

        foreach ($rows as $row) {
            $this->collectAvatar($avatars, $row['avatar'] ?? null);
        }

        foreach (self::PROFILE_TABLES as $column => [$table]) {
            $ids = [];

            foreach ($rows as $row) {
                foreach ($this->decodeIds($row[$column] ?? null) as $id) {
                    $ids[$id] = $id;
                }
            }

            $profiles[$table] = [];

            if ([] === $ids) {
                continue;
            }

            $found = $this->connection->fetchAllAssociative(
                'SELECT * FROM '.$table.' WHERE id IN (?)',
                [array_values($ids)],
                [ArrayParameterType::INTEGER],
            );

            foreach ($found as $profile) {
                $profiles[$table][(int) $profile['id']] = $profile;
                $this->collectAvatar($avatars, $profile['avatar'] ?? null);
            }
        }

        $files = [];

        if ([] !== $avatars) {
            $found = $this->connection->fetchAllAssociative(
                'SELECT * FROM tl_files WHERE uuid IN (?)',
                [array_values($avatars)],
                [ArrayParameterType::BINARY],
            );

            foreach ($found as $file) {
                $files[$this->avatarKey($file['uuid'])] = $file;
            }
        }

        return ['profiles' => $profiles, 'files' => $files];
    }

    /**
     * @param array<string, string> $avatars
     */
    private function collectAvatar(array &$avatars, mixed $uuid): void
    {
        if (\is_string($uuid) && '' !== $uuid) {
            $avatars[$this->avatarKey($uuid)] = $uuid;
        }
    }

    private function avatarKey(mixed $uuid): string
    {
        return \is_string($uuid) && '' !== $uuid ? bin2hex($uuid) : '';
    }

    /**
     * Verknüpfte Profil-IDs aus einer JSON-Liste wie ["36","44"]. IDs behalten
     * ihre Reihenfolge und Doppelungen, Einträge, die keine ID sind, entfallen.
     *
     * @return list<int>
     */
    private function decodeIds(mixed $json): array
    {
        $ids = [];

        foreach ($this->decodeList($json) as $id) {
            if ((\is_int($id) && $id > 0) || (\is_string($id) && ctype_digit($id) && (int) $id > 0)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * Dekodiert eine JSON-Spalte, die NULL, leer oder ungültig sein darf, zu einer Liste.
     *
     * @return list<mixed>
     */
    private function decodeList(mixed $json): array
    {
        if (!\is_string($json) || '' === $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return \is_array($decoded) ? array_values($decoded) : [];
    }

    private function toText(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
