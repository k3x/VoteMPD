<?php

require("includes/settings.php");
require("includes/functions.php");

// error on no action
if(!isset($_GET["action"])) {
    doError("No action specified");
}

//specify what to do on which action
switch($_GET["action"]) {
    case("showhighscore"):
        $r = doShowhighscore();
        if($r===false) doError("Error while getting highscore");
        else doOutput($r,"showhighscore");
        break;
    case("search"):
        if(!isset($_POST["keyword"])) doError("No keyword specified");
        $r = doSearch($_POST["keyword"]);
        if($r===false) doError("Error while searching");
        else doOutput($r,"search");
        break;
    case("vote"):
        if(!isset($_GET["id"])) doError("No id specified");
        $r = doVote($_SERVER['REMOTE_ADDR'],$_GET["id"]);
        doOutput($r,"vote");
        break;
    case("getmyvotes"):
        $r = doGetmyvotes();
        if($r===false) doError("Error while getting your votes");
        else doOutput($r,"getmyvotes");
        break;
    case("getnextsong"):
        doOutput(getNextsongInHighscore(),"getnextsong");
        break;
    case("mpdcurrent"):
        $r = getMpdCurrentSong();
        if($r===false) doError("mpdcurrent failed");
        else doOutput($r,"mpdcurrent");
        break;
    case("getfolderpic"):
        if(!isset($_GET["id"])) doError("No id specified");
        $folder = getFolderPic($_GET["id"]);
        header('Content-type:image/png');
        echo $folder->picture;
        break;
    case("browse-folders"):
        if(!isset($_GET["id"])) doError("No id specified");
        doOutput(getBrowseFolder(intval($_GET["id"])),"browse-folders");
        break;
    case("browse-artists"):
        if(!isset($_POST["name"])) doError("No post-name specified");
        doOutput(getBrowseArtist($_POST["name"]),"browse-artists");
        break;
    case("browse-albums"):
        if(!isset($_POST["name"])) doError("No post-name specified");
        doOutput(getBrowseAlbum($_POST["name"]),"browse-albums");
        break;
    case("browse-playlists"):
        if(!isset($_POST["name"])) doError("No post-name specified");
        doOutput(getBrowsePlaylist($_POST["name"]),"browse-playlists");
        break;
    case("browse-often-playlists"):
        doOutput(getBrowseOftenPlaylist(),"browse-often-playlists");
        break;
    case("browse-often-played"):
        doOutput(getBrowseOftenPlayed(),"browse-often-played");
        break;
    case("browse-often-votes"):
        doOutput(getBrowseOftenVote(),"browse-often-votes");
        break;
    case("browse-playlog"):
        doOutput(getBrowsePlaylog(),"browse-playlog");
        break;
    case("vote-skip-check"):
        doOutput(getVoteSkipCheck(),"vote-skip-check");
        break;
    case("vote-skip-action"):
        doOutput(getVoteSkipAction(),"vote-skip-action");
        break;
    case("upload-file"):
        doUploadFile();
        break;
    case("download-file"):
        doOutput(doDownloadFilelist(),"download-file");
        break;
    case("download-file-do"):
        if(!isset($_GET["id"])) doError("No id specified");
        doDownloadFileDo($_GET["id"]);
        break;
    default: doError("No valid action specified");
}

?>




