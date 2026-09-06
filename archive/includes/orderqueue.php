<?php
class OrderQueue
{
  private $orderList;
  private $orderCount;
 
  public function __construct()
  {
      $lt = 0;
	  $count = mysql_query("SELECT * FROM `orders` WHERE `done` = 0");
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
  
}
?>