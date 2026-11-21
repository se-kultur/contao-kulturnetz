<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\LegacyModules;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use SeKultur\ContaoKulturnetzBundle\Module\ModulePostingsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModulePostingsListAll;

/**
 * Befristet, wird vom Modulpaket gelöscht (Plan E15): registriert die
 * Legacy-Klassen der Beitrags-Feeds
 * in $GLOBALS['FE_MOD']['eProjekt'].
 *
 * Die Registrierung stand bisher in contao/config/config.php. Dort gibt es
 * keine FE_MOD-Einträge mehr, damit jede Modulgruppe für sich auf
 * Fragment-Controller umgestellt werden kann: Das Paket b-social legt die
 * Controller unter src/Controller/FrontendModule/Posting/ an und löscht im selben
 * Schritt diese Datei samt den Legacy-Klassen. Solange die Datei existiert,
 * gewinnt die Legacy-Klasse, weil der Hook nach dem GlobalsMapListener des
 * Cores (initializeSystem, Priorität 255) läuft und den Eintrag überschreibt.
 *
 * Die Prioritäten der drei Übergangs-Listener (Profile 30, Events 20,
 * Beiträge 10) erhalten die bisherige Reihenfolge der Modultypen in der
 * Auswahl des Backends. Typschlüssel (tl_module.type) und Kategorie sind
 * unverändert, eine Datenmigration ist nicht nötig.
 */
#[AsHook('initializeSystem', priority: 10)]
final class PostingLegacyModulesListener
{
    private const MODULES = [
        'ModulePostingsList' => ModulePostingsList::class,
        'ModulePostingsListAll' => ModulePostingsListAll::class,
    ];

    public function __invoke(): void
    {
        foreach (self::MODULES as $type => $class) {
            $GLOBALS['FE_MOD']['eProjekt'][$type] = $class;
        }
    }
}
