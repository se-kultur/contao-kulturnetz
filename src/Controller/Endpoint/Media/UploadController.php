<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Media;

use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaAccessGuard;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaException;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaType;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaUploadValidator;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MemberMediaStorage;
use SeKultur\ContaoKulturnetzBundle\Service\Media\RecordMediaRepository;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\StoredFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Hochladen (ersetzt files/se-kultur.de/code/upload.php).
 *
 * Eingaben (multipart/form-data, Feldnamen wie bisher): files[], title[],
 * copyright[], upload (avatar|event|gallery), bei Titelbildern baseImg[]
 * (Zuschnitt als PNG-Data-URL), bei der Galerie type und id; REQUEST_TOKEN.
 * Bei Titelbildern sind type und id optional (Anlegen-Formular ohne Datensatz);
 * sind sie angegeben, werden sie geprüft.
 *
 * Antwort wie bisher: JSON-Array [{"uuid": "…", "path": "files/…"}]. Bei der
 * Galerie werden die neuen Bilder hinten an multiSRC angehängt.
 */
#[AsController]
#[Route('/_kulturnetz/dateien/hochladen', name: 'kulturnetz_datei_hochladen', defaults: ['_scope' => 'frontend', '_token_check' => false], methods: ['POST'])]
final class UploadController extends AbstractMediaController
{
    public function __construct(
        private readonly MediaAccessGuard $guard,
        private readonly MediaUploadValidator $validator,
        private readonly MemberMediaStorage $storage,
        private readonly RecordMediaRepository $records,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->respond(function () use ($request): Response {
            $memberId = $this->guard->authorize($request);
            $mediaType = $this->guard->resolveMediaType($request->request->get('upload'));
            $homeDir = $this->guard->getHomeDir($memberId);

            if (MediaType::Gallery === $mediaType) {
                $type = $this->guard->resolveRecordType($request->request->get('type'), $mediaType);
                $record = $this->guard->requireOwnedRecord($type, $request->request->get('id'), $memberId, true);
            } else {
                $record = $this->guard->findOptionalOwnedRecord($request, $mediaType, $memberId, true);
            }

            $files = array_values(array_filter($request->files->all('files')));

            if ([] === $files) {
                throw MediaException::unprocessable(MediaException::MESSAGE_NO_FILE);
            }

            if ($mediaType->isCropped() && 1 !== \count($files)) {
                throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
            }

            $titles = $this->getList($request, 'title');
            $copyrights = $this->getList($request, 'copyright');
            $croppedImages = $this->getList($request, 'baseImg');
            $uploads = [];

            // Erst alles prüfen, dann speichern: keine halben Uploads.
            foreach ($files as $index => $file) {
                $uploads[] = [
                    'image' => $this->validator->validateImage($file),
                    'title' => $this->validator->validateTitle($titles[$index] ?? null),
                    'copyright' => $this->validator->validateCopyright($copyrights[$index] ?? null),
                    'cropped' => $mediaType->isCropped() ? $this->validator->decodeCroppedImage($croppedImages[$index] ?? null) : null,
                ];
            }

            /** @var list<StoredFile> $stored */
            $stored = [];

            try {
                foreach ($uploads as $upload) {
                    $stored[] = null === $upload['cropped']
                        ? $this->storage->storeGalleryImage($homeDir, $upload['image'], $upload['title'], $upload['copyright'])
                        : $this->storage->storeCroppedImage($homeDir, $mediaType, $upload['image'], $upload['cropped'], $upload['title'], $upload['copyright']);
                }

                if (MediaType::Gallery === $mediaType && null !== $record) {
                    $newUuids = array_map(static fn (StoredFile $file): string => hex2bin(str_replace('-', '', $file->uuid)), $stored);
                    $this->records->saveGallery($record, [...$record->gallery, ...$newUuids]);
                }
            } catch (\Throwable $exception) {
                foreach ($stored as $file) {
                    $this->deleteQuietly($file);
                }

                throw $exception;
            }

            return new JsonResponse(array_map(static fn (StoredFile $file): array => $file->toArray(), $stored));
        });
    }

    private function deleteQuietly(StoredFile $file): void
    {
        try {
            $this->storage->deleteFile(hex2bin(str_replace('-', '', $file->uuid)));
        } catch (\Throwable) {
            // Aufräumen nach einem Fehler; der ursprüngliche Fehler wird weitergereicht.
        }
    }
}
