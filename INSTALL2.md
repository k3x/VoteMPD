VoteMPD Setup Notes


Installed debian-9.8.0-i386-netinst via USB on a old and small laptop i upgraded with a ssd. No desktop environment was installed, but a ssh-server. The wifi chip is able to be run in host/accesspoint mode.

```
su
apt-get update
apt-get upgrade
apt-get dist-upgrade
apt-get install -y net-tools ufw nano bash-completion tmux alsa-tools alsa-utils php7.0 php7.0-gd php7.0-cli php7.0-json php7.0-mysql apache2 mysql-server mpd ncmpc git alsamixergui libapache2-mod-php7.0 hostapd dnsmasq
echo "source /etc/profile.d/bash_completion.sh" >> ~/.bashrc
ufw disable


echo "upload_max_filesize = 128M" >> /etc/php/7.0/cli/php.ini
echo "file_uploads = On" >> /etc/php/7.0/cli/php.ini
echo "post_max_size = 128M" >> /etc/php/7.0/cli/php.ini
echo "safe_mode = off" >> /etc/php/7.0/cli/php.ini
echo "display_errors = On" >> /etc/php/7.0/cli/php.ini

echo "upload_max_filesize = 128M" >> /etc/php/7.0/apache2/php.ini
echo "file_uploads = On" >> /etc/php/7.0/apache2/php.ini
echo "post_max_size = 128M" >> /etc/php/7.0/apache2/php.ini
echo "safe_mode = off" >> /etc/php/7.0/apache2/php.ini
echo "display_errors = On" >> /etc/php/7.0/apache2/php.ini

mkdir /music
chmod -R 777 /music
#copy music to /music

# cat /etc/mpd.conf | egrep -v "(^#.*|^$)"

cat <<EOT > /etc/mpd.conf
music_directory         "/music"
playlist_directory      "/var/lib/mpd/playlists"
db_file                 "/var/lib/mpd/tag_cache"
log_file                "/var/log/mpd/mpd.log"
pid_file                "/run/mpd/pid"
state_file              "/var/lib/mpd/state"
sticker_file            "/var/lib/mpd/sticker.sql"
user                    "mpd"
bind_to_address         "0.0.0.0"
input {
        plugin "curl"
}
audio_output {
        type            "alsa"
        name            "My ALSA Device"
        mixer_type      "software" 
}
filesystem_charset      "UTF-8"
id3v1_encoding          "UTF-8"
EOT


service mpd restart

ncmpc -h 127.0.0.1
STRG+U
q


mkdir /votempd
cd /votempd
git clone https://github.com/k3x/VoteMPD.git .

systemctl start mysql
systemctl enable mysql


nano /etc/mysql/mariadb.conf.d/50-server.cnf
bind-address = 0.0.0.0

/usr/bin/mysql -u root OR /usr/bin/mysql -u root -p
CREATE DATABASE votempd;
USE votempd;
SET PASSWORD FOR 'root'@'localhost' = PASSWORD("root");
UPDATE mysql.user SET authentication_string = PASSWORD('root') WHERE User = 'root' AND Host = 'localhost';
CREATE USER 'root'@'%' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
USE mysql;
update user set plugin='' where User='root';
flush privileges;
exit;

/usr/bin/mysql -u root votempd < /votempd/dist/votempd.sql
service mysql restart

cd /votempd
cp dist/settings.php includes/
vi includes/settings.php


$db_pass="root";
#$GLOBALS["path"]="/music"; // without trailing "/"
#$GLOBALS["pathplaylists"]="1_e_playlists_mpd""; // without trailing "/"; Folder inside #$GLOBALS["path"]
#$GLOBALS["pathuploads"]="uploads"; // without trailing "/"; Folder inside #$GLOBALS["path"]
#$GLOBALS["defaultplaylist"]="2015_charts"; // playlist to use if highscore is empty
#$GLOBALS["voteskipcount"]=2; // if more than x user vote for skip => skip

php daemon.php -f
php daemon.php -p
php daemon.php


cat <<EOT >> /etc/apache2/apache2.conf
<Directory /votempd>
        Options Indexes FollowSymLinks
        AllowOverride None
        Require all granted
</Directory>
EOT

vi /etc/apache2/sites-enabled/000-default.conf
documentroot /votempd
service apache2 restart


cd /votempd
cp dist/votempd.service /etc/systemd/system/
vi /etc/systemd/system/votempd.service
WorkingDirectory=/votempd/
ExecStart=/usr/bin/php7.0 /votempd/daemon.php
Restart=always
systemctl daemon-reload
systemctl enable votempd.service
systemctl start votempd.service
systemctl status votempd.service


adjust volume with alsamixer and ncmpc -h 127.0.0.1


cp dist/etc-hostapd-hostapd.conf /etc/hostapd/hostapd.conf
nano /etc/hostapd/hostapd.conf
nano /etc/default/hostapd
DAEMON_CONF="/etc/hostapd/hostapd.conf"


nano /etc/dnsmasq.conf
dhcp-range=10.0.0.10,10.0.0.200,255.255.255.0,96h
listen-address=10.0.0.1
interface=wlp1s0
address=/musik.de/10.0.0.1

nano /etc/network/interfaces
auto wlp1s0
iface wlp1s0 inet static
address 10.0.0.1
netmask 255.255.255.0
network 10.0.0.0
broadcast 10.0.0.255
dns-nameservers 10.0.0.1


nano /etc/apt/sources.list
deb http://debian.charite.de/debian/ stretch main contrib non-free
deb-src http://debian.charite.de/debian/ stretch main contrib non-free

apt-get install firmware-ralink
```