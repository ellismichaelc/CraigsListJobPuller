<?
//echo date("m/d/y h:ia T", strtotime("2014-04-17T21:13:21-0600"));
require "lib/database.php"; 
session_start();

$per_page = 25;
$page     = isset($_POST['page']) ? $_POST['page'] : 1;
$time     = isset($_POST['time']) ? $_POST['time'] : 0;

$output   = array();
$search   = array("once every", "every", "on-site", "must live");
$filter   = isset($_POST['filter']) ? $_POST['filter'] : "";
$prop     = isset($_POST['prop']) ? $_POST['prop'] : "";
$val      = isset($_POST['val']) ? $_POST['val'] : "";
$job      = isset($_POST['job']) ? $_POST['job'] : "";
$filter   = mysql_real_escape_string(trim(preg_replace("/[^\w\s\!\@\#\$\%\^\&\*\(\)\[\]\.\<\>\'\"]/", "", $filter)));
$fltr_sql = !empty($filter) ? "AND (`title` LIKE '%{$filter}%' OR `location` LIKE '%{$filter}%' OR `desc` LIKE '%{$filter}%')" : "";
$details  = isset($_POST['details']) ? $_POST['details'] : "";
$props    = array("viewed", "clicked", "applied");
$dont_list = false;

$ses = session_id();
$query = "SELECT * FROM `sessions` WHERE `ref` = '{$ses}'";
$ses   = mysql_fetch_array(mysql_query($query));

if(!isset($ses['id'])) die("Invalid session.");

$ses_info = $ses;
$ses      = $ses['id'];

if(!is_numeric($page) || $page < 1) $page = 1;


if(!empty($prop)) {

	// Try to save this users settings!
	
	if(!empty($val) && !empty($job)) {
	
		if(!in_array($prop, $props)) die("That prop name isn't allowed.");
		if(!is_numeric($job)) die("That's an invalid job.");
	
		$val = mysql_real_escape_string($val);
		
		$query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `job_id` = '{$job}' AND `prop` = '{$prop}'";
		if(mysql_num_rows(mysql_query($query)) > 0) {
			
			// UPDATE
			$query = "UPDATE `props` SET %s WHERE `session_id` = '{$ses}' AND `job_id` = '{$job}' AND `prop` = '{$prop}'";
			
		} else {
			
			// INSERT
			$query = "INSERT INTO `props` SET %s";
			
		}
		
		$params = "`session_id` = '{$ses}', `job_id` = '{$job}', `prop` = '{$prop}', `value` = '{$val}', `time` = NOW()";
		$query  = sprintf($query, $params);
		
		mysql_query($query);
		
		if(mysql_affected_rows() > 0) $output['result'] = true;
		else						  $output['result'] = false;
		
		$output['error'] = mysql_error();
		$dont_list = true;
	
	}
	
}

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
	$dont_list = true;
	
}

if(!$dont_list) {

	$offset = ($page * $per_page) - $per_page;

	$limit = " LIMIT {$offset},{$per_page}";
	$query = "SELECT * FROM `listings` WHERE `status` = 1 %filter% GROUP BY `title`, `attr` ORDER BY `posted` DESC";
	
	$count = mysql_num_rows(mysql_query(str_replace("%filter%", $fltr_sql, $query)));
	
	if($time > 0 && is_numeric($time)) {
	
		$time       = date('Y-m-d H:i:s', $time);
		$updt_query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `time` > '{$time}'";
	
		$output['msg'] = "FILTER USING {$time}!";
	
		if(mysql_num_rows(mysql_query($updt_query)) == 0 && !strtotime($ses_info['last_update']) > strtotime($time)) {
			$fltr_sql .= " AND `added` > '{$time}'";
		} else {
			$output['msg'] = "NOT GONNA FILTER USING {$time}!";
		}
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
		
		$props = array();
		$query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `job_id` = '{$row['id']}'";
		$query = mysql_query($query);
		while($prop_row = mysql_fetch_array($query)) {
			
			$props[ $prop_row['prop'] ] = $prop_row['value'];
			
		}
		
		$output['jobs'][] = array('id'       => $row['id'],
								  'title'    => $row['title'],
					              'url'      => $row['url'],
					              'posted'   => $posted,
					              'attr'     => @unserialize($row['attr']),
					              'rate'     => $row['rate'],
					              'location' => empty($row['location']) ? "" : "(" . $row['location'] . ")",
					              'risk'     => $risk,
					              'props'    => $props
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