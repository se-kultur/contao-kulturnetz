<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Text;

/**
 * Bringt Klartext in das Speicherformat, das Contao 5.7 selbst für
 * Frontend-Eingaben in Textfelder schreibt (Entscheidung E21).
 *
 * Contao-Regel (Contao\Input::encodeInput(), Modus encodeAll, im Frontend mit
 * Kodierung der Insert-Tags):
 *  - UTF-8, ungültige Bytefolgen werden zu U+FFFD,
 *  - Zeilenumbrüche als "\n", Nullbytes werden zu U+FFFD,
 *  - die Zeichen # < > ( ) \ = " ' als numerische Entities (&#35; &#60; &#62;
 *    &#40; &#41; &#92; &#61; &#34; &#39;), "&" bleibt unverändert,
 *  - "{{" und "}}" sowie einzelne geschweifte Klammern am Anfang und Ende als
 *    Entities, damit Mitglieder keine Insert-Tags einschleusen.
 *
 * Felder mit "decodeEntities" im DCA (hier die JSON-Felder prices und dates)
 * kodiert Contao nur "<" (Modus encodeLessThanSign).
 *
 * Vor dem Kodieren werden alle HTML-Entities vollständig dekodiert. Das
 * überführt die Altdaten der früheren Ajax-Skripte (htmlentities(), z. B.
 * "&ouml;", "&amp;", "&#039;", auch doppelt kodiert) in Klartext und macht die
 * Normalisierung idempotent: normalize(normalize($x)) === normalize($x).
 *
 * Contao 6 entfernt das Input-Encoding; seine Migration OutputEncodingMigration
 * dekodiert Felder in diesem Format. Beim Sprung auf Contao 6 ist dies die eine
 * Stelle, an der neue Schreibwege auf Rohtext umgestellt werden
 * (CONTAO6-BEREITSCHAFT.md, Restpunkt zur Textkodierung).
 */
final class TextNormalizer
{
    private const SPECIAL_CHARS = ['#', '<', '>', '(', ')', '\\', '=', '"', "'"];

    private const SPECIAL_ENTITIES = ['&#35;', '&#60;', '&#62;', '&#40;', '&#41;', '&#92;', '&#61;', '&#34;', '&#39;'];

    /**
     * Höchstzahl der Dekodier-Durchläufe für mehrfach kodierte Altdaten.
     */
    private const MAX_DECODE_PASSES = 10;

    /**
     * Klartextfeld (Name, Kurzbeschreibung, Adresse, Beitragstext usw.) im
     * Contao-Speicherformat.
     */
    public function normalize(string $value): string
    {
        $value = $this->prepare($value);
        $value = str_replace(self::SPECIAL_CHARS, self::SPECIAL_ENTITIES, $value);

        return $this->encodeInsertTags($value);
    }

    /**
     * Textwert eines Feldes mit "decodeEntities" (z. B. Preisbezeichnung oder
     * Terminhinweis in den JSON-Feldern prices und dates) im Contao-Speicherformat.
     */
    public function normalizeDecodedField(string $value): string
    {
        $value = str_replace('<', '&#60;', $this->prepare($value));

        // Contao kodiert die Zeichenfolge "\0" zusätzlich (Input::encodeInput()); im
        // Modus encodeAll ist der Backslash an dieser Stelle bereits kodiert.
        $value = str_replace('\0', '&#92;0', $value);

        return $this->encodeInsertTags($value);
    }

    /**
     * Normalisiert alle Zeichenketten in einer JSON-Struktur (Werte, nicht Schlüssel)
     * nach normalizeDecodedField() und gibt sie als JSON zurück: UTF-8 unmaskiert,
     * Schrägstriche unmaskiert, Objekte bleiben Objekte. Ungültiges JSON wird
     * unverändert zurückgegeben (null zeigt das an), damit nichts verloren geht.
     */
    public function normalizeJson(string $json): string|null
    {
        if ('' === trim($json)) {
            return $json;
        }

        try {
            $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return $this->encodeJson($data);
    }

    /**
     * JSON für ein Feld mit "decodeEntities" aus einer PHP-Struktur, alle
     * Zeichenketten nach normalizeDecodedField().
     */
    public function encodeJson(mixed $data): string
    {
        return json_encode($this->walk($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Klartext für Ausgaben ohne HTML (CSV, Text-Mail, Vergleich, Suche): dekodiert
     * alle Entities eines gespeicherten Wertes. Die Insert-Tag-Kodierung wird als
     * Text "{{" bzw. "}}" zurückgegeben.
     */
    public function toPlainText(string $value): string
    {
        return $this->decodeEntities($value);
    }

    /**
     * True, wenn der Wert bereits im Contao-Speicherformat vorliegt.
     */
    public function isNormalized(string $value): bool
    {
        return $this->normalize($value) === $value;
    }

    private function prepare(string $value): string
    {
        // UTF-8 sicherstellen
        if (1 !== preg_match('//u', $value)) {
            $substitute = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            mb_substitute_character($substitute);
        }

        $value = $this->decodeEntities($value);

        // Zeilenumbrüche vereinheitlichen, Nullbytes ersetzen (wie Contao)
        $value = preg_replace('(\r\n?)', "\n", $value) ?? $value;
        return str_replace("\0", "\u{FFFD}", $value);
    }

    private function decodeEntities(string $value): string
    {
        for ($i = 0; $i < self::MAX_DECODE_PASSES && str_contains($value, '&'); ++$i) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        return $value;
    }

    private function encodeInsertTags(string $value): string
    {
        $value = str_replace(['{{', '}}'], ['&#123;&#123;', '&#125;&#125;'], $value);

        // Einzelne geschweifte Klammern am Anfang und Ende ebenfalls kodieren (wie Contao)
        return preg_replace(['/^(\s*)\{|\{(\s*)$/', '/^(\s*)\}|\}(\s*)$/'], ['$1&#123;$2', '$1&#125;$2'], $value) ?? $value;
    }

    private function walk(mixed $data): mixed
    {
        if (\is_string($data)) {
            return $this->normalizeDecodedField($data);
        }

        if (\is_array($data)) {
            return array_map($this->walk(...), $data);
        }

        if ($data instanceof \stdClass) {
            $result = new \stdClass();

            foreach (get_object_vars($data) as $key => $item) {
                $result->{$key} = $this->walk($item);
            }

            return $result;
        }

        return $data;
    }
}
