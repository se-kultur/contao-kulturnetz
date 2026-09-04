<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Helper;

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
 * sonst entscheidet allein die Uhrzeit. Im aktuellen Programm betrifft das fünf
 * der siebzehn Festivaltage; an den übrigen liegt der Block fest.
 *
 * Die Reihenfolge ist tagesstabil: Aus dem Seed (Default: der heutige
 * Kalendertag) wird ein Hash gebildet, wodurch jeder Aufruf innerhalb desselben
 * Kalendertags dieselbe Reihenfolge liefert. Am Folgetag ergibt sich an den
 * Gleichständen eine andere Reihenfolge. Der Hash hängt nur am Seed, am
 * Kalendertag und am Schlüssel des Termins, nicht an der Größe der
 * Trefferliste. Ein Filter ändert die Reihenfolge damit nicht mehr, er entfernt
 * lediglich Einträge.
 *
 * Die Sonderbehandlung lässt sich abschalten: Schwelle 0 bedeutet, dass keine
 * Veranstaltung als Langläufer gilt und der Tag ausschließlich nach Uhrzeit
 * sortiert wird. Der Hash bleibt dann vollständig außen vor, bei identischer
 * Uhrzeit entscheidet der Schlüssel des Termins. Die Ausgabe ist damit über
 * alle Kalendertage hinweg dieselbe (siehe self::LANGLAEUFER_SCHWELLE).
 *
 * Die Klasse arbeitet bewusst ohne Contao-Abhängigkeiten und ohne globale
 * Zufallsquelle, damit sie isoliert prüfbar bleibt und keine Nebenwirkungen auf
 * den Zufallszustand des laufenden Prozesses hat.
 */
class ProgrammSortierer
{
    /**
     * Ab wie vielen distinkten künftigen Kalendertagen eine Veranstaltung als
     * langlaufend gilt und deshalb an das Ende ihres Tages rückt.
     *
     * Das ist der zentrale Schalter der Sonderbehandlung: Der Wert 0 schaltet
     * sie vollständig ab, dann gilt an jedem Tag ausschließlich die Uhrzeit und
     * bei identischer Uhrzeit der Schlüssel des Termins. Die Ausgabe steht damit
     * fest und wechselt insbesondere nicht mehr täglich. Alle Aufrufe, die keine
     * eigene Schwelle übergeben (auch der Weg über
     * SekEventsModel::sortiereInnerhalbDerTage() und das Frontend-Modul), lesen
     * diesen Wert.
     */
    public const LANGLAEUFER_SCHWELLE = 3;

    /**
     * Sortiert die Trefferliste tagweise um.
     *
     * @param array       $data                 Chronologisch sortierte Liste, Schlüssel "<tstamp>_<id>",
     *                                          Werte sind Event-Objekte mit "dates_formatted"
     * @param string|null $seed                 Seed für die Hash-Bildung. Null bedeutet: heutiger Kalendertag,
     *                                          die Reihenfolge wechselt also täglich einmal.
     * @param int         $langlaeuferSchwelle  Ab wie vielen distinkten künftigen Kalendertagen eine
     *                                          Veranstaltung als langlaufend gilt. 0 (oder kleiner)
     *                                          schaltet die Sonderbehandlung ab.
     * @param int|null    $abZeitpunkt          Untergrenze für die Tageszählung. Null bedeutet: heute Mitternacht,
     *                                          dieselbe Grenze wie in SekEventsModel::findAllSekEvents().
     *
     * @return array Dieselben Schlüssel wie die Eingabe, lediglich in anderer Reihenfolge
     */
    public static function sortiereTage(array $data, ?string $seed = null, int $langlaeuferSchwelle = self::LANGLAEUFER_SCHWELLE, ?int $abZeitpunkt = null): array
    {
        if (count($data) < 2) {
            return $data;
        }

        if (null === $seed) {
            $seed = date('Y-m-d');
        }

        if (null === $abZeitpunkt) {
            $abZeitpunkt = strtotime('today midnight');
        }

        // Schwelle 0 (oder kleiner) heißt: keine Sonderbehandlung. Die Prüfung
        // muss explizit erfolgen, denn ein Vergleich "Tageszahl >= 0" träfe auf
        // jede Veranstaltung zu und würde das Gegenteil bewirken.
        $sonderbehandlung = $langlaeuferSchwelle > 0;

        // Nach Kalendertag gruppieren. Die Eingabe ist bereits chronologisch
        // sortiert, dadurch bleibt die Reihenfolge der Tage automatisch erhalten
        // und die Tage bleiben zusammenhängend - beides setzt das Template für
        // seine Tagesüberschriften voraus.
        $nachTag = [];

        foreach ($data as $key => $event) {
            $tstamp = (int) explode('_', (string) $key)[0];
            $nachTag[date('Y-m-d', $tstamp)][$key] = $tstamp;
        }

        // Dasselbe Event-Objekt taucht bei mehrtägigen Veranstaltungen an jedem
        // Termintag erneut in $data auf. Die Tageszählung darf deshalb pro Event
        // nur einmal laufen.
        $tageCache = [];
        $ergebnis = [];

        foreach ($nachTag as $tag => $termine) {
            $normale = [];
            $langlaeufer = [];

            foreach ($termine as $key => $tstamp) {
                if (!$sonderbehandlung) {
                    $normale[$key] = $tstamp;
                    continue;
                }

                $event = $data[$key];
                $cacheSchluessel = self::cacheSchluessel($event);

                if (!array_key_exists($cacheSchluessel, $tageCache)) {
                    $tageCache[$cacheSchluessel] = self::zaehleKuenftigeTage($event, $abZeitpunkt);
                }

                if ($tageCache[$cacheSchluessel] >= $langlaeuferSchwelle) {
                    $langlaeufer[$key] = $tstamp;
                } else {
                    $normale[$key] = $tstamp;
                }
            }

            // Normale Veranstaltungen: strikt nach Startzeit.
            $tagesliste = array_keys($normale);
            usort($tagesliste, static function ($a, $b) use ($normale, $seed, $tag, $sonderbehandlung) {
                return self::vergleicheTermine($normale, (string) $a, (string) $b, $seed, (string) $tag, $sonderbehandlung);
            });

            // Langläufer als geschlossener Block hinter den normalen
            // Veranstaltungen, im Block nach derselben Regel sortiert. Ein
            // Block, der zeitlich rückwärts läuft, wirkt auf die Redaktion
            // erneut wie eine kaputte Sortierung; die Rotation der Ausstellungen
            // untereinander bleibt über den Gleichstands-Tiebreak erhalten, denn
            // die meisten öffnen ohnehin zur selben Stunde. Besteht der Tag nur
            // aus Langläufern, ist die Liste der normalen Veranstaltungen leer
            // und der Block stellt den ganzen Tag.
            $blockliste = array_keys($langlaeufer);
            usort($blockliste, static function ($a, $b) use ($langlaeufer, $seed, $tag, $sonderbehandlung) {
                return self::vergleicheTermine($langlaeufer, (string) $a, (string) $b, $seed, (string) $tag, $sonderbehandlung);
            });

            foreach ($blockliste as $key) {
                $tagesliste[] = $key;
            }

            foreach ($tagesliste as $key) {
                $ergebnis[$key] = $data[$key];
            }
        }

        return $ergebnis;
    }

    /**
     * Zählt, an wie vielen verschiedenen Kalendertagen ab $abZeitpunkt eine
     * Veranstaltung stattfindet.
     *
     * Gezählt wird aus "dates_formatted" des Events, nicht aus der Trefferliste:
     * Bei aktivem Datumsfilter enthält die Trefferliste nur einen einzigen Tag,
     * eine Ausstellung über zwei Wochen gälte dann fälschlich als normale
     * Veranstaltung.
     *
     * @param mixed    $event       Event-Objekt mit der Eigenschaft "dates_formatted"
     * @param int|null $abZeitpunkt Null bedeutet: heute Mitternacht
     */
    public static function zaehleKuenftigeTage($event, ?int $abZeitpunkt = null): int
    {
        if (!is_object($event)) {
            return 0;
        }

        if (null === $abZeitpunkt) {
            $abZeitpunkt = strtotime('today midnight');
        }

        $dates = $event->dates_formatted ?? null;

        if (!is_array($dates)) {
            return 0;
        }

        $tage = [];

        foreach ($dates as $key => $x) {
            $tstamp = (int) explode('_', (string) $key)[0];

            // Ungültige Termine (siehe SekEventsModel::formatData()) und
            // vergangene Tage zählen nicht mit.
            if ($tstamp <= 0 || $tstamp < $abZeitpunkt) {
                continue;
            }

            $tage[date('Y-m-d', $tstamp)] = true;
        }

        return count($tage);
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
    private static function vergleicheTermine(array $stamps, string $a, string $b, string $seed, string $tag, bool $hashTiebreak): int
    {
        if ($stamps[$a] !== $stamps[$b]) {
            return $stamps[$a] <=> $stamps[$b];
        }

        if (!$hashTiebreak) {
            return strcmp($a, $b);
        }

        return self::vergleicheHash($seed, $tag, 'gleichzeitig', $a, $b);
    }

    /**
     * Bildet den Hash für einen Termin. Seed, Kalendertag und Schlüssel gehen
     * gemeinsam ein: Ohne den Kalendertag stünden dieselben zwei gleichzeitig
     * beginnenden Veranstaltungen an jedem Festivaltag in derselben Reihenfolge,
     * ohne den Schlüssel wären alle Termine eines Tages gleichwertig. Der Zweck
     * trennt Verwendungen voneinander, damit voneinander unabhängige
     * Entscheidungen nicht korrelieren.
     */
    private static function hash(string $seed, string $tag, string $zweck, string $key): int
    {
        // sha1 statt crc32: crc32 ist über GF(2) affin, Seed und Schlüssel gehen
        // in denselben String ein und die Schlüssel eines Tages sind gleich
        // lang. Dadurch entscheidet über jedes Schlüsselpaar praktisch ein
        // einzelnes Bit des Seed-Anteils, die Menge der erreichbaren
        // Reihenfolgen kollabiert (bei vier Terminen 8 von 24) und einzelne
        // Veranstaltungen stünden doppelt so oft vorne wie andere. Die ersten
        // 28 Bit eines sha1-Digests streuen dagegen gleichmäßig; sie passen
        // zugleich auf 32-Bit-PHP in einen nicht-negativen int, eine Maskierung
        // wie bei crc32 ist deshalb nicht nötig. Kryptografisch ist hier nichts
        // gefordert, allein die Streuung zählt.
        return (int) hexdec(substr(sha1($seed.'|'.$tag.'|'.$zweck.'|'.$key), 0, 7));
    }

    /**
     * Vergleicht zwei Schlüssel anhand ihres Hashes. Bei identischem Hash
     * entscheidet der Schlüssel selbst, damit die Reihenfolge eindeutig bleibt.
     */
    private static function vergleicheHash(string $seed, string $tag, string $zweck, string $a, string $b): int
    {
        $hashA = self::hash($seed, $tag, $zweck, $a);
        $hashB = self::hash($seed, $tag, $zweck, $b);

        return $hashA === $hashB ? strcmp($a, $b) : ($hashA <=> $hashB);
    }

    /**
     * Schlüssel für den Tageszähler-Cache. Bevorzugt die Event-ID; fehlt sie,
     * dient die Objektidentität als Ersatz - dasselbe Objekt taucht ohnehin
     * mehrfach in der Liste auf.
     *
     * @param mixed $event
     */
    private static function cacheSchluessel($event): string
    {
        if (!is_object($event)) {
            return 'kein-objekt';
        }

        $id = $event->id ?? null;

        if (is_scalar($id) && '' !== (string) $id) {
            return 'id:'.$id;
        }

        return 'obj:'.spl_object_id($event);
    }
}
