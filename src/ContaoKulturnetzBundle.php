<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Bundle-Klasse des KulturNetz-Bundles.
 *
 * Aufbau nach Contao-5-Standard: Die Klasse liegt in src/, AbstractBundle
 * meldet deshalb die Bundle-Wurzel als Pfad. Contao findet dort contao/
 * (DCA, Sprachdateien, Templates, config.php), Symfony lädt config/services.yaml
 * und jede Datei config/prepend/*.yaml als vorangestellte Konfiguration anderer
 * Bundles (z. B. benannte Bildgrößen für den Schlüssel "contao").
 *
 * Die semantische Konfiguration "kulturnetz" ersetzt Werte, die bisher fest in
 * Modulen und Templates standen. Die Standardwerte sind genau diese bisherigen
 * Werte; ein Projekt muss nichts konfigurieren, damit sich nichts ändert.
 */
final class ContaoKulturnetzBundle extends AbstractBundle
{
    protected string $extensionAlias = 'kulturnetz';

    /**
     * Seiten-Aliase je Typ: Detail-, Bearbeiten-, Anlegen- und Listenseite.
     */
    private const TYPE_PAGES = [
        'artists' => ['detail' => 'detailansicht-kuenstlerprofil', 'edit' => 'kuenstlerprofil-bearbeiten', 'add' => 'kuenstlerprofil-anlegen', 'list' => 'kuenstler-innen'],
        'hosts' => ['detail' => 'detailansicht-veranstalterprofil', 'edit' => 'veranstalterprofil-bearbeiten', 'add' => 'veranstalterprofil-anlegen', 'list' => 'veranstalter-innen'],
        'locations' => ['detail' => 'detailansicht-locationprofil', 'edit' => 'locationprofil-bearbeiten', 'add' => 'locationprofil-anlegen', 'list' => 'locations'],
        'events' => ['detail' => 'detailansicht-event', 'edit' => 'event-bearbeiten', 'add' => 'event-anlegen', 'list' => 'events'],
        'sekevents' => ['detail' => 'detailansicht-sekultur-event', 'edit' => 'sekultur-event-bearbeiten', 'add' => 'sekultur-event-anlegen', 'list' => 'se-kultur-events'],
    ];

    /**
     * Musterprofile, die in den Verzeichnissen zuerst stehen.
     */
    private const SAMPLE_PROFILES = ['artists' => 17, 'hosts' => 7, 'locations' => 16];

    /**
     * Zuordnung SE-KulturTage-Veranstaltung (ID) zu KulturRoute (Nummer), bisher
     * doppelt in mod_sekevent.html5 und mod_sekevents_list_all.html5.
     */
    private const KULTURROUTEN = [83 => 1, 65 => 1, 82 => 1, 33 => 1, 84 => 2, 48 => 2, 97 => 2, 6 => 2, 11 => 3, 67 => 3, 80 => 3];

    public function configure(DefinitionConfigurator $definition): void
    {
        $typePages = $definition->rootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('pages')
                    ->info('Seiten-Aliase, auf die Module, Templates und Backend-Links verweisen.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('own_profiles')->defaultValue('eigene-profile')->cannotBeEmpty()->end()
                        ->scalarNode('own_events')->info('Bisher Seiten-ID 40 (Backend-Vorschau als Mitglied).')->defaultValue('veranstaltungen')->cannotBeEmpty()->end()
                        ->scalarNode('terms')->defaultValue('teilnahmebedingungen')->cannotBeEmpty()->end()
                        ->scalarNode('signup')->info('Anmeldeseite der SE-KulturTage; steuert die Sichtbarkeit der Anmeldelinks.')->defaultValue('sekultur-event-anlegen')->cannotBeEmpty()->end()
                        ->arrayNode('types')
                            ->addDefaultsIfNotSet()
                            ->children()
        ;

        foreach (self::TYPE_PAGES as $type => $pages) {
            $typePages = $typePages
                ->arrayNode($type)
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('detail')->defaultValue($pages['detail'])->cannotBeEmpty()->end()
                        ->scalarNode('edit')->defaultValue($pages['edit'])->cannotBeEmpty()->end()
                        ->scalarNode('add')->defaultValue($pages['add'])->cannotBeEmpty()->end()
                        ->scalarNode('list')->defaultValue($pages['list'])->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ;
        }

        $root = $typePages
                            ->end()
                        ->end()
                    ->end()
                ->end()
        ;

        $root
            ->arrayNode('teaser')
                ->info('Übersichtsseite mit gekürzten Verzeichnissen und Beitrags-Feed (bisher Seiten-ID 7).')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('page')->defaultValue('se-kulturnetz')->cannotBeEmpty()->end()
                    ->integerNode('profile_limit')->defaultValue(3)->min(1)->end()
                    ->integerNode('posting_limit')->defaultValue(5)->min(1)->end()
                ->end()
            ->end()
            ->arrayNode('limits')
                ->info('Höchstzahlen außerhalb der Übersichtsseite; 0 heißt unbegrenzt, wo angegeben.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('profile_directory')->defaultValue(100)->min(1)->end()
                    ->integerNode('posting_feed')->defaultValue(50)->min(1)->end()
                    ->integerNode('member_feed')->defaultValue(25)->min(1)->end()
                    ->integerNode('profile_postings')->defaultValue(25)->min(1)->end()
                    ->integerNode('event_directory')->info('0 = unbegrenzt')->defaultValue(0)->min(0)->end()
                    ->integerNode('sekevent_directory')->info('0 = unbegrenzt')->defaultValue(200)->min(0)->end()
                ->end()
            ->end()
            ->arrayNode('sample_profiles')
                ->info('IDs der Musterprofile je Profiltyp.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('artists')->defaultValue(self::SAMPLE_PROFILES['artists'])->min(0)->end()
                    ->integerNode('hosts')->defaultValue(self::SAMPLE_PROFILES['hosts'])->min(0)->end()
                    ->integerNode('locations')->defaultValue(self::SAMPLE_PROFILES['locations'])->min(0)->end()
                ->end()
            ->end()
            ->arrayNode('kulturrouten')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('url')->info('Ziel des KulturRouten-Hinweises, der Anker #route<Nummer> wird angehängt.')->defaultValue('https://se-kultur.de/kulturrouten.html')->cannotBeEmpty()->end()
                    ->arrayNode('events')
                        ->info('Veranstaltungs-ID (tl_sekevents) => Nummer der KulturRoute.')
                        ->useAttributeAsKey('id')
                        ->integerPrototype()->min(1)->end()
                        ->defaultValue(self::KULTURROUTEN)
                    ->end()
                ->end()
            ->end()
            ->scalarNode('asset_package')
                ->info('Symfony-Asset-Paket mit den Theme-Dateien der Website.')
                ->defaultValue('se_kultur_theme')
                ->cannotBeEmpty()
            ->end()
            ->arrayNode('mail')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('sender_address')->defaultValue('noreply@se-kultur.de')->cannotBeEmpty()->end()
                    ->scalarNode('sender_name')->defaultValue('SE-Kultur.de')->end()
                    ->scalarNode('editorial_recipient')
                        ->info('Empfänger der Redaktionsmails; Standard aus der Umgebungsvariable KULTURNETZ_MAIL_EDITORIAL_RECIPIENT, sonst info@se-kultur.de.')
                        ->defaultValue('%env(KULTURNETZ_MAIL_EDITORIAL_RECIPIENT)%')
                        ->cannotBeEmpty()
                    ->end()
                    ->scalarNode('editorial_recipient_name')->defaultValue('SE-Kultur.de Admin')->end()
                    ->scalarNode('editorial_bcc')
                        ->info('Blindkopie der Redaktionsmails (mehrere Adressen mit Komma); Standard aus KULTURNETZ_MAIL_EDITORIAL_BCC, sonst keine.')
                        ->defaultValue('%env(KULTURNETZ_MAIL_EDITORIAL_BCC)%')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('upload')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_file_size')->info('Größte Uploaddatei in Byte; 0 = keine eigene Grenze (es gelten die PHP-Grenzen).')->defaultValue(0)->min(0)->end()
                    ->arrayNode('mime_types')
                        ->scalarPrototype()->cannotBeEmpty()->end()
                        ->defaultValue(['image/jpeg', 'image/png'])
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Standardwerte der Umgebungsvariablen: Redaktionsempfänger wie bisher,
        // Blindkopie leer. Persönliche Adressen gehören nur in die .env.local.
        $container->parameters()
            ->set('env(KULTURNETZ_MAIL_EDITORIAL_RECIPIENT)', 'info@se-kultur.de')
            ->set('env(KULTURNETZ_MAIL_EDITORIAL_BCC)', '')
            ->set('kulturnetz.config', $config)
            ->set('kulturnetz.asset_package', $config['asset_package'])
        ;

        $container->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Der Ordner darf fehlen; fileExists() registriert die Prüfung als
        // Ressource, damit ein später angelegter Ordner den Container neu baut.
        if ($builder->fileExists($this->getPath().'/config/prepend', false)) {
            $container->import('../config/prepend/*.yaml');
        }
    }
}
