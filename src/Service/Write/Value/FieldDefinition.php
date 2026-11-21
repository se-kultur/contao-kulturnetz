<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write\Value;

use SeKultur\ContaoKulturnetzBundle\Service\Write\FieldKind;

/**
 * Ein schreibbares Feld eines Anlegen- bzw. Bearbeiten-Formulars.
 *
 * Die Feldnamen der Formulare bleiben unverändert (Vertrag research/13, 3.5);
 * $requestName ist der Name im Formular, $column die Spalte der Tabelle. Bei
 * Feldern mit mehreren Formularfeldern (Preise, Termine) ist $requestName das
 * führende Feld (price bzw. eventdate).
 */
final class FieldDefinition
{
    /**
     * @param string|null $requiredMessage Meldung, wenn das Feld leer ist (null = optional)
     * @param int         $minLength       Mindestlänge wie im Formular-JS (0 = keine)
     * @param string|null $linkedType      Profiltyp der verknüpften IDs (nur FieldKind::LinkedIds)
     */
    public function __construct(
        public readonly string $column,
        public readonly string $requestName,
        public readonly FieldKind $kind,
        public readonly string|null $requiredMessage = null,
        public readonly int $minLength = 0,
        public readonly string|null $minLengthMessage = null,
        public readonly string|null $linkedType = null,
    ) {
    }

    public static function text(string $column, string|null $requestName = null, string|null $requiredMessage = null): self
    {
        return new self($column, $requestName ?? $column, FieldKind::Text, $requiredMessage);
    }

    public static function html(string $column, int $minLength, string $minLengthMessage): self
    {
        return new self($column, $column, FieldKind::Html, $minLengthMessage, $minLength, $minLengthMessage);
    }

    public static function checkbox(string $column): self
    {
        return new self($column, $column, FieldKind::Checkbox);
    }

    public static function integer(string $column): self
    {
        return new self($column, $column, FieldKind::Integer);
    }

    public static function avatar(): self
    {
        return new self('avatar', 'avatar', FieldKind::Avatar);
    }

    public static function linkedIds(string $column, string $linkedType): self
    {
        return new self($column, $column, FieldKind::LinkedIds, linkedType: $linkedType);
    }

    public static function prices(): self
    {
        return new self('prices', 'price', FieldKind::Prices);
    }

    public static function dates(): self
    {
        return new self('dates', 'eventdate', FieldKind::Dates);
    }
}
