<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Text\HtmlSanitizer;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\FieldDefinition;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\RecordSchema;

/**
 * Überführt die Formularwerte der Anlegen- und Bearbeiten-Formulare in
 * Spaltenwerte und prüft sie.
 *
 * Anfrageformat wie heute: Die Profilformulare (artistform.js, hostform.js,
 * locationform.js) senden ein zusammengeführtes Objekt (Name => Wert), die
 * Veranstaltungsformulare (eventform.js, sekeventform.js) serializeArray() mit
 * Feldern wie price[] und eventdate[]. Felder außerhalb der Whitelist
 * (RecordSchemaRegistry) werden ignoriert, auch member_id, alias und security.
 *
 * Speicherformat:
 *  - Klartext im Contao-Format (TextNormalizer::normalize(), E21),
 *  - beschreibung gefiltert über HtmlSanitizer,
 *  - prices und dates als JSON mit UTF-8 ohne Maskierung (TextNormalizer::encodeJson()),
 *    "€" steht damit korrekt in der Datenbank (bisher "u20ac"); das Datum bleibt
 *    wie eingegeben (Veranstaltungen TT.MM.JJJJ, SE-KulturTage TT.MM.),
 *  - Checkboxen "1" oder leer, Zahlen als Ganzzahl,
 *  - avatar binär oder NULL, verknüpfte Profile als ["12","34"].
 *
 * Prüfungen über die Spaltenlängen hinaus: Pflichtfelder ohne bloßen Leerraum,
 * Zahlenfelder der Locations, Preis- und Terminzeilen (mapPrices(), mapDates()).
 * Meldungen stehen unter dem Formularfeldnamen (FieldDefinition::formName()).
 *
 * Beim Bearbeiten bleiben Text-, Zahlen-, Bild- und Verknüpfungsfelder, die die
 * Anfrage nicht enthält, unverändert. Checkboxen, Preise und Termine werden immer
 * geschrieben, weil ein Formular sie bei "aus" bzw. ohne Zeilen nicht sendet.
 */
final class RecordInputMapper
{
    public const MESSAGE_INVALID = 'Ungültige Eingabe.';

    public const MESSAGE_TOO_LONG = 'Die Eingabe ist zu lang.';

    public const MESSAGE_TOO_LONG_CHARACTERS = 'Die Eingabe ist zu lang (höchstens %d Zeichen).';

    public const MESSAGE_NOT_A_NUMBER = 'Bitte geben Sie eine Zahl ein.';

    public const MESSAGE_DATES_MISSING = 'Bitte geben Sie mindestens einen Termin an.';

    public const MESSAGE_DATE_MISSING = 'Bitte geben Sie für jeden Termin ein Datum an.';

    public const MESSAGE_TOO_MANY_DATES = 'Bitte geben Sie höchstens %d Termine an.';

    public const MESSAGE_PRICETAG_MISSING = 'Bitte geben Sie für jeden Preis einen Titel an.';

    public const MESSAGE_TOO_MANY_PRICES = 'Bitte geben Sie höchstens %d Preise an.';

    private const UINT_MAX = 4294967295;

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly OwnershipChecker $ownership,
        private readonly LinkedProfileFilter $linkedProfiles,
        private readonly ColumnLimits $limits,
        private readonly Connection $connection,
        private readonly KulturnetzConfig $config,
    ) {
    }

    /**
     * Spaltenwerte zu den Formularwerten.
     *
     * @param array<string, mixed>      $input   POST-Daten
     * @param array<string, mixed>|null $current gespeicherte Zeile beim Bearbeiten, null beim Anlegen
     *
     * @return array<string, string|int|null> Spalte => Wert (nur zu schreibende Spalten)
     *
     * @throws RecordValidationException mit den Feldfehlern (Formularfeldname => Meldung)
     */
    public function map(RecordSchema $schema, array $input, int $memberId, array|null $current = null): array
    {
        $values = [];
        $errors = [];

        foreach ($schema->fields as $field) {
            try {
                $result = $this->mapField($schema, $field, $input, $memberId, $current);
            } catch (\UnexpectedValueException $exception) {
                $formName = $exception instanceof FieldValueException ? $exception->formName : null;
                $errors[$formName ?? $field->formName()] = $exception->getMessage();

                continue;
            }

            if (null !== $result) {
                $values[$field->column] = $result[0];
            }
        }

        if ([] !== $errors) {
            throw new RecordValidationException($errors);
        }

        return $values;
    }

    /**
     * Länge eines Wertes, wie JavaScript sie zählt (UTF-16-Codeeinheiten), damit
     * der Server nichts ablehnt, was das Formular-JS durchlässt.
     */
    public static function jsLength(string $value): int
    {
        return intdiv(\strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current
     *
     * @return array{0: string|int|null}|null null = Spalte nicht schreiben
     *
     * @throws \UnexpectedValueException mit der Meldung für das Feld
     */
    private function mapField(RecordSchema $schema, FieldDefinition $field, array $input, int $memberId, array|null $current): array|null
    {
        $present = \array_key_exists($field->requestName, $input);
        $raw = $input[$field->requestName] ?? null;
        $creating = null === $current;

        return match ($field->kind) {
            FieldKind::Checkbox => [$present ? '1' : ''],
            FieldKind::Prices => [$this->checkLength($schema, $field, $this->mapPrices($input, \array_key_exists('eintrittfrei', $input)))],
            FieldKind::Dates => [$this->checkLength($schema, $field, $this->mapDates($input))],
            default => match (true) {
                $present => [$this->mapValue($schema, $field, $raw, $memberId, $current)],
                $creating => [$this->defaultValue($field)],
                default => null,
            },
        };
    }

    /**
     * Wert eines gesendeten Feldes (ohne Checkbox, Preise, Termine).
     *
     * @param array<string, mixed>|null $current
     */
    private function mapValue(RecordSchema $schema, FieldDefinition $field, mixed $raw, int $memberId, array|null $current): string|int|null
    {
        if (!\is_string($raw)) {
            throw new \UnexpectedValueException(self::MESSAGE_INVALID);
        }

        return match ($field->kind) {
            FieldKind::Text => $this->mapText($schema, $field, $raw),
            FieldKind::Html => $this->mapHtml($schema, $field, $raw),
            FieldKind::Integer => $this->mapInteger($raw),
            FieldKind::Number => $this->mapNumber($schema, $field, $raw),
            FieldKind::Avatar => $this->mapAvatar($raw, $memberId, $current['avatar'] ?? null),
            FieldKind::LinkedIds => $this->checkLength($schema, $field, $this->mapLinkedIds($field, $raw, $memberId, $current)),
            default => throw new \LogicException(\sprintf('Feldart %s wird hier nicht behandelt.', $field->kind->name)),
        };
    }

    /**
     * Wert eines beim Anlegen fehlenden Feldes (wie bisher leer bzw. 0, Titelbild NULL).
     * Pflichtfelder und die Beschreibung lösen die Meldung aus dem Formular-JS aus.
     */
    private function defaultValue(FieldDefinition $field): string|int|null
    {
        if (null !== $field->requiredMessage) {
            throw new \UnexpectedValueException($field->requiredMessage);
        }

        return match ($field->kind) {
            FieldKind::Integer => 0,
            FieldKind::Avatar => null,
            FieldKind::LinkedIds => '[]',
            default => '',
        };
    }

    private function mapText(RecordSchema $schema, FieldDefinition $field, string $raw): string
    {
        $value = $this->normalizer->normalize($raw);

        // Pflichtfelder (Name, Sparten): ohne Leerraum am Rand gespeichert, nur
        // Leerzeichen gelten wie ein leeres Feld als fehlend.
        if (null !== $field->requiredMessage) {
            $value = self::trimText($value);

            if ('' === $value) {
                throw new \UnexpectedValueException($field->requiredMessage);
            }
        }

        return $this->checkLength($schema, $field, $value);
    }

    /**
     * Zahl eines Formularfeldes type="number" in einer Textspalte. Leer ist
     * erlaubt; sonst eine nicht negative Zahl, höchstens zwei Nachkommastellen mit
     * Punkt (Browser) oder Komma.
     */
    private function mapNumber(RecordSchema $schema, FieldDefinition $field, string $raw): string
    {
        $value = self::trimText($raw);

        if ('' !== $value && 1 !== preg_match('/^\d{1,9}(?:[.,]\d{1,2})?$/', $value)) {
            throw new \UnexpectedValueException(self::MESSAGE_NOT_A_NUMBER);
        }

        return $this->checkLength($schema, $field, $value);
    }

    private function mapHtml(RecordSchema $schema, FieldDefinition $field, string $raw): string
    {
        // Wie das Formular-JS: Länge des gesendeten Wertes (HTML aus dem Editor)
        if ($field->minLength > 0 && self::jsLength($raw) < $field->minLength) {
            throw new \UnexpectedValueException($field->minLengthMessage ?? self::MESSAGE_INVALID);
        }

        return $this->checkLength($schema, $field, $this->sanitizer->sanitize($raw));
    }

    private function mapInteger(string $raw): int
    {
        $raw = trim($raw);

        if ('' === $raw) {
            return 0;
        }

        if (1 !== preg_match('/^\d{1,10}$/', $raw) || (int) $raw > self::UINT_MAX) {
            throw new \UnexpectedValueException(self::MESSAGE_INVALID);
        }

        return (int) $raw;
    }

    /**
     * Titelbild: nur eine Datei im Mitgliedsordner oder das bisherige Titelbild des
     * Datensatzes (z. B. von der Redaktion gesetzt). Leere, unbekannte oder fremde
     * Dateien ergeben NULL (bisher wurde jede UUID ungeprüft übernommen, eine leere
     * als 16 Null-Byte).
     */
    private function mapAvatar(string $raw, int $memberId, mixed $currentAvatar): string|null
    {
        $raw = trim($raw);

        if (1 === preg_match('/^[0-9a-f]{8}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{4}-?[0-9a-f]{12}$/i', $raw)) {
            $binary = hex2bin(str_replace('-', '', $raw));
        } else {
            return null;
        }

        if (false === $binary || !$this->fileExists($binary)) {
            return null;
        }

        if ($binary === $currentAvatar || $this->ownership->isFileInHomeDir($binary, $memberId)) {
            return $binary;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $current
     */
    private function mapLinkedIds(FieldDefinition $field, string $raw, int $memberId, array|null $current): string
    {
        $currentIds = LinkedProfileFilter::parseStored($current[$field->column] ?? null);

        try {
            return $this->linkedProfiles->filter((string) $field->linkedType, $raw, $memberId, $currentIds);
        } catch (\UnexpectedValueException) {
            throw new \UnexpectedValueException(self::MESSAGE_INVALID);
        }
    }

    /**
     * Preiszeilen wie bisher: je Eintrag in price[] die Bezeichnung aus pricetag[]
     * mit gleichem Index.
     *
     * Zeilen ohne Titel und ohne Preis werden verworfen (sie zeigen nichts an). Eine
     * Zeile mit Preis braucht einen Titel (im Formular als Pflichtfeld markiert),
     * außer bei freiem Eintritt: Dann sind die Preise ausgeblendet und werden wie
     * bisher unverändert gespeichert. Höchstens records.max_prices Zeilen.
     *
     * @param array<string, mixed> $input
     */
    private function mapPrices(array $input, bool $freeEntry): string
    {
        $prices = $this->arrayInput($input, 'price');
        $tags = $this->arrayInput($input, 'pricetag');
        $max = $this->config->getRecordMaxPrices();

        if (\count($prices) > $max) {
            throw new FieldValueException(\sprintf(self::MESSAGE_TOO_MANY_PRICES, $max));
        }

        $rows = [];

        foreach ($prices as $index => $price) {
            $row = [
                'pricetag' => $this->stringItem($tags[$index] ?? ''),
                'price' => $this->stringItem($price),
            ];

            if (self::isBlank($row['pricetag'])) {
                if (self::isBlank($row['price'])) {
                    continue;
                }

                if (!$freeEntry) {
                    throw new FieldValueException(self::MESSAGE_PRICETAG_MISSING, 'pricetag[]');
                }
            }

            $rows[] = $row;
        }

        return $this->normalizer->encodeJson($rows);
    }

    /**
     * Terminzeilen wie bisher: je Eintrag in eventdate[] Beginn, Ende und Hinweis
     * mit gleichem Index. Das Datum bleibt im eingegebenen Format.
     *
     * Vollständig leere Zeilen werden verworfen, jede übrige Zeile braucht ein
     * Datum, und mindestens ein Termin ist Pflicht (im Formular als Pflichtfeld
     * markiert; das Anlegen-Formular sendet die erste Zeile auch leer). Höchstens
     * records.max_dates Zeilen.
     *
     * @param array<string, mixed> $input
     */
    private function mapDates(array $input): string
    {
        $dates = $this->arrayInput($input, 'eventdate');
        $starts = $this->arrayInput($input, 'eventtimestart');
        $ends = $this->arrayInput($input, 'eventtimeend');
        $comments = $this->arrayInput($input, 'eventcomment');
        $max = $this->config->getRecordMaxDates();

        if (\count($dates) > $max) {
            throw new FieldValueException(\sprintf(self::MESSAGE_TOO_MANY_DATES, $max));
        }

        $rows = [];

        foreach ($dates as $index => $date) {
            $row = [
                'date' => $this->stringItem($date),
                'start' => $this->stringItem($starts[$index] ?? ''),
                'end' => $this->stringItem($ends[$index] ?? ''),
                'comment' => $this->stringItem($comments[$index] ?? ''),
            ];

            if (self::isBlank($row['date'])) {
                if (self::isBlank($row['start'].$row['end'].$row['comment'])) {
                    continue;
                }

                throw new FieldValueException(self::MESSAGE_DATE_MISSING);
            }

            $rows[] = $row;
        }

        if ([] === $rows) {
            throw new FieldValueException(self::MESSAGE_DATES_MISSING);
        }

        return $this->normalizer->encodeJson($rows);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<int|string, mixed>
     */
    private function arrayInput(array $input, string $name): array
    {
        $value = $input[$name] ?? [];

        if (!\is_array($value)) {
            throw new \UnexpectedValueException(self::MESSAGE_INVALID);
        }

        return $value;
    }

    private function stringItem(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(self::MESSAGE_INVALID);
        }

        return $value;
    }

    /**
     * Entfernt Leerraum am Rand, auch geschützte und andere Unicode-Leerzeichen.
     */
    private static function trimText(string $value): string
    {
        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? trim($value);
    }

    private static function isBlank(string $value): bool
    {
        return '' === self::trimText($value);
    }

    private function checkLength(RecordSchema $schema, FieldDefinition $field, string $value): string
    {
        if ($this->limits->fits($schema->table(), $field->column, $value)) {
            return $value;
        }

        $characters = $this->limits->getCharacterLimit($schema->table(), $field->column);

        throw new \UnexpectedValueException(null === $characters ? self::MESSAGE_TOO_LONG : \sprintf(self::MESSAGE_TOO_LONG_CHARACTERS, $characters));
    }

    private function fileExists(string $binaryUuid): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT id FROM tl_files WHERE uuid = ? AND type = 'file'",
            [$binaryUuid],
            [ParameterType::BINARY],
        );
    }
}
