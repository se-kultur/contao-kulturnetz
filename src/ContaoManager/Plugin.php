<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use SeKultur\ContaoKulturnetzBundle\ContaoKulturnetzBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Contao-Manager-Plugin: meldet das Bundle an und lädt seine Routen.
 *
 * Routen entstehen ausschließlich als Attribute (#[Route]) an Controllern unter
 * src/Controller/. Das Verzeichnis darf leer sein oder fehlen (Git kennt keine
 * leeren Ordner); dann liefert das Plugin eine leere Sammlung, die sich beim
 * Anlegen des Verzeichnisses neu aufbaut.
 */
final class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoKulturnetzBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): RouteCollection
    {
        $path = \dirname(__DIR__).'/Controller';

        if (!is_dir($path)) {
            $collection = new RouteCollection();
            $collection->addResource(new FileExistenceResource($path));

            return $collection;
        }

        $loader = $resolver->resolve($path, 'attribute');

        if (false === $loader) {
            throw new \RuntimeException(\sprintf('Kein Routen-Loader für Attribut-Routen in "%s" verfügbar.', $path));
        }

        $collection = $loader->load($path, 'attribute');

        return $collection instanceof RouteCollection ? $collection : new RouteCollection();
    }
}
