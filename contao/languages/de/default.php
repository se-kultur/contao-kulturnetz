<?php

/*
 * Die Legenden und Feldbezeichnungen der fünf Bundle-Tabellen stehen in den
 * Sprachdateien der jeweiligen Tabelle (tl_artists.php usw.).
 */

/*
 * Die E-Mail-Adresse dient als Benutzername (MailUsernameListener, DCA tl_member):
 * Das Anmeldeformular und die Registrierung beschriften das Feld "username" mit
 * "E-Mail-Adresse" statt "Benutzername".
 */
$GLOBALS['TL_LANG']['MSC']['username'] = 'E-Mail-Adresse';

/*
 * Das Metadatenfeld "caption" der Dateiverwaltung führt auf dieser Website das
 * Copyright eines Bildes (Ausgabe als "© ..." in den Templates).
 */
$GLOBALS['TL_LANG']['MSC']['aw_caption'] = 'Copyright';

/*
 * Meldungen der Mitglieder-Endpunkte (Anlegen und Bearbeiten von Profilen und
 * Veranstaltungen, Dateien), ausgegeben über Service\Write\FormMessages. Das
 * Formular-JavaScript zeigt sie am jeweiligen Feld (Status 422) bzw. im
 * Lade-Overlay.
 */
$GLOBALS['TL_LANG']['ERR']['kulturnetz_email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an (z. B. name@beispiel.de).';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_postal_code'] = 'Bitte geben Sie eine fünfstellige Postleitzahl an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_phone'] = 'Die Telefonnummer darf nur Ziffern, Leerzeichen und die Zeichen + - / ( ) . enthalten.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_url'] = 'Bitte geben Sie eine Internetadresse an, z. B. www.beispiel.de oder https://www.beispiel.de.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_choice'] = 'Bitte wählen Sie nur Einträge aus der Liste aus.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_short_description'] = 'Bitte geben Sie eine Kurzbeschreibung an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_price'] = 'Bitte geben Sie den Preis als Betrag an, z. B. 12,50 €.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_required'] = 'Bitte füllen Sie dieses Feld aus.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_row'] = 'Termin %d: %s';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date'] = 'Bitte geben Sie ein gültiges Datum im Format TT.MM.JJJJ an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_yearless'] = 'Bitte geben Sie ein gültiges Datum im Format TT.MM. an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_range'] = 'Bitte geben Sie ein Datum zwischen dem %s und dem %s an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_time_missing'] = 'Bitte geben Sie Beginn und Ende an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_time_format'] = 'Bitte geben Sie die Uhrzeit im Format HH:MM zwischen 00:00 und 23:59 an.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_signup_closed'] = 'Die Anmeldung zu den SE-KulturTagen ist geschlossen. Neue Events können nicht mehr angelegt werden.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_file_too_large'] = 'Die gewählte Datei ist zu groß. Erlaubt sind höchstens %s.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_media_plain_text'] = 'Bildbeschreibung und Copyright sind reiner Text. Bitte entfernen Sie HTML-Tags wie <a href="…">.';
