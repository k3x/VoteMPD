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
    case("search"):
        if(!isset($_POST["keyword"])) {
            doError("No keyword specified");
        }
        $r = doSearch($_POST["keyword"]);
        if($r===false) doError("Error while searching");
        else doOutput($r,"search");
        break;
    case("vote"):
        if(!isset($_GET["id"])) {
            doError("No id specified");
        }
        $r = doVote($_SERVER['REMOTE_ADDR'],$_GET["id"]);
        if($r===false) doError("Error while voting");
        else doOutput($r,"vote");
        break;
    case("getmyvotes"):
        $r = doGetmyvotes();
        if($r===false) doError("Error while getting your votes");
        else doOutput($r,"getmyvotes");
        break;
    case("getnextsong"):
        $r = getNextsongInHighscore();
        if($r===null) {
            doError("Getnext failed");
        } else {
            doOutput($r,"getnextsong");
        }
        break;
    case("mpdcurrent"):
        $r = getMpdCurrentSong();
        if($r===false) {
            doError("mpdcurrent failed");
        } else {
            doOutput($r,"mpdcurrent");
        }
        break;
    case("getfolderpic"):
        if(!isset($_GET["id"])) {
            doError("No id specified");
        }
        $folder = getFolderPic($_GET["id"]);
        header('Content-type:image/png');
        echo $folder->picture;
        break;
    case("browse-folders"):
        if(!isset($_GET["id"])) {
            doError("No id specified");
        }
        $r = getBrowseFolder(intval($_GET["id"]));
        doOutput($r,"browse-folders");
        break;
    case("browse-artists"):
        if(!isset($_POST["name"])) {
            doError("No post-name specified");
        }
        $r = getBrowseArtist($_POST["name"]);
        doOutput($r,"browse-artists");
        break;
    case("browse-albums"):
        if(!isset($_POST["name"])) {
            doError("No post-name specified");
        }
        $r = getBrowseAlbum($_POST["name"]);
        doOutput($r,"browse-albums");
        break;
    default: doError("No valid action specified");
}

?>




