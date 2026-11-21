<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * KulturKalender mit Suche (Modultyp "ModuleEventsListAll").
 */
#[AsFrontendModule(type: 'ModuleEventsListAll', category: 'eProjekt', template: 'frontend_module/kulturnetz_event_directory')]
final class EventDirectoryController extends AbstractEventDirectoryController
{
    protected function getEventType(): EventType
    {
        return EventType::Events;
    }
}
