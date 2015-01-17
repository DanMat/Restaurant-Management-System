$(document).ready(function() {



function countDown(count) {
    var i = count;
	
    myinterval = setInterval(function() {
        document.getElementById("countdown").innerHTML = i;
        if (i === 0) {
            clearInterval(myinterval);
            alert('Calling 911') // call your function
        }
		else if($("#DisableCall").attr("value") == "false") clearInterval(myinterval);
        else i--;
    }, 1000);
}

function countDownT(count) {
    var i = count;
	
    myinterval = setInterval(function() {
        document.getElementById("countdownT").innerHTML = i;
        if (i === 0) {
            clearInterval(myinterval);
            alert('Calling 911') // call your function
        }
		else if($("#DisableCall").attr("value") == "false") clearInterval(myinterval);
        else i--;
    }, 1000);
}

$( "#SaveStatus").hide();
$("#BreachSave").click(function(){ 
	if($("#botDoor").is(':checked')) floo1Door = 1; else floo1Door = 0;
	if($("#botWin").is(':checked')) floo1Win = 1; else floo1Win = 0;
	if($("#topWin").is(':checked')) floo2Win = 1; else floo2Win = 0;
	$.post("simulation.php",{ f1Door:floo1Door, f1Win: floo1Win, f2Win: floo2Win }, function(){
		$( "#SaveStatus span" ).empty();
		setTimeout(
		  function() 
		  {
			$( "#SaveStatus span" ).html("<strong>Success!!!</strong> Breach updated.");
		  }, 500);
		
		$( "#SaveStatus").show();
	});
});

$("#HazardSave").click(function(){ 
	if($("#Fire").is(':checked')) fire = 1; else fire = 0;
	if($("#Gas").is(':checked')) gas = 1; else gas = 0;
	$.post("simulation.php",{ f: fire, g: gas }, function(){
		$( "#SaveStatus span" ).empty();
		setTimeout(
		  function() 
		  {
			$( "#SaveStatus span" ).html("<strong>Success!!!</strong> Hazard updated.");
		  }, 500);
		
		$( "#SaveStatus").show();
	});
});

$("#alertSecurity").click(function(){ 
	alertTime = $("#BreachTime").val();
	var alertTime = new Date(alertTime);
	var timeNow = new Date();
	var sec = (timeNow - alertTime)/1000;
	if(Math.round(90-sec) > 0) {
		countDown(Math.round(90-sec));
	} else $("#DisableCall").text("911 Called");
});

$("#DisableCall").click(function(){ 
	$("#DisableCall").attr("value", "false");
	$("#DisableCall").text("911 Call Disabled");
	$.post("simulation.php",{ dis911: 1 }, function(){});
});

$("#alertSmoke").click(function(){ 
	alertTime = $("#SmokeTime").val();
	var alertTime = new Date(alertTime);
	var timeNow = new Date();
	var sec = (timeNow - alertTime)/1000;
	if(Math.round(90-sec) > 0) {
		countDownT(Math.round(90-sec));
	} else $("#SmokeCall").text("Authorities Called");
});

$("#SmokeCall").click(function(){ 
	$("#SmokeCall").attr("value", "false");
	$("#SmokeCall").text("Authorities Call Disabled");
	$.post("simulation.php",{ disSmoke: 1 }, function(){});
});

});