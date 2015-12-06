var ajaxpath = window.location.href+"ajax.php"; //absolute url to ajax.php
var ajaxpathrel = "ajax.php"; //relative path to ajax.php
var lastcurrent = null; //last fileinfos for currently played song
var tempposition = null; //interpolated position of song
var intervalfast = 500; //fast update interval (interpolate song position)
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
            activate: function( event, ui ) {loadTab();}
    });
    
    
    $("#search-text").val(""); //clear search input field
    getCurrent(); //update fileinfos for currently played song
    getNext(); //update fileinfos for next played song
    
    setInterval(function(){ intervalSlow(); }, 30000); //do slow update every 30s (not really needed, but for safety)
    setInterval(function(){ intervalMid(); }, 2000); //do mid update (current song changed?, paused? current position in song?)
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
        case(6): getPlaylists(currentPlaylist);break;
        case(7): getOftenPlaylists(); break;
        case(8): getOftenVotes();break;
        default: break;
    }
}

//do slow update ()
function intervalSlow() {
    getNext(); //update fileinfos for next played song
}

//do mid update
function intervalMid() {
    getCurrent(); //update fileinfos for currently played song
}

//do fast update (only local, interpolate song position)
function intervalFast() {
    if(tempposition==null || lastcurrent==null || lastcurrent.state!="play") return;
    tempposition += intervalfast/1000;
    var percent = 100*tempposition/lastcurrent.fileinfos.length
    if(percent>100) percent=100;
    $("#innerhead").css("background","linear-gradient(90deg, rgba(164,164,164,0.7) "+percent+"%, rgba(256,256,256,0.8) "+percent+"%)");
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
    return date.substring(11,16)+" Uhr";
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
                content="Es trat ein Fehler auf!";
                lastcurrent = null;
            } else {
                if(response.content.state!="stop") {
                    if(response.content.fileinfos==null) {
                        content="Error";
                        lastcurrent = null;
                    } else {
                        content=    response.content.fileinfos.artist+" - "+
                                    response.content.fileinfos.title;
                        percent = 100*response.content.time/response.content.fileinfos.length;
                        picture = response.content.fileinfos.picture;
                        if(lastcurrent==null || lastcurrent.fileinfos.id!=response.content.fileinfos.id) {
                            intervalSlow();
                        }
                        lastcurrent = response.content;
                        tempposition = parseFloat(parseInt(response.content.time));
                    }
                } else {
                    content="(keine Wiedergabe)";
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
                content="Es trat ein Fehler auf!";
            } else {
                if(response.content==null) {
                    content="Next: (keiner)";
                } else {
                    content="Next: "+response.content.artist+" - "+response.content.title+" "+formatLength(response.content.length);
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
                alert("Es trat ein Fehler auf!");
            } else {
                loadTab();
                getNext();
            }
        }
    }
    var str = ajaxpath+"?action=vote&id="+id;
    xhttp.open("GET", str, true);
    xhttp.send();
}


/*
-----------------------------------------------------------------------------------------
-----------Below this line are functions for every accordion-tab-------------------------
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
                content="Es trat ein Fehler auf!";
            } else {
                if(response.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+" "+formatDate(entry.date)+")</li>";
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
                content="Es trat ein Fehler auf!";
            } else {
                if(response.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        var st = "Stimmen";
                        if(entry.anzahl==1) st = "Stimme";
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+" "+entry.anzahl+" "+st+") ";
                        if(entry.alreadyVoted) {
                            content+='<img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                        } else {
                            content+='<img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+entry.id+');"></li>';
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
    var text = $("#search-text").val();
    if(text.length==0) return;
    if(text.length<3) {
        $("#search > ul").html("Bitte mindestens 3 Zeichen eingeben!");  
        return;
    }
    $("#search > ul").html(loading);

    $.post(ajaxpath+"?action=search", {keyword: text}, function(result,status){
        if(status=="success") {
            var response = JSON.parse(result);
            var content = "";
            
            if(response.status!="success" || response.action!="search") {
                content="Es trat ein Fehler auf!";
            } else {
                if(response.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    for (index = 0; index < response.content.length; index++) {
                        entry = response.content[index];
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+') ';
                        if(entry.alreadyVoted) {
                            content+='<img src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                        } else {
                            content+='<img src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+entry.id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
                content += '<span class="current">'+response.content.path+"</span>";
                content += "<ul>";
                
                if(response.content.this!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getFolders(-1);">(root)</li>';
                    content += '<li class="goup" onclick="javascript:getFolders('+response.content.this.parentid+');">(..)</li>';
                }
                
                for(var i=0;i<response.content.folders.length;i++) {
                    content += '<li class="folder" onclick="javascript:getFolders('+response.content.folders[i].id+');">'+response.content.folders[i].foldername+"</li>";
                }
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].filename;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                    } else {
                        content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
        
                if(response.content.name!="ROOT") content += '<span class="current">'+response.content.name+"</span>";
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getArtists(\'ROOT\');">(root)</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.artists.length;i++) {
                        content += '<li class="artist" onclick="javascript:getArtists(\''+response.content.artists[i].artist+'\');">'+response.content.artists[i].artist+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                        } else {
                            content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
        
                if(response.content.name!="ROOT") content += '<span class="current">'+response.content.name+"</span>";
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getAlbums(\'ROOT\');">(root)</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.albums.length;i++) {
                        content += '<li class="album" onclick="javascript:getAlbums(\''+response.content.albums[i].album+'\');">'+response.content.albums[i].album+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                        } else {
                            content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
        
                if(response.content.name!="ROOT") content += '<span class="current">'+response.content.name+"</span>";
                content += "<ul>";
                
                if(response.content.name!="ROOT") {
                    content += '<li class="goup" onclick="javascript:getPlaylists(\'ROOT\');">(root)</li>';
                }
                
                if(response.content.name=="ROOT") {
                    for(var i=0;i<response.content.playlists.length;i++) {
                        content += '<li class="playlist" onclick="javascript:getPlaylists(\''+response.content.playlists[i].playlistname+'\');">'+response.content.playlists[i].playlistname+"</li>";
                    }
                } else {
                    for(var i=0;i<response.content.files.length;i++) {
                        content += '<li class="file">'+response.content.files[i].artist+": "+response.content.files[i].title;
                        
                        if(response.content.files[i].alreadyVoted) {
                            content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                        } else {
                            content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
                content += "<ol>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].count+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                    } else {
                        content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
                content="Es trat ein Fehler auf!";
            } else {
                content += "<ol>";
                for(var i=0;i<response.content.files.length;i++) {
                    content += '<li class="file">'+response.content.files[i].count+": "+response.content.files[i].artist+": "+response.content.files[i].title;
                    
                    if(response.content.files[i].alreadyVoted) {
                        content+=' <img class="votecircle" src="gfx/voted.png" alt="Bereits abgestimmt"></li>';
                    } else {
                        content+=' <img class="votecircle" src="gfx/circle.png" alt="Abstimmen" onclick="javascript:doVote('+response.content.files[i].id+');"></li>';
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
