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
 * @link http://filedownloader.projekty.mujserver.net
 *
 * @author      Jan Kuchař
 * @copyright   Copyright (c) 2009 Jan Kuchař
 * @author      Jan Kuchař
 * @version     $Id$
 *
 * @property Component $parent Parent component
 */
class AppFileDownload extends BaseFileDownload implements IPresenterResponse {
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
	 * Getts new instance of self
	 * @param Component $parent
	 * @return AppFileDownload
	 */
	public static function getInstance(Component $parent) {
		return new AppFileDownload($parent);
	}

	/**
	 * @param Component $parent
	 */
	function  __construct(Component $parent) {
		parent::__construct();
		$this->setParent($parent);
	}

	/**
	 * Setts AppFileDownload parent
	 * @param Component $parent
	 * @return AppFileDownload
	 */
	function setParent(Component $parent) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * Getts AppFileDownload parent
	 * @return Component
	 */
	function getParent() {
		return $this->parent;
	}

	/**
	 * Implementation of IPresenterResponse::send()
	 */
	function send() {
		parent::download($this->downloader);
	}

	/**
	 * Start download of the file!
	 * @param IDownloader $downloader
	 */
	function download(IDownloader $downloader = null) {
		$this->downloader = $downloader;

		// Call terminate on presenter
		if($this->parent instanceof Presenter) {
			$presenter = $this->parent;
		} else {
			$presenter = $this->parent->lookup("Nette/Application/Presenter",true);
		}

		$presenter->terminate($this);

	}
}