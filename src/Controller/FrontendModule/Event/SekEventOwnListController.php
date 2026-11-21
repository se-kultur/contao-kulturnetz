<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Eigene Events der SE-KulturTage des Mitglieds (Modultyp "ModuleSekEventsList").
 */
#[AsFrontendModule(type: 'ModuleSekEventsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_event_list')]
final class SekEventOwnListController extends AbstractEventOwnListController
{
    protected function getEventType(): EventType
    {
        return EventType::SekEvents;
    }
}
