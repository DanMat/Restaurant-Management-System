<?php
class Payment
{
  private $orderList;
  private $orderCount;
 
  public function __construct()
  {
      $lt = 0;
	  $count = mysql_query("SELECT * FROM `orders` WHERE `done` = 1 AND `paid` = 0");
	  mysql_data_seek($count, 0);
	  while ($row = mysql_fetch_assoc($count)) {
		  $this->orderList = $this->orderList.$row['orderref']."*".$row['tableid']."*".$row['orderlist']."*";
		  $lt++;
	  }
	  $this->orderCount = $lt;
  }
  
  public function orderLimit()
  {
	  return $this->orderCount;
  }
  
  public function OrderQueueList()
  {
	  return $this->orderList;
  }
  
  public function pay($tabid)
  {echo "UPDATE `orders` SET `paid`=1 WHERE `tableid` = '".$tabid."'";
	  mysql_query("UPDATE `orders` SET `paid`=1 WHERE `tableid` = '".$tabid."'");
  }  
  
}
?>