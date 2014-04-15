<?
$con = @mysql_connect("localhost", "mcecreations")
	   		or die("Couldn't establish DB connection");
	   		
$sel = @mysql_select_db("cl_jobs")
			or die("Couldn't select database");
?>