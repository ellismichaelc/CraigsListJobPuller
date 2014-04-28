<?
//echo date("m/d/y h:ia T", strtotime("2014-04-17T21:13:21-0600"));

require "lib/database.php";

$per_page = 25;
$page     = isset($_POST['page']) ? $_POST['page'] : 1;
$time     = isset($_POST['time']) ? $_POST['time'] : 0;

$output   = array();
$search   = array("once every", "every", "on-site", "must live");
$filter   = isset($_POST['filter']) ? $_POST['filter'] : "";
$filter   = mysql_real_escape_string(trim(preg_replace("/[^\w\s\!\@\#\$\%\^\&\*\(\)\[\]\.\<\>\'\"]/", "", $filter)));
$fltr_sql = !empty($filter) ? "AND (`title` LIKE '%{$filter}%' OR `location` LIKE '%{$filter}%' OR `desc` LIKE '%{$filter}%')" : "";
$details  = isset($_POST['details']) ? $_POST['details'] : "";

if(!is_numeric($page) || $page < 1) $page = 1;

if(is_numeric($details)) {
	
	// Requesting info about a specific listing
	$result = mysql_query("SELECT * FROM `listings` WHERE `id`='{$details}'");
	$row    = mysql_fetch_array($result);
	
	$risk   = 0;
	foreach($search as $term) { if(stristr($row['desc'], ' ' . $term . ' ')) $risk++; }
	$risk = (100 / count($search)) * $risk;
	
	foreach($search as $term) {
		$row['desc'] = preg_replace("/ ($term) /is", " <b style='color: red'>$1</b> ", $row['desc']);
	}
	
	// success, info, warning, danger
	// $output['alert'][] = array("type" => "warning", "msg" => "message!");
	
	if($risk >= 75)      $output['alert'][] = array("type" => "danger",  "msg" => "<strong>Warning:</strong> We strongly believe this ad to be fraudulent, a scam, or an otherwise non-telecommute job. Many terms were detected in this ad that would indicate it to be fraudulent in some manner. It is not recommended you proceed.");
	elseif($risk >= 50)  $output['alert'][] = array("type" => "warning", "msg" => "<strong>Warning:</strong> Several terms were detected in this ad that would indicate it to be fraudulent in some manner. Proceed with extreme caution.");
	elseif($risk >= 25)  $output['alert'][] = array("type" => "info",    "msg" => "<strong>Notice:</strong> Terms were detected in this ad that could indicate it to be fraudulent in some manner. Proceed with caution.");

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
		
		// Calculate risk of it NOT being actual telecommute
		$risk   = 0;
		
		foreach($search as $term) {
			if(stristr($row['desc'], ' ' . $term . ' ')) $risk++;
		}
		
		$risk = (100 / count($search)) * $risk;
		
		//$row['title'] .= " ({$risk}%)";
		
		$output['jobs'][] = array('id'       => $row['id'],
								  'title'    => $row['title'],
					              'url'      => $row['url'],
					              'posted'   => $posted,
					              'attr'     => @unserialize($row['attr']),
					              'rate'     => $row['rate'],
					              'location' => empty($row['location']) ? "" : "(" . $row['location'] . ")",
					              'risk'     => $risk
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