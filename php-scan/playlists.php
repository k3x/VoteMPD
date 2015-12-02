<?php


require("../settings.php");
doRoot($GLOBALS["pathplaylists"]);



function doRoot($p) {
    $sPath = $p.'/*.m3u';
    foreach (glob($sPath) AS $m3u)
    {
        doPlaylist($m3u);
    }
}


function doPlaylist($p) {
    
    $content = file_get_contents($p);
    $filename = basename($p);
    $filename = basename($p, '.m3u');
    echo "Do Playlist: ".$filename."\n";
    
    $array = explode("\x0d\x0a",$content);
    foreach($array as $a) {
        if(trim($a)=="") continue;
        if(file_exists($GLOBALS["path"]."/".$a)) {
            
            $fileinfos = getFileinfosforfilepath($a);
            if($fileinfos===false) {
                $path = $a;
                $id = null;
            } else {
                $path=null;
                $id = $fileinfos->id;
            }
        } else {
            $id=null;
            $path = $a;
        }
        
        $stmt = $GLOBALS["db"]->prepare("INSERT INTO playlistitems (playlistname,fileid,filepath) VALUES(:pname, :fid, :fpath)");
        if(!$stmt->execute(array(':pname' => $filename, ':fid' => $id, ':fpath' => $path))) {
            die("insertPlaylistInDb: Error");
        }
    }
}


function getFileinfosforfilepath($path) {
    $folders = explode("/",dirname($path));
    $curDir = -1;
    foreach($folders as $f) {
        $stmt = $GLOBALS["db"]->prepare("SELECT id FROM folders WHERE parentid=:p AND foldername=:f");
        if($stmt->execute(array(":p" => $curDir,":f" => $f))) {
            if($row = $stmt->fetchObject()) {
                $curDir=$row->id;
            } else return false;
        } else return false;
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE folderid=:folderid AND filename=:filename");
    if($stmt->execute(array(":folderid" => $curDir,":filename" => basename($path)))) {
        $row = $stmt->fetchObject();
        return $row;
    } else return false;
    return false;
}


?>