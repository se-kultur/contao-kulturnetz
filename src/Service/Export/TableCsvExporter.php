<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Export;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV-Exporte der Künstler, Veranstalter, Locations und Mitglieder (bisher
 * csvArtists.php, csvHosts.php, csvLocations.php, csvMembers.php).
 *
 * Spalten der Tabelle in der Reihenfolge der Datenbank mit den Spaltennamen als
 * Kopfzeile, alle Datensätze, Zeitstempel als Unix-Zeit, Dateiname
 * "<JJJJMMTT>_<Name>.csv". Bei den Profilen bleiben die Spalten multiSRC,
 * orderSRC und avatar leer. Der Mitglieder-Export lässt die Zugangs- und
 * Sicherheitsfelder von tl_member aus (Liste "exclude", Entscheidung E22).
 *
 * Abweichungen (Entscheidung E21):
 *  - Text steht als Klartext in der CSV (Umlaute und Sonderzeichen statt
 *    HTML-Entities); HTML-Felder (Langbeschreibung) bleiben unverändert.
 *  - Formelinjektion ist entschärft (CsvResponseFactory).
 */
final class TableCsvExporter
{
    /**
     * @var array<string, array{filename: string, empty: list<string>, exclude: list<string>, html: list<string>}>
     */
    private const EXPORTS = [
        'tl_artists' => ['filename' => 'Artists', 'empty' => ['multiSRC', 'orderSRC', 'avatar'], 'exclude' => [], 'html' => ['beschreibung']],
        'tl_hosts' => ['filename' => 'Hosts', 'empty' => ['multiSRC', 'orderSRC', 'avatar'], 'exclude' => [], 'html' => ['beschreibung']],
        'tl_locations' => ['filename' => 'Locations', 'empty' => ['multiSRC', 'orderSRC', 'avatar'], 'exclude' => [], 'html' => ['beschreibung']],
        'tl_member' => [
            'filename' => 'Members',
            'empty' => [],
            'exclude' => ['password', 'session', 'secret', 'backupCodes', 'useTwoFactor', 'trustedTokenVersion', 'loginAttempts', 'locked', 'homeDir'],
            'html' => [],
        ],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly TextNormalizer $textNormalizer,
        private readonly CsvResponseFactory $responseFactory,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param string $table tl_artists, tl_hosts, tl_locations oder tl_member
     */
    public function createResponse(string $table): StreamedResponse
    {
        $export = self::EXPORTS[$table] ?? throw new \InvalidArgumentException(\sprintf('Für die Tabelle "%s" gibt es keinen CSV-Export.', $table));
        $columns = $this->getColumns($table, $export['exclude']);
        $filename = $this->clock->now()->format('Ymd').'_'.$export['filename'].'.csv';

        return $this->responseFactory->create($filename, $columns, $this->rows($table, $columns, $export));
    }

    /**
     * @param list<string>                                                                 $columns
     * @param array{filename: string, empty: list<string>, exclude: list<string>, html: list<string>} $export
     *
     * @return \Generator<list<string|null>>
     */
    private function rows(string $table, array $columns, array $export): \Generator
    {
        $select = implode(', ', array_map($this->connection->quoteSingleIdentifier(...), $columns));
        $result = $this->connection->executeQuery("SELECT $select FROM $table");

        while (false !== ($row = $result->fetchAssociative())) {
            $cells = [];

            foreach ($columns as $column) {
                $value = $row[$column];

                if (\in_array($column, $export['empty'], true) || null === $value) {
                    $cells[] = '';
                } elseif (\is_string($value) && !\in_array($column, $export['html'], true)) {
                    $cells[] = $this->textNormalizer->toPlainText($value);
                } else {
                    $cells[] = (string) $value;
                }
            }

            yield $cells;
        }
    }

    /**
     * Spaltennamen der Tabelle in der Reihenfolge der Datenbank (wie DESCRIBE).
     *
     * @param list<string> $exclude
     *
     * @return list<string>
     */
    private function getColumns(string $table, array $exclude): array
    {
        $columns = $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table],
        );

        $excluded = array_map(strtolower(...), $exclude);

        return array_values(array_filter(
            array_map(strval(...), $columns),
            static fn (string $column): bool => !\in_array(strtolower($column), $excluded, true),
        ));
    }
}
