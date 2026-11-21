<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Search;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;
use SeKultur\ContaoKulturnetzBundle\Service\Search\EventSuggestionSearch;
use SeKultur\ContaoKulturnetzBundle\Service\Search\SuggestionTerm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Öffentliche Vorschlagssuche der Veranstaltungsübersichten (Suchfeld "text"),
 * Ersatz für autocompleteEvents.php und autocompleteSekevents.php.
 *
 * GET mit "term" (mindestens 3 Zeichen). Antwort: JSON-Liste wie bisher, bei 0
 * Treffern []. Fehlt der Begriff oder ist er zu kurz: 422.
 */
#[AsController]
final class EventSuggestionController
{
    public function __construct(
        private readonly SuggestionTerm $term,
        private readonly EventSuggestionSearch $search,
    ) {
    }

    #[Route('/_kulturnetz/suche/veranstaltungen', name: 'kulturnetz_suche_events', defaults: ['_scope' => 'frontend'], methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        return $this->respond($request, EventType::Events);
    }

    #[Route('/_kulturnetz/suche/sekultur-events', name: 'kulturnetz_suche_sekevents', defaults: ['_scope' => 'frontend'], methods: ['GET'])]
    public function sekevents(Request $request): JsonResponse
    {
        return $this->respond($request, EventType::SekEvents);
    }

    private function respond(Request $request, EventType $type): JsonResponse
    {
        $term = $this->term->fromRequest($request);

        if (null === $term) {
            return JsonErrorResponse::unprocessable(SuggestionTerm::MESSAGE_TOO_SHORT, ['fields' => ['term' => SuggestionTerm::MESSAGE_TOO_SHORT]]);
        }

        return new JsonResponse($this->search->suggest($type, $term, $request->getBasePath()));
    }
}
