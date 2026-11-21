<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Security;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Prüft das Contao-Request-Token der Endpunkte des Bundles.
 *
 * Der globale RequestTokenListener von Contao überspringt Ajax-Anfragen
 * (X-Requested-With: XMLHttpRequest) und Anfragen ohne Formular-Inhaltstyp. Die
 * Endpunkte prüfen deshalb selbst und unabhängig von diesem Header.
 *
 * Das Token steht im POST-Feld "REQUEST_TOKEN" (wie bei Contao-Formularen) oder im
 * Header "X-CSRF-Token"; das Template liefert den Wert aus contao.request_token.
 * Geprüft wird mit dem Contao-CSRF-Token-Manager und dem Token-Namen aus dem
 * Parameter contao.csrf_token_name.
 */
final class CsrfRequestValidator
{
    public const FIELD = 'REQUEST_TOKEN';

    public const HEADER = 'X-CSRF-Token';

    public function __construct(
        private readonly ContaoCsrfTokenManager $tokenManager,
        #[Autowire(param: 'contao.csrf_token_name')]
        private readonly string $tokenName,
    ) {
    }

    /**
     * True, wenn der Request ein gültiges Token trägt.
     */
    public function isValid(Request $request): bool
    {
        $value = $this->getTokenValue($request);

        if (null === $value || '' === $value) {
            return false;
        }

        return $this->tokenManager->isTokenValid(new CsrfToken($this->tokenName, $value));
    }

    /**
     * Token aus dem POST-Feld, sonst aus dem Header; null, wenn keines vorhanden
     * oder das Feld kein String ist.
     */
    public function getTokenValue(Request $request): string|null
    {
        $value = $request->request->all()[self::FIELD] ?? null;

        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        $header = $request->headers->get(self::HEADER);

        return \is_string($header) && '' !== $header ? $header : null;
    }
}
