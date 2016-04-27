<?php

require("mpd.php");
require("settings.php");
require_once('libs/getid3/getid3.php');

/*
---------------------------------------------------------------------------
------------------------------AJAX STUFF-----------------------------------
---------------------------------------------------------------------------
*/

//output an ajax error
function doError($e) {
    $a = array();
    $a["status"] = "error";
    $a["reason"] = $e;
    echo json_encode($a);
    die();
}

//output ajax
function doOutput($content,$action) {
    $a = array();
    $a["status"] = "success";
    $a["action"] = $action;
    $a["content"] = $content;
    echo json_encode($a);
    die();
}

/*
---------------------------------------------------------------------------
------------------------------DAEMON STUFF---------------------------------
---------------------------------------------------------------------------
*/

//first daemon call
function daemonCallInit() {
    Tasker::removeAll();
    $mpd = new MPD();
    $mpd->cmd("stop");
    $mpd->cmd("clear");
    addOneFileToMpdQueue(true);
}

//called periodically. Checks for enough votes in table "voteforskip"
function checkForSkipSong() {
    Tasker::add(5,'checkForSkipSong');
    
    $timeTotal = intval(getMpdValue("currentsong","Time"));
    $timeCurrent = intval(getMpdCurrentTime());
    if(($timeTotal-$timeCurrent)<10) return;

    $c = getMpdCurrentSong();
    if($c===null || $c===false) return;
    if(!isset($c["fileinfos"])) return;
    if(!isset($c["fileinfos"]->id)) return;

    $fileid = $c["fileinfos"]->id;
    $skip = false;
    $stmt = $GLOBALS["db"]->prepare("SELECT COUNT(*) as count FROM voteforskip WHERE fileid=:fileid AND DATE>DATE_SUB(NOW(),INTERVAL :seconds SECOND)");
    if($stmt->execute(array(":fileid" => $fileid,":seconds" => $timeCurrent))) {
        $row = $stmt->fetchObject();
        if($row->count >= $GLOBALS["voteskipcount"]) $skip = true;
    }
    
    if($skip) {
        $stmt = $GLOBALS["db"]->prepare("DELETE FROM voteforskip");
        $stmt->execute();
        daemonCallInit();
    }
}

//take first file from highscore and add it to mpd Queue
function addOneFileToMpdQueue($first=false) {
    checkForSavePlaylog();
    $mpd = new MPD();

    $voted = false;
    $tmp = doShowhighscore(true);
    if($tmp!==false && $tmp!==null && count($tmp)>=1) {
        $voted = true;
    }
    
    $hn = getNextsongInHighscore(true);
    if($hn!==null) {
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
        Tasker::add($timeToAction,'addOneFileToMpdQueue');
        
        if($voted) {
            $stmt = $GLOBALS["db"]->prepare("UPDATE votes set played=1 WHERE fileid=:fileid");
            if(!$stmt->execute(array(":fileid" => $hn->id))) {
                echo "error";
            }
            
            
            $stmt = $GLOBALS["db"]->prepare("INSERT INTO playlog (fileid,date) VALUES (:fileid,NOW())");
            if(!$stmt->execute(array(":fileid" => $hn->id))) {
                echo "error";
            }
        }
        
        Tasker::add(5,'checkForSkipSong');
    } else {
        Tasker::add(5,'daemonCallInit',array());
    }
}

//maybe saves playlog to playlist
function checkForSavePlaylog() {
    $lastDate = getLastPlaylogSaveDate();
    
    if($lastDate===null) { //do initial set lastplaylogsave
        $stmt = $GLOBALS["db"]->prepare("INSERT INTO options_date (id,value) VALUES (\"lastplaylogsave\",NOW())");
        $stmt->execute();
        return;
    }
    
    $hours = getHoursSinceLastPlaylogSaveDate();    
    $songs = getSongsSinceLastPlaylogSaveDate();  //array of strings (fileid)
    if($hours>10 && count($songs)>0) {
        $stmt = $GLOBALS["db"]->prepare("UPDATE options_date set value=NOW() WHERE id=\"lastplaylogsave\"");
        $stmt->execute();
        $name = gmDate("Y_m_d\-H.i")."_autosave";
        foreach($songs as $s) {
            $s = intval($s);
            $stmt = $GLOBALS["db"]->prepare("INSERT INTO playlistitems (playlistname,fileid,filepath) VALUES (:n,:s,NULL)");
            $stmt->execute(array(":n"=>$name,":s"=>$s));
        }
    }
}

function scanP() {
    doRootPlaylists($GLOBALS["path"]."/".$GLOBALS["pathplaylists"]);
}

function scanF() {
    $GLOBALS["getid3"] = new getID3;
    doRootFiles($GLOBALS["path"]);
}

/*
---------------------------------------------------------------------------
------------------------------MPD STUFF------------------------------------
---------------------------------------------------------------------------
*/

//get one value from mpd server
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

//get current time position in song from mpd server
function getMpdCurrentTime() {
    $time = false;
    $time = getMpdValue("status","time");
    if($time!==false) $time = explode(":",getMpdValue("status","time"))[0];
    return $time;
}

//get current song from mpd server
function getMpdCurrentSong() {
    $path = getMpdValue("currentsong","file");
    if($path===false) $fileinfos = false;
    else $fileinfos = getFileinfosforfilepath($path);
    $state = getMpdValue("status","state");
    return array("state"=>$state,"time"=>getMpdCurrentTime(),"fileinfos"=>$fileinfos);
}

//adds a mp3 to mpd library
function mpdScan($filepath) {
    $mpd = new MPD();
    $r=$mpd->cmd('update "'.$filepath.'"');
}

/*
---------------------------------------------------------------------------
------------------------------HELPER FUNCTIONS-----------------------------
---------------------------------------------------------------------------
*/

//returns date of last saved item in playlog, or null
function getLastPlaylogSaveDate() {
    $stmt = $GLOBALS["db"]->prepare("SELECT value FROM options_date WHERE id=\"lastplaylogsave\"");
    if($stmt->execute()) {
        if($row = $stmt->fetchColumn()) return $row;
    }
    return null;
}

//returns hours passed since date of last saved item in playlog, or null
function getHoursSinceLastPlaylogSaveDate() {
    $d = getLastPlaylogSaveDate();
    if($d===null) return null;

    $stmt = $GLOBALS["db"]->prepare("SELECT HOUR(TIMEDIFF (NOW(),:d))");
    if($stmt->execute(array("d"=>$d))) {
        if($row = $stmt->fetchColumn()) return intval($row);
    }
    return null;
}

//returns songs played since date of last saved item in playlog (or empty array)
function getSongsSinceLastPlaylogSaveDate() {
    $d = getLastPlaylogSaveDate();
    if($d===null) return array();

    $stmt = $GLOBALS["db"]->prepare("SELECT fileid from playlog WHERE date>:d");
    if($stmt->execute(array("d"=>$d))) {
        if($rows = $stmt->fetchAll(PDO::FETCH_COLUMN)) return $rows;
    }
    return array();
}

// folderpath => folderid
function getFolderidforFolderpath($path) {
    $folders = explode("/",$path);
    $curDir = -1;
    foreach($folders as $f) {
        $stmt = $GLOBALS["db"]->prepare("SELECT id,picture FROM folders WHERE parentid=:p AND foldername=:f");
        if($stmt->execute(array(":p" => $curDir,":f" => $f))) {
            if($row = $stmt->fetchObject()) $curDir=$row->id;
            else doError("getFolderidforFolderpath db query failed (1) ".print_r(array(":p" => $curDir,":f" => $f),true));
        } else doError("getFolderidforFolderpath db query failed (2) ".print_r(array(":p" => $curDir,":f" => $f),true));
    }

    return $curDir;
}

// filepath => infos
function getFileinfosforfilepath($path) {
    $folders = explode("/",dirname($path));
    $curDir = -1;
    foreach($folders as $f) {
        $stmt = $GLOBALS["db"]->prepare("SELECT id,picture FROM folders WHERE parentid=:p AND foldername=:f");
        if($stmt->execute(array(":p" => $curDir,":f" => $f))) {
            if($row = $stmt->fetchObject()) $curDir=$row->id;
            else doError("getFileinfosforfilepath db query failed (1) ".print_r(array(":p" => $curDir,":f" => $f),true));
        } else doError("getFileinfosforfilepath db query failed (2) ".print_r(array(":p" => $curDir,":f" => $f),true));
    }
    
    $pic=false;
    if($curDir!=-1 && isset($row->picture)) {
        $pic = true;
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE folderid=:folderid AND filename=:filename");
    if($stmt->execute(array(":folderid" => $curDir,":filename" => basename($path)))) {
        if($row = $stmt->fetchObject()) {
            $row->picture = $pic;
            return $row;
        } else return false;
    } else doError("getFileinfosforfilepath db query failed (3) ".print_r(array(":folderid" => $curDir,":filename" => basename($path)),true));
    return false;
}

// folderpath => infos
function getFolderpathForFolderid($id) {
    $currentfolder = $id;
    $folders = array();
    
    while($currentfolder!=-1) {
        $folder = getFolder($currentfolder);
        $currentfolder = $folder -> parentid;
        $folders[] = $folder -> foldername;
    }
    $path = "";
    if(count($folders)>0) {
        $folders = array_reverse($folders);
        $path = implode("/",$folders)."/";
    }
    return "/".$path;
}

// fileid => filepath
function getFilepathForFileid($id) {
    $file = getFile($id);
    $currentfolder = $file -> folderid;
    $folders = array();
    
    while($currentfolder!=-1) {
        $folder = getFolder($currentfolder);
        $currentfolder = $folder -> parentid;
        $folders[] = $folder -> foldername;
    }
    $path = "";
    if(count($folders)>0) {
        $folders = array_reverse($folders);
        $path = implode("/",$folders)."/";
    }
    return $path.$file->filename;
}

//get a folder
function getFolder($id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT id,parentid,foldername FROM folders WHERE id=:id");
    if($stmt->execute(array(":id" => $id))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFolder db query failed");
}

//get a folder picture
function getFolderPic($id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM folders WHERE id=:id");
    if($stmt->execute(array(":id" => $id))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFolder db query failed");
}

//get a file
function getFile($id) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE id=:id");
    if($stmt->execute(array(":id" => $id))) {
        $row = $stmt->fetchObject();
        return $row;
    } else doError("getFile db query failed");
}

/*
---------------------------------------------------------------------------
--------------------------AJAX CALLS NOT IN ACCORDION ---------------------
---------------------------------------------------------------------------
*/

//get the next song in highscore
function getNextsongInHighscore($daemoncall = false) {
    $tmp = doShowhighscore($daemoncall);
    if(!($tmp===false || $tmp===null || count($tmp)==0)) {
        //return first highscore item
        return $tmp[0];
    } else { //output file from default playlist
        //get current position in default playlist
        $pos = 0;
        $stmt = $GLOBALS["db"]->prepare("SELECT * FROM options_int WHERE id='defaultplaylistposition'");
        if($stmt->execute(array())) {
            if(false===($row = $stmt->fetchObject())) {
                $stmt2 = $GLOBALS["db"]->prepare("INSERT INTO options_int (id,value) VALUES ('defaultplaylistposition',0)");
                if(!$stmt2->execute(array())) {
                    doError("ERROR: defaultplaylistposition,0");
                }
                $pos = 0;
            } else {
                $pos = intval($row->value);
            }
        }
        
        $subFiles = array();
        $stmt = $GLOBALS["db"]->prepare("
            SELECT 
                id,
                filename,
                artist,
                title,
                length,
                size 
            FROM 
                playlistitems 
            INNER JOIN 
                files on(files.id=playlistitems.fileid) 
            WHERE 
                fileid IS NOT NULL AND 
                playlistname=:name");
        if($stmt->execute(array(":name" => $GLOBALS["defaultplaylist"]))) {
            while ($row = $stmt->fetchObject()) {
                $subFiles[] = $row;
            }
        } else doError("getNextsongInHighscore db query failed");
        if(count($subFiles)==0) return null;
        
        if($pos>=count($subFiles)) $pos = 0;
        
        if($daemoncall) {
            $newpos=$pos+1;
            if($newpos>=count($subFiles)) $newpos = 0;
            
            $stmt = $GLOBALS["db"]->prepare("UPDATE options_int SET value=:v WHERE id='defaultplaylistposition'");
            if(!$stmt->execute(array(":v"=>$newpos))) {
                doError("ERROR: save defaultplaylistposition");
            }
        }
    
    return $subFiles[$pos];
    }
}

//vote for a song
function doVote($ip,$id) {
    if($id=="" || !ctype_digit($id)) doError("doVote no number");
    $file = getFile($id);
    if($file===false) doError("doVote no valid number");

    $stmt = $GLOBALS["db"]->prepare("SELECT votes.date FROM votes WHERE votes.ip=:ip AND votes.played=0 AND votes.fileid=:fileid");
    $tmp = array();
    $exists = false;
    if($stmt->execute(array(":ip" => $ip,":fileid" => $id))) {
        if ($row = $stmt->fetchObject()) {
            $exists = true;
        }
    } else doError("Getmyvotes db query failed");
    
    if($exists) {
        doError("doVote already voted");
    } else {
        $stmt = $GLOBALS["db"]->prepare("INSERT INTO votes (fileid,ip,date) VALUES (:fid,:ip,NOW())");
        if(!($stmt->execute(array(":fid" => $id,":ip"=>$ip)))) return false;
        else return $id;
    }
}

//remove one of my votes
function doRemoveMyVote($ip,$id) {
    $stmt = $GLOBALS["db"]->prepare("DELETE FROM votes WHERE votes.ip=:ip AND votes.played=0 AND votes.fileid=:fileid");
    if($stmt->execute(array(":ip" => $ip,":fileid" => $id))) {
        $num = $stmt->rowCount();
        if($num===0) doError("doRemoveMyVote no row found");
        if($num===1) return $id;
        if($num>=2) doError("doRemoveMyVote more than one row found");
    } else doError("doRemoveMyVote db query failed");
}

//vote vor next song
function getVoteSkipAction() {
    $fileid = getMpdCurrentSong()["fileinfos"]->id;
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO voteforskip (fileid,ip,date) VALUES (:fileid,:ip,NOW())");
    if($stmt->execute(array(":fileid" => $fileid, "ip" => $_SERVER['REMOTE_ADDR']))) {
        return true;
    }
    return false;
}

//download a file
function doDownloadFileDo($id) {
    $path = $GLOBALS["path"]."/".getFilepathForFileid($id);    
	if (!file_exists($path)) die("Datei existiert nicht !");
	$filesize = filesize($path);
	$mimetype=mime_content_type($path);
	header('Content-Disposition: attachment; filename="'.basename($path).'"');
	header("Content-type: ".$mimetype);
	header("Content-Length: $filesize");
	readfile($path);
}

//download a playlist
function doDownloadPlaylistDo($name) {
    $subFiles = array();
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id from playlistitems INNER JOIN files on(files.id=playlistitems.fileid) WHERE fileid IS NOT NULL AND playlistname=:name");
    if($stmt->execute(array(":name" => $name))) {
        $r = "\xef\xbb\xbf";
        while ($row = $stmt->fetchObject()) {
            $r .= "../".getFilepathForFileid($row->id)."\r\n";
        }
        
        header('Content-Disposition: attachment; filename="'.$name.'.m3u"');
        header("Content-type: audio/mpegurl;");
        header("Content-Length: ".strlen($r)); //in bytes, not characters!
        echo $r;
        die();
        
    } else doError("doDownloadPlaylistDo DB error");   
}

/*
---------------------------------------------------------------------------
------------------------------ACCORDION STUFF------------------------------
---------------------------------------------------------------------------
*/

//return votes from this ip
function doGetmyvotes() {
    $stmt = $GLOBALS["db"]->prepare("SELECT votes.date,files.* FROM votes INNER JOIN files on files.id=votes.fileid WHERE votes.ip=:ip AND votes.played=0 ORDER BY date ASC");
    $tmp = array();
    if($stmt->execute(array(":ip" => $_SERVER['REMOTE_ADDR']))) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        return $tmp;
    } else doError("Getmyvotes db query failed");
}

//return highscore
function doShowhighscore($daemoncall = false) {
    $stmt = $GLOBALS["db"]->prepare("
        SELECT 
            files.*,votes.date,COUNT(*) as count 
        FROM 
            (SELECT * FROM votes ORDER BY date ASC) as votes
        INNER JOIN 
            files on files.id=votes.fileid 
        WHERE 
            votes.played=0
        GROUP BY
            votes.fileid
        ORDER BY
            count DESC,
			date ASC");
    $tmp = array();
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        
        if(!$daemoncall) {
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
        }
        
        return $tmp;
    } else doError("Highscore db query failed");
}

//search for keywords
function doSearch($keyword) {
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM files WHERE filename LIKE :d OR artist LIKE :d OR title LIKE :d OR album LIKE :d");
    $tmp = array();
    if($stmt->execute(array(":d" => "%".$keyword."%"))) {
        while ($row = $stmt->fetchObject()) {
            $tmp[] = $row;
        }
        
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

//browse folders
function getBrowseFolder($id) {
    if($id==-1) {
        $thisFolder = "ROOT";
    } else {
        $thisFolder = getFolder($id);
        if($thisFolder===false) doError("getBrowseFolder Folder not found");
    }

    $subFolders = array();
    $subFiles = array();

    $stmt = $GLOBALS["db"]->prepare("SELECT id,foldername FROM folders WHERE parentid=:id");
    if($stmt->execute(array(":id" => $id))) {
        while ($row = $stmt->fetchObject()) {
            $subFolders[] = $row;
        }
    } else doError("getBrowseFolder (getSubFolders) db query failed");    
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id,filename,artist,title,length,size FROM files WHERE folderid=:id");
    if($stmt->execute(array(":id" => $id))) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseFolder (getSubFiles) db query failed");
    return ["path"=>getFolderpathForFolderid($id),"this"=>$thisFolder,"folders"=>$subFolders,"files"=>$subFiles];
}

//browse artists
function getBrowseArtist($name) {
    $subFiles = array();
    
    if($name=="ROOT") {
        $artists = array();
        $stmt = $GLOBALS["db"]->prepare("SELECT artist FROM files WHERE artist!='' AND artist!=' ' GROUP BY artist");
        if($stmt->execute(array(":name" => $name))) {
            while ($row = $stmt->fetchObject()) {
                $subFiles[] = $row;
            }
    
        } else doError("getBrowseArtist (getArtists) db query failed");
        return ["name"=>$name,"artists"=>$subFiles];
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id,filename,artist,title,length,size FROM files WHERE artist=:name");
    if($stmt->execute(array(":name" => $name))) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseArtist (getSubFiles) db query failed");
    return ["name"=>$name,"files"=>$subFiles];
}

//browse albums
function getBrowseAlbum($name) {
    $subFiles = array();
    
    if($name=="ROOT") {
        $artists = array();
        $stmt = $GLOBALS["db"]->prepare("SELECT album FROM files WHERE album!='' AND album!=' ' GROUP BY album");
        if($stmt->execute(array(":name" => $name))) {
            while ($row = $stmt->fetchObject()) {
                $subFiles[] = $row;
            }
    
        } else doError("getBrowseAlbum (getAlbum) db query failed");
        return ["name"=>$name,"albums"=>$subFiles];
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id,filename,artist,title,length,size FROM files WHERE album=:name");
    if($stmt->execute(array(":name" => $name))) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseAlbum (getSubFiles) db query failed");
    return ["name"=>$name,"files"=>$subFiles];
}

//browse playlists
function getBrowsePlaylist($name) {
    $subFiles = array();
    
    if($name=="ROOT") {
        $playlists = array();
        $stmt = $GLOBALS["db"]->prepare("SELECT playlistname FROM playlistitems WHERE playlistname!='' AND playlistname!=' ' GROUP BY playlistname");
        if($stmt->execute(array(":name" => $name))) {
            while ($row = $stmt->fetchObject()) {
                $subFiles[] = $row;
            }
    
        } else doError("getBrowsePlaylist (getPlaylist) db query failed");
        return ["name"=>$name,"playlists"=>$subFiles];
    }
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id,filename,artist,title,length,size from playlistitems INNER JOIN files on(files.id=playlistitems.fileid) WHERE fileid IS NOT NULL AND playlistname=:name");
    if($stmt->execute(array(":name" => $name))) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowsePlaylist (getSubFiles) db query failed");
    return ["name"=>$name,"files"=>$subFiles];
}

//get files often played in playlists
function getBrowseOftenPlaylist() {
    $subFiles = array();
    
    $stmt = $GLOBALS["db"]->prepare("SELECT id,filename,artist,title,length,size, COUNT(*) as count from playlistitems INNER JOIN files on(files.id=playlistitems.fileid) WHERE fileid IS NOT NULL GROUP BY id ORDER BY count DESC LIMIT 100");
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseOftenPlaylist (getSubFiles) db query failed");
    return ["files"=>$subFiles];
}

//get files often voted
function getBrowseOftenVote() {
    $subFiles = array();
    
    $stmt = $GLOBALS["db"]->prepare("SELECT files.id,filename,artist,title,length,size, COUNT(*) as count from votes INNER JOIN files on(files.id=votes.fileid) GROUP BY files.id ORDER BY count DESC LIMIT 100");
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseOftenPlaylist (getSubFiles) db query failed");
    return ["files"=>$subFiles];
}

//get files last played
function getBrowsePlaylog($number = 100) {
    $subFiles = array();
    
    $stmt = $GLOBALS["db"]->prepare("SELECT files.id,filename,artist,title,length,size,TIMESTAMPDIFF(MINUTE,playlog.date,NOW()) as date from playlog INNER JOIN files on(files.id=playlog.fileid) ORDER BY date ASC LIMIT :number");
    $stmt->bindParam(':number', $number, PDO::PARAM_INT);
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
    } else doError("getBrowseOftenPlaylist (getSubFiles) db query failed");
    return ["files"=>$subFiles];
}

function getBrowseOftenPlayed() {
    $subFiles = array();
    
    $stmt = $GLOBALS["db"]->prepare("SELECT files.id,filename,artist,title,length,size,COUNT(*) as count from playlog INNER JOIN files on(files.id=playlog.fileid) GROUP BY files.id ORDER BY count DESC LIMIT 100");
    if($stmt->execute()) {
        while ($row = $stmt->fetchObject()) {
            $subFiles[] = $row;
        }
        
        for($i=0;$i<count($subFiles);$i++) {
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM votes WHERE fileid =:fid AND ip=:ip ORDER BY date DESC LIMIT 1");
            $dateLastVote=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id,":ip" => $_SERVER['REMOTE_ADDR']))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastVote = $row->date;
                }
            }
            
            $stmt = $GLOBALS["db"]->prepare("SELECT date FROM playlog WHERE fileid =:fid ORDER BY date DESC LIMIT 1");
            $dateLastPlay=null;
            if($stmt->execute(array(":fid" => $subFiles[$i]->id))) {
                if ($row = $stmt->fetchObject()) {
                    $dateLastPlay = $row->date;
                }
            }
            
            if($dateLastVote===null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote===null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = false;
            } elseif($dateLastVote!==null && $dateLastPlay===null) {
                $subFiles[$i]->alreadyVoted = true;
            } elseif($dateLastVote!==null && $dateLastPlay!==null) {
                $subFiles[$i]->alreadyVoted = ($dateLastVote>$dateLastPlay);
            }
        }
        
    } else doError("getBrowseOftenPlayed (getSubFiles) db query failed");
    return ["files"=>$subFiles];
}

//vote possible?
function getVoteSkipCheck() {
    //not possible if state!=playing
    $state = getMpdValue("status","state");
    if($state != "play") return 2;

    //not possible in last 10 seconds of song
    $timeTotal = intval(getMpdValue("currentsong","Time"));
    $timeCurrent = intval(getMpdCurrentTime());
    if(($timeTotal-$timeCurrent)<10) return 2;

    //not possible if already voted
    $fileid = getMpdCurrentSong()["fileinfos"]->id;
    $stmt = $GLOBALS["db"]->prepare("SELECT * FROM voteforskip WHERE fileid=:fileid AND ip=:ip AND DATE>DATE_SUB(NOW(),INTERVAL :seconds SECOND)");
    if($stmt->execute(array(":fileid" => $fileid, "ip" => $_SERVER['REMOTE_ADDR'], ":seconds" => $timeCurrent))) {
        $row = $stmt->fetchObject();
        if($row!==false) return 1;
    }

    //possible
    return 0;
}

//upload file
function doUploadFile() {
    $GLOBALS["getid3"] = new getID3;
    $count = count($_FILES['thefile']['error']);
    $echohtml_content="";
    for($i=0;$i<$count;$i++) {
        $echohtml_content.= "<h2>".$_FILES['thefile']['name'][$i]."</h2>";
        if(!($_FILES['thefile']['error'][$i]===0))      $echohtml_content.= "Errorcode: ".$_FILES['thefile']['error'][$i]." http://php.net/manual/de/features.file-upload.errors.php<br />";
        if(!isset($_FILES['thefile']['tmp_name'][$i])) 	{$echohtml_content.= "Error: no tmp_filename"; break; }
        if($_FILES['thefile']['tmp_name'][$i]=="") 		{$echohtml_content.= "Error: tmp_filename ist empty"; break; } //[tmp_name] => /home/www/sp01_62/phptmp/phpLqU14d
        if(!isset($_FILES['thefile']['name'][$i])) 		{$echohtml_content.= "Error: no name"; break; } //name] => lircd.bak
        if($_FILES['thefile']['name'][$i]=="") 		    {$echohtml_content.= "Error: name ist blank"; break; }
        if(!isset($_FILES['thefile']['error'][$i])) 	{$echohtml_content.= "Error: no errorcode"; break; }
        if(!($_FILES['thefile']['error'][$i]===0))  	{$echohtml_content.= "Error: errornumber ist not 0"; break; } //[error] => 0
        if($_FILES['thefile']['size'][$i]==0) 		    {$echohtml_content.= "Error: Filesize is zero"; break; } //[size] => 2917 ca 3kb => 10mb
        $ending=strrchr($_FILES['thefile']['name'][$i], ".");
        $ending=str_replace(".", "", $ending);
        $ending=strtolower($ending);
        if ($ending!="mp3") { $echohtml_content.="Error: Only .mp3 files are allowed!<br>"; break; }

        $date=date("Y.m.d_H.i.s");
        $file_name=$date."_".preg_replace("/[^A-Za-z0-9._ ]/", '', $_FILES['thefile']['name'][$i]);
        $file_name=str_replace(" ","_",$file_name);
        $file_name=str_replace(" ","_",$file_name);
        $file_path_rel=$GLOBALS["pathuploads"]."/".$file_name;
        $file_path_abs=$GLOBALS["path"]."/".$file_path_rel;
        if (file_exists($file_path_abs)) { $echohtml_content.= "Error: File already exists!<br>"; break; }
        if(move_uploaded_file($_FILES['thefile']['tmp_name'][$i],$file_path_abs)){
            $echohtml_content.="Success<br>Filename: ".$file_name."<br>";
            mpdScan($file_path_rel);
            $foldernum = getFolderidforFolderpath($GLOBALS["pathuploads"]);
            insertFileInDb($foldernum,$file_path_abs,false);
        } else $echohtml_content.="Error: There was an error copying yout file. Maybe the program has no write access to the destination folder?";
    }
    $echohtml_content.='<br /><br /><a href="/">back</a>'; //todo translate this, or use icon
    echo $echohtml_content;
}

//returns list with "currentsong","myvotes","highscoreItems"
function doDownloadFilelist() {
    $z = getMpdCurrentSong();
    if($z["state"]!="play" || !isset($z["fileinfos"])) $z=array();
    else $z=array($z["fileinfos"]);
    
    $a = array();
    $b = array();
    $c = array();
    $d = array();
    $already = array();
    
    foreach($z as $item) {
        if(!in_array($item->id,$already)) {
            $a[] = $item;
            $already[] = $item->id;
        }
    }
    foreach(doGetmyvotes() as $item) {
        if(!in_array($item->id,$already)) {
            $b[] = $item;
            $already[] = $item->id;
        }
    }
    foreach(doShowhighscore() as $item) {
        if(!in_array($item->id,$already)) {
            $c[] = $item;
            $already[] = $item->id;
        }
    }
    foreach(getBrowsePlaylog(10)["files"] as $item) {
        if(!in_array($item->id,$already)) {
            $d[] = $item;
            $already[] = $item->id;
        }
    }
    return array("a"=>$a,"b"=>$b,"c"=>$c,"d"=>$d);
}

/*
---------------------------------------------------------------------------
------------------------------SCAN FILES STUFF-----------------------------
---------------------------------------------------------------------------
*/

//Print current progress in console
function printProgress() {

    //calculate fps (files per second) every x seconds
    if(time()>=$GLOBALS["next10sec"]) {
        $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"];
        $diff = $GLOBALS["files"] - $GLOBALS["lastfiles"];
        $GLOBALS["filespersec"] = $diff/$GLOBALS["timestep"];
        $GLOBALS["lastfiles"] = $GLOBALS["files"];
    }
    
    //only continue this function every x files ($GLOBALS["outputeveryxfiles"])
    if($GLOBALS["files"]%$GLOBALS["outputeveryxfiles"]!=0) return;
    
    //echo ProgressBar
    echo "[";
    $num = $GLOBALS["ProgressBarLength"]*$GLOBALS["files"]/$GLOBALS["totalfiles"];
    for($i=0;$i<$GLOBALS["ProgressBarLength"];$i++) {
        if($i<=intval($num)) echo "#";
        else echo ".";
    }
    echo "]     ";
    
    //echo currentFiles/totalFiles and percent
    echo    $GLOBALS["files"]."/".$GLOBALS["totalfiles"]." ".
            number_format(100*$GLOBALS["files"]/$GLOBALS["totalfiles"],2)."% ";
            
    //echo files per second
    if($GLOBALS["filespersec"]!=0) {
        echo    $GLOBALS["filespersec"]."fps ".
                number_format(((($GLOBALS["totalfiles"]-$GLOBALS["files"])/$GLOBALS["filespersec"])/60),2)."min remaining";
    }
    echo "\n";

}

//first called function
function doRootFiles($p) {
    $GLOBALS["files"]=0;
    $GLOBALS["totalfiles"]=0;
    $GLOBALS["filespersec"]=0;
    $GLOBALS["next10sec"]=0;
    $GLOBALS["lastfiles"]=0;
    $GLOBALS["ProgressBarLength"] = 40; //length of ProgressBar
    $GLOBALS["timestep"] = 5; //re-calculate files per second every x seconds
    $GLOBALS["outputeveryxfiles"] = 10; //print status every x files
    $GLOBALS["next10sec"] = time() + $GLOBALS["timestep"];
    
    //calculate time for specific tasks
    $GLOBALS["Ttags"]=0; //get Tags
    $GLOBALS["Tsize"]=0; //get size
    $GLOBALS["Tdb"]=0; //insert into database
    $GLOBALS["Tpro"]=0; //calculate and print progress
    
    echo "Do Root: ".$p."\n";
    
    //calculate total filecount
    $GLOBALS["totalfiles"]=intval(shell_exec('find "'.$p.'" -iname "*.mp3" | wc -l'));
    
    //do mp3s in root
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        //insert mp3 in database
        insertFileInDb(-1,$mp3);
    }
    
    //do folders in root
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder(-1,$dir);
    }
    
    //print timers
    echo "\nFinished!\n";
    echo "TAGS: ".$GLOBALS["Ttags"]."s\n";
    echo "DABA: ".$GLOBALS["Tdb"]."s\n";
    echo "PROG: ".$GLOBALS["Tpro"]."s\n";
    echo "SIZE: ".$GLOBALS["Tsize"]."s\n";
}

//proceed one folder
function doOneFolder($parentid,$p) {
    echo "Do Folder: ".$p."\n";

    //insert folder in db
    $foldernum = insertFolderInDb($parentid,$p);
    
    //do mp3s
    $sPath = $p.'/*.mp3';
    foreach (glob($sPath) AS $mp3)
    {
        //insert mp3 in database
        insertFileInDb($foldernum,$mp3);
    }
    
    //continue with sub-folders
    foreach(glob($p.'/*' , GLOB_ONLYDIR) AS $dir) {
        doOneFolder($foldernum,$dir);
    }
}

//enters folder in database
//returns folderid
function insertFolderInDb($parentid,$folderpath) {

    //get picture
    $pic = null;
    if(file_exists($folderpath."/cover.jpg")) {
        $pic = file_get_contents($folderpath."/cover.jpg");
    } else if(file_exists($folderpath."/AlbumArtSmall.jpg")) {
        $pic = file_get_contents($folderpath."/AlbumArtSmall.jpg");
    } else if(file_exists($folderpath."/Folder.jpg")) {
        $pic = file_get_contents($folderpath."/Folder.jpg");
    }
    
    //insert in database
    $stmt = $GLOBALS["db"]->prepare("INSERT INTO folders (parentid,foldername,picture) VALUES(:pid, :fname, :pic)");
    $foldername = basename($folderpath);
    if(!$stmt->execute(array(':pid' => intval($parentid), ':fname' => $foldername, ':pic' => $pic))) {
        die("insertFolderInDb: Error");
    }
    
    //return id
    return intval($GLOBALS["db"]->lastInsertID());
}

//enters file in database
//returns fileid
function insertFileInDb($foldernum,$p,$scan = true) {
    //get size
    if($scan) $Tstart = microtime(true);
    $size = filesize($p);
    if($scan) $GLOBALS["Tsize"]+=(microtime(true)-$Tstart);
    
    //get tags&length
    if($scan) $Tstart = microtime(true);
    $ThisFileInfo = $GLOBALS["getid3"]->analyze($p);
    $year =     isset($ThisFileInfo['tags']['id3v2']['year'][0]) ? $ThisFileInfo['tags']['id3v2']['year'][0] : "";
    $artist =   isset($ThisFileInfo['tags']['id3v2']['artist'][0]) ? $ThisFileInfo['tags']['id3v2']['artist'][0] : "";
    $title =    isset($ThisFileInfo['tags']['id3v2']['title'][0] ) ? $ThisFileInfo['tags']['id3v2']['title'][0]  : "";
    $album =    isset($ThisFileInfo['tags']['id3v2']['album'][0] ) ? $ThisFileInfo['tags']['id3v2']['album'][0]  : "";
    $length =   isset($ThisFileInfo['playtime_seconds']) ? $ThisFileInfo['playtime_seconds'] : 0;
    if($year===null) $year="";
    if($scan) $GLOBALS["Ttags"]+=(microtime(true)-$Tstart);
    
    //insert into database
    if($scan) $Tstart = microtime(true);
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
    if($scan) $GLOBALS["Tdb"]+=(microtime(true)-$Tstart);
    
    if($scan) $GLOBALS["files"]++;
    
    //print Progress
    if($scan) $Tstart = microtime(true);
    if($scan) printProgress();
    if($scan) $GLOBALS["Tpro"]+=(microtime(true)-$Tstart);
    
    //return id
    return intval($GLOBALS["db"]->lastInsertID());
}

/*
---------------------------------------------------------------------------
---------------------------SCAN PLAYLISTS STUFF----------------------------
---------------------------------------------------------------------------
*/


//first called function
function doRootPlaylists($p) {
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
    $content = str_replace("\xef\xbb\xbf","",$content);
    
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
            $id = null;
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