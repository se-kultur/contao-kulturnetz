<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Liste der eigenen Künstler:innenprofile (Modultyp ModuleArtistsList).
 */
#[AsFrontendModule(type: 'ModuleArtistsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_profile_list')]
final class ArtistOwnListController extends AbstractProfileOwnListController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Artists;
    }
}
