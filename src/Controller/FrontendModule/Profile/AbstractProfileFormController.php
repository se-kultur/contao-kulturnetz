<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Exception\PageNotFoundException;
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
 * (auto_item), sofern es dem angemeldeten Mitglied gehört. Fehlt der Alias, ist er
 * unbekannt oder gehört das Profil einem anderen Mitglied, antwortet die Seite mit
 * 404 (Seite "Nicht gefunden" der Website), noch bevor ein Formular oder eine URL
 * erzeugt wird; fremde Profile verraten so nicht, ob es sie gibt. Bisher lieferte
 * Contao 4.13 in diesen Fällen ein leeres Formular, das sich nicht speichern ließ.
 * Zusätzlich prüft der Endpunkt kulturnetz_profil_aendern Mitglied, Besitz und
 * Token beim Speichern selbst.
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
        $record = ProfileFormMode::Edit === $mode ? $this->getOwnProfile($type, $request) : [];

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
     * Das Profil aus der URL, wenn es dem angemeldeten Mitglied gehört.
     *
     * @return array<string, mixed>
     *
     * @throws PageNotFoundException wenn der Alias fehlt oder unbekannt ist oder das Profil einem anderen Mitglied gehört
     */
    private function getOwnProfile(ProfileType $type, Request $request): array
    {
        $alias = $this->autoItem->resolve($request);
        $memberId = $this->memberProvider->getMemberId();
        $profile = null !== $alias ? $this->profiles->findByAliasOrId($type, $alias) : null;

        if (null === $profile || $memberId < 1 || $memberId !== (int) $profile['member_id']) {
            throw new PageNotFoundException(\sprintf('%s zum Bearbeiten nicht gefunden: %s', $type->label(), $alias ?? '(ohne Alias)'));
        }

        return $profile;
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
