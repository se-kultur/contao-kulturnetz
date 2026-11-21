<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Text;

/**
 * Bereitet gespeicherte Texte für die Ausgabe in den Twig-Templates des Bundles
 * auf (Bildtitel, Copyright, Alternativtext, Termin- und Preisangaben).
 *
 * Die Templates unter @Contao laufen in Contao 5.7 durch den Contao-Escaper
 * (Contao\CoreBundle\Twig\Interop\ContaoEscaper): Er erwartet Werte im
 * Contao-Eingabeformat und kodiert vorhandene Entities nur dann nicht doppelt,
 * wenn der Wert kein rohes Anführungszeichen, "<" oder ">" enthält. Contao
 * ersetzt außerdem Insert-Tags in der fertigen Seite; ein Text mit "{{...}}"
 * würde ausgewertet.
 *
 * Deshalb liefert encode() den Wert im Contao-Eingabeformat
 * (TextNormalizer::normalize()): Altdaten werden vollständig dekodiert, danach
 * stehen Sonderzeichen und die Klammern von Insert-Tags als Entities
 * ("&#123;&#123;", "&#125;&#125;"). Der Escaper gibt das unverändert aus, der
 * Browser zeigt den Text wörtlich, und Contao findet keine Insert-Tags. Ein
 * Klartext mit nur kodierten Klammern reichte nicht: Enthält er zusätzlich ein
 * Anführungszeichen, kodiert der Escaper doppelt und die Besucher sähen
 * "&#123;&#123;".
 *
 * Die Kodierung ist idempotent: encode(encode($x)) === encode($x).
 *
 * Contao 6 entfernt das Input-Encoding und den Contao-Escaper
 * (CONTAO6-BEREITSCHAFT.md, Restpunkt zur Textkodierung). Dann liefert diese
 * Methode Klartext, und die Insert-Tag-Klammern werden nach dem Maskieren
 * kodiert; die Aufrufer bleiben unverändert.
 */
final class TemplateTextEncoder
{
    public function __construct(private readonly TextNormalizer $textNormalizer)
    {
    }

    public function encode(string|null $value): string
    {
        return $this->textNormalizer->normalize((string) $value);
    }
}
