# MPDVote

* Author: Felix Sterzelmaier
* Version 0.1
* Date: 28. November 2015

MPDVote allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com


## Features
* Scan Filesystem and fill id3 information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest voted songs

## Install
* sudo apt-get install mp3info php5 mpd
* php shell exec has to be eneabled
* configure /etc/mpd.conf
* sudo service mpd restart
* sudo apt-get install gmpc
* run gmpc, connect to mpd, do Server -> Update MPD Database
* Install mysql server and configure "settings.php". Also enter the same "path" like you configured for mpd.
* run "php start.php"
* Let your Apache "/" point to the folder "php-vote" (the folder with css,gfx,js,php,index.html)
* in console run php daemon.php

## Todos
* Browse songs by folder,artist,album,interpret
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi
* Default queue when no user is voting
* Maybe do not scan filesystem. Instead Get Music Database from MPD. Advantage: no need to run on the same server
* Upload File
* SCAN case insensetive jpg
* SCAN files/totalfiles, %, files per sec, remaining time

## License

## Used Librarys/Icons/Codesnippets/Licenses (todo)
* phpMp3 http://sourceforge.net/projects/phpmp3
* getID3 http://getid3.sourceforge.net   