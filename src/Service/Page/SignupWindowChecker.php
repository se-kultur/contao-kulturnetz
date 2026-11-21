<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Page;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Psr\Clock\ClockInterface;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Steuert zentral die Sichtbarkeit der SE-KulturTage-Anmeldelinks (Navigation,
 * Fuß, Teaser-Box in der Eventliste).
 *
 * Gleiche Logik wie bisher Helper\AnmeldungHelper::linksOffen(); der Alias der
 * Anmeldeseite kommt aus der Konfiguration (pages.signup, Standard
 * "sekultur-event-anlegen"). Die Links sind sichtbar, solange
 *   - die Seite veröffentlicht ist,
 *   - ihr Startdatum erreicht (oder leer) ist,
 *   - das Stoppdatum der Links noch nicht erreicht ist.
 *
 * Stoppdatum der Links ist das Seitenfeld "sekAnmeldungLinkStop", ist es leer, das
 * reguläre Stoppdatum "stop" der Seite. So können die Links früher verschwinden,
 * als die Seite offline geht; die Seite bleibt für Nachzügler per Direktlink
 * erreichbar.
 */
final class SignupWindowChecker
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly KulturnetzConfig $config,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * True, wenn die Anmeldelinks jetzt sichtbar sein sollen.
     */
    public function isOpen(): bool
    {
        $this->framework->initialize();

        $page = $this->framework->getAdapter(PageModel::class)->findOneBy('alias', $this->config->getSignupPageAlias());

        if (!$page instanceof PageModel || !$page->published) {
            return false;
        }

        $now = $this->clock->now()->getTimestamp();

        // Startdatum gesetzt und noch nicht erreicht: Anmeldung noch nicht offen.
        if ($page->start && (int) $page->start > $now) {
            return false;
        }

        // Eigenes Link-Stoppdatum hat Vorrang, sonst das Stoppdatum der Seite.
        $linkStop = $page->sekAnmeldungLinkStop ?: $page->stop;

        if ($linkStop && (int) $linkStop <= $now) {
            return false;
        }

        return true;
    }
}
