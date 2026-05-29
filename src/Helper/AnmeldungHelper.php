<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Helper;

use Contao\PageModel;

/**
 * Steuert zentral die Sichtbarkeit der SE-KulturTage-Anmeldelinks.
 *
 * Die Anmeldelinks (Navigation, Footer, Teaser-Box in der Eventliste) verweisen
 * auf die Seite "sekultur-event-anlegen" und werden eingeblendet, solange die
 * Anmeldephase läuft. Maßgeblich ist:
 *   - die Seite ist veröffentlicht,
 *   - ihr Startdatum ist erreicht (oder leer),
 *   - das Stoppdatum der Links ist noch nicht erreicht.
 *
 * Das Stoppdatum der Links ist primär das eigene Feld "sekAnmeldungLinkStop".
 * Ist es leer, gilt als Fallback das reguläre Stoppdatum ("stop") der Seite.
 * Dadurch können die Links früher verschwinden, als die Seite selbst offline
 * geht – die Seite bleibt für Nachzügler per Direktlink erreichbar.
 *
 * Diese Logik lebt bewusst an genau einer Stelle, damit alle Anzeigeorte
 * dieselbe Wahrheit nutzen.
 */
class AnmeldungHelper
{
    /**
     * Alias der Seite mit dem Anmeldeformular für die SE-KulturTage.
     */
    public const EVENT_ANLEGEN_ALIAS = 'sekultur-event-anlegen';

    /**
     * Gibt zurück, ob die Anmeldelinks aktuell sichtbar sein sollen.
     */
    public static function linksOffen(): bool
    {
        $page = PageModel::findByAlias(self::EVENT_ANLEGEN_ALIAS);

        if (null === $page || !$page->published) {
            return false;
        }

        // Startdatum gesetzt und noch nicht erreicht -> Anmeldung noch nicht offen.
        if ($page->start && $page->start > time()) {
            return false;
        }

        // Eigenes Link-Stoppdatum hat Vorrang; sonst das System-Stoppdatum der Seite.
        $linkStop = $page->sekAnmeldungLinkStop ?: $page->stop;

        if ($linkStop && $linkStop <= time()) {
            return false;
        }

        return true;
    }
}
