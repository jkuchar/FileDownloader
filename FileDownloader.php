<?php

/**
 * FileDownloader is a small library to make a comfort,
 * fast and simple downloading of files.
 *
 * It supports:
 *  + partial downloads
 *  + speedlimits
 *  + auto mime type detection
 * 
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař (http://mujserver.net)
 * @link        http://www.php.net/manual/en/function.readfile.php#86244 Link to the original script
 * @license     http://www.gnu.org/copyleft/lesser.html Lesser General Public License (LGPL)
 * @version     1.0.0 alfa
 */
class FileDownloader extends Object
{
  /**
   * Maximal speed of download (in kb/s)
   * 0 is unlimited
   * @var int
   */
  static public $maxDownloadSpeed = 0;

  /**
   * Terminate sript when file download is completed?
   * @var bool
   */
  static public $autoTerminate = TRUE;

  /**
   * Kbytes sent in one cycle. (for speed tuning)
   * @var <type>
   */
  static public $stepSize = 64;

	/**
	 * Static class - cannot be instantiated.
	 */
	final public function __construct()
	{
		throw new LogicException("Cannot instantiate static class " . get_class($this));
	}

  /**
   * Show "download dialog" in browser for required file
   *  + Supports partial downloads
   *  + Supports speedlimits
   *  + Supports auto mimetype detection
   *  + Very low server stress (very fast script)
   *  + Auto switch:
   *    + readfile() (compatibility mode)
   *    + internal readfile function - supports
   *      partial downloads and is faster than readFile()
   *
   * @param string $fileLocation  Location of source file
   * @param string $filename      File name with witch will be file sent to browser. for example: "test.jpg"
   * @param string $mimeType      Mimetype of the file.
   */
  static function readFile($fileLocation, $filename=null, $mimeType=null)
  {
    if(!file_exists($fileLocation))
      throw new InvalidArgumentException("File not found!",404);

    if(!is_file($fileLocation))
      throw new InvalidStateException("The specified location do not point at file!");

    if(!is_readable($fileLocation))
      throw new InvalidStateException("File is not readable!");

    if(self::$maxDownloadSpeed<0)
      throw new InvalidArgumentException("Download rate must be a non-negative number. ( \$dr>=0 )");

    if($filename===null)
      $filename = pathinfo($fileLocation, PATHINFO_BASENAME);

    if($mimeType === null)
      $mimeType = self::getMimeType($filename);

    $fastDownload = self::configEnvironment();

    if($fastDownload === FALSE and self::$maxDownloadSpeed!==0)
      throw new InvalidStateException("set_time_limit or ini_set function must be allowed when you want to use speed limits. You must disable speed limits to be able to use this function without set_time_limit or ini_set function.");
    
    $size = filesize($fileLocation);
    $time = date('r',filemtime($fileLocation)); // Modification time
    
    /**
     * File resource
     */
    $fm=fopen($fileLocation,'rb');
    if(!$fm)
      throw new InvalidStateException("Can't open the file!");

    $begin=0;
    $end=$size;

    if(isset($_SERVER['HTTP_RANGE']) and $fastDownload === TRUE) {
      if(preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)){
        $begin=intval($matches[0]);
        if(!empty($matches[1]))
          $end=intval($matches[1]);
      }
    }

    $httpResponse = Environment::getHttpResponse();
    $httpResponse->setContentType($mimeType);

    if(($begin>0||$end<$size))
      $httpResponse->setCode(206); // PARTIAL CONTENT
    else
      $httpResponse->setCode(IHttpResponse::S200_OK);

    $httpResponse->setHeader('Expires', '0');
    $httpResponse->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    $httpResponse->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
    $httpResponse->setHeader('Pragma', 'public');
    //$httpResponse->setHeader('Accept-Ranges', 'bytes');
    $httpResponse->setHeader('Accept-Ranges', '0-'.($end-$begin));
    $httpResponse->setHeader('Content-Length', ($end-$begin));
    $httpResponse->setHeader('Content-Range', 'bytes '.$begin."-".$end."/".$size);
    $httpResponse->setHeader('Content-Disposition', 'attachment; filename='.$filename);
    $httpResponse->setHeader('Content-Description', 'File Transfer');
    $httpResponse->setHeader('Content-Transfer-Encoding', 'binary');
    $httpResponse->setHeader('Last-Modified', $time);
    $httpResponse->setHeader('Connection', 'close');
    $httpResponse->setHeader('ETag', self::getETag($fileLocation));

    $speedLimit = false;
    $step       = self::$stepSize;
    if(self::$maxDownloadSpeed !== 0)
    {
      $speedLimit = true;
      $step       = self::$maxDownloadSpeed;
    }

    if($fastDownload === TRUE){
      $cur=$begin;
      fseek($fm,$begin,0);
      while(!feof($fm)&&$cur<$end&&(connection_status()==0)) {
        echo fread($fm,min(1024*$step,$end-$cur));
        $cur+=1024*$step;
        flush();
        if($speedLimit == TRUE) sleep(1);
      }
    }else{ // Přečteme soubor standardním způsobem - nepodporuje omezování rychlosti
      readfile($fileLocation);
    }

    if(self::$autoTerminate === TRUE)
      die();
  }

  /**
   * Identifies a most used mime types
   * @param string $location Location to file
   * @return string Mime type
   */
  public static function getMimeType($location){
    $ext = String::lower(pathinfo($location, PATHINFO_EXTENSION));
    switch ($ext) {
      case "pdf": return "application/pdf";break;

      case "doc": return "application/msword";break;
      case "xls": return "application/vnd.ms-excel";break;
      case "ppt": return "application/vnd.ms-powerpoint";break;
      case "pps": return "application/vnd.ms-powerpoint";break;

      case "gif": return "image/gif";
      case "png": return "image/png" ;break;
      case "jpg": case "jpe": case "jpeg":
        return "image/jpeg";break;

      case "zip": return "application/zip";break;

        case "mp3": return "audio/mpeg";break;
        case "wav": return "audio/x-wav";break;

        case "wmv": return "video/x-ms-wmv";break;
        case "mpeg":return "video/mpeg";break;
        case "mpg": return "video/mpeg";break;
        case "mpe": return "video/mpeg";break;
        case "mov": return "video/quicktime";break;
        case "avi": return "video/x-msvideo";break;
      default:
        return "application/octet-stream";break;
    }
  }

  /**
   * Generates ETag and returns
   * @param string $location    Location to source file
   * @return string             ETag
   */
  private static function getETag($location){
    return "\"".md5($location.filemtime($location).filesize($location))."\"";
  }

  /**
   * Config environment for downloading files
   * @return bool
   */
  private static function configEnvironment(){
    if(function_exists("set_time_limit")){
      @set_time_limit(0);
    }elseif(function_exists("ini_set")){
      @ini_set("max_execution_time", 0);
    }

    $return = TRUE;
    if(!function_exists("ini_get"))
      throw new InvalidStateException("Function ini_get must be allowed.");

    if((int)@ini_get("max_execution_time") !== 0)
      $return = FALSE;
    return $return;
  }
}