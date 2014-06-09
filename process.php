<?
// Script to scan for all craigslist sites, then search all sites for telecommute jobs

require "lib/database.php";

set_time_limit(0);

// Just some stuff for display in browser or in terminal
if(isset($_SERVER['SERVER_SOFTWARE'])) echo "<pre>";

$search_base  = "http://www.craigslist.org/about/sites";
$search_terms = "";
$search_url   = "/search/sof?zoomToPosting=&catAbb=sof&query=" . urlencode($search_terms) . "&is_telecommuting=1&excats=";

echo "\n** Starting scraper to grab list of free proxies!\n\n";

$proxy_list     = "https://www.hidemyass.com/proxy-list/";
$proxy_urls     = array($proxy_list);
$proxy_check    = file_get_contents($search_base) or die("Proxy check fail."); // Check this against what the proxy retrieves to make sure its alive
$proxy_url      = ""; 							 							 // This will store the active proxy
$proxy_id       = 0;
$proxy_uses     = array();
$proxy_start    = 0; 															 // Will hold the time the request started so we can determine lag
$proxy_lag      = 0;
$proxy_max_lag  = 5;
$proxy_lag_avg  = 0;
$proxy_last_lag = 0;
$proxy_lag_arr  = array();
$proxy_arr_key  = 0;
$proxy_lag_max  = 10; // Number of lag samples to use for averaging

$cur       = 0;
$added     = 0;
$updated   = 0;
$failed    = 0;
$page      = 1;

$result   = mysql_query("SELECT TIME_TO_SEC(TIMEDIFF(NOW(), `added`)) AS `last_add` FROM `proxies` ORDER BY `added` DESC LIMIT 1");
$last_add = mysql_result($result, 0);

if($last_add < (60 * 60)) {
	
	echo "** Skipping proxy scrape, last run was too recent.\n\n";
	
	$proxy_urls = null;
	
}

while(isset($proxy_urls[$cur])) {

	echo "Grabbing page #{$page} .. ";
	
	$url  = $proxy_urls[$cur];
	$cur  = $cur + 1;
	$data = getURL($url, null, null, 0, false, true);
	$cur_pg = $page;
	
	// Totally inefficient, just for display purposes. The next page method
	// is more reliable in-case there's a limit to the # of pages shown at once.
	
	preg_match_all("/<a href=\"\/proxy-list\/\d+\">(\d+)<\/a>/", $data, $matches);
	if(isset($matches[1])) $last_page = end($matches[1]);

	preg_match("/<li class=\"nextpageactive\"><a href=\"\/proxy-list\/([\d]+)\" class=\"next\">Next &#187;<\/a>/sm", $data, $matches);
	if(isset($matches[1])) {
	
		$proxy_urls[] = $proxy_list . $matches[1];
		$page         = $matches[1];
		$found_next   = true;
		
	} else $found_next = false;
	
	preg_match_all("/<tr.*?>(.*?)<\/tr>/sm", $data, $matches);
	if(!isset($matches[1])) continue;
	
	unset($matches[1][0]); // Header
	
	echo "Found " . count($matches[1]) . " proxies" . ($found_next ? " on page {$cur_pg} of {$last_page}." : ", this is the last page.\n") . "\n";
	
	foreach($matches[1] as $row) {
		
		preg_match_all("/<td.*?>(.*?)<\/td>/sm", $row, $cols);
		
		if(!isset($cols[1])) {
			$failed++;
			continue;
		}
		
		$cols  = $cols[1];
		$ip    = $cols[1];
		$port  = trim($cols[2]);
		$loc   = trim($cols[3]);
		$speed = $cols[4];
		$lag   = $cols[5];
		$proto = trim($cols[6]);
		$anon  = trim($cols[7]);
		
		// okay lets weed through their tactics to prevent scraping..
		
		preg_match_all("/\.(.*?){(.*?)}/", $ip, $css);
		if(!isset($css[1])) {
			$failed++;
			continue;
		}
		
		for($i=0; $i<count($css[1]); $i++) {
			
			$sel  = $css[1][$i];
			$attr = $css[2][$i];
			
			$ip = str_replace("class=\"{$sel}\"", "style=\"{$attr}\"", $ip);
			
		}

		$ip  = preg_replace("/<style>.*<\/style>/s", "", $ip);
		$ip  = preg_replace("/<div style=\"display:none\">.*?<\/div>/", "", $ip);
		$ip  = preg_replace("/<span style=\"display:none\">.*?<\/span>/", "", $ip);
		$ip  = strip_tags($ip);
		$loc = trim(strip_tags($loc));
		
		preg_match("/style=\"width:(\d*)%\"/", $speed, $speed);
		preg_match("/style=\"width:(\d*)%\"/", $lag, $lag);
		
		if(isset($speed[1])) $speed = $speed[1];
		else				 $speed = "";
		
		if(isset($lag[1]))   $lag   = $lag[1];
		else				 $lag   = "";
		
		// alright we have all of our variables, lets update the row in the database
		
		if(mysql_num_rows(mysql_query("SELECT * FROM `proxies` WHERE `ip`='{$ip}' AND `port`='{$port}' LIMIT 1")) > 0) {
			
			// Listing exists, update `updated` time
			mysql_query("UPDATE `proxies` SET `updated` = NOW(), `alive` = 1 WHERE `ip`='{$ip}' AND `port`='{$port}'");
			
			$updated++;
			
		} else {
			
			// Listing needs to be added
			mysql_query("INSERT INTO `proxies` VALUES('', '{$ip}', '{$port}', '{$loc}', '{$speed}', '{$lag}', '{$proto}', '{$anon}', NOW(), NOW(), '', 1, 0, 0);");
			
			$added++;
			
		}
		
	}
	
}

if($proxy_urls != null) echo "$added proxies added\n$updated proxies updated\n$failed proxies failed parse\n\n";

echo "** Starting scraper to grab list of CL sites!\n";

$cl_sites  = getURL($search_base) or die("Couldn't get list of sites");
$all_sites = array();
$added     = 0;
$updated   = 0;
$parsed    = 0;
$failed    = 0;
$next      = 0;
$dupes     = 0;

$matcher   = preg_match_all("/href=\"(http:.*?)\"/", $cl_sites, $matches);

foreach($matches[1] as $match) $all_sites[] = $match;

echo "\nFound: " . count($all_sites) . " CL sites to scrape\n\n";
echo "** Starting site scraper to grab links!\n\n";

foreach($all_sites as $key=>$site) {

	$url  = $site . $search_url;
	
	echo "Scanning: [" . ($key + 1) . "/" . count($all_sites) . "] {$site}\n";
	
	$urls = array($url);
	$cur  = 0;
	
	while(isset($urls[$cur])) {
		$url  = $urls[$cur];
		$data = getURL($url) or die("Failed to download listings from {$site}");
		
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
// WHERE (`status` <= 1 OR `status` = 4)
$result = mysql_query("SELECT * FROM `listings` WHERE STATUS <> 3 ORDER BY `status` ASC, `posted` DESC");

while($row = mysql_fetch_array($result)) {
	// id, url, added, updated, posted, title, status, rate, attr, desc

	$url   = $row['url'];
	$info  = array();
	$tries = 0;
	
	echo "GRABBING: {$url}\n";
	
	
	// Let's retry three times
	while(!$data = getURL($url)) {
		echo "\nRETRYING..";
		$tries++;

		if($tries == 3) break;
		
		sleep(1);
		
	}
	
	preg_match("/^(.*)\/.*\/(\d+)\.html/", $url, $matches);

	$desc_url = $matches[1] . "/fb/" . $matches[2];
	$desc     = getURL($desc_url);
	
	
	
	if(!$data) {
	
		echo "\t-DEAD LINK\n";
	
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
		
			if(!empty($desc) && !stristr($desc, "404 error")) {
			
				$info['desc'] = trim($desc);
				echo "\t-CLEAN DESC\n";
				
			} else {
			
				preg_match("/<section id=\"postingbody\">(.*?)<\/section>/sm", $data, $matches);
				if(!empty($matches[1])) $info['desc']   = trim($matches[1]);
				
			}
			
			preg_match("/<p class=\"attrgroup\">(.*?)<\/p>/sm", $data, $matches);
			if(!empty($matches[1])) $info['attr']   = $matches[1];
			
			
			if(isset($info['attr'])) {
			
				preg_match_all("/<span>(.*?)<\/span>/sm", $info['attr'], $matches);
				if(!empty($matches[1])) $info['attr']   = serialize($matches[1]);
				
			}
			
			
			$info['status'] = 1;
			
			$parsed++;
			
		} else {
			
			echo "\t-INVALID, BAD LISTING\n";
			
			unset($info['title']);
			
			$info['status'] = 3;
			
			$failed++;
			
		}
	}
	
	echo "\t-STATUS: {$info['status']}\n";
	
	// Remove duplicates first
	$dupe = $info;
	
	unset($dupe['posted']);
	unset($dupe['status']);
	unset($dupe['location']);

	// Build update query
	$sql = "UPDATE `listings` SET `updated`=NOW()";
	foreach($info as $key=>$val) $sql .= ", `{$key}`='" . mysql_real_escape_string($val) . "'";
	$sql .= " WHERE `id`='{$row['id']}'";
	
	// Run update query
	mysql_query($sql);
	
	// Build duplicate removal query
	$sql = "";
	foreach($dupe as $key=>$val) $sql .=" AND `{$key}`='" . mysql_real_escape_string($val) . "'";
	
	// Run duplicate query if this listing is valid
	if($info['status'] == 1) {
		mysql_query("UPDATE `listings` SET `status`='4' WHERE `status`='1' " . $sql);
		
		// Tally
		$affected = mysql_affected_rows();
		$dupes   += $affected;
	
		// Display errors
		$err = mysql_error();
		if($err) echo "<b>Error:</b> {$err}<br><b>Query:</b> {$sql}<br>";
	
		// Show info
		if($affected > 0) echo "\t-DUPES: {$affected}\n";
		
		// Show lag
		echo "\t-LAG: {$proxy_last_lag} / AVG: {$proxy_lag_avg}\n";
	
		// All rows are now marked bad, lets mark just one valid
		mysql_query("UPDATE `listings` SET `status`='1' WHERE `status`='4' " . $sql . "  ORDER BY `posted` DESC LIMIT 1");
	
		// Display errors
		$err = mysql_error();
		if($err) echo "<b>Error:</b> {$err}<br><b>Query:</b> {$sql}<br>";
	}
	
	echo "\n";
}

echo "\n$dupes duplicate rows removed\n$added rows added\n$updated rows updated\n$parsed rows parsed\n$failed rows failed to download or parse\n$next next links followed";

function getURL($url = "", $config = array(), $proxy_url = false, $attempt = 0, $force_new_proxy = false, $force_no_proxy = false) {

	global $proxy_start;
	global $proxy_max_lag;
	global $proxy_last_lag;
	
	// TO PREVENT BEING BLOCKED BY CL
	//sleep(3);
	
	$curl_proxy = $force_no_proxy ? false : ($proxy_url && !$force_new_proxy ? $proxy_url : getProxyURL($force_new_proxy));
	
	//echo "\nUsing proxy (" . ($proxy_url ? 'CHECK' : 'REAL') . "): {$curl_proxy}\n";
	
	$test_run    = $proxy_url ? true : false;
	$using_proxy = !empty($curl_proxy);
	
	//echo "+ PROXY: {$curl_proxy}\n";
	
	if($test_run) echo "+ TESTING PROXY.. ";
	
	$curl = curl_init();

	$config[ CURLOPT_URL ] = $url;

	$curl_config = array(
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_FOLLOWLOCATION => true,
	    CURLOPT_COOKIESESSION  => true,
	    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12',
	    CURLOPT_SSL_VERIFYPEER => false,
	    CURLOPT_CONNECTTIMEOUT => $proxy_max_lag * 2, 
		CURLOPT_TIMEOUT        => $proxy_max_lag * 2,
	    CURLOPT_PROXY          => $curl_proxy,
	);

	curl_setopt_array($curl, $curl_config);
	curl_setopt_array($curl, $config);
	
	$proxy_start = microtime(true);
	$result      = curl_exec($curl);
	
	$lag = microtime(true) - $proxy_start;
	
	$proxy_last_lag = $lag;
	
	if($test_run) echo "FINISHED IN {$lag} SECONDS.\n\n";
	
	if(!$result) {
	
		// Let's retry.. what.. 3 times?
		if($attempt == 3 || $proxy_url) {
		
			if($using_proxy) usedProxy(true);
			//echo "failed.";
			return false;
			
		}
		
		//echo "retrying.";
		return getURL($url, $config, $proxy_url, $attempt + 1);
	
	}
	
	if(preg_match("/This IP has been automatically blocked/", $result)) {
		
		// Craigslist has blocked us, lets use a new IP!
		
		echo "\n+ SWITCHING PROXY DUE TO CL BLOCK.\n";
		
		return getURL($url, $config, $proxy_url, 0, true);
		
	}
	
	if($using_proxy) usedProxy(false);

	curl_close($curl);
	
	return $result;
	
}

function usedProxy($failed = false) {
	
	global $proxy_id;
	global $proxy_start;
	global $proxy_url;
	global $proxy_uses;
	global $proxy_lag;
	global $proxy_max_lag;
	global $proxy_lag_avg;
	global $proxy_lag_arr;
	global $proxy_arr_key;
	global $proxy_lag_max;
	
	$field = $failed ? 'failed' : 'uses';
	
	mysql_query("UPDATE `proxies` SET `{$field}` = `{$field}` + 1, `used` = NOW() WHERE `id` = {$proxy_id}");
	
	if(!isset($proxy_uses[$proxy_url])) {
		
		$proxy_uses[$proxy_url] = 1;
		$proxy_lag              = 0;
		$proxy_lag_arr          = array();
		
	} else $proxy_uses[$proxy_url] ++;
	
	$uses = $proxy_uses[$proxy_url];
	$lag  = microtime(true) - $proxy_start;
	
	$proxy_lag_arr[$proxy_arr_key] = $lag;
	
	$proxy_arr_key ++;
	
	if($proxy_arr_key == $proxy_lag_max) $proxy_arr_key = 0;
	
	$proxy_lag     = $proxy_lag + $lag;
	$proxy_lag_avg = array_sum($proxy_lag_arr) / count($proxy_lag_arr);
	
	//echo "\n\n+ LAG: {$lag} / AVG: {$proxy_lag_avg} / USES: {$uses}\n\n";
	
	if($uses > 3 && $proxy_lag_avg > $proxy_max_lag) {
		
		echo "\n+ SWITCHING PROXY, RESPONSE TIME IS > {$proxy_max_lag} SEC. ({$proxy_lag_avg})\n";
		
		$proxy_url = getProxyURL(true);
		
	} elseif($uses > 10) {
		
		// Lets cap the lag calculations to 10 data samples
		
		$proxy_uses[$proxy_url] = 10;
		
	}
}

function getProxyURL($force_new_proxy = false) {
	
	// This function should select the least recently used proxy from the DB
	// then update the DB to set it as used, and this function should also
	// return the same proxy for .. 3-5 attempts? 
	
	global $proxy_url;
	global $proxy_check;
	global $search_base;
	global $proxy_uses;
	global $proxy_max;
	global $proxy_id;
	global $proxy_lag;
	
	//if(isset($proxy_uses[$proxy_url]) && !$force_new_proxy) {
		
		// The currently selected proxy has been used before,
		// we can use it again. But only so many times.
		
		// EDIT: We really dont need this as it will switch automatically when CL blocks us!
		
		/*
		$proxy_uses[$proxy_url] ++;
		if($proxy_uses[$proxy_url] <= $proxy_max) return $proxy_url;
		*/
		
	//if($proxy_id > 0 && !isset($proxy_uses[$proxy_url])) $proxy_uses[$proxy_url] = 0;
	
		//$proxy_uses[$proxy_url] ++;
	
	if($proxy_id > 0 && !$force_new_proxy) return $proxy_url;
		
	//}
	
	$result = mysql_query("SELECT *, (`speed` + `lag`) as `sp_lag` FROM `proxies` WHERE `alive` = 1 ORDER BY `used` ASC, sp_lag DESC, `uses` DESC, `failed` ASC LIMIT 1");
	$proxy  = mysql_fetch_array($result);
	
	mysql_query("UPDATE `proxies` SET `used` = NOW() WHERE `id` = {$proxy['id']}");
	
	$proto = "";
	
	if($proxy['proto'] == 'HTTPS')    $proto = "https://";
	if($proxy['proto'] == 'HTTP')     $proto = "http://";
	if($proxy['proto'] == 'socks4/5') $proto = "socks5://";
	
	$proxy_url = $proto . $proxy['ip'] . ":" . $proxy['port'];
	$proxy_id  = $proxy['id'];
	$proxy_lag = 0;
	
	echo "\n+ SELECTING AND TESTING PROXY: $proxy_url\n\n";
	
	$tries = 0;
	
	while(!$check = getURL($search_base, null, $proxy_url)) {
		break;
		$tries++;
		
		if($tries == 3) break;
		
	}
	
	if($check != $proxy_check) {
	
		mysql_query("UPDATE `proxies` SET `alive` = 0 WHERE `id` = {$proxy['id']}");

		if(empty($check)) $check = "EMPTY";

		echo "- PROXY IS BAD, SWITCHING NOW. (RESPONSE: {$check})\n";

		return getProxyURL(true);
	
	}
	
	//if(!isset($proxy_uses[$proxy_url])) $proxy_uses[$proxy_url] = 0;
	
	return $proxy_url;
	
}
?>






