<?php

$GLOBALS['TL_DCA']['tl_sekevents'] = [
    'config' => [        
        'dataContainer' => 'Table',
        //'ctable' => ['tl_parts'],
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
				'name' => 'index',
				'alias' => 'unique'
            ],
        ],
		//'onload_callback' => ['tl_sekevents' => 'onloadCallback']
    ],
    
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 2,//DataContainer::MODE_SORTABLE,
			'flag'					  => 1,
			'fields'                  => array('tstamp DESC'),
			//'panelLayout'             => 'filter;sort,search,limit',
			'panelLayout'             => 'testpanel,filter;sort,search,limit',
			'panel_callback'          => array(
				'testpanel' => ['tl_sekevents', 'addPanelData']
			)
		),
		'label' => array
		(
			'fields'                  => array('name', 'member_id', 'email', 'created', 'tstamp'),
			'showColumns'             => true,
			'label_callback'          => array('tl_sekevents', 'addLabelData')
		),
		'global_operations' => array
		(
			'csv' => array(
                'href'      => '#',
                'button_callback'=> array('tl_sekevents', 'downloadButton')
            )
			/*'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)*/
		),
		'operations' => array
		(
			'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg'
			),
			/*'copy' => array
			(
				'href'                => 'act=copy',
				'icon'                => 'copy.svg'
			),*/
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
			),
			'toggle' => array
			(
				'href'                => 'act=toggle&amp;field=disable',
				'icon'                => 'visible.svg',
				'reverse'             => true
			),
			/*'show' => array
			(
				'href'                => 'act=show',
				'icon'                => 'show.svg'
			),
			'su' => array
			(
				'href'                => 'key=su',
				'icon'                => 'su.svg',
				'button_callback'     => array('tl_member', 'switchUser')
			)*/
		)
	),
    
	'palettes' => array
	(
		//'__selector__'                => array('login', 'assignDir'),
		'default'                     => '{personal_legend},name,alias,avatar,plakatentwurf,sparten,kulturform,zielgruppen,artists,linked_artists;
		{eintritt},eintrittfrei,prices,pricecomment,spenden,anmeldung,anmeldestelle;
		{termine},dates;
		{location},locations,linked_locations,location_adresse,location_plz,location_ort,map,lat,lon,location_barrierefreiheit,location_telefon,location_email,location_website;
		{hosts},hosts,linked_hosts,host_adresse,host_plz,host_ort,host_telefon,host_email,host_website
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
			'label'					=> ['Mitglied', ''],
			'inputType'             => 'text',
			'eval'					=> array('disabled' => true),
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
			//'eval'                    => array('rgxp'=>'folderalias', 'doNotCopy'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) BINARY NOT NULL default ''",
			'save_callback' => array
			(
				array('tl_sekevents', 'generateAlias')
			),
		],
		'avatar' => [
            'inputType' => 'fileTree',
            'eval' => [
                'tl_class' 		=> 'clr',
                'fieldType' 	=> 'radio',
                'filesOnly' 	=> true,
                'extensions' 	=> \Contao\Config::get('validImageTypes'),
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
				'orderField'	=>'orderSRC', 
				'files'			=>true,
				'extensions' 	=> \Contao\Config::get('validImageTypes'),
			],
			'sql'                     => "blob NULL",
		),
		'orderSRC' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['sortOrder'],
			'sql'                     => "blob NULL"
		),
		
		'lat' => [
			'label'					=> ['Latitude', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(25) NOT NULL",
			'eval'                    => array('tl_class'=>'w50'),
		],
		'lon' => [
			'label'					=> ['Longitude', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(25) NOT NULL",
			'eval'                    => array('tl_class'=>'w50'),
		],
		'map' => [
            'label'         		=> ['Position auf Karte', ''],
            'input_field_callback' => [
				'tl_sekevents', 'latlonmap'
			]
        ],
		
		'plakatentwurf' => [
			'label'					=> ['Plakatentwurf erbeten?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		
		
		'programmhefte' => [
			'label'					=> ['Programmhefte', ''],
			'inputType'               => 'text',
			'sql'                     => "int(2) unsigned NOT NULL default 0"
		],
		'werbeplakate_a4' => [
			'label'					=> ['Werbeplakate A4', ''],
			'inputType'               => 'text',
			'sql'                     => "int(2) unsigned NOT NULL default 0"
		],
		'werbeplakate_a3' => [
			'label'					=> ['Werbeplakate A3', ''],
			'inputType'               => 'text',
			'sql'                     => "int(2) unsigned NOT NULL default 0"
		],
		'werbeplakate_a1' => [
			'label'					=> ['Werbeplakate A1', ''],
			'inputType'               => 'text',
			'sql'                     => "int(2) unsigned NOT NULL default 0"
		],
		'werbeplakate_a0' => [
			'label'					=> ['Werbeplakate A0', ''],
			'inputType'               => 'text',
			'sql'                     => "int(2) unsigned NOT NULL default 0"
		],
		'wimpelketten' => [
			'label'					=> ['Wimpelketten erbeten?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		
		'sparten' => [
			'label'					=> ['Sparten', ''],
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'kulturform' => [
			'label'					=> ['Kulturform', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'zielgruppen' => [
			'label'					=> ['Zielgruppen', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'artists' => [
			'label'					=> ['Künstler:innen', 'Freitextfeld'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_artists' => [
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
			'label'					=> ['Eintritt frei?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'prices' => [
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
			'label'					=> ['Kommentar zu Preisen', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		'spenden' => [
			'label'					=> ['Spenden erbeten?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'anmeldung' => [
			'label'					=> ['Anmeldung notwendig?', ''],
			'inputType'               => 'checkbox',
			'filter'                  => true,
			'sql'                     => "char(1) NOT NULL default ''",
		],
		'anmeldestelle' => [
			'label'					=> ['Anmeldestelle(n)', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		'dates' => [
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
			'label'					=> ['Locations', 'Freitextfeld'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_locations' => [
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
			'label'					=> ['Location Adresse', ''],
			'inputType'               => 'text',
			//'search'                  => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_plz' => [
			'label'					=> ['Location PLZ', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_ort' => [
			'label'					=> ['Location Ort', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'sorting'					  => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_barrierefreiheit' => [
			'label'					=> ['Location Barrierefreiheit', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''",
			'eval'                    => array('tl_class'=>'clr'),
		],
		'location_telefon' => [
			'label'					=> ['Location Telefon', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_email' => [
			'label'					=> ['Location E-Mail', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'location_website' => [
			'label'					=> ['Location Website', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'hosts' => [
			'label'					=> ['Veranstalter:innen', 'Freitextfeld'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linked_hosts' => [
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
			'label'					=> ['Veranstalter:in Adresse', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_plz' => [
			'label'					=> ['Veranstalter:in PLZ', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_ort' => [
			'label'					=> ['Veranstalter:in Ort', ''],
			'inputType'               => 'text',
			'search'                  => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_telefon' => [
			'label'					=> ['Veranstalter:in Telefon', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_email' => [
			'label'					=> ['Veranstalter:in E-Mail', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'host_website' => [
			'label'					=> ['Veranstalter:in Website', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'kooperationspartner' => [
			'label'					=> ['Kooperationspartner', ''],
			'inputType'               => 'text',
			'sql'                     => "text NULL"
		],
		
		
		'kurzbeschreibung' => [
			'label'					=> ['Kurzbeschreibung', ''],
			'inputType'               => 'textarea',
			'sql'                     => "text NULL"
		],
		'beschreibung' => [
			'label'					=> ['Langbeschreibung', ''],
			'inputType'               => 'textarea',
			'eval'                    => array('mandatory'=>true, 'rte'=>'tinyMCE', 'helpwizard'=>true),
			'sql'                     => "mediumtext NULL"
		],
		'disable' => [
			'label'					=> ['Unsichtbar/Nicht öffentlich', 'Neue Profile sind grundsätzlich nicht öffentlich und müssen erst überprüft und freigeschaltet werden.'],
			'exclude'                 => true,
			'toggle'                  => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => "char(1) NOT NULL default ''",
			//'save_callback'			  => [['tl_sekevents', 'sendActivationMail']]
		],
		'readonly' => [
			'label'					=> ['Gesperrt/Nicht bearbeitbar', 'Wenn aktiviert, kann das Mitglied den Datensatz nicht im Frontend bearbeiten/verändern.'],
			'exclude'                 => true,
			'toggle'                  => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => "char(1) NOT NULL default ''",
			//'save_callback'			  => [['tl_sekevents', 'sendActivationMail']]
		],
		'comment' => [
			'label'					=> ['Kommentar (INTERN)', 'Das Kommentar kann nur über das Backend an dieser Stelle gelesen und verändert werden und dient lediglich der internen Kommunikation / Protokollierung'],
			'inputType'               => 'textarea',
			'exclude'				  => true,
			'sql'                     => "mediumtext NULL"
		],
		'sendmail' => [
			'label'					=> ['E-Mail senden?'],
			'inputType'				=> 'checkbox',
			'save_callback'			=> [
				['tl_sekevents', 'mailing']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			]
		],
		'sendmailsubject' => [
			'label'					=> ['E-Mail Betreff'],
			'inputType'				=> 'text',
			'save_callback'			=> [
				['tl_sekevents', 'dontsaveempty']
			],
			'load_callback' 		=> [
				['tl_sekevents', 'loadsubject']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			],
		],
		'sendmailtext' => [
			'label'					=> ['E-Mail Inhalt'],
			'inputType'				=> 'textarea',
			'save_callback'			=> [
				['tl_sekevents', 'dontsaveempty']
			],
			'load_callback' 		=> [
				['tl_sekevents', 'loadmailtext']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			],
		],
		
		'memberinfos' => [
            'label'         		=> ['Informationen zum Mitglied', ''],
            'input_field_callback' => [
				'tl_sekevents', 'memberinfos'
			]
        ],
    ],
];

//$GLOBALS['TL_DCA']['tl_sekevents']['list']['sorting']['fields'] = ['host_ort ASC'];
//$GLOBALS['TL_DCA']['tl_sekevents']['list']['label']['fields'] = array('name', 'member_id', 'email', 'location_ort', 'tstamp');

class tl_sekevents extends Backend {
	public function __construct()
	{
		parent::__construct();
		//$this->import(BackendUser::class, 'User');
		
		//$GLOBALS['TL_DCA']['tl_sekevents']['list']['label']['fields'] = array('name', 'member_id', 'email', 'created', 'tstamp');
	}
	
	public function addPanelData(DataContainer $dc) 
	{
		$objSessionBag = System::getContainer()->get('request_stack')->getSession()->getBag('contao_backend');
    	$data = $objSessionBag->all();
		
		//echo '<pre>';
		//var_dump($data['sorting']);
		//exit;
		
		if(isset($data['sorting']['tl_sekevents'])) {
			if($data['sorting']['tl_sekevents'] !== 'name') {
				$GLOBALS['TL_DCA']['tl_sekevents']['list']['label']['fields'] = array('name', 'member_id', 'email', $data['sorting']['tl_sekevents']);	
			}
		}
	}
	
	public function downloadButton($arrRow, $href, $label, $title, $icon) {
        return '
		<a href="./files/se-kultur.de/code/csvEvents.php" style="display:inline-block;margin-left: 15px;">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: auto; height: 16px;"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M0 64C0 28.7 28.7 0 64 0H224V128c0 17.7 14.3 32 32 32H384V304H176c-35.3 0-64 28.7-64 64V512H64c-35.3 0-64-28.7-64-64V64zm384 64H256V0L384 128zM200 352h16c22.1 0 40 17.9 40 40v8c0 8.8-7.2 16-16 16s-16-7.2-16-16v-8c0-4.4-3.6-8-8-8H200c-4.4 0-8 3.6-8 8v80c0 4.4 3.6 8 8 8h16c4.4 0 8-3.6 8-8v-8c0-8.8 7.2-16 16-16s16 7.2 16 16v8c0 22.1-17.9 40-40 40H200c-22.1 0-40-17.9-40-40V392c0-22.1 17.9-40 40-40zm133.1 0H368c8.8 0 16 7.2 16 16s-7.2 16-16 16H333.1c-7.2 0-13.1 5.9-13.1 13.1c0 5.2 3 9.9 7.8 12l37.4 16.6c16.3 7.2 26.8 23.4 26.8 41.2c0 24.9-20.2 45.1-45.1 45.1H304c-8.8 0-16-7.2-16-16s7.2-16 16-16h42.9c7.2 0 13.1-5.9 13.1-13.1c0-5.2-3-9.9-7.8-12l-37.4-16.6c-16.3-7.2-26.8-23.4-26.8-41.2c0-24.9 20.2-45.1 45.1-45.1zm98.9 0c8.8 0 16 7.2 16 16v31.6c0 23 5.5 45.6 16 66c10.5-20.3 16-42.9 16-66V368c0-8.8 7.2-16 16-16s16 7.2 16 16v31.6c0 34.7-10.3 68.7-29.6 97.6l-5.1 7.7c-3 4.5-8 7.1-13.3 7.1s-10.3-2.7-13.3-7.1l-5.1-7.7c-19.3-28.9-29.6-62.9-29.6-97.6V368c0-8.8 7.2-16 16-16z"/></svg>
			CSV-Download
		</a> 
		
		<a href="./files/se-kultur.de/code/pdfEvents.php" target="_blank" style="display:inline-block;margin-left: 15px;">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: auto; height: 16px;"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M0 64C0 28.7 28.7 0 64 0L224 0l0 128c0 17.7 14.3 32 32 32l128 0 0 144-208 0c-35.3 0-64 28.7-64 64l0 144-48 0c-35.3 0-64-28.7-64-64L0 64zm384 64l-128 0L256 0 384 128zM176 352l32 0c30.9 0 56 25.1 56 56s-25.1 56-56 56l-16 0 0 32c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-48 0-80c0-8.8 7.2-16 16-16zm32 80c13.3 0 24-10.7 24-24s-10.7-24-24-24l-16 0 0 48 16 0zm96-80l32 0c26.5 0 48 21.5 48 48l0 64c0 26.5-21.5 48-48 48l-32 0c-8.8 0-16-7.2-16-16l0-128c0-8.8 7.2-16 16-16zm32 128c8.8 0 16-7.2 16-16l0-64c0-8.8-7.2-16-16-16l-16 0 0 96 16 0zm80-112c0-8.8 7.2-16 16-16l48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 32 32 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 48c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-64 0-64z"/></svg>
			PDF-Download
		</a>';
    }
	
	
	public function generateAlias($varValue, DataContainer $dc)
	{
		$aliasExists = function (string $alias) use ($dc): bool
		{
			return $this->Database->prepare("SELECT id FROM tl_sekevents WHERE alias=? AND id!=?")->execute($alias, $dc->id)->numRows > 0;
		};

		// Generate alias if there is none
		if (!$varValue)
		{
			$varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->name);
		}
		elseif (preg_match('/^[1-9]\d*$/', $varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
		}
		elseif ($aliasExists($varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
		}

		return $varValue;
	}
	
	
	public function addLabelData($row, $label, DataContainer $dc, $args) 
	{
		// Label NAME
		$fieldName = 'name';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];
		
		$readonly = '';
		if($row['readonly'] == 1) $readonly = ' <img src="system/themes/flexible/icons/lock-locked.svg" style="max-width:16px;height:auto;" title="Bearbeitung im Frontend gesperrt">';

        $args[$key] = '<a href="https://'.Idna::decode(Environment::get('host')).'/detailansicht-sekultur-event/'.$row['alias'].'.html" target="_blank" title="Im Frontend ansehen">'.$oldVar.'</a>'.$readonly;
		
		// Label MEMBER_ID
		$fieldName = 'member_id';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];
		
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
				->execute($oldVar);

        $args[$key] = $objMember->firstname.' '.$objMember->lastname.' <a href="/contao/preview?page=40&amp;user='.$objMember->email.'" title="" target="_blank"><img src="system/themes/flexible/icons/su.svg" width="16" height="16" alt="Frontend-Vorschau als Mitglied ID '.$objMember->id.'"></a>';
		
		// Label EMAIL
		$fieldName = 'email';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		
        $args[$key] = $objMember->email;
		
		
		// Label TSTAMP
		$fieldName = 'tstamp';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        if($key = array_search($fieldName, $fields, true)) {
			$oldVar = $args[$key];

			$args[$key] = '<span style="white-space: nowrap;">'.date('d.m.y - H:i', $oldVar).'</span>';
		}
		
		// Label CREATED
		$fieldName = 'created';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        if($key = array_search($fieldName, $fields, true)) {
			$oldVar = $args[$key];

			$args[$key] = '<span style="white-space: nowrap;">'.date('d.m.y - H:i', $oldVar).'</span>';
		}
		
		return $args;
	}
	
	public function sendActivationMail($varValue, DataContainer $dc) 
	{
		/*if ($dc->activeRecord->disable != $varValue && $varValue != '1') {
			$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
				->execute($dc->activeRecord->member_id);
			
			
			$objEmail = new Email();
			$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
			$objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'] ?? null;
			$objEmail->subject = 'Ihr Event für die SE-KulturTage wurde aktiviert';
			$objEmail->text = "Moin ".$objMember->firstname." ".$objMember->lastname."!\r\n\r\nIhr Event für die SE-KulturTage wurde aktiviert - herzlichen Glückwunsch!\r\n\r\nEs ist nun über unsere Webseite öffentlich erreichbar:\r\nhttps://".Idna::decode(Environment::get('host'))."/detailansicht-sekultur-event/".$dc->activeRecord->alias.".html\r\n\r\nLiebe Grüße\r\nIhr SE-KulturTeam";
			$objEmail->sendTo($objMember->email);
		}	
		
		return $varValue;*/
	} 
	
	public function dontsaveempty()
	{
		return '';
	}
	
	public function loadsubject($varValue, DataContainer $dc) {
		return 'Ihr SE-KulturTage Event wurde überprüft';
	}
	
	public function loadmailtext($varValue, DataContainer $dc) {
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
			->execute($dc->activeRecord->member_id);

		$var = "Moin ".$objMember->firstname." ".$objMember->lastname."!\r\n\r\nIhr Event in den SE-KulturTagen wurde überprüft und freigeschaltet - herzlichen Glückwunsch!\r\n\r\nSie können Ihr Event über folgenden Link erreichen:\r\nhttps://".Idna::decode(Environment::get('host'))."/detailansicht-sekultur-event/".$dc->activeRecord->alias.".html\r\n\r\nHinweis: Änderungen können ab sofort nur noch durch das SE-KulturTeam vorgenommen werden.\r\n\r\nLiebe Grüße\r\nIhr SE-KulturTeam";
		
		return $var;
	}
	
	public function mailing($varValue, DataContainer $dc) {
		if ($varValue == '1') {
			$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
			->execute($dc->activeRecord->member_id);
			
			$objEmail = new Email();
			$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
			$objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'] ?? null;
			$objEmail->subject = $_POST['sendmailsubject'];
			$objEmail->text = $_POST['sendmailtext'];
			$objEmail->sendTo($objMember->email);
			
			$this->log(
				'Bestätigungsmail wurde an '.$objMember->email.' gesendet (tl_locations)', __METHOD__, TL_EMAIL
			);
		}	
		
		return '';
	}
	
	public function latlonmap(DataContainer $dc, $strLabel) {
		return '
		<div class="widget">
		<button class="tl_submit" id="getLocationData" style="float:right;margin-top:10px">Adresse auf Karte bestimmen</button>
		<h3 style="clear:both;">Position auf Karte</h3><div id="map" style="width:100%;height:350px;background:#eee;"></div></div>
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
		
		<script>
		var center = ['.(($dc->activeRecord->lat > 0)?$dc->activeRecord->lat:'53.5437641').', '.(($dc->activeRecord->lat > 0)?$dc->activeRecord->lon:'10.0099133').']
			 var map = L.map(\'map\').setView(center, '.(($dc->activeRecord->lat > 0)?'13':'9').');
			 L.tileLayer(\'https://tile.openstreetmap.org/{z}/{x}/{y}.png\', {
				maxZoom: 19,
				attribution: \'© OpenStreetMap\'
			}).addTo(map); 
			
			var marker = L.marker([51.5, -0.09]).addTo(map);
			
			'.(($dc->activeRecord->lat > 0)?'marker = L.marker(['.$dc->activeRecord->lat.', '.$dc->activeRecord->lon.']).addTo(map);':'').'
			
			map.on(\'click\', function(e){
				var coord = e.latlng;
				console.log(e);
				
				document.getElementById(\'ctrl_lat\').value = coord.lat;
                document.getElementById(\'ctrl_lon\').value = coord.lng;
				
				var newLatLng = new L.LatLng(coord.lat, coord.lng);
    			marker.setLatLng(newLatLng); 
				
				map.panTo(newLatLng);
			});
			
			document.getElementById("getLocationData").onclick = function(e){
				e.preventDefault();
				
				var xmlhttp = new XMLHttpRequest();
				
				xmlhttp.onreadystatechange = function() {
					if (xmlhttp.readyState == XMLHttpRequest.DONE) { // XMLHttpRequest.DONE == 4
					   if (xmlhttp.status == 200) {
					   		var response = JSON.parse(xmlhttp.responseText);
							console.log( response.length );
							console.log( response[0] );
							
							if(response.length > 0) {
								var newLatLng = [parseFloat(response[0].lat), parseFloat(response[0].lon)];
								marker.setLatLng(newLatLng); 

								//map.panTo(newLatLng);
								//map.setZoom(15);
								map.flyTo(newLatLng, 15);
								
								document.getElementById(\'ctrl_lat\').value = response[0].lat;
                				document.getElementById(\'ctrl_lon\').value = response[0].lon;
							}
						   	
					   }
					   else if (xmlhttp.status == 400) {
						  alert("There was an error 400");
					   }
					   else {
						   alert("something else other than 200 was returned");
					   }
					}
				};
				
				var address = document.getElementById(\'ctrl_location_adresse\').value+", "+document.getElementById(\'ctrl_location_plz\').value+" "+document.getElementById(\'ctrl_location_ort\').value;

				xmlhttp.open("GET", "https://nominatim.openstreetmap.org/search?format=json&q="+address, true);
				xmlhttp.send();
			};
		</script>';
	}
	
	public function memberinfos(DataContainer $dc, $strLabel) {
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
			->execute($dc->activeRecord->member_id);
		
		return '<div style="padding:14px;"><table class="tl_listing showColumns">
		  <tbody>
		  	<tr>
				<th class="tl_folder_tlist col_"></th>
				<th class="tl_folder_tlist col_firstname">Vorname</th>
				<th class="tl_folder_tlist col_lastname">Nachname</th>
				<th class="tl_folder_tlist col_username">Benutzername</th>
				<th class="tl_folder_tlist col_dateAdded ordered_by">Hinzugefügt am</th>
				<th class="tl_folder_tlist tl_right_nowrap"></th>
			  </tr>
			  <tr class="even click2edit toggle_select hover-row">
				<td colspan="1" class="tl_file_list col_"><div class="list_icon_new" style="background-image:url(\'system/themes/flexible/icons/member.svg\')" data-icon="system/themes/flexible/icons/member.svg" data-icon-disabled="system/themes/flexible/icons/member_.svg">&nbsp;</div></td><td colspan="1" class="tl_file_list col_firstname">'.$objMember->firstname.'</td><td colspan="1" class="tl_file_list col_lastname">'.$objMember->lastname.'</td><td colspan="1" class="tl_file_list col_username">'.$objMember->email.'</td><td colspan="1" class="tl_file_list col_dateAdded ordered_by">'.date('Y-m-d H:i', $objMember->dateAdded).'</td>
				<td class="tl_file_list tl_right_nowrap">
				<a href="contao?do=member&amp;act=edit&amp;id='.$objMember->id.'&amp;rt='.System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue().'" title="" class="edit"><img src="system/themes/flexible/icons/edit.svg" width="16" height="16" alt="Mitglied ID '.$objMember->id.' bearbeiten"></a> 
				<a href="contao?do=member&amp;act=show&amp;id='.$objMember->id.'&amp;popup=1&amp;rt='.System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue().'" title="" onclick="Backend.openModalIframe({\'title\':\'Details des Mitglieds ID '.$objMember->id.' anzeigen\',\'url\':this.href});return false" class="show"><img src="system/themes/flexible/icons/show.svg" width="16" height="16" alt="Details des Mitglieds ID '.$objMember->id.' anzeigen"></a>
				<a href="/contao/preview?user='.$objMember->email.'" title="" target="_blank"><img src="system/themes/flexible/icons/su.svg" width="16" height="16" alt="Frontend-Vorschau als Mitglied ID '.$objMember->id.'"></a></td>
			  </tr>
		  </tbody></table>
		</div>
		
		<script>
		var textarea = document.querySelector(\'textarea[name="kurzbeschreibung"]\');
		var ldiv = document.createElement("div");
		
		//document.body.insertBefore(ldiv, textarea);
		textarea.insertAdjacentElement(\'afterend\', ldiv); 
		ldiv.style.fontSize = \'12px\';
		ldiv.style.textAlign = \'right\';
		ldiv.innerHTML = textarea.value.length+\' / 250\';
		
		textarea.addEventListener("keyup", (event) => {
			ldiv.innerHTML = textarea.value.length+\' / 250\';
		});
		</script>';
	}
}