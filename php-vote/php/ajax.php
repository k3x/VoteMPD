<?php

require("../../settings.php");
require("functions.php");

if(!isset($_GET["action"])) {
    doError("No action specified");
}

//$_SERVER['REMOTE_ADDR'] = 127.0.0.1

switch($_GET["action"]) {
    case("showhighscore"): 
        $r = doShowhighscore();
        if($r===false) doError("Error while getting highscore");
        else doOutput($r,"showhighscore");
        break;
    case("search"): //TODO keyword post, mit ajax
        if(!isset($_GET["keyword"])) {
            doError("No keyword specified");
        }
        $r = doSearch($_GET["keyword"]);
        if($r===false) doError("Error while searching");
        else doOutput($r,"search");
        break;
    case("getmyvotes"):
        $r = doGetmyvotes();
        if($r===false) doError("Error while getting your votes");
        else doOutput($r,"getmyvotes");
        break;
    default: doError("No valid action specified");

}


?>