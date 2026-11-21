<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Social;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Security\CsrfRequestValidator;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Social\PostingService;
use SeKultur\ContaoKulturnetzBundle\Service\Social\SocialActionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Beiträge anlegen, ändern und löschen (ersetzt files/se-kultur.de/code/posting.php).
 *
 * POST-Felder wie bisher: posting_type (posting, edit, delete), content,
 * profile_id und profile_type (Tabellenname) beim Anlegen, posting_id beim Ändern
 * und Löschen, dazu REQUEST_TOKEN. Die früheren Felder member_id und security
 * werden ignoriert; Mitglied und Besitz kommen aus Sitzung und Datenbank.
 *
 * Erfolg: Text "success" wie bisher. Fehler: JSON {"success": false, "error": ...}
 * mit Status 401 (nicht angemeldet), 403 (Token ungültig, fremdes Profil oder
 * fremder Beitrag), 404 (Beitrag unbekannt), 422 (Eingabe ungültig) oder 500
 * (Datenbankfehler); "error" enthält die bisherigen Meldungstexte.
 */
#[Route('/_kulturnetz/beitraege', name: 'kulturnetz_beitrag', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class PostingController extends AbstractController
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly CsrfRequestValidator $csrfValidator,
        private readonly PostingService $postingService,
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

        $input = $request->request;
        $action = $input->all()['posting_type'] ?? null;
        $content = $input->all()['content'] ?? null;

        try {
            if ('delete' === $action) {
                $this->postingService->delete($memberId, $this->intField($request, 'posting_id'));
            } elseif ('posting' === $action || 'edit' === $action) {
                if (!\is_string($content)) {
                    throw SocialActionException::invalid(SocialActionException::MESSAGE_CONTENT_MISSING);
                }

                if ('posting' === $action) {
                    $profileType = $input->all()['profile_type'] ?? null;

                    $this->postingService->create(
                        $memberId,
                        \is_string($profileType) ? $profileType : '',
                        $this->intField($request, 'profile_id'),
                        $content,
                    );
                } else {
                    $this->postingService->update($memberId, $this->intField($request, 'posting_id'), $content);
                }
            } else {
                throw SocialActionException::invalid(SocialActionException::MESSAGE_UNKNOWN_ACTION);
            }
        } catch (SocialActionException $exception) {
            return new JsonErrorResponse($exception->getMessage(), $exception->getStatus());
        }

        return new Response('success', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Ganzzahliges POST-Feld, 0 bei fehlendem oder ungültigem Wert.
     */
    private function intField(Request $request, string $name): int
    {
        $value = $request->request->all()[$name] ?? null;

        return \is_string($value) && 1 === preg_match('/^[1-9]\d{0,9}$/', $value) ? (int) $value : 0;
    }
}
