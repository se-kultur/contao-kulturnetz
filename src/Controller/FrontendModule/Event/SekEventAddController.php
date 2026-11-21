<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Formular zum Anlegen eines Events der SE-KulturTage (Modultyp "ModuleSekEventAdd").
 */
#[AsFrontendModule(type: 'ModuleSekEventAdd', category: 'eProjekt', template: 'frontend_module/kulturnetz_sekevent_form')]
final class SekEventAddController extends AbstractEventFormController
{
    protected function getEventType(): EventType
    {
        return EventType::SekEvents;
    }

    protected function getMode(): string
    {
        return self::MODE_ADD;
    }
}
