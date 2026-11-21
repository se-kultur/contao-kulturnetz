<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Detailansicht des Künstler:innenprofils (Modultyp ModuleArtist).
 */
#[AsFrontendModule(type: 'ModuleArtist', category: 'eProjekt', template: 'frontend_module/kulturnetz_artist_detail')]
final class ArtistDetailController extends AbstractProfileDetailController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Artists;
    }
}
