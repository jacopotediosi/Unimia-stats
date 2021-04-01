<?php
// DB Connection
$db = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));

// Used to limit the importance of previous months in charts which must show very recent data
$n_months_short_term = (int) getenv('N_MONTHS_SHORT_TERM');

// First date
$first_date   = $db->query("SELECT date_datetime FROM stats ORDER BY date_datetime ASC LIMIT 1")->fetch_row()[0];

// Uptime summary
$uptime_today = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 24 HOUR")->fetch_row();
$uptime_week  = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 7 DAY")->fetch_row();
$uptime_month = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 30 DAY")->fetch_row();
$uptime_all   = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats")->fetch_row();

// Daily uptime
$daily_uptime = $db->query("
SELECT t1.date_datetime,
100/(
	SELECT COUNT(*) FROM stats t2
	WHERE t2.date_datetime=t1.date_datetime
)*(
	SELECT COUNT(*) FROM stats t2
	WHERE t2.date_datetime=t1.date_datetime AND t2.is_up=1
) AS uptime_percentage
FROM (
	SELECT DISTINCT date_datetime
	FROM stats
) t1
");
$daily_uptime_array = [];
while ($row = mysqli_fetch_assoc($daily_uptime)) {
	$daily_uptime_array[$row['date_datetime']] = (double) $row['uptime_percentage'];
}

// Uptime heatmap
$uptime_heatmap_array = [];
$uptime_heatmap = $db->query("
SELECT t1.dayname, t1.hour_datetime, 
100/(
	SELECT COUNT(*) FROM stats t2
	WHERE dayname(t2.datetime)=t1.dayname AND t2.hour_datetime=t1.hour_datetime AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
)*(
	SELECT COUNT(*) FROM stats t2 WHERE dayname(t2.datetime)=t1.dayname AND t2.hour_datetime=t1.hour_datetime AND t2.is_up=1 AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) as uptime_percentage
FROM (
	SELECT DISTINCT dayname(datetime) as dayname, hour_datetime
	FROM stats
	WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) t1
");
while ($row = mysqli_fetch_assoc($uptime_heatmap)) {
	$uptime_heatmap_array[$row['dayname']][$row['hour_datetime']] = (double) $row['uptime_percentage'];
}

// Response time summary
$response_time_today = (int) $db->query("SELECT AVG(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 24 HOUR AND is_up=1")->fetch_row()[0];
$response_time_week  = (int) $db->query("SELECT AVG(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 7 DAY AND is_up=1")->fetch_row()[0];
$response_time_month = (int) $db->query("SELECT AVG(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 30 DAY AND is_up=1")->fetch_row()[0];
$response_time_all   = (int) $db->query("SELECT AVG(response_time) FROM stats WHERE is_up=1")->fetch_row()[0];

// Daily response time
$daily_response_time = $db->query("
SELECT date_datetime, AVG(response_time) AS avg
FROM stats
WHERE is_up=1
GROUP BY date_datetime
");
$daily_response_time_array = [];
while ($row = mysqli_fetch_assoc($daily_response_time)) {
	$daily_response_time_array[$row['date_datetime']] = (double) $row['avg'];
}

// Response time heatmap
$response_time_heatmap_array = [];
$response_time_heatmap = $db->query("
SELECT DAYNAME(datetime) AS dayname, hour_datetime, AVG(response_time) AS avg
FROM stats
WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH AND is_up=1
GROUP BY DAYNAME(datetime), hour_datetime
");
while ($row = mysqli_fetch_assoc($response_time_heatmap)) {
	$response_time_heatmap_array[$row['dayname']][$row['hour_datetime']] = (double) $row['avg'];
}

// Detailed view uptime
$detailed_view_uptime = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE date_datetime = CURDATE()")->fetch_row();

// Detailed view response time
$detailed_view_response_time = $db->query("SELECT AVG(response_time) AS avg, MIN(response_time) AS min, MAX(response_time) AS max FROM stats WHERE date_datetime = CURDATE() AND is_up=1")->fetch_assoc();
$detailed_view_response_time_avg = (int) $detailed_view_response_time["avg"];
$detailed_view_response_time_min = (int) $detailed_view_response_time["min"];
$detailed_view_response_time_max = (int) $detailed_view_response_time["max"];

// Detailed view time graph
$detailed_view_time = $db->query("SELECT DATE_FORMAT(datetime, '%H:%i:%s') AS time, response_time, reason FROM stats WHERE date_datetime = CURDATE()");
$detailed_view_time_array = [];
while ($row = mysqli_fetch_assoc($detailed_view_time)) {
	$detailed_view_time_array[$row['time']] = [
		'response_time' => (int) $row['response_time'],
		'reason'        => utf8_encode($row['reason'])
	];
}
?>
<!DOCTYPE HTML>
<html>
	<head>
		<meta charset="UTF-8">
		<title>Unimia stats</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="shortcut icon" href="favicon.ico">

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
		<!-- Bootstrap datepicker CSS -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker3.standalone.min.css" integrity="sha512-p4vIrJ1mDmOVghNMM4YsWxm0ELMJ/T0IkdEvrkNHIcgFsSzDi/fV7YxzTzb3mnMvFPawuIyIrHcpxClauEfpQg==" crossorigin="anonymous">
		<!-- Main theme -->
		<link rel="stylesheet" href="/css/main.css">

<?php // Google Analytics JS (only if env var GTAG_ID is defined)
	$gtag_id = getenv('GTAG_ID');
	if ($gtag_id) {
		echo <<<EOL
				<!-- Global site tag (gtag.js) - Google Analytics -->
				<script async src="https://www.googletagmanager.com/gtag/js?id=$gtag_id"></script>
				<script>
					window.dataLayer = window.dataLayer || [];
					function gtag(){dataLayer.push(arguments);}
					gtag('js', new Date());
					gtag('config', '$gtag_id');
				</script>
				<!-- Cookie Script -->
				<script type="text/javascript" charset="UTF-8" src="https://cdn.cookie-script.com/s/fa5c2a49f4aa070719c30eb57383ee68.js"></script>
		EOL;
	}
?>

	</head>

	<body>
		<!-- Navbar -->
		<nav class="navbar navbar-expand-sm navbar-dark fixed-top bg-dark">
			<a class="navbar-brand" href="#">Unimia stats</a>
			<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNavDropdown">
				<ul class="navbar-nav ml-auto">
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Uptime</a>
						<div class="dropdown-menu dropdown-menu-right">
							<a class="dropdown-item" href="#uptime_summary">Summary</a>
							<a class="dropdown-item" href="#daily_uptime">Daily</a>
							<a class="dropdown-item" href="#uptime_heatmap">Weekday / Daytime Heatmap</a>
						</div>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Response time</a>
						<div class="dropdown-menu dropdown-menu-right">
							<a class="dropdown-item" href="#response_time_summary">Summary</a>
							<a class="dropdown-item" href="#daily_response_time">Daily</a>
							<a class="dropdown-item" href="#response_time_heatmap">Weekday / Daytime Heatmap</a>
						</div>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Detailed view</a>
						<div class="dropdown-menu dropdown-menu-right">
							<a class="dropdown-item" href="#detailed_view_pick_date">Pick a date</a>
							<a class="dropdown-item" href="#detailed_view_uptime">Average uptime on selected date</a>
							<a class="dropdown-item" href="#detailed_view_response_time">Response time on selected date</a>
							<a class="dropdown-item" href="#detailed_view_time_graph">Data collected on selected date</a>
						</div>
					</li>
					<li class="nav-item">
						<a href="https://github.com/jacopotediosi/Unimia-stats" class="github-corner ml-sm-2 ml-0 d-block" aria-label="View source on GitHub" title="View source on GitHub" target="_blank">
							<!-- SVG made from Tim Holman, https://tholman.com/github-corners/ -->
							<svg width="35" height="35" viewBox="115 50 95 90" aria-hidden="true">
								<path d="M155.618 127.341C139.174 132.47 134.779 121.995 134.779 121.995C131.422 115.261 127.317 113.775 127.317 113.775C121.425 110.528 127.419 110.075 127.419 110.075C133.45 110.044 137.196 115.537 137.196 115.537C143.415 124.149 151.658 121.015 155.096 118.94" fill="currentColor" style="transform-origin: 149px 121px;" class="octo-arm"/>
								<path d="M146.84 143.77C146.84 143.912 150.517 142.215 150.517 140.659L150.588 121.072C151.153 117.113 152.709 114.426 154.477 112.941C141.042 111.385 126.97 106.223 126.9 83.101C126.97 76.525 129.304 71.08 133.193 66.908C132.486 65.352 130.435 59.2 133.759 50.927C133.759 50.927 138.779 49.301 150.376 57.079C155.113 55.736 160.275 55.099 165.437 55.029C170.528 55.029 175.761 55.736 180.569 57.15C192.095 49.301 197.186 50.857 197.186 50.857C200.439 59.2 198.388 65.352 197.752 66.837C201.641 71.151 203.974 76.454 203.974 83.101C203.974 106.294 189.832 111.385 176.397 112.941C178.518 114.779 180.498 118.456 180.498 124.113L180.428 140.588C180.428 142.286 184.67 143.983 184.741 143.77Z" fill="currentColor" class="octo-body"/>
							</svg>
						</a>
					</li>
				</ul>
			</div>
		</nav>

		<!-- Container -->
		<div class="container-fluid mb-4">
			<!-- UPTIME -->
			<h1 class="mt-3 mb-4 display-4">Uptime</h1>

			<!-- Summary -->
			<h2 id="uptime_summary" class="mt-3 mb-4 mb-md-3 text-center">Summary</h2>
			<div class="row text-center">
				<div class="col-lg-3 col-sm-6">
					<canvas id="uptime1_canvas" class="mb-2"></canvas>
					<h5 class="mb-4 mb-lg-2">Last 24 hours</h5>
				</div>
				<div class="col-lg-3 col-sm-6">
					<canvas id="uptime2_canvas" class="mb-2"></canvas>
					<h5 class="mb-4 mb-lg-2">Last 7 days</h5>
				</div>
				<div class="col-lg-3 col-sm-6">
					<canvas id="uptime3_canvas" class="mb-2"></canvas>
					<h5 class="mb-4 mb-sm-2">Last 30 days</h5>
				</div>
				<div class="col-lg-3 col-sm-6">
					<canvas id="uptime4_canvas" class="mb-2"></canvas>
					<h5>Since <?php echo $first_date; ?></h5>
				</div>
			</div>

			<!-- Daily -->
			<h2 id="daily_uptime" class="mt-4 mb-3 text-center">Daily</h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the detailed view of the dates</p>
			<div style="height: 300px">
				<canvas id="daily_uptime_canvas"></canvas>
			</div>
			
			<!-- Heatmap -->
			<h2 id="uptime_heatmap" class="mt-4 mb-3 text-center">Weekday / Daytime Heatmap  (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
			<div style="height: 300px">
				<canvas id="uptime_heatmap_canvas"></canvas>
			</div>

			<!-- AVERAGE RESPONSE TIME -->
			<h1 class="mt-5 mb-4 mb-sm-5 display-4">Average response time (when up) <a href="javascript:void(0)" data-toggle="tooltip" data-placement="right" title="Response times include the time to login to the CAS">*</a></h1>

			<!-- Summary -->
			<h2 id="response_time_summary" class="mt-4 mb-3 text-center">Summary</h2>
			<div class="row text-center my-5">
				<div class="col-lg-3 col-sm-6 mb-5 mb-lg-2">
					<h3><?php echo $response_time_today; ?> ms</h3>
					<h5>Last 24 hours</h5>
				</div>
				<div class="col-lg-3 col-sm-6 mb-5 mb-lg-2">
					<h3><?php echo $response_time_week; ?> ms</h3>
					<h5>Last 7 days</h5>
				</div>
				<div class="col-lg-3 col-sm-6">
					<h3><?php echo $response_time_month; ?> ms</h3>
					<h5 class="mb-5 mb-sm-2">Last 30 days</h5>
				</div>
				<div class="col-lg-3 col-sm-6">
					<h3><?php echo $response_time_all; ?> ms</h3>
					<h5>Since <?php echo $first_date; ?></h5>
				</div>
			</div>

			<!-- Daily -->
			<h2 id="daily_response_time" class="mt-4 mb-3 text-center">Daily</h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the detailed view of the dates</p>
			<div style="height: 300px">
				<canvas id="daily_response_time_canvas"></canvas>
			</div>

			<!-- Heatmap -->
			<h2 id="response_time_heatmap" class="mt-4 mb-3 text-center">Weekday / Daytime Heatmap  (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
			<div style="height: 300px">
				<canvas id="response_time_heatmap_canvas"></canvas>
			</div>

			<!-- DETAILED VIEW -->
			<h1 class="mt-5 mb-5 display-4">Detailed view</h1>

			<div class="row text-center mb-4">
				<!-- Calendar -->
				<div class="col-lg-4 mb-4 mb-lg-0">
					<div class="d-flex flex-column h-100">
						<h2 id="detailed_view_pick_date" class="mb-4 mb-md-3">Pick a date</h2>
						<div class="d-flex flex-column h-100">
							<div id="detailed_view_calendar" class="d-inline-block mx-auto"></div>
						</div>
					</div>
				</div>
				<!-- Average uptime -->
				<div class="col-md-6 col-lg-4 mb-4 mb-md-0">
					<div class="d-flex flex-column h-100">
						<h2 id="detailed_view_uptime" class="mb-4 mb-md-3">Average uptime on <span class="text-nowrap detailed_view_selected_date"><?php echo date('Y-m-d'); ?></span></h2>
						<div class="my-auto">
							<canvas id="detailed_view_uptime_canvas" class="mb-2"></canvas>
						</div>
					</div>
				</div>
				<!-- Response time (avg, min, max) -->
				<div class="col-md-6 col-lg-4">
					<div class="d-flex flex-column h-100">
						<h2 id="detailed_view_response_time" class="mb-4 mb-md-3">
							Response time on <span class="text-nowrap detailed_view_selected_date"><?php echo date('Y-m-d'); ?></span>
							<a href="javascript:void(0)" data-toggle="tooltip" data-placement="right" title="Response times include the time to login to the CAS">*</a>
						</h2>
						<div class="my-auto py-3">
							<h4>Avg: </h4>
							<h3 id="detailed_view_response_time_avg"><?php echo $detailed_view_response_time_avg; ?> ms</h3><br>
							<div class="row text-center align-items-center">
								<div class="col-6">
									<h4>Min: </h4>
									<h3 id="detailed_view_response_time_min"><?php echo $detailed_view_response_time_min; ?> ms</h3>
								</div>
								<div class="col-6">
									<h4>Max: </h4>
									<h3 id="detailed_view_response_time_max"><?php echo $detailed_view_response_time_max; ?> ms</h3>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Time graph -->
			<h2 id="detailed_view_time_graph" class="mt-3 mb-4 mb-md-3 text-center">
				Data collected on <span class="text-nowrap detailed_view_selected_date"><?php echo date('Y-m-d'); ?></span>
				<a href="javascript:void(0)" data-toggle="tooltip" data-placement="right" title="Response times include the time to login to the CAS">*</a>
			</h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the screenshots taken when Unimia was down</p>
			<div style="height: 400px">
				<canvas id="detailed_view_time_canvas"></canvas>
			</div>
		</div>

		<!-- Jquery -->
		<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
		<!-- Bootstrap js (bundle version already includes Popper.js) -->
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
		<!-- Bootstrap datepicker js -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js" integrity="sha512-T/tUfKSV1bihCnd+MxKD0Hm1uBBroVYBOYSk1knyvQ9VyZJpc/ALb4P0r6ubwVPSGB2GvjeoMAJJImBG12TiaQ==" crossorigin="anonymous"></script>
		<!-- Chart.js (and its dependency Moment.js) -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js" integrity="sha512-d9xgZrVZpmmQlfonhQUvTR7lMPtO7NkZMkA0ABN3PHCbKA5nqylQ/yWlFAyY6hYgdF1Qh6nYiuADWwKB4C2WSw==" crossorigin="anonymous"></script>
		<!-- Chart.js Zoom Plugin (and its dependency Hammer.js) -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js" integrity="sha512-UXumZrZNiOwnTcZSHLOfcTs0aos2MzBWHXOHOuB0J/R44QB0dwY5JgfbvljXcklVf65Gc4El6RjZ+lnwd2az2g==" crossorigin="anonymous"></script>
		<script src="/js/chartjs-plugin-zoom.min.js"></script> <!-- Local edited version instead of CDN until they fix https://github.com/chartjs/chartjs-plugin-zoom/pull/429 -->
		<!-- Chart.js Matrix Plugin -->
		<script src="/js/chartjs-chart-matrix.min.js"></script>
		
		<!-- Charts Constructors -->
		<script src="/js/charts-constructors.js"></script>

		<!-- Week starts with Monday in all charts -->
		<script type="text/javascript">moment.updateLocale('en', {week: {dow: 1}});</script>

		<!-- Charts creation -->
		<script type="text/javascript">	
			<!-- Uptime donut charts -->
			var chart_ids = ['uptime1_canvas', 'uptime2_canvas', 'uptime3_canvas', 'uptime4_canvas'];
			var chart_datas = [
				[<?php echo $uptime_today[0]; ?>, <?php echo $uptime_today[1]; ?>],
				[<?php echo $uptime_week[0]; ?>, <?php echo $uptime_week[1]; ?>],
				[<?php echo $uptime_month[0]; ?>, <?php echo $uptime_month[1]; ?>],
				[<?php echo $uptime_all[0]; ?>, <?php echo $uptime_all[1]; ?>]
			];

			for (var i = 0; i < 4; i++) {
				chart_create_up_down_donuts(chart_ids[i], chart_datas[i][0], chart_datas[i][1]);
			}
		
			<!-- Daily uptime -->
			chart_create_daily(
				'daily_uptime_canvas',
				<?php echo json_encode(array_keys($daily_uptime_array)); ?>,
				<?php echo json_encode(array_values($daily_uptime_array)); ?>,
				'Uptime',
				'<?php echo max(date('Y-m-d', strtotime("-2 months")), $first_date); /* Starts zoomed in */ ?>',
				100,
				'%',
				'Uptime: ',
				'%',
				<?php echo strtotime($first_date)*1000; ?>,
				<?php echo microtime(true)*1000; ?>
			);
		
			<!-- Uptime heatmap -->
			chart_create_weekday_daytime_heatmap(
				'uptime_heatmap_canvas',
				<?php echo json_encode($uptime_heatmap_array); ?>,
				[
					{color:"#c70000", hover_color:"#b30000", min:00, max:50,  label: "<=50% uptime"},  // Red
					{color:"#e87d00", hover_color:"#cc6d00", min:51, max:60,  label: "51-60% uptime"}, // Orange
					{color:"#f0ca0f", hover_color:"#d8b60e", min:61, max:70,  label: "61-70% uptime"}, // Yellow
					{color:"#8fda3e", hover_color:"#75c125", min:71, max:80,  label: "71-80% uptime"}, // Light green
					{color:"#00b300", hover_color:"#009900", min:81, max:90,  label: "81-90% uptime"}, // Green
					{color:"#008000", hover_color:"#006600", min:91, max:100, label: ">90% uptime"}    // Dark green
				],
				'Uptime: ',
				'%'
			);
		
			<!-- Daily response time -->
			chart_create_daily(
				'daily_response_time_canvas',
				<?php echo json_encode(array_keys($daily_response_time_array)); ?>,
				<?php echo json_encode(array_values($daily_response_time_array)); ?>,
				'Avg response time',
				'<?php echo max(date('Y-m-d', strtotime("-2 months")), $first_date); /* Starts zoomed in */ ?>',
				undefined,
				' ms',
				'Response time: ',
				' ms',
				<?php echo strtotime($first_date)*1000; ?>,
				<?php echo microtime(true)*1000; ?>
			);
		
			<!-- Average response time heatmap -->
			chart_create_weekday_daytime_heatmap(
				'response_time_heatmap_canvas',
				<?php echo json_encode($response_time_heatmap_array); ?>,
				[
					{color:"#c70000", hover_color:"#b30000", min:15000, max:100000, label:">15s"},    // Red
					{color:"#e87d00", hover_color:"#cc6d00", min:9000, max:14999,   label:"9-14,9s"}, // Orange
					{color:"#f0ca0f", hover_color:"#d8b60e", min:6000, max:8999,    label:"6-8,9s"},  // Yellow
					{color:"#8fda3e", hover_color:"#75c125", min:4000, max:5999,    label:"4-5,9s"},  // Light green
					{color:"#00b300", hover_color:"#009900", min:3000, max:3999,    label:"3-3,9s"},  // Green
					{color:"#008000", hover_color:"#006600", min:0000, max:2999,    label:"<3s"}      // Dark green
				],
				'Avg response time: ',
				' ms'
			);
		</script>

		<!-- Detailed view calendar -->
		<script type="text/javascript">
			$('#detailed_view_calendar').datepicker({
				startDate: "<?php echo $first_date; ?>",
				endDate: "<?php echo date('Y-m-d'); ?>",
				format: "yyyy-mm-dd",
				todayHighlight: true,
				weekStart: 1
			});
			$("#detailed_view_calendar").datepicker("update", "<?php echo date('Y-m-d'); ?>");

			function detailed_view_change_date(date) {
				// Start the loading animation
				$('#loading_screen').fadeIn();

				// Start ajax request
				$.get("ajax.php?operation=detailed_view&date="+date, function(data) {
					// Parse data
					var data = data["data"];

					// Update uptime chart
					detailed_view_uptime_chart.data.datasets[0].data = data.uptime;
					detailed_view_uptime_chart.update();

					// Update response times
					$("#detailed_view_response_time_avg").text(data.response_avg);
					$("#detailed_view_response_time_min").text(data.response_min);
					$("#detailed_view_response_time_max").text(data.response_max);

					// Update time chart
					detailed_view_time_chart.data.labels = data.time_graph_labels;
					detailed_view_time_chart.data.datasets[0].data = data.time_graph_data;
					detailed_view_time_chart.data.datasets[0].reason = data.time_graph_reason;
					detailed_view_time_chart.data.datasets[0].screenshot = data.time_graph_screenshot;
					detailed_view_time_chart_update();

					// Update date in all h2 titles
					$(".detailed_view_selected_date").text(date);

					// Update the datapicker
					$("#detailed_view_calendar").datepicker("update", date);

					// Stop the loading animation
					$('#loading_screen').fadeOut();
				});
			}
			
			$('#detailed_view_calendar').datepicker().on("changeDate", function(e) {
				function pad(str, max) {
					str = str.toString();
					return str.length < max ? pad("0" + str, max) : str;
				}

				var year  = e.date.getFullYear();
				var month = pad(e.date.getMonth()+1, 2);
				var day   = pad(e.date.getDate(), 2);
				var date  = year + '-' + month + '-' + day;

				detailed_view_change_date(date);
			});
		</script>

		<!-- Detailed view uptime graph -->
		<script type="text/javascript">
			detailed_view_uptime_chart = chart_create_up_down_donuts(
				'detailed_view_uptime_canvas',
				<?php echo $detailed_view_uptime[0]; ?>,
				<?php echo $detailed_view_uptime[1]; ?>
			);
		</script>

		<!-- Detailed view time graph -->
		<script type="text/javascript">
			detailed_view_time_chart_pointBackgroundColors = [];

			detailed_view_time_chart = new Chart(document.getElementById('detailed_view_time_canvas'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode(array_keys($detailed_view_time_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_column($detailed_view_time_array, 'response_time')); ?>,
						pointBackgroundColor: detailed_view_time_chart_pointBackgroundColors,
						reason: <?php echo json_encode(array_column($detailed_view_time_array, 'reason')); ?>,
						screenshot: <?php
							$screenshot_array = [];
							foreach( array_keys($detailed_view_time_array) as $label ) {
								$label = str_replace(":", "-", $label);
								$label = str_replace(" ", "_", $label);
								$screenshot_filename = "screenshot/".date('Y-m-d')."_".$label.".jpg";
								if( file_exists($screenshot_filename) ) {
									array_push($screenshot_array, $screenshot_filename);
								} else {
									array_push($screenshot_array, "");
								}
							}
							echo json_encode(array_values($screenshot_array));
						?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {display: false},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Response time'
							},
							ticks: {
								callback: function(value, index, values) {
									return value + ' ms';
								}
							}
						}],
						xAxes: [{
							type: 'time',
							time: {
								parser: 'HH:mm:ss',
								unit: 'hour',
								displayFormats: {
									hour: 'HH:mm:ss'
								}
							},
							scaleLabel: {
								display: true,
								labelString: 'Hour'
							}
						}]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function(tooltipItems, data) {
								var value = data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index];
								var reason = data.datasets[tooltipItems.datasetIndex].reason[tooltipItems.index];
								var screenshot = data.datasets[tooltipItems.datasetIndex].screenshot[tooltipItems.index];
								if (value == 0) {
									var result = ['DOWN'];
									if (reason) {
										result.push('Reason: '+reason);
									}
									if (screenshot) {
										result.push('Screenshot available. Click to see!');
									}
									return result;
								} else {
									return ['UP', 'Response time: ' + value + ' ms'];
								}
							}
						}
					},
					plugins: {
						zoom: {
							pan: {
								enabled: true,
								mode: 'x'
							},
							zoom: {
								enabled: true,
								mode: 'x'
							}
						}
					}
				}
			});

			document.getElementById('detailed_view_time_canvas').onclick = function (evt) {
				var clicked_points = detailed_view_time_chart.getElementAtEvent(evt);
				if (clicked_points.length && detailed_view_time_chart.data.datasets[0].screenshot[clicked_points[0]._index]) {
					window.open(detailed_view_time_chart.data.datasets[0].screenshot[clicked_points[0]._index], '_blank');
				}
			};

			function detailed_view_time_chart_update() {
				detailed_view_time_chart.resetZoom();
				
				detailed_view_time_chart_pointBackgroundColors.length = 0;

				for (i = 0; i < detailed_view_time_chart.data.datasets[0].data.length; i++) {
					if (detailed_view_time_chart.data.datasets[0].data[i] == 0) {
						detailed_view_time_chart_pointBackgroundColors.push("red");
					} else {
						detailed_view_time_chart_pointBackgroundColors.push("green");
					}
				}

				detailed_view_time_chart.update();
			}

			detailed_view_time_chart_update();
		</script>
		
		<!-- Load tooltips -->
		<script type="text/javascript">
			$(function () {
				$('[data-toggle="tooltip"]').tooltip()
			})
		</script>

		<!-- Loading screen -->
		<div class="fixed-top w-100 h-100" style="background-color:rgba(179, 179, 179, 0.7); display:none" id="loading_screen">
			<div class="d-flex justify-content-center h-100">
				<div class="spinner-border my-auto text-primary" role="status" style="height:50vh; width:50vh">
					<span class="sr-only">Loading...</span>
				</div>
			</div>
		</div>
	</body>
</html>
