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

use Nette\Application\IResponse as IResponse2;
use Nette\Application\UI\Presenter;
use Nette\ComponentModel\Component;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\Session;

/**
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2014 Jan Kuchar
 * @author      Jan Kuchař
 *
 * @property Component $parent Parent component
 */
class AppFileDownload extends BaseFileDownload implements IResponse2 {
	/**
	 * Parent of this object
	 * @var Component
	 */
	private $parent;

	/**
	 * Downloader used to download file (optional)
	 * @var IDownloader|null
	 */
	private $downloader;

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var Response
	 */
	private $response;

	/**
	 * @var Session
	 */
	private $session;


	/**
	 * Getts new instance of self
	 * @param Component $parent
	 * @return AppFileDownload
	 * @deprecated
	 */
	public static function getInstance(Component $parent, Request $request, Response $response, Session $session) {
		return new AppFileDownload($parent, $request, $response, $session);
	}

	/**
	 * @param Component $parent
	 */
	public function __construct(Component $parent, Request $request, Response $response, Session $session) {
		parent::__construct();
		$this->setParent($parent);
		$this->request = $request;
		$this->response = $response;
		$this->session = $session;
	}

	/**
	 * Setts AppFileDownload parent
	 * @param Component $parent
	 * @return AppFileDownload
	 */
	public function setParent(Component $parent) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * Getts AppFileDownload parent
	 * @return Component
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * Implementation of IPresenterResponse::send()
	 */
	public function send(IRequest $httpRequest, IResponse $httpResponse) {
		parent::download($this->downloader, $this->request, $this->response, $this->session);
	}

	/**
	 * Start download of the file!
	 * @param IDownloader $downloader
	 */
	public function download(IDownloader $downloader = null, Request $request, Response $response, Session $session) {
		$this->downloader = $downloader;

		// Call sendResponse on presenter (used since 2.0 instead of terminate)
		if($this->parent instanceof Presenter) {
			$presenter = $this->parent;
		} else {
			$presenter = $this->parent->lookup('Nette/Application/UI/Presenter',true);
		}

		$presenter->sendResponse($this);

	}

}


