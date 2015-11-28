<?php

function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
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