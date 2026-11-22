<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Migration\Text;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Bringt die Klartextfelder der Bundle-Tabellen in das Speicherformat von
 * Contao 5.7 (Entscheidung E21, Service\Text\TextNormalizer).
 *
 * Die früheren Ajax-Skripte (files/se-kultur.de/code/insert*.php, posting.php)
 * schrieben Eingaben mit htmlentities() bzw. utf8_decode() in die Datenbank. Die
 * Daten bestehen deshalb aus einem Gemisch aus benannten HTML-Entities
 * ("&ouml;", "&amp;", "&#039;") und echten Zeichen; Werte aus dem Backend folgen
 * dagegen der Contao-Kodierung. Die Migration dekodiert die Altentities und kodiert
 * nach der Contao-Regel neu, sodass alle Werte einheitlich sind und die
 * OutputEncodingMigration von Contao 6 sie korrekt in Rohtext überführen kann.
 *
 * Erfasst sind die Klartextfelder, die die Altskripte schreiben (research/13,
 * Abschnitt 1.3 und 1.5), in tl_artists, tl_hosts, tl_locations, tl_events,
 * tl_sekevents und tl_postings sowie in den Archivtabellen tl_sekevents_<Jahr>
 * (gleiche Felder wie tl_sekevents, soweit vorhanden). In den JSON-Feldern prices
 * und dates werden die Zeichenketten nach der Regel für Felder mit
 * "decodeEntities" normalisiert.
 *
 * Die Archivtabellen nimmt die Website per doctrine.dbal.schema_filter vom
 * Schemaabgleich aus; sie werden deshalb über information_schema gesucht
 * (Muster wie der Filter: genau vier Ziffern). Normalisiert werden sie, damit
 * Datensätze, die die Redaktion aus einem Archiv nach tl_sekevents zurückkopiert,
 * im selben Format vorliegen wie die übrigen. Die Darstellung ändert sich dadurch
 * nicht (die Kodierung wird beim Auslesen wieder aufgelöst).
 *
 * Nicht erfasst:
 *  - HTML-Felder (beschreibung): Sie werden nicht dekodiert, sondern beim
 *    Speichern über Service\Text\HtmlSanitizer gefiltert.
 *  - Felder, die nur das Backend schreibt (comment, lat, lon), Aliase und die
 *    Zahlenlisten linked_*.
 *
 * Tabellen, Spalten und Spaltenlängen werden bei jedem Aufruf von shouldRun()
 * und run() neu gelesen. contao:migrate prüft die Migrationen nach dem
 * Schemaabgleich im selben Prozess erneut; erst dann sind die verbreiterten
 * Spalten (z. B. varchar(1024) für Webadressen) sichtbar, und Werte, die vorher
 * nicht in die Spalte passten, werden im selben Aufruf umgewandelt.
 *
 * Idempotent und konvergent: Die Migration läuft, solange ein Wert vom
 * normalisierten Wert abweicht und in die Spalte passt. Erzeugt die
 * BasicEntitiesMigration des Bundles danach neue Entities, gleicht ein weiterer
 * Durchlauf sie an; eine feste Reihenfolge ist deshalb nicht nötig. Ungültiges
 * JSON bleibt unverändert.
 */
class BundleTextEncodingMigration extends AbstractMigration
{
    private const PROFILE_FIELDS = [
        'name', 'kurzbeschreibung', 'ort', 'plz', 'adresse', 'telefon', 'email', 'xing', 'linkedin', 'tiktok',
        'instagram', 'facebook', 'website', 'themen', 'zitat',
    ];

    private const EVENT_FIELDS = [
        'name', 'kurzbeschreibung', 'sparten', 'kulturform', 'zielgruppen', 'artists', 'pricecomment', 'anmeldestelle',
        'locations', 'location_adresse', 'location_plz', 'location_ort', 'location_barrierefreiheit', 'location_telefon',
        'location_email', 'location_website', 'hosts', 'host_adresse', 'host_plz', 'host_ort', 'host_telefon',
        'host_email', 'host_website', 'kooperationspartner',
    ];

    private const EVENT_JSON_FIELDS = ['prices', 'dates'];

    /**
     * Archivtabellen der SE-KulturTage, gleiches Muster wie doctrine.dbal.schema_filter
     * der Website (tl_sekevents_\d{4}).
     */
    private const ARCHIVE_TABLE_PATTERN = '^tl_sekevents_[0-9]{4}$';

    public function __construct(
        private readonly Connection $connection,
        private readonly TextNormalizer $normalizer,
    ) {
    }

    public function getName(): string
    {
        return 'KulturNetz: Textkodierung der Bundle-Tabellen auf das Contao-Format bringen';
    }

    public function shouldRun(): bool
    {
        foreach ($this->getTargets() as $table => $fields) {
            foreach ($this->fetchRows($table, $fields) as $row) {
                if ([] !== $this->computeChanges($table, $row, $fields)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $counts = [];
        $invalidJson = [];
        $tooLong = [];

        foreach ($this->getTargets() as $table => $fields) {
            foreach ($this->fetchRows($table, $fields) as $row) {
                $changes = $this->computeChanges($table, $row, $fields, $invalidJson, $tooLong);

                if ([] === $changes) {
                    continue;
                }

                $this->connection->update($table, $changes, ['id' => (int) $row['id']], ['id' => ParameterType::INTEGER]);

                foreach (array_keys($changes) as $field) {
                    $counts["$table.$field"] = ($counts["$table.$field"] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        $summary = [] === $counts
            ? 'keine Änderungen'
            : implode(', ', array_map(static fn (string $key, int $count): string => "$key: $count", array_keys($counts), $counts));

        if ([] !== $invalidJson) {
            $summary .= '; ungültiges JSON unverändert: '.implode(', ', $invalidJson);
        }

        if ([] !== $tooLong) {
            $summary .= '; zu lang für die Spalte, unverändert: '.implode(', ', $tooLong);
        }

        return $this->createResult(true, $this->getName().' ('.$summary.')');
    }

    /**
     * Geänderte Werte einer Zeile (Spalte => neuer Wert).
     *
     * @param array<string, mixed>                                                                 $row
     * @param array{text: list<string>, json: list<string>, lengths: array<string, int|null>} $fields
     * @param list<string>                                                                         $invalidJson
     * @param list<string>                                                                         $tooLong
     *
     * @return array<string, string>
     */
    private function computeChanges(string $table, array $row, array $fields, array &$invalidJson = [], array &$tooLong = []): array
    {
        $changes = [];

        foreach ($fields['text'] as $field) {
            $value = $row[$field] ?? null;

            if (!\is_string($value) || '' === $value) {
                continue;
            }

            $normalized = $this->normalizer->normalize($value);

            if ($normalized === $value) {
                continue;
            }

            // Die Kodierung verlängert den Wert (z. B. "=" in URLs wird "&#61;"). Passt er
            // nicht mehr in die Spalte, bleibt er unverändert; der alte Wert ist gültiges
            // UTF-8 und wird weiter korrekt ausgegeben.
            $maxLength = $fields['lengths'][$field] ?? null;

            $length = null !== $maxLength && $maxLength < 0 ? \strlen($normalized) : mb_strlen($normalized);

            if (null !== $maxLength && $length > abs($maxLength)) {
                $tooLong[] = $table.'.'.$row['id'].'.'.$field;

                continue;
            }

            $changes[$field] = $normalized;
        }

        foreach ($fields['json'] as $field) {
            $value = $row[$field] ?? null;

            if (!\is_string($value) || '' === trim($value)) {
                continue;
            }

            $normalized = $this->normalizer->normalizeJson($value);

            if (null === $normalized) {
                $invalidJson[] = $table.'.'.$row['id'].'.'.$field;

                continue;
            }

            if ($normalized !== $value) {
                $changes[$field] = $normalized;
            }
        }

        return $changes;
    }

    /**
     * @param array{text: list<string>, json: list<string>, lengths: array<string, int|null>} $fields
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $table, array $fields): array
    {
        $columns = array_map(
            fn (string $column): string => $this->connection->quoteSingleIdentifier($column),
            ['id', ...$fields['text'], ...$fields['json']],
        );

        return $this->connection->fetchAllAssociative(\sprintf(
            'SELECT %s FROM %s ORDER BY id',
            implode(', ', $columns),
            $this->connection->quoteSingleIdentifier($table),
        ));
    }

    /**
     * Tabellen mit vorhandenen Spalten und deren Höchstlängen, bei jedem Aufruf
     * frisch aus information_schema (kein Zwischenspeicher, siehe Klassenkommentar).
     * Fehlende Tabellen oder Spalten werden übersprungen (z. B. bei einer frischen
     * Installation vor dem Schemaabgleich). Ein Archiv ohne Spalte "id" wird
     * ebenfalls übersprungen, weil Zeilen nur über die ID aktualisiert werden.
     *
     * Höchstlänge je Spalte: varchar/char in Zeichen, TEXT-Spalten in Byte
     * (negativer Wert), null ohne bekannte Grenze.
     *
     * @return array<string, array{text: list<string>, json: list<string>, lengths: array<string, int|null>}>
     */
    private function getTargets(): array
    {
        $eventFields = ['text' => self::EVENT_FIELDS, 'json' => self::EVENT_JSON_FIELDS];

        $wanted = [
            'tl_artists' => ['text' => [...self::PROFILE_FIELDS, 'sparten'], 'json' => []],
            'tl_hosts' => ['text' => self::PROFILE_FIELDS, 'json' => []],
            'tl_locations' => ['text' => [...self::PROFILE_FIELDS, 'barrierefreiheit', 'auftrittsflaeche', 'gesamtflaeche', 'buehnentyp', 'technik', 'sitzplaetze', 'stehplaetze'], 'json' => []],
            'tl_events' => $eventFields,
            'tl_sekevents' => $eventFields,
            'tl_postings' => ['text' => ['content'], 'json' => []],
        ];

        foreach ($this->findArchiveTables() as $archive) {
            $wanted[$archive] = $eventFields;
        }

        $targets = [];

        foreach ($wanted as $table => $fields) {
            // Spalten und Längen über information_schema statt über die Schema-Introspektion
            // von DBAL (tablesExist()/listTableColumns() sind in DBAL 4 als veraltet
            // markiert). Eine fehlende Tabelle liefert keine Zeilen und wird übersprungen.
            $rows = $this->connection->fetchAllAssociative(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS'
                .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            );

            $lengths = [];

            foreach ($rows as $row) {
                $length = $row['CHARACTER_MAXIMUM_LENGTH'];

                // varchar/char zählen in Zeichen, TEXT-Typen in Byte (negativer Wert)
                $lengths[strtolower((string) $row['COLUMN_NAME'])] = null === $length
                    ? null
                    : (\in_array(strtolower((string) $row['DATA_TYPE']), ['varchar', 'char'], true) ? (int) $length : -(int) $length);
            }

            if (!\array_key_exists('id', $lengths)) {
                continue;
            }

            $filter = static fn (array $list): array => array_values(array_filter($list, static fn (string $column): bool => \array_key_exists(strtolower($column), $lengths)));

            $existing = ['text' => $filter($fields['text']), 'json' => $filter($fields['json']), 'lengths' => []];

            // Spaltennamen der Längenliste wie im DCA schreiben (Schema liefert Kleinschreibung).
            foreach ([...$existing['text'], ...$existing['json']] as $field) {
                $existing['lengths'][$field] = $lengths[strtolower($field)];
            }

            if ([] !== $existing['text'] || [] !== $existing['json']) {
                $targets[$table] = $existing;
            }
        }

        return $targets;
    }

    /**
     * Vorhandene Archivtabellen tl_sekevents_<Jahr>, aufsteigend nach Name.
     *
     * @return list<string>
     */
    private function findArchiveTables(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            .' AND TABLE_NAME REGEXP ? ORDER BY TABLE_NAME',
            [self::ARCHIVE_TABLE_PATTERN],
        ));
    }
}
