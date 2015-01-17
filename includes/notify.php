<?php
class Notify
{
  
  public function order($tabid)
  {
	  mysql_query("UPDATE `orders` SET `done`=1 WHERE `tableid` = '".$tabid."'");
  }
  
}
?>