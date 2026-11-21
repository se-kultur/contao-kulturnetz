<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Gallery\GalleryViewBuilder;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\AutoItemResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detailansicht einer Veranstaltung (Module "ModuleEvent" und "ModuleSekEvent",
 * bisher Module\ModuleEvent und Module\ModuleSekEvent).
 *
 * Die Veranstaltung kommt per Alias oder ID aus der URL (auto_item). Eine
 * unbekannte Veranstaltung und eine URL ohne Alias ergeben 404. Nicht
 * freigeschaltete Veranstaltungen (disable = 1) sind wie bisher per Direktlink
 * erreichbar, tragen aber "noindex,nofollow" im Meta-Tag robots (HtmlHeadBag der
 * Antwort); damit nehmen weder Suchmaschinen noch die Contao-Suche
 * (SearchIndexListener) sie auf.
 * Besitzer:innen sehen zusätzlich die Aktionen zum Bearbeiten, Hochladen und
 * Verwalten von Fotos sowie die Hinweise "Nicht öffentlich".
 */
abstract class AbstractEventDetailController extends AbstractEventController
{
    public function __construct(
        private readonly EventRepository $repository,
        private readonly AutoItemResolver $autoItemResolver,
        private readonly FrontendMemberProvider $memberProvider,
        private readonly GalleryViewBuilder $galleryViewBuilder,
        private readonly EventViewFactory $viewFactory,
        private readonly KulturnetzConfig $config,
    ) {
    }

    final protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getEventType();
        $alias = $this->autoItemResolver->resolve($request);
        $event = null !== $alias ? $this->repository->findByAliasOrId($type, $alias) : null;

        if (null === $event) {
            throw new PageNotFoundException(\sprintf('%s nicht gefunden: %s', EventType::Events === $type ? 'Veranstaltung' : 'SE-Kultur-Event', $alias));
        }

        if ((int) ($event['disable'] ?? 0) > 0) {
            $this->getHtmlHeadBag()?->setMetaRobots('noindex,nofollow');
        }

        $memberId = $this->memberProvider->getMemberId();
        $isOwner = $memberId > 0 && $memberId === (int) $event['member_id'];

        $template->set('event', $event);
        $template->set('event_type', $type->value);
        $template->set('member_id', $memberId);
        $template->set('is_owner', $isOwner);
        $template->set('title_image', $this->viewFactory->titleImage($event));
        $template->set('dates', $this->viewFactory->dates($event));
        $template->set('prices', $this->viewFactory->prices($event));
        $template->set('linked', $this->viewFactory->linkedProfiles($event));
        $template->set('location_contact', $this->viewFactory->contact($event, 'location'));
        $template->set('host_contact', $this->viewFactory->contact($event, 'host'));
        $template->set('gallery', $this->galleryViewBuilder->buildForRecord($type->value, (int) $event['id'], $isOwner));
        $template->set('kulturrouten_url', $this->config->getKulturroutenUrl());

        return $template->getResponse();
    }
}
