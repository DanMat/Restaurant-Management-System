<?php
class PlaceOrders
{
  private $order;
  private $orderRef;
 
  
  public function addOrder($id)
  {
	   $this->order = unserialize($_SESSION[$id."orderline"]);
	   $this->orderRef = $id."order".date("YmdGis");
	   echo "INSERT INTO `orders` ( `orderref`, `tableid`, `orderlist`) VALUES ( '$this->orderRef', '$id', '$this->order')";
	   mysql_query("INSERT INTO `orders` ( `orderref`, `tableid`, `orderlist`) VALUES ( '$this->orderRef', '$id', '$this->order')");
	   unset( $_SESSION[$id."orderline"] );
  }
  
}
?>