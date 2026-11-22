<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

/**
 * Format eines Klartextfeldes der Anlegen- und Bearbeiten-Formulare, das
 * RecordInputMapper serverseitig prüft. Ein leerer Wert ist immer zulässig; ob
 * ein Feld Pflicht ist, legt FieldDefinition::$requiredMessage fest.
 */
enum FieldFormat
{
    /**
     * E-Mail-Adresse (Formularfeld type="email").
     */
    case Email;

    /**
     * Fünfstellige deutsche Postleitzahl (Formularfeld maxlength="5").
     */
    case PostalCode;

    /**
     * Telefonnummer: Ziffern, Leerzeichen und + - / ( ) .
     */
    case Phone;

    /**
     * Internetadresse mit http:// bzw. https:// oder ohne Schema (z. B.
     * "www.beispiel.de"); andere Schemata wie "javascript:" sind unzulässig.
     */
    case Url;

    /**
     * Schlüssel der Meldung in den Sprachdateien (ERR.<Schlüssel>, contao/languages).
     */
    public function messageKey(): string
    {
        return match ($this) {
            self::Email => 'kulturnetz_email',
            self::PostalCode => 'kulturnetz_postal_code',
            self::Phone => 'kulturnetz_phone',
            self::Url => 'kulturnetz_url',
        };
    }

    public function matches(string $value): bool
    {
        return match ($this) {
            // Wie die Browserprüfung von type="email" (WHATWG), Domain auch mit Umlauten.
            self::Email => 1 === preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?(?:\.[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?)+$/u', $value),
            self::PostalCode => 1 === preg_match('/^\d{5}$/', $value),
            self::Phone => 1 === preg_match('/^[0-9 +\-\/().]+$/', $value),
            self::Url => self::isUrl($value),
        };
    }

    /**
     * Schema (falls vorhanden) http oder https, danach ein Hostname mit Punkt und
     * ohne Leerraum. "www.beispiel.de:8080/pfad" ist kein Schema, weil vor dem
     * Doppelpunkt ein Punkt steht.
     */
    private static function isUrl(string $value): bool
    {
        if (1 === preg_match('/^([a-z][a-z0-9+\-]*):/i', $value, $matches) && !\in_array(strtolower($matches[1]), ['http', 'https'], true)) {
            return false;
        }

        $address = preg_replace('#^https?://#i', '', $value) ?? $value;

        return 1 === preg_match('#^[\p{L}\p{N}](?:[\p{L}\p{N}\-]*[\p{L}\p{N}])?(?:\.[\p{L}\p{N}](?:[\p{L}\p{N}\-]*[\p{L}\p{N}])?)+(?::\d{1,5})?(?:[/?\#]\S*)?$#u', $address);
    }
}
