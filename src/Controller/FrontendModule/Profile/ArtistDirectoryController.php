<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Verzeichnis aller Künstler:innenprofile (Modultyp ModuleArtistsListAll).
 */
#[AsFrontendModule(type: 'ModuleArtistsListAll', category: 'eProjekt', template: 'frontend_module/kulturnetz_profile_list')]
final class ArtistDirectoryController extends AbstractProfileDirectoryController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Artists;
    }
}
