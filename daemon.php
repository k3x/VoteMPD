<?php
require("includes/functions.php");
require("includes/tasker.php");

function runDaemon() {
    // do Init
    daemonCallInit();

    //periodically call Tasker::doWork()
    while(true) {
        Tasker::doWork();
        sleep(1);
    }
}

function printHelp() {
    echo "Parameters are:\n";
    echo "-h Help\n";
    echo "-f Scan filesystem\n";
    echo "-p Scan playlists\n";
    echo "-d Run daemon\n";
    echo "-t truncate Database (use with caution!)\n";
    echo "(no parameters) Run daemon\n";
}

if(count(getopt("h::"))>0) {
    printHelp();
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

if(count(getopt("d::"))>0) {
    runDaemon();
    die();
}

if(count(getopt("t::"))>0) {
    truncateDatabase();
    die();
}

//no parameters
printHelp();
echo "\nrunning daemon\n";
runDaemon();

?>