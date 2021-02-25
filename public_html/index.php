<?php
// DB Connection
$db = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));

// N months
$n_months_long_term  = (int) getenv('N_MONTHS_LONG_TERM');  // Used when query/graph details day by day
$n_months_short_term = (int) getenv('N_MONTHS_SHORT_TERM'); // Used when query/graph summarizes by hours/days of week

// First date
$first_date   = $db->query("SELECT DATE(datetime) FROM stats ORDER BY datetime asc LIMIT 1")->fetch_row()[0];

// Uptime summary
$uptime_today = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 24 HOUR")->fetch_row();
$uptime_week  = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 7 DAY")->fetch_row();
$uptime_month = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE datetime >= NOW() - INTERVAL 30 DAY")->fetch_row();
$uptime_all   = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats")->fetch_row();

// Uptime in last n months
$uptime_last_n_months = $db->query("
SELECT t1.date,
100/(
	SELECT COUNT(*) FROM stats t2
	WHERE date(t2.datetime)=t1.date AND datetime >= NOW() - INTERVAL $n_months_long_term MONTH
)*(
	SELECT COUNT(*) FROM stats t2
	WHERE date(t2.datetime)=t1.date AND t2.is_up=1 AND datetime >= NOW() - INTERVAL $n_months_long_term MONTH
) as uptime_percentage
FROM (
	SELECT DISTINCT date(datetime) as date
	FROM stats
	WHERE datetime >= NOW() - INTERVAL $n_months_long_term MONTH
) t1
ORDER BY date ASC
");
$uptime_last_n_months_array = [];
while ($row = mysqli_fetch_assoc($uptime_last_n_months)) {
	$uptime_last_n_months_array[$row['date']] = $row['uptime_percentage'];
}

// Uptime time during week
$uptime_during_week = $db->query("
SELECT t1.dayname,
100/(
	SELECT COUNT(*) FROM stats t2
	WHERE dayname(t2.datetime)=t1.dayname AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
)*(
	SELECT COUNT(*) FROM stats t2 WHERE dayname(t2.datetime)=t1.dayname AND t2.is_up=1 AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) as uptime_percentage
FROM (
	SELECT DISTINCT dayname(datetime) as dayname
	FROM stats
	WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) t1
ORDER BY FIELD(dayname , 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday','Saturday', 'Sunday')
");
$uptime_during_week_array = [];
while ($row = mysqli_fetch_assoc($uptime_during_week)) {
	$uptime_during_week_array[$row['dayname']] = $row['uptime_percentage'];
}

// Uptime during day
$uptime_during_day = $db->query("
SELECT t1.hour,
100/(
	SELECT COUNT(*) FROM stats t2
	WHERE hour(t2.datetime)=t1.hour AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
)*(
	SELECT COUNT(*) FROM stats t2 WHERE hour(t2.datetime)=t1.hour AND t2.is_up=1 AND datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) as uptime_percentage
FROM (
	SELECT DISTINCT hour(datetime) as hour
	FROM stats
	WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH
) t1
ORDER BY hour ASC
");
$uptime_during_day_array = [];
while ($row = mysqli_fetch_assoc($uptime_during_day)) {
	$uptime_during_day_array[$row['hour']] = $row['uptime_percentage'];
}

// Response time summary
$response_time_today = (int) $db->query("SELECT avg(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 24 HOUR AND is_up=1")->fetch_row()[0];
$response_time_week  = (int) $db->query("SELECT avg(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 7 DAY AND is_up=1")->fetch_row()[0];
$response_time_month = (int) $db->query("SELECT avg(response_time) FROM stats WHERE datetime >= NOW() - INTERVAL 30 DAY AND is_up=1")->fetch_row()[0];
$response_time_all   = (int) $db->query("SELECT avg(response_time) FROM stats WHERE is_up=1")->fetch_row()[0];

// Response time in last n months
$response_time_last_n_months = $db->query("
SELECT date(datetime) AS date, avg(response_time) AS avg
FROM stats
WHERE datetime >= NOW() - INTERVAL $n_months_long_term MONTH AND is_up=1
GROUP BY date(datetime)
ORDER BY date ASC
");
$response_time_last_n_months_array = [];
while ($row = mysqli_fetch_assoc($response_time_last_n_months)) {
	$response_time_last_n_months_array[$row['date']] = $row['avg'];
}

// Response time during week
$response_time_during_week = $db->query("
SELECT DAYNAME(datetime) AS dayname, avg(response_time) AS avg
FROM stats
WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH AND is_up=1
GROUP BY DAYNAME(datetime)
ORDER BY FIELD(dayname , 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday','Saturday', 'Sunday')
");
$response_time_during_week_array = [];
while ($row = mysqli_fetch_assoc($response_time_during_week)) {
	$response_time_during_week_array[$row['dayname']] = $row['avg'];
}

// Response time during day
$response_time_during_day = $db->query("
SELECT hour(datetime) AS hour, avg(response_time) AS avg
FROM stats WHERE datetime >= NOW() - INTERVAL $n_months_short_term MONTH AND is_up=1
GROUP BY hour(datetime)
ORDER BY hour ASC
");
$response_time_during_day_array = [];
while ($row = mysqli_fetch_assoc($response_time_during_day)) {
	$response_time_during_day_array[$row['hour']] = $row['avg'];
}

// Detailed view uptime
$detailed_view_uptime = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE date(datetime) = date(NOW())")->fetch_row();

// Detailed view response time
$detailed_view_response_time_avg = (int) $db->query("SELECT avg(response_time) FROM stats WHERE date(datetime) = date(NOW()) AND is_up=1")->fetch_row()[0];
$detailed_view_response_time_min = (int) $db->query("SELECT min(response_time) FROM stats WHERE date(datetime) = date(NOW()) AND is_up=1")->fetch_row()[0];
$detailed_view_response_time_max = (int) $db->query("SELECT max(response_time) FROM stats WHERE date(datetime) = date(NOW()) AND is_up=1")->fetch_row()[0];

// Detailed view time graph
$detailed_view_time = $db->query("SELECT DATE_FORMAT(datetime, '%H:%i:%s') as time, response_time, reason FROM stats WHERE date(datetime) = date(NOW()) ORDER by time ASC");
$detailed_view_time_array = [];
while ($row = mysqli_fetch_assoc($detailed_view_time)) {
	$detailed_view_time_array[$row['time']] = [
		'response_time' => $row['response_time'],
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

		<style type="text/css">
			/* Navbar & scroll to anchor fix */
			html {
				scroll-padding-top: 56px;
			}
			body {
				padding-top: 56px;
			}
			/* Datepicker */
			.datepicker, .table-condensed {
				width: 300px;
				height: 300px;
			}
			/* Github corner */
			.github-corner {
				margin-top: 2px;
			}
			.github-corner:hover .octo-arm {
				animation:octocat-wave 560ms ease-in-out
			}
			.github-corner svg {
				fill:#343A40;
				color:#fff;
				border: 0;
			}
			@keyframes octocat-wave {
				0%,100% {transform:rotate(0)}
				20%,60% {transform:rotate(-25deg)}
				40%,80% {transform:rotate(10deg)}
			}
			@media (max-width:500px) {
				.github-corner:hover .octo-arm {animation:none}
				.github-corner .octo-arm {animation:octocat-wave 560ms ease-in-out}
			}
		</style>

<?php
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
				<script type="text/javascript" charset="UTF-8" src="//cdn.cookie-script.com/s/fa5c2a49f4aa070719c30eb57383ee68.js"></script>
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
						<div class="dropdown-menu">
							<a class="dropdown-item" href="#uptime_summary">Summary</a>
							<a class="dropdown-item" href="#uptime_last_n_months">In last 3 months</a>
							<a class="dropdown-item" href="#uptime_during_week">During week (last 1 month)</a>
							<a class="dropdown-item" href="#uptime_during_day">During day (last 1 month)</a>
						</div>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Response time</a>
						<div class="dropdown-menu">
							<a class="dropdown-item" href="#response_time_summary">Summary</a>
							<a class="dropdown-item" href="#response_time_last_n_months">In last 3 months</a>
							<a class="dropdown-item" href="#response_time_during_week">During week (last 1 month)</a>
							<a class="dropdown-item" href="#response_time_during_day">During day (last 1 month)</a>
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

			<!-- In last n months -->
			<h2 id="uptime_last_n_months" class="mt-3 mb-4 mb-md-3 text-center">In last <?php echo $n_months_long_term; echo ($n_months_long_term>1) ? ' months':' month'; ?></h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the detailed view of the dates</p>
			<div style="height: 300px">
				<canvas id="uptime_last_n_months_canvas"></canvas>
			</div>
			

			<div class="row text-center">
				<!-- During week -->
				<div class="col-lg-6">
					<h2 id="uptime_during_week" class="mt-3 mb-4 mb-md-3">During week (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
					<div style="height: 300px">
						<canvas id="uptime_during_week_canvas"></canvas>
					</div>
				</div>
				<!-- During day -->
				<div class="col-lg-6">
					<h2 id="uptime_during_day" class="mt-3 mb-4 mb-md-3">During day (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
					<div style="height: 300px">
						<canvas id="uptime_during_day_canvas"></canvas>
					</div>
				</div>
			</div>

			<!-- AVERAGE RESPONSE TIME -->
			<h1 class="mt-5 mb-4 mb-sm-5 display-4">Average response time (when up)*</h1>

			<!-- Summary -->
			<h2 id="response_time_summary" class="mt-3 mb-4 mb-md-3 text-center">Summary</h2>
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

			<!-- In last n months -->
			<h2 id="response_time_last_n_months" class="mt-3 mb-4 mb-md-3 text-center">In last <?php echo $n_months_long_term; echo ($n_months_long_term>1) ? ' months':' month'; ?></h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the detailed view of the dates</p>
			<div style="height: 300px">
				<canvas id="response_time_last_n_months_canvas"></canvas>
			</div>

			<div class="row text-center">
				<!-- During week -->
				<div class="col-lg-6">
					<h2 id="response_time_during_week" class="mt-3 mb-4 mb-md-3">During week (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
					<div style="height: 300px">
						<canvas id="response_time_during_week_canvas"></canvas>
					</div>
				</div>
				<!-- During day -->
				<div class="col-lg-6">
					<h2 id="response_time_during_day" class="mt-3 mb-4 mb-md-3">During day (last <?php echo $n_months_short_term; echo ($n_months_short_term>1) ? ' months':' month'; ?>)</h2>
					<div style="height: 300px">
						<canvas id="response_time_during_day_canvas"></canvas>
					</div>
				</div>
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
						<h2 id="detailed_view_response_time" class="mb-4 mb-md-3">Response time on <span class="text-nowrap detailed_view_selected_date"><?php echo date('Y-m-d'); ?>*</span></h2>
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
			<h2 id="detailed_view_time_graph" class="mt-3 mb-4 mb-md-3 text-center">Data collected on <span class="text-nowrap detailed_view_selected_date"><?php echo date('Y-m-d'); ?>*</span></h2>
			<p class="text-center text-muted mt-2">By clicking on the graph points you can see the screenshots taken when Unimia was down</p>
			<div style="height: 400px">
				<canvas id="detailed_view_time_canvas"></canvas>
			</div>

			<!-- Clarifications -->
			<p class="text-muted mt-4">*Response times include the time to login to the CAS</p>
		</div>

		<!-- Jquery -->
		<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
		<!-- Bootstrap JS (Bundle version already includes Popper.JS) -->
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
		<!-- Bootstrap datepicker JS -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js" integrity="sha512-T/tUfKSV1bihCnd+MxKD0Hm1uBBroVYBOYSk1knyvQ9VyZJpc/ALb4P0r6ubwVPSGB2GvjeoMAJJImBG12TiaQ==" crossorigin="anonymous"></script>
		<!-- Chart.js (and its dependency Moment.js) -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js" integrity="sha512-d9xgZrVZpmmQlfonhQUvTR7lMPtO7NkZMkA0ABN3PHCbKA5nqylQ/yWlFAyY6hYgdF1Qh6nYiuADWwKB4C2WSw==" crossorigin="anonymous"></script>
		<!-- Chart.js Zoom Plugin and its dependencies -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js" integrity="sha512-UXumZrZNiOwnTcZSHLOfcTs0aos2MzBWHXOHOuB0J/R44QB0dwY5JgfbvljXcklVf65Gc4El6RjZ+lnwd2az2g==" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/0.7.7/chartjs-plugin-zoom.min.js" integrity="sha512-8E9fPF4pjWxI0dpprpn4WYeciAMo2kh6xN0COFxvTfurMttjZzih/sBp+Fxu49Zr6IUSp4sqWY6KLecnqOCwxA==" crossorigin="anonymous"></script>

		<!-- Uptime pie charts -->
		<script type="text/javascript">
			chart_ids = ['uptime1_canvas', 'uptime2_canvas', 'uptime3_canvas', 'uptime4_canvas'];
			chart_datas = [
				[<?php echo $uptime_today[0]; ?>, <?php echo $uptime_today[1]; ?>],
				[<?php echo $uptime_week[0]; ?>, <?php echo $uptime_week[1]; ?>],
				[<?php echo $uptime_month[0]; ?>, <?php echo $uptime_month[1]; ?>],
				[<?php echo $uptime_all[0]; ?>, <?php echo $uptime_all[1]; ?>]
			];

			for (var i = 0; i < 4; i++) {
				new Chart(document.getElementById(chart_ids[i]), {
					type: 'doughnut',
					data: {
						labels: ['UP', 'DOWN'],
						datasets: [{
							data: chart_datas[i],
							backgroundColor: [
								'green',
								'red'
							]
						}]
					},
					options: {
						legend: {
							display: false
						},
						tooltips: {
							callbacks: {
								label: function(tooltipItems, data) {
									return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' times checked';
								}
							}
						}
					},
					plugins: [{
						beforeDraw: function (chart) {
							var width = chart.chart.width,
								height = chart.chart.height,
								ctx = chart.chart.ctx;

							ctx.restore();
							ctx.font = (height / 150).toFixed(2) + "em sans-serif";
							ctx.textBaseline = "middle";

							var text = Math.round(100/(chart.data.datasets[0].data[0]+chart.data.datasets[0].data[1])*chart.data.datasets[0].data[0])+"%",
								textX = Math.round((width - ctx.measureText(text).width) / 2),
								textY = height / 2 - (chart.titleBlock.height - 3);

							ctx.fillText(text, textX, textY);
							ctx.save();
						}
					}]
				});
			}
		</script>

		<!-- Uptime in last n months graph -->
		<script type="text/javascript">
			uptime_last_n_months_chart = new Chart(document.getElementById('uptime_last_n_months_canvas'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode(array_keys($uptime_last_n_months_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($uptime_last_n_months_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Uptime'
							},
							ticks: {
								max: 100,
								callback: function(value, index, values) {
									return value + '%';
								}
							}
						}],
						xAxes: [{
							type: 'time',
							time: {
								parser: 'YYYY-MM-DD',
								unit: 'day',
								displayFormats: {
									day: 'YYYY-MM-DD'
								}
							},
							scaleLabel: {
								display: true,
								labelString: 'Date'
							},
							ticks: {
								min: '<?php echo max(date('Y-m-d', strtotime("-2 months")), $first_date); ?>' // Start zoomed in to show the last 2 months
							}
						}]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function(tooltipItems, data) {
								return [
									'Uptime: ' + data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + '%',
									'Click to see this date details'
								];
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
								mode: 'x',
							}
						}
					}
				}
			});

			document.getElementById('uptime_last_n_months_canvas').onclick = function (evt) {
				var clicked_points = uptime_last_n_months_chart.getElementAtEvent(evt);
				if (clicked_points.length) {
					detailed_view_change_date(uptime_last_n_months_chart.data.labels[clicked_points[0]._index]);
					$('html,body').animate({
						'scrollTop':   $('#detailed_view_uptime').offset().top-56
					}, 'slow');
				}
			};
		</script>

		<!-- Uptime during week graph -->
		<script type="text/javascript">
			new Chart(document.getElementById('uptime_during_week_canvas'), {
				type: 'bar',
				data: {
					labels: <?php echo json_encode(array_keys($uptime_during_week_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($uptime_during_week_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Uptime'
							},
							ticks: {
								max: 100,
								callback: function(value, index, values) {
									return value + '%';
								}
							}
						}],
						xAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Day'
							}
						}]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function(tooltipItems, data) {
								return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + '%';
							}
						}
					}
				}
			});
		</script>

		<!-- Uptime during day graph -->
		<script type="text/javascript">
			new Chart(document.getElementById('uptime_during_day_canvas'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode(array_keys($uptime_during_day_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($uptime_during_day_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Uptime'
							},
							ticks: {
								max: 100,
								callback: function(value, index, values) {
									return value + '%';
								}
							}
						}],
						xAxes: [{
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
								return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + '%';
							}
						}
					}
				}
			});
		</script>

		<!-- Average response time in last n months graph -->
		<script type="text/javascript">
			response_time_last_n_months_chart = new Chart(document.getElementById('response_time_last_n_months_canvas'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode(array_keys($response_time_last_n_months_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($response_time_last_n_months_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Avg response time'
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
								parser: 'YYYY-MM-DD',
								unit: 'day',
								displayFormats: {
									day: 'YYYY-MM-DD'
								}
							},
							scaleLabel: {
								display: true,
								labelString: 'Date'
							},
							ticks: {
								min: '<?php echo max(date('Y-m-d', strtotime("-2 months")), $first_date); ?>' // Start zoomed in to show the last 2 months
							}
						}]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function(tooltipItems, data) {
								return [
									'Response time: ' + data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' ms',
									'Click to see this date details'
								];
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
								mode: 'x',
							}
						}
					}
				}
			});
		
			document.getElementById('response_time_last_n_months_canvas').onclick = function (evt) {
				var clicked_points = response_time_last_n_months_chart.getElementAtEvent(evt);
				if (clicked_points.length) {
					detailed_view_change_date(response_time_last_n_months_chart.data.labels[clicked_points[0]._index]);
					$('html,body').animate({
						'scrollTop':   $('#detailed_view_uptime').offset().top-56
					}, 'slow');
				}
			};
		</script>

		<!-- Average response time during week graph -->
		<script type="text/javascript">
			new Chart(document.getElementById('response_time_during_week_canvas'), {
				type: 'bar',
				data: {
					labels: <?php echo json_encode(array_keys($response_time_during_week_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($response_time_during_week_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Avg response time'
							},
							ticks: {
								callback: function(value, index, values) {
									return value + ' ms';
								}
							}
						}],
						xAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Day'
							}
						}]
					},
					tooltips: {
						displayColors: false,
						callbacks: {
							label: function(tooltipItems, data) {
								return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' ms';
							}
						}
					}
				}
			});
		</script>

		<!-- Average response time during day graph -->
		<script type="text/javascript">
			new Chart(document.getElementById('response_time_during_day_canvas'), {
				type: 'line',
				data: {
					labels: <?php echo json_encode(array_keys($response_time_during_day_array)); ?>,
					datasets: [{
						data: <?php echo json_encode(array_values($response_time_during_day_array)); ?>
					}]
				},
				options: {
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					scales: {
						yAxes: [{
							scaleLabel: {
								display: true,
								labelString: 'Avg response time'
							},
							ticks: {
								callback: function(value, index, values) {
									return value + ' ms';
								}
							}
						}],
						xAxes: [{
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
								return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' ms';
							}
						}
					}
				}
			});
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
			detailed_view_uptime_chart = new Chart(document.getElementById('detailed_view_uptime_canvas'), {
				type: 'doughnut',
				data: {
					labels: ['UP', 'DOWN'],
					datasets: [{
						data: [<?php echo $detailed_view_uptime[0]; ?>, <?php echo $detailed_view_uptime[1]; ?>],
						backgroundColor: [
							'green',
							'red'
						]
					}]
				},
				options: {
					legend: {
						display: false
					},
					tooltips: {
						callbacks: {
							label: function(tooltipItems, data) {
								return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' times checked';
							}
						}
					}
				},
				plugins: [{
					beforeDraw: function (chart) {
						var width = chart.chart.width,
							height = chart.chart.height,
							ctx = chart.chart.ctx;

						ctx.restore();
						ctx.font = (height / 150).toFixed(2) + "em sans-serif";
						ctx.textBaseline = "middle";

						var text = Math.round(100/(chart.data.datasets[0].data[0]+chart.data.datasets[0].data[1])*chart.data.datasets[0].data[0])+"%",
							textX = Math.round((width - ctx.measureText(text).width) / 2),
							textY = height / 2 - (chart.titleBlock.height - 3);

						ctx.fillText(text, textX, textY);
						ctx.save();
					}
				}]
			});
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
					legend: {
						display: false
					},
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
									return 'Response time: ' + value + ' ms';
								}
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
