$(document).ready(function() {
$(".filterSelect").hide();
$( ".zoneFilter" ).change(function() {
  if($(this).val() == "Date") {
  $("#dateFilter").show();
  $("#monthFilter").hide();
  } else {
  $("#dateFilter").hide();
  $("#monthFilter").show();
  }
});

$( ".zoneFilter2" ).change(function() {
  if($(this).val() == "Date") {
  $("#dateFilter2").show();
  $("#monthFilter2").hide();
  } else {
  $("#dateFilter2").hide();
  $("#monthFilter2").show();
  }
});

$( "#zone1DatePicker" ).datepicker({
	showOn: "button",
	buttonImage: "images/calendar.png",
	changeMonth: true,
	changeYear: true,
	buttonImageOnly: true,
	buttonText: "Select date",
	dateFormat: 'yy-mm-dd',
	onSelect: function(date, instance) {
		$.getJSON("./js/plugins/chart/Chart_db.php", { val: date, device: '1'}, function(db){
		if(db != ""  || typeof db !== 'undefined') {
		$("#zone1alert").hide();
		$("#zone1").show();
		var values = [];
		var dataset = [];
		bars = db.split(";");
		for(var i = 0; i < bars.length; i++) values.push(bars[i].split("-"));  
		for(var i = 0; i < values.length-1; i++) {
			dataset[values[i][0]] = values[i][1];
		}
			var data = {
					labels: ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23"],
					datasets: [
						{
							fillColor: "rgba(151,187,205,0.5)",
							strokeColor: "rgba(151,187,205,0.8)",
							highlightFill: "rgba(151,187,205,0.75)",
							highlightStroke: "rgba(151,187,205,1)",
							data: dataset
						}
					]
				};
				var options = {
					//Boolean - Whether the scale should start at zero, or an order of magnitude down from the lowest value
					scaleBeginAtZero : true,

					//Boolean - Whether grid lines are shown across the chart
					scaleShowGridLines : true,

					//String - Colour of the grid lines
					scaleGridLineColor : "rgba(0,0,0,.05)",

					//Number - Width of the grid lines
					scaleGridLineWidth : 1,

					//Boolean - If there is a stroke on each bar
					barShowStroke : true,

					//Number - Pixel width of the bar stroke
					barStrokeWidth : 2,

					//Number - Spacing between each of the X value sets
					barValueSpacing : 5,

					//Number - Spacing between data sets within X values
					barDatasetSpacing : 1,
					
					// Boolean - whether or not the chart should be responsive and resize when the browser does.

					responsive: true,

					// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container

					maintainAspectRatio: false,
					
					scaleOverride: true, 
					
					scaleStartValue: 0, 
					
					scaleStepWidth: 1, 
					
					scaleSteps: 5

				   
				};

				var zone1 = document.getElementById("zone1");
				var sz1 = zone1.getContext("2d");
				sz1.canvas.originalwidth = sz1.canvas.width;
				sz1.canvas.originalheight = sz1.canvas.height;
				sz1.clearRect(0, 0, sz1.canvas.width, sz1.canvas.height);
				new Chart(sz1).Bar(data, options);
			} else {
				$("#zone1alert").show();
				$("#zone1").hide();
			}
		});
     }
});

$( "#zone1MonthPicker" ).datepicker({
	showOn: "button",
	buttonImage: "images/calendar.png",
	changeMonth: true,
	changeYear: true,
	buttonImageOnly: true,
	buttonText: "Select date",
	dateFormat: 'yy-mm',
	onSelect: function(date, instance) {
			$.getJSON("./js/plugins/chart/Chart_db.php", { month: date, device: '1'}, function(db){
			if(db) {
			$("#zone1alert").hide();
			$("#zone1").show();
			mon = date.split("-");
			var days31 = [ 1, 3, 4, 5, 7, 8, 10, 12 ];
			var days30 = [ 4, 6, 9, 11 ];
			console.log(days31.indexOf(parseFloat(mon[1])));
			console.log(days30.indexOf(parseFloat(mon[1])));
			if(days31.indexOf(parseFloat(mon[1])) != -1) count = 31;
			else if(days30.indexOf(parseFloat(mon[1])) != -1) count = 30;
			else count = 28;
			
			var values = [];
			dataset = [];
			monthLabel = [];
			bars = db.split(";");
			for(var i = 0; i < bars.length; i++) values.push(bars[i].split("-"));  
			for(var i = 0; i < values.length-1; i++) {
				dataset[values[i][0]] = 0;
			}
			for(var i = 0; i < values.length-1; i++) {
				dataset[values[i][0]] += parseFloat(values[i][1]);
			}
			
			for(var i = 0; i < count; i++) {
				monthLabel[i] = i+1;
			}
			
			var data = {
					labels: monthLabel,
					datasets: [
						{
							fillColor: "rgba(151,187,205,0.5)",
							strokeColor: "rgba(151,187,205,0.8)",
							highlightFill: "rgba(151,187,205,0.75)",
							highlightStroke: "rgba(151,187,205,1)",
							data: dataset
						}
					]
				};
				var options = {
					//Boolean - Whether the scale should start at zero, or an order of magnitude down from the lowest value
					scaleBeginAtZero : true,

					//Boolean - Whether grid lines are shown across the chart
					scaleShowGridLines : true,

					//String - Colour of the grid lines
					scaleGridLineColor : "rgba(0,0,0,.05)",

					//Number - Width of the grid lines
					scaleGridLineWidth : 1,

					//Boolean - If there is a stroke on each bar
					barShowStroke : true,

					//Number - Pixel width of the bar stroke
					barStrokeWidth : 2,

					//Number - Spacing between each of the X value sets
					barValueSpacing : 5,

					//Number - Spacing between data sets within X values
					barDatasetSpacing : 1,
					
					// Boolean - whether or not the chart should be responsive and resize when the browser does.

					responsive: true,

					// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container

					maintainAspectRatio: false,
					
					scaleOverride: true, 
					
					scaleStartValue: 0, 
					
					scaleStepWidth: 1, 
					
					scaleSteps: 20

				   
				};

				var zone1 = document.getElementById("zone1");
				var sz1 = zone1.getContext("2d");
				sz1.canvas.originalwidth = sz1.canvas.width;
				sz1.canvas.originalheight = sz1.canvas.height;
				sz1.clearRect(0, 0, sz1.canvas.width, sz1.canvas.height);
				new Chart(sz1).Bar(data, options);
			} else {
				$("#zone1alert").show();
				$("#zone1").hide();
			}

		});
     }
});

$( "#zone2DatePicker" ).datepicker({
	showOn: "button",
	buttonImage: "images/calendar.png",
	changeMonth: true,
	changeYear: true,
	buttonImageOnly: true,
	buttonText: "Select date",
	dateFormat: 'yy-mm-dd',
	onSelect: function(date, instance) {
		$.getJSON("./js/plugins/chart/Chart_db.php", { val: date, device: '2'}, function(db){
		if(db) {
		$("#zone2alert").hide();
		$("#zone2").show();
		var values = [];
		var dataset = [];
		bars = db.split(";");
		for(var i = 0; i < bars.length; i++) values.push(bars[i].split("-"));  
		for(var i = 0; i < values.length-1; i++) {
			dataset[values[i][0]] = values[i][1];
		}
			var data = {
					labels: ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23"],
					datasets: [
						{
							fillColor: "rgba(151,187,205,0.5)",
							strokeColor: "rgba(151,187,205,0.8)",
							highlightFill: "rgba(151,187,205,0.75)",
							highlightStroke: "rgba(151,187,205,1)",
							data: dataset
						}
					]
				};
				var options = {
					//Boolean - Whether the scale should start at zero, or an order of magnitude down from the lowest value
					scaleBeginAtZero : true,

					//Boolean - Whether grid lines are shown across the chart
					scaleShowGridLines : true,

					//String - Colour of the grid lines
					scaleGridLineColor : "rgba(0,0,0,.05)",

					//Number - Width of the grid lines
					scaleGridLineWidth : 1,

					//Boolean - If there is a stroke on each bar
					barShowStroke : true,

					//Number - Pixel width of the bar stroke
					barStrokeWidth : 2,

					//Number - Spacing between each of the X value sets
					barValueSpacing : 5,

					//Number - Spacing between data sets within X values
					barDatasetSpacing : 1,
					
					// Boolean - whether or not the chart should be responsive and resize when the browser does.

					responsive: true,

					// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container

					maintainAspectRatio: false,
					
					scaleOverride: true, 
					
					scaleStartValue: 0, 
					
					scaleStepWidth: 1, 
					
					scaleSteps: 5

				   
				};

				var zone1 = document.getElementById("zone2");
				var sz1 = zone1.getContext("2d");
				sz1.canvas.originalwidth = sz1.canvas.width;
				sz1.canvas.originalheight = sz1.canvas.height;
				new Chart(sz1).Bar(data, options);
			} else {
				$("#zone1alert").show();
				$("#zone1").hide();
			}
		});
     }
});

$( "#zone2MonthPicker" ).datepicker({
	showOn: "button",
	buttonImage: "images/calendar.png",
	changeMonth: true,
	changeYear: true,
	buttonImageOnly: true,
	buttonText: "Select date",
	dateFormat: 'yy-mm',
	onSelect: function(date, instance) {
			$.getJSON("./js/plugins/chart/Chart_db.php", { month: date, device: '2'}, function(db){
			if(db) {
			$("#zone2alert").hide();
			$("#zone2").show();
			mon = date.split("-");
			var days31 = [ 1, 3, 4, 5, 7, 8, 10, 12 ];
			var days30 = [ 4, 6, 9, 11 ];
			console.log(days31.indexOf(parseFloat(mon[1])));
			console.log(days30.indexOf(parseFloat(mon[1])));
			if(days31.indexOf(parseFloat(mon[1])) != -1) count = 31;
			else if(days30.indexOf(parseFloat(mon[1])) != -1) count = 30;
			else count = 28;
			
			var values = [];
			dataset = [];
			monthLabel = [];
			bars = db.split(";");
			for(var i = 0; i < bars.length; i++) values.push(bars[i].split("-"));  
			for(var i = 0; i < values.length-1; i++) {
				dataset[values[i][0]] = 0;
			}
			for(var i = 0; i < values.length-1; i++) {
				dataset[values[i][0]] += parseFloat(values[i][1]);
			}
			
			for(var i = 0; i < count; i++) {
				monthLabel[i] = i+1;
			}
			
			var data = {
					labels: monthLabel,
					datasets: [
						{
							fillColor: "rgba(151,187,205,0.5)",
							strokeColor: "rgba(151,187,205,0.8)",
							highlightFill: "rgba(151,187,205,0.75)",
							highlightStroke: "rgba(151,187,205,1)",
							data: dataset
						}
					]
				};
				var options = {
					//Boolean - Whether the scale should start at zero, or an order of magnitude down from the lowest value
					scaleBeginAtZero : true,

					//Boolean - Whether grid lines are shown across the chart
					scaleShowGridLines : true,

					//String - Colour of the grid lines
					scaleGridLineColor : "rgba(0,0,0,.05)",

					//Number - Width of the grid lines
					scaleGridLineWidth : 1,

					//Boolean - If there is a stroke on each bar
					barShowStroke : true,

					//Number - Pixel width of the bar stroke
					barStrokeWidth : 2,

					//Number - Spacing between each of the X value sets
					barValueSpacing : 5,

					//Number - Spacing between data sets within X values
					barDatasetSpacing : 1,
					
					// Boolean - whether or not the chart should be responsive and resize when the browser does.

					responsive: true,

					// Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container

					maintainAspectRatio: false,
					
					scaleOverride: true, 
					
					scaleStartValue: 0, 
					
					scaleStepWidth: 1, 
					
					scaleSteps: 20

				   
				};

				var zone1 = document.getElementById("zone2");
				var sz1 = zone1.getContext("2d");
				sz1.canvas.originalwidth = sz1.canvas.width;
				sz1.canvas.originalheight = sz1.canvas.height;
				sz1.clearRect(0, 0, sz1.canvas.width, sz1.canvas.height);
				new Chart(sz1).Bar(data, options);
			} else {
				$("#zone2alert").show();
				$("#zone2").hide();
			}

		});
     }
});



});
