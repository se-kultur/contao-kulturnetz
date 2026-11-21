<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventCriteria;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventFacetSet;

/**
 * Facetten der Veranstaltungsübersicht: Orts-Badges und Datumsbereich für den
 * Datepicker (bisher EventsModel::getStats(), SekEventsModel::getStatsAndDateRange()
 * und die in beiden ...ListAll-Modulen kopierte Badge-Logik).
 *
 * Statistik und Datumsbereich entstehen in einem Durchlauf über die schlanken
 * Zeilen aller aktiven Veranstaltungen (eine Abfrage, ohne Auflösung der
 * verknüpften Profile). Sie ignorieren Suchfilter und beziehen sich auf dieselbe
 * Datenbasis wie die Trefferliste: nur Veranstaltungen mit heutigem oder
 * künftigem Termin. Sonst böte die Oberfläche Orte und Datumsgrenzen an, die
 * garantiert zu null Treffern führen.
 *
 * Die Orts-Badges dagegen folgen den aktuellen Filtern: Ein Ort wird nur
 * angeboten, wenn er unter den gesetzten Filtern Treffer liefert. Der Ortsfilter
 * (text) bleibt dabei außen vor, weil ein Badge ihn überschreibt; sonst bliebe
 * nach dem ersten Klick nur noch der gewählte Ort übrig.
 */
final class EventFacets
{
    public function __construct(
        private readonly EventRepository $repository,
        private readonly EventFormatter $formatter,
        private readonly EventSearch $search,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>>|null $visibleEvents Bereits geladene Trefferliste zu $criteria
     *                                                                (EventSearch::search()). Ohne Textfilter
     *                                                                entspricht sie der Facettenmenge und wird
     *                                                                wiederverwendet, statt erneut zu suchen.
     */
    public function build(EventType $type, EventCriteria $criteria, array|null $visibleEvents = null, int|null $now = null): EventFacetSet
    {
        $now ??= $this->clock->now()->getTimestamp();
        $strategy = $this->formatter->getDatesStrategy($type);
        $midnight = (int) strtotime('today midnight', $now);

        $count = 0;
        $locations = [];
        $locationOrteAll = [];
        $min = null;
        $max = null;
        $perDay = $strategy->countsPerDay();
        $slots = [];
        $entries = [];

        foreach ($this->repository->findActiveForFacets($type) as $row) {
            $stamps = $strategy->facetTimestamps($this->decodeDates($row['dates'] ?? null), $midnight, $now);

            if ([] === $stamps) {
                continue;
            }

            foreach ($stamps as $tstamp) {
                $min = null === $min || $tstamp < $min ? $tstamp : $min;
                $max = null === $max || $tstamp > $max ? $tstamp : $max;

                if ($perDay) {
                    $slots[$strategy->resultKeyTimestamp($tstamp).'_'.$row['id']] = $row;
                }
            }

            if (!$perDay) {
                $entries[] = $row;
            }
        }

        // events zählen je Tag und Veranstaltung in Ergebnisreihenfolge, sekevents je
        // Veranstaltung in ID-Reihenfolge.
        if ($perDay) {
            ksort($slots, SORT_STRING);
            $entries = array_values($slots);
        }

        foreach ($entries as $row) {
            ++$count;
            $city = trim((string) $row['location_ort']);
            $locationOrteAll[$city] = ($locationOrteAll[$city] ?? 0) + 1;
            $locations[] = $row['location_adresse'].', '.$row['location_plz'].' '.$row['location_ort'];
        }

        $facetEvents = null !== $visibleEvents && !$criteria->hasText()
            ? $visibleEvents
            : $this->search->search($type, $criteria->withoutText(), $now);

        return new EventFacetSet(
            $count,
            $locations,
            $locationOrteAll,
            $this->buildBadges($facetEvents),
            [
                'min' => null !== $min ? date('d.m.Y', $min) : null,
                'max' => null !== $max ? date('d.m.Y', $max) : null,
            ],
        );
    }

    /**
     * Orts-Badges: Anzahl der Einträge je Ort, ohne leere Orte, nach Ort sortiert.
     *
     * @param array<string, array<string, mixed>> $events
     *
     * @return array<string, int>
     */
    private function buildBadges(array $events): array
    {
        $cities = [];

        foreach ($events as $event) {
            $city = trim((string) ($event['location_ort'] ?? ''));

            if ('' === $city) {
                continue;
            }

            $cities[$city] = ($cities[$city] ?? 0) + 1;
        }

        ksort($cities);

        return $cities;
    }

    /**
     * @return list<mixed>
     */
    private function decodeDates(mixed $json): array
    {
        $decoded = \is_string($json) && '' !== $json ? json_decode($json, true) : null;

        return \is_array($decoded) ? array_values($decoded) : [];
    }
}
