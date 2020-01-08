<?php
/**
 * JKM Shell
 * 
 */

namespace CakeMinify\Stylesheets;

use tubalmartin\CssMin\Minifier;
use Exception;
use CakeMinify\Minify\Helper;
use Cake\Core\Configure;

/**
 * Updates the CSS files 
 * @property \App\Model\Table\SitesTable $Sites Sites Table
 */
class CssMinifier
{
    /**
     * The Basedir for the minifier
     * @var string
     */
    private $baseDir = "";
    
    /**
     * 
     * @param string $baseDir
     */
    public function __construct(string $baseDir = "") {
        $this->baseDir = $baseDir;
    }
    
    /**
     * Actual minify command. Combines and Minifies the css files into one file
     * @param string $collection
     * @param int $site_id
     * @param string $language
     * @param string $theme
     * @return string name of minified file
     */
    public function minify(string $filename, string $outputFilename, bool $createGzip = FALSE) : string
    {
        $filesToMerge = [];
		
        // temp fix as sass compiler compiles and minifies aleady
        foreach (Configure::read('CakeMinify.Stylesheets.'.$filename) as $filename) {
            $filePath = sprintf('%s%s', $this->baseDir, $filename);
            
            if (!file_exists($filePath)) {
                $filePath = sprintf('%s%s%s', WWW_ROOT, Configure::read('App.cssBaseUrl'), $filename);
                if (!file_exists($filePath)) {
                    throw new Exception("The file {$filePath} could not be found. Run sass compiler again?");
                    continue; 
                }                
            }
            
            $filesToMerge[] = $filePath;
        }
        
        if (empty($filesToMerge)) {
            throw new Exception("The file {$filePath} could not be found. Run sass compiler again?");
        }
        
        // cssDir
		$compressor = new Minifier();
        
        $success = TRUE;
        $timestamp = time();

        $contents = Helper::getConcatenatedContentOfFiles($filesToMerge);
        $output_css = $compressor->run($contents);
        
        $newFilename = sprintf('%s%s.css', $outputFilename, $timestamp);
        $newFilePath = sprintf('%s%s', $this->baseDir, $newFilename);

        // compress, save and gzip
        $success = $success && Helper::createFile($newFilePath, $output_css);	
        if ($createGzip) {
            $success = $success && Helper::createGzipFile($newFilePath, $output_css);
        }

        if (!$success) {
            throw new Exception("Failed creating a minified CSS version {$newFilePath}");
        }
        
        return $newFilename;
	}
}
