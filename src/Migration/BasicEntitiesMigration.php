<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Migration;

use Contao\CoreBundle\Migration\Version500\AbstractBasicEntitiesMigration;

/**
 * Wandelt die Basic-Entity-Platzhalter ([nbsp], [-], [&], [&amp;], [lt],
 * [gt]) in den Textspalten der Bundle-Tabellen in echte HTML-Entities um.
 *
 * Contao 4.13 hat diese Platzhalter beim Speichern im Backend erzeugt und erst
 * beim Rendern der ganzen Seite ersetzt. Contao 5 ersetzt sie nicht mehr; die
 * Core-Migration Version500\BasicEntitiesMigration deckt nur die Tabellen des
 * Kerns ab. Ohne diese Migration stünde z. B. "[nbsp]" sichtbar in den
 * Beschreibungen der Profile und Veranstaltungen.
 *
 * Erfasst sind die Freitextspalten, die im Frontend, in den Bearbeiten-
 * Formularen oder im Backend ausgegeben werden. Technische Spalten (alias,
 * linked_*, dates, Binärspalten, Koordinaten, Schalter) bleiben außen vor.
 *
 * Das Archiv tl_sekevents_2024 ist bewusst nicht enthalten und wird nie
 * verändert.
 *
 * Laufbedingung und Idempotenz kommen aus der Basisklasse: Die Migration läuft
 * nur, solange die Contao-4-Spalten tl_article.keywords und tl_theme.vars noch
 * existieren (also ohne --with-deletes) und mindestens eine der Spalten noch
 * einen Platzhalter enthält.
 */
class BasicEntitiesMigration extends AbstractBasicEntitiesMigration
{
    private const PROFIL_SPALTEN = [
        'name',
        'sparten',
        'themen',
        'website',
        'facebook',
        'instagram',
        'tiktok',
        'linkedin',
        'xing',
        'email',
        'telefon',
        'adresse',
        'plz',
        'ort',
        'zitat',
        'kurzbeschreibung',
        'beschreibung',
        'comment',
    ];

    private const LOCATION_SPALTEN = [
        'barrierefreiheit',
        'auftrittsflaeche',
        'gesamtflaeche',
        'buehnentyp',
        'technik',
        'sitzplaetze',
        'stehplaetze',
    ];

    private const EVENT_SPALTEN = [
        'name',
        'sparten',
        'kulturform',
        'zielgruppen',
        'artists',
        'prices',
        'pricecomment',
        'anmeldestelle',
        'locations',
        'location_adresse',
        'location_plz',
        'location_ort',
        'location_barrierefreiheit',
        'location_telefon',
        'location_email',
        'location_website',
        'hosts',
        'host_adresse',
        'host_plz',
        'host_ort',
        'host_telefon',
        'host_email',
        'host_website',
        'kooperationspartner',
        'kurzbeschreibung',
        'beschreibung',
        'comment',
    ];

    public function getName(): string
    {
        return 'KulturNetz: Basic Entities in den Bundle-Tabellen umwandeln';
    }

    protected function getDatabaseColumns(): array
    {
        $columns = [];

        // tl_hosts und tl_locations haben keine Spalte "sparten"; die
        // Basisklasse überspringt nicht vorhandene Spalten.
        foreach (['tl_artists', 'tl_hosts', 'tl_locations'] as $table) {
            foreach (self::PROFIL_SPALTEN as $column) {
                $columns[] = [$table, $column];
            }
        }

        foreach (self::LOCATION_SPALTEN as $column) {
            $columns[] = ['tl_locations', $column];
        }

        foreach (['tl_events', 'tl_sekevents'] as $table) {
            foreach (self::EVENT_SPALTEN as $column) {
                $columns[] = [$table, $column];
            }
        }

        $columns[] = ['tl_postings', 'content'];

        return $columns;
    }
}
