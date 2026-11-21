<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Text;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Filtert HTML aus dem Frontend-Editor der Mitglieder (Feld "beschreibung" der
 * Profile und Veranstaltungen) vor dem Speichern.
 *
 * Erlaubt ist genau das, was die Werkzeugleiste des Frontend-Editors erzeugt
 * (Rückgängig/Wiederholen, Format entfernen, fett, kursiv, nummerierte und
 * Aufzählungsliste, Link) plus Absätze und Zeilenumbrüche: p, br, strong/b,
 * em/i, ul, ol, li, a mit href, title, target, rel. Links nur mit http, https,
 * mailto, tel oder relativ. Alle anderen Elemente werden entfernt, ihr Text bleibt
 * erhalten; script, style, eingebettete Inhalte und Formularelemente werden samt
 * Inhalt entfernt.
 *
 * Grundlage ist der Symfony HTML Sanitizer, den auch der Twig-Filter
 * sanitize_html von Contao nutzt. Der Contao-Sanitizer "contao"
 * (ContaoHtmlSanitizer) kennt nur die globale Erlaubnisliste aus den
 * Systemeinstellungen und baut auf Contao\Input auf, das mit dem Input-Encoding
 * in Contao 6 entfällt; deshalb hier eine eigene, engere Konfiguration.
 *
 * Wie bei Contao-Frontend-Eingaben werden Insert-Tags ("{{", "}}") kodiert, damit
 * Mitglieder keine Insert-Tags in die Seitenausgabe einschleusen.
 */
final class HtmlSanitizer
{
    private const ALLOWED_ELEMENTS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    private const DROPPED_ELEMENTS = [
        'script', 'style', 'template', 'noscript', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'svg', 'math', 'form', 'input', 'button', 'select', 'option', 'textarea', 'head', 'title', 'meta', 'link',
    ];

    private readonly SymfonyHtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            // Keine Kürzung: Beschreibungen sind mediumtext, die Länge prüft der Schreibweg.
            ->withMaxInputLength(-1)
        ;

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        foreach (self::DROPPED_ELEMENTS as $element) {
            $config = $config->dropElement($element);
        }

        $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }

    /**
     * Gefiltertes HTML im Speicherformat (Insert-Tags kodiert).
     */
    public function sanitize(string $html): string
    {
        $html = $this->sanitizer->sanitize($html);
        $html = str_replace(['{{', '}}'], ['&#123;&#123;', '&#125;&#125;'], $html);

        return trim($html);
    }

    /**
     * Länge des sichtbaren Textes eines HTML-Wertes (Zeichen, ohne Tags, Entities
     * dekodiert, Leerraum zusammengefasst), z. B. für die Mindestlänge der
     * Beschreibung (heute 30 Zeichen im Formular-JS).
     */
    public function textLength(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strlen($text);
    }
}
