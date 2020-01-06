<?php
/**
 * JKM Shell
 * 
 */

namespace App\Shell\Task;

use App\Shell\Task\QueueTask;
use JShrink\Minifier;
use Cake\Core\Configure;
use App\Helper\Minify;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Site;

/**
 * Updates the JS files 
 */
class QueueScriptsTask extends QueueTask
{
	/**
	 * {@inheritDoc}
	 */
	protected static $CRONRULE = '0 0 * * *'; // every day 0:00
	
	/**
	 * Checks the current database file and the creation date. if before the 
	 * current javscript file, update the Javascript file. 
	 * - auto sync (rsync) on the server is used to update the file
	 * 
	 * @return void
	 */
	public function main(array $data = [], $jobId = NULL):bool
	{
		$compressor = new Minifier();
		
		// JS Dir
		$jsSourcedir = WWW_ROOT . Configure::read('App.jsBaseUrl');
		
		// check if newer file exists
		$this->out("Checking JS filemtime..");
		
		// files to create -> which stylesheets to create
		$filesToCreate = Configure::read('Minify.Scripts');
		foreach ($filesToCreate as $jsFilename=>$filesToMerge) {
            foreach (Configure::read('Minify.Themes') as $theme) {
                $theme = sprintf('Theme_%s', $theme);   
            
                $timestamp = $this->getTimestampSumOfFiles($filesToMerge, $jsSourcedir);

                // get config for current file, for all sites. Expensive loop, but does not need to run that often
                foreach (TableRegistry::getTableLocator()->get('Sites')->find() as $site) {
                    $oldTimestamp = Minify::get('Scripts', $site->id, Site::$DEFAULT_LANGUAGE, $theme, 0);
                    $oldFilename = Minify::getFileNameFor($jsFilename . '.js', $site->id, Site::$DEFAULT_LANGUAGE, $theme, $oldTimestamp);
                    $newFilePath = sprintf('%scompiled/', $jsSourcedir);
                    
                    $timestampCheck = $timestamp > $oldTimestamp;
                    $fileNotExistsCheck = !file_exists($newFilePath . $oldFilename);
                    
                    if ($timestampCheck || $fileNotExistsCheck) {
                        $contents = $this->getConcatenatedContentOfFiles($filesToMerge, $jsSourcedir);                       

                        $newFilename = Minify::getFileNameFor($jsFilename . '.js', $site->id, Site::$DEFAULT_LANGUAGE, $theme, $timestamp);

                        // compress, save and gzip
                        $output_js = $compressor->minify($contents);
                        $this->createFile($newFilePath . $newFilename, $output_js);	
                        $this->createGzipFile($newFilePath . $newFilename, $output_js);

                        // set for site
                        Minify::set('Scripts', $site->id, Site::$DEFAULT_LANGUAGE, $theme, $timestamp);

                        // remove all files older than one week and not the current and previous sheet
                        //$this->deleteOldFilesFromFolder($newFilePath, $jsFilename, 'js');
                    } else {
                        $this->out(sprintf('No need to update JS files. File %s exists.', $oldFilename));
                    }
                }
            }
		}
		
		return true;
	}
}

