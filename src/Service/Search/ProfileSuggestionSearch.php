<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Search;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Vorschläge für die Profilfelder der Veranstaltungsformulare (Ersatz für
 * files/se-kultur.de/code/autocompleteArtists.php, autocompleteHosts.php und
 * autocompleteLocations.php).
 *
 * Gesucht wird im Namen; gefunden werden freigeschaltete Profile und die eigenen
 * gesperrten Profile des angemeldeten Mitglieds (bisher steuerte ein frei
 * wählbarer URL-Parameter member_id, wessen gesperrte Profile erscheinen).
 * Höchstens 15 Treffer in Reihenfolge der ID.
 *
 * Antwortobjekte mit denselben Schlüsseln wie bisher, alle Werte als Text ohne
 * HTML-Entities: label, value, alias, id, avatar, bei Veranstalter*innen und
 * Locations die Kontaktdaten (ort, plz, adresse, telefon, email, website, bei
 * Locations zusätzlich barrierefreiheit), disable ("1" oder leer), owned ("1"/"0").
 */
final class ProfileSuggestionSearch
{
    private const CONTACT_FIELDS = [
        'artists' => [],
        'hosts' => ['ort', 'plz', 'adresse', 'telefon', 'email', 'website'],
        'locations' => ['ort', 'plz', 'adresse', 'telefon', 'email', 'website', 'barrierefreiheit'],
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly SuggestionTerm $term,
        private readonly TextNormalizer $normalizer,
        private readonly AvatarUrlResolver $avatars,
    ) {
    }

    /**
     * @param int    $memberId angemeldetes Mitglied (eigene gesperrte Profile)
     * @param string $basePath Basis-Pfad des Requests für die Bildadressen
     *
     * @return list<array<string, string>>
     */
    public function suggest(ProfileType $type, string $term, int $memberId, string $basePath = ''): array
    {
        $contactFields = self::CONTACT_FIELDS[$type->value];
        $columns = ['id', 'alias', 'name', 'avatar', ...$contactFields, 'disable', 'member_id'];
        $escape = SuggestionTerm::ESCAPE_CHAR;

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                "SELECT %s FROM %s WHERE name LIKE ? ESCAPE '%s' AND (disable != '1' OR member_id = ?) ORDER BY id LIMIT %d",
                implode(', ', $columns),
                $type->table(),
                $escape,
                SuggestionTerm::LIMIT,
            ),
            [$this->term->containsPattern($term), $memberId],
            [ParameterType::STRING, ParameterType::INTEGER],
        );

        $paths = $this->avatars->findPaths(array_column($rows, 'avatar'));
        $result = [];

        foreach ($rows as $row) {
            $name = $this->normalizer->toPlainText((string) $row['name']);

            $item = [
                'label' => $name,
                'value' => $name,
                'alias' => (string) $row['alias'],
                'id' => (string) $row['id'],
                'avatar' => $this->avatars->toUrl($paths[(string) $row['avatar']] ?? null, $basePath),
            ];

            foreach ($contactFields as $field) {
                $item[$field] = $this->normalizer->toPlainText((string) $row[$field]);
            }

            $item['disable'] = (string) $row['disable'];
            $item['owned'] = $memberId > 0 && (int) $row['member_id'] === $memberId ? '1' : '0';

            $result[] = $item;
        }

        return $result;
    }
}
