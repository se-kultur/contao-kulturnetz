<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

/**
 * Sortiert das SE-KulturTage-Programm innerhalb der einzelnen Tage.
 *
 * Die Tage selbst bleiben chronologisch und zusammenhängend, das Programm liest
 * sich also weiterhin von vorne nach hinten. Innerhalb eines Tages stehen
 * zuerst die normalen Veranstaltungen nach Uhrzeit aufsteigend.
 *
 * Danach folgt als Block am Ende des Tages die Gruppe der langlaufenden
 * Veranstaltungen (typischerweise Ausstellungen, die sich über viele
 * Festivaltage ziehen). Sie beginnen meist früh am Vormittag und stünden bei
 * reiner Zeitsortierung an jedem einzelnen Tag ganz oben. Weil sie im aktuellen
 * Programm rund jede dritte Karte stellen, wirkte auch ein Einstreuen an
 * wechselnden Positionen wie eine Zufallsmischung; deshalb stehen sie jetzt
 * geschlossen hinten.
 *
 * Innerhalb des Blocks gilt dieselbe Sortierregel wie bei den normalen
 * Veranstaltungen: aufsteigend nach Uhrzeit. Ein Block, der zeitlich rückwärts
 * läuft, liest sich für die Redaktion erneut wie eine kaputte Sortierung.
 * Erst bei identischer Uhrzeit entscheidet ein tagesstabiler Hash: Beginnen
 * zwei Ausstellungen zur selben Uhrzeit, wechseln sie täglich die Reihenfolge,
 * sonst entscheidet allein die Uhrzeit.
 *
 * Die Reihenfolge ist tagesstabil: Aus dem Seed (Standard: der heutige
 * Kalendertag) wird ein Hash gebildet, wodurch jeder Aufruf innerhalb desselben
 * Kalendertags dieselbe Reihenfolge liefert. Am Folgetag ergibt sich an den
 * Gleichständen eine andere Reihenfolge. Der Hash hängt nur am Seed, am
 * Kalendertag und am Schlüssel des Termins, nicht an der Größe der
 * Trefferliste. Ein Filter ändert die Reihenfolge damit nicht, er entfernt
 * lediglich Einträge.
 *
 * Die Sonderbehandlung lässt sich abschalten: Schwelle 0 bedeutet, dass keine
 * Veranstaltung als Langläufer gilt und der Tag ausschließlich nach Uhrzeit
 * sortiert wird. Der Hash bleibt dann vollständig außen vor, bei identischer
 * Uhrzeit entscheidet der Schlüssel des Termins. Die Ausgabe ist damit über
 * alle Kalendertage hinweg dieselbe.
 *
 * Die Klasse arbeitet bewusst ohne Contao-Abhängigkeiten und ohne globale
 * Zufallsquelle, damit sie isoliert prüfbar bleibt und keine Nebenwirkungen auf
 * den Zufallszustand des laufenden Prozesses hat. Events dürfen Arrays (wie sie
 * der EventFormatter liefert) oder Objekte mit der Eigenschaft "dates_formatted"
 * sein.
 */
final class ProgramSorter
{
    /**
     * Ab wie vielen distinkten künftigen Kalendertagen eine Veranstaltung als
     * langlaufend gilt und deshalb an das Ende ihres Tages rückt.
     *
     * Das ist der zentrale Schalter der Sonderbehandlung: Der Wert 0 schaltet
     * sie vollständig ab, dann gilt an jedem Tag ausschließlich die Uhrzeit und
     * bei identischer Uhrzeit der Schlüssel des Termins. Die Ausgabe steht damit
     * fest und wechselt insbesondere nicht mehr täglich. Alle Aufrufe, die keine
     * eigene Schwelle übergeben, lesen diesen Wert.
     */
    public const LONG_RUNNER_THRESHOLD = 3;

    /**
     * Sortiert die Trefferliste tagweise um.
     *
     * @param array<string, array<string, mixed>|object> $data      Chronologisch sortierte Liste, Schlüssel
     *                                                              "<Zeitstempel>_<ID>", Werte sind Events mit
     *                                                              "dates_formatted"
     * @param string|null                                $seed      Seed für die Hash-Bildung. Null bedeutet: heutiger
     *                                                              Kalendertag, die Reihenfolge wechselt also täglich
     *                                                              einmal.
     * @param int|null                                   $threshold Ab wie vielen distinkten künftigen Kalendertagen
     *                                                              eine Veranstaltung als langlaufend gilt. Null
     *                                                              bedeutet LONG_RUNNER_THRESHOLD; 0 (oder kleiner)
     *                                                              schaltet die Sonderbehandlung ab.
     * @param int|null                                   $from      Untergrenze für die Tageszählung. Null bedeutet:
     *                                                              heute Mitternacht, dieselbe Grenze wie in
     *                                                              EventSearch.
     *
     * @return array<string, array<string, mixed>|object> Dieselben Schlüssel wie die Eingabe, lediglich in anderer
     *                                                    Reihenfolge
     */
    public function sortDays(array $data, string|null $seed = null, int|null $threshold = null, int|null $from = null): array
    {
        if (\count($data) < 2) {
            return $data;
        }

        $threshold ??= self::LONG_RUNNER_THRESHOLD;
        $seed ??= date('Y-m-d');
        $from ??= (int) strtotime('today midnight');

        // Schwelle 0 (oder kleiner) heißt: keine Sonderbehandlung. Die Prüfung
        // muss explizit erfolgen, denn ein Vergleich "Tageszahl >= 0" träfe auf
        // jede Veranstaltung zu und würde das Gegenteil bewirken.
        $specialTreatment = $threshold > 0;

        // Nach Kalendertag gruppieren. Die Eingabe ist bereits chronologisch
        // sortiert, dadurch bleibt die Reihenfolge der Tage automatisch erhalten
        // und die Tage bleiben zusammenhängend; die Vorlage setzt beides für ihre
        // Tagesüberschriften voraus.
        $byDay = [];

        foreach ($data as $key => $event) {
            $tstamp = (int) explode('_', (string) $key)[0];
            $byDay[date('Y-m-d', $tstamp)][$key] = $tstamp;
        }

        // Dasselbe Event taucht bei mehrtägigen Veranstaltungen an jedem
        // Termintag erneut in $data auf. Die Tageszählung darf deshalb pro Event
        // nur einmal laufen.
        $dayCountCache = [];
        $result = [];

        foreach ($byDay as $day => $slots) {
            $regular = [];
            $longRunners = [];

            foreach ($slots as $key => $tstamp) {
                if (!$specialTreatment) {
                    $regular[$key] = $tstamp;
                    continue;
                }

                $event = $data[$key];
                $cacheKey = $this->cacheKey($event);

                if (!\array_key_exists($cacheKey, $dayCountCache)) {
                    $dayCountCache[$cacheKey] = $this->countFutureDays($event, $from);
                }

                if ($dayCountCache[$cacheKey] >= $threshold) {
                    $longRunners[$key] = $tstamp;
                } else {
                    $regular[$key] = $tstamp;
                }
            }

            // Normale Veranstaltungen: strikt nach Startzeit.
            $dayList = array_keys($regular);
            usort($dayList, fn ($a, $b): int => $this->compareSlots($regular, (string) $a, (string) $b, $seed, (string) $day, $specialTreatment));

            // Langläufer als geschlossener Block hinter den normalen
            // Veranstaltungen, im Block nach derselben Regel sortiert. Die Rotation
            // der Ausstellungen untereinander bleibt über den Gleichstands-Tiebreak
            // erhalten, denn die meisten öffnen ohnehin zur selben Stunde. Besteht
            // der Tag nur aus Langläufern, ist die Liste der normalen
            // Veranstaltungen leer und der Block stellt den ganzen Tag.
            $blockList = array_keys($longRunners);
            usort($blockList, fn ($a, $b): int => $this->compareSlots($longRunners, (string) $a, (string) $b, $seed, (string) $day, $specialTreatment));

            foreach ($blockList as $key) {
                $dayList[] = $key;
            }

            foreach ($dayList as $key) {
                $result[$key] = $data[$key];
            }
        }

        return $result;
    }

    /**
     * Zählt, an wie vielen verschiedenen Kalendertagen ab $from eine
     * Veranstaltung stattfindet.
     *
     * Gezählt wird aus "dates_formatted" des Events, nicht aus der Trefferliste:
     * Bei aktivem Datumsfilter enthält die Trefferliste nur einen einzigen Tag,
     * eine Ausstellung über zwei Wochen gälte dann fälschlich als normale
     * Veranstaltung.
     *
     * @param array<string, mixed>|object $event Event mit der Eigenschaft "dates_formatted"
     * @param int|null                    $from  Null bedeutet: heute Mitternacht
     */
    public function countFutureDays(array|object $event, int|null $from = null): int
    {
        $from ??= (int) strtotime('today midnight');

        $dates = \is_array($event) ? ($event['dates_formatted'] ?? null) : ($event->dates_formatted ?? null);

        if (!\is_array($dates)) {
            return 0;
        }

        $days = [];

        foreach ($dates as $key => $unused) {
            $tstamp = (int) explode('_', (string) $key)[0];

            // Ungültige Termine (siehe EventFormatter) und vergangene Tage zählen
            // nicht mit.
            if ($tstamp <= 0 || $tstamp < $from) {
                continue;
            }

            $days[date('Y-m-d', $tstamp)] = true;
        }

        return \count($days);
    }

    /**
     * Vergleicht zwei Termine desselben Tages: zuerst die Startzeit, bei
     * identischer Startzeit der Tiebreak.
     *
     * Der Tiebreak hängt am Schalter. Mit Sonderbehandlung entscheidet der
     * tagesstabile Hash, damit unter gleichzeitig beginnenden Veranstaltungen
     * nicht dauerhaft dieselbe vorne steht (zuvor entschied die ID). Ohne
     * Sonderbehandlung (Schwelle 0) entscheidet der Schlüssel: Der Wert 0 ist
     * der Rückfallschalter auf eine Reihenfolge, die sich nie ändert, und ein
     * Hash-Tiebreak würde die Ausgabe an jedem Gleichstand weiterhin täglich
     * umstellen.
     *
     * @param array<string, int> $stamps Startzeitpunkte je Schlüssel
     */
    private function compareSlots(array $stamps, string $a, string $b, string $seed, string $day, bool $hashTiebreak): int
    {
        if ($stamps[$a] !== $stamps[$b]) {
            return $stamps[$a] <=> $stamps[$b];
        }

        if (!$hashTiebreak) {
            return strcmp($a, $b);
        }

        return $this->compareHash($seed, $day, 'gleichzeitig', $a, $b);
    }

    /**
     * Bildet den Hash für einen Termin. Seed, Kalendertag und Schlüssel gehen
     * gemeinsam ein: Ohne den Kalendertag stünden dieselben zwei gleichzeitig
     * beginnenden Veranstaltungen an jedem Festivaltag in derselben Reihenfolge,
     * ohne den Schlüssel wären alle Termine eines Tages gleichwertig. Der Zweck
     * trennt Verwendungen voneinander, damit voneinander unabhängige
     * Entscheidungen nicht korrelieren.
     */
    private function hash(string $seed, string $day, string $purpose, string $key): int
    {
        // sha1 statt crc32: crc32 ist über GF(2) affin, Seed und Schlüssel gehen
        // in denselben String ein und die Schlüssel eines Tages sind gleich
        // lang. Dadurch entscheidet über jedes Schlüsselpaar praktisch ein
        // einzelnes Bit des Seed-Anteils, die Menge der erreichbaren
        // Reihenfolgen kollabiert (bei vier Terminen 8 von 24) und einzelne
        // Veranstaltungen stünden doppelt so oft vorne wie andere. Die ersten
        // 28 Bit eines sha1-Digests streuen dagegen gleichmäßig; sie passen
        // zugleich in einen nicht-negativen int. Kryptografisch ist hier nichts
        // gefordert, allein die Streuung zählt.
        return (int) hexdec(substr(sha1($seed.'|'.$day.'|'.$purpose.'|'.$key), 0, 7));
    }

    /**
     * Vergleicht zwei Schlüssel anhand ihres Hashes. Bei identischem Hash
     * entscheidet der Schlüssel selbst, damit die Reihenfolge eindeutig bleibt.
     */
    private function compareHash(string $seed, string $day, string $purpose, string $a, string $b): int
    {
        $hashA = $this->hash($seed, $day, $purpose, $a);
        $hashB = $this->hash($seed, $day, $purpose, $b);

        return $hashA === $hashB ? strcmp($a, $b) : ($hashA <=> $hashB);
    }

    /**
     * Schlüssel für den Tageszähler-Cache. Bevorzugt die Event-ID; fehlt sie,
     * dient die Objektidentität als Ersatz (bei Arrays ein Hash des Inhalts):
     * dasselbe Event taucht ohnehin mehrfach in der Liste auf.
     *
     * @param array<string, mixed>|object $event
     */
    private function cacheKey(array|object $event): string
    {
        $id = \is_array($event) ? ($event['id'] ?? null) : ($event->id ?? null);

        if (\is_scalar($id) && '' !== (string) $id) {
            return 'id:'.$id;
        }

        return \is_object($event) ? 'obj:'.spl_object_id($event) : 'arr:'.md5(serialize($event));
    }
}
