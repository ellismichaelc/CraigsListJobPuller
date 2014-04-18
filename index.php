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

	}
	
	/* Landscape phones and smaller */
	@media (max-width: 480px) {

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
    
  </style>

  <script>
	$(document).ready(function() {

		$('#refresh').click(function() {
			getData();
		});
		
		$('#filter').on('input', function() {
			filter = $(this).val();
			
			$('.job_row').each(function() {
				
				row   = $(this);
				match = row.find("#title").html().toLowerCase().indexOf(filter.toLowerCase()) !== -1;
				
				if(!match) {
					match = row.find("#location").html().toLowerCase().indexOf(filter.toLowerCase()) !== -1;
				}
				
				if(match) {
					
					// display row
					row.fadeIn();
					
				} else {
				
					// dont display row
					row.fadeOut();
					
				}
				
			});
		});
	
	    function getData() {
	    	$('#status').html('Updating..');

		    $.get("data.php", function(data) {
		    	updated = moment(data.last_update).unix();
		    	now     = moment().unix();
		    	
		    	
			    $('#status').html('List Updated: ' + formatDuration(now - updated, false, " hour", " minute", false, " ", true, false, "just now", " ago", true, true));
			    
			    $(data.jobs).each(function(i, job) {
					updateRow(job);
			    });
			    
			    
		    }, "json").fail(function() {
				$('#status').html('Error while fetching data.');
			});
	    }
	    
	    function log(text) {
		    $('#log').append(text + "<br>");
	    }
	    
	    function updateRow(job) {
			var row = $('#data_job_' + job.id);
			
			if(row.length == 0) row = createRow(job);

	    	now     = moment().unix();
	    	
	    	posted = formatDuration(now - job.posted, " day", " hour", " minute", false, " ", true, false, "just now", " ago", true, true);
			
			row.find("#title").html(job.title);
			row.find("#location").html(job.location);
			row.find("#updated").html(posted);
			row.find("#link").attr('href', job.url);

			row.fadeIn();
	    }
	    
	    function createRow(job) {
			var new_row = $('#data_template').clone().appendTo('#data_content');
			new_row.attr('id', 'data_job_' + job.id).addClass("job_row");
			
			return new_row;
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
	    
	    setInterval(getData, 60000);
	    getData();
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
					<li class="active"><a href="#">All Jobs</a></li>
				</ul>
          

			</div><!--/.navbar-collapse -->
		</div>
	</div>
	
	<div class="container" role="main">
		<div class="page-header">
			<h1 id="#title">All Jobs</h1>
					
			<div style="display: block; overflow: auto;">
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
			<a href="" id="link" class="page-thumbnail">
				<div class="title">
					<span id="title"></span>
					<div class="summary"><span id="location"></span></div>
				</div>
				
				<div class="last-updated"><span id="updated"></span></div>
				<div class="clearfix"></div>
	        </a>
		</div>
		
		<div id="data_content">
		
		</div>

	</div>
 
 
 </body>
</html>