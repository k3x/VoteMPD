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

function daemonCallInit() {
    $mpd = new MPD();
    $mpd->cmd("stop");
    $mpd->cmd("clear");
    addOneFileToMpdQueue(true);
}

function addOneFileToMpdQueue($first=false) {
    $mpd = new MPD();
    $hn = getNextsongInHighscore();
    
    if($hn!==null) { //todo get song from static playlist
        $path = getFilepathForFileid($hn->id);
        $mpd->cmd('add "'.$path.'"');
        $state = getMpdValue("status","state");
        if($state != "play") {
            $mpd->cmd("play");
        }
        if($first) {
            $timeToAction = intval($hn->length)-4;
        } else {
            $timeTotal = getMpdValue("currentsong","Time");
            $timeCurrent = getMpdCurrentTime();
            $timeToAction = intval($hn->length)+(intval($timeTotal)-intval($timeCurrent))-4;
        }
        Tasker::add($timeToAction,'addOneFileToMpdQueue',array());
        
        $stmt = $GLOBALS["db"]->prepare("UPDATE votes set played=1 WHERE fileid=:fileid");
        if(!$stmt->execute(array(":fileid" => $hn->id))) {
            //todo logerror
            echo "error";
        }
    }
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

function getMpdCurrentTime() {
    $time = false;
    $time = getMpdValue("status","time");
    if($time!==false) $time = explode(":",getMpdValue("status","time"))[0];
    return $time;
}

function getMpdCurrentSong() {
    $path = getMpdValue("currentsong","file");
    if($path===false) $fileinfos = false;
    else $fileinfos = getFileinfosforfilepath($path);
    $state = getMpdValue("status","state");
    return array("state"=>$state,"time"=>getMpdCurrentTime(),"fileinfos"=>$fileinfos);
}

//todo ohne "musik/" (in php-scan)
function getFileinfosforfilepath($path) {
    $folders = explode("/",dirname("musik/".$path));
    $curDir = -1;
    foreach($folders as $f) {
        $stmt = $GLOBALS["db"]->prepare("SELECT id,picture FROM folders WHERE parentid=:p AND foldername=:f");
        if($stmt->execute(array(":p" => $curDir,":f" => $f))) {
            $row = $stmt->fetchObject();
            $curDir=$row->id;
        } else doError("getFileinfosforfilepath db query failed");
    }
    
    $pic=false;
    if($curDir!=-1 && isset($row->picture)) {
        $pic = true;
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE folderid=:folderid AND filename=:filename");
    if($stmt->execute(array(":folderid" => $curDir,":filename" => basename("musik/".$path)))) {
        $row = $stmt->fetchObject();
        $row->picture = $pic;
        return $row;
    } else doError("getFileinfosforfilepath db query failed2");
    return false;
}

function doVote($ip,$id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT votes.date,files.* FROM votes INNER JOIN files on files.id=votes.fileid WHERE votes.ip=:ip AND votes.played=0 AND votes.fileid=:fileid");
    $tmp = array();
    $exists = false;
    if($stmt->execute(array(":ip" => $ip,":fileid" => $id))) {
        if ($row = $stmt->fetchObject()) {
            $exists = true;
        }
    } else doError("Getmyvotes db query failed");
    
    if($exists) {
        return false;
    } else {
        $stmt = $GLOBALS["db"]->prepare("INSERT INTO votes (fileid,ip,date) VALUES (:fid,:ip,NOW())");
        return ($stmt->execute(array(":fid" => $id,":ip"=>$ip)));
    }
}

//todo order +by oldest vote ^
function doShowhighscore() {
    $stmt = $GLOBALS["db"]->prepare("SELECT files.*,COUNT(*) as anzahl FROM votes INNER JOIN files on files.id=votes.fileid WHERE votes.played=0 GROUP BY votes.fileid ORDER BY anzahl DESC, votes.fileid");
    $tmp = array();
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } else doError("Highscore db query failed");
}



function getNextsongInHighscore() {
    $tmp = doShowhighscore();
    if($tmp===false || $tmp===null || count($tmp)==0) return null;
    return $tmp[0];
}

function doSearch($keyword) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE filename LIKE :d OR artist LIKE :d OR title LIKE :d OR album LIKE :d");
    $tmp = array();
    if($stmt->execute(array(":d" => "%".$keyword."%"))) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        
        //todo implement boolean $tmp[$i]->alreadyVoted in mysql query
        for($i=0;$i<count($tmp);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $tmp[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $tmp[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $tmp[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $tmp[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $tmp[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $tmp[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
        return $tmp;
    } else doError("Search db query failed");
}

function doGetmyvotes() {
    $stmt = $GLOBALS["db"]->prepare("SELECT votes.date,files.* FROM votes INNER JOIN files on files.id=votes.fileid WHERE votes.ip=:ip AND votes.played=0 ORDER BY date DESC");
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