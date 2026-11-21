<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Zusätzliches Stoppdatum für die SE-KulturTage-Anmeldelinks.
 *
 * Steuert ausschließlich die Sichtbarkeit der Anmeldelinks in Navigation und
 * Footer (ausgewertet im Theme-Template fe_page.html5). Ist das Feld leer,
 * greift das reguläre System-Stoppdatum der Seite. Die Seite selbst bleibt
 * über ihre normale Veröffentlichung erreichbar, damit Nachzügler den
 * Direktlink weiter nutzen können.
 */
$GLOBALS['TL_DCA']['tl_page']['fields']['sekAnmeldungLinkStop'] = array(
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => array('rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'),
	'sql'       => "varchar(10) NOT NULL default ''",
);

PaletteManipulator::create()
	->addField('sekAnmeldungLinkStop', 'stop', PaletteManipulator::POSITION_AFTER)
	->applyToPalette('regular', 'tl_page');
