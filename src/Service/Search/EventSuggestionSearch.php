<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Search;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Vorschläge für das Suchfeld der Veranstaltungsübersichten (Ersatz für
 * files/se-kultur.de/code/autocompleteEvents.php und autocompleteSekevents.php).
 *
 * Gesucht wird in name und location_ort der freigeschalteten Veranstaltungen
 * (disable != "1", ohne unfertige Datensätze mit tstamp = 0, siehe SavedRecord).
 * Wie bisher erscheint je Ort höchstens ein Vorschlag (das
 * Altskript gruppierte nach location_ort): der Datensatz mit der kleinsten ID je
 * Ort, sortiert nach Ort, höchstens 15. Passt der Name, ist es ein
 * Veranstaltungsvorschlag (label, alias, id, avatar, column "name"), sonst ein
 * Ortsvorschlag (label, column "location_ort").
 *
 * Abweichungen zum Altskript (dokumentiert im Paketbericht): Werte ohne
 * HTML-Entities, Bildpfad ab der Wurzel, ob der Name passt, entscheidet derselbe
 * Vergleich wie die Suche (Sortierfolge der Datenbank, also ohne Unterschied
 * zwischen Groß- und Kleinschreibung und Akzenten), LIKE-Platzhalter wörtlich.
 */
final class EventSuggestionSearch
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SuggestionTerm $term,
        private readonly TextNormalizer $normalizer,
        private readonly AvatarUrlResolver $avatars,
    ) {
    }

    /**
     * @param string $basePath Basis-Pfad des Requests für die Bildadressen
     *
     * @return list<array<string, string>>
     */
    public function suggest(EventType $type, string $term, string $basePath = ''): array
    {
        $table = $type->table();
        $pattern = $this->term->containsPattern($term);
        $escape = SuggestionTerm::ESCAPE_CHAR;

        // Ein Datensatz je Ort (kleinste ID), wie die Gruppierung des Altskripts.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, alias, name, avatar, location_ort, CASE WHEN name LIKE ? ESCAPE '$escape' THEN 1 ELSE 0 END AS name_hit
            FROM $table
            WHERE id IN (
                SELECT MIN(id) FROM $table
                WHERE (name LIKE ? ESCAPE '$escape' OR location_ort LIKE ? ESCAPE '$escape') AND disable != '1' AND ".SavedRecord::CONDITION."
                GROUP BY location_ort
            )
            ORDER BY location_ort, id
            LIMIT ".SuggestionTerm::LIMIT,
            [$pattern, $pattern, $pattern],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
        );

        $paths = $this->avatars->findPaths(array_column($rows, 'avatar'));
        $result = [];

        foreach ($rows as $row) {
            if (1 === (int) $row['name_hit']) {
                $result[] = [
                    'label' => $this->normalizer->toPlainText((string) $row['name']),
                    'alias' => (string) $row['alias'],
                    'id' => (string) $row['id'],
                    'avatar' => $this->avatars->toUrl($paths[(string) $row['avatar']] ?? null, $basePath),
                    'column' => 'name',
                ];
            } else {
                $result[] = [
                    'label' => $this->normalizer->toPlainText((string) $row['location_ort']),
                    'column' => 'location_ort',
                ];
            }
        }

        return $result;
    }
}
