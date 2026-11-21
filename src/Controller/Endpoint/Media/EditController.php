<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Media;

use Doctrine\DBAL\Connection;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaAccessGuard;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaException;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaType;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaUploadValidator;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MemberMediaStorage;
use SeKultur\ContaoKulturnetzBundle\Service\Media\RecordMediaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bildbeschreibung, Copyright und Galerie-Reihenfolge speichern (ersetzt
 * files/se-kultur.de/code/edit.php).
 *
 * Eingaben: uuid[], title[], copyright[] (gleiche Reihenfolge), bei der Galerie
 * zusätzlich upload=gallery, type und id; REQUEST_TOKEN.
 *
 * Galerie: Jede UUID muss zur Galerie des eigenen Datensatzes gehören. Die
 * Reihenfolge von uuid[] wird die neue Reihenfolge von multiSRC; Bilder der
 * Galerie, die in der Anfrage fehlen, bleiben hinten erhalten (Löschen nur über
 * den Löschen-Endpunkt). Antwort {"success": true, "order": "new"}.
 * Titelbild: Die Datei muss dem Mitglied gehören. Antwort {"success": true}.
 */
#[AsController]
#[Route('/_kulturnetz/dateien/bearbeiten', name: 'kulturnetz_datei_bearbeiten', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class EditController extends AbstractMediaController
{
    public function __construct(
        private readonly MediaAccessGuard $guard,
        private readonly MediaUploadValidator $validator,
        private readonly MemberMediaStorage $storage,
        private readonly RecordMediaRepository $records,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->respond(function () use ($request): Response {
            $memberId = $this->guard->authorize($request);
            $isGallery = MediaType::Gallery->value === $request->request->get('upload');

            $uuids = array_map($this->guard->parseUuid(...), $this->getList($request, 'uuid'));
            $titles = $this->getList($request, 'title');
            $copyrights = $this->getList($request, 'copyright');

            if ([] === $uuids || \count($titles) !== \count($uuids) || \count($copyrights) !== \count($uuids)) {
                throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
            }

            $metadata = [];

            foreach ($uuids as $index => $uuid) {
                $metadata[] = [
                    'uuid' => $uuid,
                    'title' => $this->validator->validateTitle($titles[$index]),
                    'copyright' => $this->validator->validateCopyright($copyrights[$index]),
                ];
            }

            $record = null;

            if ($isGallery) {
                $type = $this->guard->resolveRecordType($request->request->get('type'), MediaType::Gallery);
                $record = $this->guard->requireOwnedRecord($type, $request->request->get('id'), $memberId, true);

                foreach ($uuids as $uuid) {
                    if (!$record->hasGalleryImage($uuid)) {
                        throw MediaException::forbidden();
                    }
                }
            }

            foreach ($uuids as $uuid) {
                $this->guard->requireOwnedFile($uuid, $memberId, true);
            }

            $this->connection->transactional(function () use ($metadata, $record, $uuids): void {
                foreach ($metadata as $item) {
                    $this->storage->updateMetadata($item['uuid'], $item['title'], $item['copyright']);
                }

                if (null !== $record) {
                    $remaining = array_values(array_diff($record->gallery, $uuids));
                    $this->records->saveGallery($record, [...array_values(array_unique($uuids)), ...$remaining]);
                }
            });

            return new JsonResponse($isGallery ? ['success' => true, 'order' => 'new'] : ['success' => true]);
        });
    }
}
