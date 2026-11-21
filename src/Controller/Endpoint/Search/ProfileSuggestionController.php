<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Search;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileType;
use SeKultur\ContaoKulturnetzBundle\Service\Search\ProfileSuggestionSearch;
use SeKultur\ContaoKulturnetzBundle\Service\Search\SuggestionTerm;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vorschlagssuche der Profilfelder in den Veranstaltungsformularen (Künstler*innen,
 * Veranstalter*innen, Locations), Ersatz für autocompleteArtists.php,
 * autocompleteHosts.php und autocompleteLocations.php.
 *
 * Nur für angemeldete Mitglieder (401). GET mit "term" (mindestens 3 Zeichen,
 * sonst 422); der frühere Parameter member_id entfällt, das Mitglied kommt aus
 * der Sitzung. Antwort: JSON-Liste wie bisher, bei 0 Treffern [].
 */
#[AsController]
final class ProfileSuggestionController
{
    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly SuggestionTerm $term,
        private readonly ProfileSuggestionSearch $search,
    ) {
    }

    #[Route(
        '/_kulturnetz/suche/profile/{typ}',
        name: 'kulturnetz_suche_profile',
        requirements: ['typ' => 'artists|hosts|locations'],
        defaults: ['_scope' => 'frontend'],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $typ): JsonResponse
    {
        $type = ProfileType::tryFrom($typ);

        if (null === $type) {
            return JsonErrorResponse::notFound('Unbekannter Typ');
        }

        if (!$this->memberProvider->hasMember()) {
            return JsonErrorResponse::unauthorized();
        }

        $term = $this->term->fromRequest($request);

        if (null === $term) {
            return JsonErrorResponse::unprocessable(SuggestionTerm::MESSAGE_TOO_SHORT, ['fields' => ['term' => SuggestionTerm::MESSAGE_TOO_SHORT]]);
        }

        return new JsonResponse($this->search->suggest($type, $term, $this->memberProvider->getMemberId(), $request->getBasePath()));
    }
}
