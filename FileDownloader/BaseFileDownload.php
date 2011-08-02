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
 * FileDownload is a small library to make a comfort,
 * fast and simple downloading of files.
 * It supports:
 *  + partial downloads
 *  + speed limits
 *  + auto mime type detection
 *  + fluent interface
 *  + callbacks
 *  + defaults
 *  + modular system
 *  +
 *
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař
 * @author      Jan Kuchař
 * @version     $Id$
 *
 * @property string $sourceFile         Source file path
 * @property string $transferFileName   File name witch will be used for transfer
 * @property string $mimeType           Mime-type of transferred file
 * @property int $speedLimit            Speed limit
 * @property int $transferredBytes      How many bytes was sent to browser
 * @property int $contentDisposition    Content disposition: inline or attachment
 * @property-read float $sourceFileSize   File size
 * @property-read int $transferID       TransferId
 */
abstract class BaseFileDownload extends Object {
	/**
	 * Array with defaults (will web called <code>$file->$key = $val;</code>)
	 * @var array
	 */
	public static $defaults = array();

	/**
	 * Downloaders array
	 * @var array
	 */
	private static $fileDownloaders=array();

	/**
	 * Close session before start download? (if not, it will block session until file is transferred!)
	 * @var bool
	 */
	public static $closeSession = true;

	/**
	 * Add file downlaoder
	 *   Order is priority (last added will be used first)
	 * @param IDownloader $downloader
	 */
	public static function registerFileDownloader(IDownloader $downloader) {
		self::$fileDownloaders[] = $downloader;
	}

	/**
	 * Getts all registred file downloaders
	 * @return array
	 */
	public static function getFileDownloaders() {
		return self::$fileDownloaders;
	}

	/**
	 * Unregister all registred downloaders
	 */
	public static function clearFileDownloaders() {
		self::$fileDownloaders = array();
	}

	/**
	 * Getts new instance of self
	 * @return FileDownload
	 */
	// moved to subclasses
	//public static function getInstance() {
	//	return new FileDownload();
	//}

	/**
	 * Transfer identificator
	 * @var string
	 */
	private $vTransferID = -1;

	const CONTENT_DISPOSITION_ATTACHMENT = "attachment";
	const CONTENT_DISPOSITION_INLINE = "inline";

	/**
	 * Content disposition: attachment / inline
	 * @var string
	 */
	private $vContentDisposition = 'attachment';

	/**
	 * Maximal speed of download (in kb/s)
	 * 0 is unlimited
	 * @var int
	 */
	private $vSpeedLimit = 0;

	/**
	 * Location of the file
	 * @var string|null
	 */
	private $vSourceFile = null;

	/**
	 * Send as filename
	 * @var string|null
	 */
	private $vTransferFileName = null;

	/**
	 * Mimetype of file
	 * null = autodetection
	 *
	 * @var string|null
	 */
	private $vMimeType = null;

	/**
	 * Enable browser cache
	 * @var Bool|null to auto
	 */
	public $enableBrowserCache = null;

	/**
	 * How many bytes is sent
	 * @var int
	 */
	public $transferredBytes = 0;

	/**
	 * Callback - before downloader starts.
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 * @var array
	 */
	public $onBeforeDownloaderStarts = array();

	/**
	 * Adds onBeforeDownloaderStarts callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addBeforeDownloaderStartsCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - before is send first bit of file to browser
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onBeforeOutputStarts = array();

	/**
	 * Adds onBeforeOutputStarts callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addBeforeOutputStartsCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when status changes
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onStatusChange = array();

	/**
	 * Adds StatusChange callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addStatusChangeCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when file download completed
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 * @var array
	 */
	public $onComplete = array();

	/**
	 * Adds Complete callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addCompleteCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when file download has been corrupted/stopped and now
	 * again conected and wants only part of the file.
	 * Called after - onBeforeOutputStarts
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onTransferContinue = array();

	/**
	 * Adds TransferContinue callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addTransferContinueCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when new file download starts (from the begining)
	 * Called after - onBeforeOutputStarts
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onNewTransferStart = array();

	/**
	 * Adds NewTransferStart callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addNewTransferStartCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when browser disconnects from server (abort)
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onAbort = array();

	/**
	 * Adds Abort callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addAbortCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Callback - when browser disconnects from server (abort,timeout)
	 * First parameter will be this file
	 * Second parameter will be downloader object
	 *  NOTE: This callback must be supported by downloader!
	 * @var array
	 */
	public $onConnectionLost = array();

	/**
	 * Adds ConnectionError callback
	 * @param callback $callback Callback
	 * @return BaseFileDownload
	 */
	function addConnectionLostCallback($callback) {
		return $this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Adds callback
	 * @param string $name          Name of callback
	 * @param callback $callback    Callback
	 * @return BaseFileDownload
	 */
	private function addCallback($fceName,$callback) {
		preg_match("/^.*::add(.*)Callback$/", $fceName, $matches);
		$varName = "on".$matches[1];
		$var = &$this->$varName;
		$var[] = $callback;
		return $this;
	}

	function  __construct() {
		$this->vTransferID = time()."-".rand();
		foreach(self::$defaults AS $key => $val) {
			$this->$key = $val;
		}
	}

	/**
	 * Getts transfer identificator
	 * @return string
	 */
	public function getTransferId() {
		return $this->vTransferID;
	}

	/**
	 * Setts location of source file
	 * @param string $location Location of the source file
	 * @return BaseFileDownload
	 */
	function setSourceFile($location) {
		if($location === null) {
			$this->vSourceFile = null;
		}else {
			if(!file_exists($location)) throw new BadRequestException("File not found at '".$location."'!");
			if(!is_readable($location)) throw new InvalidStateException("File is NOT readable!");
			$this->transferFileName = pathinfo($location, PATHINFO_BASENAME);
			$this->vSourceFile = realpath($location);
		}
		return $this;
	}

	/**
	 * Getts location of the source file
	 * @return BaseFileDownload
	 */
	function getSourceFile() {
		if($this->vSourceFile === null) throw new InvalidStateException("Location is not set!");
		return $this->vSourceFile;
	}

	/**
	 * Setts content disposition
	 * @param string $disposition
	 * @return BaseFileDownload
	 */
	function setContentDisposition($disposition) {
		$values = array("inline","attachment");
		if(!in_array($disposition,$values))
			throw new InvalidArgumentException("Content disposition must be one of these: ".implode(",", $values));
		$this->vContentDisposition = $disposition;
		return $this;
	}

	/**
	 * Getts content disposition
	 * @return string
	 */
	function getContentDisposition() {
		return $this->vContentDisposition;
	}

	/**
	 * Getts send as name
	 * @return string
	 */
	function getTransferFileName() {
		return $this->vTransferFileName;
	}

	/**
	 * Setts send as name
	 * @param string $sendAs
	 * @return BaseFileDownload
	 */
	function setTransferFileName($name) {
		$this->vTransferFileName = pathinfo($name, PATHINFO_BASENAME);
		return $this;
	}


	/**
	 * Setts speed limit
	 * @param int $speed Speed limit
	 * @return BaseFileDownload
	 */
	function setSpeedLimit($speed) {
		if(!is_int($speed)) throw new InvalidArgumentException("Max download speed must be intiger!");
		if($speed<0) throw new InvalidArgumentException("Max download speed can't be smaller than zero!");
		$availableMem = FDTools::getAvailableMemory();
		$availableMemWithReserve = ($availableMem-100*1024);
		if($availableMem !== null AND $speed>$availableMemWithReserve) throw new InvalidArgumentException("Max download speed can't be a bigger than available memory ".$availableMemWithReserve."b!");
		$this->vSpeedLimit = (int)round($speed);
		return $this;
	}

	/**
	 * Getts speed limit
	 * @return int
	 */
	function getSpeedLimit() {
		return $this->vSpeedLimit;
	}

	/**
	 * Returns mimetype of the file
	 *
	 * @param string $location  Everithing what accepts pathinfo()
	 * @return string           Mime type
	 */
	public function getMimeType() {
		if($this->vMimeType !== null) return $this->vMimeType;

		$mime = "";
		if (extension_loaded('fileinfo') and function_exists("finfo_open")) {
			//TODO: test this code:
			if ($finfo = @finfo_open(FILEINFO_MIME)) {
				$mime = @finfo_file($finfo, $this->sourceFile);
				@finfo_close($finfo);
				if(FDTools::isValidMimeType($mime))
					return $mime;
			}
		}

		if(function_exists("mime_content_type")) {
			$mime = mime_content_type($this->sourceFile);
			if(FDTools::isValidMimeType($mime))
				return $mime;
		}

		// By file extension from ini file
		$cache = Environment::getCache("FileDownloader");
		if(!IsSet($cache["mime-types"]))
			$cache["mime-types"] = parse_ini_file(dirname(__FILE__).DIRECTORY_SEPARATOR."mime.ini");
		$mimetypes = $cache["mime-types"];

		$extension = pathinfo($this->sourceFile, PATHINFO_EXTENSION);
		if (array_key_exists($extension, $mimetypes))
			$mime = $mimetypes[$extension];

		if(FDTools::isValidMimeType($mime))
			return $mime;
		else
			return "application/octet-stream";
	}

	/**
	 * Setts Mime-type
	 * @param string $mime Mime-type
	 * @return BaseFileDownload
	 */
	public function setMimeType($mime) {
		$this->vMimeType = $mime;
		return $this;
	}

	/**
	 * Getts file size
	 * @return float
	 */
	public function getSourceFileSize() {
		return FDTools::filesize($this->sourceFile);
	}

	/**
	 * Download the file!
	 * @param IDownloader $downloader
	 */
	function download(IDownloader $downloader = null) {
		$req = Environment::getHttpRequest();
		$res = Environment::getHttpResponse();

		if(self::$closeSession) {
			$ses = Environment::getSession();
			if($ses->isStarted()) {
				$ses->close();
			}
		}

		if($this->getContentDisposition() == "inline" AND is_null($this->enableBrowserCache)) {
			$this->enableBrowserCache = true;
		}else{
			$this->enableBrowserCache = false;
		}

		if($downloader === null)
			$downloaders = self::getFileDownloaders();
		else
			$downloaders = array($downloader);

		if(count($downloaders)<=0)
			throw new InvalidStateException("There is no registred downloader!");

		krsort($downloaders);

		$lastException = null;

		foreach($downloaders AS $downloader) {
			if($downloader instanceof IDownloader and $downloader->isCompatible($this)) {
				try {
					FDTools::clearHeaders($res); // Delete all headers
					$this->transferredBytes = 0;
					$this->onBeforeDownloaderStarts($this,$downloader);
					$downloader->download($this); // Start download
					$this->onComplete($this,$downloader);
					die(); // If all gone ok -> die
				} catch (FDSkypeMeException $e) {
					if($res->isSent()) {
						throw new InvalidStateException("Headers are already sent! Can't skip downloader.");
					} else {
						continue;
					}
				} catch (Exception $e) {
					if(!$res->isSent())
						FDTools::clearHeaders($res);
					throw $e;
				}
			}
		}

		// Pokud se soubor nějakým způsobem odešle - toto už se nespustí
		if($lastException instanceof Exception) {
			FDTools::clearHeaders(Environment::getHttpResponse(),TRUE);
			throw $lastException;
		}

		if($req->getHeader("Range"))
			FDTools::_HTTPError(416); // Požadavek na range
		else
			$res->setCode(500);
		throw new InvalidStateException("There is no compatible downloader (all downloader returns downloader->isComplatible()=false or was skipped)!");
	}
}

/* Register default downloaders */
BaseFileDownload::registerFileDownloader(new NativePHPDownloader);
BaseFileDownload::registerFileDownloader(new AdvancedDownloader);

/**
 * When some http error
 */
class FileDownloaderException extends Exception {

}

/**
 * When downloader throws this exception -> it will be skipped
 */
class FDSkypeMeException extends Exception {

}