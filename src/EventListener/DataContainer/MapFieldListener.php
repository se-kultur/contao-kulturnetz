<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;
use Symfony\Component\Asset\Packages;
use Twig\Environment;

/**
 * Feld "Position auf Karte" in den Masken der Profile und Veranstaltungen
 * (bisher latlonmap() in den fünf DCA-Dateien).
 *
 * Gibt die Karte mit Schaltfläche "Adresse auf Karte bestimmen" aus. Leaflet
 * kommt lokal aus dem Theme-Build (Asset-Paket aus der Bundle-Konfiguration,
 * Standard se_kultur_theme) statt von unpkg.com, die Logik steht in
 * public/js/backend-map.js statt in einem Inline-Skript.
 *
 * Verhalten wie unter Contao 4.13: Mit gespeicherter Position (Breite > 0)
 * zeigt die Karte diese mit Zoom 13, sonst die Standardansicht (Bad Segeberg,
 * Zoom 9). Die Adresssuche liest die Felder Straße, Postleitzahl und Ort aus der
 * Konfiguration je Tabelle: Das DCA-Feld "map" nennt sie in eval.addressFields (in
 * den Profilen adresse, plz, ort; in den Veranstaltungen location_adresse,
 * location_plz, location_ort). Unter 4.13 las die Suche in allen Tabellen die
 * Veranstaltungsfelder und fand in den Profilen deshalb nichts.
 */
#[AsCallback(table: 'tl_artists', target: 'fields.map.input_field')]
#[AsCallback(table: 'tl_hosts', target: 'fields.map.input_field')]
#[AsCallback(table: 'tl_locations', target: 'fields.map.input_field')]
#[AsCallback(table: 'tl_events', target: 'fields.map.input_field')]
#[AsCallback(table: 'tl_sekevents', target: 'fields.map.input_field')]
final class MapFieldListener
{
    private const DEFAULT_CENTER = [53.5437641, 10.0099133];

    private const DEFAULT_ZOOM = 9;

    private const POSITION_ZOOM = 13;

    public function __construct(
        private readonly Environment $twig,
        private readonly Packages $packages,
        private readonly KulturnetzConfig $config,
    ) {
    }

    public function __invoke(DataContainer $dc): string
    {
        $record = $dc->getCurrentRecord() ?? [];
        $lat = (float) ($record['lat'] ?? 0);
        $lon = (float) ($record['lon'] ?? 0);

        // Bei "Mehrere bearbeiten" tragen alle Eingaben das Suffix "_<id>".
        $suffix = substr((string) $dc->inputName, \strlen((string) $dc->field));
        $assetPackage = $this->config->getAssetPackage();

        // Adressfelder dieser Tabelle (Straße, Postleitzahl, Ort), siehe eval.addressFields
        $addressFields = $GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field]['eval']['addressFields'] ?? [];

        if (3 !== \count($addressFields)) {
            throw new \LogicException(\sprintf('Das Feld "%s" in "%s" braucht eval.addressFields mit Straße, Postleitzahl und Ort.', $dc->field, $dc->table));
        }

        return $this->twig->render('@Contao/backend/kulturnetz/map_field.html.twig', [
            'suffix' => $suffix,
            'leaflet_css' => $this->packages->getUrl('vendor/leaflet/leaflet.css', $assetPackage),
            'config' => [
                'leaflet' => $this->packages->getUrl('vendor/leaflet/leaflet.js', $assetPackage),
                'hasPosition' => $lat > 0,
                'lat' => $lat,
                'lon' => $lon,
                'defaultCenter' => self::DEFAULT_CENTER,
                'defaultZoom' => self::DEFAULT_ZOOM,
                'positionZoom' => self::POSITION_ZOOM,
                'latField' => 'ctrl_lat'.$suffix,
                'lonField' => 'ctrl_lon'.$suffix,
                'locateButton' => 'getLocationData'.$suffix,
                'addressFields' => array_map(static fn (string $field): string => 'ctrl_'.$field.$suffix, array_values($addressFields)),
            ],
        ]);
    }
}
