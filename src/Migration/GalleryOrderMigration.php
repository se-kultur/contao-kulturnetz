<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Überträgt die Galerie-Reihenfolge aus orderSRC nach multiSRC.
 *
 * Contao 4.13 speicherte die Auswahl der Galeriebilder in multiSRC und die
 * Reihenfolge über eval.orderField in orderSRC. Contao 5 kennt orderField
 * nicht mehr: Das Feld multiSRC ist jetzt selbst sortierbar (isSortable), und
 * das Backend zeigt die Bilder in der Reihenfolge von multiSRC an.
 *
 * Anders als die Core-Migration Version500\OrderFieldMigration bleibt orderSRC
 * erhalten und unverändert; die Spalte entfällt erst bei der bewussten
 * Bereinigung der Datenbank. Bis dahin spiegeln GalleryOrderMirrorListener
 * (Backend) und RecordMediaRepository (Frontend-Endpunkte) die Reihenfolge von
 * multiSRC nach orderSRC, damit diese Migration eine geänderte Reihenfolge nicht
 * zurückschreibt. Kein Template und kein Skript liest orderSRC mehr.
 *
 * Neue Reihenfolge von multiSRC: zuerst alle Einträge aus orderSRC, danach die
 * übrigen Einträge aus multiSRC, ohne Duplikate (wie die Core-Migration).
 * Einträge, die nur in orderSRC stehen, zeigt das Frontend unter Contao 4.13
 * an; sie gehören deshalb auch in die Backend-Auswahl.
 *
 * Die Migration ändert nur multiSRC und nur bei Datensätzen mit gefülltem
 * orderSRC, deren Reihenfolge abweicht. tstamp bleibt unverändert. Danach
 * findet shouldRun() keinen abweichenden Datensatz mehr (idempotent).
 *
 * Das Archiv tl_sekevents_2024 ist bewusst nicht enthalten und wird nie
 * verändert.
 */
class GalleryOrderMigration extends AbstractMigration
{
    private const TABELLEN = [
        'tl_artists',
        'tl_hosts',
        'tl_locations',
        'tl_events',
        'tl_sekevents',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'KulturNetz: Galerie-Reihenfolge von orderSRC nach multiSRC übertragen';
    }

    public function shouldRun(): bool
    {
        foreach ($this->getTables() as $table) {
            foreach ($this->fetchRows($table) as $row) {
                if (null !== $this->getNewOrder($row)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function run(): MigrationResult
    {
        $updated = 0;

        foreach ($this->getTables() as $table) {
            foreach ($this->fetchRows($table) as $row) {
                $items = $this->getNewOrder($row);

                if (null === $items) {
                    continue;
                }

                // Nur aktualisieren, wenn sich multiSRC seit dem Lesen nicht
                // geändert hat (gleichzeitige Bearbeitung im Frontend).
                $updated += $this->connection->executeStatement(
                    'UPDATE '.$this->connection->quoteSingleIdentifier($table).' SET multiSRC = :items WHERE id = :id AND multiSRC <=> :multiSRC',
                    [
                        'items' => serialize($items),
                        'id' => $row['id'],
                        'multiSRC' => $row['multiSRC'],
                    ],
                );
            }
        }

        return $this->createResult(true, \sprintf('%d Datensätze aktualisiert.', $updated));
    }

    /**
     * @return list<string>
     */
    private function getTables(): array
    {
        // Abfrage über information_schema statt über die Schema-Introspektion von
        // DBAL (listTableColumns()/tablesExist() sind in DBAL 4 als veraltet markiert).
        // Eine Tabelle zählt nur, wenn beide Spalten existieren; fehlt eine der
        // beiden (z. B. nach der Bereinigung der Altspalte), wird sie übersprungen.
        $placeholders = implode(', ', array_fill(0, \count(self::TABELLEN), '?'));

        $found = $this->connection->fetchFirstColumn(
            'SELECT TABLE_NAME FROM information_schema.COLUMNS'
            .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.$placeholders.')'
            ." AND COLUMN_NAME IN ('multiSRC', 'orderSRC')"
            .' GROUP BY TABLE_NAME HAVING COUNT(DISTINCT COLUMN_NAME) = 2',
            self::TABELLEN,
        );

        return array_values(array_filter(self::TABELLEN, static fn (string $table): bool => \in_array($table, $found, true)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(string $table): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, multiSRC, orderSRC FROM '.$this->connection->quoteSingleIdentifier($table)." WHERE orderSRC IS NOT NULL AND orderSRC != ''",
        );
    }

    /**
     * Liefert die neue Reihenfolge für multiSRC oder null, wenn multiSRC
     * bereits passt.
     *
     * @param array<string, mixed> $row
     *
     * @return list<string>|null
     */
    private function getNewOrder(array $row): array|null
    {
        $order = array_values(StringUtil::deserialize($row['orderSRC'], true));

        if (!$order) {
            return null;
        }

        $current = array_values(StringUtil::deserialize($row['multiSRC'], true));
        $items = array_values(array_unique([...$order, ...$current]));

        return $items === $current ? null : $items;
    }
}
