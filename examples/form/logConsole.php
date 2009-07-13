<?php


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