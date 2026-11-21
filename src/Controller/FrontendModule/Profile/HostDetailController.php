<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Detailansicht des Veranstalter:innenprofils (Modultyp ModuleHost).
 */
#[AsFrontendModule(type: 'ModuleHost', category: 'eProjekt', template: 'frontend_module/kulturnetz_host_detail')]
final class HostDetailController extends AbstractProfileDetailController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Hosts;
    }
}
