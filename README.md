File Downloader
===============
[![Code Climate](https://codeclimate.com/github/jkuchar/FileDownloader/badges/gpa.svg)](https://codeclimate.com/github/jkuchar/FileDownloader)

Addon makes controlled downloads of files peace of cake. It supports **client reconnections**, **segmented downloading**, files **over 4GB**, automatic **mime-type detection** and **special characters** in file names. If you need to **control download speed** you are on the right site.

- License: New BSD License
- [Discussion](http://forum.nette.org/cs/1952-addon-file-downloader-file-downloader)
- [Demo](http://filedownloader.projekty.mujserver.net/demo/)


Installation
------------

Install this addon just by calling:
	`composer require jkuchar/filedownloader`

Or to install example, continue to [example repository](https://github.com/jkuchar/FileDownloader-example). (one command set-up)


## Basic usage: Just want to download file ##

Import FileDownloader from it's namespace

```php
use FileDownloader\FileDownload;
```

And use

```php
$filedownload = new FileDownload;
$filedownload->sourceFile = "source.txt";
$filedownload->download();

// or the same thing using fluent-interface
FileDownload::getInstance()
	->setSourceFile("source.txt")
	->download();

```

Advanced usage: combination of advanced features
------------------------------------------------



```php
$filedownload = new FileDownload();
$filedownload->sourceFile = "source.txt";

// apply speed limit (in this case in bytes per second)
$filedownload->speedLimit = 5 * FDTools::BYTE;

// set filename that will be seen by user
$filedownload->transferFileName = "test.txt";

// set mime-type manually (you normally do not need to so this!)
$filedownload->mimeType = "application/pdf";

// show this file directly in browser (do not download it)
$filedownload->contentDisposition =	FileDownload::CONTENT_DISPOSITION_INLINE;

$filedownload->download();
```

The same thing using fluent-interface:
```php

FileDownload::getInstance()
	->setSourceFile("source.txt")
	// Nastavíme rychlost odesílání v bytech
	->setSpeedLimit(5*FDTools::BYTE)
	->setTransferFileName("test.txt")
	->setMimeType("application/pdf")
	->setContentDisposition(
		FileDownload::CONTENT_DISPOSITION_INLINE
	)
	->download();

```

Callbacks
---------

When... ...download is cancelled ...download failed ...download succeded etc.

```php
$filedownload->onAbort[] = "onAbortHandlerFunction"; // here is everything callable accepted

// fluent-interface
FileDownload::getInstance()->addAbortCallback("onAbortHandlerFunction")

// Callback parameters are always the same
function onAbort(FileDownload $download,IDownloader $downloader){
	/* ... */
}
```


| Callback name          | Description
|------------------------|----------------------------
| BeforeDownloaderStarts | Before downloader starts
| BeforeOutputStarts     | Before output to browser starts (last chance to modify HTTP headers)
| StatusChange           | When file download status changes (when block of file is sent or every second if there is no speed limit)
| Complete               | When file download is finished
| Abort                  | When file download is aborted (user clicks cancel)
| ConnectionLost         | When connection is lost (for whatever reason)
| TransferContinue       | When paused transfer continues (this is start event for partial downloads)
| NewTransferStart       | When new transfer from beginning starts (this is start event for downloads from beginning)


In repository there is example *form* that prints on screen all called callbacks.


Technical requirements
----------------------

There are two downloaders **AdvancedDownloader** requires to set infinite time limit (tries to do so automatically). As fallback there is **NativePHPDownloader** available, this downloader requires as much memory as is file size on some PHP installtions. (php bug: if you've found solution, please let me know)


Callbacks, speed controlling and support for big files is only for **AdvancedFileDownloader**!


Support for huge files (over 4 GB)
----------------------

- This is realized through **cURL extension** so please do not forget to enable it. Addon will work also without CURL but very inefficiently.
- Support for >4GB files also requires to add [BigFileTools](https://github.com/jkuchar/BigFileTools) into your libraries (do that using composer)



