<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\FieldDefinition;
use SeKultur\ContaoKulturnetzBundle\Service\Write\Value\RecordSchema;

/**
 * Whitelist der schreibbaren Felder je Typ (research/13, Abschnitt 1.3).
 *
 * Die Felder entsprechen genau dem, was die bisherigen Skripte
 * files/se-kultur.de/code/insert<Typ>.php geschrieben haben, mit zwei Ausnahmen:
 *  - tl_events.plakatentwurf fehlt: Das Formular der Veranstaltungen enthält das
 *    Feld nicht, das Altskript hat den Wert deshalb bei jeder Bearbeitung geleert.
 *  - Feldnamen der Formulare bleiben, auch wo sie von der Spalte abweichen
 *    ("kuenstlername" für tl_artists.name, "price[]"/"pricetag[]" für prices,
 *    "eventdate[]" usw. für dates).
 *
 * Pflichtangaben wie im Formular-JS (Sparte bei Künstler*innen und
 * Veranstaltungen, Beschreibung mit mindestens 30 Zeichen) und im DCA (Name, auch
 * nicht nur aus Leerzeichen); bei Veranstaltungen mindestens ein Termin
 * (RecordInputMapper::mapDates()).
 * Die Meldungstexte sind wortgleich zu den Formular-JS-Dateien.
 *
 * Formatregeln (Runde 3): E-Mail, Postleitzahl, Telefon und Internetadresse
 * (FieldFormat), Sparten, Zielgruppen, Bühnentypen und Veranstaltungsformate nur
 * aus den Listen der Formulare (FormChoices), Kurzbeschreibung Pflicht und
 * höchstens SHORT_DESCRIPTION_MAX_LENGTH Zeichen. Die Templates lesen
 * SHORT_DESCRIPTION_MAX_LENGTH und POSTAL_CODE_LENGTH als maxlength aus diesen
 * Konstanten. Die Meldungen der neuen Regeln stehen in den Sprachdateien
 * (FormMessages).
 */
final class RecordSchemaRegistry
{
    public const MESSAGE_SPARTEN = 'Geben Sie bitte mindestens eine Sparte an.';

    public const MESSAGE_BESCHREIBUNG = 'Die lange Beschreibung sollte zumindest einige Sätze lang sein...';

    public const MESSAGE_NAME = 'Bitte geben Sie einen Namen an.';

    /**
     * Mindestlänge der Beschreibung wie im Formular-JS (data.beschreibung.length < 30).
     */
    public const DESCRIPTION_MIN_LENGTH = 30;

    /**
     * Höchstlänge der Kurzbeschreibung (Formular: maxlength, Spalte text).
     */
    public const SHORT_DESCRIPTION_MAX_LENGTH = 255;

    /**
     * Länge der Postleitzahl (Formular: maxlength, Prüfung FieldFormat::PostalCode).
     */
    public const POSTAL_CODE_LENGTH = 5;

    /**
     * Freitextfelder der Profile ohne Formatregel. Social-Media-Felder enthalten im
     * Bestand auch Namen und Handles statt Adressen (z. B. "@name"), sie bleiben
     * deshalb Freitext.
     */
    private const PROFILE_TEXT_FIELDS = ['ort', 'adresse', 'xing', 'linkedin', 'tiktok', 'instagram', 'facebook'];

    private const LOCATION_FIELDS = ['barrierefreiheit', 'technik'];

    /**
     * Maße und Plätze der Locations: im Formular type="number"
     * (kulturnetz/profile/_form_location.html.twig), gespeichert als Text.
     */
    private const LOCATION_NUMBER_FIELDS = ['auftrittsflaeche', 'gesamtflaeche', 'sitzplaetze', 'stehplaetze'];

    /**
     * Adressangaben zu Ort bzw. Veranstalter*in einer Veranstaltung (Präfix
     * location_ bzw. host_): Freitext und Felder mit Formatregel.
     */
    private const EVENT_ADDRESS_TEXT_FIELDS = ['adresse', 'ort'];

    /**
     * Kontaktfelder mit Formatregel, in Profilen ohne Präfix, in Veranstaltungen
     * mit location_ bzw. host_.
     */
    private const CONTACT_FORMAT_FIELDS = [
        'plz' => FieldFormat::PostalCode,
        'telefon' => FieldFormat::Phone,
        'email' => FieldFormat::Email,
        'website' => FieldFormat::Url,
    ];

    private const SEKEVENT_MATERIAL_FIELDS = ['programmhefte', 'werbeplakate_a4', 'werbeplakate_a3', 'werbeplakate_a1', 'werbeplakate_a0'];

    /**
     * @var array<string, RecordSchema>
     */
    private array $schemas = [];

    public function __construct(
        private readonly FormChoices $choices,
        private readonly FormMessages $messages,
    ) {
    }

    public function has(string $type): bool
    {
        return \in_array($type, KulturnetzConfig::TYPES, true);
    }

    /**
     * Schema eines Typs; unbekannte Typen sind ein Programmierfehler (die Routen
     * lassen nur die fünf Typen zu).
     */
    public function get(string $type): RecordSchema
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        return $this->schemas[$type] ??= new RecordSchema($type, 'name', $this->buildFields($type));
    }

    /**
     * @return list<FieldDefinition>
     */
    private function buildFields(string $type): array
    {
        return match ($type) {
            'artists' => [
                FieldDefinition::text('name', 'kuenstlername', self::MESSAGE_NAME),
                $this->description(),
                ...$this->contactFields(),
                FieldDefinition::choice('sparten', $this->choices->categories(), self::MESSAGE_SPARTEN),
                ...$this->texts(['themen', 'zitat']),
                FieldDefinition::avatar(),
            ],
            'hosts' => [
                FieldDefinition::text('name', null, self::MESSAGE_NAME),
                $this->description(),
                ...$this->contactFields(),
                ...$this->texts(['themen', 'zitat']),
                FieldDefinition::avatar(),
            ],
            'locations' => [
                FieldDefinition::text('name', null, self::MESSAGE_NAME),
                $this->description(),
                ...$this->contactFields(),
                ...$this->texts(['themen', 'zitat', ...self::LOCATION_FIELDS]),
                FieldDefinition::choice('buehnentyp', $this->choices->stageTypes()),
                ...array_map(FieldDefinition::number(...), self::LOCATION_NUMBER_FIELDS),
                FieldDefinition::avatar(),
            ],
            'events' => $this->eventFields(false),
            'sekevents' => $this->eventFields(true),
        };
    }

    /**
     * @return list<FieldDefinition>
     */
    private function eventFields(bool $sekultur): array
    {
        $fields = [
            FieldDefinition::text('name', null, self::MESSAGE_NAME),
            $this->description(),
            $this->shortDescription(),
        ];

        if ($sekultur) {
            $fields[] = FieldDefinition::checkbox('plakatentwurf');

            foreach (self::SEKEVENT_MATERIAL_FIELDS as $column) {
                $fields[] = FieldDefinition::integer($column);
            }

            $fields[] = FieldDefinition::checkbox('wimpelketten');
        }

        $fields[] = FieldDefinition::choice('sparten', $this->choices->categories(), self::MESSAGE_SPARTEN);

        if ($sekultur) {
            $fields[] = FieldDefinition::choice('kulturform', $this->choices->sekEventFormats());
        }

        return [
            ...$fields,
            FieldDefinition::choice('zielgruppen', $this->choices->audiences()),
            FieldDefinition::text('artists'),
            FieldDefinition::linkedIds('linked_artists', 'artists'),
            FieldDefinition::checkbox('eintrittfrei'),
            FieldDefinition::prices(),
            FieldDefinition::text('pricecomment'),
            FieldDefinition::checkbox('spenden'),
            FieldDefinition::checkbox('anmeldung'),
            FieldDefinition::text('anmeldestelle'),
            FieldDefinition::dates(),
            FieldDefinition::text('locations'),
            FieldDefinition::linkedIds('linked_locations', 'locations'),
            ...$this->eventAddressFields('location_'),
            FieldDefinition::text('location_barrierefreiheit'),
            FieldDefinition::text('hosts'),
            FieldDefinition::linkedIds('linked_hosts', 'hosts'),
            ...$this->eventAddressFields('host_'),
            FieldDefinition::text('kooperationspartner'),
            FieldDefinition::avatar(),
        ];
    }

    /**
     * Kurzbeschreibung: im Formular Pflicht (required) und maxlength.
     */
    private function shortDescription(): FieldDefinition
    {
        return FieldDefinition::requiredText('kurzbeschreibung', $this->messages->get('kulturnetz_short_description'), self::SHORT_DESCRIPTION_MAX_LENGTH);
    }

    /**
     * Kontaktdaten, Internetadresse, Social Media und Kurzbeschreibung der Profile.
     *
     * @return list<FieldDefinition>
     */
    private function contactFields(): array
    {
        $fields = [$this->shortDescription(), ...$this->texts(self::PROFILE_TEXT_FIELDS)];

        foreach (self::CONTACT_FORMAT_FIELDS as $column => $format) {
            $fields[] = FieldDefinition::formatted($column, $format);
        }

        return $fields;
    }

    /**
     * @return list<FieldDefinition>
     */
    private function eventAddressFields(string $prefix): array
    {
        $fields = $this->texts(array_map(static fn (string $column): string => $prefix.$column, self::EVENT_ADDRESS_TEXT_FIELDS));

        foreach (self::CONTACT_FORMAT_FIELDS as $column => $format) {
            $fields[] = FieldDefinition::formatted($prefix.$column, $format);
        }

        return $fields;
    }

    private function description(): FieldDefinition
    {
        return FieldDefinition::html('beschreibung', self::DESCRIPTION_MIN_LENGTH, self::MESSAGE_BESCHREIBUNG);
    }

    /**
     * @param list<string> $columns
     *
     * @return list<FieldDefinition>
     */
    private function texts(array $columns): array
    {
        return array_map(static fn (string $column): FieldDefinition => FieldDefinition::text($column), $columns);
    }
}
