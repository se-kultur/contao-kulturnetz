<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Liste der eigenen Veranstalter:innenprofile (Modultyp ModuleHostsList).
 */
#[AsFrontendModule(type: 'ModuleHostsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_profile_list')]
final class HostOwnListController extends AbstractProfileOwnListController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Hosts;
    }
}
