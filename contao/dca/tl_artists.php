<?php

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Callbacks dieser Tabelle stehen als Services mit #[AsCallback] unter
 * src/EventListener/DataContainer/ (Alias, Listenbeschriftung, Mitgliederinfo,
 * Karte, Freischaltungsmail). Der CSV-Export ist eine Backend-Route
 * (src/Controller/Backend/ExportController.php).
 *
 * Die Textareas ohne Editor tragen die Klasse "kulturnetz-textarea" (ohne eigene
 * Stile): Contao 5.7 ruft im Widget TextArea str_contains() mit der Klasse auf und
 * löst eine PHP-Deprecation aus, wenn weder eine Klasse noch "mandatory" gesetzt ist.
 */

$GLOBALS['TL_DCA']['tl_artists'] = [
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
			'fields'                  => array('created DESC'),
			'panelLayout'             => 'filter;sort,search,limit'
		),
		'label' => array
		(
			'fields'                  => array('name', 'member_id', 'email', 'created', 'tstamp'),
			'showColumns'             => true,
		),
		'global_operations' => array
		(
			'csv' => array
			(
				'route'                   => 'kulturnetz_export_artists_csv',
				'label'                   => 'CSV-Download',
				'icon'                    => 'bundles/contaokulturnetz/icons/csv.svg',
				'attributes'              => 'data-turbo="false"',
				'primary'                 => true,
			)
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
		'default'                     => '{personal_legend},name,alias,avatar,sparten,themen;
		{web_legend},website,facebook,instagram,tiktok,linkedin,xing;
		{contact_legend},email,telefon,adresse,plz,ort,map,lat,lon;
		{infos_legend},zitat,kurzbeschreibung,beschreibung,multiSRC;
		{email_legend},sendmail,sendmailsubject,sendmailtext;
		{settings_legend},disable,member_id,memberinfos,comment',
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
			'label'					=> ['Künstlername', ''],
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
			'eval'                    => array('mandatory'=>true, 'doNotCopy'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			// NULL statt '': unfertige Entwürfe ("Neu") und Kopien blockieren den eindeutigen
			// Index nicht (EmptyAliasToNullMigration, RecordCopyListener).
			'sql'                     => "varchar(255) BINARY NULL",
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
            'eval'          		=> ['addressFields' => ['adresse', 'plz', 'ort']],
        ],
		'sparten' => [
			'exclude' => false,
			'label'					=> ['Sparten', ''],
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'themen' => [
			'exclude' => false,
			'label'					=> ['Themenschwerpunkte', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'website' => [
			'exclude' => false,
			'label'					=> ['Webseite', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'facebook' => [
			'exclude' => false,
			'label'					=> ['Facebook-URL', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'instagram' => [
			'exclude' => false,
			'label'					=> ['Instagram-URL', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'tiktok' => [
			'exclude' => false,
			'label'					=> ['TikTok-URL', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'linkedin' => [
			'exclude' => false,
			'label'					=> ['LinkedIn-URL', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'xing' => [
			'exclude' => false,
			'label'					=> ['Xing-URL', ''],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>1024),
			'sql'                     => "varchar(1024) NOT NULL default ''"
		],
		'email' => [
			'exclude' => false,
			'label'					=> ['E-Mail Adresse', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'telefon' => [
			'exclude' => false,
			'label'					=> ['Telefon- oder Handynummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'adresse' => [
			'exclude' => false,
			'label'					=> ['Straße und Hausnummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'plz' => [
			'exclude' => false,
			'label'					=> ['Postleitzahl', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sorting'                 => true,
			'search'                 => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'ort' => [
			'exclude' => false,
			'label'					=> ['Ort', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sorting'                 => true,
			'search'                 => true,
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'zitat' => [
			'exclude' => false,
			'label'					=> ['Zitat', 'Persönliches Kultur-Zitat'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255),
			'sql'                     => "varchar(255) NOT NULL default ''"
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
			'label'					=> ['Gesperrt?', 'Neue Profile sind grundsätzlich nicht öffentlich und müssen erst überprüft und freigeschaltet werden.'],
			'exclude'                 => true,
			'reverseToggle'           => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			// "Neu" im Backend beginnt gesperrt, wie der Hilfetext verspricht.
			// Frontend-Anlagen setzen disable selbst (RecordWriter), die Spalte
			// behält ihren Standardwert.
			'default'                 => '1',
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'comment' => [
			'exclude' => false,
			'label'					=> ['Kommentar (INTERN)', 'Das Kommentar kann nur über das Backend an dieser Stelle gelesen und verändert werden und dient lediglich der internen Kommunikation / Protokollierung'],
			'inputType'               => 'textarea',
			'eval'                    => array('class'=>'kulturnetz-textarea'),
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
