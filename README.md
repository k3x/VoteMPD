# MPDVote

Author: Felix Sterzelmaier

Version 0.1

Date: 28. November 2015

MPDVote allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi.


## Features
* Scan Filesystem and fill id3 information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest votet songs

## Install
sudo apt-get install mp3info php5

## Todos
* Implement php daemon: Connect to mpd server and insert first highscore item to mpd queue 5 seconds before current song ends.
* Browse songs by folder,artist,album,interpret  
* Count only votes for song after the song was played last (same behavior like delete all songvotes on songplay, but we have history)
* Only alow voting if user has not already votet since last time the song was played
* Implement as Androidapp, so you only need a Tablet/2nd Smartphone instead of Notebook/RaspberryPi