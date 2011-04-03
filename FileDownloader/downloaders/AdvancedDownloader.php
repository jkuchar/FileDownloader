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

// TODO: Floating buffer (buffer size changing dynamically by the speed of client)
// TODO: Add custom priority of download modules
// TODO: Move from float to strings and use bcmath for computations

/**
 *
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař
 * @author      Jan Kuchař
 * @version     $Id$
 */
class AdvancedDownloader extends BaseDownloader {
	/**
	 * Check for environment configuration?
	 * @var bool
	 */
	static $checkEnvironmentSettings = true;

	public $size = 0;
	public $start = 0;
	public $end = 0;
	public $length = 0;
	public $position = 0;
	public $transferred = 0;

	/**
	 * @var BaseFileDownload
	 */
	public $currentTransfer;

	protected $buffer;

	/**
	 * @internal
	 * @var boolean
	 */
	protected $sleep;

	/**
	 * Download file!
	 * @param BaseFileDownload $file
	 */
	function download(BaseFileDownload $transfer) {
		$this->currentTransfer = $transfer;
		$this->sendStandardFileHeaders($transfer,$this);

		@ignore_user_abort(true); // For onAbort event

		$req = Environment::getHttpRequest();
		$res = Environment::getHttpResponse();

		$filesize = $this->size   = $transfer->sourceFileSize;
		$this->length = $this->size; // Content-length
		$this->start  = 0;
		$this->end    = $this->size - 1;

		/* ### Headers ### */

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

		//$res->setHeader("Accept-Ranges", "0-".$this->end); // single-part - now not accepted by mozilla
		$res->setHeader("Accept-Ranges", "bytes"); // multi-part (through Mozilla)
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2

		if ($req->getHeader("Range", false)) // If partial download
		{
			try {
				$range_start = $this->start;
				$range_end   = $this->end;

				// Extract the range string
				$rangeArray = explode('=', $req->getHeader("Range"), 2);
				$range = $rangeArray[1];

				// Make sure the client hasn't sent us a multibyte range
				if (strpos($range, ',') !== false) {
					// (?) Shoud this be issued here, or should the first
					// range be used? Or should the header be ignored and
					// we output the whole content?
					throw new FileDownloaderException("HTTP 416",416);
				}

				// If the range starts with an '-' we start from the beginning
				// If not, we forward the file pointer
				// And make sure to get the end byte if spesified
				if ($range{0} == '-') {
					// The n-number of the last bytes is requested
					$range_start = $this->size - (float)substr($range, 1);
				}
				else {
					$range  = explode('-', $range);
					$range_start = $range[0];
					$range_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $this->size;
				}

				/**
				 * Check the range and make sure it's treated according to the specs.
				 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
				 */
				// End bytes can not be larger than $end.
				$range_end = ($range_end > $this->end) ? $this->end : $range_end;
				// Validate the requested range and return an error if it's not correct.
				if ($range_start > $range_end || $range_start > $this->size - 1 || $range_end >= $this->size) {
					throw new FileDownloaderException("HTTP 416",416);
				}

				// All is ok - so assign variables back
				$this->start  = $range_start;
				$this->end    = $range_end;
				$this->length = $this->end - $this->start + 1; // Calculate new content length
			} catch (FileDownloaderException $e) {
				if($e->getCode() == 416) {
					$res->setHeader("Content-Range", "bytes $this->start-$this->end/$this->size");
					FDTools::_HTTPError(416);
				}else throw $e;
			}
			$res->setCode(206); // Partial content
		} // End of if partial download

		// Notify the client the byte range we'll be outputting
		$res->setHeader("Content-Range","bytes $this->start-$this->end/$this->size");
		$res->setHeader("Content-Length",$this->length);

		/* ### Call callbacks ### */

		$transfer->onBeforeOutputStarts($transfer,$this);
		if($this->start > 0) $transfer->onTransferContinue($transfer,$this);
		else $transfer->onNewTransferStart($transfer,$this);

		/* ### Send file to browser - document body ### */

		$buffer = FDTools::$readFileBuffer;
		$sleep = false;
		if(is_int($transfer->speedLimit) and $transfer->speedLimit>0) {
			$sleep  = true;
			$buffer = (int)round($transfer->speedLimit);
		}
		$this->sleep = $sleep;
		
		if($buffer<1) throw new InvalidArgumentException("Buffer must be bigger than zero!");
		if($buffer>(FDTools::getAvailableMemory()-memory_get_usage())) throw new InvalidArgumentException("Buffer is too big! (bigger than available memory)");
		$this->buffer = $buffer;



		$fp = fopen($transfer->sourceFile,"rb");
		// TODO: Add flock() READ
		if(!$fp) throw new InvalidStateException("Can't open file for reading!");
		if($this->end===null) $this->end = $filesize-1;


		if(fseek($fp, $this->start, SEEK_SET) === -1) { // Move file pointer to the start of the download
			// Can not move pointer to begining of the filetransfer

			if($this->processByCUrl() === true) {
				// Request was hadled by curl, clean, exit
				$this->cleanAfterTransfer();
				return;
			}
			
			// Use this hack (fread file to start position)
			$destPos = $this->position = PHP_INT_MAX-1;
			if(fseek($fp, $this->position, SEEK_SET) === -1) {
				rewind($fp);
				$this->position = 0;
				throw new InvalidStateException("Can not move pointer to position ($destPos)");
			}
			$maxBuffer = 1024*1024;
			while($this->position < $this->start) {
				$this->position += strlen(fread($fp, min($maxBuffer, $this->start-$this->position)));
			}
		}else{
			// We are at the begining
			$this->position = $this->start;
		}

		$this->processNative($fp,$sleep);
		$this->cleanAfterTransfer();
	}

	protected function cleanAfterTransfer() {
		$this->currentTransfer->transferredBytes = $this->transferred = $this->length;
		$this->currentTransfer = null;
	}

	protected function processNative($fp) {
		$tmpTime = null;
		if($this->sleep===false)
			$tmpTime = time()+1; // Call onStatusChange next second!

		$buffer = $this->buffer;
		while(!feof($fp) && $this->position <= $this->end) {
			if ($this->position + $buffer > $this->end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $this->end - $this->position + 1;
			}
			$data = fread($fp, $buffer);
			echo $data;
			$this->position += strlen($data);
			unset($data);

			$this->_afterBufferSent($tmpTime, $fp);
		}
		fclose($fp);
	}

	protected function processByCUrl() {
		if(function_exists("curl_init")) { // Curl available

			$transfer = $this->currentTransfer;

			$ch = curl_init("file://" . realpath($transfer->sourceFile));
			$range = $this->start.'-'.$this->end; // HTTP range
			curl_setopt($ch, CURLOPT_RANGE, $range);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_BUFFERSIZE, $this->buffer);
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this,"_curlProcessBlock"));
			$curlRet = curl_exec($ch);
			if($curlRet === false) {
				throw new Exception("cUrl error number ".curl_errno($ch).": ".curl_error($ch));
			}
			return true;
		}else{
			return false;
		}
	}

	/**
	 * @internal
	 */
	public function _curlProcessBlock($ch, $chunk) {
		static $curl;
		static $tmpTime;

		if($curl !== $ch) { // Set defaults
			$tmpTime = null;
			if($this->sleep===false)
				$tmpTime = time()+1; // Call onStatusChange next second!
		}

		echo $chunk;
		$len = strlen($chunk);
		$this->position += $len;

		$this->_afterBufferSent($tmpTime);

		return $len;
	}

	protected function _afterBufferSent($tmpTime, $fp=null) {
		$transfer = $this->currentTransfer;

		flush(); // PHP: Do not buffer it - send it to browser!
		@ob_flush();

		if(connection_status()!=CONNECTION_NORMAL) {
			if($fp) fclose($fp);
			$transfer->onConnectionLost($transfer,$this);
			if(connection_aborted()) {
				$transfer->onAbort($transfer,$this);
			}
			die();
		}
		if($this->sleep==true OR $tmpTime<=time()) {
			$transfer->transferredBytes = $this->transferred = $this->position-$this->start;
			$transfer->onStatusChange($transfer,$this);
			if(IsSet($tmpTime))
				$tmpTime = time()+1;
		}
		if($this->sleep==true)
			sleep(1);
	}

	/**
	 * Is this downloader initialized?
	 * @return bool
	 */
	function isInitialized() {
		if($this->end == 0)
			return false;
		return true;
	}


	/**
	 * Is this downloader compatible?
	 * @param BaseFileDownload $file
	 * @return bool TRUE if is compatible; FALSE if not
	 */
	function isCompatible(BaseFileDownload $file) {
		if(self::$checkEnvironmentSettings === true) {
			if(FDTools::setTimeLimit(0)!==true)
				return false;
		}
		return true;
	}
}