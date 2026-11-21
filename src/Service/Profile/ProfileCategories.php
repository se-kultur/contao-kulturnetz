<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Profile;

/**
 * Die Kategorien (Sparten) der Suchformulare von Künstler*innen- und
 * Veranstaltungsverzeichnissen.
 *
 * Bisher stand die Liste dreifach in den Templates (mod_artists_list,
 * mod_events_list_all, mod_sekevents_list_all); hier ist die eine Quelle. Die
 * Werte sind zugleich die Suchbegriffe für "sparten" und die sichtbaren Texte.
 */
final class ProfileCategories
{
    /**
     * Wert der Option "Alle Kategorien"; mit diesem Wert greift kein Filter.
     */
    public const ALL = 'Alle';

    /**
     * Beschriftung der Option "Alle Kategorien".
     */
    public const ALL_LABEL = 'Alle Kategorien';

    /**
     * Kategorien in der Reihenfolge der Auswahlliste.
     */
    public const CATEGORIES = [
        'Literatur',
        'Musik',
        'Theater',
        'Tanz',
        'Bildende Kunst',
        'Film',
        'Fotografie',
        'Multimedial',
        'Performance',
        'Sonstiges',
    ];

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Optionen der Auswahlliste inklusive "Alle Kategorien" als Wert => Beschriftung.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return [self::ALL => self::ALL_LABEL, ...array_combine(self::CATEGORIES, self::CATEGORIES)];
    }
}
