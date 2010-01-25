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
	 * Download file!
	 * @param FileDownload $file
	 */
	function download(FileDownload $transfer) {
		$this->sendStandardFileHeaders($transfer,$this);

		@ignore_user_abort(true); // For onAbort event

		$req = Environment::getHttpRequest();
		$res = Environment::getHttpResponse();

		$this->size   = $transfer->sourceFileSize;
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
					$range_start = $this->size - (int)substr($range, 1);
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
					$this->_HTTPError(416);
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
		if($buffer<1) throw new InvalidArgumentException("Buffer must be bigger than zero!");
		if($buffer>(FDTools::getAvailableMemory()*0.9)) throw new InvalidArgumentException("Buffer is too big! (bigger than available memory)");

		$fp = fopen($transfer->sourceFile,"rb");
		if(!$fp) throw new InvalidStateException("Can't open file for reading!");
		if($this->end===null) $this->end = filesize($transfer->sourceFile);

		fseek($fp, $this->start); // Move file pointer to the start of the download

		if($sleep===false)
			$tmpTime = time()+1; // Call onStatusChange next second!
		while(!feof($fp) && ($this->position = ftell($fp)) <= $this->end) {
			if ($this->position + $buffer > $this->end) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$buffer = $this->end - $this->position + 1;
			}
			echo fread($fp, $buffer);
			$this->position = ftell($fp);
			flush(); // PHP: Do not buffer it - send it to browser!
			@ob_flush();
			if(connection_status()!=CONNECTION_NORMAL) {
				fclose($fp);
				$transfer->onConnectionLost($transfer,$this);
				if(connection_aborted()) {
					$transfer->onAbort($transfer,$this);
				}
				die();
			}
			if($sleep==true OR $tmpTime<=time()) {
				$transfer->transferredBytes = $this->transferred = $this->position-$this->start;
				$transfer->onStatusChange($transfer,$this);
				if(IsSet($tmpTime))
					$tmpTime = time()+1;
			}
			if($sleep==true)
				sleep(1);
		}
		fclose($fp);

		$transfer->transferredBytes = $this->transferred = $this->length;
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
	 * @param FileDownload $file
	 * @return bool TRUE if is compatible; FALSE if not
	 */
	function isCompatible(FileDownload $file) {
		if(self::$checkEnvironmentSettings === true) {
			if(FDTools::setTimeLimit(0)!==true)
				return false;
		}
		return true;
	}
}