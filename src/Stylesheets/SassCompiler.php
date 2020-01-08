<?php
/*
 * @copyright (C) 2018 Michiel Keijts, Normit
 * 
 */
namespace CakeMinify\Stylesheets;

use Cake\Filesystem\File;
use Cake\Http\Exception\NotFoundException;
use Cake\View\View;
use Cake\Log\Log;
use Cake\Core\Configure;


/**
 * Compiles sass content using a node-sass compiler
 *
 * @author michiel
 */
class SassCompiler {
    
    /**
     * View for rendering
     * 
     * @var View
     */
    private $_view;
    
    /**
     * Name of the viewFile to render the node-sass command
     * @var string
     */
    public $viewFile = 'CakeMinify.Node/sass_compile';
    
    /**
     * Name of the outputFile
     * @var string 
     */
    private $_outputFilename = "";
    
    /**
     * Compression style
     * @var string
     */    
    private $_outputStyle = "";
    
    /**
     * SASS Content to compile
     * @var string 
     */
    private $_content = "";
    
    /**
     * Create compiler
     * @param $outputFilename the output filename (should have .css extension
     * @param $outputStyle  default ('crunched')
     */
    public function __construct(string $outputFilename, string $outputStyle = NULL) {
        $this->_view = new View();
        $this->_outputFilename = $outputFilename;
        $this->_outputStyle = empty($outputStyle) ? Configure::read('CakeMinify.sass.outputStyle') : $outputStyle;
    }  
    
    /**
     * Compile execute
     * @return string json empty if ok, or sass error. 
     */
    public function compile($content = "", $list_of_files = []) 
    {
        // add correct path
        if (substr(reset($list_of_files),0,1) !== DS) {
            $sassPath = Configure::read('CakeMinify.Sass.path');
        
            // set in correct path and scss extension
            foreach ($list_of_files as &$file) {
                $file = $sassPath . str_replace('.css','.scss',$file);
            }       
        }
        
        $this->setContent($content, $list_of_files);
        $this->setViewVariables();
        $node_sass_content = $this->_view->render($this->viewFile,'ajax');
        return $this->execute($node_sass_content);
    }
    
    private function setViewVariables () : bool 
    {
        $data = new \stdClass();
        $data->outputFilename = $this->_outputFilename;
        $data->outputStyle = $this->_outputStyle;
        $data->includePaths = [Configure::read('CakeMinify.Sass.path')];
        $data->data = $this->getContent();
                
        $this->_view
                ->set('data', $data)
                ->set('outputFilename', $this->_outputFilename);
                
        return TRUE;
    }
    
    /**
     * Execute the node-sass compiler
     * @param type $node_sass_content
     * @return \stdClass json decodeded string (empty in case no error)
     */
    private function execute($node_sass_content)
    {
        $executableFile = static::getTmpFileForContent($node_sass_content);

        // execute the file
        $output = [];
        $return_code = 0;
        $cwd = getcwd();
        chdir(ROOT);
        
        exec("node {$executableFile}", $output, $return_code);
    
        chdir ($cwd);
        
        // remove tmp file
        unlink($executableFile);

        if ($return_code > 0) {
            Log::error("Sass Compiler Error: \n" . implode("\n", $output));
            $error = json_decode($this->formatErrorAsJSON($output));
            return $error;
        }
        
        return json_decode("{\"success\":true}");
    }
    
    /**
     * Formats the error to a readable JSON string. This helps debugging
     * - Indicates line where the error occured
     * 
     * @param array $err
     * @return string JSON 
     */
    private function formatErrorAsJSON(array $err) : string
    {
        // when not starting with { stop
        if ($err[0]{0} !== '{') 
            return false;
        
        // remove first line
        $error = '{'.implode('', array_slice($err, 1));
        
        if (preg_match_all('/([a-z0-9]+):\s?([^,]+)/i', $error, $output_array) === FALSE)
            return "{success:false}";
        
        array_pop($output_array[1]);
        array_pop($output_array[2]);
        foreach ($output_array[1] as &$i) {
            $i = str_replace(["'",'\\"'],["","'"],$i);
        }
        foreach ($output_array[2] as &$i) {
            $i = str_replace(["'",'"'],["","'"],$i);
        }
        
        $obj = json_decode(json_encode(array_combine($output_array[1], $output_array[2])));
        
        $obj->success = FALSE;
        
        if (json_last_error() !== 0) {
            return "{success:false}";
        }
        
        return json_encode($obj);
    }
    
    /**
     * Get the content string
     * @return string
     */
    public function getContent() : string
    {
        return $this->_content;
    }
    
	/**
     * Set contents to $content + content of files
     * @param string $content
     * @param array $list_of_files
     * @return string
     * @throws NotFoundException
     */
    private function setContent(string $content, array $list_of_files) : string
    {
        $this->appendContent($content);
        foreach ($list_of_files as $filename) {
            $fcontent = file_get_contents($filename);
            if ($fcontent !== FALSE) {
                $this->appendContent($fcontent);
            } else {
                throw new NotFoundException("Could not find file: {$filename}");
            }
        }    

        return $this->_content;
    }
	
    /**
     * Append content to the content holder
     * @param string $content
     * @return string
     */
    public function appendContent(string $content) : string
    {
        $this->_content = sprintf("%s\n%s", $this->_content, $content);
        
        return $this->_content;
    }

    /**
     * Save the content to a file and return filename. This is the executable 
     * for the NodeJS
     * @param string $content
     * @return string
     */
    private function getTmpFileForContent($content) : string
    {
        $tmpFileName = sprintf('%s%s', TMP, uniqid());
        $tmpFile = new File($tmpFileName);
        
        
        $tmpFile->write($content);  
        
        $tmpFile->close();
        
        return $tmpFileName;
    }           
}
