<?
// Script to scan for all craigslist sites, then search all sites for telecommute jobs

require "lib/database.php";

set_time_limit(0);

$search_base  = "http://www.craigslist.org/about/sites";
$search_terms = "php";
$search_url   = "/search/sof?zoomToPosting=&catAbb=sof&query=" . urlencode($search_terms) . "&is_telecommuting=1&excats=";

$cl_sites  = file_get_contents($search_base) or die("Couldn't get list of sites");
$all_sites = array();
$added     = 0;
$updated   = 0;
$parsed    = 0;
$failed    = 0;
$next      = 0;
$dupes     = 0;

$matcher   = preg_match_all("/href=\"(http.*?)\"/", $cl_sites, $matches);

foreach($matches[1] as $match) $all_sites[] = $match;
$all_sites = array("http://manila.en.craigslist.com.ph");
foreach($all_sites as $key=>$site) {
	$url  = $site . $search_url;
	
	$urls = array($url);
	$cur  = 0;
	
	while(isset($urls[$cur])) {
		$url  = $urls[$cur];
		$data = file_get_contents($url) or die("Failed to download listings from {$site}");
		
		echo "scanning $url<br>";
		
		$site_safe = preg_replace("/([\.\/])/", "\\\\$1", $site);
		$matcher   = preg_match_all("/<a href=\"(\/[\/a-zA-Z0-9]*\/[0-9]+\.html)\">(.*?)<\/a>/", $data, $matches);
	
		for($i=0; $i<count($matches[1]); $i++) {
			$link = $matches[1][$i];
			$text = $matches[2][$i];
			
			$link = $site . $link;
			$text = mysql_real_escape_string($text);
			
			if(mysql_num_rows(mysql_query("SELECT * FROM `listings` WHERE `url`='{$link}' LIMIT 1")) > 0) {
				
				// Listing exists, update `updated` time
				mysql_query("UPDATE `listings` SET `updated` = NOW() WHERE `url`='{$link}'");
				$updated++;
				
			} else {
				
				// Listing needs to be added
				mysql_query("INSERT INTO `listings` VALUES('', '{$link}', NOW(), NOW(), -1, '{$text}', 0, NULL, NULL, NULL);");
				
				$added++;
				
			}
			
			$err = mysql_error();
			if($err) die($err);
		}
		
		preg_match("/href=\'(\/search\/.*?)\' class=\"button next\"/", $data, $matches);
		if(isset($matches[1])) {
		
			$urls[] = $site . trim($matches[1]);
			
			$next++;
		
		}
		
		$cur++;
	}
}

// Now loop through all the listings and parse them
$result = mysql_query("SELECT * FROM `listings` ORDER BY `status` ASC, `posted` ASC");

while($row = mysql_fetch_array($result)) {
	// id, url, added, updated, posted, title, status, rate, attr, desc

	$url  = $row['url'];
	$info = array();
	
	$data = @file_get_contents($url);
	
	if(!$data) {
	
		$info['status'] = 2;
		
		$failed++;
		
	} else {
	
		preg_match("/<h2 class=\"postingtitle\">.*?<span class=\"star\"><\/span>(.*?)<\/h2>/sm", $data, $matches);
		$info['title']  = empty($matches[1]) ? false : trim($matches[1]);
		
		if(!empty($info['title'])) {
		
			preg_match("/<div class=\"bigattr\">compensation: <b>(.*?)<\/b><\/div>/", $data, $matches);
			if(!empty($matches[1])) $info['rate']   = trim($matches[1]);
		
			preg_match("/<time datetime=\"(.*?)\">.*?<\/time>/sm", $data, $matches);
			if(!empty($matches[1])) $info['posted'] = trim($matches[1]);
		
			preg_match("/<section id=\"postingbody\">(.*?)<\/section>/sm", $data, $matches);
			if(!empty($matches[1])) $info['desc']   = trim($matches[1]);
		
			preg_match("/<p class=\"attrgroup\">(.*?)<\/p>/sm", $data, $matches);
			if(!empty($matches[1])) $info['attr']   = $matches[1];
			
			preg_match_all("/<span>(.*?)<\/span>/sm", $info['attr'], $matches);
			if(!empty($matches[1])) $info['attr']   = serialize($matches[1]);
			
			$info['status'] = 1;
			
			$parsed++;
			
		} else {
			
			$info['status'] = 2;
			
			$failed++;
			
		}
	}
	
	// Remove duplicates first
	$dupe = $info;
	
	unset($dupe['posted']);
	unset($dupe['status']);
	
	$sql = "UPDATE `listings` SET `status`='2' WHERE `id` <> '{$row['id']}'";
	foreach($dupe as $key=>$val) $sql .= " AND `{$key}`='" . mysql_real_escape_string($val) . "'";
	
	// Run duplicate query if this listing is valid
	if($info['status'] == 1) {
		mysql_query($sql);
		$dupes += mysql_affec