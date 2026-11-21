<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Gemeinsame Basis der zehn Veranstaltungsmodule. Die konkreten Controller (je
 * Veranstaltungsart und Modulart ein dünner Controller mit Attribut) legen nur die
 * Art fest, Verhalten und Daten liefern die Basisklassen der vier Modulgruppen:
 * Detail, eigene Liste, Verzeichnis, Formular (Anlegen und Bearbeiten).
 *
 * Die Modultypen (tl_module.type) und die Kategorie sind unverändert, damit keine
 * Datenmigration nötig ist.
 */
abstract class AbstractEventController extends AbstractFrontendModuleController
{
    /**
     * Art der Veranstaltung, die das Modul darstellt.
     */
    abstract protected function getEventType(): EventType;
}
