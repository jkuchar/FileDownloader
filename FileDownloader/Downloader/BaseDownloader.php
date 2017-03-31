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
use FileDownloader\FDTools;
use FileDownloader\IDownloader;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Object;

/**
 *
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2014 Jan Kuchar
 * @author      Jan Kuchař
 */
abstract class BaseDownloader extends Object implements IDownloader {
	/**
	 * Sends a standard headers for file download
	 * @param Request          $request
	 * @param Response         $rCesponse
	 * @param BaseFileDownload $file       File
	 * @param BaseDownloader   $downloader Downloader of the file
	 * @throws \Nette\InvalidStateException If headers already sent
	 */
	protected function sendStandardFileHeaders(Request $request, Response $response, BaseFileDownload $file, BaseDownloader $downloader=null) {
		//FDTools::clearHeaders($res); // Voláno už v FileDownload.php

		$response->setContentType($file->mimeType, 'UTF-8');
		$response->setHeader('X-File-Downloader', 'File Downloader (http://filedownloader.projekty.mujserver.net)');
		if ($downloader !== null) {
			$response->setHeader('X-FileDownloader-Actual-Script', $downloader::getReflection()->name);
		}

		$response->setHeader('Pragma', 'public'); // Fix for IE - Content-Disposition
		$response->setHeader('Content-Disposition', $file->getContentDisposition() . '; filename="' . FDTools::getContentDispositionHeaderData($request, $file->transferFileName) . '"');
		$response->setHeader('Content-Description', 'File Transfer');
		$response->setHeader('Content-Transfer-Encoding', 'binary');
		$response->setHeader('Connection', 'close');
		$response->setHeader('ETag', FDTools::getETag($file->sourceFile));
		$response->setHeader('Content-Length', FDTools::filesize($file->sourceFile));

		// Cache control
		if ($file->enableBrowserCache) {
			$this->setupCacheHeaders($response, $file);
		} else {
			$this->setupNonCacheHeaders($response, $file);
		}
	}

	protected function setupCacheHeaders(Response $response, BaseFileDownload $file) {
		$response->setExpiration(time() + 99999999);
		$response->setHeader('Last-Modified', 'Mon, 23 Jan 1978 10:00:00 GMT');
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
			$response->setCode(Response::S304_NOT_MODIFIED);
			//header("HTTP/1.1 304 Not Modified");
			exit();
		}
	}

	protected function setupNonCacheHeaders(Response $response, BaseFileDownload $file) {
		$response->setHeader('Expires', '0');
		$response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
	}

}


