<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventFacets;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventSearch;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Event\ProgramSorter;
use SeKultur\ContaoKulturnetzBundle\Service\Event\Value\EventCriteria;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Verzeichnis aller kommenden Veranstaltungen mit Suchformular (Module
 * "ModuleEventsListAll" und "ModuleSekEventsListAll", bisher
 * Module\ModuleEventsListAll und Module\ModuleSekEventsListAll): der KulturKalender
 * und das Programm der SE-KulturTage.
 *
 * Gesucht und gefiltert wird über Service\Event\EventSearch (Datum, Kategorie,
 * Format, Text; Termine vor heute 0:00 Uhr entfallen), die Orts-Badges und die
 * Grenzen des Datepickers liefert Service\Event\EventFacets. Das Programm der
 * SE-KulturTage wird innerhalb der Tage über Service\Event\ProgramSorter geordnet.
 */
abstract class AbstractEventDirectoryController extends AbstractEventController
{
    public function __construct(
        private readonly EventSearch $search,
        private readonly EventFacets $facets,
        private readonly ProgramSorter $programSorter,
        private readonly FrontendMemberProvider $memberProvider,
        private readonly EventViewFactory $viewFactory,
        private readonly ContentUrlGenerator $urlGenerator,
        private readonly KulturnetzConfig $config,
    ) {
    }

    final protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getEventType();
        $criteria = EventCriteria::fromQuery($type, $request->query->all());

        $events = $this->search->search($type, $criteria);

        if ($type->datesStoredWithoutYear()) {
            $events = $this->programSorter->sortDays($events);
        }

        $facets = $this->facets->build($type, $criteria, $events);
        $search = $criteria->toArray();
        $resetUrl = $this->getCurrentUrl($request);

        $template->set('event_type', $type->value);
        $template->set('member_id', $this->memberProvider->getMemberId());
        // Die Werte stehen wieder im Formular: Insert-Tags aus der Anfrage kodieren, Contao ersetzt sie sonst.
        $template->set('search', array_map($this->viewFactory->encodeInsertTags(...), $search));
        $template->set('has_active_filter', $this->hasActiveFilter($search));
        $template->set('reset_url', $resetUrl);
        $template->set('location_badges', $this->createLocationBadges($facets->locationOrte, $search, $resetUrl));
        $template->set('date_range', $facets->dateRange);
        $template->set('days', $this->viewFactory->directoryDays($type, $events));
        $template->set('event_count', \count($events));
        $template->set('formats', EventViewFactory::SEKEVENT_FORMATS);
        $template->set('kulturrouten_url', $this->config->getKulturroutenUrl());

        return $template->getResponse();
    }

    /**
     * Ein Zurücksetzen-Link erscheint nur bei tatsächlich gesetzten Filtern; das
     * Suchformular sendet per GET immer alle Felder mit, deshalb zählt der Wert und
     * nicht das Vorhandensein ("Alle" bei Kategorie und Format heißt kein Filter).
     *
     * @param array<string, string> $search
     */
    private function hasActiveFilter(array $search): bool
    {
        return !empty($search['text'])
            || !empty($search['datum'])
            || (!empty($search['kategorie']) && EventCriteria::ALL !== $search['kategorie'])
            || (!empty($search['format']) && EventCriteria::ALL !== $search['format']);
    }

    /**
     * Orts-Badges: Link auf dieselbe Seite mit den gesetzten Filtern, nur der
     * Textfilter wird auf den Ort gesetzt. Übernommen werden ausschließlich die
     * bekannten Filter (EventCriteria), keine fremden Query-Parameter.
     *
     * @param array<string, int>    $locations Ort => Anzahl der Treffer
     * @param array<string, string> $search
     *
     * @return list<array{label: string, url: string}>
     */
    private function createLocationBadges(array $locations, array $search, string $baseUrl): array
    {
        $badges = [];

        foreach (array_keys($locations) as $location) {
            if ('' === trim((string) $location)) {
                continue;
            }

            $badges[] = [
                'label' => (string) $location,
                'url' => $baseUrl.'?'.http_build_query([...$search, 'text' => (string) $location], '', '&'),
            ];
        }

        return $badges;
    }

    /**
     * Pfad der aktuellen Seite ohne Query (Ziel des Zurücksetzens und der Badges).
     */
    private function getCurrentUrl(Request $request): string
    {
        $page = $this->getPageModel();

        if (null === $page) {
            return $request->getPathInfo();
        }

        return $this->urlGenerator->generate($page, [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
