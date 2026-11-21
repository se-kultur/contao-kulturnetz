<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Helper;

use Contao\FrontendUser;
use Contao\System;

/**
 * Zentrale Abfragen zum Kontext des aktuellen Requests für die Legacy-Klassen
 * des Bundles (Frontend-Module, Inhaltselemente, Models).
 *
 * Contao 5 kennt die Konstanten TL_MODE und FE_USER_LOGGED_IN nicht mehr. Die
 * Legacy-Klassen haben keinen Zugriff auf Dependency Injection, deshalb lösen
 * die Methoden die benötigten Dienste über System::getContainer() auf – genau
 * wie die Legacy-Module des Contao-Kerns (z. B. ModuleChangePassword,
 * ModuleCloseAccount, ModuleBooknav in Contao 5.7).
 *
 * Unterschiede zu Contao 4.13:
 *  - TL_MODE wurde einmalig beim Start des Frameworks aus dem Haupt-Request
 *    bestimmt. isBackendRequest() prüft dagegen den aktuellen Request des
 *    request_stack (so wie der Kern in 5.7). Ohne Request (Konsole, Cron)
 *    liefert die Methode false; in 4.13 war TL_MODE dort null, ein Vergleich
 *    mit 'BE' also ebenfalls false.
 *  - FE_USER_LOGGED_IN war in Backend-Requests immer false. hasFrontendUser()
 *    fragt wie der Kern in 5.7 nur den Token-Checker; in einem Backend-Request
 *    kann der Wert true sein, wenn in derselben Sitzung ein Mitglied angemeldet
 *    ist. Die Module prüfen isBackendRequest() vorher und geben die Wildcard
 *    aus, die Abweichung bleibt damit ohne Wirkung.
 *  - In der Frontend-Vorschau als Mitglied (Backend-Vorschau mit gewähltem
 *    Mitglied) authentifiziert Contao das Mitglied in der Frontend-Firewall;
 *    hasFrontendUser() liefert dann true, wie FE_USER_LOGGED_IN in 4.13.
 */
final class RequestContext
{
    /**
     * Ersatz für TL_MODE == 'BE'.
     */
    public static function isBackendRequest(): bool
    {
        $container = System::getContainer();
        $request = $container->get('request_stack')->getCurrentRequest();

        if (null === $request) {
            return false;
        }

        return $container->get('contao.routing.scope_matcher')->isBackendRequest($request);
    }

    /**
     * Ersatz für FE_USER_LOGGED_IN === true.
     */
    public static function hasFrontendUser(): bool
    {
        return System::getContainer()->get('contao.security.token_checker')->hasFrontendUser();
    }

    /**
     * Liefert das angemeldete Mitglied oder null, wenn kein Mitglied angemeldet
     * ist. Ersatz für "FE_USER_LOGGED_IN === true" plus
     * \FrontendUser::getInstance().
     */
    public static function getFrontendUser(): ?FrontendUser
    {
        if (!self::hasFrontendUser()) {
            return null;
        }

        $user = FrontendUser::getInstance();

        // Ohne Token im Speicher und ohne Benutzername in der Sitzung liefert
        // getInstance() ein leeres Objekt ohne ID; das gilt nicht als Mitglied.
        if (!$user instanceof FrontendUser || (int) $user->id < 1) {
            return null;
        }

        return $user;
    }

    /**
     * ID des angemeldeten Mitglieds, sonst 0.
     */
    public static function getMemberId(): int
    {
        $user = self::getFrontendUser();

        return null === $user ? 0 : (int) $user->id;
    }
}
