<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Media;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fachlicher Fehler der Datei-Endpunkte mit dem HTTP-Status der Antwort
 * (Entscheidung E31: 401, 403, 404, 422).
 *
 * Die Controller wandeln die Ausnahme mit toResponse() in die einheitliche
 * JSON-Fehlerantwort des Bundles um. Die Meldung ist für Mitglieder bestimmt
 * und enthält keine internen Angaben (Pfade, IDs fremder Datensätze).
 */
#[Exclude]
final class MediaException extends \RuntimeException
{
    public const MESSAGE_INVALID_FILE_TYPE = 'Der gewählte Dateityp ist nicht erlaubt. Bitte wählen Sie ausschließlich PNG- oder JPG-Dateien aus.';

    public const MESSAGE_NO_FILE = 'Bitte wählen Sie eine Datei aus!';

    public const MESSAGE_FILE_TOO_LARGE = 'Die gewählte Datei ist zu groß.';

    public const MESSAGE_COPYRIGHT_MISSING = 'Bitte geben Sie das Copyright an.';

    public const MESSAGE_INVALID_INPUT = 'Die Angaben sind unvollständig oder ungültig.';

    public const MESSAGE_UNKNOWN_TYPE = 'Unbekannter Typ.';

    public const MESSAGE_NO_HOME_DIR = 'Für Ihr Benutzerkonto ist kein Dateiordner eingerichtet.';

    private function __construct(string $message, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function unauthorized(): self
    {
        return new self(JsonErrorResponse::MESSAGE_UNAUTHORIZED, Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden(string $message = JsonErrorResponse::MESSAGE_FORBIDDEN): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    public static function notFound(): self
    {
        return new self(JsonErrorResponse::MESSAGE_NOT_FOUND, Response::HTTP_NOT_FOUND);
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function toResponse(): JsonErrorResponse
    {
        return new JsonErrorResponse($this->getMessage(), $this->status);
    }
}
