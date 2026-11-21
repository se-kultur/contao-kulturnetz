<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Typisierter Zugriff auf die semantische Bundle-Konfiguration "kulturnetz".
 *
 * Die Werte kommen aus ContaoKulturnetzBundle::configure() (Standardwerte =
 * bisher fest im Code und in den Templates stehende Werte) und können in der
 * Projektkonfiguration (config/config.yaml, Schlüssel "kulturnetz") überschrieben
 * werden. Der Container übergibt den bereits validierten Konfigurationsbaum über
 * den Parameter "kulturnetz.config"; Umgebungsvariablen (Mail-Empfänger) sind
 * zu diesem Zeitpunkt aufgelöst.
 *
 * Profil- und Veranstaltungstypen heißen wie die Tabellen ohne Präfix "tl_":
 * artists, hosts, locations, events, sekevents.
 */
final class KulturnetzConfig
{
    /**
     * Alle Typen mit eigenen Detail-, Bearbeiten-, Anlegen- und Listenseiten.
     */
    public const TYPES = ['artists', 'hosts', 'locations', 'events', 'sekevents'];

    /**
     * Typen mit Profil (Musterprofil, Profil-Verzeichnis).
     */
    public const PROFILE_TYPES = ['artists', 'hosts', 'locations'];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        #[Autowire(param: 'kulturnetz.config')]
        private readonly array $config,
    ) {
    }

    /**
     * Alias der Detailseite eines Typs (z. B. "detailansicht-kuenstlerprofil").
     */
    public function getDetailPageAlias(string $type): string
    {
        return $this->getTypePage($type, 'detail');
    }

    /**
     * Alias der Bearbeiten-Seite eines Typs (z. B. "kuenstlerprofil-bearbeiten").
     */
    public function getEditPageAlias(string $type): string
    {
        return $this->getTypePage($type, 'edit');
    }

    /**
     * Alias der Anlegen-Seite eines Typs (z. B. "kuenstlerprofil-anlegen").
     */
    public function getAddPageAlias(string $type): string
    {
        return $this->getTypePage($type, 'add');
    }

    /**
     * Alias der öffentlichen Listenseite eines Typs (z. B. "kuenstler-innen").
     */
    public function getListPageAlias(string $type): string
    {
        return $this->getTypePage($type, 'list');
    }

    /**
     * Alias der Seite "Eigene Profile" im Mitgliederbereich.
     */
    public function getOwnProfilesPageAlias(): string
    {
        return $this->config['pages']['own_profiles'];
    }

    /**
     * Alias der Seite mit den eigenen Veranstaltungen (bisher Seiten-ID 40,
     * u. a. Ziel der Backend-Vorschau "als Mitglied").
     */
    public function getOwnEventsPageAlias(): string
    {
        return $this->config['pages']['own_events'];
    }

    /**
     * Alias der Seite mit den Teilnahmebedingungen.
     */
    public function getTermsPageAlias(): string
    {
        return $this->config['pages']['terms'];
    }

    /**
     * Alias der Anmeldeseite der SE-KulturTage. Ihre Veröffentlichung und ihr
     * Zeitraum steuern die Sichtbarkeit der Anmeldelinks.
     */
    public function getSignupPageAlias(): string
    {
        return $this->config['pages']['signup'];
    }

    /**
     * Alias der Übersichtsseite, auf der die Verzeichnisse und der
     * Beitrags-Feed gekürzt erscheinen (bisher Seiten-ID 7).
     */
    public function getTeaserPageAlias(): string
    {
        return $this->config['teaser']['page'];
    }

    /**
     * Anzahl der Profile je Verzeichnis auf der Übersichtsseite.
     */
    public function getTeaserProfileLimit(): int
    {
        return $this->config['teaser']['profile_limit'];
    }

    /**
     * Anzahl der Beiträge im öffentlichen Feed auf der Übersichtsseite.
     */
    public function getTeaserPostingLimit(): int
    {
        return $this->config['teaser']['posting_limit'];
    }

    /**
     * Höchstzahl der Profile in einem Profil-Verzeichnis außerhalb der
     * Übersichtsseite.
     */
    public function getProfileDirectoryLimit(): int
    {
        return $this->config['limits']['profile_directory'];
    }

    /**
     * Höchstzahl der Beiträge im öffentlichen Feed außerhalb der
     * Übersichtsseite.
     */
    public function getPostingFeedLimit(): int
    {
        return $this->config['limits']['posting_feed'];
    }

    /**
     * Höchstzahl der Beiträge im Feed des angemeldeten Mitglieds.
     */
    public function getMemberFeedLimit(): int
    {
        return $this->config['limits']['member_feed'];
    }

    /**
     * Höchstzahl der Beiträge in der Detailansicht eines Profils.
     */
    public function getProfilePostingLimit(): int
    {
        return $this->config['limits']['profile_postings'];
    }

    /**
     * Höchstzahl der Veranstaltungen im Veranstaltungsverzeichnis; null heißt
     * unbegrenzt.
     */
    public function getEventDirectoryLimit(): int|null
    {
        return $this->config['limits']['event_directory'] ?: null;
    }

    /**
     * Höchstzahl der Veranstaltungen im Verzeichnis der SE-KulturTage; null
     * heißt unbegrenzt.
     */
    public function getSekEventDirectoryLimit(): int|null
    {
        return $this->config['limits']['sekevent_directory'] ?: null;
    }

    /**
     * ID des Musterprofils eines Profiltyps (wird in den Verzeichnissen zuerst
     * und mit dem Hinweis "Musterprofil" angezeigt).
     */
    public function getSampleProfileId(string $type): int
    {
        $this->assertType($type, self::PROFILE_TYPES);

        return $this->config['sample_profiles'][$type];
    }

    /**
     * Zuordnung Veranstaltungs-ID der SE-KulturTage => Nummer der KulturRoute.
     *
     * @return array<int, int>
     */
    public function getKulturrouten(): array
    {
        return $this->config['kulturrouten']['events'];
    }

    /**
     * Nummer der KulturRoute einer SE-KulturTage-Veranstaltung oder null.
     */
    public function getKulturrouteForEvent(int $eventId): int|null
    {
        return $this->config['kulturrouten']['events'][$eventId] ?? null;
    }

    /**
     * Ziel des KulturRouten-Hinweises; der Anker "#route<Nummer>" wird angehängt.
     */
    public function getKulturroutenUrl(): string
    {
        return $this->config['kulturrouten']['url'];
    }

    /**
     * Name des Symfony-Asset-Pakets mit den Theme-Dateien der Website.
     */
    public function getAssetPackage(): string
    {
        return $this->config['asset_package'];
    }

    public function getMailSenderAddress(): string
    {
        return $this->config['mail']['sender_address'];
    }

    public function getMailSenderName(): string
    {
        return $this->config['mail']['sender_name'];
    }

    /**
     * Empfänger der Hinweismails an die Redaktion (neue oder geänderte Profile
     * und Veranstaltungen).
     */
    public function getEditorialRecipientAddress(): string
    {
        return trim((string) $this->config['mail']['editorial_recipient']);
    }

    public function getEditorialRecipientName(): string
    {
        return $this->config['mail']['editorial_recipient_name'];
    }

    /**
     * Blindkopie-Empfänger der Redaktionsmails; leer gesetzt bedeutet keine
     * Blindkopie.
     *
     * @return list<string>
     */
    public function getEditorialBccAddresses(): array
    {
        $value = trim((string) $this->config['mail']['editorial_bcc']);

        if ('' === $value) {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $address): bool => '' !== $address));
    }

    /**
     * Größte zulässige Uploaddatei in Byte; null heißt keine eigene Grenze
     * (dann gelten upload_max_filesize und post_max_size von PHP).
     */
    public function getUploadMaxFileSize(): int|null
    {
        return $this->config['upload']['max_file_size'] ?: null;
    }

    /**
     * Zulässige MIME-Typen für Bild-Uploads der Mitglieder.
     *
     * @return list<string>
     */
    public function getUploadMimeTypes(): array
    {
        return $this->config['upload']['mime_types'];
    }

    private function getTypePage(string $type, string $purpose): string
    {
        $this->assertType($type, self::TYPES);

        return $this->config['pages']['types'][$type][$purpose];
    }

    /**
     * @param list<string> $allowed
     */
    private function assertType(string $type, array $allowed): void
    {
        if (!\in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s", erlaubt sind: %s.', $type, implode(', ', $allowed)));
        }
    }
}
