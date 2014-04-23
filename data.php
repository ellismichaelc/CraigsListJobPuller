<?
//echo date("m/d/y h:ia T", strtotime("2014-04-17T21:13:21-0600"));

require "lib/database.php";

$per_page = 25;
$page     = isset($_POST['page']) ? $_POST['page'] : 1;
$time     = isset($_POST['time']) ? $_POST['time'] : 0;

$output   = array();
$filter   = isset($_POST['filter']) ? $_POST['filter'] : "";
$filter   = mysql_real_escape_string(trim(preg_replace("/[^\w\s\!\@\#\$\%\^\&\*\(\)\[\]\.\<\>\'\"]/", "", $filter)));
$fltr_sql = !empty($filter) ? "AND (`title` LIKE '%{$filter}%' OR `location` LIKE '%{$filter}%')" : "";
$details  = isset($_POST['details']) ? $_POST['details'] : "";

if(!is_numeric($page) || $page < 1) $page = 1;

if(is_numeric($details)) {
	
	// Requesting info about a specific listing
	$result = mysql_query("SELECT * FROM `listings` WHERE `id`='{$details}'");
	$row    = mysql_fetch_array($result);
	
	$output['desc'] = utf8_encode($row['desc']);
	
} else {

	$offset = ($page * $per_page) - $per_page;

	$limit = " LIMIT {$offset},{$per_page}";
	$query = "SELECT * FROM `listings` WHERE `status` = 1 %filter% GROUP BY `title`, `attr` ORDER BY `posted` DESC";
	
	$count = mysql_num_rows(mysql_query(str_replace("%filter%", $fltr_sql, $query)));
	
	if($time > 0 && is_numeric($time)) {
		$time      = date('Y-m-d H:i:s', $time);
		$fltr_sql .= " AND `added` > '{$time}'";
	}
	
	$result = mysql_query(str_replace("%filter%", $fltr_sql, $query) . $limit);
	while($row = mysql_fetch_array($result)) {
	
		$posted = $row['posted'];
		$posted = strtotime($posted);
		
		$ago = time() - $posted;
		$ago = round($ago / 60 / 60 / 24) . " days ago ";
		
		$location = empty($row['location']) ? "" : "({$row['location']})";
		
		$row['title']    = utf8_encode($row['title']);
		$row['location'] = utf8_encode($row['location']);
		$row['desc']     = utf8_encode($row['desc']);
		$row['rate']     = utf8_encode($row['rate']);
		
		$output['jobs'][] = array('id'       => $row['id'],
								  'title'    => $row['title'],
					              'url'      => $row['url'],
					              'posted'   => $posted,
					              'attr'     => @unserialize($row['attr']),
					              'rate'     => $row['rate'],
					              'location' => empty($row['location']) ? "" : "(" . $row['location'] . ")"
					             );
	
	}
	
	$output['total'] = $count;
	$output['count'] = ($per_page * ($page - 1)) + @count($output['jobs']);
	$output['pages'] = round(($count / $per_page) + .5);
	$output['time']  = time();
	$output['page']  = $page;
}

echo json_encode($output);
?>