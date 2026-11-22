<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

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
            ->arrayNode('postings')
                ->info('Beiträge der Profile.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_length')
                        ->info('Höchstlänge eines Beitrags in Zeichen, gezählt im Speicherformat (nach TextNormalizer::normalize()). Höchstens 16383, damit der Text auch aus lauter 4-Byte-Zeichen in die TEXT-Spalte (65.535 Byte) passt.')
                        ->defaultValue(10000)
                        ->min(11)
                        ->max(16383)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('records')
                ->info('Grenzen der Anlegen- und Bearbeiten-Formulare für Profile und Veranstaltungen.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_dates')->info('Höchstzahl der Terminzeilen einer Veranstaltung.')->defaultValue(100)->min(1)->end()
                    ->integerNode('max_prices')->info('Höchstzahl der Preiszeilen einer Veranstaltung.')->defaultValue(100)->min(1)->end()
                    ->arrayNode('create_rate_limit')
                        ->info('Neue Einträge je Mitglied über alle Typen zusammen (gleitendes Zeitfenster). Jede Anlage sendet eine Redaktionsmail.')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->integerNode('limit')->defaultValue(20)->min(1)->end()
                            ->scalarNode('interval')
                                ->info('Zeitfenster im Format von DateInterval::createFromDateString(), z. B. "1 hour" oder "30 minutes".')
                                ->defaultValue('1 hour')
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (mixed $value): bool => !self::isValidInterval($value))
                                    ->thenInvalid('Ungültiges Zeitfenster %s, erwartet wird z. B. "1 hour".')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('sekevents')
                ->info('SE-KulturTage.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('festival_month')
                        ->info('Monat der SE-KulturTage (1 bis 12); begrenzt die Terminauswahl im Anmeldeformular auf diesen Monat.')
                        ->defaultValue(9)
                        ->min(1)
                        ->max(12)
                    ->end()
                ->end()
            ->end()
            ->arrayNode('upload')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('max_file_size')
                        ->info('Größte Uploaddatei der Mitglieder in Byte (Standard 20 MB); 0 = keine eigene Grenze. Wirksam ist höchstens die PHP-Grenze (upload_max_filesize, post_max_size), das Formular nennt die wirksame Grenze.')
                        ->defaultValue(20 * 1024 * 1024)
                        ->min(0)
                    ->end()
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

        // Ratenlimit für neue Einträge (RecordCreationLimiter). Der Limiter wird hier
        // und nicht über framework.rate_limiter angelegt, weil seine Werte aus der
        // Bundle-Konfiguration stammen, die beim Vorbelegen der Framework-Konfiguration
        // noch nicht verarbeitet ist. Aufbau wie die Limiter des Contao-Cores
        // (Speicher im Cache-Pool cache.rate_limiter, Sperre über lock.factory).
        $rateLimit = $config['records']['create_rate_limit'];

        $container->services()
            ->set('kulturnetz.rate_limiter.storage', CacheStorage::class)
                ->args([service('cache.rate_limiter')])
            ->set('kulturnetz.rate_limiter.record_create', RateLimiterFactory::class)
                ->args([
                    [
                        'id' => 'kulturnetz_record_create',
                        'policy' => 'sliding_window',
                        'limit' => $rateLimit['limit'],
                        'interval' => $rateLimit['interval'],
                    ],
                    service('kulturnetz.rate_limiter.storage'),
                    service('lock.factory')->nullOnInvalid(),
                ])
        ;
    }

    /**
     * Prüft ein Zeitfenster so, wie der RateLimiterFactory es später liest.
     */
    private static function isValidInterval(mixed $value): bool
    {
        if (!\is_string($value) || '' === trim($value)) {
            return false;
        }

        try {
            $interval = @\DateInterval::createFromDateString($value);
        } catch (\Throwable) {
            return false;
        }

        if (!$interval instanceof \DateInterval) {
            return false;
        }

        $now = new \DateTimeImmutable('@0');

        return $now->add($interval)->getTimestamp() > 0;
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
