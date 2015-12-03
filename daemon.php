<?php
require("includes/functions.php");
require("includes/tasker.php");

// do Init
daemonCallInit();

//periodically call Tasker::doWork()
while(true) {
    Tasker::doWork();
    sleep(1);
}

?>