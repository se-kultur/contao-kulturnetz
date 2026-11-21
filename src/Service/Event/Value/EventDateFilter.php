<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Value;

/**
 * Ergebnis der Datumsfilter-Auswertung einer Datumsstrategie.
 *
 * - inactive(): kein Datumsfilter gesetzt.
 * - nothing(): der Filterwert ist ungültig oder passt nicht zum Laufzeitjahr; die
 *   Suche liefert bewusst keine Treffer (statt eines Fehlers oder einer
 *   irreführenden Teilmenge).
 * - match(): vorfiltern per SQL-Muster und anschließend nur Termine im
 *   Zeitfenster [$rangeStart, $rangeEnd] (beide inklusive) übernehmen.
 */
final readonly class EventDateFilter
{
    private function __construct(
        public bool $active,
        public bool $matchesNothing,
        public string|null $likePattern,
        public int|null $rangeStart,
        public int|null $rangeEnd,
    ) {
    }

    public static function inactive(): self
    {
        return new self(false, false, null, null, null);
    }

    public static function nothing(): self
    {
        return new self(true, true, null, null, null);
    }

    public static function match(string $likePattern, int $rangeStart, int $rangeEnd): self
    {
        return new self(true, false, $likePattern, $rangeStart, $rangeEnd);
    }

    public function contains(int $tstamp): bool
    {
        return !$this->matchesNothing && $tstamp >= $this->rangeStart && $tstamp <= $this->rangeEnd;
    }
}
