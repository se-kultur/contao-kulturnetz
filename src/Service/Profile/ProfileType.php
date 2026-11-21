<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Profile;

use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Die drei Profilarten des KulturNetzes.
 *
 * Der Wert entspricht dem Typschlüssel der Bundle-Konfiguration "kulturnetz"
 * (pages.types.<typ>, sample_profiles.<typ>, siehe Config\KulturnetzConfig) und
 * dem Tabellennamen ohne Präfix "tl_". Controller, Twig-Templates, Endpunkte und
 * DCA-Callbacks lesen Tabelle, Beschriftung und Verhalten aus dieser einen Quelle.
 */
enum ProfileType: string
{
    case Artists = 'artists';
    case Hosts = 'hosts';
    case Locations = 'locations';

    /**
     * Liefert die Profilart zu einem Typschlüssel ("artists") oder Tabellennamen
     * ("tl_artists"), sonst null. Das bisherige Frontend-JS sendet bei Beiträgen
     * und beim Folgen den Tabellennamen, die Konfiguration nutzt den Typschlüssel.
     */
    public static function tryFromTypeOrTable(string $value): self|null
    {
        return self::tryFrom(str_starts_with($value, 'tl_') ? substr($value, 3) : $value);
    }

    /**
     * Tabelle mit den Profilen dieser Art.
     */
    public function table(): string
    {
        return 'tl_'.$this->value;
    }

    /**
     * Schlüssel für die Seiten-Konfiguration (KulturnetzConfig::getDetailPageAlias() usw.).
     */
    public function pageKey(): string
    {
        return $this->value;
    }

    /**
     * Beschriftung des Profils, wie sie die Frontend-Templates verwenden
     * (z. B. "Künstler*innenprofil").
     */
    public function label(): string
    {
        return match ($this) {
            self::Artists => 'Künstler*innenprofil',
            self::Hosts => 'Veranstalter*innenprofil',
            self::Locations => 'Locationprofil',
        };
    }

    /**
     * ID des Musterprofils, das im Verzeichnis zuerst und mit dem Hinweis
     * "Musterprofil" erscheint (Konfiguration sample_profiles.<typ>).
     */
    public function sampleProfileId(KulturnetzConfig $config): int
    {
        return $config->getSampleProfileId($this->value);
    }

    /**
     * Nur das Künstler*innen-Verzeichnis hat einen Kategorie-Filter (Sparten).
     */
    public function hasCategoryFilter(): bool
    {
        return self::Artists === $this;
    }

    /**
     * Filterschlüssel, die das Suchformular des Verzeichnisses dieser Art kennt.
     *
     * @return list<string>
     */
    public function searchKeys(): array
    {
        return $this->hasCategoryFilter() ? ['kategorie', 'text'] : ['text'];
    }
}
