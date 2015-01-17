<?php
class OrderLines
{
  private $order;
 
  public function __construct()
  {
	  $this->order = "";
  }
  
  public function addItem($id, $desc, $quan, $price)
  {
	   $this->order = $this->order.$desc."-".$quan."-".$price.";";
	   
	   if (!isset($_SESSION[$id."orderline"])) $_SESSION[$id."orderline"] = serialize($this->order);
       else   {
	   $test = unserialize($_SESSION[$id."orderline"]);
	   $test = $test.$this->order;
	   $_SESSION[$id."orderline"] = serialize($test);
	   }

  }
  
  public function viewLineItem($id)
  {
	  return unserialize($_SESSION[$id."orderline"]);
  }
  
}
?>