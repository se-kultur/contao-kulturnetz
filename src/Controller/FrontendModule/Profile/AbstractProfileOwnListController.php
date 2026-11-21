<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liste der eigenen Profile einer Profilart (Modultypen ModuleArtistsList,
 * ModuleHostsList, ModuleLocationsList): alle Profile des angemeldeten
 * Mitglieds, auch gesperrte. Ohne Profil bleibt die Liste leer.
 *
 * Template-Variablen (Template frontend_module/kulturnetz_profile_list):
 *  profile_type, items (siehe ProfileViewFactory::createListItems()),
 *  filter (false, kein Suchformular), search (leer), categories (null).
 */
abstract class AbstractProfileOwnListController extends AbstractProfileController
{
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getProfileType();
        $memberId = $this->memberProvider->getMemberId();

        $template->set('profile_type', $type->value);
        $template->set('items', $this->views->createListItems($type, $this->profiles->findByMemberId($type, $memberId), $memberId));
        $template->set('filter', false);
        $template->set('search', []);
        $template->set('categories', null);

        return $template->getResponse();
    }
}
