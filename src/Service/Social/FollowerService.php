<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Social;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Folgen zwischen Profilen (Tabelle tl_followers) über Doctrine DBAL.
 *
 * Ersetzt FollowersModel und das Skript follow.php. Ein Eintrag verknüpft ein
 * Profil des Mitglieds (follower_type/follower_id) mit einem anderen Profil
 * (following_type/following_id); die Typen stehen wie bisher als Tabellenname
 * ("tl_artists") in der Datenbank. Erlaubt sind nur die drei Profilarten
 * (ProfileType).
 *
 * Schlüssel eines Profils in Formularen und Listen: "<tabelle>-<id>", z. B.
 * "tl_artists-12" (bisheriges Format von follower[] und following_profiles).
 */
final class FollowerService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProfileRepository $profileRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Profile, die dem Profil folgen ("Gefolgt von").
     *
     * Reihenfolge der Einträge in tl_followers (aufsteigende ID); mehrfache
     * Einträge desselben Profils erscheinen einmal an der ersten Stelle (bisher
     * Schlüssel "<tabelle>-<id>" im Array). Profile, die es nicht mehr gibt oder
     * deren Typ unbekannt ist, haben "profile" = null.
     *
     * @return list<array{key: string, type: ProfileType|null, profile: array<string, mixed>|null}>
     */
    public function findFollowers(ProfileType $type, int $profileId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT follower_type AS profile_type, follower_id AS profile_id FROM tl_followers WHERE following_id = ? AND following_type = ? ORDER BY id',
            [$profileId, $type->table()],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        return $this->withProfiles($rows);
    }

    /**
     * Profile, denen das Profil folgt ("Vernetzt mit"), Regeln wie findFollowers().
     *
     * @return list<array{key: string, type: ProfileType|null, profile: array<string, mixed>|null}>
     */
    public function findFollowings(ProfileType $type, int $profileId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT following_type AS profile_type, following_id AS profile_id FROM tl_followers WHERE follower_id = ? AND follower_type = ? ORDER BY id',
            [$profileId, $type->table()],
            [ParameterType::INTEGER, ParameterType::STRING],
        );

        return $this->withProfiles($rows);
    }

    /**
     * Schlüssel der Profile des Mitglieds, die dem Profil folgen (bisher
     * FollowersModel::findMembersFollowingsFor(), Variable following_profiles).
     *
     * @return list<string>
     */
    public function findMemberFollowerKeys(ProfileType $type, int $profileId, int $memberId): array
    {
        if ($memberId < 1) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT follower_type, follower_id FROM tl_followers WHERE following_id = ? AND following_type = ? AND member_id = ? ORDER BY id',
            [$profileId, $type->table(), $memberId],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER],
        );

        return array_values(array_unique(array_map(
            static fn (array $row): string => $row['follower_type'].'-'.$row['follower_id'],
            $rows,
        )));
    }

    /**
     * Alle Profile des Mitglieds je Tabelle in der Reihenfolge Künstler*innen,
     * Veranstalter*innen, Locations (bisher Variable member_profiles). Auch
     * gesperrte Profile, wie bisher.
     *
     * @return array<string, list<array<string, mixed>>> Tabellenname => Profile
     */
    public function findMemberProfiles(int $memberId): array
    {
        $profiles = [];

        foreach (ProfileType::cases() as $type) {
            $profiles[$type->table()] = $memberId > 0 ? $this->profileRepository->findByMemberId($type, $memberId) : [];
        }

        return $profiles;
    }

    /**
     * Schaltet das Folgen je Profil des Mitglieds um (wie follow.php): Folgt das
     * Profil bereits, wird der Eintrag gelöscht, sonst angelegt.
     *
     * Vor jeder Änderung wird alles geprüft; bei einem Fehler ändert sich nichts.
     *  - Ziel: Profilart aus der festen Liste, Profil existiert (sonst 422 bzw. 404);
     *    der Sperrstatus des Ziels spielt keine Rolle: auch gesperrte fremde
     *    Profile (disable = 1), die per Direktlink erreichbar sind, lassen sich
     *    wie bisher (follow.php) folgen und entfolgen,
     *  - jedes Follower-Profil: Schlüssel gültig, Profil gehört dem Mitglied (403),
     *  - kein Profil folgt sich selbst (422).
     *
     * @param list<string> $followerKeys Schlüssel "<tabelle>-<id>" der eigenen Profile
     *
     * @return int Anzahl der umgeschalteten Einträge
     *
     * @throws SocialActionException
     */
    public function toggle(int $memberId, string $followingTable, int $followingId, array $followerKeys): int
    {
        if ($memberId < 1) {
            throw SocialActionException::forbidden();
        }

        $followingType = ProfileType::tryFromTypeOrTable($followingTable);

        if (null === $followingType || !str_starts_with($followingTable, 'tl_') || $followingId < 1) {
            throw SocialActionException::invalid(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        $target = $this->profileRepository->findById($followingType, $followingId);

        if (null === $target) {
            throw SocialActionException::notFound(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        $followers = [];

        foreach ($followerKeys as $key) {
            [$followerType, $followerId] = $this->parseKey($key);

            $profile = $this->profileRepository->findById($followerType, $followerId);

            if (null === $profile || (int) $profile['member_id'] !== $memberId) {
                throw SocialActionException::forbidden();
            }

            if ($followerType === $followingType && $followerId === $followingId) {
                throw SocialActionException::invalid(SocialActionException::MESSAGE_SELF_FOLLOW);
            }

            $followers[] = [$followerType, $followerId];
        }

        try {
            $this->connection->transactional(function (Connection $connection) use ($followers, $followingType, $followingId, $memberId): void {
                foreach ($followers as [$followerType, $followerId]) {
                    $this->toggleOne($connection, $followerType, $followerId, $followingType, $followingId, $memberId);
                }
            });
        } catch (DbalException $exception) {
            throw SocialActionException::databaseError($exception);
        }

        return \count($followers);
    }

    private function toggleOne(Connection $connection, ProfileType $followerType, int $followerId, ProfileType $followingType, int $followingId, int $memberId): void
    {
        $existing = $connection->fetchOne(
            'SELECT id FROM tl_followers WHERE follower_id = ? AND follower_type = ? AND following_id = ? AND following_type = ? LIMIT 1',
            [$followerId, $followerType->table(), $followingId, $followingType->table()],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING],
        );

        if (false !== $existing) {
            $connection->delete('tl_followers', ['id' => (int) $existing], ['id' => ParameterType::INTEGER]);

            return;
        }

        $connection->insert(
            'tl_followers',
            [
                'follower_id' => $followerId,
                'follower_type' => $followerType->table(),
                'following_id' => $followingId,
                'following_type' => $followingType->table(),
                'tstamp' => $this->clock->now()->getTimestamp(),
                'member_id' => $memberId,
            ],
            [
                'follower_id' => ParameterType::INTEGER,
                'following_id' => ParameterType::INTEGER,
                'tstamp' => ParameterType::INTEGER,
                'member_id' => ParameterType::INTEGER,
            ],
        );
    }

    /**
     * Zerlegt "<tabelle>-<id>"; nur die drei Profiltabellen und positive IDs.
     *
     * @return array{0: ProfileType, 1: int}
     *
     * @throws SocialActionException
     */
    private function parseKey(string $key): array
    {
        if (1 !== preg_match('/^(tl_[a-z]+)-([1-9]\d*)$/', $key, $matches)) {
            throw SocialActionException::invalid(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        $type = ProfileType::tryFromTypeOrTable($matches[1]);

        if (null === $type) {
            throw SocialActionException::invalid(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        return [$type, (int) $matches[2]];
    }

    /**
     * @param list<array{profile_type: string, profile_id: int|string}> $rows
     *
     * @return list<array{key: string, type: ProfileType|null, profile: array<string, mixed>|null}>
     */
    private function withProfiles(array $rows): array
    {
        $keys = [];
        $idsByType = [];

        foreach ($rows as $row) {
            $key = $row['profile_type'].'-'.$row['profile_id'];

            if (isset($keys[$key])) {
                continue;
            }

            $type = ProfileType::tryFromTypeOrTable((string) $row['profile_type']);
            $keys[$key] = [$type, (int) $row['profile_id']];

            if (null !== $type) {
                $idsByType[$type->value][] = (int) $row['profile_id'];
            }
        }

        $profiles = [];

        foreach ($idsByType as $typeValue => $ids) {
            foreach ($this->profileRepository->findByIds(ProfileType::from($typeValue), $ids) as $profile) {
                $profiles[$typeValue][(int) $profile['id']] = $profile;
            }
        }

        $result = [];

        foreach ($keys as $key => [$type, $id]) {
            $result[] = [
                'key' => (string) $key,
                'type' => $type,
                'profile' => null !== $type ? ($profiles[$type->value][$id] ?? null) : null,
            ];
        }

        return $result;
    }
}
