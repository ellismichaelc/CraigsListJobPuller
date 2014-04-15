<html>
 <head>
  <title>CraigsList Jobs</title>
 </head>
 <body>
 
 <?
 require "lib/database.php";
 
 $result = mysql_query("SELECT * FROM `listings` WHERE `status` = 1 ORDER BY `posted` DESC");
 while($row = mysql_fetch_array($result)) {
	 
	 echo "<a href=\"{$row['url']}\">" . $row['title'] . "</a><br>";
	 
 }
 ?>
 
 </body>
</html>