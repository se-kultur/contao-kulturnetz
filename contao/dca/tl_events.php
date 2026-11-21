<?php

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Callbacks dieser Tabelle stehen als Services mit #[AsCallback] unter
 * src/EventListener/DataContainer/ (Alias, Listenbeschriftung, Mitgliederinfo,
 * Karte, Freischaltungsmail). Der CSV-Export ist eine Backend-Route
 * (src/Controller/Backend/ExportController.php); für Veranstaltungen gibt es wie
 * bisher keinen Export.
 *
 * Die JSON-Felder (prices, dates, linked_*) behalten "decodeEntities" bis zum
 * Sprung auf Contao 6: Contao 5.7 merkt sich die Kodierung jedes Feldes in
 * prepare_for_output_encoding (Version507\PrepareForOutputEncodingMigration),
 * und die Migration von Contao 6.0 dekodiert danach die Bestandsdaten. Ohne die
 * Angabe blieben gespeicherte Entities stehen (Restpunkt in CONTAO6-BEREITSCHAFT.md).
 *
 * Die Textareas ohne Editor tragen die Klasse "kulturnetz-textarea" (ohne eigene
 * Stile): Contao 5.7 ruft im Widget TextArea str_contains() mit der Klasse auf und
 * löst eine PHP-Deprecation aus, wenn weder eine Klasse noch "mandatory" gesetzt ist.
 */

$GLOBALS['TL_DCA']['tl_events'] = [
    'config' => [        
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
				'name' => 'index',
				'alias' => 'unique'
            ],
        ],
    ],
    
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => DataContainer::MODE_SORTABLE,
			'flag'					  => 1,
			'fields'                  => array('tstamp DESC'),
			'panelLayout'             => 'filter;sort,search,limit'
		),
		'label' => array
		(
			'fields'                  => array('name', 'member_id', 'email', 'created', 'tstamp'),
			'showColumns'             => true,
		),
		'operations' => array
		(
			'edit',
			'!delete',
			'toggle',
		)
	),
    
	'palettes' => array
	(
		'default'                     => '{personal_legend},name,alias,avatar,sparten,zielgruppen,artists,linked_artists;
		{eintritt_legend},eintrittfrei,prices,pricecomment,spenden,anmeldung,anmeldestelle;
		{termine_legend},dates;
		{location_legend},locations,linked_locations,location_adresse,location_plz,location_ort,map,lat,lon,location_barrierefreiheit,location_telefon,location_email,location_website;
		{hosts_legend},hosts,linked_hosts,host_adresse,host_plz,host_ort,host_telefon,host_email,host_website;
		{infos_legend},kurzbeschreibung,beschreibung,kooperationspartner,multiSRC;
		{email_legend},sendmail,sendmailsubject,sendmailtext;
		{settings_legend},disable,readonly,member_id,memberinfos,comment',
	),
	
    'fields' => [
        'id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'autoincrement' => true
			],
        ],
		'member_id' => [
			'exclude' => false,
			'label'					=> ['Mitglied', ''],
			'inputType'             => 'text',
			'eval'					=> array('disabled' => true),
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0
			],
        ],
        'created' => [
			'label'					=> ['Erstellt am', ''],
			'sorting'                 => true,
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0
			]
        ],
        'tstamp' => [
			'label'					=> ['Bearbeitet am', ''],
			'sorting'                 => true,
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0
			]
        ],
		'name' => [
			'label'					=> ['Eventname', ''],
			'exclude'                 => true,
			'search'                  => true,
			'sorting'                 => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'alias' => [
			'label'					=> ['Alias', 'Einzigartiger Alias - Wird in der URL angezeigt'],
			'exclude'                 => true,
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) BINARY NOT NULL default ''",
		],
		'avatar' => [
            'exclude' => false,
            'inputType' => 'fileTree',
            'eval' => [
                'tl_class' 		=> 'clr',
                'fieldType' 	=> 'radio',
                'filesOnly' 	=> true,
                'extensions' 	=> '%contao.image.valid_extensions%',
            ],
            'sql' => ['type' => 'binary', 'length' => 16, 'notnull' => false, 'fixed' => true]
        ],
		'multiSRC' => array
		(
			'exclude'                 => true,
			'inputType'               => 'fileTree',
			'eval'                    => [
				'multiple'		=>true, 
				'fieldType'		=>'checkbox', 
				'isSortable'	=> true,
				'files'			=>true,
				'extensions' 	=> '%contao.image.valid_extensions%',
			],
			'sql'                     => "blob NULL",
		),
		
		'lat' => [
			'exclude' => false,
			'label'					=> ['Latitude', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(25) NOT NULL default ''",
			'eval'                    => array('tl_class'=>'w50'),
		],
		'lon' => [
			'exclude' => false,
			'label'					=> ['Longitude', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(25) NOT NULL default ''",
			'eval'                    => array('tl_class'=>'w50'),
		],
		'map' => [
            'exclude' => false,
            'label'         		=> ['Position auf Karte', ''],
            // Felder für "Adresse auf Karte bestimmen": Straße, Postleitzahl, Ort (MapFieldListener)
            'eval'          		=> ['addressFields' => ['location_adresse', 'location_plz', 'location_ort']],
        ],
		
		'plakatentwurf' => [
			'exclude' => false,
			'label'					=> ['Plakatentwurf erbeten?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'sparten' => [
			'exclude' => false,
			'label'					=> ['Sparten', ''],
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'kulturform' => [
			'exclude' => false,
			'label'					=> ['Kulturform', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'zielgruppen' => [
			'exclude' => false,
			'label'					=> ['Zielgruppen', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'artists' => [
			'exclude' => false,
			'label'					=> ['Künstler:innen', 'Freitextfeld'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_artists' => [
			'exclude' => false,
			'label'					=> ['Verlinkte Künstler:innen', ''],
			'inputType'             => 'text',
			'sql'                   => "varchar(255) NOT NULL default ''",
			'eval' 					=> [
				'tl_class' => 'clr', 
				'helpwizard' => false, 
				'rte' => 'ace|json',
				'decodeEntities' => true
			],
		],
		'eintrittfrei' => [
			'exclude' => false,
			'label'					=> ['Eintritt frei?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'prices' => [
			'exclude' => false,
			'label'					=> ['Preise', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL",
			'eval' 					=> [
				'tl_class' => 'clr', 
				'helpwizard' => false, 
				'rte' => 'ace|json',
				'decodeEntities' => true
			],
		],
		'pricecomment' => [
			'exclude' => false,
			'label'					=> ['Kommentar zu Preisen', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		'spenden' => [
			'exclude' => false,
			'label'					=> ['Spenden erbeten?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'anmeldung' => [
			'exclude' => false,
			'label'					=> ['Anmeldung notwendig?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'anmeldestelle' => [
			'exclude' => false,
			'label'					=> ['Anmeldestelle(n)', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		'dates' => [
			'exclude' => false,
			'label'					=> ['Daten und Zeiten', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL",
			'eval' 					=> [
				'tl_class' => 'clr', 
				'helpwizard' => false, 
				'rte' => 'ace|json',
				'decodeEntities' => true
			],
		],
		'locations' => [
			'exclude' => false,
			'label'					=> ['Locations', 'Freitextfeld'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_locations' => [
			'exclude' => false,
			'label'					=> ['Verlinkte Location', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval' 					=> [
				'tl_class' => 'clr', 
				'helpwizard' => false, 
				'rte' => 'ace|json',
				'decodeEntities' => true
			],
		],
		'location_adresse' => [
			'exclude' => false,
			'label'					=> ['Location Adresse', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_plz' => [
			'exclude' => false,
			'label'					=> ['Location PLZ', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_ort' => [
			'exclude' => false,
			'label'					=> ['Location Ort', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'sorting'					  => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_barrierefreiheit' => [
			'exclude' => false,
			'label'					=> ['Location Barrierefreiheit', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval'                    => array('tl_class'=>'clr', 'maxlength'=>255),
		],
		'location_telefon' => [
			'exclude' => false,
			'label'					=> ['Location Telefon', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_email' => [
			'exclude' => false,
			'label'					=> ['Location E-Mail', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_website' => [
			'exclude' => false,
			'label'					=> ['Location Website', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'hosts' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:innen', 'Freitextfeld'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_hosts' => [
			'exclude' => false,
			'label'					=> ['Verlinkte Veranstalter:innen', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval' 					=> [
				'tl_class' => 'clr', 
				'helpwizard' => false, 
				'rte' => 'ace|json',
				'decodeEntities' => true
			],
		],
		'host_adresse' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in Adresse', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_plz' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in PLZ', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_ort' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in Ort', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_telefon' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in Telefon', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_email' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in E-Mail', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_website' => [
			'exclude' => false,
			'label'					=> ['Veranstalter:in Website', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'kooperationspartner' => [
			'exclude' => false,
			'label'					=> ['Kooperationspartner', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		
		
		'kurzbeschreibung' => [
			'exclude' => false,
			'label'					=> ['Kurzbeschreibung', ''],
			'inputType'               => 'textarea',
			'eval'                    => array('class'=>'kulturnetz-textarea'),
			'sql'                     => "text NULL"
		],
		'beschreibung' => [
			'exclude' => false,
			'label'					=> ['Langbeschreibung', ''],
			'inputType'               => 'textarea',
			'eval'                    => array('mandatory'=>true, 'rte'=>'tinyMCE', 'helpwizard'=>true),
			'sql'                     => "mediumtext NULL"
		],
		'disable' => [
			'label'					=> ['Unsichtbar/Nicht öffentlich', 'Neue Profile sind grundsätzlich nicht öffentlich und müssen erst überprüft und freigeschaltet werden.'],
			'exclude'                 => true,
			'reverseToggle'           => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'readonly' => [
			'label'					=> ['Gesperrt/Nicht bearbeitbar', 'Wenn aktiviert, kann das Mitglied den Datensatz nicht im Frontend bearbeiten/verändern.'],
			'exclude'                 => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'comment' => [
			'label'					=> ['Kommentar (INTERN)', 'Das Kommentar kann nur über das Backend an dieser Stelle gelesen und verändert werden und dient lediglich der internen Kommunikation / Protokollierung'],
			'inputType'               => 'textarea',
			'eval'                    => array('class'=>'kulturnetz-textarea'),
			'exclude'				  => true,
			'sql'                     => "mediumtext NULL"
		],
		'sendmail' => [
			'exclude' => false,
			'label'					=> ['E-Mail senden?'],
			'inputType'				=> 'checkbox',
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			]
		],
		'sendmailsubject' => [
			'exclude' => false,
			'label'					=> ['E-Mail Betreff'],
			'inputType'				=> 'text',
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			],
		],
		'sendmailtext' => [
			'exclude' => false,
			'label'					=> ['E-Mail Inhalt'],
			'inputType'				=> 'textarea',
			'eval'					=> [
				'doNotSaveEmpty'	=> true,
				'class'				=> 'kulturnetz-textarea'
			],
		],
		
		'memberinfos' => [
            'exclude' => false,
            'label'         		=> ['Informationen zum Mitglied', ''],
        ],
    ],
];
