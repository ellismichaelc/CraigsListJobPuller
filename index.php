<?
require "lib/database.php";

$user = false;
$ref  = isset($_GET['ref']) ? mysql_real_escape_string($_GET['ref']) : "";

if(!empty($ref)) {

	$query  = "SELECT * FROM `sessions` WHERE `ref` = '{$ref}'";
	$result = mysql_query($query);
	$user   = mysql_fetch_array($result);
	
	// Restore the old session ID	
	sess_id($user['ref']);
	
}

if(!$user) {

	$ses  = sess_id();
	
	$query  = "INSERT INTO `sessions` SET `ref` = '{$ses}', `first_access` = NOW(), `last_access` = NOW()";
	$result = mysql_query($query);
	
}

if($ref !== sess_id()) {
	header('Location: ' . session_id());
	exit;
}

function sess_id($id = false) {
	// same as session_id() but will create a session if its not started
	
	if($id) {
	
		// we are setting
		session_id($id);
		
		session_start();
		
	} else {
		
		if(session_id() == "") session_start();
		
		// we are getting
		
	}
	
	return session_id();
	
}
?>

<!DOCTYPE html>
<html lang="en">
 <head>
  <title>CraigsList Remote Jobs</title>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <script type="text/javascript" language="javascript" src="js/jquery-2.1.0.min.js"></script>
  <script type="text/javascript" language="javascript" src="js/bootstrap.min.js"></script>
  <script type="text/javascript" language="javascript" src="js/moment.min.js"></script>
  <script type="text/javascript" language="javascript" src="js/jquery.cookie.js"></script>
  
  <style type="text/css" title="currentStyle">
	@import "css/font.css";
	@import "css/bootstrap.min.css";
	@import "css/bootstrap-theme.min.css";
  </style>

  <style>
  	body {
	  	font-family: museo-sans;
  	}

	body {
	  padding-top: 40px;
	  padding-bottom: 30px;
	}
  	
  	div#container {
	  padding: 15px;
	  overflow: auto;
  	}

a.page-thumbnail {
  display: block;
  padding: 7px 7px 7px 11px;
  margin-top: 3px;
  margin-bottom: 3px; }
  
  a.page-thumbnail .title {
    display: inline;
    float: left; }
    
    a.page-thumbnail .title h2 {
      display: inline;
      margin-top: 0;
      margin-bottom: 0;
      font-size: 16px;
      line-height: 24px;
      font-weight: bold;
      color: #0088cc; }
      
    a.page-thumbnail .title .summary {
      display: inline-block;
      margin-left: 11px;
      font-size: 15.2px;
      line-height: 24px;
      color: #999999; }
      
  a.page-thumbnail .last-updated {
    font-size: 15.2px;
    line-height: 24px;
    display: inline;
    float: right;
    color: #AAAAAA; }

	#listing_desc .modal-footer{
		margin-top: 10px;
		padding: 0px 10px 10px;
		text-align: right;
		border-top: 0;
	}
	
	#listing_desc .modal-header {
		padding: 8px;
	}
	
	#listing_desc .modal-title {
		line-height: normal;
	}
    
    .te {
	    word-break: break-word;
    }

	#listing_desc .modal-dialog {
	  width: 60%; /* desired relative width */
	  /*left: 20%; /* (100%-width)/2 */
	  /* place center */
	  margin-left:auto;
	  margin-right:auto; 
	}
	
	.page-header {
		margin: 20px 0 20px;
	}
	
	.attr-label {
		display: inline-block !important;
		white-space: normal !important;
	}
	
	.nav .glyphicon {
		font-size: 12px;
		margin-right: 2px;
	}

	/* Large desktops and laptops */
	@media (min-width: 1200px) {

	}
	
	/* Portrait tablets and medium desktops */
	@media (min-width: 992px) and (max-width: 1199px) {

	}
	
	/* Portrait tablets and small desktops */
	@media (min-width: 768px) and (max-width: 991px) {

	}
	
	/* Landscape phones and portrait tablets */
	@media (max-width: 767px) {
		#listing_desc .modal-title {
			font-size: 13px;
		}
		
		#listing_desc .modal-dialog {
			width: 90%;
			font-size: 11px;
			margin-bottom: 30px;
		}
		
		.job_row #title {
			font-size: 13px !important;
		}
		
		.job_row #updated {
			font-size: 11px;
		}
		
		.status_filter #refresh, .status_filter #status, .status_filter #filter {
			font-size: 13px !important;
		}
		
		#listing_desc .btn {
			font-size: 12px !important;
			padding: 6px !important;
		}
	}
	
	/* Landscape phones and smaller */
	@media (max-width: 480px) {
		#listing_desc .modal-title {
			font-size: 12px;
		}
		
		#listing_desc .modal-dialog {
			width: 100%;
			font-size: 10px;
			margin-bottom: 30px;
		}
		
		.job_row #title, .job_row #link_icon {
			font-size: 10px !important;
		}
		
		.job_row #updated {
			font-size: 8px;
		}
		
		.status_filter #refresh, .status_filter #status, .status_filter #filter, #results {
			font-size: 12px !important;
		}
		
		.status_filter #filter {
			width: 100px;
		}

		#listing_desc .btn {
			font-size: 9px !important;
			padding: 4px !important;
		}
		
		.job_row {
			line-height: normal !important;//
		}
		
		.job_row a#link {
			padding: 0 !important;
			margin: 0 !important;
		}
		
		.job_row #link_icon {
			margin-right: 2px !important;
		}
	}
  </style>

  <script>
	$(document).ready(function() {
	
		var filter = "";
		var page   = 1;
		var xhr    = null;
		var xhr2   = null;
		var pages  = 0;
		var count  = 0;
		var results = 0;
		var max_risk = 2;
		var disable_scrollspy = false;
		var time   = 0;
		var cname  = "cljobs";
		var scroll_timer;
		var data_timer;
		var viewed    = [];
		var last_update;
		var last_run = 0;
		var last_time = 0;
		var frequency = 60; // seconds to update
		var version;
		var type_filter = "";
		
		$('#refresh').click(function() {
			getData(false, true);
		});
		
		$('#filter').on('input', function() {
		
			filter = $('#filter').val();
			page   = 1;
			
			getData(true, false, true);
		});

		$(window).scroll(function() {

			  if(scroll_timer){
			         clearTimeout(scroll_timer);  
			  }
			  
			  scroll_timer = setTimeout(handleScroll, 200);
		});
		
		$('.nav #menu_1 a').click(function() {
			
			var type = $(this).attr('data-type');
			
			if(type != 'undefined') {
				type_filter = type;
			} else {
				type_filter = "";
			}

			page   = 1;
			getData(true, false, true);
			
		});

		$("#data_content").on("click", ".job_row", function(e) {
		
			// Cancel previous description loader
			if(xhr2) xhr2.abort();
			
			// Prevent opening the link
			e.preventDefault();
			
			// Hide the 'new' label if they clicked
			$(this).find('#new_label').fadeOut();
			
			// Job data
			var job = $(this).data('job');
			
			// Load the dialog
			var dialog = $('#listing_desc');
			dialog.find(".modal-title").html(job.title);
			dialog.find(".attr-label").remove();
			dialog.find("#desc").remove();
			dialog.find('.alert').remove();
			dialog.find(".te").show().html("Loading..");
			dialog.modal();
			
			// Hook the view button
			dialog.find('.view-listing').unbind().click(function() {
				
				window.open(job.url, "_blank", "");
				setJobProp(job, 'clicked');
				
			});

			// Hook the applied button
			dialog.find('.apply_applied').unbind().click(function() {

				setJobProp(job, 'applied');
				
			});

			// Hook the wont apply button
			dialog.find('.apply_wont').unbind().click(function() {
				
				setJobProp(job, 'wont');
				
			});

			// Hook the apply later button
			dialog.find('.apply_later').unbind().click(function() {
				
				setJobProp(job, 'later');
				
			});

			xhr2 = $.post("data.php", {details: job.id}, function(data) {
			
				dialog.find(".te").hide().clone().insertAfter(dialog.find(".te"))
					.attr('id', 'desc')
					.hide()
					.html(data.desc)
					.fadeIn();
				
				if(job.rate.length > 0) {
					dialog.find('#desc').before(
						$('<span id="attr_label" class="label label-success attr-label">Pay: ' + job.rate + '</span>')
							.css('margin-right', '5px')
							.fadeIn());
				}
				
				
				attr = 0;
				$(job.attr).each(function(i, v) {
					
					if(v == null) return;

					dialog.find('#desc').before(
						$('<span id="attr_label" class="label label-primary attr-label">' + v + '</span>')
							.css('margin-right', '5px')
							.fadeIn());
				
					attr++;
					
				});
				
				if(attr > 0) dialog.find('.te').css('margin-top', '15px');
				
				$(data.alert).each(function(i, v) {

					dialog.find('.modal-body').prepend(
						$('<div class="alert alert-' + v.type + '">' + v.msg + '</div>')
							.fadeIn());

				});
				
				setJobProp(job, 'viewed');
				
			}, "json").fail(function() {
				
				dialog.find(".te").html("Error: Couldn't load listing");
				
			});
			
		});
		
		function handleScroll() {
			if(!pages > 1) return;
		
			if($(window).scrollTop() + $(window).height() > $(document).height() - 200) {
				
				// User is far down enough to load more results!
				if(!disable_scrollspy) {
					disable_scrollspy = true;
					
					getData(true, false, false, true);
				}
				
			}	
		}
		
		function updateResults() {
			$('#data_status').fadeIn();
			
			var output = "Displaying " + count + " of " + results + " results";
			
			if(pages - page > 0) output = output + (pages > 1 ? ", " + (pages - page) + " more pages" : "");
			
			if(count == results) output = "Displaying " + count + " results";
			
			$('#results').html(output);
		}
		
		function updateStatus(last_run_time) {
	    	//updated = moment(last_update).unix();
	    	
	    	if(last_run_time == false) {
				last_run = 0;
	    	}
	    	
	    	if(!last_run_time == '') {
	    		last_run = last_run_time;
	    	}
	    	
	    	now     = moment().unix();
	    	last    = now - last_run;
	    	updated = formatDuration(last, false, " hour", " minute", " second", " ", true, false, "just now", " ago", true, true);

	    	if(last > frequency && last_run > 0) {
		    	getData(false, true);
	    	}
	    	
	    	if(last_run == 0) {
				
				$('#status').html('Updating list..');
				
	    	
	    	} else if (last_run == -1) {
		    
		    	// do nothing
		    	
	    	} else {
	    	
		    	$('#status').html('List Updated: ' + updated);
		    	
		    }
		    
		    if(now - last_time >= 60) {
		    
		    
			    $('.job_row').each(function(i,v) {
					
					job = $(v).data("job");
					
					updateTime(job);
					
			    });
			    
			    last_time = now;
		    
		    }
		}
		
	
	    function getData(disallow_new_tags, is_auto, remove_old, next_page) {
	    
	    	// if auto refresh is true, it means the interval timer called the function
	    	// therefore, the filter hasnt changed, and there were already results displayed
	    	// so we should tag all new results with a 'new' tag or something snazzy if auto_refresh == true
	    
			// if append is set, should append results to the bottom instead of updating and removing ones not on the list
	    
			clearTimeout(data_timer);
			
	    	if(xhr) xhr.abort();
	    	
	    	$('.job_row').attr('data-state', 'pending');
	    	if(remove_old) $('.job_row').css('opacity', '.5');
	    
	    	
			updateStatus(false);
			page_num = is_auto ? 1 : page;
			
			if(next_page && pages > 1) page_num++;
			
			if(page_num > 1 && pages > 1) {
				$('#data_loading h4').html("Grabbing page " + page_num + " of " + pages + " ..");
			
				$('#data_loading').fadeIn();
				
				$('html, body').animate({
			        scrollTop: $("#data_loading").offset().top
			    }, 1000);
			}
			
			var options = {filter: filter, page: page_num, type: type_filter};
			
			if(is_auto) {
				options['time'] = time;
			}

		    xhr = $.post("data.php", options, function(data) {			    
			    pages   = data.pages;
			    results = data.total;
			    last_update = data.last_update;
			    
				if(version) {
					
					if(version !== data.ver) {
						
						$('.modal').modal('hide');
						dialog = $('.modal#page_updated').css('display','block');
						margin = ($(window).height() - 200) / 2;
						dialog.css('margin-top', margin + 'px').modal();
						
						setTimeout(function() {
							
							location.reload(); 
							
						}, 3500);
						
					}
					
				} else {
					version = data.ver;
				}
			    
			    time = data.time;
			    
			    if(!is_auto) {
			    	page = data.page;
			    }
			    
			    $(data.jobs).each(function(i, job) {
			    	count++;
			    	
					var row = updateRow(job);
					
					if(row.data('new') == true && !disallow_new_tags && row.find('#new_label').length == 0) {
						$('<span id="new_label" class="label label-success">New</span>').css('margin-right', '5px').insertBefore(row.find('#title'));
					}
					
					if(row.data('new') == true) {

					}
			    });
			    
			    if(remove_old) {
			    
				    $('.job_row[data-state="pending"]').fadeOut(function() {
				    	$(this).remove();
				    });
				    
			    } else {
			    
				    $('.job_row[data-state="pending"]').each(function(i,v) {
				    	$(this).attr('data-state', 'complete').css('opacity', $(this).data('opacity'));
				    });
				    
			    }
			    
			    if(page < pages) disable_scrollspy = false;
			    
			    $('#data_loading').fadeOut();
			    
			    
			    if(data.remove) {
				    
					$(data.remove).each(function(i, v) {
					
						$('#data_job_' + v).fadeOut(function() {
				    		$(this).remove();
						});
						
					});
				    
			    }

			    if(data.props) {
			    
			    	$('.job_row').each(function(i, v) {
				    	
				    	job = $(v).data('job');
				    	
				    	job.props = {};
				    	
						updateRow(job);
				    	
			    	});
				    
					$(data.props).each(function(i, v) {
					
						// job_id, prop, val
						
						job = getJob(v.job_id);
						
						if(job) {
							
							job.props[v.prop] = v.val;
							updateRow(job);
							
						}
						
					});
				    
			    }
			    
			    
			    count = $('.job_row[data-state="complete"]').length;
			    
			    updateStatus(moment().unix());
			    updateResults();
			    //loadUserData();
			    
			    data_timer = setTimeout(function(){ getData(false, true); }, frequency * 1000);
				
		    }, "json").fail(function(jqXHR, textStatus, errorThrown) {
		    
		    	if(errorThrown == 'abort') return;
		    
		    	updateStatus(-1);
		    	
				$('#status').html('Error while fetching data.');
				$('#data_loading h4').html($('#data_loading h4').html() + " Failed. Retrying..");
				$('.job_row[data-state="pending"]').attr('data-state', 'complete').css('opacity', 1);
				
				setTimeout(function() {
					disable_scrollspy = false;
					handleScroll();
				}, 1000);
				
				data_timer = setTimeout(function(){ getData(false, true); }, 3000);
			});
	    }
	    
	    function log(text) {
		    $('#log').append(text + "<br>");
	    }
	    
	    function updateTime(job) {
	    	now    = moment().unix();
	    	posted = formatDuration(now - job.posted, " day", " hour", " minute", false, " ", true, false, "just now", " ago", true, true);
			
			$('#data_job_' + job.id).find('#updated').html(posted);
	    }
	    
	    function updateRow(job) {
			var row = $('#data_job_' + job.id);
			var old = true;
			
			if(row.length == 0) {
				row = createRow(job);
				old = false;
			}
			
			if(job.risk >= max_risk) row.data('opacity', '.5');
			else			         row.data('opacity', 1);
			
			updateTime(job);
			
			row.data('job', job);
			row.data('new', !old);
			row.find("#title").html(job.title);
			row.find("#location").html(job.location);
			row.find("#link").attr('href', job.url);
			
			row.attr('data-state', 'complete');
			row.find(".glyphicon").remove();
			
			var icon    = "star";
			var color   = "000";
			var opacity = 0;
		
			if(job.props['viewed']) {
				icon    = "star-empty";
				color   = "666";
				opacity = .6;
			}

			if(job.props['clicked']) {
				icon    = "star";
				color   = "550000";
				opacity = .6;
			}

			if(job.props['wont']) {
				icon    = "ban-circle";
				color   = "990000";
				opacity = 0;
				
				row.data('opacity', 0.5);
			}

			if(job.props['later']) {
				icon    = "time";
				color   = "E68A00";
				opacity = .7;
			}
			
			if(job.props['applied']) {
				icon    = "ok";
				color   = "009900";
				opacity = 1;
			}

			if(job.props['saving']) {
				icon    = "floppy-open";
				color   = "000000";
				opacity = 0.4;
				
				delete job.props['saving'];
			}

			if(job.props['failed']) {
				icon    = "remove";
				color   = "aa0000";
				opacity = 0.6;
				
				
				delete job.props['failed'];
			}
		
			$('<span id="link_icon" class="glyphicon glyphicon-' + icon + '"></span>')
				.css('color', '#' + color)
				.css('font-size', '90%')
				.css('margin-right', '8px')
				.css('opacity', opacity)
				.insertBefore(row.find('#title'));
				
			row.css('opacity', row.data('opacity'));
			row.fadeIn();
			
			return row;
	    }
	    
	    function createRow(job) {
	    	count++;
	    
	    	var jobs    = $('.job_row');
	    	var new_row = $('#data_template').clone();
	    	
	    	new_row.attr('id', 'data_job_' + job.id).addClass("job_row");
	    	new_row.appendTo("#data_content");

			var row     = new_row;
	    	jobs.each(function(i, j) {
		    	j = $(j);
		    	job2 = j.data('job');

		    	if(job.id == job2.id) return;
		    	
				if(job.posted > job2.posted) {
				
					// new job is older than one we are looping
					// so lets place it after
					
					j.replaceWith(row);
					j.insertAfter(row);
					
					j.data('job', job2);
					
					return false;
				}
		    	
	    	});
	    	
			return new_row;
	    }
	    
	    function getJob(job_id) {
		    
		    return $('#data_job_' + job_id).data('job');
		    
	    }

		function loadUserData() {
			//cookie_data = $.cookie(cname);
			
			//if(!cookie_data) return;
			
			if(data_loaded) return;

		    $.post("data.php", {prop: 'get'}, function(data) {
	
				user_data   = data.user_data;
				
				data_loaded = true;
				
		    }, "json").fail(function() {
		    
				console.log("Failed");
		
			});
			
			
			
			//user_data   = JSON.parse(cookie_data);
		}

		function saveUserData(name, val) {
			if(name) user_data[name] = val;
			
			/*
			$.cookie.json = true;
			$.cookie(cname, user_data, { expires: 999 });*/

		}
		
		function getUserData(name) {
			if(!name) return user_data;
			
			return user_data[name];
		}
		
		function setJobProp(job, prop, val) {
			
			//if(!user_data) user_data = {};
			//if(!user_data[prop]) user_data[prop] = {};
			
			val = !val || val == undefined ? true : val;
			//user_data[prop][job.id] = val;
			
			//saveUserData();
			
			if(job.props[prop]) return;
				
			new_props = {};
			
			if(job.props['clicked']) new_props['clicked'] = true;
			if(job.props['viewed'])  new_props['viewed'] = true;
			
			job.props = new_props;
			
			
			
			job.props['saving'] = true;
			updateRow(job);
			
		    $.post("data.php", {job: job.id, prop: prop, val: val}, function(data) {
	
				job.props[prop] = val;
				
				updateRow(job);
				
		    }, "json").fail(function() {
		    
				job.props['failed'] = true;
				
				updateRow(job);
		
			});
		 
		}
		
		function getJobProp(job, prop) {		
			try {
				if(user_data[prop][job.id] == undefined) return false;
				return user_data[prop][job.id];
			} catch(e) { return false; }
		}
		
	    function updateSyncd() {
			rows = $('[id^="data_user_"]');
			
			rows.each(function(i, row) {
				syncd  = $(row).data("syncd");
				format = moment(syncd).fromNow();
				
				$(row).find("#data_syncd").html(format);
			});
	    }
	    
	    // Will turn num seconds/minutes into h/m/s with sep as separator
	    // use is_mins = true if youre passing minutes
	    // zero_return is the string to return if h/m/s are all empty and exclude_empty is true
	    function formatDuration(num, d_format, h_format, m_format, s_format, sep, exclude_empty, is_mins, zero_return, suffix, largest_only, append_s) {
		    duration = is_mins ? minutesFormat(num) : secondsFormat(num);
		    ret_val = "";
		    
			if(!exclude_empty || (exclude_empty && duration.d > 0) && d_format)
				ret_val = duration.d + d_format + (append_s ? (duration.d == 1 ? "" : "s") : "");

			if(!exclude_empty || (exclude_empty && duration.h > 0) && h_format && (!largest_only || (largest_only && ret_val == "")))
				ret_val = (ret_val != "" ? ret_val + sep : "") + duration.h + h_format + (append_s ? (duration.h == 1 ? "" : "s") : "");
			
			if(!exclude_empty || (exclude_empty && duration.m > 0) && m_format && (!largest_only || (largest_only && ret_val == "")))
				ret_val = (ret_val != "" ? ret_val + sep : "") + duration.m + m_format + (append_s ? (duration.m == 1 ? "" : "s") : "");
				
			if(!exclude_empty || (exclude_empty && duration.s > 0) && s_format && (!largest_only || (largest_only && ret_val == "")))
				ret_val = (ret_val != "" ? ret_val + sep : "") + duration.s + s_format + (append_s ? (duration.s == 1 ? "" : "s") : "");
			
			if(ret_val == "") return zero_return;
			
			if(!suffix) suffix = '';
			
			return ret_val + suffix;
	    }
	    
	    function minutesFormat(mins) {
		    return secondsFormat(mins * 60);
	    }

		function secondsFormat(secs) {
			var days = Math.floor(secs / (60 * 60) / 24);
			
		    var hours = Math.floor(secs / (60 * 60));
		   
		    var divisor_for_minutes = secs % (60 * 60);
		    var minutes = Math.floor(divisor_for_minutes / 60);
		 
		    var divisor_for_seconds = divisor_for_minutes % 60;
		    var seconds = Math.ceil(divisor_for_seconds);
		   
		    var obj = {
		    	"d": days,
		        "h": hours,
		        "m": minutes,
		        "s": seconds
		    };
		    
		    return obj;
		}
	    
	    getData(true);
	    updateStatus();
	    
	    setInterval(updateStatus, 1000);
	});
  </script>
 </head>
 <body role="document">
 
 	<div id="log" style="background:#eee"></div>
	<div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				
				<a class="navbar-brand" href="#">CraigsList Remote Jobs</a>
				
			</div>
			
			<div class="navbar-collapse collapse">
				<ul class="nav navbar-nav">
					<li class="dropdown">
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">Job List <b class="caret"></b></a>
						<ul class="dropdown-menu" id="menu_1">
							<li class=""><a href="#"><span class="glyphicon glyphicon-asterisk"></span> All Jobs</a></li>
							<li class="divider"></li>
							
							<li><a href="#" data-type="later"><span class="glyphicon glyphicon-time"></span> Apply Later</a></li>
							<li><a href="#" data-type="applied"><span class="glyphicon glyphicon-ok"></span> Applied To</a></li>
							<li><a href="#" data-type="viewed"><span class="glyphicon glyphicon-star-empty"></span> Peeked At</a></li>
							<li><a href="#" data-type="clicked"><span class="glyphicon glyphicon-star"></span> Viewed</a></li>
							<li><a href="#" data-type="wont"><span class="glyphicon glyphicon-ban-circle"></span> Won't Apply</a></li>							
						</ul>
					</li>
					<li class=""><a href="#options">Options</a></li>
				</ul>

			</div><!--/.navbar-collapse -->
		</div>
	</div>
	
	<div class="container" role="main">
		<div class="page-header">
			<!--<h1 id="#title">All Jobs</h1>-->
					
			<div style="display: block; overflow: auto;" class="status_filter">
				<div style="float:left;">
	
					<h5 style="color: #666;">
						<span class="glyphicon glyphicon-refresh" style="cursor: pointer; font-size: 13px;" id="refresh"></span>
						<span id="status" style="margin-left: 5px; vertical-align: top;"></span>
					</h5>
				</div>
	
				<div style="float:right;">
					<div class="input-group">
						<input type="text" placeholder="Filter results.." id="filter" class="form-control">
					</div>
				</div>
			</div>
		</div>

		<div id="data_template" style="display:none;">
			<a href="" id="link" target="_blank" class="page-thumbnail">
				<div class="title">
					<span id="title"></span>
					<div class="summary" class="hidden-xs"><span id="location" class="hidden-xs"></span></div>
				</div>
				
				<div class="last-updated"><span id="updated"></span></div>
				<div class="clearfix"></div>
	        </a>
		</div>
		
		<div id="data_status" style="display: none; margin-bottom: 20px;">
			<h5 style="color: #666;">
				<span class="glyphicon glyphicon-search" style="cursor: pointer; font-size: 13px;" id="refresh"></span>
				<span id="results" style="margin-left: 5px; vertical-align: top;"></span>
			</h5>
		</div>
		
		<div id="data_content">
		
		</div>
		
		<div id="data_loading" style="display: none; margin-top: 25px;">
			<h4></h4>
		</div>

	</div>

	<!-- Listing Content Dialog -->
	<div class="modal fade" id="listing_desc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	    <div class="modal-dialog">
	        <div class="modal-content">
	            <div class="modal-header">
	                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	                 <h4 class="modal-title">Modal title</h4>
	
	            </div>
	            <div class="modal-body"><div class="te"></div></div>
	            <div class="modal-footer">
	            	<button type="button" class="btn btn-success apply_applied" data-dismiss="modal" style="float: left;">I Applied</button>
	            	<button type="button" class="btn btn-warning apply_later" data-dismiss="modal" style="float: left;">Apply Later</button>
	            	<button type="button" class="btn btn-danger apply_wont" data-dismiss="modal" style="float: left;">Won't Apply</button>
	                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	                <button type="button" class="btn btn-primary view-listing">View Listing</button>
	            </div>
	        </div>
	        <!-- /.modal-content -->
	    </div>
	    <!-- /.modal-dialog -->
	</div>
	<!-- /.modal -->

	<!-- Page Updated Dialog -->
	<div class="modal fade" id="page_updated" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	    <div class="modal-dialog modal-sm">
	        <div class="modal-content">
	            <div class="modal-body" style="text-align:center">
	            	<div class="te">
	            		<h4>This page has been updated!</h4>
	            		.. loading the new version now ..
	            	</div>
	            </div>
	        </div>
	        <!-- /.modal-content -->
	    </div>
	    <!-- /.modal-dialog -->
	</div>
	<!-- /.modal -->


 
 
 </body>
</html>