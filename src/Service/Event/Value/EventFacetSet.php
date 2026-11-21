<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Value;

/**
 * Facetten der Veranstaltungsübersicht.
 *
 * - $count, $locations, $locationOrteAll: Statistik über alle aktiven, heutigen
 *   und künftigen Veranstaltungen, unabhängig von Suchfiltern (bisher getStats()
 *   bzw. getStatsAndDateRange()['stats']). Gezählt wird wie bisher: "events" je
 *   Tag und Veranstaltung, "sekevents" je Veranstaltung.
 * - $locationOrte: Orts-Badges unter den aktuellen Filtern (ohne Textsuche),
 *   nach Ort sortiert, ohne leere Orte.
 * - $dateRange: frühester und spätester Tag als TT.MM.JJJJ für den Datepicker,
 *   null, wenn es keinen heutigen oder künftigen Termin gibt.
 */
final readonly class EventFacetSet
{
    /**
     * @param list<string>                                      $locations
     * @param array<string, int>                                $locationOrteAll
     * @param array<string, int>                                $locationOrte
     * @param array{min: string|null, max: string|null}         $dateRange
     */
    public function __construct(
        public int $count,
        public array $locations,
        public array $locationOrteAll,
        public array $locationOrte,
        public array $dateRange,
    ) {
    }

    /**
     * Die Statistik in der Form, die die Übersichtsvorlagen kennen
     * (count, location_orte = Badges, locations).
     *
     * @return array{count: int, location_orte: array<string, int>, locations: list<string>}
     */
    public function stats(): array
    {
        return [
            'count' => $this->count,
            'location_orte' => $this->locationOrte,
            'locations' => $this->locations,
        ];
    }
}
