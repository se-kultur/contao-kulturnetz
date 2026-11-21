<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Page;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Symfony\Component\HttpFoundation\Request;

/**
 * Liefert den Alias aus der URL (Routenparameter "auto_item") für Detail- und
 * Bearbeiten-Module, die als Fragment-Controller laufen.
 *
 * Der Wert steht im Request-Attribut "auto_item". Contao 5.7 meldet einen
 * Routenparameter aber nur dann als benutzt, wenn er über Input::get() gelesen
 * wurde, sonst antwortet die Seite mit 404 (LegacyRouteParametersListener,
 * Restpunkt R17 in CONTAO6-BEREITSCHAFT.md). Deshalb steht der Adapter-Aufruf
 * von Input::get() genau hier an einer Stelle; der gelieferte Wert selbst kommt
 * roh aus dem Request (nicht input-kodiert) und wird beim Ausgeben in Twig
 * maskiert.
 *
 * Nur in Controllern aufrufen, die den Parameter wirklich auswerten: Wer den
 * Parameter markiert, obwohl die Seite ihn nicht braucht, verhindert die
 * 404-Antwort für unbekannte URLs.
 */
final class AutoItemResolver
{
    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * Alias bzw. ID aus der URL oder null, wenn die URL keinen Parameter enthält.
     */
    public function resolve(Request $request): string|null
    {
        $value = $request->attributes->get('auto_item');

        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $this->framework->initialize();
        $this->framework->getAdapter(Input::class)->get('auto_item');

        return $value;
    }
}
