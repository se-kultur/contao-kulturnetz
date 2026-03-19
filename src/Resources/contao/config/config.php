<?php
use SeKultur\ContaoKulturnetzBundle\Models\ArtistsModel;
use SeKultur\ContaoKulturnetzBundle\Models\HostsModel;
use SeKultur\ContaoKulturnetzBundle\Models\LocationsModel;
use SeKultur\ContaoKulturnetzBundle\Models\EventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\SekEventsModel;
use SeKultur\ContaoKulturnetzBundle\Models\FollowersModel;
use SeKultur\ContaoKulturnetzBundle\Models\PostingsModel;
//use SeKultur\ContaoKulturnetzBundle\EventListener\StoreFormDataListener;

// Models registrieren
$GLOBALS['TL_MODELS']['tl_artists'] = ArtistsModel::class;
$GLOBALS['TL_MODELS']['tl_hosts'] = HostsModel::class;
$GLOBALS['TL_MODELS']['tl_locations'] = LocationsModel::class;
$GLOBALS['TL_MODELS']['tl_events'] = EventsModel::class;
$GLOBALS['TL_MODELS']['tl_sekevents'] = SekEventsModel::class;
$GLOBALS['TL_MODELS']['tl_followers'] = FollowersModel::class;
$GLOBALS['TL_MODELS']['tl_postings'] = PostingsModel::class;


// Frontend-Elemente registrieren
$GLOBALS['TL_CTE']['texts']['testelement'] = '\SeKultur\ContaoKulturnetzBundle\Elements\TestElement';

// Backend-Module registrieren
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

$GLOBALS['FE_MOD']['eProjekt']['ModuleArtist'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleArtist';
$GLOBALS['FE_MOD']['eProjekt']['ModuleArtistAdd'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistAdd';
$GLOBALS['FE_MOD']['eProjekt']['ModuleArtistEdit'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistEdit';
$GLOBALS['FE_MOD']['eProjekt']['ModuleArtistsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistsList';
$GLOBALS['FE_MOD']['eProjekt']['ModuleArtistsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleArtistsListAll';

$GLOBALS['FE_MOD']['eProjekt']['ModuleLocation'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleLocation';
$GLOBALS['FE_MOD']['eProjekt']['ModuleLocationAdd'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationAdd';
$GLOBALS['FE_MOD']['eProjekt']['ModuleLocationEdit'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationEdit';
$GLOBALS['FE_MOD']['eProjekt']['ModuleLocationsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationsList';
$GLOBALS['FE_MOD']['eProjekt']['ModuleLocationsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleLocationsListAll';

$GLOBALS['FE_MOD']['eProjekt']['ModuleHost'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleHost';
$GLOBALS['FE_MOD']['eProjekt']['ModuleHostAdd'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleHostAdd';
$GLOBALS['FE_MOD']['eProjekt']['ModuleHostEdit'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleHostEdit';
$GLOBALS['FE_MOD']['eProjekt']['ModuleHostsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleHostsList';
$GLOBALS['FE_MOD']['eProjekt']['ModuleHostsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleHostsListAll';

$GLOBALS['FE_MOD']['eProjekt']['ModuleEvent'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleEvent';
$GLOBALS['FE_MOD']['eProjekt']['ModuleEventAdd'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleEventAdd';
$GLOBALS['FE_MOD']['eProjekt']['ModuleEventEdit'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleEventEdit';
$GLOBALS['FE_MOD']['eProjekt']['ModuleEventsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleEventsList';
$GLOBALS['FE_MOD']['eProjekt']['ModuleEventsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleEventsListAll';

$GLOBALS['FE_MOD']['eProjekt']['ModuleSekEvent'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEvent';
$GLOBALS['FE_MOD']['eProjekt']['ModuleSekEventAdd'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventAdd';
$GLOBALS['FE_MOD']['eProjekt']['ModuleSekEventEdit'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventEdit';
$GLOBALS['FE_MOD']['eProjekt']['ModuleSekEventsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventsList';
$GLOBALS['FE_MOD']['eProjekt']['ModuleSekEventsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModuleSekEventsListAll';

$GLOBALS['FE_MOD']['eProjekt']['ModulePostingsList'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModulePostingsList';
$GLOBALS['FE_MOD']['eProjekt']['ModulePostingsListAll'] = '\SeKultur\ContaoKulturnetzBundle\Module\ModulePostingsListAll';


// Hooks registrieren - scheinbar nicht mehr nötig in Contao 4.13
//$GLOBALS['TL_HOOKS']['saveArtist'][] = [SaveArtistListener::class, '__invoke'];
//$GLOBALS['TL_HOOKS']['storeFormData'][] = [StoreFormDataListener::class, '__invoke'];

//use SeKultur\ContaoKulturnetzBundle\EventListener\PrepareFormDataListener;
//$GLOBALS['TL_HOOKS']['activateAccount'][] = [PrepareFormDataListener::class, '__invoke'];