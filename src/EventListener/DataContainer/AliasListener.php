<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Slug\Slug;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prüft bzw. erzeugt beim Speichern im Backend den Alias der Profile und
 * Veranstaltungen (bisher generateAlias() in den fünf DCA-Dateien).
 *
 * Ein leerer Alias wird wie bisher mit dem Contao-Slug-Service aus dem Namen
 * erzeugt, ein rein numerischer oder bereits vergebener Alias mit den
 * Contao-Meldungen ERR.aliasNumeric bzw. ERR.aliasExists abgelehnt. Neu: Ein
 * erzeugter Alias, der schon vergeben ist, bekommt wie im Contao-Kern einen
 * Zähler ("-2", "-3"), statt beim Speichern am eindeutigen Index zu scheitern.
 *
 * Die Spalte alias ist nullable: Ein noch nicht gespeicherter Datensatz ("Neu")
 * hat den Alias NULL, damit mehrere unfertige Entwürfe den eindeutigen Index
 * nicht blockieren. Ergibt der Name keinen Alias, gibt der Rückruf einen
 * Leerstring zurück; DC_Table schreibt dafür den leeren Wert der Spalte (NULL).
 *
 * generateUniqueAlias() nutzt auch der RecordCopyListener für Kopien.
 */
#[AsCallback(table: 'tl_artists', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_hosts', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_locations', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_events', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_sekevents', target: 'fields.alias.save')]
final class AliasListener
{
    public const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents'];

    public function __construct(
        private readonly Connection $connection,
        private readonly Slug $slug,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(mixed $value, DataContainer $dc): string
    {
        $table = $this->assertTable($dc->table);
        $value = (string) $value;
        $recordId = (int) $dc->id;

        // Wie bisher (!$varValue) gilt auch "0" als leer.
        if ('' === $value || '0' === $value) {
            return $this->generateUniqueAlias($table, (string) ($dc->getCurrentRecord()['name'] ?? ''), $recordId);
        }

        if (preg_match('/^[1-9]\d*$/', $value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasNumeric', [$value], 'contao_default'));
        }

        if ($this->aliasExists($table, $value, $recordId)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasExists', [$value], 'contao_default'));
        }

        return $value;
    }

    /**
     * Eindeutiger Alias aus dem Namen (Contao-Slug, bei Bedarf mit Zähler "-2",
     * "-3"). $recordId nimmt den eigenen Datensatz von der Prüfung aus. Ein Name
     * ohne verwertbare Zeichen ergibt einen Leerstring.
     */
    public function generateUniqueAlias(string $table, string $name, int $recordId): string
    {
        $table = $this->assertTable($table);

        return $this->slug->generate(
            $name,
            [],
            fn (string $alias): bool => '' !== $alias && $this->aliasExists($table, $alias, $recordId),
        );
    }

    private function aliasExists(string $table, string $alias, int $recordId): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT id FROM $table WHERE alias = ? AND id != ?",
            [$alias, $recordId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    /**
     * Tabellennamen gehen in SQL ein und kommen deshalb nur aus der festen Liste.
     */
    private function assertTable(string $table): string
    {
        if (!\in_array($table, self::TABLES, true)) {
            throw new \InvalidArgumentException(\sprintf('Die Tabelle "%s" wird vom AliasListener nicht unterstützt.', $table));
        }

        return $table;
    }
}
