# VoteMPD

* Author: Felix Sterzelmaier
* Version 0.7
* Date: 03. January 2016
* Github Page: http://k3x.github.io/VoteMPD/
* Github Code: https://github.com/k3x/VoteMPD
* Github Git URL: https://github.com/k3x/VoteMPD.git

VoteMPD allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com

## Features
* Scan Filesystem and fill id3 information into database
* Scan m3u playlists ans fill information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest voted songs
* Delete own votes
* Show highscore
* Upload/Download mp3 files

## Install

### Base
* sudo apt-get install php5 php5-cli php5-json php5-mysql apache2 mysql-server mpd alsa-base
* php shell exec has to be eneabled; php.ini: file_uploads = On,post_max_size = 100M,upload_max_filesize = 100M
* configure /etc/mpd.conf (music library and audio autput, mixer_type "software")
* sudo service mpd restart
* let mpd rescan the database. For example: on a client: sudo apt-get install gmpc; run gmpc; connect to mpd; do Server -> Update MPD Database
* Create new mysql database and configure "includes/settings.php". Enter your mpd connection infos and also enter the same "path" like you configured for mpd.
* import dist/votempd.sql in the created mysql database.
* run "php daemon.php -f" and run "php daemon.php -p"
* Let your Apache "/" point to the root folder (the folder with index.html)
* in console run: php daemon.php

### settings.php
* $GLOBALS["path"]: absolute path to your music library. Has to be the same like you configured in your mpd.conf. Example: "/home/k3x/music"
* $GLOBALS["pathplaylists"]: relative path to your playlistfolder inside $GLOBALS["path"]. Example: "playlists"
* $GLOBALS["pathuploads"]: relative path to your uploadsfolder inside $GLOBALS["path"]. Example: "uploads"
* $GLOBALS["defaultplaylist"]: name of your default playlist (without ".m3u"). The playlist uses the databasetable "playlistitems" so this playlist hast to be scanned before using the command "php daemon.php -p". Example: "charts_2015"
* $GLOBALS["voteskipcount"]: Integer. If this amount of users voted to skip the song it is skipped. Example: 2

### Sample folderstructure for examples in settings.php
```
/
|-home
  |-k3x
    |-music
      |-playlists
        |-charts_2015.m3u
      |-uploads
      |-ACDC
        |-Back in Black.mp3
      |-Creedence Clearwater Revival
        |-Proud Mary.mp3
```
In this example a valid entry in the playlist would be: "ACDC/Back in Black.mp3"

### Further information / find errors
* if you configured mpd to use alsa and you can not hear music check "alsamixer". Also check that no channel is muted.
* ncmpc is a commandline mpd client

### Autostart
* if you want the daemon to start on every boot maybe the systemd script dist/votempd.service will help you.
* sudo nano /etc/systemd/system/votempd.service
* sudo systemctl daemon-reload
* sudo systemctl start votempd.service
* systemctl status votempd.service

### Let apache not wait for ethernet on boot (only wifi)
* sudo nano /lib/systemd/system/network-online.target.wants/ifup-wait-all-auto.service
* for i in $(echo "wlp1s0"); do INTERFACES="$INTERFACES$i "; done; \

### create WIFI Hotspot (Accesspoint/AP/Master Mode)
* sudo apt-get install hostapd
* sudo nano /etc/hostapd/hostapd.conf     (see dist/etc-hostapd-hostapd.conf)
* sudo nano /etc/default/hostapd    =>    DAEMON_CONF="/etc/hostapd/hostapd.conf"

### network, DNS and DHCP
* configure your wifi connection to a static ip. (see dist(etc-network-interfaces)
* sudo apt-get install dnsmasq
* sudo nano /etc/dnsmasq.conf   (dhcp-range,listen-address,interface,address) (see dist/dnsmasq.conf)
* sudo service dnsmasq restart

## Playlists Information
* .m3u playlists with one song per line. Linebreaks: \x0D\x0A Charset: UTF-8 (with Byte Order Mark (BOM), so files start with \xEF\xBB\xBF)
* Paths have to be relative to your root dir. For example: "somedir/somefile.mp3" (without quotes)

## Used Librarys
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
