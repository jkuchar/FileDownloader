<?php

/**
 * Copyright (c) 2009, Jan Kucha�
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
 * @author     Jan Kucha�
 * @copyright  Copyright (c) 2009 Jan Kucha� (http://mujserver.net)
 * @license    New BSD License
 * @link       http://filedownloader.projekty.mujserver.net
 * @version    $Id$
 */


define("APP_DIR",dirname(__FILE__));

require_once "../load.php";
require_once "example_library.php";

if(IsSet($_GET["logConsole"]))
	require "logConsole.php";

use Nette\Environment;
use Nette\Forms\Form;

// Generate form
$f = new Form("upload-form");
$f->getElementPrototype()->id = "frm";
$f->setMethod("GET");
$f->addSelect("speed", "Speed",array(
	1                     =>"1byte/s", 
	50                    =>"50bytes/s",
	512                   =>"512bytes/s",
	1*FDTools::KILOBYTE   =>"1kb/s",
	5*FDTools::KILOBYTE   =>"5kb/s",
	20*FDTools::KILOBYTE  =>"20kb/s",
	32*FDTools::KILOBYTE  =>"32kb/s",
	50*FDTools::KILOBYTE  =>"50kb/s",
	64*FDTools::KILOBYTE  =>"64kb/s",
	100*FDTools::KILOBYTE =>"100kb/s",
	128*FDTools::KILOBYTE =>"128kb/s",
	200*FDTools::KILOBYTE =>"200kb/s",
	256*FDTools::KILOBYTE =>"256kb/s",
	300*FDTools::KILOBYTE =>"300kb/s",
	512*FDTools::KILOBYTE =>"512kb/s",
	1*FDTools::MEGABYTE   =>"1mb/s",
	2*FDTools::MEGABYTE   =>"2mb/s",
	5*FDTools::MEGABYTE   =>"5mb/s",
	10*FDTools::MEGABYTE  =>"10mb/s",
	0                     =>"Unlimited"
));

$f->addText("filename", "Filename")
	->addRule(Form::FILLED, "You must fill name!");

$f->addSelect("size", "Size", array(
	1=>"1MB",
	4=>"4MB",
	8=>"8MB",
	16=>"16MB",
	32=>"32MB",
	64=>"64MB",
	128=>"128MB",
	256=>"256MB",
	512=>"512MB",
));

$f->addSelect("log", "Log called events?",array(
	0=>"No",
	1=>"Yes (may cause CPU load)",
));

$f->addSubmit("download", "Download!");

$f->setDefaults(array(
	"speed"=>50,
	"filename"=>"Some horrible file name - ěščřžýáíé.bin",
	"size"=>8,
	"log"=>1,
));

if($f->isSubmitted() and $f->isValid()) {
	\Nette\Diagnostics\Debugger::enable(\Nette\Diagnostics\Debugger::PRODUCTION); // Log errors to file!
	$val = $f->getValues();
	$location = dirname(__FILE__)."/cache/test-".$val["size"]."MB.tmp";
	if(!file_exists($location)) generateFile($location, $val["size"]*1024);

	/* Interface with getters and setters */
	$file = new FileDownload;
	$file->sourceFile = $location;
	$file->transferFileName = $val["filename"];
	$file->speedLimit = (int)$val["speed"];
	//$file->mimeType = $val["mimeType"];

	/* Functions defined in example_library.php */
	if($val["log"]==1) {
		$file->onBeforeDownloaderStarts[]   = "onBeforeDownloaderStarts";
		$file->onBeforeOutputStarts[]       = "onBeforeOutputStarts";
		$file->onStatusChange[]             = "onStatusChange";
		$file->onComplete[]                 = "onComplete";
		$file->onConnectionLost[]           = "onConnectionLost";
		$file->onAbort[]                    = "onAbort";
		$file->onTransferContinue[]         = "onTransferContinue";
		$file->onNewTransferStart[]         = "onNewTransferStart";
	}
	$file->download();

	/* Fluent interface is as separate example */

}



?>
<html>
	<head>
		<title>Example of FileDownloader</title>
		<style type="text/css">
			body form table tr td input,body form table tr td select{
				width: 100%;
			}
			body tr th{
				text-align: right;
			}
			body form table{
				width: 100%;
			}
			body form{
				text-align: left;
				display: block;
				width: 500px;
				margin: 0px auto;
			}
			body{
				text-align: center;
			}
		</style>
		<script src="http://jqueryjs.googlecode.com/files/jquery-1.3.2.min.js" type="text/JavaScript"></script>
	</head>
	<body>
		<h1>File Downloader example</h1>
		<?php
		/* Compatibility check -> makes warning */
		$adownloader = new AdvancedDownloader;
		if(!$adownloader->isCompatible(FileDownload::getInstance())) {
			echo "<div style=\"background-color: red;color: white;font-weight: bold;\">Your system is not compatible with AdvancedDownloader (time limit is not zero) -> now running in compatibility mode! All fetures will NOT be available.</div>";
		}

		echo $f;
		?>
		<script type="text/JavaScript">
			$("#frm-download").parent().parent().hide();
			function refreshLink(){
				var jfrm = $("#frm");
				var frm = jfrm.get(0);
				if(frm.action=="") frm.action = "?";
				var str = frm.action+jfrm.serialize();
				$("#link")[0].href = str;
			}
			$("body").append("<a href=\"javascript:;\" id=\"link\">Start download!</a>");
			refreshLink();
			$("form *").click(refreshLink).keypress(refreshLink).change(refreshLink);
			function refreshLog(){
				$("#logConsole").load("?logConsole");
			}
			$(function(){
				refreshLog();
				setInterval(refreshLog, 1000);
			});
		</script>
		<div id="logConsole">For using log console you must enable JavaScript!</div>
	</body>
</html>