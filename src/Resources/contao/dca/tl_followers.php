<?php

$GLOBALS['TL_DCA']['tl_followers'] = [
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
	
    'fields' => [
        'id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 'autoincrement' => true
			],
        ],
		'follower_id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true, 
			],
        ],
		'follower_type' => [
			'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
		'following_id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true,
			],
        ],
		'member_id' => [
            'sql' => [
				'type' => 'integer', 'unsigned' => true,
			],
        ],
		'following_type' => [
			'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
		'tstamp' => [
			'sql' => [
				'type' => 'integer', 'unsigned' => true, 'default' => 0
			]
        ],
    ],
];
