<?php
/**
 * JKM Shell
 * 
 */

namespace App\Shell\Task;

use App\Shell\Task\QueueTask;
use tubalmartin\CssMin\Minifier;
use Cake\Core\Configure;
use App\Helper\Minify;
use App\Model\Entity\Site;

/**
 * Updates the CSS files 
 * @property \App\Model\Table\SitesTable $Sites Sites Table
 */
class QueueStylesheetsTask extends QueueTask
{
	/**
	 * {@inheritDoc}
	 */
	protected static $CRONRULE = '0 * * * *';
	
	/**
	 * Checks the current database file and the creation date. if before the 
	 * current stylesheet, update the CSS file. 
	 * - auto sync (rsync) on the server is used to update the file
	 * 
	 * @return bool
	 */
	public function main(array $data = [], $jobId = NULL):bool
	{
        $this->loadModel('Sites');
	
        // loop over all sites/languages and check the filetime of css files
		$this->out("Looping through all sites..");
        $this->setProgressMax(10);
		foreach ($this->Sites->find() as $site) {
            $this->out("Stepping in to {$site->name} with ID {$site->id}");
            
            $language = Site::$DEFAULT_LANGUAGE;
            foreach (Configure::read('Minify.Themes') as $theme) {
                $theme = sprintf('Theme_%s', $theme);     
                $minify_time = Minify::get('Stylesheets', $site->id, $language, $theme, 0);
                $timestamp = Minify::getMaxTimestampFor('Stylesheets', 'default', $site->id, $language, $theme);

                $filename = Minify::getFileNameFor('default.css', $site->id, $language, $theme, $minify_time);

                if (!file_exists($filename) || $minify_time < $timestamp) {
                    // execute minfy
                    $this->updateProgress();
                    $this->minify('default', $site->id, $language, $theme);
                }
            }
        }
        
        // save
		$this->updateProgress(1);
        
        return TRUE;
    }
    
    /**
     * Actual minify command. Combines and Minifies the css files into one file
     * @param string $collection
     * @param int $site_id
     * @param string $language
     * @param string $theme
     * @return boolean
     */
    public function minify(string $collection, int $site_id, string $language, string $theme)
    {
        $this->params['force'] = TRUE;
        
        $filesToMerge = [];
		
        foreach (Configure::read('Minify.Stylesheets.'.$collection) as $filename) {
            $filenameForSiteLanguage = Minify::getFileNameFor($filename, $site_id, $language, $theme, 0, TRUE);
            if (!file_exists($filenameForSiteLanguage)) {
                $this->out("The file {$filenameForSiteLanguage} could not be found. Run sass compiler again?");
                continue;
            }
            $filesToMerge[] = $filenameForSiteLanguage;
        }
        
        if (empty($filesToMerge)) {
            $this->out("Could not find any file to merge. Skipping for site/language {$site_id} {$language}");
        }
        
        // CSS Dir
		$cssSourcedir = WWW_ROOT . Configure::read('App.cssBaseUrl');
        $compressor = new Minifier();
        
        $success = TRUE;
        $timestamp = Minify::getMaxTimestampFor('Stylesheets', $collection, $site_id, $language, $theme);

        $contents = $this->getConcatenatedContentOfFiles($filesToMerge);

        $newFilename = Minify::getFileNameFor($collection . '.css', $site_id, $language, $theme, $timestamp);
        $newFilePath = sprintf('%scompiled/', $cssSourcedir, $newFilename);

        // compress, save and gzip
        $output_css = $compressor->run($contents);
        $success = $success && $this->createFile($newFilePath . $newFilename, $output_css);	
        $success = $success && $this->createGzipFile($newFilePath . $newFilename, $output_css);

        if ($success) {
            Minify::set('Stylesheets', $site_id, $language, $theme, $timestamp);
            //$this->deleteOldFilesFromFolder($newFilePath, $collection, 'css');
        }
        return true;
	}
}
