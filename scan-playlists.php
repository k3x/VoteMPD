<?php

require("includes/settings.php");
require("includes/functions.php");
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

?>