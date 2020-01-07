<?php
/*
 * (C) Normit, 2018, Michiel Keijts
 */

namespace CakeMinify\Minify;

use Cake\Filesystem\File;
use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Exception;
use App\Model\Entity\Config;
use Cake\ORM\TableRegistry;
use Cake\Collection\Collection;
use Cake\Utility\Hash;
use App\Helper\SassCompiler;
use Cake\Filesystem\Folder;
use App\Helper\MinifyConfigDatabase;
use App\Shell\Task\QueueStylesheetsTask;

/**
 * Minifyhelper is a static class helping with the minification of the assets
 * 
 * @author Michiel Keijts, Normit
 */
class Minify {
    /**
     * If initialized
     * @var bool
     */
    private static $_initialized = FALSE;
            
    /**
     * List of files (path => 
     * @var array
     */
    protected static $_file_list = [];
    
    /**
     * Current data
     * @var MinifyConfig
     */
    private static $minify_config;
    
    protected static function initialize() 
    {
        if (static::$_initialized) {
            return TRUE;
        }
        
        self::readConfig();
        
        static::$_initialized = TRUE;
    }
    
	/**
	 * Get the debug or non debug version of the assets
	 * @param mixed $names string or array: set of stylesheets/javascript to merge
	 * @param string $type Stylesheets or Scripts
     * @param int $site_id 
     * @param string $language
     * @param string $theme
     * @return array List Of Files
	 * @throws NotFoundException
	 */
	public static function getAssetFiles($names = 'default', $type, $site_id, $language, $theme) : array
	{
		if (!is_array($names))	
			$names = [$names];
		
		// check if is debug mode
		$debug = Configure::read('debug');
		
		// build array of files
		$assetFiles = [];
		foreach ($names as $name) {
    		// load either the compiled version, or all files
			if (!$debug) {
                // timestamp is saved in the minify config
                $timestamp = static::get($type, $site_id, $language, $theme, FALSE);
				
                if (!$timestamp) {
					throw new NotFoundException(__('Minified version not found. Please run `cake minify` again'));
				}
                
                array_push($assetFiles, sprintf('compiled/%s', self::getFileNameFor($name . ($type == 'Stylesheets'?'.css':'.js'), $site_id, $language, $theme, $timestamp)));
			} else {
                $asset_files = Configure::read('Minify.'. $type . '.' . $name);

                foreach ($asset_files as &$filename) {
                    if ($type == 'Stylesheets') {
                        $filename = sprintf('%s%s', 'compiled/', self::getFileNameFor($filename,  $site_id, $language, $theme));
                    } 
                }
				$assetFiles = array_merge($assetFiles, $asset_files);
			}
		}
		
		return $assetFiles;
	}
    
    /**
     * Get the timestamp of most recent modified file of type
     * @param string $type Stylesheets of Scripts
     * @param string $collection (default: 'default')
     * @param int $site_id
     * @param string $language
     * @param string $theme
     * @return int timestamp
     */
    public static function getMaxTimestampFor(string $type, string $collection = 'default', int $site_id, string $language, string $theme) : int
    {
        // first get timestamp of files
        $files = static::getFileListFor($type, $collection, $site_id, $language, $theme);
        
        $timestamps = [];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                array_push($timestamps, filemtime($file));
            }
        }
        
        if ($type == 'Stylesheets') {
            array_push($timestamps, static::getThemeCssFromDatabase($site_id, $language, $theme)->modified->format('U'));
        }
        
        return max($timestamps);
    }
    
    /**
     * Get filename for a specific sheet for a specific site.
     * 
     * e.g. shark.css will become shark_<site_id><language><optional timestamp>.css
     * @param string $name
     * @param int $site_id
     * @param string $language
     * @param string $theme
     * @param int  $timestamp (default 0)
     * @param bool $full return full path
     * @return string
     */
    public static function getFileNameFor(string $name = 'shark.css', int $site_id, string $language, string $theme, int $timestamp = 0, $full = FALSE) : string
    {
        if (preg_match('/(.*)\.(scss|css|js)/',$name, $matches) !== 1)
            return $name;
        
        $fname = $matches[1];
        $ext = $matches[2];
        
        if ($timestamp > 0) {
            $filename = sprintf('%s_%s%s_%s%d.%s', $fname, $site_id, $language, $theme, $timestamp, $ext);
        } else {
            $filename = sprintf('%s_%s%s_%s.%s', $fname, $site_id, $language, $theme, $ext);
        }
        
        if ($full) {
            return sprintf('%scss/compiled/%s',WWW_ROOT, $filename);
        }
        
        return $filename;
    }
    
    /**
     * Get a list of files of all the files in a configuration, using the whole path
     * @param string $type Stylesheets or Scripts
     * @param string $name
     * @param int $site_id
     * @param string $language
     * @param string $theme
     * @return array
     * @throws Exception
     */
    protected static function getFileListFor(string $type, string $name = 'default', int $site_id, string $language, string $theme) : array
    {
        if (!in_array($type, ['Stylesheets','Scripts'])) {
            throw new Exception("Trying to get not defined minify type: {$type}");
        }
        
        $cssBase = Configure::read('App.cssBaseUrl');
        $jsBase = Configure::read('App.jsBaseUrl');
        
        static::$_file_list['Stylesheets.'. $name] = (new Collection(Configure::read('Minify.Stylesheets.' . $name)))->map(function($item) use ($site_id, $language, $theme) { 
            return static::getFileNameFor($item, $site_id, $language, $theme, 0, TRUE);
        })->toArray();
        
        static::$_file_list['Scripts.'. $name] = (new Collection(Configure::read('Minify.Scripts.' . $name)))->map(function($item) use ($jsBase) { 
            return sprintf('%s%scompiled/%s', WWW_ROOT, $jsBase, $item);
        })->toArray();
        
        return static::$_file_list[$type.'.'.$name];
    }

    /**
     * Get the configuration item for this site and language. If not found, return 
     * empty configuration with modified timestamp 0
     * @param int $site_id
     * @param string $language
     * @param string $theme Full, without extension (E.g. Theme_default)
     * @return Config
     */
    public static function getThemeCssFromDatabase(int $site_id, string $language, string $theme) : Config
    {
        $options = [
            'auto' => FALSE, 
            'site_id' => $site_id,
            'language' => $language
        ];
         
        $Configs = TableRegistry::getTableLocator()->get('Configs');
        
        $query = $Configs
                ->find()
                //->find('ForSiteAndLanguage', ['site_id'=>$site_id, 'language'=>$language])
                ->where(["name"=>sprintf("%s.css", $theme)])
                ;
        
        $data = $Configs->getWithDefaults($query, $options);
        
        if (empty($data)) {
            $data = ['modified'=>0, 'name'=>$theme.'.css', 'site_id'=>$site_id, 'language'=>$language];
        } else {
            return $data[0];

        }
        
        return new Config($data);
        
        return $config;
    }
    
    /**
     * 
     * Compile a specific file using sass compiler from NodeJS
     * @param string $scss Extra content to parse
     * @param string $filename
     * @param string $style default compressed
     * @return bool
     */
    public static function compileSass(string $scss, $style = 'compressed') : bool
    {
        $success = TRUE;
        
        // loop over all possible stylesheets and sass compile them
        foreach (static::getSassFilesFromDirectory() as $source_file) {
            
            // make file to create per site/language
            $file_to_create = static::getFileNameFor($source_file, $config->site_id, $config->language, str_replace('.css','', $config->name));
            $file_to_create = str_replace(['/sass/','scss'], ['/webroot/css/compiled/','css'], $file_to_create);
                    
            $compiler = new SassCompiler($file_to_create);
            $returnCode = $compiler->compile($config->value, [$source_file]);
            if ($returnCode !== TRUE) {
                throw new Exception(json_encode($returnCode));
            }
        }
        
        return $success;
    }   
    
    /**
     * Helper to update a site/language combination defined by $config, instead
     * of calling various functions sequentially
     
     * @param string $scss
     * @param string $filename
     * @param string $style default compressed
     * @return bool
     */
    public static function compileAndMinifyFor(Config $config, $style = 'compressed') : bool
    {
        $success = TRUE;
        
        $Compressor = new QueueStylesheetsTask();
        
        $success = $success && self::compileSassForConfig($config, $style);
        
        $theme_name = str_replace('.css', '', $config->name); // e.g. Theme_default
        $collection = 'default'; // the collection to compile, could become more in future
        $success = $success && $Compressor->minify($collection, $config->site_id, $config->language, $theme_name);
        
        return $success;
    } 
    
    /**
     * Return the *.scss files from the Minify.Sass.path directory in app.php
     * @return array
     */
    protected static function getSassFilesFromDirectory() : array
    {
        $return_list = [] ;
        $folder = new Folder(Configure::read('Minify.Sass.path'));
        foreach ($folder->find('.*\.scss') as $filename) {
            array_push($return_list, Configure::read('Minify.Sass.path') . DS . $filename);
        }
        
        return $return_list;
    }
}
