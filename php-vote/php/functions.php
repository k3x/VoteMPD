<?php

require("mpd.php");

function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
}

//todo with current elapsedtime. in js show time%
function getMpdCurrentSong() {
    $mpd = new MPD();
    $r = $mpd->cmd("currentsong");
    if(false===strpos($r,"file: ")) {
        return null;
    } else {
        $tmp = explode("\n",$r);
        $path = null;
        foreach($tmp as $t) {
            $tmpar = explode(": ",$t);
            if(count($tmpar)===2 && $tmpar[0]=="file") {
                $path = $tmpar[1];
            }
        }
        if($path === null) return null;
        return getFileinfosforfilepath($path);
    }
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

//todo
function getFilepathForFileid($id) {
    return "";
}

//todo only if not already voted
function doVote($ip,$id) {
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO votes (fileid,ip,date) VALUES (:fid,:ip,NOW())");
    return ($stmt->execute(array(":fid" => $id,":ip"=>$ip)));
}

//todo nur zählen, wenn vote nicht vor einem Eintrag in playlog ist.
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

function getNextsong() {
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