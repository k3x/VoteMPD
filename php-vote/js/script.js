var ajaxpath = window.location.href+"php/ajax.php";
var lastcurrent = null;
var tempposition = null;
var intervalfast = 500;

$(function() {
    $( "#auswahl" ).accordion({active: 1,heightStyle: "content",collapsible: true});
    getCurrent();
    getNext();
    getHigh();
    getMy();
    
    setInterval(function(){ intervalSlow(); }, 15000);
    setInterval(function(){ intervalMid(); }, 2000);
    setInterval(function(){ intervalFast(); }, intervalfast);
});

function intervalSlow() {
    getNext();
    getHigh();
    getMy();
}

function intervalMid() {
    getCurrent();
}

function intervalFast() {
    if(tempposition==null || lastcurrent==null || lastcurrent.state!="play") return;
    tempposition += intervalfast/1000;
    var percent = 100*tempposition/lastcurrent.fileinfos.length
    $("#innerhead").css("background","linear-gradient(90deg, rgba(164,164,164,0.7) "+percent+"%, rgba(256,256,256,0.8) "+percent+"%)");
}

//from http://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
function formatBytes(bytes) {
   if(bytes == 0) return '0 Byte';
   var k = 1024;
   var sizes = ['b', 'kb', 'mb', 'gb', 'tb'];
   var i = Math.floor(Math.log(bytes) / Math.log(k));
   return Math.floor((bytes / Math.pow(k, i))) + sizes[i];
}

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

function formatDate(date) {
    return date.substring(11,16)+" Uhr";
}

function getCurrent() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            var percent = 0;
            var picture = null;
            if(antwort.status!="success" || antwort.action!="mpdcurrent") {
                content="Es trat ein Fehler auf!";
                lastcurrent = null;
            } else {
                if(antwort.content.state!="stop") {
                    if(antwort.content.fileinfos==null) {
                        content="Error";
                        lastcurrent = null;
                    } else {
                        content=    antwort.content.fileinfos.artist+" - "+
                                    antwort.content.fileinfos.title;
                        percent = 100*antwort.content.time/antwort.content.fileinfos.length;
                        picture = antwort.content.fileinfos.picture;
                        if(lastcurrent==null || lastcurrent.fileinfos.id!=antwort.content.fileinfos.id) {
                            intervalSlow();
                        }
                        lastcurrent = antwort.content;
                        tempposition = parseFloat(parseInt(antwort.content.time));
                    }
                }
            }
            if(picture==true) {
                $("#head").css("background-image","url(php/ajax.php?action=getfolderpic&id="+antwort.content.fileinfos.folderid+")");
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

function getNext() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="getnextsong") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content==null) {
                    content="No next Song!";
                } else {
                    content="Next: "+antwort.content.artist+" - "+antwort.content.title+" "+formatLength(antwort.content.length);
                }
            }
            $("#next").html(content);
        }
    }
    var str = ajaxpath+"?action=getnextsong";
    xhttp.open("GET", str, true);
    xhttp.send();
}

function doVote(id) {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="vote") {
                alert("Es trat ein Fehler auf!");
            } else {
                doSearch();
                getNext();
                getHigh();
                getMy();
            }
        }
    }
    var str = ajaxpath+"?action=vote&id="+id;
    xhttp.open("GET", str, true);
    xhttp.send();
}

function getMy() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="getmyvotes") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < antwort.content.length; index++) {
                        entry = antwort.content[index];
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

function doSearch() {
    var text = $("#search-text").val();
    if(text.length<3) {
        $("#search > ul").html("Bitte mindestens 3 Zeichen eingeben!");  
        return;
    }

    $.post(ajaxpath+"?action=search", {keyword: text}, function(result,status){
        if(status=="success") {
            var antwort = JSON.parse(result);
            var content = "";
            
            if(antwort.status!="success" || antwort.action!="search") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    for (index = 0; index < antwort.content.length; index++) {
                        entry = antwort.content[index];
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

function getHigh() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="showhighscore") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < antwort.content.length; index++) {
                        entry = antwort.content[index];
                        content+="<li>"+entry.artist+": "+entry.title+" ("+formatLength(entry.length)+" "+formatBytes(entry.size)+" "+entry.anzahl+" Stimmen)</li>";
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
