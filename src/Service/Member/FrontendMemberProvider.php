<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Member;

use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liefert das angemeldete Frontend-Mitglied über Symfony Security.
 *
 * Einzige Stelle des Bundles, die das Mitglied bestimmt; es gibt weder
 * FrontendUser::getInstance() noch die Konstante FE_USER_LOGGED_IN (beides
 * entfällt in Contao 6).
 *
 * Abgedeckte Fälle:
 *  - Frontend-Seiten, Fragment-Controller und Routen mit "_scope: frontend": Der
 *    Request läuft in der Contao-Frontend-Firewall, Security::getUser() liefert
 *    das Mitglied.
 *  - Frontend-Vorschau aus dem Backend "als Mitglied": Contao authentifiziert das
 *    gewählte Mitglied in derselben Firewall (FrontendPreviewAuthenticator), das
 *    Mitglied ist damit ebenfalls über Security::getUser() verfügbar, wie bisher
 *    über den Token-Checker.
 *  - Backend-Requests, Konsole und Cron: kein Mitglied (null bzw. 0).
 */
final class FrontendMemberProvider
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * Das angemeldete Mitglied oder null, wenn niemand bzw. kein Mitglied
     * angemeldet ist.
     */
    public function getMember(): FrontendUser|null
    {
        $user = $this->security->getUser();

        // Ein Benutzerobjekt ohne ID gilt nicht als Mitglied.
        if (!$user instanceof FrontendUser || (int) $user->id < 1) {
            return null;
        }

        return $user;
    }

    /**
     * ID des angemeldeten Mitglieds, sonst 0 (wie bisher RequestContext::getMemberId()).
     */
    public function getMemberId(): int
    {
        return (int) $this->getMember()?->id;
    }

    /**
     * True, wenn ein Mitglied angemeldet ist.
     */
    public function hasMember(): bool
    {
        return null !== $this->getMember();
    }
}
