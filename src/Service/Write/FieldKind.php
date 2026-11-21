<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Service\Write;

/**
 * Art eines schreibbaren Feldes der Anlegen- und Bearbeiten-Endpunkte. Die Art
 * bestimmt, wie der Wert aus dem Formular gelesen, geprüft und gespeichert wird
 * (RecordInputMapper).
 */
enum FieldKind
{
    /**
     * Klartext, gespeichert im Contao-Format (TextNormalizer::normalize(), E21).
     */
    case Text;

    /**
     * HTML aus dem Frontend-Editor, gefiltert über HtmlSanitizer.
     */
    case Html;

    /**
     * Checkbox: "1", wenn das Feld gesendet wurde, sonst leer (wie bisher isset()).
     */
    case Checkbox;

    /**
     * Nicht negative Ganzzahl, leer wird 0.
     */
    case Integer;

    /**
     * Nicht negative Zahl in einer Textspalte (Maße und Plätze der Locations,
     * Formularfeld type="number"); leer bleibt leer, gespeichert wie eingegeben.
     */
    case Number;

    /**
     * Titelbild: UUID einer Datei im Mitgliedsordner, sonst NULL.
     */
    case Avatar;

    /**
     * Verknüpfte Profile als JSON-Liste von IDs (z. B. ["12","34"]).
     */
    case LinkedIds;

    /**
     * Preise aus den Feldern price[] und pricetag[] als JSON.
     */
    case Prices;

    /**
     * Termine aus eventdate[], eventtimestart[], eventtimeend[] und eventcomment[] als JSON.
     */
    case Dates;
}
