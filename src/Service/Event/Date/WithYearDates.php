<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Date;

use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventDateFilter;

/**
 * Datumsstrategie "mit Jahr" (tl_events): Das Datum steht vollständig als
 * TT.MM.JJJJ in "date", die Uhrzeit in "start" ist ein freier Text (z. B. "11:00")
 * und geht unverändert in strtotime() ein.
 *
 * Ergebnisse der Suche werden je Kalendertag und Veranstaltung geführt (Schlüssel
 * "<Mitternacht des Tages>_<ID>"): Mehrere Termine einer Veranstaltung am selben
 * Tag erscheinen einmal.
 */
final class WithYearDates extends AbstractEventDates
{
    public function resultKeyTimestamp(int $tstamp): int
    {
        return (int) strtotime(date('Y-m-d', $tstamp));
    }

    public function createDateFilter(string $datum, int $now): EventDateFilter
    {
        // Streng TT.MM.JJJJ wie der Datepicker und die gespeicherten Termine.
        if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datum, $matches)) {
            return EventDateFilter::nothing();
        }

        [, $day, $month, $year] = array_map('intval', $matches);

        return EventDateFilter::match(
            sprintf('%%"date":"%02d.%02d.%04d"%%', $day, $month, $year),
            (int) mktime(0, 0, 0, $month, $day, $year),
            (int) mktime(23, 59, 59, $month, $day, $year),
        );
    }

    public function facetTimestamps(array $entries, int $midnight, int $now): array
    {
        $stamps = [];

        foreach ($this->formatDates($entries, $now) as $key => $unused) {
            $tstamp = (int) explode('_', (string) $key)[0];

            if ($tstamp > 0 && $tstamp >= $midnight) {
                $stamps[] = $tstamp;
            }
        }

        return $stamps;
    }

    public function countsPerDay(): bool
    {
        return true;
    }

    protected function entryTimestamp(mixed $entry, int $now): int|null
    {
        if (null === $this->dateOf($entry)) {
            return null;
        }

        $start = \is_scalar($entry['start'] ?? null) ? (string) $entry['start'] : '';
        $tstamp = strtotime($entry['date'].' '.$start.':00');

        return false === $tstamp ? null : $tstamp;
    }
}
