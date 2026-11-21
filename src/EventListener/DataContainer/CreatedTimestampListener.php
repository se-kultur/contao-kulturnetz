<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;

/**
 * Setzt "Erstellt am" (created) bei Datensätzen, die die Redaktion im Backend
 * anlegt.
 *
 * Die Frontend-Endpunkte schreiben created selbst (RecordWriter); beim Anlegen
 * im Backend ("Neu") füllt Contao nur tstamp, created bliebe 0 und die Liste
 * zeigte "01.01.70". Der Rückruf läuft direkt nach dem Einfügen der leeren
 * Zeile. Er setzt den Wert nur, solange created noch 0 ist.
 */
#[AsCallback(table: 'tl_artists', target: 'config.oncreate')]
#[AsCallback(table: 'tl_hosts', target: 'config.oncreate')]
#[AsCallback(table: 'tl_locations', target: 'config.oncreate')]
#[AsCallback(table: 'tl_events', target: 'config.oncreate')]
#[AsCallback(table: 'tl_sekevents', target: 'config.oncreate')]
final class CreatedTimestampListener
{
    private const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $set
     */
    public function __invoke(string $table, int|string $insertId, array $set, DataContainer $dc): void
    {
        if (!\in_array($table, self::TABLES, true)) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE '.$this->connection->quoteSingleIdentifier($table).' SET created = ? WHERE id = ? AND created = 0',
            [$this->clock->now()->getTimestamp(), (int) $insertId],
            [ParameterType::INTEGER, ParameterType::INTEGER],
        );
    }
}
