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
        // Beiträge entstehen nur im Frontend (PostingService); "Neu" im Backend
        // legte eine Zeile ohne profile_id an und endete mit HTTP 500.
        'notCreatable' => true,
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
			'!delete',
		)
	),
    
	'palettes' => array
	(
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
