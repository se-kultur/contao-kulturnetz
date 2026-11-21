<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write\Value;

/**
 * Schreibbare Felder eines Typs (Whitelist) für die Anlegen- und
 * Bearbeiten-Endpunkte. Alle anderen Spalten (alias, disable, member_id,
 * created, readonly, comment, lat, lon, Galerie) schreiben die Endpunkte nie aus
 * Formularwerten.
 */
final class RecordSchema
{
    /**
     * @param string                $type      Typschlüssel (artists, hosts, locations, events, sekevents)
     * @param string                $nameField Spalte, aus der der Alias entsteht
     * @param list<FieldDefinition> $fields
     */
    public function __construct(
        public readonly string $type,
        public readonly string $nameField,
        public readonly array $fields,
    ) {
    }

    public function table(): string
    {
        return 'tl_'.$this->type;
    }

    public function getField(string $column): FieldDefinition|null
    {
        foreach ($this->fields as $field) {
            if ($field->column === $column) {
                return $field;
            }
        }

        return null;
    }
}
