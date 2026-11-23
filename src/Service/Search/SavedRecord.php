<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Search;

/**
 * SQL-Bedingung "gespeicherter Datensatz" für alle Frontend-Abfragen auf die
 * Profile (tl_artists, tl_hosts, tl_locations) und Veranstaltungen (tl_events,
 * tl_sekevents).
 *
 * "Neu" im Backend legt sofort eine leere Zeile mit tstamp = 0 an, erst das erste
 * Speichern setzt tstamp. Contao löscht die unfertige Zeile nur, wenn die
 * Redaktion die Maske über "Zurück" verlässt (DC_Table::reviseTable() mit dem
 * Parameter "revise", in 4.13 und 5.7 gleich). Wird die Maske anders verlassen
 * (Tab schließen, Liste direkt aufrufen), bleibt die Zeile stehen. Eine
 * unfertige Kopie ("Duplizieren" vor dem Speichern) hat ebenfalls tstamp = 0.
 * Solche Zeilen sind keine Inhalte und erscheinen im Frontend nirgends: weder in
 * Listen, Suche, Vorschlägen und Facetten noch per Direktlink.
 *
 * Frontend-Anlagen (Service\Write\RecordWriter) setzen tstamp immer.
 */
final class SavedRecord
{
    /**
     * Bedingung für die WHERE-Klausel, Spalten ohne Tabellenpräfix.
     */
    public const CONDITION = 'tstamp > 0';

    private function __construct()
    {
    }
}
