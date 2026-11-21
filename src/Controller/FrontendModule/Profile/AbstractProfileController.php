<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;

/**
 * Gemeinsame Wurzel der Profilmodule (Künstler*innen, Veranstalter*innen,
 * Locations): Die drei Profilarten unterscheiden sich in diesen Modulen nur durch
 * die ProfileType, die die konkreten Controller nennen. Typschlüssel
 * (tl_module.type), Kategorie und Template stehen im Attribut
 * #[AsFrontendModule] der konkreten Klasse.
 *
 * Die Wildcard im Backend erzeugt AbstractFrontendModuleController aus den
 * FMD-Texten der Sprachdatei modules.php.
 */
abstract class AbstractProfileController extends AbstractFrontendModuleController
{
    public function __construct(
        protected readonly FrontendMemberProvider $memberProvider,
        protected readonly ProfileRepository $profiles,
        protected readonly ProfileViewFactory $views,
    ) {
    }

    /**
     * Profilart, für die das Modul läuft.
     */
    abstract protected function getProfileType(): ProfileType;
}
