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
 * Erfasst sind tl_events.prices und tl_sekevents.prices, nicht die Archivtabellen
 * (tl_sekevents_<Jahr>). Ersetzt wird in den Zeichenketten des dekodierten JSON;
 * geschrieben wird im Format der übrigen Schreibwege (TextNormalizer::encodeJson(),
 * konvergent mit der BundleTextEncodingMigration). Ungültiges JSON bleibt
 * unverändert und steht in der Ergebnismeldung. Idempotent: Nach einem Lauf
 * enthält kein gültiger Wert mehr "u20ac".
 */
class PriceCurrencyMigration extends AbstractMigration
{
    private const TABLES = ['tl_events', 'tl_sekevents'];

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
            "SELECT id, prices FROM $table WHERE prices LIKE ? ORDER BY id",
            ['%'.self::LOST_ESCAPE.'%'],
        );
    }

    /**
     * Vorhandene Tabellen mit Spalte prices (bei einer frischen Installation fehlen
     * sie vor dem Schema-Abgleich).
     *
     * @return list<string>
     */
    private function getTables(): array
    {
        $existing = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'prices' AND TABLE_NAME IN (?, ?)",
            self::TABLES,
        );

        $existing = array_map('strtolower', array_map('strval', $existing));

        return array_values(array_filter(self::TABLES, static fn (string $table): bool => \in_array($table, $existing, true)));
    }
}
