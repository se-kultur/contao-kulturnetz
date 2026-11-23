<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use Psr\Clock\ClockInterface;

/**
 * Zeitraum, in dem neue Termine ganzjähriger Veranstaltungen liegen dürfen: wie
 * der Datepicker des Formulars (eventform.js, createDateConfig) von heute bis zum
 * Ende des übernächsten Jahres. Der Datepicker begrenzt nur die Auswahl, getippte
 * Daten wie 05.12.2999 lässt er durch; der Server prüft deshalb dieselben Grenzen.
 *
 * Termine der SE-KulturTage haben kein Jahr und fallen nicht darunter.
 */
final class EventDateRange
{
    /**
     * Ende des Jahres, das so viele Jahre nach dem laufenden liegt (Datepicker:
     * maxDate Jahr + 2).
     */
    private const MAX_YEARS_AHEAD = 2;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * Heute, 00:00 Uhr (Datepicker: minDate heute).
     */
    public function getMin(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    /**
     * 31.12. des übernächsten Jahres, 00:00 Uhr.
     */
    public function getMax(): \DateTimeImmutable
    {
        $min = $this->getMin();

        return $min->setDate((int) $min->format('Y') + self::MAX_YEARS_AHEAD, 12, 31);
    }

    /**
     * True, wenn der Tag (Format TT.MM.JJJJ, ein gültiger Kalendertag) im Zeitraum liegt.
     */
    public function contains(string $date): bool
    {
        $min = $this->getMin();
        $day = \DateTimeImmutable::createFromFormat('!d.m.Y', $date, $min->getTimezone());

        return false !== $day && $day >= $min && $day <= $this->getMax();
    }
}
