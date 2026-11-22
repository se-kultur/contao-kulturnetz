<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Migration\Price;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Setzt in den Preisen der Veranstaltungen "u20ac" wieder zu "€" zusammen.
 *
 * Die früheren Skripte insertEvent.php und insertSekEvent.php haben das JSON der
 * Preise ohne Maskierung in das SQL eingesetzt; MySQL hat dabei den Backslash von
 * "€" verschluckt, gespeichert wurde z. B. "15,00 u20ac". Templates und
 * Exporte haben das mit str_replace('u20ac', '€', ...) überdeckt, das
 * Bearbeiten-Formular zeigte "u20ac" roh an. Die neuen Endpunkte speichern "€"
 * direkt (TextNormalizer::encodeJson()).
 *
 * Erfasst sind tl_events.prices, tl_sekevents.prices und die Spalte prices der
 * Archivtabellen tl_sekevents_<Jahr>. Die Archive nimmt die Website per
 * doctrine.dbal.schema_filter vom Schemaabgleich aus; sie werden über
 * information_schema gesucht (Muster wie der Filter: genau vier Ziffern) und
 * repariert, damit aus einem Archiv zurückkopierte Datensätze in Frontend und
 * Exporten "€" zeigen. Ersetzt wird in den Zeichenketten des dekodierten JSON;
 * geschrieben wird im Format der übrigen Schreibwege (TextNormalizer::encodeJson(),
 * konvergent mit der BundleTextEncodingMigration). Ungültiges JSON bleibt
 * unverändert und steht in der Ergebnismeldung. Idempotent: Nach einem Lauf
 * enthält kein gültiger Wert mehr "u20ac". Die Tabellen werden bei jedem Aufruf
 * neu ermittelt.
 */
class PriceCurrencyMigration extends AbstractMigration
{
    private const TABLES = ['tl_events', 'tl_sekevents'];

    /**
     * Archivtabellen der SE-KulturTage, gleiches Muster wie doctrine.dbal.schema_filter
     * der Website (tl_sekevents_\d{4}).
     */
    private const ARCHIVE_TABLE_PATTERN = '^tl_sekevents_[0-9]{4}$';

    private const LOST_ESCAPE = 'u20ac';

    private const EURO = '€';

    public function __construct(
        private readonly Connection $connection,
        private readonly TextNormalizer $normalizer,
    ) {
    }

    public function getName(): string
    {
        return 'KulturNetz: Preise der Veranstaltungen mit "€" statt "u20ac"';
    }

    public function shouldRun(): bool
    {
        foreach ($this->getTables() as $table) {
            foreach ($this->fetchCandidates($table) as $row) {
                if (null !== $this->convert((string) $row['prices'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $counts = [];
        $invalid = [];

        foreach ($this->getTables() as $table) {
            $counts[$table] = 0;

            foreach ($this->fetchCandidates($table) as $row) {
                $converted = $this->convert((string) $row['prices'], $isInvalid);

                if ($isInvalid) {
                    $invalid[] = $table.'.'.$row['id'];

                    continue;
                }

                if (null === $converted) {
                    continue;
                }

                $this->connection->update($table, ['prices' => $converted], ['id' => (int) $row['id']], ['prices' => ParameterType::STRING, 'id' => ParameterType::INTEGER]);
                ++$counts[$table];
            }
        }

        $summary = implode(', ', array_map(static fn (string $table, int $count): string => "$table: $count", array_keys($counts), $counts));

        if ([] !== $invalid) {
            $summary .= '; ungültiges JSON unverändert: '.implode(', ', $invalid);
        }

        return $this->createResult(true, $this->getName().' ('.$summary.')');
    }

    /**
     * Neuer Wert oder null, wenn nichts zu ändern ist (auch bei ungültigem JSON).
     */
    private function convert(string $json, bool|null &$isInvalid = null): string|null
    {
        $isInvalid = false;

        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $isInvalid = true;

            return null;
        }

        $changed = false;
        $data = $this->replace($data, $changed);

        return $changed ? $this->normalizer->encodeJson($data) : null;
    }

    private function replace(mixed $data, bool &$changed): mixed
    {
        if (\is_string($data)) {
            if (str_contains($data, self::LOST_ESCAPE)) {
                $changed = true;

                return str_replace(self::LOST_ESCAPE, self::EURO, $data);
            }

            return $data;
        }

        if (\is_array($data)) {
            foreach ($data as $key => $item) {
                $data[$key] = $this->replace($item, $changed);
            }

            return $data;
        }

        if ($data instanceof \stdClass) {
            foreach (get_object_vars($data) as $key => $item) {
                $data->{$key} = $this->replace($item, $changed);
            }
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCandidates(string $table): array
    {
        return $this->connection->fetchAllAssociative(
            \sprintf('SELECT id, prices FROM %s WHERE prices LIKE ? ORDER BY id', $this->connection->quoteSingleIdentifier($table)),
            ['%'.self::LOST_ESCAPE.'%'],
        );
    }

    /**
     * Vorhandene Tabellen mit den Spalten id und prices (bei einer frischen
     * Installation fehlen sie vor dem Schema-Abgleich): zuerst tl_events und
     * tl_sekevents, dann die Archive aufsteigend nach Name.
     *
     * @return list<string>
     */
    private function getTables(): array
    {
        $existing = $this->connection->fetchFirstColumn(
            'SELECT TABLE_NAME FROM information_schema.COLUMNS'
            ." WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('id', 'prices')"
            .' AND (TABLE_NAME IN (?, ?) OR TABLE_NAME REGEXP ?)'
            .' GROUP BY TABLE_NAME HAVING COUNT(DISTINCT COLUMN_NAME) = 2 ORDER BY TABLE_NAME',
            [...self::TABLES, self::ARCHIVE_TABLE_PATTERN],
        );

        $existing = array_map('strval', $existing);
        $archives = array_values(array_diff($existing, self::TABLES));

        return [...array_values(array_intersect(self::TABLES, $existing)), ...$archives];
    }
}
