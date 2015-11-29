# MPDVote

Author: Felix Sterzelmaier

Version 0.1

Date: 28. November 2015

MPDVote allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com


## Features
* Scan Filesystem and fill id3 information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest votet songs

## Install
sudo apt-get install mp3info php5 mpd
php shell exec has to be eneabled
configure /etc/mpd.conf
sudo service mpd restart
sudo apt-get install gmpc
run gmpc, connect to mpd, do Server -> Update MPD Database
Install mysql server and configure "settings.php". Also enter the same "path" like you configured for mpd.
run "php start.php"


## Todos
* Implement php daemon: Connect to mpd server and insert first highscore item to mpd queue 5 seconds before current song ends.
* Browse songs by folder,artist,album,interpret
* Favicon + css
* Design current/next song
* Count only votes for song after the song was played last (same behavior like delete all songvotes on songplay, but we have history)
* Only allow voting if user has not already votet since last time the song was played
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi
* Fileinfos with folder pictures
* Play on vote when queue is empty
* Default queue when no user is voting

## License

## Used Librarys/Icons/Codesnippets/Licenses (todo)
* phpMp3 http://sourceforge.net/projects/phpmp3
* getID3 http://getid3.sourceforge.net   