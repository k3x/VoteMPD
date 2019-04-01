# VoteMPD

* Author: Felix Sterzelmaier
* Version 0.8.9
* Date: 1. April 2019
* Github Page: http://k3x.github.io/VoteMPD/
* Github Code: https://github.com/k3x/VoteMPD
* Github Git URL: https://github.com/k3x/VoteMPD.git

VoteMPD allows your party guests to fill the queue of your MusicPlayerDaemon.
Just run this Script on a server and make it availiable over wifi. See also: http://getfestify.com

* [Install instructions (Variant A)](INSTALL1.md)
* [Install instructions (Variant B)](INSTALL2.md)
* [Rescan instructions](RESCAN.md)

## Features
* Scan Filesystem and fill id3 information into database
* Scan m3u playlists ans fill information into database
* Allow users to vote for songs via a mobile friendly webpage
* MPD queue is filled with highest voted songs
* Delete own votes
* Show highscore
* Upload/Download mp3 files

### Language
* Errors are always in english
* If you want the interface to be english instead of german: 
** Delete index.html and rename index_en.html to index.html.
** in script.js.php, line 26: $language = "en";

### Further information / find errors
* if you configured mpd to use alsa and you can not hear music check "alsamixer". Also check that no channel is muted.
* ncmpc is a commandline mpd client
* for uploading files your folder has to be writeable

## Playlists Information
* .m3u playlists with one song per line. Linebreaks: \x0D\x0A Charset: UTF-8 (with Byte Order Mark (BOM), so files start with \xEF\xBB\xBF)
* Paths have to be relative to your root dir. For example: "somedir/somefile.mp3" (without quotes)

## Used Librarys
* phpMp3 (for MPD communication) http://sourceforge.net/projects/phpmp3
* getID3 (for getting id3 tags) http://getid3.sourceforge.net

## todo's

## License
VoteMPD is free software. It is released under the terms of
the following BSD License.

Copyright © 2018 by 
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
