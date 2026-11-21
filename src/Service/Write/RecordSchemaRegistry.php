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

    private const CONTACT_FIELDS = [
        'kurzbeschreibung', 'ort', 'plz', 'adresse', 'telefon', 'email', 'xing', 'linkedin', 'tiktok', 'instagram',
        'facebook', 'website',
    ];

    private const LOCATION_FIELDS = ['barrierefreiheit', 'buehnentyp', 'technik'];

    /**
     * Maße und Plätze der Locations: im Formular type="number"
     * (kulturnetz/profile/_form_location.html.twig), gespeichert als Text.
     */
    private const LOCATION_NUMBER_FIELDS = ['auftrittsflaeche', 'gesamtflaeche', 'sitzplaetze', 'stehplaetze'];

    private const EVENT_LOCATION_FIELDS = [
        'location_adresse', 'location_plz', 'location_ort', 'location_barrierefreiheit', 'location_telefon',
        'location_email', 'location_website',
    ];

    private const EVENT_HOST_FIELDS = ['host_adresse', 'host_plz', 'host_ort', 'host_telefon', 'host_email', 'host_website'];

    private const SEKEVENT_MATERIAL_FIELDS = ['programmhefte', 'werbeplakate_a4', 'werbeplakate_a3', 'werbeplakate_a1', 'werbeplakate_a0'];

    /**
     * @var array<string, RecordSchema>
     */
    private array $schemas = [];

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
                ...$this->texts(self::CONTACT_FIELDS),
                FieldDefinition::text('sparten', null, self::MESSAGE_SPARTEN),
                ...$this->texts(['themen', 'zitat']),
                FieldDefinition::avatar(),
            ],
            'hosts' => [
                FieldDefinition::text('name', null, self::MESSAGE_NAME),
                $this->description(),
                ...$this->texts([...self::CONTACT_FIELDS, 'themen', 'zitat']),
                FieldDefinition::avatar(),
            ],
            'locations' => [
                FieldDefinition::text('name', null, self::MESSAGE_NAME),
                $this->description(),
                ...$this->texts([...self::CONTACT_FIELDS, 'themen', 'zitat', ...self::LOCATION_FIELDS]),
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
            FieldDefinition::text('kurzbeschreibung'),
        ];

        if ($sekultur) {
            $fields[] = FieldDefinition::checkbox('plakatentwurf');

            foreach (self::SEKEVENT_MATERIAL_FIELDS as $column) {
                $fields[] = FieldDefinition::integer($column);
            }

            $fields[] = FieldDefinition::checkbox('wimpelketten');
        }

        $fields[] = FieldDefinition::text('sparten', null, self::MESSAGE_SPARTEN);

        if ($sekultur) {
            $fields[] = FieldDefinition::text('kulturform');
        }

        return [
            ...$fields,
            FieldDefinition::text('zielgruppen'),
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
            ...$this->texts(self::EVENT_LOCATION_FIELDS),
            FieldDefinition::text('hosts'),
            FieldDefinition::linkedIds('linked_hosts', 'hosts'),
            ...$this->texts(self::EVENT_HOST_FIELDS),
            FieldDefinition::text('kooperationspartner'),
            FieldDefinition::avatar(),
        ];
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
