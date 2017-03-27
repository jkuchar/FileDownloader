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

namespace FileDownloader\Downloader;

use FileDownloader\BaseFileDownload;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\InvalidStateException;

/**
 *
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2014 Jan Kuchar
 * @author      Jan Kuchař
 */
class NativePHPDownloader extends BaseDownloader {

	/**
	 * Download file!
	 * @param BaseFileDownload $file
	 */
	public function download(Request $request, Response $response, BaseFileDownload $file) {
		$this->sendStandardFileHeaders($request, $response, $file, $this);
		$file->onBeforeOutputStarts($file, $this);

		// Bugfix: when output buffer active, there is a problem with memory
		// @see http://www.php.net/manual/en/function.readfile.php#81032
		while (@ob_end_flush()); // @see example at http://php.net/manual/en/function.ob-end-flush.php
		flush();

		if(!@readfile($file->sourceFile)) {
			throw new InvalidStateException('PHP readfile() function fails!');		}

		// Or use this code? (from http://www.php.net/manual/en/function.readfile.php#50212)
		//
		// $fp = @fopen($file->sourceFile,"rb");
		// fpassthru($fp);
		// fclose($fp);
	}

	/**
	 * Is this downloader compatible?
	 * @param BaseFileDownload $file
	 * @param bool $isLast Is this last downloader in list?
	 * @return bool TRUE if is compatible; FALSE if not
	 */
	public function isCompatible(BaseFileDownload $file) {
		return true;
	}
}

