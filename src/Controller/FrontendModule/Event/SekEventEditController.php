<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Formular zum Bearbeiten eines Events der SE-KulturTage (Modultyp "ModuleSekEventEdit").
 */
#[AsFrontendModule(type: 'ModuleSekEventEdit', category: 'eProjekt', template: 'frontend_module/kulturnetz_sekevent_form')]
final class SekEventEditController extends AbstractEventFormController
{
    protected function getEventType(): EventType
    {
        return EventType::SekEvents;
    }

    protected function getMode(): string
    {
        return self::MODE_EDIT;
    }
}
