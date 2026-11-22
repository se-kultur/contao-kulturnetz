<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Gibt einer im Backend kopierten Zeile ("Speichern und duplizieren",
 * "Mehrere bearbeiten" > "Kopieren") einen eigenen Alias und sperrt sie.
 *
 * Der Alias wird nicht mitkopiert (eval.doNotCopy), die Kopie entsteht deshalb
 * mit alias = NULL und kollidiert nicht mit dem eindeutigen Index. Danach
 * erzeugt dieser Rückruf den Alias aus dem Namen wie beim Speichern
 * (AliasListener::generateUniqueAlias(), Zähler "-2", "-3"). Die Kopie wird
 * gesperrt (disable = 1), damit sie nicht ungeprüft öffentlich erscheint; die
 * Redaktion schaltet sie nach dem Bearbeiten wie ein neues Profil frei.
 */
#[AsCallback(table: 'tl_artists', target: 'config.oncopy')]
#[AsCallback(table: 'tl_hosts', target: 'config.oncopy')]
#[AsCallback(table: 'tl_locations', target: 'config.oncopy')]
#[AsCallback(table: 'tl_events', target: 'config.oncopy')]
#[AsCallback(table: 'tl_sekevents', target: 'config.oncopy')]
final class RecordCopyListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AliasListener $aliasListener,
    ) {
    }

    public function __invoke(int|string $insertId, DataContainer $dc): void
    {
        $table = $dc->table;

        if (!\in_array($table, AliasListener::TABLES, true)) {
            return;
        }

        $quotedTable = $this->connection->quoteSingleIdentifier($table);
        $id = (int) $insertId;

        $name = $this->connection->fetchOne("SELECT name FROM $quotedTable WHERE id = ?", [$id], [ParameterType::INTEGER]);

        if (false === $name) {
            return;
        }

        $alias = $this->aliasListener->generateUniqueAlias($table, (string) $name, $id);

        $this->connection->executeStatement(
            "UPDATE $quotedTable SET alias = ?, disable = '1' WHERE id = ?",
            ['' === $alias ? null : $alias, $id],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }
}
