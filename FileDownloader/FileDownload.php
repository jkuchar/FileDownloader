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
 * @copyright  Copyright (c) 2014 Jan Kuchar (http://mujserver.net)
 * @license    New BSD License
 * @link       http://filedownloader.projekty.mujserver.net
 */

namespace FileDownloader;

use Exception;
use FileDownloader\Downloader\AdvancedDownloader;
use FileDownloader\Downloader\NativePHPDownloader;
use Nette\Application\BadRequestException;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Object;

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
 * @copyright   Copyright (c) 2014 Jan Kuchar
 * @author      Jan Kuchař
 *
 * @property string $sourceFile         Source file path
 * @property string $transferFileName   File name witch will be used for transfer
 * @property string $mimeType           Mime-type of transferred file
 * @property int $speedLimit            Speed limit
 * @property int $transferredBytes      How many bytes was sent to browser
 * @property int $contentDisposition    Content disposition: inline or attachment
 * @property-read float $sourceFileSize   File size
 */
final class FileDownload extends Object {

	const CONTENT_DISPOSITION_ATTACHMENT = 'attachment';
	const CONTENT_DISPOSITION_INLINE = 'inline';

	/**
	 * Content disposition: attachment / inline
	 * @var string
	 */
	private $vContentDisposition = self::CONTENT_DISPOSITION_ATTACHMENT;

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
	private $vSourceFile;

	/**
	 * Send as filename
	 * @var string|null
	 */
	private $vTransferFileName;

	/**
	 * Mimetype of file
	 * null = autodetection
	 *
	 * @var string|null
	 */
	private $vMimeType;

	/**
	 * Enable browser cache
	 * @var Bool|null to auto
	 */
	public $enableBrowserCache;


	public function __construct($sourceFilePath)
	{
		$this->setSourceFile($sourceFilePath);
	}

	private function setSourceFile($location) {
		if($location === null) {
			$this->vSourceFile = null;
		}else {
			if (!file_exists($location)) {
				throw new BadRequestException("File not found at '" . $location . "'!");
			}
			if (!is_readable($location)) {
				throw new InvalidStateException('File is NOT readable!');
			}
			$this->transferFileName = pathinfo($location, PATHINFO_BASENAME);
			$this->vSourceFile = realpath($location);
		}
		return $this;
	}

	/**
	 * Get location of the source file
	 * @return string
	 */
	public function getSourceFile() {
		return $this->vSourceFile;
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
		$this->vContentDisposition = $disposition;
		return $this;
	}

	/**
	 * Get content disposition
	 * @return string
	 */
	public function getContentDisposition() {
		return $this->vContentDisposition;
	}

	/**
	 * Get send as name
	 * @return string
	 */
	public function getTransferFileName() {
		return $this->vTransferFileName;
	}

	/**
	 * Set send as name
	 * @param string $name
	 * @return FileDownload
	 */
	public function setTransferFileName($name) {
		$this->vTransferFileName = pathinfo($name, PATHINFO_BASENAME);
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
		$this->vSpeedLimit = (int)round($speed);
		return $this;
	}

	/**
	 * Get speed limit
	 * @return int
	 */
	public function getSpeedLimit() {
		return $this->vSpeedLimit;
	}

	/**
	 * Returns mime-type of the file
	 *
	 * @return string           Mime type
	 */
	public function getMimeType() {
		if ($this->vMimeType !== null) {
			return $this->vMimeType;
		}

		return Tools::detectMimeType($this->vSourceFile);
	}

	/**
	 * Set Mime-type
	 * @param string $mime Mime-type
	 * @return FileDownload
	 */
	public function setMimeType($mime) {
		$this->vMimeType = $mime;
		return $this;
	}

	/**
	 * Get file size
	 * @return \Brick\Math\BigInteger
	 */
	public function getSourceFileSize() {
		return Tools::filesize($this->sourceFile);
	}

}




