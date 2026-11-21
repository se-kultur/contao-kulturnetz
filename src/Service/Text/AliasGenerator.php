<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Text;

use Contao\CoreBundle\Slug\Slug;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use SeKultur\ContaoKulturnetzBundle\Config\KulturnetzConfig;

/**
 * Erzeugt eindeutige Aliase für neue Profile und Veranstaltungen aus dem Frontend
 * (Entscheidung E32).
 *
 * Stil wie bei den bisher im Frontend angelegten Datensätzen (Funktion slugify()
 * der Ajax-Skripte, z. B. "ane-konigsbaum", "heidi-gossner"): Umlaute werden zum
 * Grundbuchstaben, "ß" zu "ss", alles klein, Trennzeichen "-". Die Umschrift der
 * deutschen Sonderzeichen ist hier ausdrücklich festgelegt; die übrigen Zeichen
 * schreibt der Contao-Slug-Service ohne Sprachregel nach Latin-ASCII um
 * (deterministisch, unabhängig von Locale und iconv des Servers).
 *
 * Eindeutigkeit je Tabelle mit Zähler im Contao-Stil ("name-2", "name-3"). Die
 * Altskripte hängten den Zähler ohne Bindestrich an ("name2"); diese Abweichung
 * ist dokumentiert. Rein numerische Aliase erhalten wie in Contao das Präfix "id-",
 * weil sie sonst als ID gelesen würden.
 */
final class AliasGenerator
{
    /**
     * Ausdrückliche Umschrift der deutschen Sonderzeichen (Grundbuchstabe statt "ae").
     */
    private const TRANSLITERATION = [
        'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
        'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
        'ß' => 'ss', 'ẞ' => 'SS',
    ];

    private const SLUG_OPTIONS = [
        'validChars' => 'a-z0-9',
        'delimiter' => '-',
        'locale' => '',
    ];

    /**
     * Alias, wenn der Name keine verwertbaren Zeichen enthält (wie slugify()).
     */
    private const EMPTY_ALIAS = 'n-a';

    public function __construct(
        private readonly Slug $slug,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Alias ohne Eindeutigkeitsprüfung (Stil-Regel, z. B. für Vergleiche).
     */
    public function slugify(string $name): string
    {
        $alias = $this->slug->generate(strtr($this->decode($name), self::TRANSLITERATION), self::SLUG_OPTIONS);

        return '' === $alias ? self::EMPTY_ALIAS : $alias;
    }

    /**
     * Eindeutiger Alias für einen Datensatz des Typs (artists, hosts, locations,
     * events, sekevents). $excludeId nimmt den eigenen Datensatz von der Prüfung aus.
     */
    public function generate(string $type, string $name, int|null $excludeId = null): string
    {
        if (!\in_array($type, KulturnetzConfig::TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Unbekannter KulturNetz-Typ "%s".', $type));
        }

        $table = 'tl_'.$type;

        $exists = function (string $alias) use ($table, $excludeId): bool {
            return false !== $this->connection->fetchOne(
                "SELECT id FROM $table WHERE alias = CAST(? AS BINARY) AND id != ? LIMIT 1",
                [$alias, $excludeId ?? 0],
                [ParameterType::STRING, ParameterType::INTEGER],
            );
        };

        $base = $this->slugify($name);
        $alias = $base;

        for ($count = 2; $exists($alias); ++$count) {
            $alias = $base.'-'.$count;
        }

        return $alias;
    }

    private function decode(string $name): string
    {
        // Gespeicherte Namen können Entities enthalten (Contao-Speicherformat).
        return html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
