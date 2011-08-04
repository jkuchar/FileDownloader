<?php

/**
 * Copyright (c) 2009, Jan Kuchař
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms,
 * with or without modification, are permitted provided
 * that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above
 *       copyright notice, this list of conditions and the following
 *       disclaimer in the documentation and/or other materials provided
 *       with the distribution.
 *     * Neither the name of the Mujserver.net nor the names of its
 *       contributors may be used to endorse or promote products derived
 *       from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author     Jan Kuchař
 * @copyright  Copyright (c) 2009 Jan Kuchař (http://mujserver.net)
 * @license    New BSD License
 * @link       http://filedownloader.projekty.mujserver.net
 */

/**
 *
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař
 * @author      Jan Kuchař
 * @version     $Id$
 */
class FDTools extends Object {
	const BYTE = 1;
	const KILOBYTE = 1024;
	const MEGABYTE = 1048576;
	const GYGABYTE = 1073741824;
	const TERABYTE = 1099511627776;

	/**
	 * Buffer for FDTools::readfile()
	 * @var int
	 */
	static public $readFileBuffer = 524288; // 512kb

	static function getAvailableMemory() {
		$mem = self::parsePHPIniMemoryValue(ini_get("memory_limit"));
		if ($mem == 0)
			return null;
		return $mem - memory_get_usage();
	}

	/**
	 * Parse php ini file memory values (5G,10M,3K)
	 * @param string $phpIniValueStr
	 * @return int
	 */
	static function parsePHPIniMemoryValue($phpIniValueStr) {
		$phpIniValueInt = (int) $phpIniValueStr;
		if ($phpIniValueInt == 0)
			return 0;
		switch (substr($phpIniValueStr, -1, 1)) {
			case "K":
				$phpIniValueInt *= self::KILOBYTE;
				break;
			case "M":
				$phpIniValueInt *= self::MEGABYTE;
				;
				break;
			case "G":
				$phpIniValueInt *= self::GYGABYTE;
				break;
			case "T":
				$phpIniValueInt *= self::TERABYTE;
				break;
			default:
				throw new InvalidStateException("Can't parse php ini value!");
		}
		return $phpIniValueInt;
	}

	/**
	 * Clears all http headers
	 * @param IHTTPResponse $res
	 * @return IHTTPResponse
	 */
	static function clearHeaders(IHTTPResponse $res, $setContentType=false) {
		$res->setCode(IHTTPResponse::S200_OK);
		foreach ($res->getHeaders() AS $key => $val) {
			$res->setHeader($key, null);
		}
		if ($setContentType === true)
			$res->setContentType("text/html", "UTF-8");
		return $res;
	}

	/**
	 * Setts php time limit
	 * @param int $time     Time limit
	 * @return bool
	 */
	static function setTimeLimit($time=0) {
		if (!function_exists("ini_get"))
			throw new InvalidStateException("Function ini_get must be allowed.");

		if ((int) @ini_get("max_execution_time") === $time)
			return true;

		if (function_exists("set_time_limit"))
			@set_time_limit($time);
		elseif (function_exists("ini_set"))
			@ini_set("max_execution_time", $time);

		if ((int) @ini_get("max_execution_time") === $time)
			return true;

		return false;
	}

	/**
	 * Generates ETag and returns
	 *
	 * @param string $location    Location to source file
	 * @return string             ETag
	 */
	static function getETag($location) {
		return "\"" . md5($location . filemtime($location) . self::filesize($location)) . "\"";
	}

	/**
	 * Returns filename (but if IE fix the bug)
	 *
	 * @link http://cz2.php.net/manual/en/function.fpassthru.php#25801
	 * @author Unknown
	 * @param string $basename Path to file or filename
	 * @return string
	 */
	static function getContentDispositionHeaderData($basename) {
		$basename = basename($basename);
		$req = Environment::getHttpRequest();
		$userAgent = $req->getHeader("User-Agent");
		if ($userAgent AND strstr($userAgent, "MSIE")) {
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
	 * @param int $code       HTTP code
	 * @param string $message HTTP status
	 */
	static function _HTTPError($code, $message=null) {
		$errors = array(
		    416 => "Requested Range not satisfiable"
		);
		if ($message === null and isset($errors[$code]))
			$message = $errors[$code];
		$res = Environment::getHttpResponse();
		$res->setCode($code);
		$res->setContentType("plain/text", "UTF-8");
		die("<html><body><h1>HTTP Error " . $code . " - " . $message . "</h1><p>" . $message . "</p></body></html>");
	}

	/**
	 * Checks if mime type is valid
	 * @param string $mime      Mime-type
	 * @return bool
	 */
	static function isValidMimeType($mime) {
		$mime = (string) $mime;
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
	 * It is strongly recomended to set time limit to zero. ( FDTools::setTimeLimit(0) )
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
	public static function readFile($location, $start=0, $end=null, $speedLimit=0) {
		$buffer = self::$readFileBuffer;
		$sleep = false;
		if (is_int($speedLimit) and $speedLimit > 0) {
			$sleep = true;
			$buffer = (int) round($speedLimit);
		}
		if ($buffer < 1)
			throw new InvalidArgumentException("Buffer must be bigger than zero!");
		if ($buffer > (self::getAvailableMemory() * 0.9))
			throw new InvalidArgumentException("Buffer is too big! (bigger than available memory)");

		$fp = fopen($location, "rb");
		if (!$fp)
			throw new InvalidStateException("Can't open file for reading!");
		if ($end === null)
			$end = self::filesize($location);
		fseek($fp, $start); // Move file pointer to the start of the download
		while (!feof($fp) && ($p = ftell($fp)) <= $end) {
			if ($p + $buffer > $end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $end - $p + 1;
			}
			echo fread($fp, $buffer);
			flush();
			if ($sleep == true)
				sleep(1);
		}
		fclose($fp);
	}

	/**
	 * Function that determines a file's size only with PHP functions,
	 * for all files, also those > 4 GB:
	 * @see http://www.php.net/manual/en/function.filesize.php#102135
	 * @param string $file
	 * @return float
	 */
	public static function filesize($file) {
		return BigFileTools::fromPath($file)->size(true);
	}

}