<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer\Label;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Image;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Bausteine der Listenbeschriftungen in den KulturNetz-Modulen des Backends.
 *
 * Die Spalten sehen aus wie unter Contao 4.13: Name als Link auf die
 * Detailseite im Frontend, Mitglied mit Schaltfläche "Frontend-Vorschau als
 * Mitglied", E-Mail des Mitglieds, Datumsangaben "TT.MM.JJ - HH:MM" ohne
 * Zeilenumbruch. Das HTML beschränkt sich auf diese Links und Symbole; der
 * Zeilenumbruch der Datumsspalten wird über eine Klasse und das Backend-CSS des
 * Bundles verhindert (statt Inline-Style). Contao 6 erwartet dafür ein
 * RecordLabel mit HTML-Anteil (Restpunkt R2).
 *
 * Werte aus der Datenbank sind im Contao-Format gespeichert (Sonderzeichen als
 * numerische Entities). Sie werden deshalb ohne doppelte Kodierung escaped.
 */
final class RecordLabelBuilder implements ResetInterface
{
    /**
     * @var array<string, int|false>
     */
    private array $pageIds = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ListMemberDirectory $members,
    ) {
    }

    /**
     * Name des Datensatzes als Link auf die Detailseite im Frontend (neues Fenster).
     * Ohne Detailseite (Seite fehlt) nur der Name.
     *
     * @param string $type artists, hosts, locations, events oder sekevents
     */
    public function frontendLink(string $type, string $alias, string $name): string
    {
        $url = '' === $alias ? null : $this->pageUrlResolver->getDetailUrl($type, $alias);

        if (null === $url) {
            return $this->escape($name);
        }

        return \sprintf('<a href="%s" target="_blank" title="Im Frontend ansehen">%s</a>', $this->escape($url), $this->escape($name));
    }

    /**
     * Symbol "Bearbeitung im Frontend gesperrt" hinter dem Namen (Veranstaltungen mit readonly).
     */
    public function readonlyIcon(): string
    {
        return ' '.$this->image()->getHtml('lock-locked.svg', '', 'title="Bearbeitung im Frontend gesperrt"');
    }

    /**
     * Vor- und Nachname des Mitglieds mit Schaltfläche "Frontend-Vorschau als Mitglied".
     *
     * @param string|null $previewPageKey Seitenschlüssel der Vorschau (z. B. "own_events"),
     *                                    null für die Startseite
     */
    public function memberWithPreview(string $table, int $memberId, string|null $previewPageKey = null): string
    {
        $member = $this->members->find($table, $memberId);

        if (null === $member) {
            return '';
        }

        $parameters = [];

        if (null !== $previewPageKey && null !== ($pageId = $this->getPageId($previewPageKey))) {
            $parameters['page'] = $pageId;
        }

        $parameters['user'] = $member['email'];

        $url = $this->urlGenerator->generate('contao_backend_preview', $parameters);
        $icon = $this->image()->getHtml('su.svg', \sprintf('Frontend-Vorschau als Mitglied ID %d', $member['id']));

        return \sprintf(
            '%s %s <a href="%s" target="_blank">%s</a>',
            $this->escape($member['firstname']),
            $this->escape($member['lastname']),
            $this->escape($url),
            $icon,
        );
    }

    /**
     * E-Mail-Adresse des zugeordneten Mitglieds (nicht die des Datensatzes, wie bisher).
     */
    public function memberEmail(string $table, int $memberId): string
    {
        return $this->escape($this->members->find($table, $memberId)['email'] ?? '');
    }

    /**
     * Datum und Uhrzeit "TT.MM.JJ - HH:MM" ohne Zeilenumbruch.
     */
    public function date(int $timestamp): string
    {
        return $this->nowrap(date('d.m.y - H:i', $timestamp));
    }

    /**
     * HTML-Ausschnitt ohne Zeilenumbruch (Klasse aus dem Backend-CSS des Bundles).
     */
    public function nowrap(string $html): string
    {
        return '<span class="kulturnetz-nowrap">'.$html.'</span>';
    }

    public function reset(): void
    {
        $this->pageIds = [];
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    private function getPageId(string $pageKey): int|null
    {
        if (!isset($this->pageIds[$pageKey])) {
            $page = $this->pageUrlResolver->findPage($pageKey);
            $this->pageIds[$pageKey] = null === $page ? false : (int) $page->id;
        }

        return $this->pageIds[$pageKey] ?: null;
    }

    /**
     * @return Image
     */
    private function image(): object
    {
        $this->framework->initialize();

        return $this->framework->getAdapter(Image::class);
    }
}
