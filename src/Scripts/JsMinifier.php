<?php
/* 
 * @copyright (C) 2020 Michiel Keijts, Normit
 * 
 */

namespace CakeMinify\Scripts;

use Cake\Core\Configure;
use JShrink\Minifier;
use CakeMinify\Minify\Helper;
use Exception;

class JsMinifier {
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
     * Minfies the Script files listed in CakeMinify.Scripts.$filename Configuration
     * @param string $filename
     * @param string $outputFilename
     * @param bool $createGzip
     * @param int $checkTimestamp
     * @return string new Filename
     * @throws Exception
     */
    public function minify(string $filename, string $outputFilename, bool $createGzip = FALSE, int $checkTimestamp = -1 ): string
    {
		$compressor = new Minifier();
		
		// JS Dir
		$jsSourcedir = WWW_ROOT . Configure::read('App.jsBaseUrl');
	
        // temp fix as sass compiler compiles and minifies aleady
        foreach (Configure::read('CakeMinify.Scripts.'.$filename) as $filename) {
            $filePath = sprintf('%s%s', $this->baseDir, $filename);
            
            if (!file_exists($filePath)) {
                $filePath = sprintf('%s%s%s', WWW_ROOT, Configure::read('App.jsBaseUrl'), $filename);
                if (!file_exists($filePath)) {
                    throw new Exception("The file {$filePath} could not be found. Check JS configuration");
                    continue; 
                }                
            }
            
            $filesToMerge[] = $filePath;
        }
        
        $timestamp = Helper::getTimestampSumOfFiles($filesToMerge);
        $newFilename = $this->generateTimestampFilename($outputFilename, $timestamp);
        $newFilePath = sprintf('%s%s', $this->baseDir, $newFilename);
        
        // check timestamp if necessary to change the files
        if ($checkTimestamp >= 0 
                && $checkTimestamp <= $timestamp
                && file_exists($newFilePath)) {
            
            return $newFilename; // do not update
        } 
        
        $contents = Helper::getConcatenatedContentOfFiles($filesToMerge);       

        // compress, save and gzip
        $output_js = $compressor->minify($contents);
        
        Helper::createFile($newFilePath, $output_js);	
            
        if ($createGzip !== FALSE) {
            Helper::createGzipFile($newFilePath, $output_js);
        }
        
        return $newFilename;
    }
    
    /**
     * Returns the $timestamped filename
     * @param string $filename
     * @param int $timestamp
     * @return string
     */
    private function generateTimestampFilename(string $filename, int $timestamp) : string
    {
        $newFilename = sprintf('%s%d.js', $filename, $timestamp);
        
        return $newFilename;
    }
}