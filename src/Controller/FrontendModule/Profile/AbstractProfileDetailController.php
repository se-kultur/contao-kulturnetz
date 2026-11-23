<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Security\UnpublishedRecordAccess;
use SeKultur\ContaoKulturnetzBundle\Service\Gallery\GalleryViewBuilder;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\AutoItemResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Social\SocialViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Detailansicht eines Profils (Modultypen ModuleArtist, ModuleHost,
 * ModuleLocation).
 *
 * Das Profil kommt über Alias oder ID aus der URL (auto_item). Fehlt der
 * Parameter oder gibt es das Profil nicht, antwortet die Seite mit 404. Gesperrte
 * Profile (disable = 1) liefert die Seite nur an das besitzende Mitglied, an
 * angemeldete Backend-Benutzer und an Vorschaulinks mit unveröffentlichten Inhalten
 * aus (Security\UnpublishedRecordAccess), allen anderen mit 404 wie bei einem
 * unbekannten Alias (bis Runde 7 per Direktlink für alle sichtbar, E39). Nur der
 * Besitzer sieht dazu den Hinweis "Nicht öffentlich". Die Seite trägt dann
 * "noindex,nofollow" im Meta-Tag robots (HtmlHeadBag der Antwort), wodurch weder
 * Suchmaschinen noch die Contao-Suche (SearchIndexListener) sie aufnehmen.
 *
 * Template-Variablen:
 *  - profile_type ("artists"), profile_label ("Künstler*innenprofil"), profile
 *    (Tabellenzeile),
 *  - avatar (Titelbild der Seitenleiste, siehe ProfileViewFactory),
 *  - social (Daten der Reiter und des Folgen-Dialogs, siehe SocialViewFactory,
 *    enthält auch member_id und is_owner),
 *  - gallery (GalleryView der Bildergalerie, für den Besitzer ohne Bilder die
 *    Beispielgalerie).
 */
abstract class AbstractProfileDetailController extends AbstractProfileController
{
    public function __construct(
        FrontendMemberProvider $memberProvider,
        ProfileRepository $profiles,
        ProfileViewFactory $views,
        private readonly AutoItemResolver $autoItem,
        private readonly SocialViewFactory $socialViewFactory,
        private readonly GalleryViewBuilder $galleryViewBuilder,
        private readonly UnpublishedRecordAccess $unpublishedAccess,
    ) {
        parent::__construct($memberProvider, $profiles, $views);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getProfileType();
        $alias = $this->autoItem->resolve($request);
        $profile = null !== $alias ? $this->profiles->findByAliasOrId($type, $alias) : null;

        // Gesperrte Profile ohne Berechtigung wie unbekannte behandeln.
        if (null === $profile || !$this->unpublishedAccess->canView($profile)) {
            throw new PageNotFoundException(\sprintf('%s nicht gefunden: %s', $type->label(), $alias ?? '(ohne Alias)'));
        }

        if ($this->unpublishedAccess->isUnpublished($profile)) {
            $this->getHtmlHeadBag()?->setMetaRobots('noindex,nofollow');
        }

        $social = $this->socialViewFactory->createProfileContext($type, $profile, $this->memberProvider->getMemberId());

        $template->set('profile_type', $type->value);
        $template->set('profile_label', $type->label());
        $template->set('profile', $profile);
        $template->set('avatar', $this->views->createDetailImage($profile));
        $template->set('social', $social);
        $template->set('gallery', $this->galleryViewBuilder->buildForRecord($type->value, (int) $profile['id'], $social['is_owner']));

        return $template->getResponse();
    }
}
