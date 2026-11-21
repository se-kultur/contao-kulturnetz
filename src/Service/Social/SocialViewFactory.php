<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Social;

use Contao\FilesModel;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Image\ImageSize;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Baut die Template-Daten der Social-Partials unter
 * contao/templates/kulturnetz/social/ (Reiter Veranstaltungen, Beiträge und
 * Netzwerk eines Profils, Folgen-Dialog) und der Beitrags-Feeds.
 *
 * Die Sichtbarkeitsregeln entsprechen den bisherigen Templates
 * (elements/tabs-*.html5, modal-followings.html5, mod_events_list.html5,
 * mod_sekevents_list.html5, mod_postings_list*.html5).
 *
 * Bilder kommen als ['src' => URL, 'alt' => Text]: Titelbilder in der Größe
 * kulturnetz_avatar (120 × 120) bzw. kulturnetz_list (272 × 272), ohne Bild das
 * Ersatzbild in derselben Größe.
 *
 * Öffentlich nur für die befristeten Übergangsbrücken (Entscheidung E34) in
 * contao/templates/elements/{tabs-events,tabs-followers,tabs-postings,modal-followings}.html5,
 * die als Legacy-Templates keine Dependency Injection haben. Das Paket
 * "abschluss" entfernt die Brücken und mit ihnen "public: true".
 */
#[Autoconfigure(public: true)]
final class SocialViewFactory
{
    public function __construct(
        private readonly FollowerService $followerService,
        private readonly PostingService $postingService,
        private readonly EventRepository $eventRepository,
        private readonly FigureFactory $figureFactory,
    ) {
    }

    /**
     * Daten aller Social-Partials eines Profils.
     *
     * Schlüssel (Vertrag der Partials):
     *  - profile (Zeile), profile_type ("artists"), profile_table ("tl_artists"),
     *    profile_image (src, alt; Größe avatar), member_id, is_owner,
     *  - events, sekevents: has_any (es gibt verknüpfte Veranstaltungen),
     *    has_past (es gibt vergangene, auch unsichtbare), upcoming, past (sichtbare Veranstaltungen mit "image", Größe list),
     *    visible_count,
     *  - postings (Beiträge des Profils, siehe PostingService),
     *  - followings, followers: sichtbare verknüpfte Profile (key, type, profile,
     *    image); followings_any, followers_any: es gibt Einträge, auch wenn keiner
     *    sichtbar ist (dann wie bisher eine leere Liste plus Hinweistext),
     *  - member_profiles: eigene Profile des Mitglieds (key, type, profile, image,
     *    following = folgt diesem Profil bereits).
     *
     * @param array<string, mixed> $profile Zeile aus tl_artists, tl_hosts oder tl_locations
     *
     * @return array<string, mixed>
     */
    public function createProfileContext(ProfileType $type, array $profile, int $memberId): array
    {
        $profileId = (int) $profile['id'];
        $isOwner = $memberId > 0 && $memberId === (int) $profile['member_id'];

        return [
            'profile' => $profile,
            'profile_type' => $type->value,
            'profile_table' => $type->table(),
            'profile_image' => $this->image($profile['avatar'] ?? null, ImageSize::Avatar),
            'member_id' => $memberId,
            'is_owner' => $isOwner,
            'events' => $this->eventGroups(EventType::Events, $type, $profileId, $memberId),
            'sekevents' => $this->eventGroups(EventType::SekEvents, $type, $profileId, $memberId),
            'postings' => $this->postingService->findForProfile($type, $profileId),
            ...$this->profileLists('followings', $this->followerService->findFollowings($type, $profileId)),
            ...$this->profileLists('followers', $this->followerService->findFollowers($type, $profileId)),
            'member_profiles' => $this->memberProfiles($type, $profileId, $memberId),
        ];
    }

    /**
     * Ergänzt Beiträge eines Feeds um das Bild ihres Profils ("image", Größe avatar).
     *
     * @param list<array<string, mixed>> $postings Ergebnis von PostingService
     *
     * @return list<array<string, mixed>>
     */
    public function createFeedItems(array $postings): array
    {
        return array_map(
            fn (array $posting): array => [...$posting, 'image' => $this->image($posting['profile']['avatar'] ?? null, ImageSize::Avatar)],
            $postings,
        );
    }

    /**
     * Veranstaltungen eines Profils, aufgeteilt wie bisher in mod_events_list.html5
     * bzw. mod_sekevents_list.html5: kommende (nächster Termin in der Zukunft) und
     * vergangene (kein künftiger Termin). Gesperrte Veranstaltungen sieht nur ihr
     * Mitglied. has_past gilt wie bisher auch, wenn keine vergangene Veranstaltung
     * sichtbar ist (die Überschrift "Vergangene Veranstaltungen" steht dann allein).
     *
     * @return array{has_any: bool, has_past: bool, upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>, visible_count: int}
     */
    private function eventGroups(EventType $eventType, ProfileType $profileType, int $profileId, int $memberId): array
    {
        $events = $this->eventRepository->findByProfile($eventType, $profileType->value, $profileId);
        $upcoming = [];
        $past = [];

        $hasPast = false;

        foreach ($events as $event) {
            $isPast = false === $event['nextdate'] || (int) $event['nextdate'] < 1;
            $hasPast = $hasPast || $isPast;

            $visible = '1' !== (string) $event['disable'] || ($memberId > 0 && $memberId === (int) $event['member_id']);

            if (!$visible) {
                continue;
            }

            $event['image'] = $this->image($event['avatar'] ?? null, ImageSize::List);

            if ($isPast) {
                $past[] = $event;
            } else {
                $upcoming[] = $event;
            }
        }

        return [
            'has_any' => [] !== $events,
            'has_past' => $hasPast,
            'upcoming' => $upcoming,
            'past' => $past,
            'visible_count' => \count($upcoming) + \count($past),
        ];
    }

    /**
     * @param list<array{key: string, type: ProfileType|null, profile: array<string, mixed>|null}> $entries
     *
     * @return array<string, mixed>
     */
    private function profileLists(string $name, array $entries): array
    {
        return [
            $name => $this->visibleProfiles($entries),
            $name.'_any' => [] !== $entries,
        ];
    }

    /**
     * Nur vorhandene, nicht gesperrte Profile (wie bisher in tabs-followers.html5).
     *
     * @param list<array{key: string, type: ProfileType|null, profile: array<string, mixed>|null}> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function visibleProfiles(array $entries): array
    {
        $visible = [];

        foreach ($entries as $entry) {
            if (null === $entry['profile'] || '1' === (string) $entry['profile']['disable']) {
                continue;
            }

            $entry['image'] = $this->image($entry['profile']['avatar'] ?? null, ImageSize::Avatar);
            $visible[] = $entry;
        }

        return $visible;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memberProfiles(ProfileType $type, int $profileId, int $memberId): array
    {
        if ($memberId < 1) {
            return [];
        }

        $following = $this->followerService->findMemberFollowerKeys($type, $profileId, $memberId);
        $result = [];

        foreach ($this->followerService->findMemberProfiles($memberId) as $table => $profiles) {
            foreach ($profiles as $profile) {
                $key = $table.'-'.$profile['id'];

                $result[] = [
                    'key' => $key,
                    'type' => ProfileType::tryFromTypeOrTable($table),
                    'profile' => $profile,
                    'image' => $this->image($profile['avatar'] ?? null, ImageSize::Avatar),
                    'following' => \in_array($key, $following, true),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{src: string, alt: string}
     */
    private function image(FilesModel|string|null $source, ImageSize $size): array
    {
        return [
            'src' => (string) $this->figureFactory->getImageUrl($source, $size),
            'alt' => (string) $this->figureFactory->getMetadata($source)?->getAlt(),
        ];
    }
}
