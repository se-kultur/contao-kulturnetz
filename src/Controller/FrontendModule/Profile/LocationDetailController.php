<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Detailansicht des Locationprofils (Modultyp ModuleLocation).
 */
#[AsFrontendModule(type: 'ModuleLocation', category: 'eProjekt', template: 'frontend_module/kulturnetz_location_detail')]
final class LocationDetailController extends AbstractProfileDetailController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Locations;
    }
}
