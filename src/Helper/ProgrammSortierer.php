<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Helper;

/**
 * Sortiert das SE-KulturTage-Programm innerhalb der einzelnen Tage.
 *
 * Die Tage selbst bleiben chronologisch und zusammenhängend, das Programm liest
 * sich also weiterhin von vorne nach hinten. Innerhalb eines Tages stehen die
 * Veranstaltungen nach Uhrzeit aufsteigend.
 *
 * Ausnahme sind langlaufende Veranstaltungen (typischerweise Ausstellungen, die
 * sich über viele Festivaltage ziehen). Sie beginnen meist früh am Vormittag und
 * stünden bei reiner Zeitsortierung an jedem einzelnen Tag ganz oben. Damit die
 * Aufmerksamkeit nicht dauerhaft bei denselben Einträgen landet, werden nur
 * diese an einer aus einem Hash abgeleiteten Position in den Tag eingestreut.
 * Die übrigen Veranstaltungen behalten untereinander strikt die Zeitreihenfolge.
 *
 * Die Reihenfolge ist tagesstabil: Aus dem Seed (Default: der heutige
 * Kalendertag) wird per crc32 ein Hash gebildet, wodurch jeder Aufruf innerhalb
 * desselben Kalendertags bei unveränderter Filterung dieselbe Reihenfolge
 * liefert. Am Folgetag ergibt sich eine andere Reihenfolge. Die Zeitreihenfolge
 * der normalen Veranstaltungen ist filterunabhängig; die Einfügeposition der
 * Langläufer hängt an der Zahl der Einfügeplätze und verschiebt sich deshalb,
 * wenn ein Filter die Menge eines Tages ändert.
 *
 * Die Klasse arbeitet bewusst ohne Contao-Abhängigkeiten und ohne globale
 * Zufallsquelle, damit sie isoliert prüfbar bleibt und keine Nebenwirkungen auf
 * den Zufallszustand des laufenden Prozesses hat.
 */
class ProgrammSortierer
{
    /**
     * Sortiert die Trefferliste tagweise um.
     *
     * @param array       $data                 Chronologisch sortierte Liste, Schlüssel "<tstamp>_<id>",
     *                                          Werte sind Event-Objekte mit "dates_formatted"
     * @param string|null $seed                 Seed für die Hash-Bildung. Null bedeutet: heutiger Kalendertag,
     *                                          die Reihenfolge wechselt also täglich einmal.
     * @param int         $langlaeuferSchwelle  Ab wie vielen distinkten künftigen Kalendertagen eine
     *                                          Veranstaltung als langlaufend gilt
     * @param int|null    $abZeitpunkt          Untergrenze für die Tageszählung. Null bedeutet: heute Mitternacht,
     *                                          dieselbe Grenze wie in SekEventsModel::findAllSekEvents().
     *
     * @return array Dieselben Schlüssel wie die Eingabe, lediglich in anderer Reihenfolge
     */
    public static function sortiereTage(array $data, ?string $seed = null, int $langlaeuferSchwelle = 3, ?int $abZeitpunkt = null): array
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
            $langlaeuferSchluessel = [];

            foreach ($termine as $key => $tstamp) {
                $event = $data[$key];
                $cacheSchluessel = self::cacheSchluessel($event);

                if (!array_key_exists($cacheSchluessel, $tageCache)) {
                    $tageCache[$cacheSchluessel] = self::zaehleKuenftigeTage($event, $abZeitpunkt);
                }

                if ($tageCache[$cacheSchluessel] >= $langlaeuferSchwelle) {
                    $langlaeuferSchluessel[] = $key;
                } else {
                    $normale[$key] = $tstamp;
                }
            }

            // Normale Veranstaltungen: strikt nach Startzeit. Bei identischer
            // Startzeit entscheidet der Hash, damit auch hier nicht dauerhaft
            // dieselbe Veranstaltung vorne steht (zuvor entschied die ID).
            $tagesliste = array_keys($normale);
            usort($tagesliste, static function ($a, $b) use ($normale, $seed, $tag) {
                if ($normale[$a] !== $normale[$b]) {
                    return $normale[$a] <=> $normale[$b];
                }

                return self::vergleicheHash($seed, (string) $tag, 'gleichzeitig', (string) $a, (string) $b);
            });

            // Langläufer in einer eigenen, ebenfalls hash-basierten Reihenfolge
            // abarbeiten. Sonst hinge die Einfügereihenfolge an der Startzeit und
            // der früheste Langläufer säße systematisch weit vorne.
            usort($langlaeuferSchluessel, static function ($a, $b) use ($seed, $tag) {
                return self::vergleicheHash($seed, (string) $tag, 'reihenfolge', (string) $a, (string) $b);
            });

            foreach ($langlaeuferSchluessel as $key) {
                // Die Position wird gegen die bereits gewachsene Liste gerechnet,
                // sie reicht also von ganz vorne bis ganz hinten. Besteht der Tag
                // nur aus Langläufern, ist die Liste anfangs leer: Der erste
                // Eintrag landet zwangsläufig auf Position 0, alle weiteren
                // verschieben ihn und es entsteht trotzdem eine Permutation.
                $position = self::hash($seed, (string) $tag, 'position', (string) $key) % (count($tagesliste) + 1);
                array_splice($tagesliste, $position, 0, [$key]);
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
     * Bildet den Hash für einen Termin. Seed, Kalendertag und Schlüssel gehen
     * gemeinsam ein: Ohne den Kalendertag bekäme derselbe Langläufer an jedem
     * Festivaltag dieselbe Position, ohne den Schlüssel wären alle Termine eines
     * Tages gleichwertig. Der Zweck trennt die Verwendungen voneinander, damit
     * Einfügereihenfolge und Einfügeposition nicht miteinander korrelieren.
     */
    private static function hash(string $seed, string $tag, string $zweck, string $key): int
    {
        // Maskierung auf 31 Bit: crc32() liefert nur auf 64-Bit-PHP durchgehend
        // nicht-negative Werte, auf 32-Bit-Systemen dagegen auch negative. Ein
        // negativer Rest ergäbe bei array_splice() einen vom Ende her gezählten
        // Offset und damit eine schiefe Verteilung der Einfügepositionen.
        return crc32($seed.'|'.$tag.'|'.$zweck.'|'.$key) & 0x7FFFFFFF;
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
