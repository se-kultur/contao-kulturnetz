<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Formular zum Bearbeiten eines Künstler:innenprofils (Modultyp ModuleArtistEdit).
 */
#[AsFrontendModule(type: 'ModuleArtistEdit', category: 'eProjekt', template: 'frontend_module/kulturnetz_artist_form')]
final class ArtistEditController extends AbstractProfileFormController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Artists;
    }

    protected function getMode(): ProfileFormMode
    {
        return ProfileFormMode::Edit;
    }
}
