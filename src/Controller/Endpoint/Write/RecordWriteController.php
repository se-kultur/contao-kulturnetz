<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Write;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Security\CsrfRequestValidator;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordSchemaRegistry;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordValidationException;
use SeKultur\ContaoKulturnetzBundle\Service\Write\RecordWriter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Anlegen und Bearbeiten von Profilen und Veranstaltungen durch Mitglieder
 * (Ersatz für files/se-kultur.de/code/insert<Typ>.php, research/13 Abschnitt 3.2).
 *
 * Typen: artists, hosts, locations, events, sekevents. Anfrage: POST mit den
 * Feldern der heutigen Formulare (application/x-www-form-urlencoded) plus
 * REQUEST_TOKEN (oder Header X-CSRF-Token); beim Bearbeiten zusätzlich "id".
 * member_id, alias und security werden ignoriert.
 *
 * Antworten: {"success":true} wie bisher; Fehler als JSON
 * {"success":false,"error":"..."} mit 401 (kein Mitglied), 403 (Token ungültig,
 * fremder oder schreibgeschützter Datensatz), 404 (Datensatz unbekannt), 422
 * (Eingabe ungültig, zusätzlich "fields" mit Formularfeldname => Meldung). Ein
 * unbekannter Typ passt auf keine Route (404 der Website).
 *
 * Der Contao-RequestTokenListener ist für die Routen abgeschaltet
 * (_token_check: false), weil er Ajax-Anfragen ohnehin überspringt und sonst eine
 * HTML-Fehlerseite statt der JSON-Antwort liefert; das Token prüft der Controller
 * selbst (CsrfRequestValidator).
 */
#[AsController]
final class RecordWriteController
{
    public const MESSAGE_UNKNOWN_TYPE = 'Unbekannter Typ';

    public const MESSAGE_INVALID_ID = 'Ungültige ID';

    public const MESSAGE_READONLY = 'Dieser Eintrag kann nicht mehr bearbeitet werden.';

    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly CsrfRequestValidator $csrfValidator,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly RecordSchemaRegistry $schemas,
        private readonly RecordWriter $writer,
    ) {
    }

    #[Route(
        '/_kulturnetz/{typ}/anlegen',
        name: 'kulturnetz_profil_anlegen',
        requirements: ['typ' => 'artists|hosts|locations|events|sekevents'],
        defaults: ['_scope' => 'frontend', '_token_check' => false],
        methods: ['POST'],
    )]
    public function create(Request $request, string $typ): JsonResponse
    {
        if (null !== $error = $this->checkRequest($request, $typ)) {
            return $error;
        }

        try {
            $this->writer->create($this->schemas->get($typ), $request->request->all(), $this->memberProvider->getMemberId());
        } catch (RecordValidationException $exception) {
            return JsonErrorResponse::unprocessable($exception->getMessage(), ['fields' => $exception->getFieldErrors()]);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route(
        '/_kulturnetz/{typ}/aendern',
        name: 'kulturnetz_profil_aendern',
        requirements: ['typ' => 'artists|hosts|locations|events|sekevents'],
        defaults: ['_scope' => 'frontend', '_token_check' => false],
        methods: ['POST'],
    )]
    public function update(Request $request, string $typ): JsonResponse
    {
        if (null !== $error = $this->checkRequest($request, $typ)) {
            return $error;
        }

        $id = $request->request->all()['id'] ?? null;

        if (!\is_string($id) || 1 !== preg_match('/^[1-9]\d{0,9}$/', $id)) {
            return JsonErrorResponse::unprocessable(self::MESSAGE_INVALID_ID, ['fields' => ['id' => self::MESSAGE_INVALID_ID]]);
        }

        $schema = $this->schemas->get($typ);
        $memberId = $this->memberProvider->getMemberId();
        $current = $this->writer->find($schema, (int) $id);

        if (null === $current) {
            return JsonErrorResponse::notFound();
        }

        if (!$this->ownershipChecker->ownsRecord($typ, (int) $id, $memberId)) {
            return JsonErrorResponse::forbidden();
        }

        if ($this->ownershipChecker->isReadonly($typ, (int) $id)) {
            return JsonErrorResponse::forbidden(self::MESSAGE_READONLY);
        }

        try {
            $this->writer->update($schema, $current, $request->request->all(), $memberId);
        } catch (RecordValidationException $exception) {
            return JsonErrorResponse::unprocessable($exception->getMessage(), ['fields' => $exception->getFieldErrors()]);
        }

        return new JsonResponse(['success' => true]);
    }

    /**
     * Gemeinsame Prüfungen in fester Reihenfolge: Typ (404), Mitglied (401), Token (403).
     */
    private function checkRequest(Request $request, string $type): JsonResponse|null
    {
        if (!$this->schemas->has($type)) {
            return JsonErrorResponse::notFound(self::MESSAGE_UNKNOWN_TYPE);
        }

        if (!$this->memberProvider->hasMember()) {
            return JsonErrorResponse::unauthorized();
        }

        if (!$this->csrfValidator->isValid($request)) {
            return JsonErrorResponse::forbidden();
        }

        return null;
    }
}
