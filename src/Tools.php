<?php

namespace FileDownloader;

use BigFileTools;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Object;


class Tools extends Object {
	const BYTE  = 1;
	const KILOBYTE = 1024;
	const MEGABYTE = 1048576;
	const GYGABYTE = 1073741824;
	const TERABYTE = 1099511627776;

	/**
	 * Buffer for Tools::readfile()
	 * @var int
	 */
	static public $readFileBuffer = 524288; // 512kb

	/**
	 * Returns available memery in bytes or NULL when no limit it set
	 * @return int|null
	 */
	public static function getAvailableMemory() {
		$mem = self::parsePHPIniMemoryValue(ini_get('memory_limit'));
		if ($mem === 0) {
			return null;
		}
		return $mem-memory_get_usage();
	}

	/**
	 * Parse php ini file memory values (5G,10M,3K)
	 * @param string $phpIniValueStr
	 * @return int
	 */
	public static function parsePHPIniMemoryValue($phpIniValueStr) {
		$phpIniValueInt = (int)$phpIniValueStr;
		if ($phpIniValueInt <= 0) {
			return 0;
		}
		switch ($phpIniValueStr[strlen($phpIniValueStr) - 1]) {
			case 'K':
				$phpIniValueInt *= self::KILOBYTE;
				break;
			case 'M':
				$phpIniValueInt *= self::MEGABYTE;
				;
				break;
			case 'G':
				$phpIniValueInt *= self::GYGABYTE;
				break;
			case 'T':
				$phpIniValueInt *= self::TERABYTE;
				break;
			default:
				throw new InvalidStateException("Can't parse php ini value!");
		}
		return $phpIniValueInt;
	}

	/**
	 * Clears all http headers
	 * @param IResponse $res
	 * @return IResponse
	 */
	public static function clearHeaders(IResponse $res, $setContentType=false) {
		$res->setCode(IResponse::S200_OK);
		foreach($res->getHeaders() AS $key => $val) {
			$res->setHeader($key, null);
		}
		if ($setContentType === true) {
			$res->setContentType('text/html', 'UTF-8');
		}
		return $res;
	}

	/**
	 * Setts php time limit
	 * @param int $time     Time limit
	 * @return bool
	 */
	public static function setTimeLimit($time=0) {
		if (!function_exists('ini_get')) {
			throw new InvalidStateException('Function ini_get must be allowed.');
		}

		if ((int) @ini_get('max_execution_time') === $time) {
			return true;
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit($time);
		} elseif (function_exists('ini_set')) {
			@ini_set('max_execution_time', $time);
		}

		if ((int) @ini_get('max_execution_time') === $time) {
			return true;
		}

		return false;
	}

	/**
	 * Generates ETag and returns
	 *
	 * @param string $location    Location to source file
	 * @return string             ETag
	 */
	public static function getETag($location) {
		return '"' . md5($location . filemtime($location) . self::filesize($location)) . '"';
	}


	/**
	 * Returns filename (but if IE fix the bug)
	 *
	 * @link http://cz2.php.net/manual/en/function.fpassthru.php#25801
	 * @author Unknown
	 * @param Request $request HTTP request
	 * @param string $basename Path to file or filename
	 * @return string
	 */
	public static function getContentDispositionHeaderData(Request $request, $basename) {
		$basename = basename($basename);
		$userAgent = $request->getHeader('User-Agent');
		if ($userAgent && FALSE !== strpos($userAgent, 'MSIE')) {
			// workaround for IE filename bug with multiple periods / multiple dots in filename
			// that adds square brackets to filename - eg. setup.abc.exe becomes setup[1].abc.exe
			$iefilename = preg_replace('/\./', '%2e', $basename, substr_count($basename, '.') - 1);
			$basename = rawurlencode($basename); // Czech chars in filename
		}
		return $basename;
	}


	/**
	 * Sends http error to client
	 *
	 * @author Jan Kuchař
	 * @param IResponse $response
	 * @param int $code HTTP code
	 * @param string $message HTTP status
	 */
	public static function sendHttpError(IResponse $response, $code, $message=null) {
		$errors = array(
			416=> 'Requested Range not satisfiable'
		);
		if ($message === null && isset($errors[$code])) {
			$message = $errors[$code];
		}
		$response->setCode($code);
		$response->setContentType('plain/text', 'UTF-8');
		die('<html><body><h1>HTTP Error ' .$code. ' - ' .$message. '</h1><p>' .$message. '</p></body></html>');
	}

	/**
	 * Checks if mime type is valid
	 * @param string $mime      Mime-type
	 * @return bool
	 */
	public static function isValidMimeType($mime) {
		$mime = (string)$mime;
		// Thanks to Matúš Matula: http://forum.nette.org/cs/1952-addon-file-downloader-file-downloader?p=2#p61785
		// return preg_match('#^[-\w]+/[-\w\+]+$#i', $mime); // simple check

		// Thanks to voda http://forum.nette.org/cs/1952-addon-file-downloader-file-downloader?p=2#p61794
		// @see http://tools.ietf.org/html/rfc4288#section-4.2
		$regName = '[a-z0-9!#$&.+^_-]{1,127}';
		return preg_match("|^$regName/$regName$|i", $mime);
	}

	/**
	 * Sends file to browser. (enhanced readfile())
	 * This function do not send any headers!
	 *
	 * It is strongly recomended to set time limit to zero. ( Tools::setTimeLimit(0) )
	 * If time limit gone before file download ends download may be corrupted!
	 *
	 * Sources:
	 *    @link http://cz2.php.net/manual/en/function.fpassthru.php#47110
	 *    @link http://cz2.php.net/manual/en/function.readfile.php#86244
	 *    @link http://cz2.php.net/manual/en/function.readfile.php#83653
	 *
	 * @author Jan Kuchař
	 * @param string $location      File location
	 * @param int $start            Start byte
	 * @param int $end              End byte
	 * @param bool $speedLimit      Bytes per second - zero is unlimited
	 * @param int $buffer           Buffer size in bytes
	 */
	public static function readFile($location,$start=0,$end=null,$speedLimit=0) {
		$buffer = self::$readFileBuffer;
		$sleep = false;
		if(is_int($speedLimit) && $speedLimit>0) {
			$sleep  = true;
			$buffer = (int)round($speedLimit);
		}
		if ($buffer < 1) {
			throw new InvalidArgumentException('Buffer must be bigger than zero!');
		}
		$availableMem = self::getAvailableMemory();
		if ($availableMem && $buffer > ($availableMem * 0.9)) {
			throw new InvalidArgumentException('Buffer is too big! (bigger than available memory)');
		}

		$fp = fopen($location, 'rb');
		if (!$fp) {
			throw new InvalidStateException("Can't open file for reading!");
		}
		if ($end === null) {
			$end = self::filesize($location);
		}
		fseek($fp, $start); // Move file pointer to the start of the download
		while(!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			echo fread($fp, $buffer);
			flush();
			if ($sleep === true) {
				sleep(1);
			}
		}
		fclose($fp);
	}

	/**
	 * Function that determines a file's size only with PHP functions,
	 * for all files, also those > 4 GB:
	 * @see http://www.php.net/manual/en/function.filesize.php#102135
	 * @param string $file
	 * @return \Brick\Math\BigInteger
	 */
	public static function filesize($file) {
		return BigFileTools\BigFileTools::createDefault()->getFile($file)->getSize();
	}

	/**
	 * @param string $file path to file
	 * @return string mime-type
	 */
	public static function detectMimeType($file) {
		if (extension_loaded('fileinfo') && function_exists('finfo_open')) {
			if ($finfo = @finfo_open(FILEINFO_MIME)) {
				$mime = @finfo_file($finfo, $file);
				@finfo_close($finfo);
				if (Tools::isValidMimeType($mime)) {
					return $mime;
				}
			}
		}

		if(function_exists('mime_content_type')) {
			$mime = mime_content_type($file);
			if (Tools::isValidMimeType($mime)) {
				return $mime;
			}
		}

		// By file extension from ini file
		$mimeTypes = parse_ini_file(__DIR__ . DIRECTORY_SEPARATOR . 'mime.ini');

		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if (array_key_exists($extension, $mimeTypes)) {
			$mime = $mimeTypes[$extension];
		}
		if (Tools::isValidMimeType($mime)) {
			return $mime;
		}

		return 'application/octet-stream';
	}

}


