<?php

// TODO: Split request parsing
// TODO: abstraction: sending blocks (do not care where from they come)

// TODO: Floating buffer (buffer size changing dynamically by the speed of client)
// TODO: Add custom priority of download modules
// TODO: Move from float to strings and use bcmath for computations

namespace FileDownloader;

use Exception;
use FileDownloader\DownloaderNotSupported;
use FileDownloader\FileDownload;
use FileDownloader\FileDownloaderException;
use FileDownloader\IDownloader;
use FileDownloader\Tools;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Response;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;

/**
 * File downloader with support for file speed regulation and reconnection.
 *
 * Callbacks:
 * @method void onBeforeOutputStarts(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onStatusChange(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onComplete(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onTransferContinue(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onNewTransferStart(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onAbort(FileDownload $fileDownload, IDownloader $downloader)
 * @method void onConnectionLost(FileDownload $fileDownload, IDownloader $downloader)
 */
class AdvancedDownloader implements IDownloader {

	/**
	 * Check for environment configuration?
	 * @var bool
	 */
	public static $checkEnvironmentSettings = true;

	public $size = 0;
	public $start = 0;
	public $end = 0;
	public $length = 0;
	public $position = 0;
	public $transferred = 0;

	/**
	 * @var FileDownload
	 */
	public $currentTransfer;

	protected $buffer;

	/**
	 * @internal
	 * @var boolean
	 */
	protected $sleep;

	public function start(FileDownload $file, IRequest $request, IResponse $response) {
		if(!$this->isCompatible($file)) {
			throw new DownloaderNotSupported('Please check you P');
		}

		$this->currentTransfer = $file;
		$this->sendStandardFileHeaders($request, $response, $file,$this);

		@ignore_user_abort(true); // For onAbort event

		$sourceFile = $file->getSourceFile();
		$filesize = $this->size   = $sourceFile;
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
		$response->setHeader('Accept-Ranges', 'bytes'); // multi-part (through Mozilla)
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2

		if ($request->getHeader('Range', false)) // If partial download
		{
			try {
				$range_start = $this->start;
				$range_end   = $this->end;

				// Extract the range string
				$rangeArray = explode('=', $request->getHeader('Range'), 2);
				$range = $rangeArray[1];

				// Make sure the client hasn't sent us a multibyte range
				if (strpos($range, ',') !== false) {
					// (?) Shoud this be issued here, or should the first
					// range be used? Or should the header be ignored and
					// we output the whole content?
					throw new FileDownloaderException('HTTP 416',416);
				}

				// If the range starts with an '-' we start from the beginning
				// If not, we forward the file pointer
				// And make sure to get the end byte if spesified
				if ($range{0} === '-') {
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
					throw new FileDownloaderException('HTTP 416',416);
				}

				// All is ok - so assign variables back
				$this->start  = $range_start;
				$this->end    = $range_end;
				$this->length = $this->end - $this->start + 1; // Calculate new content length
			} catch (FileDownloaderException $e) {
				if ($e->getCode() === 416) {
					$response->setHeader('Content-Range', "bytes $this->start-$this->end/$this->size");
					Tools::sendHttpError($response, 416);
				} else {
					throw $e;
				}
			}
			$response->setCode(206); // Partial content
		} // End of if partial download

		// Notify the client the byte range we'll be outputting
		$response->setHeader('Content-Range',"bytes $this->start-$this->end/$this->size");
		$response->setHeader('Content-Length',$this->length);

		/* ### Call callbacks ### */

		$this->onBeforeOutputStarts($file,$this);
		if ($this->start > 0) {
			$this->onTransferContinue($file, $this);
		} else {
			$this->onNewTransferStart($file, $this);
		}

		/* ### Send file to browser - document body ### */

		$buffer = Tools::$readFileBuffer;
		$sleep = false;

		$speedLimit = $file->getSpeedLimit();
		if(is_int($speedLimit) && $speedLimit > 0) {
			$sleep  = true;
			$buffer = (int)round($speedLimit);
		}
		$this->sleep = $sleep;

		if ($buffer < 1) {
			throw new InvalidArgumentException('Buffer must be bigger than zero!');
		}
		$availableMem = Tools::getAvailableMemory();
		if ($availableMem && $buffer > ($availableMem - memory_get_usage())) {
			throw new InvalidArgumentException('Buffer is too big! (bigger than available memory)');
		}
		$this->buffer = $buffer;

		/** @noinspection ReturnFalseInspection checked later */
		$fp = fopen($sourceFile, 'rb');
		// TODO: Add flock() READ
		if ($fp === FALSE) {
			throw new InvalidStateException("Can't open file for reading!");
		}
		if ($this->end === null) {
			$this->end = $filesize - 1;
		}


		if(fseek($fp, $this->start, SEEK_SET) === -1) { // Move file pointer to the start of the download
			// Can not move pointer to beginning of the filetransfer

			if($this->processByCUrl() === true) {
				// Request was hadled by curl, clean, exit
				$this->onComplete($file, $this);
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
			// We are at the beginning
			$this->position = $this->start;
		}

		$this->processNative($fp);
		$this->cleanAfterTransfer();
		$this->onComplete($file, $this);
	}

	protected function cleanAfterTransfer() {
		$this->currentTransfer->transferredBytes = $this->transferred = $this->length;
		$this->currentTransfer = null;
	}

	protected function processNative($fp) {
		$tmpTime = null;
		if ($this->sleep === false) {
			// Call onStatusChange next second!
			$tmpTime = time() + 1;
		}

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
		if(!function_exists('curl_init')) {return false;}

		$transfer = $this->currentTransfer;

		$ch = curl_init('file://' . realpath($transfer->getSourceFile()));
		$range = $this->start.'-'.$this->end; // HTTP range
		curl_setopt($ch, CURLOPT_RANGE, $range);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BUFFERSIZE, $this->buffer);
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this,
			'_curlProcessBlock'
		));
		$curlRet = curl_exec($ch);
		if($curlRet === false) {
			throw new Exception('cUrl error number ' .curl_errno($ch). ': ' .curl_error($ch));
		}
		return true;
	}

	/**
	 * @internal
	 */
	public function _curlProcessBlock($ch, $chunk) {
		static $curl;
		static $tmpTime;

		if($curl !== $ch) { // Set defaults
			$tmpTime = null;
			if ($this->sleep === false) {
				 // Call onStatusChange next second!
				$tmpTime = time() + 1;
			}
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

		if(connection_status() !== CONNECTION_NORMAL) {
			if ($fp) {
				fclose($fp);
			}
			$this->onConnectionLost($transfer,$this);
			if(connection_aborted()) {
				$this->onAbort($transfer,$this);
			}
			die();
		}
		if($this->sleep === true || $tmpTime<=time()) {
			$transfer->transferredBytes = $this->transferred = $this->position-$this->start;
			$this->onStatusChange($transfer,$this);
			if ($tmpTime !== NULL) {
				$tmpTime = time() + 1;
			}
		}
		if ($this->sleep === true) {
			sleep(1);
		}
	}

	/**
	 * Is this downloader initialized?
	 * @return bool
	 */
	public function isInitialized() {
		if ($this->end === 0) {
			return false;
		}
		return true;
	}


	/**
	 * Is this downloader compatible?
	 * @param FileDownload $file
	 * @return bool TRUE if is compatible; FALSE if not
	 */
	public function isCompatible(FileDownload $file) {
		if(self::$checkEnvironmentSettings === true) {
			if (Tools::setTimeLimit(0) !== true) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Sends a standard headers for file download
	 * @param IRequest        $request
	 * @param IResponse       $response
	 * @param FileDownload   $file       File
	 * @param AdvancedDownloader $downloader Downloader of the file
	 * @throws \Nette\InvalidStateException If headers already sent
	 */
	private function sendStandardFileHeaders(IRequest $request, IResponse $response, FileDownload $file, AdvancedDownloader $downloader=null) {
		//Tools::clearHeaders($res); // Voláno už v FileDownload.php

		$response->setContentType($file->getMimeType(), 'UTF-8');
		$response->setHeader('X-File-Downloader', 'File Downloader (https://github.com/jkuchar/FileDownloader)');
		if ($downloader !== null) {
			$response->setHeader('X-FileDownloader-Actual-Script', get_class($downloader));
		}

		$response->setHeader('Pragma', 'public'); // Fix for IE - Content-Disposition
		$response->setHeader('Content-Disposition', $file->getContentDisposition() . '; filename="' . Tools::getContentDispositionHeaderData($request, $file->getTransferFileName()) . '"');
		$response->setHeader('Content-Description', 'File Transfer');
		$response->setHeader('Content-Transfer-Encoding', 'binary');
		$response->setHeader('Connection', 'close');
		$response->setHeader('ETag', Tools::getETag($file->getSourceFile()));
		$response->setHeader('Content-Length', Tools::filesize($file->getSourceFile()));

		// Cache control
		if ($file->cacheContent) {
			$this->setupCacheHeaders($response, $file);
		} else {
			$this->setupNonCacheHeaders($response, $file);
		}
	}

	private function setupCacheHeaders(IResponse $response, FileDownload $file) {
		$response->setExpiration(time() + 99999999);
		$response->setHeader('Last-Modified', 'Mon, 23 Jan 1978 10:00:00 GMT');
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			$response->setCode(Response::S304_NOT_MODIFIED);
			//header("HTTP/1.1 304 Not Modified");
			exit();
		}
	}

	private function setupNonCacheHeaders(IResponse $response, FileDownload $file) {
		$response->setHeader('Expires', '0');
		$response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
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
	 * @return void
	 */
	public function addBeforeOutputStartsCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addStatusChangeCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addCompleteCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addTransferContinueCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addNewTransferStartCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addAbortCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
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
	 * @return void
	 */
	public function addConnectionLostCallback($callback) {
		$this->addCallback(__METHOD__, $callback);
	}

	/**
	 * Adds callback
	 * @param string $name          Name of callback
	 * @param callback $callback    Callback
	 */
	private function addCallback($fceName, $callback) {
		preg_match('/^.*::add(.*)Callback$/', $fceName, $matches);
		$varName = 'on' .$matches[1];
		$var = &$this->$varName;
		$var[] = $callback;
	}


	/**
	 * How many bytes is sent
	 * @var int
	 */
	public $transferredBytes = 0;

}

