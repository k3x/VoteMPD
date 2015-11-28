<?php

require("mpd.php");

function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
}



//todo
function getMpdCurrentSong() {
    $mpd = new MPD();
    //telnet localhost 6600
    //http://www.musicpd.org/doc/protocol/command_reference.html
    
    echo $mpd->cmd("status"); 
    //song -> playlistinfo Pos
    //songid -> playlistinfo Id
    //elapsed is time played
    //volume: -1 repeat: 0 random: 0 single: 0 consume: 0 playlist: 4 playlistlength: 2 mixrampdb: 0.000000 state: play song: 0 songid: 1 time: 71:240 elapsed: 71.181 bitrate: 128 audio: 44100:24:2 nextsong: 1 nextsongid: 2 
    //volume: -1 repeat: 0 random: 0 single: 0 consume: 0 playlist: 4 playlistlength: 2 mixrampdb: 0.000000 state: stop song: 0 songid: 1 nextsong: 1 nextsongid: 2 
    
    //echo $mpd->cmd("play");
    
    //echo $mpd->cmd("add \"B/Böhse Onkelz/Mexico.mp3\""); //fügt song in playlist ein
    
    
    //echo $mpd->cmd("playlistinfo");
    /*
file: A/ACDC/High Voltage.mp3
Last-Modified: 2015-04-27T17:10:25Z
Time: 260
Artist: ACDC
AlbumArtist: AC/DC
Title: High Voltage
Album: The Very Best of AC/DC
Track: ACDC
Genre: Hard Rock
Pos: 0
Id: 89
file: B/BÃ¶hse Onkelz/Mexico.mp3
Last-Modified: 2015-04-27T17:10:29Z
Time: 169
Artist: BÃ¶hse Onkelz
Title: Mexico
Pos: 1
Id: 90
file: B/BÃ¶hse Onkelz/Mexico.mp3
Last-Modified: 2015-04-27T17:10:29Z
Time: 169
Artist: BÃ¶hse Onkelz
Title: Mexico
Pos: 2
Id: 91
file: B/BÃ¶hse Onkelz/Mexico.mp3
Last-Modified: 2015-04-27T17:10:29Z
Time: 169
Artist: BÃ¶hse Onkelz
Title: Mexico
Pos: 3
Id: 92

*/
    
    //currentsong
    //stats
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
    } doError("Highscore db query failed");
}

function getNextsong() {
    $stmt = $GLOBALS["db"]->prepare("SELECT files.*,COUNT(*) as anzahl FROM votes INNER JOIN files on files.id=votes.fileid GROUP BY votes.fileid ORDER BY anzahl DESC LIMIT 1");
    if($stmt->execute()) {
        if ($row = $stmt->fetchObject()) {
            return $row;
        }
    } 
    return null;
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
    } doError("Search db query failed");
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
    } doError("Getmyvotes db query failed");
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