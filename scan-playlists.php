<?php

require("includes/settings.php");
require("includes/functions.php");

//start script
doRoot($GLOBALS["pathplaylists"]);

//first called function
function doRoot($p) {
    //scan for m3us
    $sPath = $p.'/*.m3u';
    foreach (glob($sPath) AS $m3u)
    {
        //proceed with one playlist
        doPlaylist($m3u);
    }
}

//one playlist
function doPlaylist($p) {
    //read file content
    $content = file_get_contents($p);
    
    //get filename
    $filename = basename($p);
    $filename = basename($p, '.m3u');
    echo "Do Playlist: ".$filename."\n";
    
    //get lines
    $array = explode("\x0d\x0a",$content);
    
    //foreach line (song)
    foreach($array as $a) {
        if(trim($a)=="") continue;
        if(file_exists($GLOBALS["path"]."/".$a)) {
            //if file exists enter fileid in database
            $fileinfos = getFileinfosforfilepath($a);
            if($fileinfos===false) {
                $path = $a;
                $id = null;
            } else {
                $path=null;
                $id = $fileinfos->id;
            }
        } else {
            //if file does not exists enter path in database
            $id=null;
            $path = $a;
        }
        
        //enter into database
        $stmt = $GLOBALS["db"]->prepare("INSERT INTO playlistitems (playlistname,fileid,filepath) VALUES(:pname, :fid, :fpath)");
        if(!$stmt->execute(array(':pname' => $filename, ':fid' => $id, ':fpath' => $path))) {
            die("insertPlaylistInDb: Error");
        }
    }
}

?>