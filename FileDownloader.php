<?php

/**
 * This source file is subject to the "Nette license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://nettephp.com
 *
 * @author     Jan Kuchař
 * @copyright  Copyright (c) 2009 Jan Kuchař (http://mujserver.net)
 * @license    http://nettephp.com/license Nette license
 * @link       http://mujserver.net
 */

/**
 * FileDownloader is a small library to make a comfort,
 * fast and simple downloading of files.
 * It supports:
 *  + partial downloads
 *  + speed limits
 *  + auto mime type detection
 * Original script from:  http://www.thomthom.net/blog/2007/09/php-resumable-download-server/
 * Original script autor: Thomas Thomassen
 *
 *
 * @name        File Downloader
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař
 * @author      Jan Kuchař
 * @version     $Id$
 */
class FileDownloader extends Object
{
  /**
   * Maximal speed of download (in kb/s)
   * 0 is unlimited
   * @var int
   */
  public static $maxDownloadSpeed = 0;

  /**
   * Kbytes sent in one cycle. (for speed tuning)
   * This tweak work only when speed limiter is disabled!
   * @var int
   */
  public static $stepSize = 16;

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
   *  + Supports speed limits
   *  + Supports auto mimetype detection (from config ini or from fileinfo php extension)
   *  + Lower server stress (upload func. is faster than readfile)
   *  + Auto switch:
   *    + readfile() (compatibility mode)
   *    + internal readfile function - supports
   *      partial downloads and is faster than readFile()
   *
   * @param string $location  Location of source file
   * @param string $filename      File name with witch will be file sent to browser. for example: "test.jpg"
   * @param string $mimeType      Mimetype of the file.
   * @param int    $speedLimiter  Limits file download speed
   * @param string $terminate     Terminate script after download completes
   */
  static function download($location, $filename=null, $mimeType=null,$speedLimiter=null,$terminate=true)
  {
    if($speedLimiter===null or $speedLimiter<0 or !is_int($speedLimiter))
      $speedLimiter=self::$maxDownloadSpeed;

    if(!file_exists($location))
      throw new BadRequestException("File not found!",404);

    if(!is_file($location))
      throw new InvalidStateException("The specified location do not point at file!");

    if(!is_readable($location))
      throw new InvalidStateException("File is not readable!");

    if($speedLimiter<0)
      throw new InvalidArgumentException("Download rate must be a non-negative number. ( \$dr >= 0 )");

    if($filename===null)
      $filename = pathinfo($location, PATHINFO_BASENAME);

    if($mimeType === null)
      $mimeType = self::getMimeType($filename);

    $fastDownload = self::_configEnvironment(); // On success returns TRUE

    if($fastDownload === FALSE and $speedLimiter!==0)
      throw new InvalidStateException("Set_time_limit or ini_set function must be allowed when you want to use speed limits. You must disable speed limits (set to 0) to be able to use FileDownloader without set_time_limit or ini_set function.");

    /* ------------------------------------------------------------- */

    $res = Environment::getHttpResponse();
    $req = Environment::getHttpRequest();

    $res->setContentType($mimeType);

    $res->setHeader('Expires', '0');
    $res->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
    $res->setHeader('Pragma', 'public'); // Fix for IE - Content-Disposition
    $res->setHeader('Content-Disposition', 'attachment; filename='.self::_getContentDispositionHeaderData($filename));
    $res->setHeader('Content-Description', 'File Transfer');
    $res->setHeader('Content-Transfer-Encoding', 'binary');
    $res->setHeader('Connection', 'close');
    $res->setHeader('ETag', self::getETag($location));



    $speedLimit = false;
    $step       = self::$stepSize;
    if($speedLimiter !== 0)
    {
      $speedLimit = true;
      $step       = $speedLimiter;
    }

    if($fastDownload === TRUE){
      self::_sendFileToBrowser($location,$speedLimit,$step);
    }else{ // Přečteme soubor standardním způsobem - nepodporuje omezování rychlosti
      $res->setHeader('Content-Length', filesize($location));
      $res->setHeader('Accept-Ranges', "none");
      if($req->getHeader("Range"))
        self::_HTTPError(416);
      readfile($location);
    }
    if($terminate === TRUE)
      die();
  }

  /**
   * Returns mimetype of the file
   * @param string $location  Location to file
   * @return string           Mime type
   */
  public static function getMimeType($location){
    /*if (extension_loaded('fileinfo'))
    {
      // !!! NOT TESTED !!! CAN SOMEONE TEST IT??? !!!
      if ($finfo = finfo_open(FILEINFO_MIME))
      {
        $mime = finfo_file($finfo, $location);
        finfo_close($finfo);
        return $mime;
      }
    }
    else
    {*/
      // By file extension
      $cache = Environment::getCache("FileDownloader");
      if(!IsSet($cache["mime-types"]))
        $cache["mime-types"] = parse_ini_file(dirname(__FILE__)."\\mime.ini");
      $mimetypes = $cache["mime-types"];
      
      $ex = pathinfo($location, PATHINFO_EXTENSION);
      if (array_key_exists($ex, $mimetypes))
        return $mimetypes[$ex];
      else
        return "application/octet-stream";
    /*}*/
  }

  /**
   * Send file to browser. (enhanced readfile())
   * This function do not send any headers!
   *
   * It is strongly recomended to set_time_limit(0). If
   * time limit gone before file download ends file may
   * be corrupted!
   *
   * @param string $location      File location
   * @param int $start            Start byte
   * @param int $end              End byte
   * @param bool $speedLimit      Use buffer value as bytes per second?
   * @param int $buffer           Buffer size
   */
  public static function readFile($location,$start=0,$end=null,$speedLimit=false,$buffer=16)
  {
    $fp = fopen($location,"rb");
    if(!$fp) throw new InvalidStateException("Can't open file for reading!");
    if($end===null) $end = filesize($location);
    fseek($fp, $start); // Move file pointer to the start of the download
		while(!feof($fp) && ($p = ftell($fp)) <= $end)
		{
			if ($p + $buffer > $end)
			{
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			echo fread($fp, $buffer);
			flush();
      if($speedLimit===true) sleep(1);
		}
    fclose($fp);
  }

  /* Now private functions */

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
  private static function _configEnvironment(){
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

  /**
   * Returns filename (but if IE fix the bug)
   * @param string $basename Path to file or filename
   * @return string
   */
  private static function _getContentDispositionHeaderData($basename){
    $basename = basename($basename);
    if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], "MSIE"))
    {
      // workaround for IE filename bug with multiple periods / multiple dots in filename
      // that adds square brackets to filename - eg. setup.abc.exe becomes setup[1].abc.exe
      $iefilename = preg_replace('/\./', '%2e', $basename, substr_count($basename, '.') - 1);
    }
    return $basename;
  }

  /**
   * Sends file to browser (faster method).
   * This will generate headers and send file to browser.
   * @param string $fileLocation  Path to file
   * @param bool $speedLimit      Apply step as bytes per second?
   * @param int $step             Bytes sent in one cycle
   */
  private static function _sendFileToBrowser($fileLocation,$speedLimit=false,$step=16){
    $req = Environment::getHttpRequest();
    $res = Environment::getHttpResponse();

    $size   = filesize($fileLocation);
    $length = $size; // Content-length
    $start  = 0;
    $end    = $size - 1;

    // Now that we've gotten so far without errors we send the accept range header
    /* At the moment we only support single ranges.
     * Multiple ranges requires some more work to ensure it works correctly
     * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
     *
     * Multirange support annouces itself with:
     * header('Accept-Ranges: bytes');
     *
     * Multirange content must be sent with multipart/byteranges mediatype,
     * (mediatype = mimetype)
     * as well as a boundry header to indicate the various chunks of data.
     */

    $res->setHeader("Accept-Ranges", "0-".$length);
    // header('Accept-Ranges: bytes'); // For multipart messages
    // multipart/byteranges
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2

    if ($req->getHeader("Range", false))
    { // If partial download
      try {
        self::_parseRangeHeader($req, $res, $req->getHeader("Range"), $size, &$start, &$end, &$length);
      } catch (FileDownloaderException $e) {
        if($e->getCode() == 416){
          $res->setHeader("Content-Range", "bytes $start-$end/$size");
          self::_HTTPError(416);
        }else throw $e;
      }
      $res->setCode(206); // Partial content
    } // End of if(is set "range" header)

    // Notify the client the byte range we'll be outputting
    $res->setHeader("Content-Range","bytes $start-$end/$size");
    $res->setHeader("Content-Length",$length);

    self::readFile($fileLocation, $start, $end,$speedLimit, 1024 * $step);
  }

  /**
   * Sends http error to client
   * @param int $code       HTTP code
   * @param string $message HTTP status
   */
  private static function _HTTPError($code,$message=null){
    $errors = array(
      416=>"Requested Range not satisfiable"
    );
    if($message===null and isset($errors[$code]))
      $message = $errors[$code];
    $httpResponse = Environment::getHttpResponse();
    $httpResponse->setCode($code);
    $httpResponse->setContentType("plain/text","UTF-8");
    die("<html><body><h1>HTTP Error ".$code." - ".$message."</h1><p>".$message."</p></body></html>");
  }

  /**
   * This will parse Range header and set results to the referential variables
   * @param IHttpRequest $req Request object
   * @param IHttpResponse $res Response object
   * @param string $header Range header
   * @param int $size File size
   * @param int $start Start byte
   * @param int $end End byte
   * @param int $length Content-legth
   */
  private static function _parseRangeHeader(IHttpRequest $req,IHttpResponse $res,$header,$size,&$start,&$end,&$length){
    $copy_start = $start;
    $copy_end   = $end;

    // Extract the range string
    $rangeArray = explode('=', $req->getHeader("Range"), 2);
    $range = $rangeArray[1];

    // Make sure the client hasn't sent us a multibyte range
    if (strpos($range, ',') !== false)
    {
      // (?) Shoud this be issued here, or should the first
      // range be used? Or should the header be ignored and
      // we output the whole content?
      throw new FileDownloaderException("HTTP 416",416);
    }

    // If the range starts with an '-' we start from the beginning
    // If not, we forward the file pointer
    // And make sure to get the end byte if spesified
    if ($range{0} == '-')
    {
      // The n-number of the last bytes is requested
      $copy_start = $size - (int)substr($range, 1);
    }
    else
    {
      $range  = explode('-', $range);
      $copy_start = $range[0];
      $copy_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
    }

    /**
     * Check the range and make sure it's treated according to the specs.
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
     */
    // End bytes can not be larger than $end.
    $copy_end = ($copy_end > $end) ? $end : $copy_end;
    // Validate the requested range and return an error if it's not correct.
    if ($copy_start > $copy_end || $copy_start > $size - 1 || $copy_end >= $size)
    {
      throw new FileDownloaderException("HTTP 416",416);
    }

    // All is ok - so assign variables back
    $start  = $copy_start;
    $end    = $copy_end;
    $length = $end - $start + 1; // Calculate new content length
  }
}

/**
 * Exception class
 */
class FileDownloaderException extends Exception{}