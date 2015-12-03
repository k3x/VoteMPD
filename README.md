# VoteMPD

* Author: Felix Sterzelmaier
* Version 0.4
* Date: 02. December 2015

VoteMPD allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com

## Features
* Scan Filesystem and fill id3 information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest voted songs

## Install

### Base
* sudo apt-get install php5 php5-cli php5-json php5-mysql apache2 mysql-server mpd alsa-base
* php shell exec has to be eneabled
* configure /etc/mpd.conf (music library and audio autput)
* sudo service mpd restart
* sudo apt-get install gmpc
* run gmpc, connect to mpd, do Server -> Update MPD Database
* Install mysql server, create new database and configure "includes/settings.php". Enter your mpd connection infos and also enter the same "path" like you configured for mpd.
* import dist/votempd.sql in the created mysql database.
* run "php scan-files.php" and run "php scan-playlists.php"
* Let your Apache "/" point to the root folder (the folder with index.html)
* in console run php daemon.php

### Further information / find errors
* if you configured mpd to use alsa and you can not hear music check "alsamixer". Also check that no channel is muted.
* ncmpc is a commandline mpd client

### Autostart
* if you want to the daemon to start on every reboot maybe the systemd script dist/votempd.service will help you.
* sudo nano /etc/systemd/system/votempd.service
* sudo systemctl daemon-reload
* sudo systemctl start votempd.service
* systemctl status votempd.service

### create WIFI Hotspot (Accesspoint/AP/Master Mode)
* sudo apt-get install hostapd
* sudo nano /etc/hostapd/hostapd.conf     (see dist/etc-hostapd-hostapd.conf)
* sudo nano /etc/default/hostapd    =>    DAEMON_CONF="/etc/hostapd/hostapd.conf"

### network, DNS and DHCP
* configure your wifi connection to a static ip. (see dist(etc-network-interfaces)
* sudo apt-get install dnsmasq
* sudo nano /etc/dnsmasq.conf   (dhcp-range,listen-address,interface,address) (see dist/dnsmasq.conf)
* sudo service dnsmasq restart

### Information

#### Playlists
* .m3u playlists with one song per line. Linebreaks: \x0d\x0a Charset: UTF-8
* Paths have to be relative to your root dir. For example: "somedir/somefile.mp3" (without quotes)

## Todos
### 1. Priority
* laptop mpd volume
* volume on hotkeys
* interpret, album, playlists, oip, oiv => no filename but artist: title

### 2. Priority
* addOneFileToMPDQueue(): get song from static playlist if($hn===null) / Default queue when no user is voting
* every code/comment should be english

### 3. Priority
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi
* Maybe do not scan filesystem. Instead Get Music Database from MPD. Advantage: no need to run on the same server
* Upload File
* save playlog to playlist (new plalist on 10h pause)
* Multilanguage
* Explain m3u format (Linebreaks,charset,relative path to)

### Used Librarys/Icons/Codesnippets
* phpMp3 (for MPD communication) http://sourceforge.net/projects/phpmp3
* getID3 (for getting id3 tags) http://getid3.sourceforge.net

## License

VoteMPD is free software. It is released under the terms of
the following BSD License.

Copyright © 2015 by 
    Felix 'K3X' Sterzelmaier

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in
   the documentation and/or other materials provided with the
   distribution.
 * Neither the name of VoteMPD nor the names of its
   contributors may be used to endorse or promote products derived
   from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
