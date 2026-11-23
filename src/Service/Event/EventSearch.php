<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventCriteria;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventDateFilter;

/**
 * Suche der öffentlichen Veranstaltungsübersichten (bisher
 * EventsModel::findAllEvents() und SekEventsModel::findAllSekEvents()).
 *
 * Gesucht wird unter den aktiven Veranstaltungen (disable ungleich 1, ohne
 * unfertige Datensätze mit tstamp = 0, siehe EventRepository), neueste ID
 * zuerst, mit den Limits aus der Bundle-Konfiguration (limits.event_directory,
 * limits.sekevent_directory; das Limit greift wie bisher auf die Datensätze,
 * bevor die Termine ausgewertet werden). Danach wird jeder Termin einzeln
 * betrachtet:
 *
 * - Termine vor heute 0:00 Uhr entfallen. Die Grenze ist Mitternacht, damit ein
 *   Termin am laufenden Tag sichtbar bleibt, auch wenn seine Uhrzeit vorbei ist.
 *   Vergangene Veranstaltungen bleiben über den Direktlink erreichbar.
 * - Bei gesetztem Datumsfilter zählen nur Termine dieses Tages (TT.MM.JJJJ).
 *
 * Ergebnis: formatierte Veranstaltungen (siehe EventFormatter), Schlüssel
 * "<Zeitstempel>_<ID>", chronologisch sortiert. Bei events ist der Zeitstempel
 * die Mitternacht des Tages (mehrere Termine einer Veranstaltung am selben Tag
 * ergeben einen Eintrag), bei sekevents der Terminzeitpunkt in der bisherigen
 * Schlüsselform (Ortszeit als UTC gelesen, siehe YearlessDates::resultKeyTimestamp()).
 * Eine mehrtägige Veranstaltung steht an jedem ihrer Tage, jeweils derselbe
 * Datensatz. Der Tag ist wie bisher aus dem Schlüssel ablesbar:
 * date('Y-m-d', (int) explode('_', $key)[0]).
 */
final class EventSearch
{
    public function __construct(
        private readonly EventRepository $repository,
        private readonly EventFormatter $formatter,
        private readonly ClockInterface $clock,
        private readonly KulturnetzConfig $config,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function search(EventType $type, EventCriteria $criteria, int|null $now = null): array
    {
        $now ??= $this->clock->now()->getTimestamp();
        $strategy = $this->formatter->getDatesStrategy($type);
        $dateFilter = null !== ($datum = $criteria->activeDatum())
            ? $strategy->createDateFilter($datum, $now)
            : EventDateFilter::inactive();

        $rows = $this->repository->findActive($type, $criteria, $dateFilter, $this->getLimit($type));

        if ([] === $rows) {
            return [];
        }

        // Vergangene Termine gehören nicht in Liste und Suche, Grenze ist Mitternacht.
        $midnight = (int) strtotime('today midnight', $now);
        $results = [];

        foreach ($this->formatter->formatMany($type, $rows, $now) as $event) {
            foreach ($event['dates_formatted'] as $key => $unused) {
                $tstamp = (int) explode('_', (string) $key)[0];

                if ($tstamp <= 0 || $tstamp < $midnight) {
                    continue;
                }

                if ($dateFilter->active && !$dateFilter->contains($tstamp)) {
                    continue;
                }

                $results[$strategy->resultKeyTimestamp($tstamp).'_'.$event['id']] = $event;
            }
        }

        ksort($results, SORT_STRING);

        return $results;
    }

    /**
     * Limit der Datensätze je Art (null: unbegrenzt).
     */
    public function getLimit(EventType $type): int|null
    {
        return $type->datesStoredWithoutYear()
            ? $this->config->getSekEventDirectoryLimit()
            : $this->config->getEventDirectoryLimit();
    }
}
