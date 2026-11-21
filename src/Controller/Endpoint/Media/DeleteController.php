<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Media;

use Doctrine\DBAL\Connection;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaAccessGuard;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaException;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaType;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MemberMediaStorage;
use SeKultur\ContaoKulturnetzBundle\Service\Media\RecordMediaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bild löschen (ersetzt files/se-kultur.de/code/delete.php).
 *
 * Galerie: upload=gallery, uuid, type, id; das Bild muss zur Galerie des eigenen
 * Datensatzes gehören. Titelbild: avatar (UUID). In beiden Fällen muss die Datei
 * im Mitgliedsordner liegen, und jeder Datensatz, der sie verwendet, muss dem
 * Mitglied gehören.
 *
 * Wirkung: Verweise werden entfernt (Galerie: aus multiSRC, Titelbild: Feld
 * avatar geleert, Entscheidung E33), danach die Datei samt Original (_ori) bzw.
 * früherer Kopie (_sm) gelöscht. Antwort wie bisher der Text "deleted".
 */
#[AsController]
#[Route('/_kulturnetz/dateien/loeschen', name: 'kulturnetz_datei_loeschen', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class DeleteController extends AbstractMediaController
{
    public function __construct(
        private readonly MediaAccessGuard $guard,
        private readonly MemberMediaStorage $storage,
        private readonly RecordMediaRepository $records,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->respond(function () use ($request): Response {
            $memberId = $this->guard->authorize($request);

            if (MediaType::Gallery->value === $request->request->get('upload')) {
                $type = $this->guard->resolveRecordType($request->request->get('type'), MediaType::Gallery);
                $record = $this->guard->requireOwnedRecord($type, $request->request->get('id'), $memberId, true);
                $uuid = $this->guard->parseUuid($request->request->get('uuid'));

                if (!$record->hasGalleryImage($uuid)) {
                    throw MediaException::forbidden();
                }
            } else {
                $uuid = $this->guard->parseUuid($request->request->get('avatar'));
            }

            $references = $this->guard->requireOwnedFile($uuid, $memberId, true);

            $this->connection->transactional(function () use ($references, $uuid): void {
                foreach ($references as $record) {
                    if ($record->hasAvatar($uuid)) {
                        $this->records->clearAvatar($record, $uuid);
                    }

                    $this->records->removeFromGallery($record, $uuid);
                }

                $this->storage->deleteFile($uuid);
            });

            return new Response('deleted', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
        });
    }
}
