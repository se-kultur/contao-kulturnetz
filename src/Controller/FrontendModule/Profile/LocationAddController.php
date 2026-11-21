<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Formular zum Anlegen eines Locationprofils (Modultyp ModuleLocationAdd).
 */
#[AsFrontendModule(type: 'ModuleLocationAdd', category: 'eProjekt', template: 'frontend_module/kulturnetz_location_form')]
final class LocationAddController extends AbstractProfileFormController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Locations;
    }

    protected function getMode(): ProfileFormMode
    {
        return ProfileFormMode::Add;
    }
}
