<?php
// Response will be in json format
header("Content-Type: application/json");

// API error format is { "error":{"code":123,"message":"xxx"} }
function api_error_die($http_status_code, $error_message) {
	http_response_code($http_status_code);
	die( json_encode(["error" => ["code"=>$http_status_code, "message"=>$error_message]]) );
}

// API success format is { "data":{...} }
function api_success_die($data) {
	die( json_encode(["data" => $data]) );
}

// Die if no operation is specified
if ( !isset($_GET['operation']) || !$_GET['operation'] )
	api_error_die(400, "Missing or empty 'operation' parameter");

// Open DB Connection
$db = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE'));

// Check operation
if ( $_GET['operation']==='detailed_view' ) {
	// Detailed view operation
	
	// Check date parameter
	if ( !isset($_GET['date']) || !preg_match('/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/', $_GET['date']) ) {
		// Date parameter is wrong
		api_error_die(400, "Missing date parameter or wrong date format");
	} else {
		// Get uptimes
		$uptime = $db->prepare("SELECT COUNT(CASE WHEN is_up=1 THEN 1 END),COUNT(CASE WHEN is_up=0 THEN 1 END) FROM stats WHERE date_datetime = ?");
		$uptime->bind_param('s', $_GET['date']);
		$uptime->execute();
		$uptime = $uptime->get_result()->fetch_row();
		$uptime = [(int) $uptime[0], (int) $uptime[1]];

		// Get response times
		$response = $db->prepare("SELECT AVG(response_time) AS avg, MIN(response_time) AS min, MAX(response_time) AS max FROM stats WHERE date_datetime = ? AND is_up=1");
		$response->bind_param('s', $_GET['date']);
		$response->execute();
		$response = $response->get_result()->fetch_assoc();
		$response_avg = (int) $response["avg"];
		$response_min = (int) $response["min"];
		$response_max = (int) $response["max"];

		// Get time graph datas
		$time_graph = $db->prepare("SELECT DATE_FORMAT(datetime, '%H:%i:%s') AS time, response_time, reason FROM stats WHERE date_datetime = ?");
		$time_graph->bind_param('s', $_GET['date']);
		$time_graph->execute();
		$time_graph = $time_graph->get_result();
		$time_graph_array = [];
		while ($row = $time_graph->fetch_assoc()) {
			$time_graph_array[$row['time']] = [
				'response_time' => (int) $row['response_time'],
				'reason'        => utf8_encode($row['reason'])
			];
		}

		// Screenshot
		$time_graph_screenshot = [];
		foreach( array_keys($time_graph_array) as $time_graph_array_time ) {
			$time_graph_array_time = str_replace(":", "-", $time_graph_array_time);
			$time_graph_array_time = str_replace(" ", "_", $time_graph_array_time);
			$screenshot_filename = "screenshot/".$_GET['date']."_".$time_graph_array_time.".jpg";
			if( file_exists($screenshot_filename) ) {
				array_push($time_graph_screenshot, $screenshot_filename);
			} else {
				array_push($time_graph_screenshot, "");
			}
		}

		// Output
		api_success_die(array(
			'uptime'                => $uptime,
			'response_avg'          => $response_avg,
			'response_min'          => $response_min,
			'response_max'          => $response_max,
			'time_graph_labels'     => array_keys($time_graph_array),
			'time_graph_data'       => array_column($time_graph_array, 'response_time'),
			'time_graph_reason'     => array_column($time_graph_array, 'reason'),
			'time_graph_screenshot' => array_values($time_graph_screenshot)
		));
	}
} elseif ( $_GET['operation']==='uptime_heatmap' ) {
	// Uptime heatmap operation
	
	// Check daterange parameter
	if (isset($_GET['daterange'])) {
		// Initialize uptime_heatmap_array
		$uptime_heatmap_array = [];
		
		// Where condition based on the daterange
		switch($_GET['daterange']) {
			case 'current-month':
				$where_condition = "datetime >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH AND datetime < LAST_DAY(CURDATE()) + INTERVAL 1 DAY";
				break;
			case 'previous-month':
				$where_condition = "datetime >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH AND datetime < LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH";
				break;
			case 'last-30-days':
				$where_condition = "datetime >= NOW() - INTERVAL 30 DAY";
				break;
			case 'last-60-days':
				$where_condition = "datetime >= NOW() - INTERVAL 60 DAY";
				break;
			case 'last-90-days':
				$where_condition = "datetime >= NOW() - INTERVAL 90 DAY";
				break;
			case 'last-180-days':
				$where_condition = "datetime >= NOW() - INTERVAL 180 DAY";
				break;
			case 'ever':
				$where_condition = "1=1";
				break;
			default:
				// Wrong daterange parameter
				api_error_die(400, "Wrong daterange parameter");
		}
		
		// Do the query
		$uptime_heatmap = $db->query("
			SELECT t1.dayname, t1.hour_datetime, 
			100/(
				SELECT COUNT(*) FROM stats t2
				WHERE dayname(t2.datetime)=t1.dayname AND t2.hour_datetime=t1.hour_datetime AND $where_condition
			)*(
				SELECT COUNT(*) FROM stats t2 WHERE dayname(t2.datetime)=t1.dayname AND t2.hour_datetime=t1.hour_datetime AND t2.is_up=1 AND $where_condition
			) as uptime_percentage
			FROM (
				SELECT DISTINCT dayname(datetime) as dayname, hour_datetime
				FROM stats
				WHERE $where_condition
			) t1
		");
		// Fill the uptime_heatmap_array
		while ($row = mysqli_fetch_assoc($uptime_heatmap)) {
			$uptime_heatmap_array[$row['dayname']][$row['hour_datetime']] = (double) $row['uptime_percentage'];
		}
		
		// Output
		api_success_die(array(
			'uptime_heatmap_array' => $uptime_heatmap_array
		));
	} else {
		// Missing daterange parameter
		api_error_die(400, "Missing daterange parameter");
	}
} elseif ( $_GET['operation']==='response_time_heatmap' ) {
	// Uptime heatmap operation
	
	// Check daterange parameter
	if (isset($_GET['daterange'])) {
		// Initialize uptime_heatmap_array
		$response_time_heatmap_array = [];
		
		// Where condition based on the daterange
		switch($_GET['daterange']) {
			case 'current-month':
				$where_condition = "datetime >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH AND datetime < LAST_DAY(CURDATE()) + INTERVAL 1 DAY";
				break;
			case 'previous-month':
				$where_condition = "datetime >= LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH AND datetime < LAST_DAY(CURDATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH";
				break;
			case 'last-30-days':
				$where_condition = "datetime >= NOW() - INTERVAL 30 DAY";
				break;
			case 'last-60-days':
				$where_condition = "datetime >= NOW() - INTERVAL 60 DAY";
				break;
			case 'last-90-days':
				$where_condition = "datetime >= NOW() - INTERVAL 90 DAY";
				break;
			case 'last-180-days':
				$where_condition = "datetime >= NOW() - INTERVAL 180 DAY";
				break;
			case 'ever':
				$where_condition = "1=1";
				break;
			default:
				// Wrong daterange parameter
				api_error_die(400, "Wrong daterange parameter");
		}
		
		// Do the query
		$response_time_heatmap = $db->query("
			SELECT DAYNAME(datetime) AS dayname, hour_datetime, AVG(response_time) AS avg
			FROM stats
			WHERE $where_condition AND is_up=1
			GROUP BY DAYNAME(datetime), hour_datetime
		");
		// Fill the uptime_heatmap_array
		while ($row = mysqli_fetch_assoc($response_time_heatmap)) {
			$response_time_heatmap_array[$row['dayname']][$row['hour_datetime']] = (double) $row['avg'];
		}
		
		// Output
		api_success_die(array(
			'response_time_heatmap_array' => $response_time_heatmap_array
		));
	} else {
		// Missing daterange parameter
		api_error_die(400, "Missing daterange parameter");
	}
}

// Unknown operation specified
api_error_die(400, "Unknown operation specified");
