<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Formular zum Anlegen eines Künstler:innenprofils (Modultyp ModuleArtistAdd).
 */
#[AsFrontendModule(type: 'ModuleArtistAdd', category: 'eProjekt', template: 'frontend_module/kulturnetz_artist_form')]
final class ArtistAddController extends AbstractProfileFormController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Artists;
    }

    protected function getMode(): ProfileFormMode
    {
        return ProfileFormMode::Add;
    }
}
