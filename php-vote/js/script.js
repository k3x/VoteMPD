var ajaxpath = window.location.href+"php/ajax.php";

$(function() {
    $( "#auswahl" ).accordion({active: 1,heightStyle: "content",collapsible: true});
    getCurrent();
    getNext();
    getHigh();
    getMy();
});

function getCurrent() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="mpdcurrent") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content==null) {
                    content="leer";
                } else {
                    content=antwort.content.artist+": "+antwort.content.title+" "+antwort.content.length+"s";
                }
            }
            $("#head").html(content);
        }
    }
    var str = ajaxpath+"?action=mpdcurrent";
    xhttp.open("GET", str, true);
    xhttp.send();
}

//todo
function getNext() {
    
}

function doVote(id) {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            var content = "";
            if(antwort.status!="success" || antwort.action!="vote") {
                content="Es trat ein Fehler auf!";
            } else {
                content="Erfolg!";
            }
            alert(content);
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
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"byte "+entry.date+")</li>";
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

//todo show votebutton only if boolean is false
function doSearch() {
    var text = $("#search-text").val();

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
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"Byte) <button onclick=\"javascript:doVote("+entry.id+");\">Abstimmen</button></li>";
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
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"Byte "+entry.anzahl+" Stimmen)</li>";
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
