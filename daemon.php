<?php
require("includes/functions.php");
require("includes/tasker.php");

daemonCallInit();

while(true) {
    Tasker::doWork();
    sleep(1);
}

?>