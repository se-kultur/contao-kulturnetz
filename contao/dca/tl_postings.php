<?php

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Die Listenbeschriftung kommt aus dem Service
 * src/EventListener/DataContainer/PostingLabelListener.php (#[AsCallback]).
 */

$GLOBALS['TL_DCA']['tl_postings'] = [
    'config' => [        
        'dataContainer' => DC_Table::class,
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
		),
		'global_operations' => array
		(
		),
		'operations' => array
		(
			'delete' => array
			(
				'href'                => 'act=delete',
				'icon'                => 'delete.svg',
				'attributes'          => 'data-action="contao--scroll-offset#store" onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false"'
			)
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
			'exclude' => false,
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
