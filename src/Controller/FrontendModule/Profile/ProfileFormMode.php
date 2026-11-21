<?php

declare(strict_types=1);

namespace SeKultur\ContaoKulturnetzBundle\Controller\FrontendModule\Profile;

/**
 * Betriebsart der Profilformulare: neues Profil anlegen oder eigenes bearbeiten.
 *
 * Der Wert steht im Template als Variable "mode" und am Formular als
 * data-type (das Formular-JS wertet ihn aus).
 */
enum ProfileFormMode: string
{
    case Add = 'add';
    case Edit = 'edit';
}
