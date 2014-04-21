<?
//echo date("m/d/y h:ia T", strtotime("2014-04-17T21:13:21-0600"));

require "lib/database.php";

$output   = array();
$filter   = isset($_POST['filter']) ? $_POST['filter'] : "";
$filter   = mysql_real_escape_string(trim(preg_replace("/[^\w\s\!\@\#\$\%\^\&\*\(\)\[\]\.\<\>\'\"]/", "", $filter)));
$fltr_sql = !empty($filter) ? "AND `title` LIKE '%{$filter}%'" : "";

$result = mysql_query("SELECT * FROM `listings` WHERE `status` = 1 {$fltr_sql} ORDER BY `posted` DESC LIMIT 25");
while($row = mysql_fetch_array($result)) {

	$posted = $row['posted'];
	$posted = strtotime($posted);
	
	$ago = time() - $posted;
	$ago = round($ago / 60 / 60 / 24) . " days ago ";
	
	$location = empty($row['location']) ? "" : "({$row['location']})";
	
	$output['jobs'][] = array('id'       => $row['id'],
							  'title'    => $row['title'],
				              'url'      => $row['url'],
				              'posted'   => $posted,
				              'location' => empty($row['location']) ? "" : "(" . $row['location'] . ")",
				              'desc'     => $row['desc'],
				             );

}

echo json_encode($output);
?>