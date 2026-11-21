<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Media;

use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaAccessGuard;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaType;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MemberMediaStorage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bilder mit Metadaten abrufen (ersetzt files/se-kultur.de/code/get.php).
 *
 * Galerie: upload=gallery, type, id; Antwort alle Bilder aus multiSRC in
 * gespeicherter Reihenfolge. Titelbild: avatar (UUID); Antwort ein Element.
 * Je Element path, uuid, title, alt, link, caption (Copyright), license wie
 * bisher, ohne den früheren serialisierten Wert "meta"; Texte als Klartext.
 * Bilder ohne Datei werden übersprungen (bisher eine PHP-Warnung).
 */
#[AsController]
#[Route('/_kulturnetz/dateien/abrufen', name: 'kulturnetz_datei_abrufen', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class FetchController extends AbstractMediaController
{
    public function __construct(
        private readonly MediaAccessGuard $guard,
        private readonly MemberMediaStorage $storage,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->respond(function () use ($request): Response {
            $memberId = $this->guard->authorize($request);

            if (MediaType::Gallery->value === $request->request->get('upload')) {
                $type = $this->guard->resolveRecordType($request->request->get('type'), MediaType::Gallery);
                $uuids = $this->guard->requireOwnedRecord($type, $request->request->get('id'), $memberId, false)->gallery;
            } else {
                $uuid = $this->guard->parseUuid($request->request->get('avatar'));
                $this->guard->requireOwnedFile($uuid, $memberId, false);
                $uuids = [$uuid];
            }

            $files = [];

            foreach ($uuids as $uuid) {
                if (null !== ($info = $this->storage->getFileInfo($uuid))) {
                    $files[] = $info->toArray();
                }
            }

            return new JsonResponse($files);
        });
    }
}
