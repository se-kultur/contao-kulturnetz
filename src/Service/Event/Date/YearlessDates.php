<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Date;

use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventDateFilter;

/**
 * Datumsstrategie "ohne Jahr" (tl_sekevents, SE-KulturTage): "date" enthält nur
 * TT.MM., das Jahr ist das Jahr des Aufrufs. Die Uhrzeit "start" ist ein
 * ungeprüftes Freitextfeld: nur HH:MM (ein- oder zweistellige Stunde) gilt, jeder
 * andere Wert, auch ein leerer, wird zu 00:00, damit der Termin nicht
 * stillschweigend verschwindet.
 *
 * Ergebnisse der Suche werden je Terminzeitpunkt und Veranstaltung geführt
 * (Schlüssel "<Zeitstempel>_<ID>"). Der Zeitstempel im Schlüssel ist, wie bisher,
 * die Ortszeit des Termins als UTC-Zeitstempel gelesen (siehe resultKeyTimestamp()).
 */
final class YearlessDates extends AbstractEventDates
{
    public function resultKeyTimestamp(int $tstamp): int
    {
        // Der bisherige Schlüssel entstand aus strtotime(date('Y-m-d-His', $tstamp)):
        // Die Ortszeit des Termins wird als UTC gelesen, der Zeitstempel verschiebt
        // sich um den UTC-Versatz (in Deutschland +1 bzw. +2 Stunden). Die
        // Reihenfolge bleibt chronologisch. Die ProgramSorter-Rotation bei gleicher
        // Uhrzeit leitet ihren Hash aus dem Schlüssel ab; ein anderer Schlüssel
        // ergäbe an den Gleichständen eine andere Reihenfolge als bisher. Folge:
        // Der Kalendertag, der sich per date() aus dem Schlüssel ergibt, kippt für
        // Termine ab 22 bzw. 23 Uhr auf den Folgetag (siehe Bericht, Beobachtungen).
        return $tstamp + (int) date('Z', $tstamp);
    }

    public function createDateFilter(string $datum, int $now): EventDateFilter
    {
        // Tag und Monat mit je 1 bis 2 Ziffern, optional gefolgt von einem Punkt
        // und einer vierstelligen Jahreszahl (der Datepicker liefert z. B.
        // "15.09.2026"). Alles andere ist ungültig; LIKE-Platzhalter in der Eingabe
        // gelangen so nie in das SQL-Muster.
        if (!preg_match('/^(\d{1,2})\.(\d{1,2})\.?(\d{4})?$/', $datum, $matches)) {
            return EventDateFilter::nothing();
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) date('Y', $now);

        // Gespeichert ist ohne Jahr, ausgegeben wird mit dem Laufzeitjahr. Eine
        // abweichende Jahreszahl im Filter liefert deshalb keine Treffer statt
        // eines Ergebnisses, das nicht zum Formularwert passt.
        if (isset($matches[3]) && '' !== $matches[3] && (int) $matches[3] !== $year) {
            return EventDateFilter::nothing();
        }

        return EventDateFilter::match(
            sprintf('%%"date":"%02d.%02d."%%', $day, $month),
            (int) mktime(0, 0, 0, $month, $day, $year),
            (int) mktime(23, 59, 59, $month, $day, $year),
        );
    }

    public function facetTimestamps(array $entries, int $midnight, int $now): array
    {
        $stamps = [];

        foreach ($entries as $entry) {
            $date = $this->dateOf($entry);

            if (null === $date) {
                continue;
            }

            // Für Statistik und Datumsbereich zählt nur der Tag. Die Uhrzeit bleibt
            // außen vor, weil ein ungültiger Freitext sonst den Termin aus der
            // Min/Max-Ermittlung verdrängen würde.
            $tstamp = strtotime($date.date('Y', $now));

            if (false !== $tstamp && $tstamp >= $midnight) {
                $stamps[] = $tstamp;
            }
        }

        return $stamps;
    }

    public function countsPerDay(): bool
    {
        return false;
    }

    protected function entryTimestamp(mixed $entry, int $now): int|null
    {
        $date = $this->dateOf($entry);

        if (null === $date) {
            return null;
        }

        $start = \is_scalar($entry['start'] ?? null) ? (string) $entry['start'] : '';

        if (!preg_match('/^\d{1,2}:\d{2}$/', $start)) {
            $start = '00:00';
        }

        $tstamp = strtotime($date.date('Y', $now).' '.$start.':00');

        return false === $tstamp ? null : $tstamp;
    }
}
