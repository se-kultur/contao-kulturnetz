<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Formular zum Bearbeiten eines Veranstalter:innenprofils (Modultyp ModuleHostEdit).
 */
#[AsFrontendModule(type: 'ModuleHostEdit', category: 'eProjekt', template: 'frontend_module/kulturnetz_host_form')]
final class HostEditController extends AbstractProfileFormController
{
    protected function getProfileType(): ProfileType
    {
        return ProfileType::Hosts;
    }

    protected function getMode(): ProfileFormMode
    {
        return ProfileFormMode::Edit;
    }
}
