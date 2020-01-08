<?php

/* 
 * @copyright (C) 2020 Michiel Keijts, Normit
 * 
 */
return [
    /**
	 * Configuration for Minify 
	 */
	'CakeMinify'	=>	[
        'outputDir' => 'compiled/',  // relative sub directory of jsBaseUrl and cssBaseUrl
		'Sass'	=>	[
			/**
			 *  all files in this directory are used 
			 */
			'path'	=>	ROOT . DS . 'sass/',	
			'outputStyle'	=>	'compact',       //see https://github.com/sass/node-sass/blob/master/bin/node-sass
		],
		/**
		 * Stylesheets / Javascript: Array of files to be created as compiled / minified scripts
         * 
         * This filename either exists in the baseDir (parameter of CakeMinify or in the cssBaseUrl
         * 
		 * [
		 *	<FILENAME> => [<list of files (in order) to be concatenated>,..  ],
		 *	
		 */
		'Stylesheets'	=>	[
			'default'	=>	[
				'bootstrap.css',
				'shark.css'
			],
		],
		
		/**
		 * See configuration for Styelsheets above
		 */
		'Scripts'	=>	[
			'default'	=>	[
				'jquery.min.js',
				'tether.min.js',
				'bootstrap.bundle.js',
			]
		]
    ]
];