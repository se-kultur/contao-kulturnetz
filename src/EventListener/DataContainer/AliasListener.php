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
 */
#[AsCallback(table: 'tl_artists', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_hosts', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_locations', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_events', target: 'fields.alias.save')]
#[AsCallback(table: 'tl_sekevents', target: 'fields.alias.save')]
final class AliasListener
{
    private const TABLES = ['tl_artists', 'tl_hosts', 'tl_locations', 'tl_events', 'tl_sekevents'];

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

        $aliasExists = fn (string $alias): bool => false !== $this->connection->fetchOne(
            "SELECT id FROM $table WHERE alias = ? AND id != ?",
            [$alias, $recordId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        // Wie bisher (!$varValue) gilt auch "0" als leer.
        if ('' === $value || '0' === $value) {
            $name = (string) ($dc->getCurrentRecord()['name'] ?? '');

            return $this->slug->generate($name, [], $aliasExists);
        }

        if (preg_match('/^[1-9]\d*$/', $value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasNumeric', [$value], 'contao_default'));
        }

        if ($aliasExists($value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasExists', [$value], 'contao_default'));
        }

        return $value;
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
