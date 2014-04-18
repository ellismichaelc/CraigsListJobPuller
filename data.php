<?
require "lib/database.php";

$output = array();

$result = mysql_query("SELECT * FROM `listings` WHERE `status` = 1 ORDER BY `posted` DESC LIMIT 100");
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