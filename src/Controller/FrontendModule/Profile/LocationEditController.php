<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Formular zum Bearbeiten eines Locationprofils (Modultyp ModuleLocationEdit).
 */
#[AsFrontendModule(type: 'ModuleLocationEdit', category: 'eProjekt', template: 'frontend_module/kulturnetz_location_form')]
final class LocationEditController extends AbstractProfileFormController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Locations;
    }

    protected function getMode(): ProfileFormMode
    {
        return ProfileFormMode::Edit;
    }
}
