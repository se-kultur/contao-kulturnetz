<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Date;

/**
 * Gemeinsamer Ablauf der Datumsstrategien: Einträge durchlaufen, in Zeitpunkte
 * umrechnen, sortieren und mit Wochentag versehen. Die Strategien legen nur
 * fest, wie ein einzelner Eintrag in einen Zeitpunkt übersetzt wird.
 */
abstract class AbstractEventDates implements EventDatesStrategy
{
    private const WEEKDAYS = [
        'Sonntag',
        'Montag',
        'Dienstag',
        'Mittwoch',
        'Donnerstag',
        'Freitag',
        'Samstag',
    ];

    public function formatDates(array $entries, int $now): array
    {
        $dates = [];

        foreach (array_values($entries) as $index => $entry) {
            // Eintrag ohne Datum oder ungültige Angabe: überspringen. Der Index
            // zählt trotzdem weiter, damit die Schlüssel stabil bleiben.
            $tstamp = $this->entryTimestamp($entry, $now);

            if (null === $tstamp) {
                continue;
            }

            $entry['text'] = self::WEEKDAYS[(int) date('w', $tstamp)].', '.date('d.m.Y', $tstamp);
            $dates[$tstamp.'_'.$index] = $entry;
        }

        ksort($dates, SORT_STRING);

        return $dates;
    }

    /**
     * Zeitpunkt eines Termineintrags oder null, wenn er sich nicht auswerten lässt.
     */
    abstract protected function entryTimestamp(mixed $entry, int $now): int|null;

    /**
     * Wert des Feldes "date" eines Eintrags oder null, wenn leer oder kein Text.
     */
    protected function dateOf(mixed $entry): string|null
    {
        if (!\is_array($entry)) {
            return null;
        }

        $date = $entry['date'] ?? null;

        return \is_string($date) && '' !== trim($date) ? $date : null;
    }
}
