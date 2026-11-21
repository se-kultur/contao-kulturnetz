<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Social;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;

/**
 * Beiträge der Profile (Tabelle tl_postings) über Doctrine DBAL.
 *
 * Ersetzt PostingsModel und das Skript posting.php. Lesende Methoden liefern die
 * Zeilen von tl_postings plus
 *  - "type": Profilart (ProfileType) oder null bei unbekanntem profile_type,
 *  - "profile": Zeile des Profils oder null, wenn es das Profil nicht mehr gibt.
 *
 * Gespeichert wird der Text im Contao-Format (TextNormalizer, Entscheidung E21);
 * die Ausgabe in Twig erfolgt mit dem Contao-Escaper, der vorhandene Entities nicht
 * doppelt kodiert.
 */
final class PostingService
{
    /**
     * Mindestlänge des Beitragstextes wie bisher in posting.php: mehr als zehn
     * Byte der unveränderten Eingabe (strlen).
     */
    public const MIN_CONTENT_BYTES = 11;

    public function __construct(
        private readonly Connection $connection,
        private readonly ProfileRepository $profileRepository,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly TextNormalizer $textNormalizer,
        private readonly KulturnetzConfig $config,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Beiträge eines Profils, neueste zuerst, höchstens limits.profile_postings
     * (bisher PostingsModel::findAllFor()).
     *
     * @return list<array<string, mixed>>
     */
    public function findForProfile(ProfileType $type, int $profileId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_postings WHERE profile_id = ? AND profile_type = ? ORDER BY id DESC LIMIT ?',
            [$profileId, $type->table(), $this->config->getProfilePostingLimit()],
            [ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER],
        );

        return $this->withProfiles($rows);
    }

    /**
     * Persönlicher Feed eines Mitglieds (bisher PostingsModel::findForMemberId()):
     * eigene Beiträge und Beiträge der Profile, denen ein Profil des Mitglieds
     * folgt; neueste zuerst, höchstens limits.member_feed.
     *
     * Wie bisher wird nur die Profil-ID der gefolgten Profile verglichen, nicht die
     * Profilart (OFFENE-FRAGEN.md, "Mitglieder-Feed zeigt Postings fremder Profile
     * gleicher ID", Annahme A).
     *
     * Danach entfallen wie bisher im Template Beiträge ohne Profil und Beiträge
     * gesperrter Profile, außer den eigenen. Das Limit gilt vor diesem Filter.
     *
     * @return list<array<string, mixed>>
     */
    public function findMemberFeed(int $memberId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_postings WHERE profile_id IN (SELECT following_id FROM tl_followers WHERE member_id = ?) OR member_id = ? ORDER BY id DESC LIMIT ?',
            [$memberId, $memberId, $this->config->getMemberFeedLimit()],
            [ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        return array_values(array_filter(
            $this->withProfiles($rows),
            static fn (array $posting): bool => null !== $posting['profile']
                && ('1' !== (string) $posting['profile']['disable'] || (int) $posting['member_id'] === $memberId),
        ));
    }

    /**
     * Öffentlicher Feed (bisher PostingsModel::findLastPostings()): neueste
     * Beiträge aller Profile, höchstens $limit; danach entfallen wie bisher im
     * Template Beiträge ohne Profil und Beiträge gesperrter Profile.
     *
     * @return list<array<string, mixed>>
     */
    public function findLatest(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_postings ORDER BY id DESC LIMIT ?',
            [max(0, $limit)],
            [ParameterType::INTEGER],
        );

        return array_values(array_filter(
            $this->withProfiles($rows),
            static fn (array $posting): bool => null !== $posting['profile'] && '1' !== (string) $posting['profile']['disable'],
        ));
    }

    /**
     * Legt einen Beitrag für ein Profil des Mitglieds an.
     *
     * @return int ID des neuen Beitrags
     *
     * @throws SocialActionException
     */
    public function create(int $memberId, string $profileTypeOrTable, int $profileId, string $content): int
    {
        $this->assertContentLength($content);

        $type = ProfileType::tryFromTypeOrTable($profileTypeOrTable);

        if (null === $type || !$this->ownershipChecker->ownsRecord($type->value, $profileId, $memberId)) {
            throw SocialActionException::forbidden();
        }

        try {
            $this->connection->insert(
                'tl_postings',
                [
                    'profile_id' => $profileId,
                    'profile_type' => $type->table(),
                    'member_id' => $memberId,
                    'content' => $this->textNormalizer->normalize($content),
                    'tstamp' => $this->clock->now()->getTimestamp(),
                ],
                [
                    'profile_id' => ParameterType::INTEGER,
                    'member_id' => ParameterType::INTEGER,
                    'tstamp' => ParameterType::INTEGER,
                ],
            );

            return (int) $this->connection->lastInsertId();
        } catch (DbalException $exception) {
            throw SocialActionException::databaseError($exception);
        }
    }

    /**
     * Ändert den Text eines eigenen Beitrags. Der Zeitstempel bleibt wie bisher
     * unverändert (relative Zeitangabe "Vor ..." bezieht sich auf das Anlegen).
     *
     * @throws SocialActionException
     */
    public function update(int $memberId, int $postingId, string $content): void
    {
        if ('' === trim($content)) {
            throw SocialActionException::invalid(SocialActionException::MESSAGE_CONTENT_TOO_SHORT);
        }

        $this->assertOwnPosting($memberId, $postingId, SocialActionException::MESSAGE_EDIT_DENIED);

        try {
            $this->connection->update(
                'tl_postings',
                ['content' => $this->textNormalizer->normalize($content)],
                ['id' => $postingId],
                ['id' => ParameterType::INTEGER],
            );
        } catch (DbalException $exception) {
            throw SocialActionException::databaseError($exception);
        }
    }

    /**
     * Löscht einen eigenen Beitrag.
     *
     * @throws SocialActionException
     */
    public function delete(int $memberId, int $postingId): void
    {
        $this->assertOwnPosting($memberId, $postingId, SocialActionException::MESSAGE_DELETE_DENIED);

        try {
            $this->connection->delete('tl_postings', ['id' => $postingId], ['id' => ParameterType::INTEGER]);
        } catch (DbalException $exception) {
            throw SocialActionException::databaseError($exception);
        }
    }

    /**
     * @throws SocialActionException
     */
    private function assertContentLength(string $content): void
    {
        if (\strlen($content) < self::MIN_CONTENT_BYTES) {
            throw SocialActionException::invalid(SocialActionException::MESSAGE_CONTENT_TOO_SHORT);
        }
    }

    /**
     * @throws SocialActionException
     */
    private function assertOwnPosting(int $memberId, int $postingId, string $deniedMessage): void
    {
        $exists = $postingId > 0 && false !== $this->connection->fetchOne(
            'SELECT id FROM tl_postings WHERE id = ?',
            [$postingId],
            [ParameterType::INTEGER],
        );

        if (!$exists) {
            throw SocialActionException::notFound(SocialActionException::MESSAGE_POSTING_NOT_FOUND);
        }

        if (!$this->ownershipChecker->ownsPosting($postingId, $memberId)) {
            throw SocialActionException::forbidden($deniedMessage);
        }
    }

    /**
     * Ergänzt Profilart und Profilzeile; lädt je Profilart alle Profile in einer
     * Abfrage.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withProfiles(array $rows): array
    {
        $idsByType = [];

        foreach ($rows as $row) {
            $type = ProfileType::tryFromTypeOrTable((string) $row['profile_type']);

            if (null !== $type) {
                $idsByType[$type->value][] = (int) $row['profile_id'];
            }
        }

        $profiles = [];

        foreach ($idsByType as $typeValue => $ids) {
            foreach ($this->profileRepository->findByIds(ProfileType::from($typeValue), array_values(array_unique($ids))) as $profile) {
                $profiles[$typeValue][(int) $profile['id']] = $profile;
            }
        }

        return array_map(
            static function (array $row) use ($profiles): array {
                $type = ProfileType::tryFromTypeOrTable((string) $row['profile_type']);
                $row['type'] = $type;
                $row['profile'] = null !== $type ? ($profiles[$type->value][(int) $row['profile_id']] ?? null) : null;

                return $row;
            },
            $rows,
        );
    }
}
