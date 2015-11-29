<?php

/*
thx to http://sourceforge.net/projects/phpmp3/files/phpmp3/0.2.1/phpMp3-0.2.1.tar.bz2/download

    //telnet localhost 6600
    //http://www.musicpd.org/doc/protocol/command_reference.html
    
status
seek _ INT
setvol INT
lsinfo PATH
playid ID
add _
update _
playlistinfo
shuffle
next
stop
pause
play
previous

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
    
    
require("../../settings.php");

class MPD {
    var $link;

    function open() {
        global $MPD_HOST,$MPD_PORT;
        $this->link = fsockopen($GLOBALS["mpdip"],$GLOBALS["mpdport"],$errno,$errstr,2);
        stream_set_timeout($this->link,2);
        if (!$this->link) {
            die("cannot connect to mpd-server at $MPD_HOST:$MPD_PORT");
        }
        fgets($this->link);
    }

    function close() {
        fwrite($this->link,"close\n");
        fclose($this->link);
    }
    
    function cmd($cmd) {
        $this->open();
        $q = "";
        if (is_array($cmd)) {
            $q = "command_list_begin\n". implode("\n",$cmd) . "\ncommand_list_end\n";
        } else {
            $q = "$cmd\n";
        }
        fwrite($this->link,$q);
        
        $result = "";
        while (!feof($this->link)) {
            $buf = fgets($this->link,8192);
            
            if (substr($buf,0,2) == "OK") {
                 break;
            } else {
                $result .= $buf;
            }
         }

        $this->close();
        return $result;
    }

    function status() {
        $this->open();
        fwrite($this->link,"status\n");
        $status = "";
        while (!feof($this->link)) {
            $status .= fread($this->link,8192);
            
        }
        $this->close();
        return $status;
    }    
}
?>