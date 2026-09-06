<?php
class Item
{
  private $itemInList;
  private $itemCount;
 
  public function itemList($cat)
  {
      $lt = 0;
	  $count = mysql_query("SELECT `desc`, `price` FROM `item` WHERE `catid` = $cat");
	  mysql_data_seek($count, 0);
	  while ($row = mysql_fetch_assoc($count)) {
		  $this->itemInList = $this->itemInList.$row['desc']."-".$row['price'].";";
		  $lt++;
	  }
	  $this->itemCount = $lt;
  }
  
   public function itemLimit()
  {
	  return $this->itemCount;
  }
  
  public function getItemList()
  {
	  return $this->itemInList;
  }
  
}
?>