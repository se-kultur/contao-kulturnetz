<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event;

/**
 * Die beiden Veranstaltungsarten des KulturNetzes.
 *
 * Der Wert entspricht dem Typschlüssel der Bundle-Konfiguration "kulturnetz"
 * (pages.types.<typ>, siehe Config\KulturnetzConfig) und dem Tabellennamen ohne
 * Präfix "tl_". Der Unterschied zwischen den Arten ist die Speicherung der
 * Termine: "events" speichert das volle Datum mit Jahr und eine freie Uhrzeit,
 * "sekevents" (SE-KulturTage) nur Tag und Monat, das Jahr ist das Laufzeitjahr.
 */
enum EventType: string
{
    case Events = 'events';
    case SekEvents = 'sekevents';

    /**
     * Tabelle mit den Datensätzen dieser Art.
     */
    public function table(): string
    {
        return 'tl_'.$this->value;
    }

    /**
     * Schlüssel für die Seiten- und Limit-Konfiguration (KulturnetzConfig).
     */
    public function pageKey(): string
    {
        return $this->value;
    }

    /**
     * True, wenn die Termine ohne Jahr gespeichert werden (Datumsstrategie
     * "ohne Jahr", Laufzeitjahr wird ergänzt), false bei der Strategie "mit Jahr".
     */
    public function datesStoredWithoutYear(): bool
    {
        return self::SekEvents === $this;
    }

    /**
     * Filterschlüssel, die das Suchformular dieser Art kennt.
     *
     * @return list<string>
     */
    public function searchKeys(): array
    {
        return match ($this) {
            self::Events => ['datum', 'kategorie', 'text'],
            self::SekEvents => ['datum', 'kategorie', 'format', 'text'],
        };
    }
}
