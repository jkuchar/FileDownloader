<?php

namespace FileDownloader;

use Exception;
use FileDownloader\AdvancedDownloader;
use FileDownloader\Downloader\NativePHPDownloader;
use Nette\Application\BadRequestException;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Object;

/**
 * File download parameters
 */
final class FileDownload {

	const CONTENT_DISPOSITION_ATTACHMENT = 'attachment';
	const CONTENT_DISPOSITION_INLINE = 'inline';

	/**
	 * Content disposition: attachment / inline
	 * @var string
	 */
	private $contentDisposition = self::CONTENT_DISPOSITION_ATTACHMENT;

	/**
	 * Maximal speed of download (in kb/s)
	 * 0 is unlimited
	 * @var int
	 */
	private $speedLimit = 0;

	/**
	 * Location of the file
	 * @var string|null
	 */
	private $sourceFile;

	/**
	 * Send as filename
	 * @var string|null
	 */
	private $transferFileName;

	/**
	 * Mimetype of file
	 * null = autodetection
	 *
	 * @var string|null
	 */
	private $mimeType;

	/**
	 * Enable browser cache
	 * @var Bool|null to auto
	 */
	public $cacheContent;


	public function __construct($sourceFilePath)
	{
		$this->setSourceFile($sourceFilePath);
	}

	private function setSourceFile($location) {
		if($location === null) {
			throw UsageException::noSourceFile();
		}

		if (!file_exists($location)) {
			throw UsageException::noSourceFile();
		}
		if (!is_readable($location)) {
			throw UsageException::sourceFileNotReadable();
		}

		$this->transferFileName = pathinfo($location, PATHINFO_BASENAME);

		/** @noinspection ReturnFalseInspection checked few lines later */
		$sourceFile = realpath($location);
		if($sourceFile === FALSE) {
			throw FilesystemException::realpathPermissions();
		}
		$this->sourceFile = $sourceFile;
	}


	/**
	 * Set content disposition
	 * @param string $disposition
	 * @return FileDownload
	 */
	public function setContentDisposition($disposition) {
		$values = array(self::CONTENT_DISPOSITION_ATTACHMENT, self::CONTENT_DISPOSITION_INLINE);
		if (!in_array($disposition, $values, TRUE)) {
			throw new InvalidArgumentException('Unknown value. Use FileDownload::CONTENT_DISPOSITION_* constants.');
		}
		$this->contentDisposition = $disposition;
		return $this;
	}



	/**
	 * Set send as name
	 * @param string $name
	 * @return FileDownload
	 */
	public function setTransferFileName($name) {
		$this->transferFileName = pathinfo($name, PATHINFO_BASENAME);
		return $this;
	}


	/**
	 * Set speed limit
	 * @param int $speed Speed limit
	 * @return FileDownload
	 */
	public function setSpeedLimit($speed) {
		if (!is_int($speed)) {
			throw new InvalidArgumentException('Max download speed must be integer!');
		}
		if ($speed < 0) {
			throw new InvalidArgumentException("Max download speed can't be smaller than zero!");
		}
		$availableMem = Tools::getAvailableMemory();
		if ($availableMem) {
			$availableMemWithReserve = ($availableMem-100*1024);
			if ($speed > $availableMemWithReserve) {
				throw new InvalidArgumentException("Max download speed can't be a bigger than available memory " . $availableMemWithReserve . 'b!');
			}
		}
		$this->speedLimit = (int)round($speed);
		return $this;
	}

	/**
	 * Set Mime-type
	 * @param string $mime Mime-type
	 * @return FileDownload
	 */
	public function setMimeType($mime) {
		$this->mimeType = $mime;
		return $this;
	}

	/**
	 * Get speed limit
	 * @return int
	 */
	public function getSpeedLimit() {
		return $this->speedLimit;
	}

	/**
	 * Returns mime-type of the file
	 *
	 * @return string           Mime type
	 */
	public function getMimeType() {
		if ($this->mimeType !== null) {
			return $this->mimeType;
		}

		return Tools::detectMimeType($this->sourceFile);
	}

	/**
	 * Get send as name
	 * @return string
	 */
	public function getTransferFileName() {
		return $this->transferFileName;
	}

	/**
	 * Get location of the source file
	 * @return string
	 */
	public function getSourceFile() {
		return $this->sourceFile;
	}

	/**
	 * Get content disposition
	 * @return string
	 */
	public function getContentDisposition() {
		return $this->contentDisposition;
	}

	/**
	 * Get file size
	 * @return \Brick\Math\BigInteger
	 */
	public function getSourceFileSize() {
		return Tools::filesize($this->sourceFile);
	}

}




