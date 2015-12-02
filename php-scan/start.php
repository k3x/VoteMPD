<?php
require("../settings.php");
require_once('getid3/getid3.php');
$getID3 = new getID3;
doRoot($GLOBALS["path"]);



function printProgress() {
    if(time()>=$GLOBALS["next10sec"]) {
        $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"];
        $diff = $GLOBALS["files"] - $GLOBALS["lastfiles"];
        $GLOBALS["filespersec"] = $diff/$GLOBALS["timestep"];
        $GLOBALS["lastfiles"] = $GLOBALS["files"];
    }
    if($GLOBALS["files"]%$GLOBALS["outputeveryxfiles"]!=0) return;
    
    echo "[";
    $num = $GLOBALS["ProgressBarLength"]*$GLOBALS["files"]/$GLOBALS["totalfiles"];
    for($i=0;$i<$GLOBALS["ProgressBarLength"];$i++) {
        if($i<=intval($num)) echo "#";
        else echo ".";
    }
    echo "]     ";
    
    echo    $GLOBALS["files"]."/".$GLOBALS["totalfiles"]." ".
            number_format(100*$GLOBALS["files"]/$GLOBALS["totalfiles"],2)."% ";
            
    if($GLOBALS["filespersec"]!=0) {
        echo    $GLOBALS["filespersec"]."fps ".
                number_format(((($GLOBALS["totalfiles"]-$GLOBALS["files"])/$GLOBALS["filespersec"])/60),2)."min remaining\n";
    } else {
        echo "\n";
    }
}

function doRoot($p) {
    $GLOBALS["files"]=0;
    $GLOBALS["totalfiles"]=0;
    $GLOBALS["filespersec"]=0;
    $GLOBALS["next10sec"]=0;
    $GLOBALS["lastfiles"]=0;
    $GLOBALS["ProgressBarLength"] = 40;
    $GLOBALS["timestep"] = 2;
    $GLOBALS["outputeveryxfiles"] = 10;
    $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"]; //TODO change back to 10
    
    $GLOBALS["Ttags"]=0;
    $GLOBALS["Tsize"]=0;
    $GLOBALS["Tlength"]=0;
    $GLOBALS["Tdb"]=0;
    $GLOBALS["Tpro"]=0;
    
    
    echo "Do Root: ".$p."\n";
    
    $GLOBALS["totalfiles"]=intval(shell_exec('find "'.$p.'" -iname "*.mp3" | wc -l'));
    
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        insertFileInDb(-1,$mp3);
    }
    
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder(-1,$dir);
    }
    
    echo "\nFinished!\n";
    echo "TAGS: ".$GLOBALS["Ttags"]."s\n";
    echo "DABA: ".$GLOBALS["Tdb"]."s\n";
    echo "PROG: ".$GLOBALS["Tpro"]."s\n";
    echo "SIZE: ".$GLOBALS["Tsize"]."s\n";
}


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
    } else if(file_exists($folderpath."/AlbumArtSmall.jpg")) {
        $pic = file_get_contents($folderpath."/AlbumArtSmall.jpg");
    } else if(file_exists($folderpath."/Folder.jpg")) {
        $pic = file_get_contents($folderpath."/Folder.jpg");
    }
    
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO folders (parentid,foldername,picture) VALUES(:pid, :fname, :pic)");
    $foldername = basename($folderpath);
    if(!$stmt->execute(array(':pid' => intval($parentid), ':fname' => $foldername, ':pic' => $pic))) {
        die("insertFolderInDb: Error");
    }
    return intval($GLOBALS["db"]->lastInsertID());
}

function myStrEscape($str) {
    $str = str_replace('\\','\\\\',$str);
    $str = str_replace("$","\\$",$str);
    $str = str_replace('"','\\"',$str);
    return $str;
}

//returns fileid
function insertFileInDb($foldernum,$p) {
    global $getID3;
    
    $Tstart = microtime(true);
    $size = filesize($p);
    $GLOBALS["Tsize"]+=(microtime(true)-$Tstart);
    
    $Tstart = microtime(true);
    $ThisFileInfo = $getID3->analyze($p);
    $year =     isset($ThisFileInfo['tags']['id3v2']['year'][0]) ? $ThisFileInfo['tags']['id3v2']['year'][0] : "";
    $artist =   isset($ThisFileInfo['tags']['id3v2']['artist'][0]) ? $ThisFileInfo['tags']['id3v2']['artist'][0] : "";
    $title =    isset($ThisFileInfo['tags']['id3v2']['title'][0] ) ? $ThisFileInfo['tags']['id3v2']['title'][0]  : "";
    $album =    isset($ThisFileInfo['tags']['id3v2']['album'][0] ) ? $ThisFileInfo['tags']['id3v2']['album'][0]  : "";
    $length =   isset($ThisFileInfo['playtime_seconds']) ? $ThisFileInfo['playtime_seconds'] : 0;
    if($year===null) $year="";
    $GLOBALS["Ttags"]+=(microtime(true)-$Tstart);
    
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
    
    $Tstart = microtime(true);
    printProgress();
    $GLOBALS["Tpro"]+=(microtime(true)-$Tstart);
    
    return intval($GLOBALS["db"]->lastInsertID());
}

?>