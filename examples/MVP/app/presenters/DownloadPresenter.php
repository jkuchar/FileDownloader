<?php

/**
 * My Application
 *
 * @copyright  Copyright (c) 2009 John Doe
 * @package    MyApplication
 */



/**
 * Homepage presenter.
 *
 * @author     John Doe
 * @package    MyApplication
 */
class DownloadPresenter extends BasePresenter {

	function handleDownloadClassic() {
		$fileDownload = new AppFileDownload($this);
		$fileDownload->sourceFile = __FILE__;
		$fileDownload->speedLimit = 5*FDTools::KILOBYTE;
		$fileDownload->download();
	}

	function handleDownloadFluent() {
		AppFileDownload::getInstance($this)
			->setSourceFile(__FILE__)
			->setSpeedLimit(5*FDTools::KILOBYTE)
			->download();
	}

	function handleDownloadClassicTerminate() {
		$fileDownload = new AppFileDownload($this);
		$fileDownload->sourceFile = __FILE__;
		$fileDownload->speedLimit = 5*FDTools::KILOBYTE;
		$this->terminate($fileDownload);
	}

	function handleDownloadFluentTerminate() {
		$this->terminate(
			AppFileDownload::getInstance($this)
			->setSourceFile(__FILE__)
			->setSpeedLimit(5*FDTools::KILOBYTE)
		);
	}

}
