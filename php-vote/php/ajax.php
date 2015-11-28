<?php

require("../../settings.php");
require("functions.php");

if(!isset($_GET["action"])) {
    doError("No action specified");
}



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
        if($r===false) doError("Error while getting highscore");
        else doOutput($r,"search");
        break;
    default: doError("No valid action specified");

}


?>