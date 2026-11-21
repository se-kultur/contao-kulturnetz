<?php

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Callbacks dieser Tabelle stehen als Services mit #[AsCallback] unter
 * src/EventListener/DataContainer/ (Alias, Listenbeschriftung, Mitgliederinfo,
 * Karte, Freischaltungsmail). Der CSV-Export ist eine Backend-Route
 * (src/Controller/Backend/ExportController.php).
 */

$GLOBALS['TL_DCA']['tl_locations'] = [
    'config' => [        
        'dataContainer' => DC_Table::class,
        //'ctable' => ['tl_parts'],
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
			'fields'                  => array('tstamp'),
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
				'route'                   => 'kulturnetz_export_locations_csv',
				'label'                   => 'CSV-Download',
				'icon'                    => 'bundles/contaokulturnetz/icons/csv.svg',
				'attributes'              => 'data-turbo="false"',
				'primary'                 => true,
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg'
			),
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'data-action="contao--scroll-offset#store" onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false"'
			),
			'toggle' => array
			(
				'href'                => 'act=toggle&amp;field=disable',
				'icon'                => 'visible.svg'
			)
		)
	),
    
	// member_id hat hier (anders als bei den Künstler:innen) kein Eingabefeld und
	// erschien deshalb nie in der Maske; ohne Eintrag in der Palette entfällt die
	// PHP-Deprecation, die Contao für Palettenfelder ohne inputType auslöst.
	'palettes' => array
	(
		//'__selector__'                => array('login', 'assignDir'),
		'default'                     => '{personal_legend},name,alias,avatar,themen;
		{web_legend},website,facebook,instagram,tiktok,linkedin,xing;
		{contact_legend},email,telefon,adresse,plz,ort,map,lat,lon;
		{location_legend},barrierefreiheit,auftrittsflaeche,gesamtflaeche,buehnentyp,technik,sitzplaetze,stehplaetze;
		{infos_legend},zitat,kurzbeschreibung,beschreibung,multiSRC;
		{email_legend},sendmail,sendmailsubject,sendmailtext;
		{settings_legend},disable,memberinfos,comment',
	),
	
    'fields' => [
        'id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'autoincrement' => true
			],
        ],
		'member_id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0 //'index' => true
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
			'label'					=> ['Name der Location', ''],
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
			//'eval'                    => array('rgxp'=>'folderalias', 'doNotCopy'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
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
                //'mandatory' 	=> true,
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
        ],
		'themen' => [
			'exclude' => false,
			'label'					=> ['Themenschwerpunkte', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'website' => [
			'exclude' => false,
			'label'					=> ['Webseite', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'facebook' => [
			'exclude' => false,
			'label'					=> ['Facebook-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'instagram' => [
			'exclude' => false,
			'label'					=> ['Instagram-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'tiktok' => [
			'exclude' => false,
			'label'					=> ['TikTok-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linkedin' => [
			'exclude' => false,
			'label'					=> ['LinkedIn-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'xing' => [
			'exclude' => false,
			'label'					=> ['Xing-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'email' => [
			'exclude' => false,
			'label'					=> ['E-Mail Adresse', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'telefon' => [
			'exclude' => false,
			'label'					=> ['Telefon- oder Handynummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'adresse' => [
			'exclude' => false,
			'label'					=> ['Straße und Hausnummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255),
		],
		'plz' => [
			'exclude' => false,
			'label'					=> ['Postleitzahl', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'search'                 => true,
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255),
		],
		'ort' => [
			'exclude' => false,
			'label'					=> ['Ort', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sorting'                 => true,
			'search'                 => true,
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255),
		],
		'barrierefreiheit' => [
			'exclude' => false,
			'label'					=> ['Barrierefreiheit', 'Informationen zur Barrierefreiheit der Location'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'auftrittsflaeche' => [
			'exclude' => false,
			'label'					=> ['Bühnen-Auftrittsfläche', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'gesamtflaeche' => [
			'exclude' => false,
			'label'					=> ['Gesamtfläche', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'buehnentyp' => [
			'exclude' => false,
			'label'					=> ['Bühnentyp', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'technik' => [
			'exclude' => false,
			'label'					=> ['Technische Ausstattung', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'sitzplaetze' => [
			'exclude' => false,
			'label'					=> ['Sitzplätze', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'stehplaetze' => [
			'exclude' => false,
			'label'					=> ['Stehplätze', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'zitat' => [
			'exclude' => false,
			'label'					=> ['Zitat', 'Persönliches Kultur-Zitat'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'kurzbeschreibung' => [
			'exclude' => false,
			'label'					=> ['Kurzbeschreibung', ''],
			'inputType'               => 'textarea',
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
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'comment' => [
			'exclude' => false,
			'label'					=> ['Kommentar (INTERN)', 'Das Kommentar kann nur über das Backend an dieser Stelle gelesen und verändert werden und dient lediglich der internen Kommunikation / Protokollierung'],
			'inputType'               => 'textarea',
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
				'doNotSaveEmpty'	=> true
			],
		],
		
		'memberinfos' => [
            'exclude' => false,
            'label'         		=> ['Informationen zum Mitglied', ''],
        ],
    ],
];
