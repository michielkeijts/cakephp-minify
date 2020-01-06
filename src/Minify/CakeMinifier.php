<?php
/**
 * Sass Compilation task for CakePHP projects
 * 
 * 
 * (C) Michiel Keijts, 2018
 * 
 */

namespace CakeMinifier\Minify\Minifier;

use CakeMinifier\Stylesheets\SassCompiler;
use App\Shell\Task\CssMinifier;
use Cake\Core\Configure;
use Cake\I18n\Time;
use Exception;

/**
 * Updates the CSS files by copmpiling SASS
 */
class CakeMinifier
{
    /**
     * Compiles sass files defined in CakeMinify.Stylesheets
     * Output JSON from compiler 
     * @param string $content
     * @param string $filename
     * @param string $baseDir To overwrite the path gives opportunity to create multiple versions in subdirs
     * @param string $outputStyle
     * @return string json
     */
	public static function compileSass (string $content, string $filename,  string $baseDir = "", string $outputStyle ='compressed') : string
    {
        $outputFilepath = sprintf('%s%s', self::getCssBaseDir(), $baseDir);
        
        if (!file_exists($outputFilepath)) {
            if (!mkdir($outputFilepath,0777, true)) {
                throw Exception("Could not create directories {$outputFilepath}");
            }
        }
        
        $outputFilename = $outputFilepath + $filename;
        
        $compiler = new SassCompiler($outputFilename, $outputStyle);
        
        $list_of_files = Configure::read("CakeMinify.StyleSheets.{$filename}");
        
        return $compiler->compile($content, $list_of_files);
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
        
        $minifier->minify($filename);
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
}

