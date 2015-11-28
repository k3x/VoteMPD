<?php

function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
}

//todo nur zählen, wenn vote nicht vor einem Eintrag in playlog ist.
//todo mit fileinfos und Foldername + Folderpic + Pfad
function doShowhighscore() {
    $stmt = $GLOBALS["db"]->prepare("SELECT fileid,COUNT(*) as anzahl FROM votes GROUP BY fileid");
    $tmp = array();
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } doError("Highscore db query failed");
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