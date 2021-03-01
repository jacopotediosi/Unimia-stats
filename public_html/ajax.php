<?php
header("Content-Type: application/json");

function api_error_die($http_status_code, $error_message) {
	http_response_code($http_status_code);
	die( json_encode(["error" => ["code"=>$http_status_code, "message"=>$error_message]]) );
}

function api_success_die($data) {
	die( json_encode(["data" => $data]) );
}

// Die if no operation is specified
if ( !isset($_GET['operation']) || !$_GET['operation'] )
	api_error_die(400, "Missing or empty 'operation' parameter");

// DB Connection
$db = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));

// Detailed view operation
if ( $_GET['operation']==='detailed_view' ) {
	// Check date parameter
	if ( !isset($_GET['date']) || !preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $_GET['date']) ) {
		// Date parameter is wrong
		api_error_die(400, "Missing date parameter or wrong date format");
	} else {
		// Date parameter is ok, let's escape it
		$date = $db->real_escape_string($_GET['date']);

		// Get uptimes
		$uptime = $db->query("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE date_datetime = '$date'")->fetch_row();
		$uptime = [(int) $uptime[0], (int) $uptime[1]];

		// Get response times
		$response_avg = (int) $db->query("SELECT AVG(response_time) FROM stats WHERE date_datetime = '$date' AND is_up=1")->fetch_row()[0];
		$response_min = (int) $db->query("SELECT MIN(response_time) FROM stats WHERE date_datetime = '$date' AND is_up=1")->fetch_row()[0];
		$response_max = (int) $db->query("SELECT MAX(response_time) FROM stats WHERE date_datetime = '$date' AND is_up=1")->fetch_row()[0];

		// Get time graph datas
		$time_graph = $db->query("SELECT DATE_FORMAT(datetime, '%H:%i:%s') AS time, response_time, reason FROM stats WHERE date_datetime = '$date'");
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
		api_success_die($output);
	}
}

// Wrong operation specified
api_error_die(400, "Unknown operation specified");
