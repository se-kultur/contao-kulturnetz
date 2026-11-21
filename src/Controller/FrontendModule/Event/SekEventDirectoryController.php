<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Programm der SE-KulturTage mit Suche (Modultyp "ModuleSekEventsListAll").
 */
#[AsFrontendModule(type: 'ModuleSekEventsListAll', category: 'eProjekt', template: 'frontend_module/kulturnetz_sekevent_directory')]
final class SekEventDirectoryController extends AbstractEventDirectoryController
{
    protected function getEventType(): EventType
    {
        return EventType::SekEvents;
    }
}
