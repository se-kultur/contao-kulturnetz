<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Zusätzliches Stoppdatum für die SE-KulturTage-Anmeldelinks.
 *
 * Steuert ausschließlich die Sichtbarkeit der Anmeldelinks im Mobilmenü und in
 * der Fußzeile: ausgewertet von SignupWindowChecker über die Twig-Funktion
 * kulturnetz_signup_open(), die das Layout page/layout/se-kultur.html.twig
 * aufruft; page/se-kultur/_navigation_mobile.html.twig und _footer.html.twig
 * blenden die Links damit ein oder aus. Ist das Feld leer,
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
