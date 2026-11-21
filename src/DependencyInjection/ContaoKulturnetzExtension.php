<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Lädt die Service-Konfiguration des Bundles.
 *
 * Symfony findet die Klasse per Konvention zur Bundle-Klasse
 * ContaoKulturnetzBundle (Namespace DependencyInjection, Suffix Extension).
 */
class ContaoKulturnetzExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}
