<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Profile;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\Value\ProfileFilter;

/**
 * Lesezugriff auf die Profile (tl_artists, tl_hosts, tl_locations) über Doctrine
 * DBAL mit gebundenen Parametern.
 *
 * Ersetzt die statischen Finder der Models (findByIdOrAlias, findByMemberId,
 * findAllArtists/findAllHosts/findAllLocations). Abfragen, Filter und Sortierung
 * entsprechen exakt den bisherigen Model-Methoden; die Anreicherung mit Bilddaten
 * (bisher "avatar_file") gehört nicht hierher, sondern in die Ansicht
 * (Service\Image\FigureFactory).
 *
 * Alle Methoden liefern die Tabellenzeilen als assoziative Arrays mit den
 * Spaltennamen als Schlüssel; "avatar" ist die binäre UUID wie in der Datenbank.
 */
final class ProfileRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KulturnetzConfig $config,
    ) {
    }

    /**
     * Profil über ID oder Alias (wie Contao\Model::findByIdOrAlias): Ein Wert aus
     * Ziffern ohne führende Null gilt als ID, alles andere als Alias, der
     * Alias-Vergleich unterscheidet Groß- und Kleinschreibung. Gesperrte Profile
     * werden wie bisher gefunden (Anzeige per Direktlink, E39).
     *
     * @return array<string, mixed>|null
     */
    public function findByAliasOrId(ProfileType $type, string $aliasOrId): array|null
    {
        if ('' === $aliasOrId) {
            return null;
        }

        $table = $type->table();

        if (preg_match('/^[1-9]\d*$/', $aliasOrId)) {
            $row = $this->connection->fetchAssociative(
                "SELECT * FROM $table WHERE id = ? LIMIT 1",
                [(int) $aliasOrId],
                [ParameterType::INTEGER],
            );
        } else {
            $row = $this->connection->fetchAssociative(
                "SELECT * FROM $table WHERE alias = CAST(? AS BINARY) LIMIT 1",
                [$aliasOrId],
            );
        }

        return false === $row ? null : $row;
    }

    /**
     * Profil über die ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(ProfileType $type, int $id): array|null
    {
        $row = $this->connection->fetchAssociative(
            "SELECT * FROM {$type->table()} WHERE id = ? LIMIT 1",
            [$id],
            [ParameterType::INTEGER],
        );

        return false === $row ? null : $row;
    }

    /**
     * Profile mehrerer IDs in der Reihenfolge der Datenbank (aufsteigende ID).
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function findByIds(ProfileType $type, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            "SELECT * FROM {$type->table()} WHERE id IN (?) ORDER BY id",
            [array_values(array_map(intval(...), $ids))],
            [ArrayParameterType::INTEGER],
        );
    }

    /**
     * Alle Profile eines Mitglieds, auch gesperrte (Liste "Eigene Profile").
     * Reihenfolge wie bisher die der Tabelle, also aufsteigende ID.
     *
     * @return list<array<string, mixed>>
     */
    public function findByMemberId(ProfileType $type, int $memberId): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT * FROM {$type->table()} WHERE member_id = ? ORDER BY id",
            [$memberId],
            [ParameterType::INTEGER],
        );
    }

    /**
     * Öffentliches Verzeichnis einer Profilart: nicht gesperrte Profile, das
     * Musterprofil (Konfiguration sample_profiles) zuerst, danach die neuesten
     * zuerst, höchstens $limit Einträge.
     *
     * Filter wie bisher in den Models: Kategorie (nur Profilarten mit
     * Kategorie-Filter, Wert ungleich "Alle") als Teiltreffer in "sparten",
     * Volltext als Teiltreffer in "name" oder "ort". Die Suchbegriffe werden wie
     * bisher unverändert in das LIKE-Muster übernommen (gebunden, aber ohne
     * Maskierung von "%" und "_"), damit die Trefferliste gleich bleibt.
     *
     * @return list<array<string, mixed>>
     */
    public function findDirectory(ProfileType $type, int $limit, ProfileFilter $filter = new ProfileFilter()): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($type->table())
            ->where('disable != :disabled')
            ->setParameter('disabled', '1')
            ->orderBy('(id = :sample)', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setParameter('sample', $type->sampleProfileId($this->config), ParameterType::INTEGER)
            ->setMaxResults(max(0, $limit))
        ;

        if ($type->hasCategoryFilter() && $filter->hasCategory()) {
            $qb
                ->andWhere('sparten LIKE :category')
                ->setParameter('category', '%'.$filter->category.'%')
            ;
        }

        if ($filter->hasText()) {
            $qb
                ->andWhere('(name LIKE :text OR ort LIKE :text)')
                ->setParameter('text', '%'.$filter->text.'%')
            ;
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }
}
