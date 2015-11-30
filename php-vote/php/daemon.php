<?php
require("functions.php");
require("tasker.php");

daemonCallInit();
//todo do daemon process forking
while(true) {
    Tasker::doWork();
    sleep(1);
}

?>