<?php

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

$max_size = ini_get('post_max_size');
$max_size2 = ini_get('upload_max_filesize');
if(return_bytes($max_size2)<return_bytes($max_size)) $max_size=$max_size2;
echo 'var maxsize = '.return_bytes($max_size).";\n";

if(ini_get('file_uploads') == 1) echo "var uploadsenabled=true;\n";
else echo "var uploadsenabled=false;\n";

$language = "de";
$translation = Array();
if($language=="de") {
    $translation["clock"] = "Uhr";
    $translation["error"] = "Es trat ein Fehler auf!";
    $translation["noplay"] = "keine Wiedergabe";
    $translation["none"] = "keins";
    $translation["next"] = "Als nächstes";
    $translation["min"] = "Minute";
    $translation["mins"] = "Minuten";
    $translation["hour"] = "Stunde";
    $translation["hours"] = "Stunden";
    $translation["day"] = "Tag";
    $translation["days"] = "Tagen";
    $translation["ago"] = "vor";
    $translation["alreadyvoted"] = "Bereits abgestimmt";
    $translation["noelements"] = "Keine Elemente!";
    $translation["vote"] = "Stimme";
    $translation["votes"] = "Stimmen";
    $translation["enteratleast3"] = "Bitte mindestens 3 Zeichen eingeben!";
    $translation["goroot"] = "nach ganz oben";
    $translation["gooneup"] = "eins nach oben";
    $translation["back"] = "zurück";
    $translation["currentsong"] = "Derzeitiges Lied";
    $translation["myvotedsongs"] = "Von mir abgestimmte Lieder";
    $translation["highscore"] = "Highscore";
    $translation["lastplayedsongs"] = "zuletzt gespielte Lieder";
    $translation["onlymp3"] = "Es werden nur mp3 Dateien akzeptiert!";
    $translation["selectfile"] = "Datei auswählen";
    $translation["send"] = "senden";
    $translation["cancel"] = "Abbrechen";
    $translation["deletevote"] = "Stimme löschen";
    $translation["dovote"] = "Abstimmen";
    $translation["notpossible"] = "Nicht möglich";
    $translation["download"] = "Download";
} 

if($language=="en") {
    $translation["clock"] = "o'clock";
    $translation["error"] = "There was an error!";
    $translation["noplay"] = "No song is played";
    $translation["none"] = "none";
    $translation["next"] = "Next";
    $translation["min"] = "minute";
    $translation["mins"] = "minutes";
    $translation["hour"] = "hour";
    $translation["hours"] = "hours";
    $translation["day"] = "day";
    $translation["days"] = "days";
    $translation["ago"] = "ago";
    $translation["alreadyvoted"] = "already voted";
    $translation["noelements"] = "No elements!";
    $translation["vote"] = "vote";
    $translation["votes"] = "votes";
    $translation["enteratleast3"] = "enter at least 3 characters!";
    $translation["goroot"] = "go to root";
    $translation["gooneup"] = "go one up";
    $translation["back"] = "back";
    $translation["currentsong"] = "Current song";
    $translation["myvotedsongs"] = "My voted songs";
    $translation["highscore"] = "Highscore";
    $translation["lastplayedsongs"] = "last played songs";
    $translation["onlymp3"] = "only mp3 files are allowed!";
    $translation["selectfile"] = "select file";
    $translation["send"] = "send";
    $translation["cancel"] = "cancel";
    $translation["deletevote"] = "delete vote";
    $translation["dovote"] = "vote";
    $translation["notpossible"] = "not possible";
    $translation["download"] = "Download";
}

?>

var ajaxpath = window.location.href+"ajax.php"; //absolute url to ajax.php
var ajaxpathrel = "ajax.php"; //relative path to ajax.php
var lastcurrent = null; //last fileinfos for currently played song
var tempposition = null; //interpolated position of song
var intervalfast = 1000; //fast update interval (interpolate song position)
var currentFolder = -1; //first folder for "browse-folders"
var currentArtist = "ROOT"; //first folder for "browse-artists"
var currentAlbum = "ROOT"; //first folder for "browse-albums"
var currentPlaylist = "ROOT"; //first folder for "browse-playlists"
var loading = '<img src="gfx/loading.gif">'; //image html code for ajax-loader.gif

//on start (called on end of html file)
$(function() {
    
    //create accordion
    $( "#accordion" ).accordion({
            active: null,
            heightStyle: "content",
            collapsible: true, 
            activate: function( event, ui ) {loadTab();},
            animate: 0
    });
    
    
    $("#search-text").val(""); //clear search input field
    getCurrent(); //update fileinfos for currently played song
    getNext(); //update fileinfos for next played song
    
    setInterval(function(){ intervalSlow(); }, 30000); //do slow update every 30s (not really needed, but for safety)
    setInterval(function(){ intervalMid(); }, 5000); //do mid update (current song changed?, paused? current position in song?)
    setInterval(function(){ intervalFast(); }, intervalfast); //do fast update (only local, interpolate song position)
});

//refresh content of currently open accoredon-tab
function loadTab() {
    var id = $( "#accordion" ).accordion( "option", "active" );
    
    if(id===null) return;
    switch(id) {
        case(0): getMy(); break;
        case(1): getHigh(); break;
        case(2): doSearch(); break;
        case(3): getFolders(currentFolder); break;
        case(4): getArtists(currentArtist); break;
        case(5): getAlbums(currentAlbum); break;
        case(6): getPlaylists(currentPlaylist); break;
        case(7): getOftenPlaylists(); break;
        case(8): getOftenVotes(); break;
        case(9): getPlaylog(); break;
        case(10): getVoteskip(); break;
        case(11): getUploadForm(); break;
        case(12): getDownloads(); break;
        case(13): getOftenPlayed(); break;
        default: break;
    }
}

//do slow update ()
function intervalSlow() {
    getNext(); //update fileinfos for next played song
    getVoteskip(); //possible to vote?
    getHigh(); //update highscore
}

//do mid update
function intervalMid() {
    getCurrent(); //update fileinfos for currently played song
}

//do fast update (only local, interpolate song position)
function intervalFast() {
    if(tempposition==null || lastcurrent==null || lastcurrent.state!="play") return;
    tempposition += intervalfast/1000;
    var percent = 100*tempposition/lastcurrent.fileinfos.length;
    if(percent>100) percent=100;
    $("#innerhead").css("background","linear-gradient(90deg, rgba(164,164,164,0.7) "+percent+"%, rgba(256,256,256,0.8) "+percent+"%)");
    $("#timespan").html(formatLength(tempposition)+"/"+formatLength(lastcurrent.fileinfos.length));
}


//format Bytes to KB, MB,...
//from http://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
function formatBytes(bytes) {
   if(bytes == 0) return '0 Byte';
   var k = 1024;
   var sizes = ['b', 'kb', 'mb', 'gb', 'tb'];
   var i = Math.floor(Math.log(bytes) / Math.log(k));
   return Math.floor((bytes / Math.pow(k, i))) + sizes[i];
}

//format seconds to mm:ss or hh:mm:ss
function formatLength(length) {
    var length = parseInt(length)
    var h = Math.floor(length/3600)
    var m = Math.floor((length/60)) % 3600
    var s = length % 60
    if(s<10) s="0"+s;
    if(h==0) {
        return m+":"+s
    } else {
        if(m<10) m="0"+m;
        return h+":"+m+":"+s
    }
}

//format date to time
function formatDate(date) {
    return date.substring(11,16)+" "+"<?php echo $translation["clock"] ?>";
}

//format Minutes
function formatMinutes(min) {
    min = parseInt(min);
    var language = "<?php echo $language ?>";
    if(language=="de") {
        if(min==1) return "<?php echo $translation["ago"] ?>"+" "+min+" "+"<?php echo $translation["min"] ?>";
        if(min<60) return "<?php echo $translation["ago"] ?>"+" "+min+" "+"<?php echo $translation["mins"] ?>";
        var hour = Math.floor(min/60);
        if(hour==1) return "<?php echo $translation["ago"] ?>"+" "+hour+" "+"<?php echo $translation["hour"] ?>";
        if(hour<24) return "<?php echo $translation["ago"] ?>"+" "+hour+" "+"<?php echo $translation["hours"] ?>";
        var days = Math.floor(hour/24);
        if(days==1) return "<?php echo $translation["ago"] ?>"+" "+days+" "+"<?php echo $translation["day"] ?>";
        return "<?php echo $translation["ago"] ?>"+" "+days+" "+"<?php echo $translation["days"] ?>";
    }
    
    if(language=="en") {
        if(min==1) return min+" "+"<?php echo $translation["min"] ?>"+" "+"<?php echo $translation["ago"] ?>";
        if(min<60) return min+" "+"<?php echo $translation["mins"] ?>"+" "+"<?php echo $translation["ago"] ?>";
        var hour = Math.floor(min/60);
        if(hour==1) return hour+" "+"<?php echo $translation["hour"] ?>"+" "+"<?php echo $translation["ago"] ?>";
        if(hour<24) return hour+" "+"<?php echo $translation["hours"] ?>"+" "+"<?php echo $translation["ago"] ?>";
        var days = Math.floor(hour/24);
        if(days==1) return days+" "+"<?php echo $translation["day"] ?>"+" "+"<?php echo $translation["ago"] ?>";
        return days+" "+"<?php echo $translation["days"] ?>"+" "+"<?php echo $translation["ago"] ?>";
    }
}

//update fileinfos for currently played song
function getCurrent() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            var percent = 0;
            var picture = null;
            if(response.status!="success" || response.action!="mpdcurrent") {
                content="<?php echo $translation["error"] ?>";
                lastcurrent = null;
            } else {
                if(response.content.state!="stop") {
                    if(response.content.fileinfos==null) {
                        content="<?php echo $translation["error"] ?>";
                        lastcurrent = null;
                    } else {
                        content=    response.content.fileinfos.artist+" - "+
                                    response.content.fileinfos.title+" (<span id='timespan'>"+formatLength(response.content.time)+"/"+formatLength(response.content.fileinfos.length)+"</span>)";
                        percent = 100*response.content.time/response.content.fileinfos.length;
                        picture = response.content.fileinfos.picture;
                        if(lastcurrent==null || lastcurrent.fileinfos.id!=response.content.fileinfos.id) {
                            intervalSlow();
                        }
                        lastcurrent = response.content;
                        tempposition = parseFloat(parseInt(response.content.time));
                    }
                } else {
                    content="("+"<?php echo $translation["noplay"] ?>"+")";
                    lastcurrent = null;
                }
            }
            if(picture==true) {
                $("#head").css("background-image","url("+ajaxpathrel+"?action=getfolderpic&id="+response.content.fileinfos.folderid+")");
                $("#head").css("background-repeat","no-repeat");
                $("#head").css("background-position","right center");
                $("#head").css("background-size","60px auto");
            }
            
            
            $("#innerhead").css("background","linear-gradient(90deg, rgba(164,164,164,0.7) "+percent+"%, rgba(256,256,256,0.8) "+percent+"%)");
            $("#innerhead").html(content);
        }
    }
    var str = ajaxpath+"?action=mpdcurrent";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//update fileinfos for next played song
function getNext() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="getnextsong") {
                content="<?php echo $translation["error"] ?>";
            } else {
                if(response.content==null) {
                    content=": ("<?php echo $translation["error"] ?>")";
                } else {
                    content="<?php echo $translation["next"] ?>"+": "+response.content.artist+" - "+response.content.title+" "+formatLength(response.content.length);
                }
            }
            $("#next").html(content);
        }
    }
    var str = ajaxpath+"?action=getnextsong";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//vote for one song
function doVote(id) {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="vote") {
                alert("<?php echo $translation["error"] ?>");
            } else {
                //loadTab();
                getNext();
                var myclass = ".votecircle-id-"+response.content;
                $(myclass).each(function(index) {
                    $(this).attr( "alt","<?php echo $translation["alreadyvoted"] ?>");
                    $(this).attr("src","gfx/voted.png");
                });

            }
        }
    }
    var str = ajaxpath+"?action=vote&id="+id;
    xhttp.open("GET", str, true);
    xhttp.send();
}

//vote for skip current song
function doVoteSkip() {
    $("#vote-skip").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="vote-skip-action") {
                content="<?php echo $translation["error"] ?>";
            } else {
                loadTab();
            }
        }
    }
    var str = ajaxpath+"?action=vote-skip-action";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//remove vote
function doRemoveVote(id) {    
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="remove-my-vote") {
                content="<?php echo $translation["error"] ?>";
            } else {
                var myclass = ".myvote-"+response.content;
                
                $(myclass).each(function(index) {
                    $(this).remove();
                });
            }
        }
    }
    var str = ajaxpath+"?action=remove-my-vote&id="+id;
    xhttp.open("GET", str, true);
    xhttp.send();
}

//download mp3
function doDownload(id) {
    window.open(ajaxpath+"?action=download-file-do&id="+id,'_blank');
}

//download m3u playlist
function doDownloadPlaylist(name) {
    window.open(ajaxpath+"?action=download-playlist&name="+name,'_blank');
}

/*
-----------------------------------------------------------------------------------------
-----------Below this line are functions for each accordion-tab-------------------------
-----------------------------------------------------------------------------------------
*/

//get my votes
function getMy() {
    $("#myvotes").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="getmyvotes") {
                content="<?php echo $translation["error"] ?>";
            } else {
                if(response.content.length==0) {
                    content="<?php echo $translation["noelements"] ?>";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        content+="<li class=myvote-"+entry.id+">"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+" "+formatDate(entry.date)+")";
                        content+='<img class="votetrash" src="gfx/trash.png" alt="'+"<?php echo $translation["deletevote"] ?>"+'" onclick="javascript:doRemoveVote('+entry.id+');"></li>';
                    }
                    content+="</ol>";
                }
            }
            $("#myvotes").html(content);
        }
    }
    var str = ajaxpath+"?action=getmyvotes";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get highscore
function getHigh() {
    $("#high").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="showhighscore") {
                content="<?php echo $translation["error"] ?>";
            } else {
                if(response.content.length==0) {
                    content="<?php echo $translation["noelements"] ?>";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        var st = "<?php echo $translation["votes"] ?>";
                        if(entry.count==1) st = "<?php echo $translation["vote"] ?>";
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+" "+entry.count+" "+st+") ";
                        if(entry.alreadyVoted) {
                            content+='<img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                        } else {
                            content+='<img class="votecircle votecircle-id-'+entry.id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+entry.id+');"></li>';
                        }
                    }
                    content+="</ol>";
                }
            }
            $("#high").html(content);
        }
    }
    var str = ajaxpath+"?action=showhighscore";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get search
function doSearch() {
    var textVal = $("#search-text").val();
    if(textVal.length==0) return;
    if(textVal.length<3) {
        $("#search > ul").html("<?php echo $translation["enteratleast3"] ?>");  
        return;
    }
    $("#search > ul").html(loading);

    $.post(ajaxpath+"?action=search", {keyword: textVal}, function(result,status){
        if(status=="success") {
            var response = JSON.parse(result);
            var content = "";
            
            if(response.status!="success" || response.action!="search") {
                content="<?php echo $translation["error"] ?>";
            } else {
                if(response.content.length==0) {
                    content="<?php echo $translation["noelements"] ?>";
                } else {
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+') ';
                        if(entry.alreadyVoted) {
                            content+='<img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                        } else {
                            content+='<img class="votecircle votecircle-id-'+entry.id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+entry.id+');"></li>';
                        }
                    }
                }
            }
            $("#search > ul").html(content);            
        }
    });
}

//get folders
function getFolders(folderid) {
    $("#browse-folders").html(loading);
    folderid = typeof folderid !== 'undefined' ? folderid : -1;
    currentFolder = folderid;
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="browse-folders") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += '<span class="current">'+response.content.path+"</span>";
                content += "<ul>";
                
                if(response.content.this!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getFolders(-1);">('+"<?php echo $translation["goroot"] ?>"+')</li>';
                    content += '<li class="goup" onclick="javascript:getFolders('+response.content.this.parentid+');">'+"<?php echo $translation["gooneup"] ?>"+'</li>';
                }
                
                for(var i=0;i<response.content.folders.length;i++) {
                    content += '<li class="folder" onclick="javascript:getFolders('+response.content.folders[i].id+');">'+response.content.folders[i].foldername+"</li>";
                }
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].filename;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                    } else {
                        content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                    }
                    content+="</li>";
                }
                
                content += "</ul>";
            }
            $("#browse-folders").html(content);
        }
    }
    var str = ajaxpath+"?action=browse-folders&id="+folderid;
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get artists
function getArtists(artistname) {
    $("#browse-artists").html(loading);
    artistname = typeof artistname !== 'undefined' ? artistname : "ROOT";
    currentArtist = artistname;
    $.post(ajaxpath+"?action=browse-artists", {name: artistname}, function(result,status){
        if(status=="success") {
            
            var response = JSON.parse(result);
            var content = "";
            if(response.status!="success" || response.action!="browse-artists") {
                content="<?php echo $translation["error"] ?>";
            } else {
        
                if(response.content.name!="ROOT") content += '<span class="current">'+response.content.name+"</span>";
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getArtists(\'ROOT\');">('+"<?php echo $translation["back"] ?>"+')</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.artists.length;i++) {
                        content += '<li class="artist" onclick="javascript:getArtists(\''+response.content.artists[i].artist+'\');">'+response.content.artists[i].artist+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                        } else {
                            content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                        }
                        content+="</li>";
                    }
                }
                content += "</ul>";
            }
            $("#browse-artists").html(content);         
        }
    });
}

//get albums
function getAlbums(albumname) {
    $("#browse-albums").html(loading);
    albumname = typeof albumname !== 'undefined' ? albumname : "ROOT";
    currentAlbum = albumname;
    $.post(ajaxpath+"?action=browse-albums", {name: albumname}, function(result,status){
        if(status=="success") {
            
            var response = JSON.parse(result);
            var content = "";
            if(response.status!="success" || response.action!="browse-albums") {
                content="<?php echo $translation["error"] ?>";
            } else {
        
                if(response.content.name!="ROOT") content += '<span class="current">'+response.content.name+"</span>";
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getAlbums(\'ROOT\');">('+"<?php echo $translation["back"] ?>"+')</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.albums.length;i++) {
                        content += '<li class="album" onclick="javascript:getAlbums(\''+response.content.albums[i].album+'\');">'+response.content.albums[i].album+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                        } else {
                            content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                        }
                        content+="</li>";
                    }
                }
                content += "</ul>";
            }
            $("#browse-albums").html(content);         
        }
    });
}

//get playlists
function getPlaylists(name) {
    $("#browse-playlists").html(loading);
    name = typeof name !== 'undefined' ? name : "ROOT";
    currentPlaylist = name;
    $.post(ajaxpath+"?action=browse-playlists", {name: name}, function(result,status){
        if(status=="success") {
            
            var response = JSON.parse(result);
            var content = "";
            if(response.status!="success" || response.action!="browse-playlists") {
                content="<?php echo $translation["error"] ?>";
            } else {
        
                if(response.content.name!="ROOT") {
                    content += '<span class="current">'+response.content.name+"</span> ";
                    content += '<img class="download" src="gfx/download.png" alt="'+"<?php echo $translation["download"] ?>"+'" onclick="javascript:doDownloadPlaylist(\''+response.content.name+'\');">';
                }
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getPlaylists(\'ROOT\');">('+"<?php echo $translation["back"] ?>"+')</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.playlists.length;i++) {
                        content += '<li class="playlist" onclick="javascript:getPlaylists(\''+response.content.playlists[i].playlistname+'\');">'+response.content.playlists[i].playlistname+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                        } else {
                            content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                        }
                        content+="</li>";
                    }
                }
                content += "</ul>";
            }
            $("#browse-playlists").html(content);         
        }
    });
}

//get files that often accour in playlists
function getOftenPlaylists() {
    $("#browse-often-playlists").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="browse-often-playlists") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += "<ol>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].count+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                    } else {
                        content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                    }
                    content+="</li>";
                }
                
                content += "</ol>";
            }
            $("#browse-often-playlists").html(content);
        }
    }
    var str = ajaxpath+"?action=browse-often-playlists";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get files that often accour in votes
function getOftenVotes() {
    $("#browse-often-votes").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="browse-often-votes") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += "<ol>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].count+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                    } else {
                        content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                    }
                    content+="</li>";
                }
                
                content += "</ol>";
            }
            $("#browse-often-votes").html(content);
        }
    }
    var str = ajaxpath+"?action=browse-often-votes";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get files that were played last
function getPlaylog() {
    $("#browse-playlog").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="browse-playlog") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += "<ul>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+formatMinutes(response.content.files[i].date)+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                    } else {
                        content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                    }
                    content+="</li>";
                }
                content += "</ul>";
            }
            $("#browse-playlog").html(content);
        }
    }
    var str = ajaxpath+"?action=browse-playlog";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//button to vote for skip current song
function getVoteskip() {
    $("#vote-skip").html(loading);

    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="vote-skip-check") {
                content="<?php echo $translation["error"] ?>";
            } else {
                if(response.content==0) {
                    content+='<img class="votecircle" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVoteSkip();">';
                } 
                if(response.content==1) {
                    content+='<img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'">';
                }
                if(response.content==2) {
                    content+='<img class="votecircle" src="gfx/vote_disabled.png" alt="'+"<?php echo $translation["notpossible"] ?>"+'">';
                }
            }
            $("#vote-skip").html(content);
        }
    }
    var str = ajaxpath+"?action=vote-skip-check";
    xhttp.open("GET", str, true);
    xhttp.send();
}

function getUploadForm() {
    if(uploadsenabled) {
        var content = "<?php echo $translation["onlymp3"] ?>"+
        '<form enctype="multipart/form-data" action="ajax.php?action=upload-file" method="post">'+
        '<input type="hidden" name="max_file_size" value="'+maxsize+'">'+
        '<input type="hidden" name="abgeschickt" value="ja">'+"<?php echo $translation["selectfile"] ?>"+
        ': <input name="thefile[]" type="file" multiple="multiple" style="border: 1px solid #555;"><br />'+
        '<input type="submit" value="'+"<?php echo $translation["send"] ?>"+'">'+
        '<!--<input name="abbrechen" type="button" value="'+"<?php echo $translation["cancel"] ?>"+'" id="abbrechen"><br />'+
        '<progress max="1" value="0" id="fortschritt"></progress>'+
        '<p id="fortschritt_txt"></p>-->'+
        '</form>';
    } else {
        var content = 'File uploads are dissabled on this system!';
    }
    $("#upload-file").html(content);
}

function getDownloads() {
    $("#download-file").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="download-file") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += "<h2>"+"<?php echo $translation["currentsong"] ?>"+"</h2>";
                content += "<ul>";
                for(var i=0;i<response.content.a.length;i++) {
                    content += '<li class="file">'+response.content.a[i].artist+": "+response.content.a[i].title;
                    content+=' <img class="download" src="gfx/download.png" alt="'+"<?php echo $translation["download"] ?>"+'" onclick="javascript:doDownload('+response.content.a[i].id+');"></li>';
                    content+="</li>";
                }
                content += "</ul>";
                
                content += "<h2>"+"<?php echo $translation["myvotedsongs"] ?>"+"</h2>";
                content += "<ul>";
                for(var i=0;i<response.content.b.length;i++) {
                    content += '<li class="file">'+response.content.b[i].artist+": "+response.content.b[i].title;
                    content+=' <img class="download" src="gfx/download.png" alt="'+"<?php echo $translation["download"] ?>"+'" onclick="javascript:doDownload('+response.content.b[i].id+');"></li>';
                    content+="</li>";
                }
                content += "</ul>";
                
                
                content += "<h2>"+"<?php echo $translation["highscore"] ?>"+"</h2>";
                content += "<ul>";
                for(var i=0;i<response.content.c.length;i++) {
                    content += '<li class="file">'+response.content.c[i].artist+": "+response.content.c[i].title;
                    content+=' <img class="download" src="gfx/download.png" alt="'+"<?php echo $translation["download"] ?>"+'" onclick="javascript:doDownload('+response.content.c[i].id+');"></li>';
                    content+="</li>";
                }
                content += "</ul>";
                
                
                content += "<h2>"+"<?php echo $translation["lastplayedsongs"] ?>"+"</h2>";
                content += "<ul>";
                for(var i=0;i<response.content.d.length;i++) {
                    content += '<li class="file">'+response.content.d[i].artist+": "+response.content.d[i].title;
                    content+=' <img class="download" src="gfx/download.png" alt="'+"<?php echo $translation["download"] ?>"+'" onclick="javascript:doDownload('+response.content.d[i].id+');"></li>';
                    content+="</li>";
                }
                content += "</ul>";
            }
            $("#download-file").html(content);
        }
    }
    var str = ajaxpath+"?action=download-file";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//get files that were played often
function getOftenPlayed() {
    $("#browse-often-played").html(loading);
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var response = JSON.parse(xhttp.responseText);
            var content = "";
            if(response.status!="success" || response.action!="browse-often-played") {
                content="<?php echo $translation["error"] ?>";
            } else {
                content += "<ol>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].count+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="'+"<?php echo $translation["alreadyvoted"] ?>"+'"></li>';
                    } else {
                        content+=' <img class="votecircle votecircle-id-'+response.content.files[i].id+'" src="gfx/circle.png" alt="'+"<?php echo $translation["dovote"] ?>"+'" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
                    }
                    content+="</li>";
                }
                
                content += "</ol>";
            }
            $("#browse-often-played").html(content);
        }
    }
    var str = ajaxpath+"?action=browse-often-played";
    xhttp.open("GET", str, true);
    xhttp.send();
}


