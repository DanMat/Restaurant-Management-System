<?php
class Employee
{
  private $uName;
  private $pass;
 
  public function setCredentials($user, $pwd)
  {
      $this->uName = $user;
	  $this->pass = $pwd;
  }
 
  public function getPwd()
  {
      $getPass = mysql_query("SELECT pwd FROM `employee` WHERE `username` = '".$this->uName."'");
	  $result = mysql_fetch_row($getPass, 0) or trigger_error(mysql_error().$getPass);
	  return $result[0];
  }
  
  public function getRole($name)
  {
      $getPass = mysql_query("SELECT role FROM `employee` WHERE `username` = '".$name."'");
	  $result = mysql_fetch_row($getPass, 0) or trigger_error(mysql_error().$getPass);
	  return $result[0];
  }
}
?>