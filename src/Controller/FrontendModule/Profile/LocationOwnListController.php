<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Liste der eigenen Locationprofile (Modultyp ModuleLocationsList).
 */
#[AsFrontendModule(type: 'ModuleLocationsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_profile_list')]
final class LocationOwnListController extends AbstractProfileOwnListController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Locations;
    }
}
