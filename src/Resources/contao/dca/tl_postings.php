<?php

$GLOBALS['TL_DCA']['tl_postings'] = [
    'config' => [        
        'dataContainer' => 'Table',
        //'ctable' => ['tl_parts'],
        //'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
	
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => DataContainer::MODE_SORTABLE,
			'fields'                  => array('tstamp DESC'),
			'panelLayout'             => 'filter;sort,search,limit'
		),
		'label' => array
		(
			'fields'                  => array('content', 'member_id'),
			'showColumns'             => true,
			'label_callback'          => array('tl_postings', 'addLabelData')
		),
		'global_operations' => array
		(
			/*'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)*/
		),
		'operations' => array
		(
			/*'edit' => array
			(
				'href'                => 'act=edit',
				'icon'                => 'edit.svg'
			),
			'copy' => array
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
		'default'                     => '{personal_legend},content;',
	),
	
    'fields' => [
        'id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'autoincrement' => true
			],
        ],
		'content' => [
			'label'					=> ['Kurzbeschreibung', ''],
			'inputType'               => 'textarea',
			'sql'                     => "text NULL"
		],
		'profile_id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 
			],
        ],
		'profile_type' => [
			'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
		'member_id' => [
			'label'					=> ['Mitglied', ''],
            'sql' => [
				'type' => 'integer', 'unsigned' => true,
			],
        ],
		'tstamp' => [
			'label'					=> ['Erstellt am', ''],
			'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0
			]
        ],
    ],
];


class tl_postings extends Backend {
	public function __construct()
	{
		parent::__construct();
		//$this->import(BackendUser::class, 'User');
		
		//$GLOBALS['TL_DCA']['tl_events']['list']['label']['fields'] = array('name', 'member_id', 'email', 'created', 'tstamp');
	}
		
	public function addLabelData($row, $label, DataContainer $dc, $args) 
	{
		// Label MEMBER_ID
		$fieldName = 'member_id';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        $key = array_search($fieldName, $fields, true);
		$oldVar = $args[$key];
		
		$objMember = $this->Database->prepare("SELECT * FROM tl_member WHERE id = ?")
				->execute($oldVar);

        $args[$key] = '<nobr>'.$objMember->firstname.' '.$objMember->lastname.' <a href="/contao/preview?page=40&amp;user='.$objMember->email.'" title="" target="_blank"><img src="system/themes/flexible/icons/su.svg" width="16" height="16" alt="Frontend-Vorschau als Mitglied ID '.$objMember->id.'"></a></nobr>';
		
		// Label TSTAMP
		$fieldName = 'tstamp';
        $fields = $GLOBALS['TL_DCA'][$dc->table]['list']['label']['fields'];
        if($key = array_search($fieldName, $fields, true)) {
			$oldVar = $args[$key];

			$args[$key] = '<span style="white-space: nowrap;">'.date('d.m.y - H:i', $oldVar).'</span>';
		}
		
		return $args;
	}
}