<?php declare(strict_types=1);
/**
 * This file is part of FileDownloader.
 */

namespace BlockReader;

use FileDownloader\CannotSeekToPosition;

final class NativeBlockReader
{
	private $filePointer;
	private $fromByte;
	private $toByte;
	private $blockSize;

	/**
	 * @param resource $filePointer
	 * @param int $fromByte
	 * @param int $toByte
	 * @param int $blockSize
	 */
	public function __construct($filePointer, $fromByte, $toByte, $blockSize)
	{
		$this->filePointer = $filePointer;
		$this->fromByte = $fromByte;
		$this->toByte = $toByte;
		$this->blockSize = $blockSize;
	}


	/**
	 * @param callable $callback
	 * @throws CannotSeekToPosition
	 */
	public function start(callable $callback)
	{
		$fp = $this->filePointer;

		if(fseek($fp, $this->fromByte, SEEK_SET) === -1) { // Move file pointer to the start of the download
			// Can not move pointer to beginning of the filetransfer
			throw new CannotSeekToPosition();
		}

		// We are at the beginning
		$position = $this->fromByte;

		$bufferSize = $this->blockSize;
		while(!feof($fp) && $position <= $this->toByte) {
			if ($position + $bufferSize > $this->toByte) {
				// In case we're only outputtin a chunk, make sure we don't
				// read past the length
				$bufferSize = $this->toByte - $position + 1;
			}
			$data = fread($fp, $bufferSize);

			if($callback($data, $position) === FALSE) {
				return;
			}

			$position += strlen($data);
			unset($data);
		}
	}
}
