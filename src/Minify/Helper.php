<?php
/*
 * (C) Normit, 2018, Michiel Keijts
 */

namespace CakeMinify\Minify;

use Cake\Filesystem\File;
use Cake\Core\Configure;
use Exception;
use App\Model\Entity\Config;
use Cake\Filesystem\Folder;

/**
 * Minifyhelper is a static class helping with the minification of the assets
 * 
 * @author Michiel Keijts, Normit
 */
class Helper {
    
    /**
	 * Create a compressed file
	 * @param string $filename
	 * @param mixed $contents
	 */
	public static function createGzipFile (string $filename, $contents):bool
	{
		$filename .= '.gz'; // add gz exension
        $this->out(sprintf('Creating file %s', $filename));
		$file=gzopen($filename, 'w9');
		gzwrite($file, $contents);
		gzclose($file);
		
		return file_exists($filename);
	}
    
     /**
     * Creates a file at given path
     *
     * @param string $path Where to put the file.
     * @param string $contents Content to put in the file.
     * @param bool $overwrite (default TRUE)
     * @return bool Success
     */
    public static function createFile($path, $contents, $overwrite = TRUE) : bool
    {
        $fileExists = is_file($path);
        
        if ($fileExists && !$overwrite) {
            throw new Exception("File Exists and not allowed to overwrite");
        }

        $File = new File($path, true);

        try {
            if ($File->exists() && $File->writable()) {
                $File->write($contents);

                return true;
            }
        } finally {
            $File->close();
        }
        
        return false;
    }
    
    /**
	 * Get the concatenated content of all files
	 * @param array $files
	 * @param string $path
	 * @return string
	 */
	public static function getConcatenatedContentOfFiles(array $files) : string
	{
		$content = "";
		foreach ($files as $file) {
			$content = sprintf("%s\n\n%s", $content, file_get_contents($file));
		}
		
		return $content;
	}
    
    /**
     * Return the *.scss files from the $folder directory
     * @param string $folder;
     * @return array
     */
    public static function getSassFilesFromDirectory(string $folder) : array
    {
        $return_list = [] ;
        $Folder = new Folder($folder);
        foreach ($Folder->find('.*\.scss') as $filename) {
            $return_list[$filename] = $folder . $filename;
        }
        
        return $return_list;
    }
    
    /**
	 * Get the sum of all files' creationtime
	 * @param array $files
	 * @param string $path
	 * @return int
	 */
	public static function getTimestampSumOfFiles(array $files) : int
	{
		$timestamp = 0;
		// timestamp all files and add
		foreach ($files as $file) {
			$timestamp+= filemtime($file);
		}
		
		return $timestamp;
	}
    
    
    /**
     * Save the content to a file and return filename. This is the executable 
     * for the NodeJS
     * @param mixed $content
     * @param string $filename
     * @return string
     */
    public static function getTmpFileForContent($content, string $filename = null) : string
    {
        if (empty($filename)) {
            $filename = uniqid();
        }
        
        $tmpFileName = sprintf('%s%s', TMP, $filename);
        $tmpFile = new File($tmpFileName);
        
        $tmpFile->write($content);  
        
        $tmpFile->close();
        
        return $tmpFileName;
    }   
}
