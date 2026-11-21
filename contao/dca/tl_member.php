<?php

/*
 * Erweiterungen der Mitgliedertabelle durch das KulturNetz-Bundle.
 */

// Adresse ist Pflicht (Registrierung und Backend)
$GLOBALS['TL_DCA']['tl_member']['fields']['street']['label'] = ['Straße und Hausnummer', ''];
$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['city']['eval']['mandatory'] = true;

/*
 * E-Mail-Adresse als Benutzername (Ersatz für terminal42/contao-mailusername,
 * Logik in src/EventListener/Member/MailUsernameListener.php): Die E-Mail ist
 * eindeutig, der Benutzername wird nicht eingegeben, sondern aus der E-Mail
 * übernommen und darf leer (NULL) sein. Werte wie in der Erweiterung, damit
 * Maske und Schema nach deren Entfernen gleich bleiben.
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['email']['eval']['unique'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['rgxp'] = 'email';
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['disabled'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['mandatory'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['sql'] = 'varchar(255) COLLATE utf8mb4_unicode_ci NULL';

/*
 * CSV-Export der Mitglieder als Backend-Route (src/Controller/Backend/ExportController.php).
 * "Mehrere bearbeiten" (Standardoperation "all") steht wie unter Contao 4.13 vor
 * dem Export; ohne die Angabe hängt Contao seine Standardoperationen hinten an.
 */
$GLOBALS['TL_DCA']['tl_member']['list']['global_operations'] ??= ['all'];
$GLOBALS['TL_DCA']['tl_member']['list']['global_operations']['csv'] = [
    'route' => 'kulturnetz_export_members_csv',
    'label' => 'CSV-Download',
    'icon' => 'bundles/contaokulturnetz/icons/csv.svg',
    'attributes' => 'data-turbo="false"',
    'primary' => true,
];
