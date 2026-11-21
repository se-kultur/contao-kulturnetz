<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\LegacyModules;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleArtist;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistAdd;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistEdit;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistsListAll;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleHost;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleHostAdd;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleHostEdit;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleHostsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleHostsListAll;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleLocation;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationAdd;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationEdit;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationsListAll;

/**
 * Befristet, wird vom Modulpaket gelöscht (Plan E15): registriert die
 * Legacy-Klassen der Profilmodule (Künstler:innen, Veranstalter:innen, Locations)
 * in $GLOBALS['FE_MOD']['eProjekt'].
 *
 * Die Registrierung stand bisher in contao/config/config.php. Dort gibt es
 * keine FE_MOD-Einträge mehr, damit jede Modulgruppe für sich auf
 * Fragment-Controller umgestellt werden kann: Das Paket b-module-profile legt die
 * Controller unter src/Controller/FrontendModule/Profile/ an und löscht im selben
 * Schritt diese Datei samt den Legacy-Klassen. Solange die Datei existiert,
 * gewinnt die Legacy-Klasse, weil der Hook nach dem GlobalsMapListener des
 * Cores (initializeSystem, Priorität 255) läuft und den Eintrag überschreibt.
 *
 * Die Prioritäten der drei Übergangs-Listener (Profile 30, Events 20,
 * Beiträge 10) erhalten die bisherige Reihenfolge der Modultypen in der
 * Auswahl des Backends. Typschlüssel (tl_module.type) und Kategorie sind
 * unverändert, eine Datenmigration ist nicht nötig.
 */
#[AsHook('initializeSystem', priority: 30)]
final class ProfileLegacyModulesListener
{
    private const MODULES = [
        'ModuleArtist' => ModuleArtist::class,
        'ModuleArtistAdd' => ModuleArtistAdd::class,
        'ModuleArtistEdit' => ModuleArtistEdit::class,
        'ModuleArtistsList' => ModuleArtistsList::class,
        'ModuleArtistsListAll' => ModuleArtistsListAll::class,
        'ModuleLocation' => ModuleLocation::class,
        'ModuleLocationAdd' => ModuleLocationAdd::class,
        'ModuleLocationEdit' => ModuleLocationEdit::class,
        'ModuleLocationsList' => ModuleLocationsList::class,
        'ModuleLocationsListAll' => ModuleLocationsListAll::class,
        'ModuleHost' => ModuleHost::class,
        'ModuleHostAdd' => ModuleHostAdd::class,
        'ModuleHostEdit' => ModuleHostEdit::class,
        'ModuleHostsList' => ModuleHostsList::class,
        'ModuleHostsListAll' => ModuleHostsListAll::class,
    ];

    public function __invoke(): void
    {
        foreach (self::MODULES as $type => $class) {
            $GLOBALS['FE_MOD']['eProjekt'][$type] = $class;
        }
    }
}
