<?php



	
	function getAttempts($userid) {
		$getPass = mysql_query("SELECT attempts FROM `users` WHERE `uName` = '".$userid."'");
		$result = mysql_fetch_row($getPass, 0) or trigger_error(mysql_error().$getPass);
		return $result[0];
	}
	
	function getEmail($userid) {
		$getPass = mysql_query("SELECT email FROM `users` WHERE `uName` = '".$userid."'");
		$result = mysql_fetch_row($getPass, 0) or trigger_error(mysql_error().$getPass);
		return $result[0];
	}
	
	function updateAttempt($userid, $attempt) {
		mysql_query("UPDATE `users` SET `attempts`='$attempt' WHERE `uName` = '".$userid."'");
	}
	
	function changePass($userid, $pwd) {
		mysql_query("UPDATE `users` SET `pass`='$pwd' WHERE `uName` = '".$userid."'");
	}
	
	function createUser($user, $pwd, $mail) {
		mysql_query("INSERT INTO `users` ( `uName`, `pass`, `email`) VALUES ( '$user', '$pwd', '$mail')");
	}
	
	function getDailyUsage($date, $device) {
		$graph = '';
		$getVal = mysql_query("SELECT Start_Time, Value FROM `sprinkler_usage` WHERE `Date` = '".$date."' AND `Zone`  = ".$device);
		mysql_data_seek($getVal, 0);
		while ($row = mysql_fetch_assoc($getVal)) {
		  $graph = $graph.$row['Start_Time']."-".$row['Value'].";";
		}
		return $graph;
	}
	
	function getMonthlyUsage($month, $year, $device) {
		$graph = '';
		$getVal = mysql_query("SELECT DAY(date) AS day, Value FROM `sprinkler_usage` WHERE MONTH(date) = ".$month." AND YEAR(date) = ".$year." AND `Zone`  = ".$device);
		mysql_data_seek($getVal, 0);
		while ($row = mysql_fetch_assoc($getVal)) {
		  $graph = $graph.$row['day']."-".$row['Value'].";";
		}
		return $graph;
	}
	
	function getStatusSprinkler() {
		$result = array();
		$getStat = mysql_query("SELECT Status FROM `sprinkler_controls`");
		mysql_data_seek($getStat, 0);
		while ($row = mysql_fetch_assoc($getStat)) {
		  array_push($result, $row['Status']);
		}
		return $result;
	}
	
	function getPresetSprinklerVal() {
		$result = array();
		$count = 0;
		$getStat = mysql_query("SELECT id, type, day, month, fromtime, totime, device FROM `sprinkler_preset`");
		mysql_data_seek($getStat, 0);
		while ($row = mysql_fetch_assoc($getStat)) {
		  array_push($result, $row['id']);
		  array_push($result, $row['type']);
		  array_push($result, $row['day']);
		  array_push($result, $row['month']);
		  array_push($result, $row['fromtime']);
		  array_push($result, $row['totime']);
		  array_push($result, $row['device']);
		  $count++;
		}
		array_push($result, $count);
		return $result;
	}
	
	function getPresetSprinkler() {
		$result = array();
		$getStat = mysql_query("SELECT PreFrom, PreTo FROM `sprinkler_controls`");
		mysql_data_seek($getStat, 0);
		while ($row = mysql_fetch_assoc($getStat)) {
		  array_push($result, $row['PreFrom']);
		  array_push($result, $row['PreTo']);
		}
		return $result;
	}
	
	function getStatusSecurity() {
		$getStat = mysql_query("SELECT Status FROM `alarm_controls`");
		$result = mysql_fetch_row($getStat, 0) or trigger_error(mysql_error().$getStat);
		return $result;
	}
	
	function getStatusThermostat() {
		$result = array();
		$fix = array();
		$getStat = mysql_query("SELECT Status, Temp, Fixed FROM `thermostat_controls`");
		mysql_data_seek($getStat, 0);
		while ($row = mysql_fetch_assoc($getStat)) {
		  array_push($result, $row['Status']);
		  array_push($result, $row['Temp']);
		  array_push($fix, $row['Fixed']);
		}
		$result = array_merge($result, $fix);
		return $result;
	}
	
	function getPresetThermostat() {
		$result = array();
		$fix = array();
		$getStat = mysql_query("SELECT PreFrom, PreTo, PreTemp, PreFixed FROM `thermostat_controls`");
		mysql_data_seek($getStat, 0);
		while ($row = mysql_fetch_assoc($getStat)) {
		  array_push($result, $row['PreFrom']);
		  array_push($result, $row['PreTo']);
		  array_push($result, $row['PreTemp']);
		  array_push($fix, $row['PreFixed']);
		}
		$result = array_merge($result, $fix);
		return $result;
	}
	
	function updateTemp($temperature, $status, $fix, $floor) {
		mysql_query("UPDATE `thermostat_controls` SET `Temp` = $temperature, `Status` = $status, `Fixed` = $fix WHERE `Floor` = $floor");
	}
	
	function updatePresetTemp($from, $to, $temperature, $fix, $floor) {
		mysql_query("UPDATE `thermostat_controls` SET `PreFrom` = '$from', `PreTo` = '$to', `PreTemp` = $temperature, `PreFixed` = $fix WHERE `Floor` = $floor");
	}
	
	function updateSecurity($status) {
		mysql_query("UPDATE `alarm_controls` SET `Status` = $status WHERE 1");
	}
	
	function updateSprinklers($status, $zone) {
		mysql_query("UPDATE `sprinkler_controls` SET `Status` = $status WHERE `Zone` = $zone");
	}
	
	/*function updatePresetSprinklers($from, $to, $zone) {
		mysql_query("UPDATE `sprinkler_controls` SET `PreFrom` = '$from', `PreTo` = '$to' WHERE `Zone` = $zone");
	}*/
	
	function setPresetSpringlerTime($type, $from, $to, $zone) {
		mysql_query("INSERT INTO `sprinkler_preset` ( `type`, `fromtime`, `totime`, `device`) VALUES ( '$type', '$from', '$to', '$zone')");
	}
	
	function setPresetSpringlerWeek($type, $week, $from, $to, $zone) {
		mysql_query("INSERT INTO `sprinkler_preset` ( `type`, `day`, `fromtime`, `totime`, `device`) VALUES ( '$type', '$week', '$from', '$to', '$zone')");
	}
	
	function setPresetSpringlerMonth($type, $mon, $from, $to, $zone) {
		mysql_query("INSERT INTO `sprinkler_preset` ( `type`, `month`, `fromtime`, `totime`, `device`) VALUES ( '$type', '$mon', '$from', '$to', '$zone')");
	}
	
	function updatePresetSpringler($id, $type, $week, $mon, $from, $to, $zone) {
		mysql_query("UPDATE `sprinkler_preset` SET `type`='$type',`day`='$week',`month`='$mon',`fromtime`='$from',`totime`='$to',`device`='$zone' WHERE `id`=$id");
	}
	
	function deletePresetSpringler($id) {
		mysql_query("DELETE FROM `sprinkler_preset` WHERE `id` = $id");
	}
?>