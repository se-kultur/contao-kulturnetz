<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Social;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Security\CsrfRequestValidator;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Social\FollowerService;
use SeKultur\ContaoKulturnetzBundle\Service\Social\SocialActionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Folgen und Entfolgen (ersetzt files/se-kultur.de/code/follow.php).
 *
 * POST-Felder wie bisher: following_id, following_type (Tabellenname des
 * Zielprofils), follower[] (eigene Profile als "<tabelle>-<id>"), dazu
 * REQUEST_TOKEN. Je Profil wird das Folgen umgeschaltet. Das frühere Feld
 * member_id wird ignoriert.
 *
 * Erfolg: je umgeschaltetem Profil der Text "Erfolg" wie bisher (ohne Auswahl
 * leer; das Skript lädt die Seite in beiden Fällen neu). Fehler: JSON mit Status
 * 401, 403, 404, 422 oder 500 (Entscheidung E31); vor einem Fehler wird nichts
 * geändert.
 */
#[Route('/_kulturnetz/folgen', name: 'kulturnetz_folgen', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class FollowController extends AbstractController
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly CsrfRequestValidator $csrfValidator,
        private readonly FollowerService $followerService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $memberId = $this->memberProvider->getMemberId();

        if ($memberId < 1) {
            return JsonErrorResponse::unauthorized();
        }

        if (!$this->csrfValidator->isValid($request)) {
            return JsonErrorResponse::forbidden();
        }

        $input = $request->request->all();
        $followingType = $input['following_type'] ?? null;
        $followingId = $input['following_id'] ?? null;
        $followers = $input['follower'] ?? [];

        if (!\is_string($followingType) || !\is_string($followingId) || 1 !== preg_match('/^[1-9]\d{0,9}$/', $followingId)) {
            return JsonErrorResponse::unprocessable(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        if (!\is_array($followers) || array_filter($followers, static fn ($value): bool => !\is_string($value))) {
            return JsonErrorResponse::unprocessable(SocialActionException::MESSAGE_ACCESS_DENIED);
        }

        try {
            $count = $this->followerService->toggle($memberId, $followingType, (int) $followingId, array_values($followers));
        } catch (SocialActionException $exception) {
            return new JsonErrorResponse($exception->getMessage(), $exception->getStatus());
        }

        return new Response(str_repeat('Erfolg', $count), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
