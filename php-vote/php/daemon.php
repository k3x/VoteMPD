<?php
require("functions.php");
require("tasker.php");

daemonCallInit();

while(true) {
    Tasker::doWork();
    sleep(1);
}

?>