<?php
/**
 * Sass Compilation task for CakePHP projects
 * 
 * 
 * (C) Michiel Keijts, 2018
 * 
 */

namespace App\Shell\Task;

use App\Shell\Task\QueueTask;
use App\Model\Entity\Config;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\Filesystem\File;
use Exception;
use App\Helper\Minify;
use Cake\I18n\Time;
use App\Model\Entity\Site;

/**
 * Updates the CSS files by copmpiling SASS
 */
class QueueSassTask extends QueueTask
{
	protected $defaultFormatter = 'Crunched';
	
	/**
	 * @inheritDoc
	 */
	protected static $CRONRULE = '0 * * * *'; // every hour

    public function initialize() {
        parent::initialize();
        
        $this->loadModel('Configs');
    }
    
	/**
	 * Compiles to SASS
     * 
     * First all the sites are loaded and checked if the timestamp of the 
     * theme.css (ConfigsTable) is changed. If so, sass is recompiled.
     * 
     * Otherwise, use force (true) as first argument 
     *
	 * 
	 * @return bool
	 */
	public function main(array $data = [], $jobId = NULL):bool
	{
        list($overwrite, $formatter) = $this->parse_arguments($this->args);

        $overwrite = $overwrite ?? FALSE;
        $formatter = $formatter ?: 'compressed';
        
               
        // loop over all sites/languages, checking if the modified version is newer or overwrite
        $this->loadModel('Sites');
	
        // loop over all sites/languages and check the filetime of css files
		$this->out("Looping through all sites..");
		foreach ($this->Sites->find() as $site) {
            $this->out("Stepping in to {$site->name} with ID {$site->id}");
            
            $language = Site::$DEFAULT_LANGUAGE;
            
            foreach (Configure::read('Minify.Themes') as $theme) {
                $theme = sprintf('Theme_%s', $theme);                
                $config = Minify::getThemeCssFromDatabase($site->id, $language, $theme);

                // we check if config modified is larger than minify timestamp
                $timestamp = Minify::get('Stylesheets', $site->id, $language, $theme, 0);

                // check if we overwrite or config is newer version
                if ($overwrite || empty($config->modified) || $config->modified->i18nFormat(Time::UNIX_TIMESTAMP_FORMAT) > $timestamp) {
                    Minify::compileSassForConfig($config, $formatter);
                }
            }
        }
        
		return true;
	}
	
    /**
     * Parse command line input into 2 variables
     * @param array $args
     * @return array
     */
    private function parse_arguments($args = []) : array
    {
        if (count($args) <=0) {
            return [NULL,NULL];
        }
        if (strtolower($args[0]) == 'queuesass') {
            array_shift($args);
        }
        
        switch (count($args)) {
            case 2:
                return [$args[0], $args[1]];
            case 1:
                return [$args[0], NULL];
        }
        
        return [NULL, NULL];
    }
}

