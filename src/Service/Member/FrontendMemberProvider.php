<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Member;

use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liefert das angemeldete Frontend-Mitglied über Symfony Security.
 *
 * Ersatz für Helper\RequestContext::getFrontendUser()/getMemberId() und damit für
 * FrontendUser::getInstance() und die Konstante FE_USER_LOGGED_IN aus Contao 4.
 *
 * Abgedeckte Fälle:
 *  - Frontend-Seiten, Fragment-Controller und Routen mit "_scope: frontend": Der
 *    Request läuft in der Contao-Frontend-Firewall, Security::getUser() liefert
 *    das Mitglied.
 *  - Frontend-Vorschau aus dem Backend "als Mitglied": Contao authentifiziert das
 *    gewählte Mitglied in derselben Firewall (FrontendPreviewAuthenticator), das
 *    Mitglied ist damit ebenfalls über Security::getUser() verfügbar, wie bisher
 *    über den Token-Checker.
 *  - Backend-Requests, Konsole und Cron: kein Mitglied (null bzw. 0). Bisher lieferte
 *    RequestContext dort unter Umständen das Mitglied der Sitzung; die Legacy-Module
 *    gaben im Backend aber ohnehin nur die Wildcard aus.
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

        // Ein Benutzerobjekt ohne ID gilt wie bisher nicht als Mitglied.
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
