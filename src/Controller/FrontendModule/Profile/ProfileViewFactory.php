<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Image\FigureFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Image\ImageSize;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use SeKultur\ContaoKulturnetzBundle\Service\Search\AvatarUrlResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TemplateTextEncoder;
use Symfony\Component\Uid\Uuid;

/**
 * Bereitet Profilzeilen für die Templates der Profilmodule auf (Bilder,
 * Bildbeschreibungen, Kennzeichen der Listeneinträge).
 *
 * Liegt neben den Controllern, weil nur die Profilmodule diese Ansichten
 * brauchen; die Daten selbst kommen aus den Services (ProfileRepository,
 * FigureFactory). Bildangaben (Alternativtext, Titel, Copyright) werden im
 * Contao-Eingabeformat mit kodierten Insert-Tag-Klammern geliefert
 * (TemplateTextEncoder); das Template maskiert sie mit dem Contao-Escaper.
 */
final class ProfileViewFactory
{
    /**
     * Ein Profil gilt eine Woche lang als neu.
     */
    private const NEW_PROFILE_SECONDS = 7 * 86400;

    public function __construct(
        private readonly FigureFactory $figureFactory,
        private readonly AvatarUrlResolver $avatarUrlResolver,
        private readonly TemplateTextEncoder $templateText,
        private readonly KulturnetzConfig $config,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Titelbild der Detailansicht: skaliertes Bild (700 x 700), Ziel der Lightbox
     * (Breite 1400) und die Bildangaben aus der Dateiverwaltung. Ohne Titelbild
     * erscheint das Ersatzbild mit leeren Bildangaben.
     *
     * @param array<string, mixed> $profile
     *
     * @return array{src: string, lightbox_src: string, alt: string, title: string, caption: string}
     */
    public function createDetailImage(array $profile): array
    {
        $source = $this->avatarSource($profile);
        $metadata = $this->figureFactory->getMetadata($source);

        return [
            'src' => (string) $this->figureFactory->getImageUrl($source, ImageSize::Detail),
            'lightbox_src' => (string) $this->figureFactory->getImageUrl($source, ImageSize::Lightbox),
            'alt' => $this->templateText->encode($metadata?->getAlt()),
            'title' => $this->templateText->encode($metadata?->getTitle()),
            'caption' => $this->templateText->encode($metadata?->getCaption()),
        ];
    }

    /**
     * Einträge einer Profilliste (eigene Profile oder Verzeichnis).
     *
     * Je Eintrag: das Profil (Tabellenzeile), das Bild der Liste (272 x 272, mit
     * eigenem Alternativtext), "is_owner" (Profil des angemeldeten Mitglieds),
     * "is_sample" (Musterprofil der Profilart) und "is_new" (höchstens eine Woche
     * alt).
     *
     * @param list<array<string, mixed>> $profiles
     *
     * @return list<array{profile: array<string, mixed>, image: array{src: string, alt: string}, is_owner: bool, is_sample: bool, is_new: bool}>
     */
    public function createListItems(ProfileType $type, array $profiles, int $memberId): array
    {
        $sampleId = $type->sampleProfileId($this->config);
        $newSince = $this->clock->now()->getTimestamp() - self::NEW_PROFILE_SECONDS;
        $items = [];

        foreach ($profiles as $profile) {
            $source = $this->avatarSource($profile);

            $items[] = [
                'profile' => $profile,
                'image' => [
                    'src' => (string) $this->figureFactory->getImageUrl($source, ImageSize::List),
                    'alt' => $this->templateText->encode($this->figureFactory->getMetadata($source)?->getAlt()),
                ],
                'is_owner' => $memberId > 0 && $memberId === (int) $profile['member_id'],
                'is_sample' => $sampleId === (int) $profile['id'],
                'is_new' => (int) $profile['created'] > $newSince,
            ];
        }

        return $items;
    }

    /**
     * Titelbild im Bearbeiten-Formular: Originaldatei als Vorschau (Pfad ab der
     * Wurzel der Website) und UUID für das versteckte Feld "avatar". Ohne Bild oder
     * bei fehlender Datei erscheint das Ersatzbild und die UUID bleibt leer.
     *
     * @param array<string, mixed> $record Profilzeile, bei leerem Formular leer
     *
     * @return array{src: string, uuid: string}
     */
    public function createFormAvatar(array $record, string $basePath = ''): array
    {
        $avatar = $record['avatar'] ?? null;
        $path = null;

        if (\is_string($avatar) && 16 === \strlen($avatar)) {
            $path = $this->avatarUrlResolver->findPaths([$avatar])[$avatar] ?? null;
        }

        return [
            'src' => $this->avatarUrlResolver->toUrl($path, $basePath),
            'uuid' => null !== $path ? Uuid::fromBinary($avatar)->toRfc4122() : '',
        ];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function avatarSource(array $profile): string|null
    {
        $avatar = $profile['avatar'] ?? null;

        return \is_string($avatar) && '' !== $avatar ? $avatar : null;
    }
}
