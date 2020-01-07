# CakeMinify plugin for CakePHP

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

The recommended way to install composer packages is:

```
composer require michielkeijts/CakeMinify
```

## Requirements
This package now relies on the presence of NodeJS: [node-sass](https://www.npmjs.com/package/node-sass)

## Package contains some functionality
`Minifier::compileSass (string $content, string $filename, string $outputStyle ='compressed')` Compiles the $content and the list 
of files in the Configuration to a css file ($filename)
``



## Configuration

app.php 
```
    /**
	 * Configuration for Minify 
	 */
	'CakeMinify'	=>	[
		'Sass'	=>	[
			/**
			 *  all files in this directory are used 
			 */
			'path'	=>	ROOT . DS . 'sass',	
			'formatter'	=>	'compressed',	//see https://github.com/sass/node-sass/blob/master/bin/node-sass
		],
		/**
		 * Stylesheets / Javascript: Array of files to be created as compiled / minified scripts
		 * [
		 *	<FILENAME> => [<list of files (in order) to be concatenated>,..  ],
		 *	
		 */
		'Stylesheets'	=>	[
			'default'	=>	[   // will be set as name of the file. With a timestamp added.
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
                'stickyfill.min.js',
                'jquery.magnific-popup.min.js',
				'normit_javascript_helper.js',
				'jkm4base.sidebar.js',
				'jkm4base.js'
			]
		],
        
        /**
         * Available themes for shark
         */
        'Themes'    =>  [
            'default',
            'singlewing',
            'butterfly',
            'fullwidth'
        ]
	],
``` 