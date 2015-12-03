<?php
require("includes/settings.php");
require("includes/functions.php");
require_once('libs/getid3/getid3.php');
$getID3 = new getID3;

//start script
doRoot($GLOBALS["path"]);

//Print current progress in console
function printProgress() {

    //calculate fps (files per second) every x seconds
    if(time()>=$GLOBALS["next10sec"]) {
        $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"];
        $diff = $GLOBALS["files"] - $GLOBALS["lastfiles"];
        $GLOBALS["filespersec"] = $diff/$GLOBALS["timestep"];
        $GLOBALS["lastfiles"] = $GLOBALS["files"];
    }
    
    //only continue this function every x files ($GLOBALS["outputeveryxfiles"])
    if($GLOBALS["files"]%$GLOBALS["outputeveryxfiles"]!=0) return;
    
    //echo ProgressBar
    echo "[";
    $num = $GLOBALS["ProgressBarLength"]*$GLOBALS["files"]/$GLOBALS["totalfiles"];
    for($i=0;$i<$GLOBALS["ProgressBarLength"];$i++) {
        if($i<=intval($num)) echo "#";
        else echo ".";
    }
    echo "]     ";
    
    //echo currentFiles/totalFiles and percent
    echo    $GLOBALS["files"]."/".$GLOBALS["totalfiles"]." ".
            number_format(100*$GLOBALS["files"]/$GLOBALS["totalfiles"],2)."% ";
            
    //echo files per second
    if($GLOBALS["filespersec"]!=0) {
        echo    $GLOBALS["filespersec"]."fps ".
                number_format(((($GLOBALS["totalfiles"]-$GLOBALS["files"])/$GLOBALS["filespersec"])/60),2)."min remaining";
    }
    echo "\n";

}

//first called function
function doRoot($p) {
    $GLOBALS["files"]=0;
    $GLOBALS["totalfiles"]=0;
    $GLOBALS["filespersec"]=0;
    $GLOBALS["next10sec"]=0;
    $GLOBALS["lastfiles"]=0;
    $GLOBALS["ProgressBarLength"] = 40; //length of ProgressBar
    $GLOBALS["timestep"] = 5; //re-calculate files per second every x seconds
    $GLOBALS["outputeveryxfiles"] = 10; //print status every x files
    $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"];
    
    //calculate time for specific tasks
    $GLOBALS["Ttags"]=0; //get Tags
    $GLOBALS["Tsize"]=0; //get size
    $GLOBALS["Tdb"]=0; //insert into database
    $GLOBALS["Tpro"]=0; //calculate and print progress
    
    
    echo "Do Root: ".$p."\n";
    
    //calculate total filecount
    $GLOBALS["totalfiles"]=intval(shell_exec('find "'.$p.'" -iname "*.mp3" | wc -l'));
    
    //do mp3s in root
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        //insert mp3 in database
        insertFileInDb(-1,$mp3);
    }
    
    //do folders in root
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder(-1,$dir);
    }
    
    //print timers
    echo "\nFinished!\n";
    echo "TAGS: ".$GLOBALS["Ttags"]."s\n";
    echo "DABA: ".$GLOBALS["Tdb"]."s\n";
    echo "PROG: ".$GLOBALS["Tpro"]."s\n";
    echo "SIZE: ".$GLOBALS["Tsize"]."s\n";
}

//proceed one folder
function doOneFolder($parentid,$p) {
    echo "Do Folder: ".$p."\n";

    //insert folder in db
    $foldernum = insertFolderInDb($parentid,$p);
    
    //do mp3s
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        //insert mp3 in database
        insertFileInDb($foldernum,$mp3);
    }
    
    //continue with sub-folders
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder($foldernum,$dir);
    }
}

//enters folder in database
//returns folderid
function insertFolderInDb($parentid,$folderpath) {

    //get picture
    $pic = null;
    if(file_exists($folderpath."/cover.jpg")) {
        $pic = file_get_contents($folderpath."/cover.jpg");
    } else if(file_exists($folderpath."/AlbumArtSmall.jpg")) {
        $pic = file_get_contents($folderpath."/AlbumArtSmall.jpg");
    } else if(file_exists($folderpath."/Folder.jpg")) {
        $pic = file_get_contents($folderpath."/Folder.jpg");
    }
    
    //insert in database
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO folders (parentid,foldername,picture) VALUES(:pid, :fname, :pic)");
    $foldername = basename($folderpath);
    if(!$stmt->execute(array(':pid' => intval($parentid), ':fname' => $foldername, ':pic' => $pic))) {
        die("insertFolderInDb: Error");
    }
    
    //return id
    return intval($GLOBALS["db"]->lastInsertID());
}

//enters file in database
//returns fileid
function insertFileInDb($foldernum,$p) {
    global $getID3;
    
    //get size
    $Tstart = microtime(true);
    $size = filesize($p);
    $GLOBALS["Tsize"]+=(microtime(true)-$Tstart);
    
    //get tags&length
    $Tstart = microtime(true);
    $ThisFileInfo = $getID3->analyze($p);
    $year =     isset($ThisFileInfo['tags']['id3v2']['year'][0]) ? $ThisFileInfo['tags']['id3v2']['year'][0] : "";
    $artist =   isset($ThisFileInfo['tags']['id3v2']['artist'][0]) ? $ThisFileInfo['tags']['id3v2']['artist'][0] : "";
    $title =    isset($ThisFileInfo['tags']['id3v2']['title'][0] ) ? $ThisFileInfo['tags']['id3v2']['title'][0]  : "";
    $album =    isset($ThisFileInfo['tags']['id3v2']['album'][0] ) ? $ThisFileInfo['tags']['id3v2']['album'][0]  : "";
    $length =   isset($ThisFileInfo['playtime_seconds']) ? $ThisFileInfo['playtime_seconds'] : 0;
    if($year===null) $year="";
    $GLOBALS["Ttags"]+=(microtime(true)-$Tstart);
    
    //insert into database
    $Tstart = microtime(true);
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
        die("insertFileInDb: Error");
    }
    $GLOBALS["Tdb"]+=(microtime(true)-$Tstart);
    
    $GLOBALS["files"]++;
    
    //print Progress
    $Tstart = microtime(true);
    printProgress();
    $GLOBALS["Tpro"]+=(microtime(true)-$Tstart);
    
    //return id
    return intval($GLOBALS["db"]->lastInsertID());
}

?>