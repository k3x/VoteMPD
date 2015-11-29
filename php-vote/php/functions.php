<?php

require("mpd.php");
require("../../settings.php");

function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
}

//todo push based implementation possible?
//todo on state!=play: play first highscore item, if empty from defined playlist
//todo on no next song in playlist: add first highscore item(that is not currently playing && not next song in playlist), if empty from defined playlist
function daemonCall() {
    $state = getMpdValue("status","state");
    $file = getMpdValue("currentsong","file");
    getNextsongInHighscore();
}

function getFilepathForFileid($id) {
    $file = getFile($id);
    $currentfolder = $file -> folderid;
    $folders = array();
    
    while($currentfolder!=-1) {
        $folder = getFolder($currentfolder);
        $currentfolder = $folder -> parentid;
        if($currentfolder!=-1) $folders[] = $folder -> foldername;
    }
    $path = "";
    if(count($folders)>0) {
        $folders = array_reverse($folders);
        $path = implode("/",$folders)."/";
    }
    return $path.$file->filename;
}

function getFolder($id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM folders WHERE id=:id");
    if($stmt->execute(array(":id" => $id))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFolder db query failed");
}

function getFile($id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE id=:id");
    if($stmt->execute(array(":id" => $id))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFile db query failed");
}

//calls function and returns first item value or null
function getMpdValue($function,$item) {
    $mpd = new MPD();
    $r = $mpd->cmd($function);
    if(false===strpos($r,$item.": ")) {
        return false;
    } else {
        $tmp = explode("\n",$r);
        $path = false;
        foreach($tmp as $t) {
            $tmpar = explode(": ",$t);
            if(count($tmpar)===2 && $tmpar[0]==$item) {
                return $tmpar[1];
            }
        }
        return false;
    }
}

//todo
function getMpdNextSong() {

}

//todo with current elapsedtime. in js show time%
//todo with state
function getMpdCurrentSong() {
    $path = getMpdValue("currentsong","file");
    if($path===false) $fileinfos = false;
    else $fileinfos = getFileinfosforfilepath($path);
    $state = getMpdValue("status","state");
    $time = getMpdValue("status","time");
    if($time!==false) $time = explode(":",getMpdValue("status","time"))[0];
    
    return array("state"=>$state,"time"=>$time,"fileinfos"=>$fileinfos);
}

//todo with picture; ohne "musik/" (in php-scan)
function getFileinfosforfilepath($path) {
    $folders = explode("/",dirname("musik/".$path));
    $curDir = -1;
    foreach($folders as $f) {
        $stmt = $GLOBALS["db"]->prepare("SELECT id FROM folders WHERE parentid=:p AND foldername=:f");
        if($stmt->execute(array(":p" => $curDir,":f" => $f))) {
            $row = $stmt->fetchObject();
            $curDir=$row->id;
        } else doError("getFileinfosforfilepath db query failed");
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE folderid=:folderid AND filename=:filename");
    if($stmt->execute(array(":folderid" => $curDir,":filename" => basename("musik/".$path)))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFileinfosforfilepath db query failed2");
    return false;
}

//todo only if not already voted
function doVote($ip,$id) {
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO votes (fileid,ip,date) VALUES (:fid,:ip,NOW())");
    return ($stmt->execute(array(":fid" => $id,":ip"=>$ip)));
}

//todo nur zählen, wenn vote nicht vor einem Eintrag in playlog ist.
//order +by oldest vote ^
function doShowhighscore() {
    $stmt = $GLOBALS["db"]->prepare("SELECT files.*,COUNT(*) as anzahl FROM votes INNER JOIN files on files.id=votes.fileid GROUP BY votes.fileid ORDER BY anzahl DESC");
    $tmp = array();
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } else doError("Highscore db query failed");
}

//todo nur zählen, wenn vote nicht vor einem Eintrag in playlog ist.
function getNextsongInHighscore() {
    $stmt = $GLOBALS["db"]->prepare("SELECT files.*,COUNT(*) as anzahl FROM votes INNER JOIN files on files.id=votes.fileid GROUP BY votes.fileid ORDER BY anzahl DESC LIMIT 1");
    if($stmt->execute()) {
        if ($row = $stmt->fetchObject()) {
            return $row;
        }
    } else return null;
}

//add boolean if user voted for song already (after last entry in playlog)
function doSearch($keyword) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE filename LIKE :d OR artist LIKE :d OR title LIKE :d OR album LIKE :d");
    $tmp = array();
    if($stmt->execute(array(":d" => "%".$keyword."%"))) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } else doError("Search db query failed");
}

//only after last playlog
function doGetmyvotes() {
    $stmt = $GLOBALS["db"]->prepare("SELECT votes.date,files.* FROM votes INNER JOIN files on files.id=votes.fileid WHERE votes.ip=:ip ORDER BY date DESC");
    $tmp = array();
    if($stmt->execute(array(":ip" => $_SERVER['REMOTE_ADDR']))) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } else doError("Getmyvotes db query failed");
}

function doOutput($content,$action) {
    $a = array();
    $a["status"] = "success";
    $a["action"] = $action;
    $a["content"] = $content;
    echo json_encode($a);
    die();
}

?>