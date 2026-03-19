<?php

$GLOBALS['TL_DCA']['tl_hosts'] = [
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
			'label_callback'          => array('tl_hosts', 'addLabelData')
		),
		'global_operations' => array
		(
			'csv' => array(
                'href'      => '#',
                'button_callback'=> array('tl_hosts', 'downloadButton')
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
		'default'                     => '{personal_legend},name,alias,avatar,themen;
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
			'label'					=> ['Name der Institution', ''],
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
				array('tl_hosts', 'generateAlias')
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
				'tl_hosts', 'latlonmap'
			]
        ],
		'themen' => [
			'label'					=> ['Themenschwerpunkte', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'website' => [
			'label'					=> ['Webseite', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'facebook' => [
			'label'					=> ['Facebook-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'instagram' => [
			'label'					=> ['Instagram-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'tiktok' => [
			'label'					=> ['TikTok-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'linkedin' => [
			'label'					=> ['LinkedIn-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'xing' => [
			'label'					=> ['Xing-URL', ''],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'email' => [
			'label'					=> ['E-Mail Adresse', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'telefon' => [
			'label'					=> ['Telefon- oder Handynummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'adresse' => [
			'label'					=> ['Straße und Hausnummer', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'plz' => [
			'label'					=> ['Postleitzahl', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sorting'                 => true,
			'search'                 => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'ort' => [
			'label'					=> ['Ort', 'Wird öffentlich im Profil angezeigt'],
			'inputType'               => 'text',
			'sorting'                 => true,
			'search'                 => true,
			'sql'                     => "varchar(255) NOT NULL default ''"
		],
		'zitat' => [
			'label'					=> ['Zitat', 'Persönliches Kultur-Zitat'],
			'inputType'               => 'text',
			'sql'                     => "varchar(255) NOT NULL default ''"
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
			'label'					=> ['Gesperrt?', 'Neue Profile sind grundsätzlich nicht öffentlich und müssen erst überprüft und freigeschaltet werden.'],
			'exclude'                 => true,
			'toggle'                  => true,
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => "char(1) NOT NULL default ''",
			//'save_callback'			  => [['tl_hosts', 'sendActivationMail']]
		],
		'comment' => [
			'label'					=> ['Kommentar (INTERN)', 'Das Kommentar kann nur über das Backend an dieser Stelle gelesen und verändert werden und dient lediglich der internen Kommunikation / Protokollierung'],
			'inputType'               => 'textarea',
			'sql'                     => "mediumtext NULL"
		],
		'sendmail' => [
			'label'					=> ['E-Mail senden?'],
			'inputType'				=> 'checkbox',
			'save_callback'			=> [
				['tl_hosts', 'mailing']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			]
		],
		'sendmailsubject' => [
			'label'					=> ['E-Mail Betreff'],
			'inputType'				=> 'text',
			'save_callback'			=> [
				['tl_hosts', 'dontsaveempty']
			],
			'load_callback' 		=> [
				['tl_hosts', 'loadsubject']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			],
		],
		'sendmailtext' => [
			'label'					=> ['E-Mail Inhalt'],
			'inputType'				=> 'textarea',
			'save_callback'			=> [
				['tl_hosts', 'dontsaveempty']
			],
			'load_callback' 		=> [
				['tl_hosts', 'loadmailtext']
			],
			'eval'					=> [
				'doNotSaveEmpty'	=> true
			],
		],
		
		'memberinfos' => [
            'label'         		=> ['Informationen zum Mitglied', ''],
            'input_field_callback' => [
				'tl_hosts', 'memberinfos'
			]
        ],
    ],
];

class tl_hosts extends Backend {
	public function __construct()
	{
		parent::__construct();
		//$this->import(BackendUser::class, 'User');
	}
	
	public function downloadButton($arrRow, $href, $label, $title, $icon) {
        return '
		<a href="./files/se-kultur.de/code/csvHosts.php" style="display:inline-block;margin-left: 15px;">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: auto; height: 16px;"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M0 64C0 28.7 28.7 0 64 0H224V128c0 17.7 14.3 32 32 32H384V304H176c-35.3 0-64 28.7-64 64V512H64c-35.3 0-64-28.7-64-64V64zm384 64H256V0L384 128zM200 352h16c22.1 0 40 17.9 40 40v8c0 8.8-7.2 16-16 16s-16-7.2-16-16v-8c0-4.4-3.6-8-8-8H200c-4.4 0-8 3.6-8 8v80c0 4.4 3.6 8 8 8h16c4.4 0 8-3.6 8-8v-8c0-8.8 7.2-16 16-16s16 7.2 16 16v8c0 22.1-17.9 40-40 40H200c-22.1 0-40-17.9-40-40V392c0-22.1 17.9-40 40-40zm133.1 0H368c8.8 0 16 7.2 16 16s-7.2 16-16 16H333.1c-7.2 0-13.1 5.9-13.1 13.1c0 5.2 3 9.9 7.8 12l37.4 16.6c16.3 7.2 26.8 23.4 26.8 41.2c0 24.9-20.2 45.1-45.1 45.1H304c-8.8 0-16-7.2-16-16s7.2-16 16-16h42.9c7.2 0 13.1-5.9 13.1-13.1c0-5.2-3-9.9-7.8-12l-37.4-16.6c-16.3-7.2-26.8-23.4-26.8-41.2c0-24.9 20.2-45.1 45.1-45.1zm98.9 0c8.8 0 16 7.2 16 16v31.6c0 23 5.5 45.6 16 66c10.5-20.3 16-42.9 16-66V368c0-8.8 7.2-16 16-16s16 7.2 16 16v31.6c0 34.7-10.3 68.7-29.6 97.6l-5.1 7.7c-3 4.5-8 7.1-13.3 7.1s-10.3-2.7-13.3-7.1l-5.1-7.7c-19.3-28.9-29.6-62.9-29.6-97.6V368c0-8.8 7.2-16 16-16z"/></svg>
			CSV-Download
		</a>';
    }
	
	public function generateAlias($varValue, DataContainer $dc)
	{
		$aliasExists = function (string $alias) use ($dc): bool
		{
			return $this->Database->prepare("SELECT id FROM tl_hosts WHERE alias=? AND id!=?")->execute($alias, $dc->id)->numRows > 0;
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

        $args[$key] = '<a href="https://'.Idna::decode(Environment::get('host')).'/detailansicht-veranstalterprofil/'.$row['alias'].'.html" target="_blank" title="Im Frontend ansehen">'.$oldVar.'</a>';
		
		// Label MEMBER_ID
		$fieldName = 'member_id';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];
		
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
				->execute($oldVar);

        $args[$key] = $objMember->firstname.' '.$objMember->lastname.' <a href="/contao/preview?user='.$objMember->email.'" title="" target="_blank"><img src="system/themes/flexible/icons/su.svg" width="16" height="16" alt="Frontend-Vorschau als Mitglied ID '.$objMember->id.'"></a>';
		
		// Label TSTAMP
		$fieldName = 'email';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		
        $args[$key] = $objMember->email;
		
		
		// Label TSTAMP
		$fieldName = 'tstamp';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];

        $args[$key] = '<span style="white-space: nowrap;">'.date('d.m.y - H:i', $oldVar).'</span>';
		
		
		// Label CREATED
		$fieldName = 'created';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];

        $args[$key] = '<span style="white-space: nowrap;">'.date('d.m.y - H:i', $oldVar).'</span>';
		
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
			$objEmail->subject = 'Ihr SE-KulturNetz Veranstalter:innenprofil wurde aktiviert';
			$objEmail->text = "Moin ".$objMember->firstname." ".$objMember->lastname."!\r\n\r\nIhr Veranstalter:innenprofil im SE-KulturNetz wurde aktiviert - herzlichen Glückwunsch!\r\n\r\nEs ist nun über unsere Webseite öffentlich erreichbar:\r\nhttps://".Idna::decode(Environment::get('host'))."/detailansicht-veranstalterprofil/".$dc->activeRecord->alias.".html\r\n\r\nLiebe Grüße\r\nIhr SE-KulturTeam";
			$objEmail->sendTo($objMember->email);
		}	
		
		return $varValue;*/
	} 
	
	public function dontsaveempty()
	{
		return '';
	}
	
	public function loadsubject($varValue, DataContainer $dc) {
		return 'Ihr SE-KulturNetz Veranstalter:innenprofil wurde aktiviert';
	}
	
	public function loadmailtext($varValue, DataContainer $dc) {
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
			->execute($dc->activeRecord->member_id);

		$var = "Moin ".$objMember->firstname." ".$objMember->lastname."!\r\n\r\nIhr Veranstalter:innenprofil im SE-KulturNetz wurde aktiviert - herzlichen Glückwunsch!\r\n\r\nEs ist nun über unsere Webseite öffentlich erreichbar:\r\nhttps://".Idna::decode(Environment::get('host'))."/detailansicht-veranstalterprofil/".$dc->activeRecord->alias.".html\r\n\r\nLiebe Grüße\r\nIhr SE-KulturTeam";
		
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
				'Bestätigungsmail wurde an '.$objMember->email.' gesendet (tl_hosts)', __METHOD__, TL_EMAIL
			);
		}	
		
		return '';
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
}