<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Posting;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Social\PostingService;
use SeKultur\ContaoKulturnetzBundle\Service\Social\SocialViewFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Persönlicher Beitrags-Feed des angemeldeten Mitglieds (Modultyp
 * "ModulePostingsList", bisher Module\ModulePostingsList): eigene Beiträge und
 * Beiträge der Profile, denen ein Profil des Mitglieds folgt, mit Hinweisbox.
 */
#[AsFrontendModule(type: 'ModulePostingsList', category: 'eProjekt', template: 'frontend_module/kulturnetz_posting_feed')]
final class OwnFeedController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly PostingService $postingService,
        private readonly SocialViewFactory $viewFactory,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $memberId = $this->memberProvider->getMemberId();

        $template->set('member_id', $memberId);
        $template->set('own_feed', true);
        $template->set('postings', $this->viewFactory->createFeedItems($this->postingService->findMemberFeed($memberId)));

        return $template->getResponse();
    }
}
