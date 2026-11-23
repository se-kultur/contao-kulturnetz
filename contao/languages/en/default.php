<?php

/*
 * The legends and field labels of the five bundle tables are in the language
 * files of the respective table (tl_artists.php etc.).
 */

/*
 * The email address serves as the username (MailUsernameListener, DCA tl_member):
 * the login and registration forms label the "username" field "E-mail address"
 * instead of "Username".
 */
$GLOBALS['TL_LANG']['MSC']['username'] = 'E-mail address';

/*
 * The meta field "caption" of the file manager holds the copyright of an image
 * on this website (rendered as "© ..." by the templates).
 */
$GLOBALS['TL_LANG']['MSC']['aw_caption'] = 'Copyright';

/*
 * Messages of the member endpoints (creating and editing profiles and events,
 * files), returned via Service\Write\FormMessages. The form JavaScript shows
 * them at the field (status 422) or in the loading overlay.
 */
$GLOBALS['TL_LANG']['ERR']['kulturnetz_email'] = 'Please enter a valid e-mail address (e.g. name@example.org).';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_postal_code'] = 'Please enter a five-digit postal code.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_phone'] = 'The phone number may only contain digits, spaces and the characters + - / ( ) .';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_url'] = 'Please enter a web address, e.g. www.example.org or https://www.example.org.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_choice'] = 'Please only choose entries from the list.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_short_description'] = 'Please enter a short description.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_price'] = 'Please enter the price as an amount, e.g. 12,50 €.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_required'] = 'Please fill out this field.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_row'] = 'Date %d: %s';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date'] = 'Please enter a valid date in the format DD.MM.YYYY.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_yearless'] = 'Please enter a valid date in the format DD.MM.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_date_range'] = 'Please enter a date between %s and %s.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_time_missing'] = 'Please enter start and end time.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_time_format'] = 'Please enter the time in the format HH:MM between 00:00 and 23:59.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_signup_closed'] = 'The registration for the SE-KulturTage is closed. New events can no longer be created.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_file_too_large'] = 'The selected file is too large. The maximum is %s.';
$GLOBALS['TL_LANG']['ERR']['kulturnetz_media_plain_text'] = 'Image description and copyright are plain text. Please remove HTML tags such as <a href="…">.';
