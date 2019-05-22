
## Install

### Base
* mpd/php daemon needs reading rights to files
* upload folder has to be writeable for php daemon
* sudo apt-get install php7.0 php7.0-cli php7.0-json php7.0-gd php7.0-mysql apache2 mysql-server mpd alsa-base
* php shell exec has to be eneabled; php.ini: file_uploads = On,post_max_size = 100M,upload_max_filesize = 100M
* configure /etc/mpd.conf (music library and audio autput, mixer_type "software")
* sudo service mpd restart
* let mpd rescan the database. For example: on a client: sudo apt-get install gmpc; run gmpc; connect to mpd; do Server -> Update MPD Database
* Create new mysql database and configure "includes/settings.php" (copy from dist/settings.php). Enter your mpd connection infos and also enter the same "path" like you configured for mpd.
* import dist/votempd.sql in the created mysql database.
* run "php daemon.php -f" and run "php daemon.php -p" (this command does NOT delete old database enties. On rescan truncate files,folders,playlistitems,playlog,voteforskip,votes,options_date,options_int)
* Let your Apache "/" point to the root folder (the folder with index.html)
* in console run: php daemon.php

### settings.php
* $GLOBALS["path"]: absolute path to your music library. Has to be the same like you configured in your mpd.conf. Example: "/home/k3x/music"
* $GLOBALS["pathplaylists"]: relative path to your playlistfolder inside $GLOBALS["path"]. Example: "playlists"
* $GLOBALS["pathuploads"]: relative path to your uploadsfolder inside $GLOBALS["path"]. Example: "uploads"
* $GLOBALS["defaultplaylist"]: name of your default playlist (without ".m3u"). The playlist uses the databasetable "playlistitems" so this playlist hast to be scanned before using the command "php daemon.php -p". Example: "charts_2015"
* $GLOBALS["voteskipcount"]: Integer. If this amount of users voted to skip the song it is skipped. Example: 2


### Autostart
* if you want the daemon to start on every boot maybe the systemd script dist/votempd.service will help you.
* sudo nano /etc/systemd/system/votempd.service
* sudo systemctl daemon-reload
* sudo systemctl start votempd.service
* systemctl status votempd.service

### Faster Boot without Ethernet

#### a) Let apache not wait for ethernet on boot (only wifi)
* sudo nano /lib/systemd/system/network-online.target.wants/ifup-wait-all-auto.service
* for i in $(echo "wlp1s0"); do INTERFACES="$INTERFACES$i "; done; \

#### b) change in /etc/network/interfaces
auto eth0
iface eth0 inet dhcp

to

allow-hotplug eth0
iface eth0 inet dhcp

### Create WIFI Hotspot (Accesspoint/AP/Master Mode)
* sudo apt-get install hostapd
* sudo nano /etc/hostapd/hostapd.conf     (see dist/etc-hostapd-hostapd.conf)
* sudo nano /etc/default/hostapd    =>    DAEMON_CONF="/etc/hostapd/hostapd.conf"

### Network, DNS and DHCP
* configure your wifi connection to a static ip. (see dist(etc-network-interfaces)
* sudo apt-get install dnsmasq
* sudo nano /etc/dnsmasq.conf   (dhcp-range,listen-address,interface,address) (see dist/dnsmasq.conf)
* sudo service dnsmasq restart

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
