## RESCAN
* copy files
* Server -> Update MPD Database
* sudo service mpd stop
* sudo killall php
* mysql -u root -p
* show database;
* use votempd;
* show tables;
* TRUNCATE files;
* TRUNCATE folders;
* TRUNCATE playlistitems;
* TRUNCATE playlog;
* TRUNCATE voteforskip;
* TRUNCATE votes;
* TRUNCATE options_date;
* TRUNCATE options_int;
* (CTRL-D)
* php daemon.php -f
* php daemon.php -p
* sudo service mpd start
* php daemon.php