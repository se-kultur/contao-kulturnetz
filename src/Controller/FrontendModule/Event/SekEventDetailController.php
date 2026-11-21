<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Detailansicht eines Events der SE-KulturTage (Modultyp "ModuleSekEvent").
 */
#[AsFrontendModule(type: 'ModuleSekEvent', category: 'eProjekt', template: 'frontend_module/kulturnetz_sekevent_detail')]
final class SekEventDetailController extends AbstractEventDetailController
{
    protected function getEventType(): EventType
    {
        return EventType::SekEvents;
    }
}
