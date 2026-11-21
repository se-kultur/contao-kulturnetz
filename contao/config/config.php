<?php

declare(strict_types=1);

use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\FollowersModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;
use SeKultur\ContaoKulturnetzBundle\Models\PostingsModel;
use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;

/*
 * Registrierungen ohne Attribut-Nachfolger in Contao 5.7 und 6.0 (Restpunkt R14):
 * Models und Backend-Module. Alles andere (Frontend-Module, DCA-Callbacks, Hooks,
 * Migrationen, Routen) registriert das Bundle über Attribute und
 * config/services.yaml.
 */

// Models registrieren
$GLOBALS['TL_MODELS']['tl_artists'] = ArtistsModel::class;
$GLOBALS['TL_MODELS']['tl_hosts'] = HostsModel::class;
$GLOBALS['TL_MODELS']['tl_locations'] = LocationsModel::class;
$GLOBALS['TL_MODELS']['tl_events'] = EventsModel::class;
$GLOBALS['TL_MODELS']['tl_sekevents'] = SekEventsModel::class;
$GLOBALS['TL_MODELS']['tl_followers'] = FollowersModel::class;
$GLOBALS['TL_MODELS']['tl_postings'] = PostingsModel::class;

// Backend-Module registrieren (Schlüssel stehen in do=-URLs, unverändert lassen)
$GLOBALS['BE_MOD']['KulturNetz']['Kuenstler'] = [
    'tables' => ['tl_artists'],
];
$GLOBALS['BE_MOD']['KulturNetz']['- Postings'] = [
    'tables' => ['tl_postings'],
];
$GLOBALS['BE_MOD']['KulturNetz']['Veranstalter'] = [
    'tables' => ['tl_hosts'],
];
$GLOBALS['BE_MOD']['KulturNetz']['Locations'] = [
    'tables' => ['tl_locations'],
];
$GLOBALS['BE_MOD']['KulturNetz']['Veranstaltungen'] = [
    'tables' => ['tl_events'],
];
$GLOBALS['BE_MOD']['KulturTage']['Events'] = [
    'tables' => ['tl_sekevents'],
];
