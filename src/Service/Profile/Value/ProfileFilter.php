<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Profile\Value;

/**
 * Normalisierte Filterwerte eines Profil-Verzeichnisses (Suchformular).
 *
 * Ein Wert ist null, wenn der Parameter in der URL fehlt oder kein String war
 * (z. B. ?text[]=x). Die Werte sind roh, wie sie in der URL stehen; Templates
 * maskieren sie bei der Ausgabe.
 */
final class ProfileFilter
{
    public function __construct(
        public readonly string|null $category = null,
        public readonly string|null $text = null,
    ) {
    }

    /**
     * True, wenn der Kategorie-Filter greift: Wert vorhanden und nicht "Alle"
     * (wie ArtistsModel::findAllArtists).
     */
    public function hasCategory(): bool
    {
        return null !== $this->category && 'Alle' !== $this->category;
    }

    /**
     * True, wenn die Volltextsuche greift: Wert vorhanden und nicht leer.
     */
    public function hasText(): bool
    {
        return null !== $this->text && '' !== $this->text;
    }

    /**
     * Werte für das Suchformular im heutigen Format der Template-Variable
     * "search": nur gesetzte Schlüssel, Namen wie die URL-Parameter.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $values = [];

        if (null !== $this->category) {
            $values['kategorie'] = $this->category;
        }

        if (null !== $this->text) {
            $values['text'] = $this->text;
        }

        return $values;
    }
}
