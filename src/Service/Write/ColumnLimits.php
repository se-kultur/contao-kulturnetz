<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Längengrenzen der Spalten aus dem Datenbankschema, das Contao aus den
 * "sql"-Angaben der DCA-Dateien erzeugt (varchar(255), text, mediumtext).
 *
 * Die bisherigen Skripte liefen ohne Strict Mode, zu lange Werte wurden stumm
 * abgeschnitten; die Contao-Verbindung arbeitet mit Strict Mode und bricht mit
 * "Data too long" ab. Die Endpunkte prüfen deshalb vorher und antworten mit 422.
 *
 * char/varchar zählen Zeichen, text/blob Byte. Gelesen wird einmal je Tabelle und
 * Request aus information_schema (MySQL/MariaDB, die einzigen Contao-Plattformen),
 * damit eine geänderte Spaltendefinition im DCA ohne Codeänderung gilt.
 */
final class ColumnLimits implements ResetInterface
{
    private const CHARACTER_TYPES = ['char', 'varchar'];

    /**
     * @var array<string, array<string, array{0: int, 1: bool}>>
     */
    private array $limits = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        $this->limits = [];
    }

    /**
     * True, wenn der Wert in die Spalte passt (oder die Spalte keine bekannte Grenze hat).
     */
    public function fits(string $table, string $column, string $value): bool
    {
        $limit = $this->getLimit($table, $column);

        if (null === $limit) {
            return true;
        }

        [$max, $inCharacters] = $limit;

        return ($inCharacters ? mb_strlen($value, 'UTF-8') : \strlen($value)) <= $max;
    }

    /**
     * Höchstzahl der Zeichen bei char/varchar-Spalten, sonst null (Grenze in Byte
     * oder unbekannt). Für Fehlermeldungen.
     */
    public function getCharacterLimit(string $table, string $column): int|null
    {
        $limit = $this->getLimit($table, $column);

        return null !== $limit && $limit[1] ? $limit[0] : null;
    }

    /**
     * @return array{0: int, 1: bool}|null Grenze und true, wenn in Zeichen gezählt wird
     */
    private function getLimit(string $table, string $column): array|null
    {
        if (!isset($this->limits[$table])) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH'
                .' FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            );

            $this->limits[$table] = [];

            foreach ($rows as $row) {
                $row = array_change_key_case($row, CASE_UPPER);
                $inCharacters = \in_array(strtolower((string) $row['DATA_TYPE']), self::CHARACTER_TYPES, true);
                $max = $inCharacters ? $row['CHARACTER_MAXIMUM_LENGTH'] : $row['CHARACTER_OCTET_LENGTH'];

                if (null !== $max) {
                    $this->limits[$table][strtolower((string) $row['COLUMN_NAME'])] = [(int) $max, $inCharacters];
                }
            }
        }

        return $this->limits[$table][strtolower($column)] ?? null;
    }
}
