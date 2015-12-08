<?php
require("includes/functions.php");
require("includes/tasker.php");


if(count(getopt("h::"))>0) {
    echo "Parameters are:\n";
    echo "-h Help\n";
    echo "-f Scan filesystem\n";
    echo "-p Scan playlists\n";
    echo "(no parameters) Run daemon\n";
    die();
}

if(count(getopt("f::"))>0) {
    scanF();
    die();
}

if(count(getopt("p::"))>0) {
    scanP();
    die();
}

echo "Parameters are:\n";
echo "-h Help\n";
echo "-f Scan filesystem\n";
echo "-p Scan playlists\n";
echo "(no parameters) Run daemon\n";
echo "\nrunning daemon\n";

// do Init
daemonCallInit();

//periodically call Tasker::doWork()
while(true) {
    Tasker::doWork();
    sleep(1);
}

?>