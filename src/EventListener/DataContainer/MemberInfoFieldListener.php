<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Feld "Informationen zum Mitglied" in den Masken der Profile und
 * Veranstaltungen (bisher memberinfos() in den fünf DCA-Dateien).
 *
 * Zeigt das zugeordnete Mitglied als Tabelle wie in der Mitgliederliste
 * (Vorname, Nachname, Benutzername, Hinzugefügt am) mit Links zum Bearbeiten,
 * zu den Details und zur Frontend-Vorschau. Darunter zählt ein Skript die
 * Zeichen der Kurzbeschreibung ("n / 250"); das Skript ist eine Datei des
 * Bundles (public/js/member-infos.js) statt eines Inline-Skripts.
 */
#[AsCallback(table: 'tl_artists', target: 'fields.memberinfos.input_field')]
#[AsCallback(table: 'tl_hosts', target: 'fields.memberinfos.input_field')]
#[AsCallback(table: 'tl_locations', target: 'fields.memberinfos.input_field')]
#[AsCallback(table: 'tl_events', target: 'fields.memberinfos.input_field')]
#[AsCallback(table: 'tl_sekevents', target: 'fields.memberinfos.input_field')]
final class MemberInfoFieldListener
{
    /**
     * Richtwert für die Länge der Kurzbeschreibung (Anzeige "n / 250").
     */
    private const SHORT_DESCRIPTION_LIMIT = 250;

    public function __construct(
        private readonly Connection $connection,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(DataContainer $dc): string
    {
        $record = $dc->getCurrentRecord() ?? [];
        $member = $this->findMember((int) ($record['member_id'] ?? 0));

        // Bei "Mehrere bearbeiten" tragen alle Eingaben das Suffix "_<id>".
        $suffix = substr((string) $dc->inputName, \strlen((string) $dc->field));

        return $this->twig->render('@Contao/backend/kulturnetz/member_infos.html.twig', [
            'member' => $member,
            'edit_url' => null === $member ? null : $this->urlGenerator->generate('contao_backend', ['do' => 'member', 'act' => 'edit', 'id' => $member['id']]),
            'show_url' => null === $member ? null : $this->urlGenerator->generate('contao_backend', ['do' => 'member', 'act' => 'show', 'id' => $member['id']]),
            'preview_url' => null === $member ? null : $this->urlGenerator->generate('contao_backend_preview', ['user' => $member['email']]),
            'counter_field' => 'ctrl_kurzbeschreibung'.$suffix,
            'counter_limit' => self::SHORT_DESCRIPTION_LIMIT,
        ]);
    }

    /**
     * @return array{id: int, firstname: string, lastname: string, email: string, dateAdded: int}|null
     */
    private function findMember(int $memberId): array|null
    {
        if ($memberId < 1) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, firstname, lastname, email, dateAdded FROM tl_member WHERE id = ?',
            [$memberId],
            [ParameterType::INTEGER],
        );

        if (false === $row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'firstname' => (string) $row['firstname'],
            'lastname' => (string) $row['lastname'],
            'email' => (string) $row['email'],
            'dateAdded' => (int) $row['dateAdded'],
        ];
    }
}
