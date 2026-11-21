<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Formular zum Anlegen einer Veranstaltung (Modultyp "ModuleEventAdd").
 */
#[AsFrontendModule(type: 'ModuleEventAdd', category: 'eProjekt', template: 'frontend_module/kulturnetz_event_form')]
final class EventAddController extends AbstractEventFormController
{
    protected function getEventType(): EventType
    {
        return EventType::Events;
    }

    protected function getMode(): string
    {
        return self::MODE_ADD;
    }
}
