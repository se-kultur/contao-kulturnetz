<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

use SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Event\EventViewFactory;
use SeKultur\ContaoKulturnetzBundle\Service\Profile\ProfileCategories;

/**
 * Auswahlwerte der Mitgliederformulare an einer Stelle: Die Templates lesen die
 * Optionen von hier bzw. aus denselben Konstanten (Twig constant()), und
 * RecordSchemaRegistry prüft die gesendeten Werte gegen dieselben Listen. So
 * gibt es keine zweite Kopie im Code.
 *
 * - Sparten: ProfileCategories::CATEGORIES (auch Filter der Verzeichnisse),
 * - Zielgruppen der Veranstaltungen: AUDIENCES (bisher im Template ausgeschrieben),
 * - Bühnentypen der Locations: STAGE_TYPES (bisher im Template ausgeschrieben),
 * - Veranstaltungsformate der SE-KulturTage: EventViewFactory::SEKEVENT_FORMATS.
 */
final class FormChoices
{
    /**
     * Zielgruppen der Veranstaltungen in der Reihenfolge der Auswahl.
     */
    public const AUDIENCES = ['für Erwachsene', 'für Jugendliche', 'für Kinder', 'für Familien'];

    /**
     * Bühnentypen der Locations in der Reihenfolge der Auswahl.
     */
    public const STAGE_TYPES = [
        'keine Bühne vorhanden',
        'Bühne fest eingebaut',
        'flexible Podeste',
        'Zuschauerplätze als Tribühne',
        'ebenerdig',
        'Guckkastenbühne',
        'flexible Position',
        'sonstiges',
    ];

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return ProfileCategories::CATEGORIES;
    }

    /**
     * @return list<string>
     */
    public function audiences(): array
    {
        return self::AUDIENCES;
    }

    /**
     * @return list<string>
     */
    public function stageTypes(): array
    {
        return self::STAGE_TYPES;
    }

    /**
     * @return list<string>
     */
    public function sekEventFormats(): array
    {
        return EventViewFactory::SEKEVENT_FORMATS;
    }
}
