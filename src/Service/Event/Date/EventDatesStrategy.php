<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Date;

use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventDateFilter;

/**
 * Datumsstrategie einer Veranstaltungsart.
 *
 * Die Termine liegen als JSON-Liste in der Spalte "dates" (je Eintrag date,
 * start, end, comment). Wie aus einem Eintrag ein Zeitpunkt wird und wie nach
 * einem Tag gefiltert wird, hängt davon ab, ob die Art das Jahr mitspeichert
 * (WithYearDates, tl_events) oder nicht (YearlessDates, tl_sekevents).
 */
interface EventDatesStrategy
{
    /**
     * Bereitet die Termine für die Ausgabe auf: gültige Termine nach Zeitpunkt
     * sortiert, Schlüssel "<Zeitstempel>_<Index>", Wert der Eintrag ergänzt um
     * "text" (z. B. "Samstag, 15.09.2026"). Ungültige Einträge entfallen.
     *
     * @param list<mixed> $entries Dekodierte Termineinträge
     *
     * @return array<string, array<string, mixed>>
     */
    public function formatDates(array $entries, int $now): array;

    /**
     * Zeitstempel im Schlüssel der Suchergebnisse ("<Zeitstempel>_<ID>") für
     * einen Termin. Die Ergebnisse sind danach chronologisch sortiert; der
     * Kalendertag des Eintrags ergibt sich per date('Y-m-d', Zeitstempel).
     */
    public function resultKeyTimestamp(int $tstamp): int;

    /**
     * Wertet den Datumsfilter der Suche aus (siehe EventDateFilter).
     */
    public function createDateFilter(string $datum, int $now): EventDateFilter;

    /**
     * Zeitpunkte der Termine, die für die Facetten zählen (nur heutige und
     * künftige, ab $midnight).
     *
     * @param list<mixed> $entries
     *
     * @return list<int>
     */
    public function facetTimestamps(array $entries, int $midnight, int $now): array;

    /**
     * True, wenn die Statistik je Tag und Veranstaltung zählt, false, wenn
     * je Veranstaltung.
     */
    public function countsPerDay(): bool;
}
