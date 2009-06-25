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

require "nette/loader.php";
require "../FileDownloader/FileDownloader.php";

Debug::enable();
Debug::enableProfiler();

// This i needed to cache works ok
define("APP_DIR",dirname(__FILE__));
define("TMP_DIR",APP_DIR."/tmp");

// Chceme to česky :)
Environment::getHttpResponse()->setContentType("text/html", "UTF-8");

if(!IsSet($_GET["action"])){
  ?>
  <html>
    <head>
      <title>File Downloader example</title>
      <style type="text/css">
        table tr th{
          text-align: right;
        }

        body,html{
          margin:  0px;
          padding: 0px;
          text-align: center;
        }

        h1{
          margin-top: 0px;
          padding-bottom: 15px;
          border-bottom: 1px solid black;
          text-align: center;
        }

        body .kontejner{
          padding: 20px;
          margin: 0px auto;
          width: 500px;
          text-align: left;
        }
      </style>
    </head>
  <body>
    <div class="kontejner">
      <h1>File Downloader example</h1>
      <p style="font-weight:bold;padding: 10px;background-color: #ffeac9;border: 2px solid #ffb541;-moz-border-radius: 8px;">When you downloading first time file of the some size, it can take some time before download dialog will appears. Server generating file with the required size.</p>

      <h2>File Downloader example form</h2>
      <p>Here you can test settings of File Downloader.</p>
      <?
        // Generate form
        $f = new Form;
        $f->setMethod("GET");
        $f->addHidden("action")->setValue("download");

        $f->addText("speed", "Downloading speed in kb/s (0=unlimited)", "4", 4);
        $f["speed"]->addRule(Form::FILLED,"Download speed must be filled!")
          ->addRule(Form::INTEGER,"Download speed must be intiger!")
          ->addRule(Form::RANGE,"Download speed must be in range from %d to %d kb/s",array(0,2000));

        $f->addText("filename", "As what file name you want do download the file?")
          ->addRule(Form::FILLED, "You must fill file name!");

        $f->addText("size", "Size of file for download", 2, 2)
          ->addRule(Form::INTEGER, "Size must be intinger")
          ->addRule(Form::RANGE,"Size is not in range from %d to %d.",array(1,64))
          ->addRule(Form::FILLED,"Size must be filled");

        $f->addSubmit("download", "Download!");
        
        $f->setDefaults(array(
          "speed"=>10,
          "filename"=>"test_file.tmp",
          "size"=>"8",
        ));
        echo $f;
      ?>
    </div>
  </body>
  </html>
    <?

}elseif($_GET["action"]=="download"){
  function generateFile($location,$size){
    $fp = fopen($location,"wb");
      $toWrite = "";
      for($y=0;$y<1024;$y++){ // One kb of content
        $toWrite .= chr(rand(0,255));
      }
      for($i=0;$i<$size;$i++){
        FWrite($fp,$toWrite);
      }
    fclose($fp);
  }

  if(!isSet($_GET["speed"])) $_GET["speed"] = 0;
  if(!isSet($_GET["filename"])) $_GET["filename"] = "some_file.tmp";
  if(!isSet($_GET["size"])) $_GET["size"] = 8;
  FileDownloader::$maxDownloadSpeed = (int)$_GET["speed"];

  if(!file_exists("temp/test-".(int)$_GET["size"]."MB.tmp")){
    generateFile("temp/test-".(int)$_GET["size"]."MB.tmp", 1024*(int)$_GET["size"]); // 8MB file
  }

  FileDownloader::download(dirname(__FILE__)."/temp/test-".(int)$_GET["size"]."MB.tmp",(string)$_GET["filename"]);
}else throw new BadRequestException("Page not found",404);
