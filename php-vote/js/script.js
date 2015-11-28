var ajaxpath = window.location.href+"php/ajax.php";
console.log(ajaxpath);

$(function() {
    $( "#auswahl" ).accordion({active: 2,heightStyle: "content",collapsible: true});
    getCurrent();
    getNext();
    getHigh();
    getMy();
});

function getMy() {
    var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (xhttp.readyState == 4 && xhttp.status == 200) {
            var antwort = JSON.parse(xhttp.responseText);
            console.log(antwort.content);
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
                        console.log(entry);
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"byte "+entry.date+")</li>";
                    }
                    console.log(content);
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

    $.post(ajaxpath+"?action=search", {keyword: text}, function(result,status){
        if(status=="success") {
            console.log(result);
            var antwort = JSON.parse(result);
            console.log(antwort.content);
            var content = "";
            
            
            if(antwort.status!="success" || antwort.action!="search") {
                content="Es trat ein Fehler auf!";
            } else {
                if(antwort.content.length==0) {
                    content="Keine Elemente!";
                } else {
                    content+="<ol>";
                    
                    for (index = 0; index < antwort.content.length; index++) {
                        entry = antwort.content[index];
                        console.log(entry);
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"Byte)</li>";
                    }
                    console.log(content);
                    content+="</ol>";
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
            console.log(antwort.content);
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
                        console.log(entry);
                        content+="<li>"+entry.artist+": "+entry.title+" ("+entry.length+"s "+entry.size+"Byte "+entry.anzahl+" Stimmen)</li>";
                    }
                    console.log(content);
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

//todo
function getCurrent() {
    
}

//todo
function getNext() {
    
}