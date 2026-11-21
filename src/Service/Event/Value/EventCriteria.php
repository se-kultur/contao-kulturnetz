<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Event\Value;

use SeKultur\ContaoKulturnetzBundle\Service\Event\EventType;

/**
 * Normalisierte Suchfilter der Veranstaltungsübersicht.
 *
 * Die Werte stammen aus dem Query-String und sind ungeprüft. fromQuery() übernimmt
 * deshalb nur die Schlüssel, die die jeweilige Veranstaltungsart kennt, und nur
 * Zeichenketten; Arrays und andere Typen werden verworfen (sonst Fehler bei
 * ?text[]=x). Die Zeichenketten bleiben unverändert, auch wenn sie leer sind,
 * damit das Formular sie wieder anzeigen kann (toArray()).
 */
final readonly class EventCriteria
{
    public const ALL = 'Alle';

    public function __construct(
        public string|null $datum = null,
        public string|null $kategorie = null,
        public string|null $format = null,
        public string|null $text = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $query Rohwerte, z. B. $request->query->all()
     */
    public static function fromQuery(EventType $type, array $query): self
    {
        $values = [];

        foreach ($type->searchKeys() as $key) {
            if (isset($query[$key]) && \is_string($query[$key])) {
                $values[$key] = $query[$key];
            }
        }

        return new self(
            $values['datum'] ?? null,
            $values['kategorie'] ?? null,
            $values['format'] ?? null,
            $values['text'] ?? null,
        );
    }

    /**
     * Gesetzte Filter als Array (Schlüssel nur, wenn gesetzt), wie es das
     * Formular für Werte, Zurücksetzen-Link und Orts-Badges braucht.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'datum' => $this->datum,
            'kategorie' => $this->kategorie,
            'format' => $this->format,
            'text' => $this->text,
        ], static fn (string|null $value): bool => null !== $value);
    }

    /**
     * Dieselben Filter ohne Textsuche (für die Orts-Badges: der Ortsfilter wird
     * von einem Badge überschrieben und darf die Auswahl nicht einschränken).
     */
    public function withoutText(): self
    {
        return new self($this->datum, $this->kategorie, $this->format, null);
    }

    public function hasText(): bool
    {
        return null !== $this->text && '' !== $this->text;
    }

    /**
     * Kategorie-Filter, falls er einschränkt (nicht leer und nicht "Alle").
     */
    public function activeKategorie(): string|null
    {
        return $this->activeChoice($this->kategorie);
    }

    /**
     * Format-Filter, falls er einschränkt (nicht leer und nicht "Alle").
     */
    public function activeFormat(): string|null
    {
        return $this->activeChoice($this->format);
    }

    /**
     * Datumsfilter, falls gesetzt (nicht leer), sonst null.
     */
    public function activeDatum(): string|null
    {
        return null !== $this->datum && '' !== $this->datum ? $this->datum : null;
    }

    private function activeChoice(string|null $value): string|null
    {
        return null !== $value && '' !== $value && self::ALL !== $value ? $value : null;
    }
}
