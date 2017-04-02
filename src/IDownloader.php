<?php

namespace FileDownloader;
use Nette\Http\IRequest;
use Nette\Http\IResponse;

interface IDownloader {

	/**
	 * Download file!
	 * @param FileDownload|FileDownload $file
	 * @param IRequest                   $request  HTTP request
	 * @param IResponse                  $response HTTP response
	 * @throws DownloaderNotSupported   When downloader cannot be used in current environemnt
	 * @return void
	 */
	public function start(FileDownload $file, IRequest $request, IResponse $response);

}


