<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Befristete Übergangsbrücke (Entscheidung E25): spiegelt nach dem Speichern im
 * Backend die Galerie-Reihenfolge aus multiSRC in die Altspalte orderSRC.
 *
 * Das DCA-Feld orderSRC und der Callback syncOrderSRC sind entfernt; multiSRC
 * ist die einzige Quelle der Reihenfolge. Solange die Spalte orderSRC noch
 * existiert, lesen sie aber zwei Stellen weiter:
 *  - die Legacy-Templates der Profil- und Veranstaltungsmodule (bis zu ihrer
 *    Ablösung durch die Modulpakete),
 *  - GalleryOrderMigration, die eine abweichende orderSRC-Reihenfolge bei jedem
 *    contao:migrate nach multiSRC zurückschreiben würde.
 * Ohne Spiegel würde eine im Backend geänderte Reihenfolge deshalb im Frontend
 * nicht erscheinen und beim nächsten Migrationslauf verloren gehen. Die
 * Endpunkte der Mitglieder spiegeln aus demselben Grund (RecordMediaRepository).
 *
 * Fehlt die Spalte (nach der bewussten Bereinigung mit --with-deletes), tut der
 * Listener nichts. Entfernen zusammen mit dem Spiegel in RecordMediaRepository,
 * sobald kein Legacy-Template mehr orderSRC liest und die Spalte gelöscht ist.
 */
#[AsCallback(table: 'tl_artists', target: 'config.onsubmit')]
#[AsCallback(table: 'tl_hosts', target: 'config.onsubmit')]
#[AsCallback(table: 'tl_locations', target: 'config.onsubmit')]
#[AsCallback(table: 'tl_events', target: 'config.onsubmit')]
#[AsCallback(table: 'tl_sekevents', target: 'config.onsubmit')]
final class GalleryOrderMirrorListener implements ResetInterface
{
    private const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents'];

    /**
     * @var array<string, bool>
     */
    private array $hasColumn = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(DataContainer $dc): void
    {
        $table = $dc->table;
        $id = (int) $dc->id;

        if ($id < 1 || !\in_array($table, self::TABLES, true) || !$this->hasOrderColumn($table)) {
            return;
        }

        $row = $this->connection->fetchAssociative(
            "SELECT multiSRC, orderSRC FROM $table WHERE id = ?",
            [$id],
            [ParameterType::INTEGER],
        );

        if (false === $row) {
            return;
        }

        // Wie bisher syncOrderSRC: eine geleerte Galerie ergibt "a:0:{}".
        $order = serialize(array_values(StringUtil::deserialize($row['multiSRC'], true)));

        if ($order === $row['orderSRC']) {
            return;
        }

        $this->connection->update($table, ['orderSRC' => $order], ['id' => $id], ['orderSRC' => ParameterType::BINARY, 'id' => ParameterType::INTEGER]);
    }

    public function reset(): void
    {
        $this->hasColumn = [];
    }

    private function hasOrderColumn(string $table): bool
    {
        return $this->hasColumn[$table] ??= (bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'orderSRC'",
            [$table],
        );
    }
}
