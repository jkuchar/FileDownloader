<?php

namespace FileDownloader;

use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Session;

class DownloadResponse implements \Nette\Application\IResponse {

	/**
	 * Downloader used to download file (optional)
	 * @var IDownloader|null
	 */
	private $downloader;

	/**
	 * @var Session
	 */
	private $session;

	/**
	 * @var \FileDownloader\FileDownload
	 */
	private $fileDownload;

	public function __construct(
		IDownloader $downloader,
		FileDownload $fileDownload,
		Session $session
	) {
		$this->session = $session;
		$this->downloader = $downloader;
		$this->fileDownload = $fileDownload;
	}


	/* Implementation of IPresenterResponse::send() */
	public function send(IRequest $httpRequest, IResponse $httpResponse) {
		if ($this->session->isStarted()) {
			$this->session->close();
		}
		$this->downloader->start(
			$this->fileDownload,
			$httpRequest,
			$httpResponse
		);
	}

}


