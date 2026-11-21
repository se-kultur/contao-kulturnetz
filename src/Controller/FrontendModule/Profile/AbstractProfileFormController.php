<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\AutoItemResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileCategories;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formular zum Anlegen und Bearbeiten eines Profils (Modultypen ModuleArtistAdd,
 * ModuleArtistEdit, ModuleHostAdd, ModuleHostEdit, ModuleLocationAdd,
 * ModuleLocationEdit).
 *
 * Anlegen zeigt das leere Formular. Bearbeiten füllt es mit dem Profil aus der URL
 * (auto_item), sofern es dem angemeldeten Mitglied gehört; bei einem fremden oder
 * unbekannten Profil bleibt das Formular wie bisher leer, ohne Fehlermeldung
 * (Entscheidung E39). Der Schutz liegt beim Speichern: Der Endpunkt
 * kulturnetz_profil_aendern prüft Mitglied, Besitz und Token selbst.
 *
 * Die Zugriffsregel "nur für angemeldete Mitglieder" liegt wie bisher an der
 * Seite (geschützt), nicht im Modul.
 *
 * Template-Variablen (Template frontend_module/kulturnetz_<art>_form):
 *  - mode ("add" oder "edit"), profile_type ("artists"), profile_label,
 *    help_key ("artist_edit" usw., Schlüssel des Hilfekastens),
 *  - record (Profilzeile oder leer),
 *  - avatar (Vorschau und UUID des Titelbilds, siehe ProfileViewFactory),
 *  - categories (Sparten zur Auswahl, nur Künstler*innen, sonst null).
 */
abstract class AbstractProfileFormController extends AbstractProfileController
{
    public function __construct(
        FrontendMemberProvider $memberProvider,
        ProfileRepository $profiles,
        ProfileViewFactory $views,
        private readonly AutoItemResolver $autoItem,
        private readonly ProfileCategories $categories,
    ) {
        parent::__construct($memberProvider, $profiles, $views);
    }

    /**
     * Anlegen oder Bearbeiten.
     */
    abstract protected function getMode(): ProfileFormMode;

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getProfileType();
        $mode = $this->getMode();
        $record = ProfileFormMode::Edit === $mode ? $this->findOwnProfile($type, $request) : [];

        $template->set('mode', $mode->value);
        $template->set('profile_type', $type->value);
        $template->set('profile_label', $type->label());
        $template->set('help_key', $this->getHelpKey($type, $mode));
        $template->set('record', $record);
        $template->set('avatar', $this->views->createFormAvatar($record, $request->getBasePath()));
        $template->set('categories', $type->hasCategoryFilter() ? $this->categories->all() : null);

        return $template->getResponse();
    }

    /**
     * Das Profil aus der URL, wenn es dem angemeldeten Mitglied gehört, sonst ein
     * leerer Datensatz (leeres Formular).
     *
     * @return array<string, mixed>
     */
    private function findOwnProfile(ProfileType $type, Request $request): array
    {
        $alias = $this->autoItem->resolve($request);
        $memberId = $this->memberProvider->getMemberId();

        if (null === $alias || $memberId < 1) {
            return [];
        }

        $profile = $this->profiles->findByAliasOrId($type, $alias);

        return null !== $profile && $memberId === (int) $profile['member_id'] ? $profile : [];
    }

    private function getHelpKey(ProfileType $type, ProfileFormMode $mode): string
    {
        $name = match ($type) {
            ProfileType::Artists => 'artist',
            ProfileType::Hosts => 'host',
            ProfileType::Locations => 'location',
        };

        return $name.'_'.$mode->value;
    }
}
