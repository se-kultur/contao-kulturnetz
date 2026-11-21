<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;

/**
 * Spaltenauswahl der Listen "Veranstaltungen" und "Events" nach der Sortierung.
 *
 * Ohne gewählte Sortierung zeigt die Liste Eventname, Mitglied, E-Mail,
 * Erstellt am und Bearbeitet am. Hat die Redaktion eine andere Sortierung als
 * "Eventname" gewählt, steht nach der E-Mail nur die Spalte, nach der sortiert
 * wird (z. B. "Location Ort"). Der Rückruf läuft beim Laden des
 * Datencontainers, vor dem Aufbau der Liste, und liest die Sortierung aus der
 * Backend-Sitzung; der Wechsel der Sortierung lädt die Liste neu, die Spalten
 * folgen dann beim nächsten Aufruf.
 *
 * Die Spalten stehen im DCA ($GLOBALS['TL_DCA']); eine Änderung zur Laufzeit
 * geht in Contao 5.7 und 6.0 nur dort (so auch die Listener des Contao-Kerns).
 */
#[AsCallback(table: 'tl_events', target: 'config.onload')]
#[AsCallback(table: 'tl_sekevents', target: 'config.onload')]
final class EventPanelListener
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function __invoke(DataContainer $dc): void
    {
        $table = $dc->table;
        $sorting = $this->getSessionSorting($table);

        if (null !== $sorting && 'name' !== $sorting) {
            $GLOBALS['TL_DCA'][$table]['list']['label']['fields'] = ['name', 'member_id', 'email', $sorting];
        }
    }

    /**
     * Gewählte Sortierung aus der Backend-Sitzung, ohne Richtungsangabe.
     */
    private function getSessionSorting(string $table): string|null
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        $bag = $request->getSession()->getBag('contao_backend');

        if (!$bag instanceof AttributeBagInterface) {
            return null;
        }

        $sorting = $bag->all()['sorting'][$table] ?? null;

        if (!\is_string($sorting) || '' === $sorting) {
            return null;
        }

        return preg_replace('/\s+(ASC|DESC)$/i', '', $sorting);
    }
}
