<?php
class Category
{
  private $catList;
  private $catCount;
 
  public function __construct()
  {
      $lt = 0;
	  $count = mysql_query("SELECT * FROM `category` WHERE 1");
	  mysql_data_seek($count, 0);
	  while ($row = mysql_fetch_assoc($count)) {
		  $this->catList = $this->catList.$row['catId']."-".$row['catDesc'].";";
		  $lt++;
	  }
	  $this->catCount = $lt;
  }
  
  public function catLimit()
  {
	  return $this->catCount;
  }
  
  public function categoryList()
  {
	  return $this->catList;
  }
  
}
?>