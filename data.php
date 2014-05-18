<?
//echo date("m/d/y h:ia T", strtotime("2014-04-17T21:13:21-0600"));
require "lib/database.php"; 
session_start();

$per_page = 25;
$page     = isset($_POST['page']) ? $_POST['page'] : 1;
$time     = isset($_POST['time']) ? $_POST['time'] : 0;

$ver      = "";
$files    = array("index.php");

clearstatcache();
foreach($files as $file) $ver .= filemtime($file);

$output   = array("ver" => md5($ver));
$search   = array("once every", "on-site", "must live", "office", "on site", "meeting", "mostly a tele", "mostly tele", "days of telecommute", "telecommute days", "partial telecommute", "partial remote", "only local", "local only");
$filter   = isset($_POST['filter']) ? $_POST['filter'] : "";
$prop     = isset($_POST['prop']) ? $_POST['prop'] : "";
$val      = isset($_POST['val']) ? $_POST['val'] : "";
$job      = isset($_POST['job']) ? $_POST['job'] : "";
$type     = isset($_POST['type']) ? $_POST['type'] : "";
$filter   = mysql_real_escape_string(trim(preg_replace("/[^\w\s\!\@\#\$\%\^\&\*\(\)\[\]\.\<\>\'\"]/", "", $filter)));
$fltr_sql = !empty($filter) ? "AND (`title` LIKE '%{$filter}%' OR `location` LIKE '%{$filter}%' OR `desc` LIKE '%{$filter}%')" : "";
$details  = isset($_POST['details']) ? $_POST['details'] : "";
$props    = array("viewed" => 1, "clicked" => 1, "applied" => 2, "later" => 2, "wont" => 2);
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

		if(!isset($props[$prop])) die("That prop name isn't allowed.");
		if(!is_numeric($job)) die("That's an invalid job.");
		
		$type = $props[$prop];
	
		$val = mysql_real_escape_string($val);
		
		if($type == 2) mysql_query("DELETE FROM `props` WHERE `prop_type`='2' AND `job_id`='{$job}' AND `session_id`='{$ses}'");
		
		$query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `job_id` = '{$job}' AND `prop` = '{$prop}'";
		if(mysql_num_rows(mysql_query($query)) > 0) {
			
			// UPDATE
			$query = "UPDATE `props` SET %s WHERE `session_id` = '{$ses}' AND `job_id` = '{$job}' AND `prop` = '{$prop}'";
			
		} else {
			
			// INSERT
			$query = "INSERT INTO `props` SET %s";
			
		}
		
		$params = "`session_id` = '{$ses}', `job_id` = '{$job}', `prop` = '{$prop}', `value` = '{$val}', `time` = NOW(), `prop_type` = '{$type}'";
		$query  = sprintf($query, $params);

		mysql_query("UPDATE `sessions` SET `last_update` = NOW() WHERE `id`='{$ses}'");	
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
	foreach($search as $term) { if(stristr($row['desc'], '' . $term . '')) $risk++; }
	
	foreach($search as $term) {
		$row['desc'] = preg_replace("/($term)/is", "<b style='color: red'>$1</b>", $row['desc']);
	}
	
	// success, info, warning, danger
	// $output['alert'][] = array("type" => "warning", "msg" => "message!");
	
	if($risk >= 3)      $output['alert'][] = array("type" => "danger",  "msg" => "<strong>Warning:</strong> We strongly believe this ad to be fraudulent, a scam, or an otherwise non-telecommute job. Many terms were detected in this ad that would indicate it to be fraudulent in some manner. It is not recommended you proceed.");
	elseif($risk >= 2)  $output['alert'][] = array("type" => "warning", "msg" => "<strong>Warning:</strong> Several terms were detected in this ad that would indicate it to be fraudulent in some manner. Proceed with extreme caution.");
	elseif($risk >= 1)  $output['alert'][] = array("type" => "info",    "msg" => "<strong>Notice:</strong> Terms were detected in this ad that could indicate it to be fraudulent in some manner. Proceed with caution.");

	$output['desc'] = utf8_encode($row['desc']);
	$dont_list = true;
	
}

if(!$dont_list) {

	$offset = ($page * $per_page) - $per_page;

	$limit = " LIMIT {$offset},{$per_page}";
	$query = "SELECT * FROM `listings` WHERE `status` = 1 %filter% GROUP BY `title`, `attr` ORDER BY `posted` DESC";
	
	
	
	if($time > 0 && is_numeric($time)) {
	
		$time       = date('Y-m-d H:i:s', $time);	
		$chg_lstng  = mysql_query("SELECT `id` FROM `listings` WHERE `updated` > '{$time}' AND `status` > 1");
		$chg_props  = mysql_query("SELECT * FROM `props` WHERE `session_id` = '{$ses}'");
		$updt_query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `time` > '{$time}'";
		
		while($row = mysql_fetch_array($chg_lstng)) {
			
			// Okay some rows that are probably on the page, have been disabled
			$output['remove'][] = $row['id'];
			
		}

		if(strtotime($ses_info['last_update']) > strtotime($time)) {
			
			$output['props'] = array();
			
			while($row = mysql_fetch_array($chg_props)) {
				
				// Okay some rows that are probably on the page, have been disabled
				$output['props'][] = array('job_id' => $row['job_id'], 'prop' => $row['prop'], 'val' => $row['value']);
				
			}
	
		}
	
		//$fltr_sql .= " AND (`added` > '{$time}')";
		/*
		if(mysql_num_rows(mysql_query($updt_query)) == 0) {// && !strtotime($ses_info['last_update']) > strtotime($time)) {
			$fltr_sql .= " AND (`added` > '{$time}')";
			
			
		} else $output['msg'] = $ses_info['last_update'] . " - " . ($time);*/
	}
	
	if(isset($type) && isset($props[$type])) {
		
		$query = "SELECT * FROM `listings` JOIN `props` ON `props`.`job_id` = `listings`.`id` WHERE `status` = 1 AND `prop`='{$type}' %filter% GROUP BY `title`, `attr` ORDER BY `posted` DESC";
		
	}
	
	$count = mysql_num_rows(mysql_query(str_replace("%filter%", $fltr_sql, $query)));

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
			if(stristr($row['desc'], '' . $term . '')) $risk++;
		}
		
		//$row['title'] .= " ({$risk}%)";
		
		$props = array();
		$query = "SELECT * FROM `props` WHERE `session_id` = '{$ses}' AND `job_id` = '{$row['id']}' GROUP BY `prop_type` ORDER BY `time`";
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
	
	$pages = ($count / $per_page);
	
	if($pages > round($pages)) $pages++;
	
	$pages = round($pages);
	
	$output['total'] = $count;
	$output['count'] = ($per_page * ($page - 1)) + @count($output['jobs']);
	$output['pages'] = $pages;
	$output['time']  = time();
	$output['page']  = $page;
}

echo json_encode($output);
?>