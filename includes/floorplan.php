<?php
class Table
{
  private $waiterId;
  private $tabList;
 
  public function __construct()
  {
      $id = mysql_query("SELECT id FROM `employee` WHERE `username` = '".$_SESSION['uName']."'");
	  $result = mysql_fetch_row($id, 0) or trigger_error(mysql_error().$id);
	  $this->waiterId = $result[0];
  }
  
  public function tabCount()
  {
      $lt = 0;
	  $count = mysql_query("SELECT * FROM `floorplan` WHERE `waiterid` = '".$this->waiterId."'");
	  mysql_data_seek($count, 0);
	  while ($row = mysql_fetch_assoc($count)) {
		  $this->tabList = $this->tabList.$row['tableid']."-".$row['status'].";";
		  $lt++;
	  }
	  return $lt;
  }
 
  public function tabStatus()
  {
	  return $this->tabList;
  }
  
    public function totCount()
  {
      $lt = 0;
	  $count = mysql_query("SELECT * FROM `floorplan` WHERE 1");
	  mysql_data_seek($count, 0);
	  while ($row = mysql_fetch_assoc($count)) {
		  $this->tabList = $this->tabList.$row['tableid']."-".$row['status'].";";
		  $lt++;
	  }
	  return $lt;
  }
 
  public function totStatus()
  {
	  return $this->tabList;
  }
  
  public function updateStatus($id, $stat)
  {
	  mysql_query("UPDATE `floorplan` SET `status`='$stat' WHERE `tableid` = '$id'");
  }
  
}
?>