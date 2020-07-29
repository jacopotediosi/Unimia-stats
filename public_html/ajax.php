<?php
// Die if no operation is specified
if ( !isset($_GET['operation']) || !$_GET['operation'] )
	die('Error: no operation specified');

// DB Connection
$db = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));

// Detailed view operation
if ( $_GET['operation']=='detailed_view' ) {
	// Check date parameter
	if ( !isset($_GET['date']) || !preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $_GET['date']) ) {
		// Date parameter is wrong
		die('Error: wrong date format');
	} else {
		// Date parameter is ok, let's escape it
		$date = $db->real_escape_string($_GET['date']);

		// Get uptimes
		$uptime = $db->query("SELECT count(CASE WHEN is_up=1 THEN 1 END),count(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE date(datetime) = '$date'")->fetch_row();
		$uptime = [(int) $uptime[0], (int) $uptime[1]];

		// Get response times
		$response_avg = (int) $db->query("SELECT avg(response_time) FROM stats WHERE date(datetime) = '$date' AND is_up=1")->fetch_row()[0];
		$response_min = (int) $db->query("SELECT min(response_time) FROM stats WHERE date(datetime) = '$date' AND is_up=1")->fetch_row()[0];
		$response_max = (int) $db->query("SELECT max(response_time) FROM stats WHERE date(datetime) = '$date' AND is_up=1")->fetch_row()[0];

		// Get time graph datas
		$time_graph = $db->query("SELECT DATE_FORMAT(datetime, '%H:%i:%s') as time, response_time, reason FROM stats WHERE date(datetime) = '$date' ORDER by time ASC");
		$time_graph_array = [];
		while ($row = mysqli_fetch_assoc($time_graph)) {
			$time_graph_array[$row['time']] = [
				'response_time' => (int) $row['response_time'],
				'reason'        => utf8_encode($row['reason'])
			];
		}

		// Screenshot
		$time_graph_screenshot = [];
		foreach( array_keys($time_graph_array) as $label ) {
			$label = str_replace(":", "-", $label);
			$label = str_replace(" ", "_", $label);
			$screenshot_filename = "screenshot/".$date."_".$label.".jpg";
			if( file_exists($screenshot_filename) ) {
				array_push($time_graph_screenshot, $screenshot_filename);
			} else {
				array_push($time_graph_screenshot, "");
			}
		}

		// Output
		$output = array('uptime' => $uptime, 'response_avg' => $response_avg, 'response_min' => $response_min, 'response_max' => $response_max, 'time_graph_labels' => array_keys($time_graph_array), 'time_graph_data' => array_column($time_graph_array, 'response_time'), 'time_graph_reason' => array_column($time_graph_array, 'reason'), 'time_graph_screenshot' => array_values($time_graph_screenshot));
		die(json_encode($output));
	}
}

// Wrong operation specified
die('Error: wrong operation specified');
