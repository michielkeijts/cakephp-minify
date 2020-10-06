<?php
/**
 * Sass Compilation task for CakePHP projects
 * 
 * 
 * (C) Michiel Keijts, 2018
 * 
 */

namespace CakeMinify;

use CakeMinify\Stylesheets\SassCompiler;
use CakeMinify\Stylesheets\CssMinifier;
use CakeMinify\Scripts\JsMinifier;
use Cake\Core\Configure;
use Exception;
use CakeMinify\Minify\Helper;

/**
 * Updates the CSS files by copmpiling SASS
 */
class CakeMinify
{
    /**
     * Compiles sass files defined in CakeMinify.Stylesheets
     * Output JSON from compiler 
     * @param array $content ['before'=>.., 'after'=>..]
     * @param string $filename
     * @param string $baseDir To overwrite the path gives opportunity to create multiple versions in subdirs
     * @param string $outputStyle
     * @return stdClass error info containing success parameter
     */
	public static function compileSass ($content, string $filename,  string $baseDir = "", string $outputStyle ='compressed') 
    {
        if (is_string($content)) {
            $content = ['before' => $content];
        }
        
        $outputFilepath = sprintf('%s%s', self::getCssBaseDir(), $baseDir);
        
        if (!file_exists($outputFilepath)) {
            if (!mkdir($outputFilepath,0777, true)) {
                throw Exception("Could not create directories {$outputFilepath}");
            }
        }       
        
        $list_of_files = Helper::getSassFilesFromDirectory(Configure::read("CakeMinify.Sass.path"));
        
        foreach ($list_of_files as $filename=>$filepath) {
            $outputFilename = $outputFilepath . str_replace('.scss', '.css', $filename);
        
            $compiler = new SassCompiler($outputFilename, $outputStyle);
            
            $message = $compiler->compile($content, [$filepath]);
            if ($message->success !== TRUE) {
                return $message;
            }
        }
        
        return $message;
    }
    
    /**
     * Create CSS Files
     * @param string $filename
     * @param string $baseDir
     */
    public static function createCssFiles (string $filename, string $baseDir = "") 
    {
        $baseDir = self::getCssBaseDir() . $baseDir;
        
        $minifier = new CssMinifier($baseDir);
        
        return $minifier->minify($filename, $filename);
    }
    
    /**
     * Create CSS Files
     * @param string $filename
     * @param string $baseDir
     */
    public static function createJsFiles (string $filename, string $baseDir = "", bool $createGzip = FALSE, int $timestamp = 0 ) : string
    {
        $baseDir = self::getJsBaseDir() . $baseDir;
        
        $minifier = new JsMinifier($baseDir);
        
        return $minifier->minify($filename, $filename, $createGzip, $timestamp);
    }
	
    /**
     * @return string the css base dir
     */
    public static function getCssBaseDir() : string
    {
        return sprintf('%s%s%s', 
                WWW_ROOT, 
                Configure::read('App.cssBaseUrl', "css"),
                Configure::read('CakeMinify.outputDir', "compiled")
            );
    }
    
    /**
     * @return string the js base dir
     */
    public static function getJsBaseDir() : string
    {
        return sprintf('%s%s%s', 
                WWW_ROOT, 
                Configure::read('App.jsBaseUrl', "css"),
                Configure::read('CakeMinify.outputDir', "compiled")
            );
    }
}

