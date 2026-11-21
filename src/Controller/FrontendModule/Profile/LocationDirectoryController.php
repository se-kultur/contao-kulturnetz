<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Verzeichnis aller Locationprofile (Modultyp ModuleLocationsListAll).
 */
#[AsFrontendModule(type: 'ModuleLocationsListAll', category: 'eProjekt', template: 'frontend_module/kulturnetz_profile_list')]
final class LocationDirectoryController extends AbstractProfileDirectoryController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Locations;
    }
}
