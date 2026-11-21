<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileCategories;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileFilterFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verzeichnis aller freigeschalteten Profile einer Profilart (Modultypen
 * ModuleArtistsListAll, ModuleHostsListAll, ModuleLocationsListAll).
 *
 * Auf der Übersichtsseite des KulturNetzes (Konfiguration kulturnetz.teaser.page,
 * bisher Seiten-ID 7) läuft das Modul als Teaser: wenige Profile ohne
 * Suchformular (kulturnetz.teaser.profile_limit). Sonst gilt das Verzeichnis-Limit
 * (kulturnetz.limits.profile_directory) mit Suchformular. Das Musterprofil steht
 * immer zuerst.
 *
 * Filter kommen aus der URL (kategorie nur bei Künstler*innen, text bei allen),
 * nur als einfache Zeichenketten; Arrays in der URL werden verworfen. Die Werte
 * für das Suchformular (search) stehen im Contao-Eingabeformat (TextNormalizer):
 * Sonst würde Contao Insert-Tags im Suchbegriff beim Ausliefern der Seite
 * auswerten (?text={{...}}).
 *
 * Template-Variablen (Template frontend_module/kulturnetz_profile_list):
 *  profile_type, items (siehe ProfileViewFactory::createListItems()), filter
 *  (Suchformular zeigen), search (Filterwerte der URL, siehe oben), categories (Auswahl des
 *  Kategorie-Filters, nur Künstler*innen mit Suchformular, sonst null).
 */
abstract class AbstractProfileDirectoryController extends AbstractProfileController
{
    public function __construct(
        FrontendMemberProvider $memberProvider,
        ProfileRepository $profiles,
        ProfileViewFactory $views,
        private readonly ProfileFilterFactory $filterFactory,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly ProfileCategories $categories,
        private readonly KulturnetzConfig $config,
        private readonly TextNormalizer $textNormalizer,
    ) {
        parent::__construct($memberProvider, $profiles, $views);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $type = $this->getProfileType();
        $memberId = $this->memberProvider->getMemberId();
        $filter = $this->filterFactory->createProfileFilter($request, $type);

        if ($this->isTeaserPage()) {
            $profiles = $this->profiles->findDirectory($type, $this->config->getTeaserProfileLimit());
            $showFilter = false;
        } else {
            $profiles = $this->profiles->findDirectory($type, $this->config->getProfileDirectoryLimit(), $filter);
            $showFilter = true;
        }

        $template->set('profile_type', $type->value);
        $template->set('items', $this->views->createListItems($type, $profiles, $memberId));
        $template->set('filter', $showFilter);
        $template->set('search', array_map($this->textNormalizer->normalize(...), $filter->toArray()));
        $template->set('categories', $showFilter && $type->hasCategoryFilter() ? $this->categories->options() : null);

        return $template->getResponse();
    }

    private function isTeaserPage(): bool
    {
        $teaserPage = $this->pageUrlResolver->findPage('teaser');
        $currentPage = $this->getPageModel();

        return null !== $teaserPage && null !== $currentPage && (int) $teaserPage->id === (int) $currentPage->id;
    }
}
