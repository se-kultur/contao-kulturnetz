<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
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
 * Zahlenfelder der Locations, Preis- und Terminzeilen (mapPrices(), mapDates()),
 * Formate (FieldFormat), Auswahlwerte (FieldDefinition::$choices) und die
 * Höchstlänge des Formulars (FieldDefinition::$maxLength). Meldungen stehen unter
 * dem Formularfeldnamen (FieldDefinition::formName()).
 *
 * Bestandsdaten: Formate, Auswahlwerte, Höchstlängen, Preis- und Terminformate
 * gelten nur für neue bzw. veränderte Werte. Beim Bearbeiten bleibt ein
 * unveränderter Bestandswert (z. B. eine ältere Kurzbeschreibung über 255
 * Zeichen, eine Terminzeile "24:00" oder eine nicht mehr angebotene Auswahl)
 * speicherbar, damit ein Mitglied eine Änderung an anderer Stelle nicht wegen
 * eines Altwertes verliert. Verglichen wird im Speicherformat.
 *
 * Beim Bearbeiten bleiben optionale Text-, Zahlen-, Bild- und Verknüpfungsfelder,
 * die die Anfrage nicht enthält, unverändert; ein fehlendes Pflichtfeld wird wie
 * ein leeres abgelehnt. Checkboxen, Preise und Termine werden immer
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

    /**
     * Uhrzeit HH:MM von 00:00 bis 23:59 (Formular: Eingabemaske 99:99).
     */
    private const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    /**
     * Preis wie die Eingabemaske des Formulars ("9{1,3},99 €"); unvollständige
     * Nachkommastellen sendet die Maske als "_" (z. B. "20,__ €", im Bestand häufig).
     */
    private const PRICE_PATTERN = '/^\d{1,3},(?:\d{2}|\d_|__) €$/u';

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly OwnershipChecker $ownership,
        private readonly LinkedProfileFilter $linkedProfiles,
        private readonly ColumnLimits $limits,
        private readonly Connection $connection,
        private readonly KulturnetzConfig $config,
        private readonly FormMessages $messages,
        private readonly ClockInterface $clock,
        private readonly EventDateRange $dateRange,
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
            } catch (RecordValidationException $exception) {
                $errors = [...$errors, ...$exception->getFieldErrors()];

                continue;
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
            FieldKind::Prices => [$this->checkLength($schema, $field, $this->mapPrices($input, \array_key_exists('eintrittfrei', $input), $current))],
            FieldKind::Dates => [$this->checkLength($schema, $field, $this->mapDates($schema, $input, $current))],
            default => match (true) {
                $present => [$this->mapValue($schema, $field, $raw, $memberId, $current)],
                $creating, null !== $field->requiredMessage => [$this->defaultValue($field)],
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
            FieldKind::Text => $this->mapText($schema, $field, $raw, $current),
            FieldKind::Html => $this->mapHtml($schema, $field, $raw),
            FieldKind::Integer => $this->mapInteger($raw),
            FieldKind::Number => $this->mapNumber($schema, $field, $raw),
            FieldKind::Avatar => $this->mapAvatar($raw, $memberId, $current['avatar'] ?? null),
            FieldKind::LinkedIds => $this->checkLength($schema, $field, $this->mapLinkedIds($field, $raw, $memberId, $current)),
            default => throw new \LogicException(\sprintf('Feldart %s wird hier nicht behandelt.', $field->kind->name)),
        };
    }

    /**
     * Wert eines fehlenden Feldes (wie bisher leer bzw. 0, Titelbild NULL). Ein
     * fehlendes Pflichtfeld zählt wie ein leeres und löst die Meldung aus dem
     * Formular aus, beim Anlegen wie beim Bearbeiten (die Formulare senden
     * Pflichtfelder immer mit, auch wenn sie leer sind).
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

    /**
     * @param array<string, mixed>|null $current
     */
    private function mapText(RecordSchema $schema, FieldDefinition $field, string $raw, array|null $current): string
    {
        $value = $this->normalizer->normalize($raw);

        // Pflichtfelder: nur Leerzeichen gelten wie ein leeres Feld als fehlend.
        // Name und Sparten werden ohne Leerraum am Rand gespeichert, die
        // Kurzbeschreibung wie eingegeben (FieldDefinition::$trimRequired).
        if (null !== $field->requiredMessage) {
            if (self::isBlank($value)) {
                throw new \UnexpectedValueException($field->requiredMessage);
            }

            if ($field->trimRequired) {
                $value = self::trimText($value);
            }
        }

        $previous = $this->storedText($current, $field->column);

        if (null === $previous || self::trimText($value) !== self::trimText($previous)) {
            $this->checkTextRules($field, $raw, $value, $previous);
        }

        return $this->checkLength($schema, $field, $value);
    }

    /**
     * Gespeicherter Wert einer Spalte im Speicherformat (für den Vergleich mit dem
     * gesendeten Wert), null beim Anlegen.
     *
     * @param array<string, mixed>|null $current
     */
    private function storedText(array|null $current, string $column): string|null
    {
        if (null === $current) {
            return null;
        }

        $stored = $current[$column] ?? '';

        return $this->normalizer->normalize(\is_scalar($stored) ? (string) $stored : '');
    }

    /**
     * Höchstlänge des Formulars, Format und Auswahlwerte eines neuen oder
     * veränderten Klartextwertes.
     *
     * @param string      $raw      gesendeter Wert
     * @param string      $value    gesendeter Wert im Speicherformat
     * @param string|null $previous gespeicherter Wert im Speicherformat (null beim Anlegen)
     */
    private function checkTextRules(FieldDefinition $field, string $raw, string $value, string|null $previous): void
    {
        // Wie maxlength im Browser: Zeilenumbruch als ein Zeichen, UTF-16-Zählung.
        if ($field->maxLength > 0 && self::jsLength(preg_replace('/\r\n?/', "\n", $raw) ?? $raw) > $field->maxLength) {
            throw new \UnexpectedValueException(\sprintf(self::MESSAGE_TOO_LONG_CHARACTERS, $field->maxLength));
        }

        $plain = self::trimText($this->normalizer->toPlainText($value));

        if ('' === $plain) {
            return;
        }

        if (null !== $field->format && !$field->format->matches($plain)) {
            throw new \UnexpectedValueException($this->messages->get($field->format->messageKey()));
        }

        if (null !== $field->choices) {
            // Bereits gespeicherte Einträge bleiben zulässig (z. B. ein früherer
            // Freitext-Bühnentyp), neu gewählte müssen aus der Liste stammen.
            $allowed = [...$field->choices, ...self::splitChoices($this->normalizer->toPlainText($previous ?? ''))];

            foreach (self::splitChoices($plain) as $item) {
                if (!\in_array($item, $allowed, true)) {
                    throw new \UnexpectedValueException($this->messages->get('kulturnetz_choice'));
                }
            }
        }
    }

    /**
     * Einträge einer kommagetrennten Auswahl wie js/multiselect.js (getrimmt, ohne leere).
     *
     * @return list<string>
     */
    private static function splitChoices(string $value): array
    {
        return array_values(array_filter(array_map(self::trimText(...), explode(',', $value)), static fn (string $item): bool => '' !== $item));
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
     * Dateien ergeben NULL (eine leere Angabe wurde bisher als 16 Null-Byte
     * gespeichert).
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
     * Ein Preis hat das Format der Eingabemaske (PRICE_PATTERN). Unveränderte
     * Bestandszeilen beim Bearbeiten bleiben ungeprüft.
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current
     */
    private function mapPrices(array $input, bool $freeEntry, array|null $current): string
    {
        $prices = $this->arrayInput($input, 'price');
        $tags = $this->arrayInput($input, 'pricetag');
        $max = $this->config->getRecordMaxPrices();

        if (\count($prices) > $max) {
            throw new FieldValueException(\sprintf(self::MESSAGE_TOO_MANY_PRICES, $max));
        }

        $rows = [];
        $storedRows = $this->storedRowKeys($current, 'prices', ['pricetag', 'price']);

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

            $price = self::trimText($row['price']);

            if ('' !== $price && 1 !== preg_match(self::PRICE_PATTERN, $price) && !isset($storedRows[$this->rowKey($row)])) {
                throw new FieldValueException($this->messages->get('kulturnetz_price'), 'price[]');
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
     * Jede Zeile braucht ein gültiges Datum (Veranstaltungen TT.MM.JJJJ,
     * SE-KulturTage TT.MM., beide mit Kalenderprüfung; bei Veranstaltungen
     * zusätzlich im Zeitraum des Datepickers, EventDateRange) sowie Beginn und Ende im
     * Format HH:MM von 00:00 bis 23:59. Ende vor Beginn bleibt erlaubt
     * (Veranstaltungen über Mitternacht). Ohne Beginn erscheint ein Termin in
     * keiner Liste (Event\Date\*Dates), deshalb sind beide Zeiten Pflicht.
     * Unveränderte Bestandszeilen beim Bearbeiten bleiben ungeprüft. Fehler stehen
     * je Formularfeld mit der Nummer der ersten betroffenen Zeile.
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $current
     *
     * @throws RecordValidationException bei ungültigen Terminzeilen
     */
    private function mapDates(RecordSchema $schema, array $input, array|null $current): string
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
        $errors = [];
        $yearless = EventType::tryFrom($schema->type)?->datesStoredWithoutYear() ?? false;
        $storedRows = $this->storedRowKeys($current, 'dates', ['date', 'start', 'end']);

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

            if (isset($storedRows[$this->rowKey($row, ['date', 'start', 'end'])])) {
                continue;
            }

            foreach ($this->checkDateRow($row, $yearless) as $formName => $message) {
                $errors[$formName] ??= $this->messages->get('kulturnetz_date_row', [\count($rows), $message]);
            }
        }

        if ([] !== $errors) {
            throw new RecordValidationException($errors);
        }

        if ([] === $rows) {
            throw new FieldValueException(self::MESSAGE_DATES_MISSING);
        }

        return $this->normalizer->encodeJson($rows);
    }

    /**
     * Meldungen einer Terminzeile je Formularfeld (leer = gültig).
     *
     * @param array{date: string, start: string, end: string, comment: string} $row
     *
     * @return array<string, string>
     */
    private function checkDateRow(array $row, bool $yearless): array
    {
        $errors = [];

        $date = self::trimText($row['date']);

        if (!$this->isValidDate($date, $yearless)) {
            $errors['eventdate[]'] = $this->messages->get($yearless ? 'kulturnetz_date_yearless' : 'kulturnetz_date');
        } elseif (!$yearless && !$this->dateRange->contains($date)) {
            $errors['eventdate[]'] = $this->messages->get('kulturnetz_date_range', [
                $this->dateRange->getMin()->format('d.m.Y'),
                $this->dateRange->getMax()->format('d.m.Y'),
            ]);
        }

        foreach (['start' => 'eventtimestart[]', 'end' => 'eventtimeend[]'] as $key => $formName) {
            $time = self::trimText($row[$key]);

            if ('' === $time) {
                $errors[$formName] = $this->messages->get('kulturnetz_time_missing');
            } elseif (1 !== preg_match(self::TIME_PATTERN, $time)) {
                $errors[$formName] = $this->messages->get('kulturnetz_time_format');
            }
        }

        return $errors;
    }

    /**
     * Datum TT.MM.JJJJ bzw. bei den SE-KulturTagen TT.MM. (Jahr des Festivals ist
     * das laufende Jahr, wie im Formular angezeigt), jeweils ein Kalendertag.
     */
    private function isValidDate(string $date, bool $yearless): bool
    {
        $pattern = $yearless ? '/^(\d{2})\.(\d{2})\.$/' : '/^(\d{2})\.(\d{2})\.(\d{4})$/';

        if (1 !== preg_match($pattern, $date, $matches)) {
            return false;
        }

        $year = $yearless ? (int) $this->clock->now()->format('Y') : (int) $matches[3];

        return $year > 0 && checkdate((int) $matches[2], (int) $matches[1], $year);
    }

    /**
     * Schlüssel der gespeicherten Zeilen eines JSON-Feldes (prices, dates) zum
     * Erkennen unveränderter Bestandszeilen.
     *
     * @param array<string, mixed>|null $current
     * @param list<string>              $keys
     *
     * @return array<string, true>
     */
    private function storedRowKeys(array|null $current, string $column, array $keys): array
    {
        $stored = $current[$column] ?? null;

        if (!\is_string($stored) || '' === $stored) {
            return [];
        }

        $data = json_decode($stored, true);

        if (!\is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $row) {
            if (\is_array($row)) {
                $result[$this->rowKey(array_map(static fn (mixed $item): string => \is_scalar($item) ? (string) $item : '', $row), $keys)] = true;
            }
        }

        return $result;
    }

    /**
     * Vergleichsschlüssel einer Zeile: die Werte im Klartext ohne Leerraum am Rand.
     *
     * @param array<string, string> $row
     * @param list<string>|null     $keys Standard: alle Werte der Zeile
     */
    private function rowKey(array $row, array|null $keys = null): string
    {
        $values = [];

        foreach ($keys ?? array_keys($row) as $key) {
            $values[] = self::trimText($this->normalizer->toPlainText($row[$key] ?? ''));
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
