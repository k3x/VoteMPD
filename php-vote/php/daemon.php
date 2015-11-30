<?php
require("functions.php");
require("tasker.php");

daemonCallInit();
$i = 0;
//todo do daemon process forking
while(true) {
    Tasker::doWork();
    if($i>10) {
        $i=0;
        daemonCallInit();
    } else {
        $i++;
    }
    sleep(1);
}

?>