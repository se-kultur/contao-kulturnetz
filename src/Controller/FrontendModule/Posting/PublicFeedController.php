<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Posting;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Page\PageUrlResolver;
use SeKultur\ContaoKulturnetzBundle\Service\Social\PostingService;
use SeKultur\ContaoKulturnetzBundle\Service\Social\SocialViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentlicher Beitrags-Feed (Modultyp "ModulePostingsListAll", bisher
 * Module\ModulePostingsListAll): neueste Beiträge aller nicht gesperrten Profile.
 *
 * Auf der Übersichtsseite (Konfiguration kulturnetz.teaser.page, bisher Seiten-ID
 * 7) gilt das Limit kulturnetz.teaser.posting_limit, sonst
 * kulturnetz.limits.posting_feed.
 */
#[AsFrontendModule(type: 'ModulePostingsListAll', category: 'eProjekt', template: 'frontend_module/kulturnetz_posting_feed')]
final class PublicFeedController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly PostingService $postingService,
        private readonly SocialViewFactory $viewFactory,
        private readonly PageUrlResolver $pageUrlResolver,
        private readonly KulturnetzConfig $config,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $teaserPage = $this->pageUrlResolver->findPage('teaser');
        $currentPage = $this->getPageModel();

        $limit = null !== $teaserPage && null !== $currentPage && (int) $teaserPage->id === (int) $currentPage->id
            ? $this->config->getTeaserPostingLimit()
            : $this->config->getPostingFeedLimit();

        $template->set('member_id', $this->memberProvider->getMemberId());
        $template->set('own_feed', false);
        $template->set('postings', $this->viewFactory->createFeedItems($this->postingService->findLatest($limit)));

        return $template->getResponse();
    }
}
