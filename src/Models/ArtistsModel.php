<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Models;

use Contao\Model;

/**
 * Contao-Model der Tabelle tl_artists (Künstler*innen-Profile).
 *
 * Bewusst ohne eigene Logik: Lesen und Schreiben laufen über die Services unter
 * src/Service/ (Doctrine DBAL). Die Registrierung in $GLOBALS['TL_MODELS']
 * (contao/config/config.php, Restpunkt R14) hält die Tabelle für Core-Funktionen
 * ansprechbar, die ein Model über den Tabellennamen auflösen
 * (Model::getClassFromTable()).
 */
final class ArtistsModel extends Model
{
    protected static $strTable = 'tl_artists';
}
