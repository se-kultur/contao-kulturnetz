<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\Endpoint\Media;

use SeKultur\ContaoKulturnetzBundle\Http\JsonErrorResponse;
use SeKultur\ContaoKulturnetzBundle\Service\Media\MediaException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gemeinsamer Rahmen der vier Datei-Endpunkte (Routen kulturnetz_datei_*).
 *
 * Alle Routen: nur POST, Scope "frontend" (Mitgliedersitzung der Frontend-
 * Firewall), "_token_check" aus, weil das Request-Token im Controller selbst
 * geprüft wird (CsrfRequestValidator über MediaAccessGuard), unabhängig vom
 * Header X-Requested-With und mit JSON-Antwort statt Fehlerseite.
 *
 * Erfolgsantworten wie die früheren Skripte unter files/se-kultur.de/code,
 * Fehler als JSON {"success": false, "error": "…"} mit Status 401, 403, 404
 * oder 422 (Entscheidung E31).
 */
abstract class AbstractMediaController
{
    /**
     * @param \Closure(): Response $action
     */
    protected function respond(\Closure $action): Response
    {
        try {
            return $action();
        } catch (MediaException $exception) {
            return $exception->toResponse();
        } catch (BadRequestException) {
            // Feld mit unerwarteter Struktur (z. B. Liste statt Text)
            return JsonErrorResponse::unprocessable(MediaException::MESSAGE_INVALID_INPUT);
        }
    }

    /**
     * Liste aus einem Formularfeld "name[]"; fehlt das Feld, eine leere Liste.
     *
     * @return list<mixed>
     */
    protected function getList(Request $request, string $name): array
    {
        return array_values($request->request->all($name));
    }
}
