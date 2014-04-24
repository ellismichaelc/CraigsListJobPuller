<?
// Script to scan for all craigslist sites, then search all sites for telecommute jobs

require "lib/database.php";

set_time_limit(0);

$search_base  = "http://www.craigslist.org/about/sites";
$search_terms = "";
$search_url   = "/search/sof?zoomToPosting=&catAbb=sof&query=" . urlencode($search_terms) . "&is_telecommuting=1&excats=";

echo "** Starting scraper to grab list of CL sites!\n\n";

$cl_sites  = file_get_contents($search_base) or die("Couldn't get list of sites");
$all_sites = array();
$web       = false;
$added     = 0;
$updated   = 0;
$parsed    = 0;
$failed    = 0;
$next      = 0;
$dupes     = 0;

$matcher   = preg_match_all("/href=\"(http:.*?)\"/", $cl_sites, $matches);

// Just some stuff for display in browser or in terminal
if(isset($_SERVER['SERVER_SOFTWARE'])) $web = true;
if($web) echo "<pre>";

foreach($matches[1] as $match) $all_sites[] = $match;

echo "Found: " . count($all_sites) . " CL sites to scrape\n\n";
echo "** Starting site scraper to grab links!\n\n";

foreach($all_sites as $key=>$site) {break;
	$url  = $site . $search_url;
	
	echo "Scanning: [" . ($key + 1) . "/" . count($all_sites) . "] {$site}\n";
	
	$urls = array($url);
	$cur  = 0;
	
	while(isset($urls[$cur])) {
		$url  = $urls[$cur];
		$data = file_get_contents($url) or die("Failed to download listings from {$site}");
		
		$site_safe = preg_replace("/([\.\/])/", "\\\\$1", $site);
		$matcher   = preg_match_all("/<a href=\"(\/[\/a-zA-Z0-9]*\/[0-9]+\.html)\">(.*?)<\/a>/", $data, $matches);
	
		echo "\t-Found " . count($matches[1]) . " links ";
		
		$new = 0;
		$old = 0;
	
		for($i=0; $i<count($matches[1]); $i++) {
			$link = $matches[1][$i];
			$text = $matches[2][$i];
			
			$link = $site . $link;
			$text = mysql_real_escape_string($text);
			
			if(mysql_num_rows(mysql_query("SELECT * FROM `listings` WHERE `url`='{$link}' LIMIT 1")) > 0) {
				
				// Listing exists, update `updated` time
				mysql_query("UPDATE `listings` SET `updated` = NOW() WHERE `url`='{$link}'");
				$updated++;
				$old++;
				
			} else {
				
				// Listing needs to be added
				mysql_query("INSERT INTO `listings` VALUES('', '{$link}', NOW(), NOW(), -1, '{$text}', 0, NULL, NULL, NULL, NULL);");
				
				$added++;
				$new++;
				
			}
			
			$err = mysql_error();
			if($err) die($err);
		}
		
		echo "({$new} new, {$old} existing)\n";
		
		preg_match("/href=\'(\/search\/.*?)\' class=\"button next\"/", $data, $matches);
		if(isset($matches[1])) {
		
			$urls[] = $site . trim($matches[1]);
			
			$next++;
		
		}
		
		$cur++;
	}
	
	echo "\n";
}

echo "** Starting listing parser!\n\n";

// Now loop through all the listings and parse them
$result = mysql_query("SELECT * FROM `listings` WHERE `status` < 2 ORDER BY `status` ASC,`posted` ASC");

while($row = mysql_fetch_array($result)) {
	// id, url, added, updated, posted, title, status, rate, attr, desc

	$url  = $row['url'];
	$info = array();
	
	$data = @file_get_contents($url);
	
	preg_match("/^(.*)\/.*\/(\d+)\.html/", $url, $matches);

	$desc_url = $matches[1] . "/fb/" . $matches[2];
	$desc     = @file_get_contents($desc_url);
	
	echo "{$url}\n";
	
	if(!$data) {
	
		echo "\tDEAD LINK\n";
	
		$info['status'] = 2;
		
		$failed++;
		
	} else {
	
		preg_match("/<h2 class=\"postingtitle\">.*?<span class=\"star\"><\/span>(.*?)<\/h2>/sm", $data, $matches);
		$info['title']  = empty($matches[1]) ? false : trim($matches[1]);
		
		if(!empty($info['title'])) {
			
			preg_match("/(.*)\((.*)\)$/", $info['title'], $matches);
			if(isset($matches[2])) {
				// Has location
				
				$info['location'] = trim($matches[2]);
				$info['title']    = trim($matches[1]);
			}

			echo "\t-VALID: " . substr($info['title'], 0, 50) . "\n";
		
			preg_match("/<div class=\"bigattr\">compensation: <b>(.*?)<\/b><\/div>/", $data, $matches);
			if(!empty($matches[1])) $info['rate']   = trim($matches[1]);
		
			preg_match("/<time datetime=\"(.*?)\">.*?<\/time>/sm", $data, $matches);
			if(!empty($matches[1])) $info['posted'] = trim($matches[1]);
			
			if(!empty($info['posted'])) {

				$info['posted'] = strtotime($info['posted']);				
				$info['posted'] = date('Y-m-d H:i:s', $info['posted']);
				
			}
		
			if(!empty($desc)) {
			
				$info['desc'] = trim($desc);
				echo "\t-CLEAN DESC\n";
				
			} else {
			
				preg_match("/<section id=\"postingbody\">(.*?)<\/section>/sm", $data, $matches);
				if(!empty($matches[1])) $info['desc']   = trim($matches[1]);
				
			}
			
			preg_match("/<p class=\"attrgroup\">(.*?)<\/p>/sm", $data, $matches);
			if(!empty($matches[1])) $info['attr']   = $matches[1];
			
			preg_match_all("/<span>(.*?)<\/span>/sm", $info['attr'], $matches);
			if(!empty($matches[1])) $info['attr']   = serialize($matches[1]);
			
			$info['status'] = 1;
			
			$parsed++;
			
		} else {
			
			echo "\t-INVALID, BAD LISTING\n";
			
			$info['status'] = 2;
			
			$failed++;
			
		}
	}
	
	// Remove duplicates first
	$dupe = $info;
	
	unset($dupe['posted']);
	unset($dupe['status']);
	unset($dupe['location']);
	
	$sql = "UPDATE `listings` SET `status`='2' WHERE `id` <> '{$row['id']}'";
	foreach($dupe as $key=>$val) $sql .= " AND `{$key}`='" . mysql_real_escape_string($val) . "'";
	
	// Run duplicate query if this listing is valid
	if($info['status'] == 1) {
		mysql_query($sql);
		
		$affected = mysql_affected_rows();
		$dupes   += $affected;

		// Display errors
		$err = mysql_error();
		if($err) echo "<b>Error:</b> {$err}<br><b>Query:</b> {$sql}<br>";
	
		if($affected > 0) echo "\t-DUPES: {$affected}\n";
	}
	
	// Build update query
	$sql = "UPDATE `listings` SET `updated`=NOW()";
	foreach($info as $key=>$val) $sql .= ", `{$key}`='" . mysql_real_escape_string($val) . "'";
	$sql .= " WHERE `id`='{$row['id']}'";
	
	// Run update query
	mysql_query($sql);
	
	// Display errors
	$err = mysql_error();
	if($err) echo "<b>Error:</b> {$err}<br><b>Query:</b> {$sql}<br>";
	
	echo "\n";
}

echo "\n$dupes duplicate rows removed\n$added rows added\n$updated rows updated\n$parsed rows parsed\n$failed rows failed to download or parse\n$next next links followed";
?>






