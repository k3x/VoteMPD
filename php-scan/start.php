<?php
require("../settings.php");
require_once('getid3/getid3.php');
$getID3 = new getID3;
doOneFolder(-1,$GLOBALS["path"]);

//todo Dollarzeichen im Namen zB /media/raid1tb/musik/1_a_Unsortiert/TEST/

function doOneFolder($parentid,$p) {
    echo "Do Folder: ".$p."\n";

    $foldernum = insertFolderInDb($parentid,$p);
    
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        insertFileInDb($foldernum,$mp3);
    }
    
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder($foldernum,$dir);
    }
}

//returns folderid
function insertFolderInDb($parentid,$folderpath) {
    $pic = null;
    if(file_exists($folderpath."/cover.jpg")) {
        $pic = file_get_contents($folderpath."/cover.jpg");
    }
    
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO folders (parentid,foldername,picture) VALUES(:pid, :fname, :pic)");
    $foldername = basename($folderpath);
    if(!$stmt->execute(array(':pid' => intval($parentid), ':fname' => $foldername, ':pic' => $pic))) {
        die("insertFolderInDb. Error");
    }
    return intval($GLOBALS["db"]->lastInsertID());
}

//returns fileid
function insertFileInDb($foldernum,$p) {
    global $getID3;
    $size = filesize($p);
    $year =     shell_exec('mp3info -p "%y" "'.$p.'"');
    $length =   shell_exec('mp3info -p "%S" "'.$p.'"');
    $ThisFileInfo = $getID3->analyze($p);
    $artist =   isset($ThisFileInfo['tags']['id3v2']['artist'][0]) ? $ThisFileInfo['tags']['id3v2']['artist'][0] : "";
    $title =    isset($ThisFileInfo['tags']['id3v2']['title'][0] ) ? $ThisFileInfo['tags']['id3v2']['title'][0]  : "";
    $album =    isset($ThisFileInfo['tags']['id3v2']['album'][0] ) ? $ThisFileInfo['tags']['id3v2']['album'][0]  : "";
    if($year===null) $year="";
    if($length===null) $length=0;

    $stmt = $GLOBALS["db"]->prepare("INSERT INTO files (filename,folderid,artist,title,album,year,length,size) VALUES(:fname, :fid,:ar,:ti,:al,:ye,:le,:si)");
    $filename = basename($p);
    if(!$stmt->execute(array(
        ':fname' => $filename, 
        ':fid' => intval($foldernum),
        ':ar' => $artist,
        ':ti' => $title,
        ':al' => $album,
        ':ye' => $year,
        ':le' => $length,
        ':si' => intval($size)
        
    ))) {
        print_r($stmt->queryString."\n");
        print_r($stmt->errorInfo());
        die("insertFileInDb. Error");
    }
    return intval($GLOBALS["db"]->lastInsertID());
}

?>