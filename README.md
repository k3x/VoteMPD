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
* Install mysql server, create new database and configure "settings.php". Enter your mpd connection infos and also enter the same "path" like you configured for mpd.
* import dist/votempd.sql in the created mysql database.
* run "php start.php" in php-scan/
* Let your Apache "/" point to the folder "php-vote" (the folder with css,gfx,js,php,index.html)
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

## Todos
### 1. Priority
* Browse songs by playlist
* doShowhighscore(): order +by oldest vote ^
* laptop mpd volume

### 2. Priority
* addOneFileToMPDQueue(): get song from static playlist if($hn===null) / Default queue when no user is voting
* SCAN case insensetive jpg,m3u,mp3 (glob('my/dir/*.[cC][sS][vV]') ?)
* volume on hotkeys
* do not load so much ajax. only on demand.
* spinner.gif on ajax load
* Ordnerstruktur anpassen, sodass alle aufgerufenen dateien in / sind. playlistscan soll functions.php nutzen
* rename to "VoteMPD"
* every code/comment should be english
* Comment

### 3. Priority
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi
* Maybe do not scan filesystem. Instead Get Music Database from MPD. Advantage: no need to run on the same server
* Upload File
* save playlog to playlist (new plalist on 10h pause)
* Multilanguage
* Explain m3u format (Linebreaks,charset,relative path to)
* License

## License

### VoteMPD

### Used Librarys/Icons/Codesnippets
* phpMp3 http://sourceforge.net/projects/phpmp3
* getID3 http://getid3.sourceforge.net   