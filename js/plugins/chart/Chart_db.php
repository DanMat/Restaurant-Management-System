<?php
include ("../../../includes/settings.inc.php");        // database settings
include ("../../../includes/connectdb.inc.php"); 
include ("../../../includes/sql.php"); 

if(isset($_GET['val'])) {
$usage = getDailyUsage($_GET['val'], $_GET['device']);
echo json_encode($usage);
}

if(isset($_GET['month'])) {
$time = explode("-",$_GET['month']);
$usage = getMonthlyUsage($time[1], $time[0], $_GET['device']);
echo json_encode($usage);
}

/*
$graph = '';
$month = 10;
$year = 2014;
$device = 1;
$getVal = mysql_query("SELECT DAY(date) AS day, Value FROM `usage` WHERE MONTH(date) = ".$month." AND YEAR(date) = ".$year." AND `Device`  = ".$device);
mysql_data_seek($getVal, 0);
while ($row = mysql_fetch_assoc($getVal)) {
  $graph = $graph.$row['day']."-".$row['Value'].";";
}
echo $graph;*/
?>