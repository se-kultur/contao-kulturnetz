<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use SeKultur\ContaoKulturnetzBundle\Security\CsrfRequestValidator;
use SeKultur\ContaoKulturnetzBundle\Security\OwnershipChecker;
use SeKultur\ContaoKulturnetzBundle\Service\Media\Value\MediaRecord;
use SeKultur\ContaoKulturnetzBundle\Service\Member\FrontendMemberProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Zugriffsregeln der Datei-Endpunkte an einer Stelle.
 *
 * Reihenfolge der Prüfungen: angemeldetes Mitglied (401), Request-Token (403),
 * Eingaben (422), Datensatz bzw. Datei vorhanden (404), Besitz und Schreibschutz
 * (403). Mitglieds-ID, Mitgliedsordner und Besitz kommen ausschließlich aus
 * Sitzung und Datenbank; die früheren POST-Felder member_id, security und alias
 * werden nicht ausgewertet.
 */
final class MediaAccessGuard
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly FrontendMemberProvider $memberProvider,
        private readonly CsrfRequestValidator $csrfValidator,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly RecordMediaRepository $records,
        private readonly MemberMediaStorage $storage,
        private readonly MediaUploadValidator $validator,
    ) {
    }

    /**
     * Liefert die ID des angemeldeten Mitglieds nach der Token-Prüfung.
     *
     * Ein Upload, der post_max_size überschreitet, kommt ohne POST-Daten und damit
     * ohne Token an; er wird als zu groß (422) gemeldet statt als Token-Fehler.
     *
     * @throws MediaException 401, 403 oder 422
     */
    public function authorize(Request $request): int
    {
        $memberId = $this->memberProvider->getMemberId();

        if ($memberId < 1) {
            throw MediaException::unauthorized();
        }

        if (0 === $request->request->count() && 0 === $request->files->count() && (int) $request->server->get('CONTENT_LENGTH') > 0) {
            throw MediaException::unprocessable($this->validator->getFileTooLargeMessage());
        }

        if (!$this->csrfValidator->isValid($request)) {
            throw MediaException::forbidden();
        }

        return $memberId;
    }

    /**
     * Mitgliedsordner (tl_member.homeDir) relativ zum Projektverzeichnis.
     *
     * @throws MediaException 403 ohne Ordner
     */
    public function getHomeDir(int $memberId): string
    {
        return $this->ownershipChecker->getHomeDirPath($memberId)
            ?? throw MediaException::forbidden(MediaException::MESSAGE_NO_HOME_DIR);
    }

    /**
     * Upload-Art aus dem Feld "upload" (avatar, event, gallery).
     *
     * @throws MediaException 422 bei unbekanntem Wert
     */
    public function resolveMediaType(mixed $value): MediaType
    {
        return (\is_string($value) ? MediaType::tryFrom($value) : null)
            ?? throw MediaException::unprocessable(MediaException::MESSAGE_UNKNOWN_TYPE);
    }

    /**
     * Datensatztyp aus dem Feld "type" (Typschlüssel oder Tabellenname), passend
     * zur Upload-Art.
     *
     * @throws MediaException 422 bei unbekanntem oder unpassendem Typ
     */
    public function resolveRecordType(mixed $value, MediaType $mediaType): string
    {
        $type = \is_string($value) ? $this->ownershipChecker->resolveType($value, $mediaType->allowedRecordTypes()) : null;

        return $type ?? throw MediaException::unprocessable(MediaException::MESSAGE_UNKNOWN_TYPE);
    }

    /**
     * Datensatz des Mitglieds.
     *
     * @param bool $forWrite true, wenn der Endpunkt den Datensatz oder seine
     *                       Dateien ändert (schreibgeschützte Veranstaltungen
     *                       sind dann ausgeschlossen)
     *
     * @throws MediaException 422 (ID ungültig), 404 (nicht vorhanden), 403 (fremd oder schreibgeschützt)
     */
    public function requireOwnedRecord(string $type, mixed $id, int $memberId, bool $forWrite): MediaRecord
    {
        if (!\is_string($id) || 1 !== preg_match('/^[1-9]\d{0,9}$/', $id)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        $record = $this->records->findRecord($type, (int) $id) ?? throw MediaException::notFound();

        if (!$record->isOwnedBy($memberId) || ($forWrite && $record->readonly)) {
            throw MediaException::forbidden();
        }

        return $record;
    }

    /**
     * Optionaler Datensatz der Titelbild-Uploads: Ohne "id" (Anlegen-Formular)
     * null, sonst wie requireOwnedRecord() mit Typprüfung.
     *
     * @throws MediaException
     */
    public function findOptionalOwnedRecord(Request $request, MediaType $mediaType, int $memberId, bool $forWrite): MediaRecord|null
    {
        $type = $request->request->get('type');
        $id = $request->request->get('id');

        if ((null === $id || '' === $id) && (null === $type || '' === $type)) {
            return null;
        }

        $type = $this->resolveRecordType($type, $mediaType);

        if (null === $id || '' === $id) {
            return null;
        }

        return $this->requireOwnedRecord($type, $id, $memberId, $forWrite);
    }

    /**
     * UUID aus einem Formularfeld (Textform mit Bindestrichen, jede Version) als
     * 16 Byte.
     *
     * @throws MediaException 422 bei ungültigem Wert
     */
    public function parseUuid(mixed $value): string
    {
        if (!\is_string($value) || 1 !== preg_match(self::UUID_PATTERN, $value)) {
            throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }

        return hex2bin(str_replace('-', '', $value)) ?: throw MediaException::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
    }

    /**
     * Prüft, ob das Mitglied über die Datei verfügen darf: Sie existiert (404),
     * liegt im Mitgliedsordner (403), und jeder Datensatz, der sie als Titelbild
     * oder in der Galerie verwendet, gehört dem Mitglied (403); bei ändernden
     * Zugriffen darf keiner davon schreibgeschützt sein (403).
     *
     * @return list<MediaRecord> Datensätze, die die Datei verwenden
     *
     * @throws MediaException
     */
    public function requireOwnedFile(string $binaryUuid, int $memberId, bool $forWrite): array
    {
        if (null === $this->storage->getProjectPath($binaryUuid)) {
            throw MediaException::notFound();
        }

        if (!$this->ownershipChecker->isFileInHomeDir($binaryUuid, $memberId)) {
            throw MediaException::forbidden();
        }

        $records = $this->records->findReferencingRecords($binaryUuid);

        foreach ($records as $record) {
            if (!$record->isOwnedBy($memberId) || ($forWrite && $record->readonly)) {
                throw MediaException::forbidden();
            }
        }

        return $records;
    }
}
