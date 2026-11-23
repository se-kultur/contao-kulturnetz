<?php

declare(strict_types=1);

/*
 * Bootstrap der Bundle-Tests. Das Bundle bringt kein eigenes vendor-Verzeichnis
 * mit; die Abhängigkeiten stammen aus einer Contao-Installation (Website). Pfad
 * zu deren autoload.php in KULTURNETZ_AUTOLOAD, Standard: ../contao/vendor/autoload.php
 * neben dem Bundle-Verzeichnis. Der Code des Bundles kommt immer aus src/ dieses
 * Verzeichnisses, nicht aus einer installierten Kopie unter vendor/.
 */
$autoload = getenv('KULTURNETZ_AUTOLOAD') ?: __DIR__.'/../../contao/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, sprintf("autoload.php nicht gefunden: %s (KULTURNETZ_AUTOLOAD setzen)\n", $autoload));
    exit(1);
}

$loader = require $autoload;
$loader->addPsr4('SeKultur\\ContaoKulturnetzBundle\\', __DIR__.'/../src', true);
$loader->addPsr4('SeKultur\\ContaoKulturnetzBundle\\Tests\\', __DIR__);
