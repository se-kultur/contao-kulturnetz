<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write\Value;

use SeKultur\ContaoKulturnetzBundle\Service\Write\FieldFormat;
use SeKultur\ContaoKulturnetzBundle\Service\Write\FieldKind;

/**
 * Ein schreibbares Feld eines Anlegen- bzw. Bearbeiten-Formulars.
 *
 * Die Feldnamen der Formulare bleiben unverändert (Vertrag research/13, 3.5);
 * $requestName ist der Name im Formular, $column die Spalte der Tabelle. Bei
 * Feldern mit mehreren Formularfeldern (Preise, Termine) ist $requestName das
 * führende Feld (price bzw. eventdate).
 *
 * Formatregeln ($format, $choices, $maxLength) prüft RecordInputMapper nur für
 * Werte, die beim Anlegen gesendet bzw. beim Bearbeiten gegenüber dem
 * gespeicherten Wert verändert wurden. Bestandswerte, die eine Regel verletzen,
 * bleiben so speicherbar, solange das Mitglied sie nicht ändert.
 */
final class FieldDefinition
{
    /**
     * @param string|null $requiredMessage Meldung, wenn das Feld leer ist (null = optional)
     * @param int         $minLength       Mindestlänge wie im Formular-JS (0 = keine)
     * @param string|null $linkedType      Profiltyp der verknüpften IDs (nur FieldKind::LinkedIds)
     * @param FieldFormat|null $format     Format eines Klartextfeldes (leer ist immer zulässig)
     * @param list<string>|null $choices   zulässige Werte einer (Mehrfach-)Auswahl, gesendet kommagetrennt
     * @param int         $maxLength       Höchstlänge wie im Formular (maxlength, JavaScript-Zählung; 0 = nur Spaltengrenze)
     * @param bool        $trimRequired    Pflichtfeld ohne Leerraum am Rand speichern (Name, Sparten); false speichert
     *                                     wie eingegeben und prüft nur, ob mehr als Leerraum vorhanden ist
     */
    public function __construct(
        public readonly string $column,
        public readonly string $requestName,
        public readonly FieldKind $kind,
        public readonly string|null $requiredMessage = null,
        public readonly int $minLength = 0,
        public readonly string|null $minLengthMessage = null,
        public readonly string|null $linkedType = null,
        public readonly FieldFormat|null $format = null,
        public readonly array|null $choices = null,
        public readonly int $maxLength = 0,
        public readonly bool $trimRequired = true,
    ) {
    }

    public static function text(string $column, string|null $requestName = null, string|null $requiredMessage = null): self
    {
        return new self($column, $requestName ?? $column, FieldKind::Text, $requiredMessage);
    }

    /**
     * Längerer Pflicht-Freitext mit Höchstlänge des Formulars (Kurzbeschreibung):
     * gespeichert wie eingegeben, nur Leerraum gilt als fehlend.
     */
    public static function requiredText(string $column, string $requiredMessage, int $maxLength): self
    {
        return new self($column, $column, FieldKind::Text, $requiredMessage, maxLength: $maxLength, trimRequired: false);
    }

    /**
     * Klartextfeld mit Formatprüfung (E-Mail, Postleitzahl, Telefon, Internetadresse).
     */
    public static function formatted(string $column, FieldFormat $format): self
    {
        return new self($column, $column, FieldKind::Text, format: $format);
    }

    /**
     * Auswahl (Mehrfachauswahl kommagetrennt): nur Werte aus $choices.
     *
     * @param list<string> $choices
     */
    public static function choice(string $column, array $choices, string|null $requiredMessage = null): self
    {
        return new self($column, $column, FieldKind::Text, $requiredMessage, choices: $choices);
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

    public static function number(string $column): self
    {
        return new self($column, $column, FieldKind::Number);
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

    /**
     * Name des Formularfeldes, unter dem eine Meldung erscheint: bei Preisen und
     * Terminen das Listenfeld ("price[]", "eventdate[]"), sonst der Feldname.
     */
    public function formName(): string
    {
        return match ($this->kind) {
            FieldKind::Prices, FieldKind::Dates => $this->requestName.'[]',
            default => $this->requestName,
        };
    }
}
