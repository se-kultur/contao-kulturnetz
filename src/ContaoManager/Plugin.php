<?php

namespace SeKultur\ContaoKulturnetzBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use SeKultur\ContaoKulturnetzBundle\ContaoKulturnetzBundle;

class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(ContaoKulturnetzBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }
}
