<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Search;

use Doctrine\DBAL\Connection;
use SeKultur\ContaoKulturnetzBundle\Service\Text\TextNormalizer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suchbegriff der Vorschlagssuchen (Parameter "term" wie bei jQuery UI
 * Autocomplete) und daraus das LIKE-Muster.
 *
 * Der Begriff wird wie gespeicherte Texte nach der Contao-Regel kodiert
 * (TextNormalizer::normalize(), E21), damit z. B. "(" den gespeicherten Wert
 * "&#40;" findet; die LIKE-Platzhalter "%" und "_" werden maskiert und wirken
 * wörtlich. Bisher passten die Skripte den Begriff per htmlentities() an das
 * alte Entity-Format an.
 */
final class SuggestionTerm
{
    /**
     * Mindestlänge wie minLength der Autocomplete-Felder.
     */
    public const MIN_LENGTH = 3;

    /**
     * Höchstzahl der Vorschläge wie bisher.
     */
    public const LIMIT = 15;

    /**
     * Maskierungszeichen der LIKE-Muster (SQL: LIKE ? ESCAPE '!').
     */
    public const ESCAPE_CHAR = '!';

    /**
     * Meldung, wenn der Begriff fehlt oder zu kurz ist (422).
     */
    public const MESSAGE_TOO_SHORT = 'Bitte geben Sie mindestens 3 Zeichen ein.';

    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Begriff aus dem Request oder null, wenn er fehlt, kein Text ist oder kürzer
     * als MIN_LENGTH ist (gezählt wie in JavaScript).
     */
    public function fromRequest(Request $request): string|null
    {
        $term = $request->query->all()['term'] ?? null;

        if (!\is_string($term)) {
            return null;
        }

        $length = intdiv(\strlen(mb_convert_encoding($term, 'UTF-16LE', 'UTF-8')), 2);

        return $length >= self::MIN_LENGTH ? $term : null;
    }

    /**
     * LIKE-Muster "enthält" für den Begriff, zu verwenden mit ESCAPE '!'.
     */
    public function containsPattern(string $term): string
    {
        $value = $this->connection->getDatabasePlatform()->escapeStringForLike($this->normalizer->normalize($term), self::ESCAPE_CHAR);

        return '%'.$value.'%';
    }
}
