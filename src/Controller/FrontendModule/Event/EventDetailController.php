<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Detailansicht einer Veranstaltung des KulturKalenders (Modultyp "ModuleEvent").
 */
#[AsFrontendModule(type: 'ModuleEvent', category: 'eProjekt', template: 'frontend_module/kulturnetz_event_detail')]
final class EventDetailController extends AbstractEventDetailController
{
    protected function getEventType(): EventType
    {
        return EventType::Events;
    }
}
