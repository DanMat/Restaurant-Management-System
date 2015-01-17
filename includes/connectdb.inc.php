<?php
    // connect to db
    $db = @mysql_connect($dblocation,$dbuser,$dbpass) or die("<h1>Could not connect to the database. Please check your settings</h1>");
 	@mysql_select_db($dbname,$db) or die("<h1>Could not connect to the database. Please check your settings</h1>");
?>