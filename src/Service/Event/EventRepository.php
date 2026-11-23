<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

use Doctrine\DBAL\Connection;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventCriteria;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventDateFilter;
use SeKultur\ContaoKulturnetzBundle\Service\Search\SavedRecord;

/**
 * Lesezugriff auf die Veranstaltungstabellen über Doctrine DBAL mit gebundenen
 * Parametern (bisher die statischen Finder der Models EventsModel und
 * SekEventsModel).
 *
 * Die Methoden mit Formatierung (findByAliasOrId, findByMemberId, findByProfile)
 * liefern Ergebnisse des EventFormatter. Listen sind wie bisher nach dem Schlüssel
 * "<Zeitstempel>_<ID>" sortiert; der Zeitstempel ist der nächste Termin (bei
 * events dessen Tag), Veranstaltungen ohne künftigen Termin stehen vorn.
 *
 * Keine Methode liefert unfertige Datensätze (tstamp = 0, "Neu" im Backend ohne
 * Speichern), siehe Service\Search\SavedRecord.
 */
final class EventRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventFormatter $formatter,
    ) {
    }

    /**
     * Veranstaltung per Alias oder numerischer ID, formatiert; null, wenn es sie nicht gibt.
     * Wie bisher ohne Filter auf "disable": nicht freigeschaltete Veranstaltungen
     * sind per Direktlink erreichbar.
     *
     * @return array<string, mixed>|null
     */
    public function findByAliasOrId(EventType $type, string $aliasOrId, int|null $now = null): array|null
    {
        if ('' === $aliasOrId) {
            return null;
        }

        // Wie Contao\Model::findByIdOrAlias(): positive Ganzzahlen sind IDs, alles
        // andere ein Alias, der binär (groß-/kleinschreibungsabhängig) verglichen wird.
        if (preg_match('/^[1-9]\d*$/', $aliasOrId)) {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM '.$type->table().' WHERE id = ? AND '.SavedRecord::CONDITION.' LIMIT 1',
                [(int) $aliasOrId],
            );
        } else {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM '.$type->table().' WHERE alias = CAST(? AS BINARY) AND '.SavedRecord::CONDITION.' LIMIT 1',
                [$aliasOrId],
            );
        }

        return false === $row ? null : $this->formatter->format($type, $row, $now);
    }

    /**
     * Alle Veranstaltungen eines Mitglieds (auch nicht freigeschaltete und
     * vergangene), formatiert und nach nächstem Termin sortiert.
     *
     * @return array<string, array<string, mixed>>
     */
    public function findByMemberId(EventType $type, int $memberId, int|null $now = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM '.$type->table().' WHERE member_id = ? AND '.SavedRecord::CONDITION.' ORDER BY id ASC',
            [$memberId],
        );

        return $this->groupByNextDate($type, $this->formatter->formatMany($type, $rows, $now), false);
    }

    /**
     * Alle Veranstaltungen, die ein Profil verknüpfen (Künstler:in, Veranstalter:in
     * oder Location), formatiert und nach nächstem Termin sortiert. Auch nicht
     * freigeschaltete Veranstaltungen sind enthalten, die Vorlage blendet sie aus.
     *
     * Die Zuordnung ist ein exakter Treffer auf die gespeicherten IDs. Bisher
     * suchte ein LIKE '%id%' im JSON-Text, wodurch die ID 1 auch Veranstaltungen
     * von Profil 11 oder 100 fand. Das SQL-Muster dient nur noch als Vorauswahl,
     * entschieden wird nach dem Dekodieren der ID-Liste.
     *
     * @param string $profileType artists, hosts oder locations (Wert von ProfileType)
     *
     * @return array<string, array<string, mixed>>
     */
    public function findByProfile(EventType $type, string $profileType, int $profileId, int|null $now = null): array
    {
        if (!\in_array($profileType, KulturnetzConfig::PROFILE_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown profile type "%s".', $profileType));
        }

        $column = 'linked_'.$profileType;

        $candidates = $this->connection->fetchAllAssociative(
            'SELECT * FROM '.$type->table().' WHERE '.$column." LIKE ? ESCAPE '!' AND ".SavedRecord::CONDITION.' ORDER BY id ASC',
            ['%'.$profileId.'%'],
        );

        $rows = array_values(array_filter(
            $candidates,
            fn (array $row): bool => \in_array($profileId, $this->decodeIds($row[$column] ?? null), true),
        ));

        return $this->groupByNextDate($type, $this->formatter->formatMany($type, $rows, $now), true);
    }

    /**
     * Aktive (nicht gesperrte) Veranstaltungen für die Suche, neueste ID zuerst,
     * mit den Filtern aus EventCriteria. Zeilen sind unformatiert.
     *
     * Kategorie, Format und Text suchen als "enthält" (LIKE); Platzhalter in der
     * Eingabe (%, _) werden wörtlich gesucht. Der Datumsfilter schränkt per
     * Vorauswahl auf Veranstaltungen ein, die den Tag in "dates" führen; die
     * Auswertung der Zeitpunkte erfolgt in der EventSearch.
     *
     * @return list<array<string, mixed>>
     */
    public function findActive(EventType $type, EventCriteria $criteria, EventDateFilter $dateFilter, int|null $limit = null): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($type->table())
            ->where('disable != :disabled')
            ->setParameter('disabled', '1')
            ->andWhere(SavedRecord::CONDITION)
            ->orderBy('id', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== ($kategorie = $criteria->activeKategorie())) {
            $qb->andWhere("sparten LIKE :kategorie ESCAPE '!'")
                ->setParameter('kategorie', $this->likeContains($kategorie));
        }

        if (null !== ($format = $criteria->activeFormat())) {
            $qb->andWhere("kulturform LIKE :format ESCAPE '!'")
                ->setParameter('format', $this->likeContains($format));
        }

        if ($criteria->hasText()) {
            $pattern = $this->likeContains((string) $criteria->text);

            $qb->andWhere("(name LIKE :textName ESCAPE '!' OR location_ort LIKE :textCity ESCAPE '!')")
                ->setParameter('textName', $pattern)
                ->setParameter('textCity', $pattern);
        }

        if ($dateFilter->active) {
            if ($dateFilter->matchesNothing) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('dates LIKE :datePattern')
                    ->setParameter('datePattern', $dateFilter->likePattern);
            }
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * Schlanke Zeilen aller aktiven Veranstaltungen für die Facetten (Ort,
     * Adresse, Termine), aufsteigend nach ID. Unabhängig von Suchfiltern.
     *
     * @return list<array<string, mixed>>
     */
    public function findActiveForFacets(EventType $type): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, dates, location_ort, location_adresse, location_plz FROM '.$type->table()." WHERE disable != ? AND ".SavedRecord::CONDITION.' ORDER BY id ASC',
            ['1'],
        );
    }

    /**
     * Sortiert formatierte Veranstaltungen nach nächstem Termin.
     *
     * @param list<array<string, mixed>> $events
     * @param bool                       $strategyKey true: Schlüssel wie in den Suchergebnissen der Art (Profil-Ansicht),
     *                                                false: Mitternacht des Tages des Termins (Mitglieder-Ansicht)
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupByNextDate(EventType $type, array $events, bool $strategyKey): array
    {
        $strategy = $this->formatter->getDatesStrategy($type);
        $list = [];

        foreach ($events as $event) {
            $nextDate = (int) $event['nextdate'];
            $stamp = $strategyKey ? $strategy->resultKeyTimestamp($nextDate) : (int) strtotime(date('Y-m-d', $nextDate));
            $list[$stamp.'_'.$event['id']] = $event;
        }

        ksort($list, SORT_STRING);

        return $list;
    }

    /**
     * Vorauswahl-Muster "enthält" mit wörtlich gesuchten Platzhaltern.
     */
    private function likeContains(string $value): string
    {
        return '%'.strtr($value, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }

    /**
     * @return list<int>
     */
    private function decodeIds(mixed $json): array
    {
        $decoded = \is_string($json) ? json_decode($json, true) : null;
        $ids = [];

        foreach (\is_array($decoded) ? $decoded : [] as $id) {
            if ((\is_int($id) && $id > 0) || (\is_string($id) && ctype_digit($id) && (int) $id > 0)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
