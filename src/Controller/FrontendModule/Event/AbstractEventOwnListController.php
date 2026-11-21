<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\SignupWindowChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liste der eigenen Veranstaltungen des angemeldeten Mitglieds (Module
 * "ModuleEventsList" und "ModuleSekEventsList", bisher Module\ModuleEventsList
 * und Module\ModuleSekEventsList): kommende Veranstaltungen, darunter die Gruppe
 * der vergangenen, dazu die Kachel zum Anlegen einer neuen Veranstaltung. Die
 * Kachel der SE-KulturTage erscheint nur, solange die Anmeldung offen ist.
 */
abstract class AbstractEventOwnListController extends AbstractEventController
{
    public function __construct(
        private readonly EventRepository $repository,
        private readonly FrontendMemberProvider $memberProvider,
        private readonly SignupWindowChecker $signupWindowChecker,
        private readonly EventViewFactory $viewFactory,
    ) {
    }

    final protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getEventType();
        $memberId = $this->memberProvider->getMemberId();
        $groups = $this->viewFactory->ownListGroups($this->repository->findByMemberId($type, $memberId));

        $template->set('event_type', $type->value);
        $template->set('member_id', $memberId);
        $template->set('upcoming', $groups['upcoming']);
        $template->set('past', $groups['past']);
        $template->set('add_tile', $memberId > 0 && (EventType::Events === $type || $this->signupWindowChecker->isOpen()));

        return $template->getResponse();
    }
}
