<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Migration\Alias;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Setzt leere Aliase der Profile und Veranstaltungen auf NULL.
 *
 * Die Spalte alias ist eindeutig (UNIQUE) und hatte bis Bundle 2.0 den Standard
 * '' (NOT NULL). "Neu" im Backend legt sofort eine Zeile mit diesem Standard an;
 * blieb ein solcher Entwurf liegen, scheiterte jede weitere Neuanlage in der
 * Tabelle mit "Duplicate entry '' for key 'alias'". Jetzt ist die Spalte
 * nullable mit Standard NULL, mehrere NULL verletzen den Index nicht.
 *
 * Die Migration läuft erst, wenn der Schema-Abgleich die Spalte nullable gemacht
 * hat (contao:migrate führt nach dem Schema-Abgleich die Migrationen erneut aus)
 * und noch Zeilen mit alias = '' existieren. Danach findet shouldRun() keine
 * solche Zeile mehr (idempotent). Andere Spalten und tstamp bleiben unverändert.
 *
 * Die Archivtabellen tl_sekevents_<Jahr> sind nicht enthalten.
 */
class EmptyAliasToNullMigration extends AbstractMigration
{
    private const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'KulturNetz: leere Aliase der Profile und Veranstaltungen auf NULL setzen';
    }

    public function shouldRun(): bool
    {
        foreach ($this->getTables() as $table) {
            if ($this->countEmpty($table) > 0) {
                return true;
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $counts = [];

        foreach ($this->getTables() as $table) {
            $counts[] = $table.': '.$this->connection->executeStatement(
                'UPDATE '.$this->connection->quoteSingleIdentifier($table)." SET alias = NULL WHERE alias = ''",
            );
        }

        return $this->createResult(true, $this->getName().' ('.implode(', ', $counts).')');
    }

    private function countEmpty(string $table): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM '.$this->connection->quoteSingleIdentifier($table)." WHERE alias = ''",
        );
    }

    /**
     * Tabellen, deren Spalte alias bereits NULL zulässt (vor dem Schema-Abgleich
     * ist sie NOT NULL, bei einer frischen Installation fehlen die Tabellen).
     *
     * @return list<string>
     */
    private function getTables(): array
    {
        $nullable = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'alias' AND IS_NULLABLE = 'YES' AND TABLE_NAME IN (?)",
            [self::TABLES],
            [ArrayParameterType::STRING],
        );

        $nullable = array_map('strtolower', array_map('strval', $nullable));

        return array_values(array_filter(self::TABLES, static fn (string $table): bool => \in_array($table, $nullable, true)));
    }
}
