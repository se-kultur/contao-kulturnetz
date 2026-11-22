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
 *
 * Neue SE-KulturTage-Events nimmt der Endpunkt nur an, solange die Anmeldeseite
 * selbst erreichbar ist (acceptsNewEvents(): veröffentlicht, Start erreicht,
 * reguläres Stoppdatum der Seite nicht erreicht). Das Link-Stoppdatum gilt dafür
 * nicht, damit Nachzügler mit dem Direktlink sich weiter anmelden können.
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
        $page = $this->findStartedSignupPage();

        if (null === $page) {
            return false;
        }

        // Eigenes Link-Stoppdatum hat Vorrang, sonst das Stoppdatum der Seite.
        return !$this->hasStopped($page->sekAnmeldungLinkStop ?: $page->stop);
    }

    /**
     * True, solange neue SE-KulturTage-Events angelegt werden dürfen: Die
     * Anmeldeseite ist veröffentlicht, ihr Startdatum erreicht und ihr reguläres
     * Stoppdatum nicht erreicht (wie die Sichtbarkeit der Seite selbst).
     */
    public function acceptsNewEvents(): bool
    {
        $page = $this->findStartedSignupPage();

        return null !== $page && !$this->hasStopped($page->stop);
    }

    /**
     * Veröffentlichte Anmeldeseite mit erreichtem (oder leerem) Startdatum, sonst null.
     */
    private function findStartedSignupPage(): PageModel|null
    {
        $this->framework->initialize();

        $page = $this->framework->getAdapter(PageModel::class)->findOneBy('alias', $this->config->getSignupPageAlias());

        if (!$page instanceof PageModel || !$page->published) {
            return null;
        }

        // Startdatum gesetzt und noch nicht erreicht: Anmeldung noch nicht offen.
        if ($page->start && (int) $page->start > $this->clock->now()->getTimestamp()) {
            return null;
        }

        return $page;
    }

    private function hasStopped(mixed $stop): bool
    {
        return $stop && (int) $stop <= $this->clock->now()->getTimestamp();
    }
}
