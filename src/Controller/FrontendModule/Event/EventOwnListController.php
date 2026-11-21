<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Eigene Veranstaltungen des Mitglieds (Modultyp "ModuleEventsList").
 */
#[AsFrontendModule(type: 'ModuleEventsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_event_list')]
final class EventOwnListController extends AbstractEventOwnListController
{
    protected function getEventType(): EventType
    {
        return EventType::Events;
    }
}
