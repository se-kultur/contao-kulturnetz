<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Profile;

use SeKultur\ContaoKulturnetzBundle\Service\Profile\Value\ProfileFilter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Liest die Filterwerte der Verzeichnis-Suchformulare aus der Query des Requests.
 *
 * Ersetzt die in allen "...ListAll"-Modulen kopierte Schleife über $_GET: Nur
 * bekannte Schlüssel werden übernommen, jeder Wert muss ein String sein, Arrays
 * (z. B. ?text[]=x) werden verworfen. So bleiben Treffer und Reihenfolge für
 * normale Parameter unverändert, und manipulierte Parameter führen nicht zu
 * PHP-Fehlern.
 */
final class ProfileFilterFactory
{
    /**
     * Liefert die String-Werte der genannten Query-Parameter; fehlende oder
     * nicht als String übergebene Parameter fehlen im Ergebnis.
     *
     * @param list<string> $keys
     *
     * @return array<string, string>
     */
    public function fromRequest(Request $request, array $keys): array
    {
        $query = $request->query->all();
        $values = [];

        foreach ($keys as $key) {
            if (isset($query[$key]) && \is_string($query[$key])) {
                $values[$key] = $query[$key];
            }
        }

        return $values;
    }

    /**
     * Filter für das Verzeichnis einer Profilart; der Kategorie-Parameter wird
     * nur bei Profilarten mit Kategorie-Filter gelesen (heute nur Künstler*innen).
     */
    public function createProfileFilter(Request $request, ProfileType $type): ProfileFilter
    {
        $values = $this->fromRequest($request, $type->searchKeys());

        return new ProfileFilter($values['kategorie'] ?? null, $values['text'] ?? null);
    }
}
