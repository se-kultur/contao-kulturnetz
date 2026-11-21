<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\EventListener\LegacyModules;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleEvent;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleEventAdd;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleEventEdit;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleEventsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleEventsListAll;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEvent;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventAdd;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventEdit;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventsList;
use SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventsListAll;

/**
 * Befristet, wird vom Modulpaket gelöscht (Plan E15): registriert die
 * Legacy-Klassen der Veranstaltungsmodule (Veranstaltungen und SE-KulturTage-Events)
 * in $GLOBALS['FE_MOD']['eProjekt'].
 *
 * Die Registrierung stand bisher in contao/config/config.php. Dort gibt es
 * keine FE_MOD-Einträge mehr, damit jede Modulgruppe für sich auf
 * Fragment-Controller umgestellt werden kann: Das Paket b-module-events legt die
 * Controller unter src/Controller/FrontendModule/Event/ an und löscht im selben
 * Schritt diese Datei samt den Legacy-Klassen. Solange die Datei existiert,
 * gewinnt die Legacy-Klasse, weil der Hook nach dem GlobalsMapListener des
 * Cores (initializeSystem, Priorität 255) läuft und den Eintrag überschreibt.
 *
 * Die Prioritäten der drei Übergangs-Listener (Profile 30, Events 20,
 * Beiträge 10) erhalten die bisherige Reihenfolge der Modultypen in der
 * Auswahl des Backends. Typschlüssel (tl_module.type) und Kategorie sind
 * unverändert, eine Datenmigration ist nicht nötig.
 */
#[AsHook('initializeSystem', priority: 20)]
final class EventLegacyModulesListener
{
    private const MODULES = [
        'ModuleEvent' => ModuleEvent::class,
        'ModuleEventAdd' => ModuleEventAdd::class,
        'ModuleEventEdit' => ModuleEventEdit::class,
        'ModuleEventsList' => ModuleEventsList::class,
        'ModuleEventsListAll' => ModuleEventsListAll::class,
        'ModuleSekEvent' => ModuleSekEvent::class,
        'ModuleSekEventAdd' => ModuleSekEventAdd::class,
        'ModuleSekEventEdit' => ModuleSekEventEdit::class,
        'ModuleSekEventsList' => ModuleSekEventsList::class,
        'ModuleSekEventsListAll' => ModuleSekEventsListAll::class,
    ];

    public function __invoke(): void
    {
        foreach (self::MODULES as $type => $class) {
            $GLOBALS['FE_MOD']['eProjekt'][$type] = $class;
        }
    }
}
