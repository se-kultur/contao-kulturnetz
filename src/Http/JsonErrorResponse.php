<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Http;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Einheitliche Fehlerantwort der Endpunkte (Entscheidung E31).
 *
 * Format: {"success": false, "error": "<Meldung>"} mit passendem HTTP-Status. Das
 * Format entspricht der bisherigen Fehlerantwort der Anlegen-Skripte; neu ist der
 * Status, den die JS-Dateien im error-Callback auswerten.
 */
#[Exclude]
final class JsonErrorResponse extends JsonResponse
{
    public const MESSAGE_UNAUTHORIZED = 'Zugriff verweigert';

    public const MESSAGE_FORBIDDEN = 'Zugriff verweigert';

    public const MESSAGE_NOT_FOUND = 'Nicht gefunden';

    /**
     * @param array<string, mixed> $extra zusätzliche Felder (z. B. Feldfehler)
     */
    public function __construct(string $message, int $status, array $extra = [])
    {
        parent::__construct(['success' => false, 'error' => $message, ...$extra], $status);
    }

    /**
     * 401: kein Mitglied angemeldet.
     */
    public static function unauthorized(string $message = self::MESSAGE_UNAUTHORIZED): self
    {
        return new self($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * 403: Datensatz gehört nicht dem Mitglied, schreibgeschützt oder Token ungültig.
     */
    public static function forbidden(string $message = self::MESSAGE_FORBIDDEN): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * 404: Datensatz oder Datei nicht gefunden.
     */
    public static function notFound(string $message = self::MESSAGE_NOT_FOUND): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * 422: Eingabe ungültig (Pflichtfeld, Länge, unbekannter Typ).
     *
     * @param array<string, mixed> $extra
     */
    public static function unprocessable(string $message, array $extra = []): self
    {
        return new self($message, Response::HTTP_UNPROCESSABLE_ENTITY, $extra);
    }
}
