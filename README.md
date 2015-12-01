# VoteMPD

* Author: Felix Sterzelmaier
* Version 0.2
* Date: 30. November 2015

VoteMPD allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com

## Features
* Scan Filesystem and fill id3 information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest voted songs

## Install
* sudo apt-get install php5 php5-cli php5-json php5-mysql apache2 mysql-server mpd mp3info
* php shell exec has to be eneabled
* configure /etc/mpd.conf
* sudo service mpd restart
* sudo apt-get install gmpc
* run gmpc, connect to mpd, do Server -> Update MPD Database
* Install mysql server and configure "settings.php". Also enter the same "path" like you configured for mpd.
* run "php start.php"
* Let your Apache "/" point to the folder "php-vote" (the folder with css,gfx,js,php,index.html)
* in console run php daemon.php

* DATABASE IMPORT BLANC
* AUTOSTART DAEMON

## Todos
* Browse songs by folder,artist,album,interpret
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi
* Maybe do not scan filesystem. Instead Get Music Database from MPD. Advantage: no need to run on the same server
* Upload File
* SCAN case insensetive jpg
* do daemon process forking
* doShowhighscore(): order +by oldest vote ^
* addOneFileToMPDQueue(): get song from static playlist if($hn===null) / Default queue when no user is voting
* volume on hotkeys
* scanner faster
* laptop mpd volume

## License

## Used Librarys/Icons/Codesnippets/Licenses (todo)
* phpMp3 http://sourceforge.net/projects/phpmp3
* getID3 http://getid3.sourceforge.net   