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
 * @version    $Id$
 */

require_once "nette/loader.php";
require_once "example_library.php";

date_default_timezone_set("Europe/Prague");

$loader = new RobotLoader();
$loader->addDirectory(dirname(__FILE__)."/..");
$loader->register();

Debug::enable();

// This i needed to cache works ok
define("APP_DIR",dirname(__FILE__));

FileDownload::$defaults["speedLimit"] = 10*FDTools::KILOBYTE;

Environment::getHttpResponse()->setContentType("text/html", "UTF-8");

if(IsSet($_GET["logConsole"])){
    Environment::getHttpResponse()->setHeader("refresh", "1");
    $cache = Environment::getCache("FileDownloader/log");
    echo "<html><body>";
    echo "<h1>Log console (called events)</h1>";
    echo "<p>Clear log = delete temp files</p>";
    echo "<style>p{font-size: 11px;font-family: monospace;}</style>";
    $reg = $cache["registry"];
    $y=0;
    if(count($reg)>0){
        krsort($reg);
        foreach($reg AS $tid => $none){
            $y++;
            $tid=(string)$tid;
            $log = $cache[$tid];
            $i=0;
            if(count($log)>0){
                krsort($log);
                foreach($log AS $key => $val){
                    if($i==0){
                        echo "<h2>Con. #".$tid;
                        if(strstr($val,"Abort"))
                            echo " <span style=\"color: orange;\">(Aborted)</span>";
                        elseif(strstr($val,"Lost"))
                            echo " <span style=\"color: red;\">(Connection losted)</span>";
                        elseif(strstr($val,"Complete"))
                            echo " <span style=\"color: green;\">(Completed)</span>";
                        else
                            echo " (Running)";
                        echo "</h2>";
                        echo "<p>";
                    }
                    $i++;
                    echo $key.": ".$val."<br>";
                    if($i>=10) break;
                }
                echo "</p>";
            }else echo "No items to display.";
            if($y>=7) break;
        }
    }else echo "No items to display.";
    echo "</body></html>";
    exit;
}

// Generate form
$f = new Form;
$f->setMethod("GET");
$f->addSelect("speed", "Speed",array(1=>"1byte/s",50=>"50bytes/s",512=>"512bytes/s",1*FDTools::KILOBYTE=>"1kb/s",5*FDTools::KILOBYTE=>"5kb/s",20*FDTools::KILOBYTE=>"20kb/s",0=>"Unlimited"));

$f->addText("filename", "Filename")
  ->addRule(Form::FILLED, "You must fill name!");

$f->addSelect("size", "Size", array(1=>"1MB",4=>"4MB",8=>"8MB"));
//$f->addSelect("mimeType", "Mime type", array("text/plain"=>"text/plain",null=>"autodetect"));

$f->addSubmit("download", "Download!")->getControlPrototype()->onClick = "window.open('?logConsole',null,'width=1000,height=400,menubar=yes,resizable=yes,scrollbars=yes');";

$f->setDefaults(array(
  "speed"=>50,
  "filename"=>"tmp[{ěščřžýáíéůú}].tmp",
  "size"=>8,
));

$file = new FileDownload;
if($f->isSubmitted() and $f->isValid()){
    $val = $f->getValues();
    $location = dirname(__FILE__)."/temp/test-".$val["size"]."MB.tmp";
    if(!file_exists($location)) generateFile($location, $val["size"]*1024);
    $file->sourceFile = $location;
    $file->transferFileName = $val["filename"];
    $file->speedLimit = (int)$val["speed"];
    //$file->mimeType = $val["mimeType"];

    /* Functions defines in example_library.php */
    $file->onBeforeDownloaderStarts[]   = "onBeforeDownloaderStarts";
    $file->onBeforeOutputStarts[]       = "onBeforeOutputStarts";
    $file->onStatusChange[]             = "onStatusChange";
    $file->onComplete[]                 = "onComplete";
    $file->onConnectionLost[]           = "onConnectionLost";
    $file->onAbort[]                    = "onAbort";
    $file->onTransferContinue[]         = "onTransferContinue";
    $file->onNewTransferStart[]         = "onNewTransferStart";
    $file->download();
}
$adownloader = new AdvancedDownloader;
if(!$adownloader->isCompatible($file)) {
    echo "<div style=\"background-color: red;color: white;font-weight: bold;\">Your system is not compatible with AdvancedDownloader (time limit is not zero) -> now running in compatibility mode! All fetures will NOT be available.</div>";
}

echo $f;
