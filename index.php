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

	.modal-footer {
		margin-top: 10px;
		padding: 0px 10px 10px;
		text-align: right;
		border-top: 0;
	}
	
	.modal-header {
		padding: 8px;
	}
	
	.modal-title {
		line-height: normal;
	}
    
    .te {
	    word-break: break-word;
    }

	.modal-dialog {
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
		.modal-title {
			font-size: 13px;
		}
		
		.modal-dialog {
			width: 90%;
			font-size: 11px;
			margin-bottom: 30px;
		}
		
		.job_row #title {
			font-size: 13px;
		}
		
		.job_row #updated {
			font-size: 11px;
		}
		
		.status_filter #refresh, .status_filter #status, .status_filter #filter {
			font-size: 13px !important;
		}
	}
	
	/* Landscape phones and smaller */
	@media (max-width: 480px) {
		.modal-title {
			font-size: 12px;
		}
		
		.modal-dialog {
			width: 100%;
			font-size: 10px;
			margin-bottom: 30px;
		}
		
		.job_row #title {
			font-size: 12px;
		}
		
		.job_row #updated {
			font-size: 10px;
		}
		
		.status_filter #refresh, .status_filter #status, .status_filter #filter, #results {
			font-size: 12px !important;
		}
		
		.status_filter #filter {
			width: 100px;
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
		var max_risk = 50;
		var disable_scrollspy = false;
		var time   = 0;
		var cname  = "cljobs";
		var scroll_timer;
		var user_data = {};
		var viewed    = [];
		
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
			
			// Hook the blue button
			dialog.find('.view-listing').unbind().click(function() {
				
				window.open(job.url, "_blank", "");
				setJobProp(job, 'clicked');
				
			});

			// Hook the green button
			dialog.find('.applied').unbind().click(function() {
				
				setJobProp(job, 'applied');
				
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
		
		function updateStatus() {
			$('#data_status').fadeIn();
			
			var output = "Displaying " + count + " of " + results + " results";
			
			if(pages - page > 0) output = output + (pages > 1 ? ", " + (pages - page) + " more pages" : "");
			
			if(count == results) output = "Displaying " + count + " results";
			
			$('#results').html(output);
		}
		
	
	    function getData(disallow_new_tags, is_auto, remove_old, next_page) {
	    
	    	// if auto refresh is true, it means the interval timer called the function
	    	// therefore, the filter hasnt changed, and there were already results displayed
	    	// so we should tag all new results with a 'new' tag or something snazzy if auto_refresh == true
	    
			// if append is set, should append results to the bottom instead of updating and removing ones not on the list
	    
	    	if(xhr) xhr.abort();
	    	
	    	$('.job_row').attr('data-state', 'pending');
	    	if(remove_old) $('.job_row').css('opacity', '.5');
	    
	    	$('#status').html('Updating list..');
			
			page_num = is_auto ? 1 : page;
			
			if(next_page) page_num++;
			
			if(page_num > 1 && pages > 1) {
				$('#data_loading h4').html("Grabbing page " + page_num + " of " + pages + " ..");
			
				$('#data_loading').fadeIn();
				
				$('html, body').animate({
			        scrollTop: $("#data_loading").offset().top
			    }, 1000);
			}
			
			var options = {filter: filter, page: page_num};
			
			if(is_auto) {
				options['time'] = time;
			}

		    xhr = $.post("data.php", options, function(data) {
		    	updated = moment(data.last_update).unix();
		    	now     = moment().unix();
		    	updated = formatDuration(now - updated, false, " hour", " minute", false, " ", true, false, "just now", " ago", true, true);
		    	
			    $('#status').html('List Updated: ' + updated);
			    
			    pages   = data.pages;
			    results = data.total;
			    //count   = data.count;
			    
			    if(!is_auto) {
			    	time = data.time;
			    	page = data.page;
			    }
			    
			    $(data.jobs).each(function(i, job) {
			    	count++;
			    	
					var row = updateRow(job);
					
					if(row.data('new') == true && !disallow_new_tags && row.find('#new_label').length == 0) {
						$('<span id="new_label" class="label label-default">New</span>').css('margin-right', '5px').insertBefore(row.find('#title'));
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
			    
			    count = $('.job_row[data-state="complete"]').length;
			    updateStatus();
				
		    }, "json").fail(function() {
		    
				$('#status').html('Error while fetching data.');
				$('#data_loading h4').html($('#data_loading h4').html() + " Failed. Retrying..");
				$('.job_row[data-state="pending"]').attr('data-state', 'complete').css('opacity', 1);
				
				setTimeout(function() {
					disable_scrollspy = false;
					handleScroll();
				}, 1000);
				
				
			});
	    }
	    
	    function log(text) {
		    $('#log').append(text + "<br>");
	    }
	    
	    function updateRow(job) {
			var row = $('#data_job_' + job.id);
			var old = true;
			
			if(row.length == 0) {
				row = createRow(job);
				old = false;
			}
			
	    	now    = moment().unix();
	    	posted = formatDuration(now - job.posted, " day", " hour", " minute", false, " ", true, false, "just now", " ago", true, true);
			
			if(job.risk >= max_risk) row.data('opacity', '.5');
			else			         row.data('opacity', 1);
			
			row.data('job', job);
			row.data('new', !old);
			row.find("#title").html(job.title);
			row.find("#location").html(job.location);
			row.find("#updated").html(posted);
			row.find("#link").attr('href', job.url);
			row.css('opacity', row.data('opacity'));
			row.attr('data-state', 'complete');
			row.find(".glyphicon").remove();
			
			

			var icon    = "star";
			var color   = "000";
			var opacity = 0;
		
			if(getJobProp(job, 'viewed')) {
				icon    = "star-empty";
				color   = "666";
				opacity = 1;
			}

			if(getJobProp(job, 'clicked')) {
				icon    = "star";
				color   = "550000";
				opacity = 1;
			}
			
			if(getJobProp(job, 'applied')) {
				icon    = "ok";
				color   = "009900";
				opacity = 1;
			}
		
			$('<span id="link_icon" class="glyphicon glyphicon-' + icon + '"></span>')
				.css('color', '#' + color)
				.css('font-size', '90%')
				.css('margin-right', '8px')
				.css('opacity', opacity)
				.insertBefore(row.find('#title'));
			
			
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

		function loadUserData() {
			cookie_data = $.cookie(cname);
			
			if(!cookie_data) return;
			
			user_data   = JSON.parse(cookie_data);
		}

		function saveUserData(name, val) {
			if(name) user_data[name] = val;
			
			$.cookie.json = true;
			$.cookie(cname, user_data, { expires: 999 });
		}
		
		function getUserData(name) {
			if(!name) return user_data;
			
			return user_data[name];
		}
		
		function setJobProp(job, prop, val) {
			
			if(!user_data[prop]) user_data[prop] = {};
			
			val = !val || val == undefined ? true : val;
			user_data[prop][job.id] = val;
			
			saveUserData();
			
			updateRow(job);
		 
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
	    
	    setInterval(function(){ getData(false, true); }, 60000);
	    
	    loadUserData();
	    getData(true);
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
					<li class="active"><a href="#jobs">Job List</a></li>
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
					<div class="summary"><span id="location" class="hidden-xs"></span></div>
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
	            	<button type="button" class="btn btn-success applied" data-dismiss="modal" style="float: left;">I Applied</button>
	                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
	                <button type="button" class="btn btn-primary view-listing">View Listing</button>
	            </div>
	        </div>
	        <!-- /.modal-content -->
	    </div>
	    <!-- /.modal-dialog -->
	</div>
	<!-- /.modal -->
 
 
 </body>
</html>