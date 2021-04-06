/* CHARTS CONSTRUCTORS */

function chart_create_up_down_donuts(canvas_id, n_up, n_down) {
	return new Chart(document.getElementById(canvas_id), {
		type: 'doughnut',
		data: {
			labels: ['UP', 'DOWN'],
			datasets: [{
				data: [n_up, n_down],
				backgroundColor: ['green', 'red']
			}]
		},
		options: {
			defaultFontColor: localStorage.getItem('theme')==='dark' ? 'rgba(255, 255, 255, 0.87)':'#000',
			elements: {
				arc: {
					borderColor: localStorage.getItem('theme')==='dark' ? '#121212':'#FFF'
				}
			},
			legend: {display: false},
			tooltips: {
				callbacks: {
					label: function(tooltipItems, data) {
						return data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + ' times checked';
					}
				}
			}
		},
		plugins: {
			beforeDraw: function(chart) {
				chart_plugin_percentage_in_donut_hole(chart);
			}
		}
	});
}

function chart_create_weekday_daytime_heatmap(canvas_id, heatmap_array, heatmap_legend, tooltip_pre_label, tooltip_post_label) {
	// Inizialize datasets
	var heatmap_datasets = [];
	for(i in heatmap_legend) {
		heatmap_datasets.push({
			data: [],
			label: heatmap_legend[i].label,
			backgroundColor: heatmap_legend[i].color,
			hoverBackgroundColor: heatmap_legend[i].hover_color,
			width: function(c) {
				const a = c.chart.chartArea || {};
				return (a.right - a.left) / 24 - 1;
			},
			height: function(c) {
				const a = c.chart.chartArea || {};
				return (a.bottom - a.top) / 7 - 1;
			}
		});
	}

	// Fill datasets with actual data
	for(day in heatmap_array) {
		for(hour in heatmap_array[day]) {
			// Get the value
			var value = heatmap_array[day][hour];
			// Select the right color from the legend
			for(legend in heatmap_legend) {
				if(Math.round(value)>=heatmap_legend[legend].min && Math.round(value)<=heatmap_legend[legend].max) {
					// Add to the right dataset
					heatmap_datasets[legend].data.push({x:hour, y:day, v:value});
					break;
				}
			}
		}
	}
	
	return new Chart(document.getElementById(canvas_id), {
		type: 'matrix',
		data: {
			datasets: heatmap_datasets
		},
		options: {
			maintainAspectRatio: false,
			tooltips: {
				displayColors: false,
				callbacks: {
					title: function(tooltipItems, data) {
						var hovered_item = data.datasets[tooltipItems[0].datasetIndex].data[tooltipItems[0].index];
						return hovered_item.y + ' between ' + ("0"+hovered_item.x).slice(-2) + ':00 and '+ (("0"+(parseInt(hovered_item.x)+1)).slice(-2)) + ':00';
					},
					label: function(tooltipItems, data) {
						var hovered_item = data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index];
						return tooltip_pre_label + hovered_item.v + tooltip_post_label;
					}
				}
			},
			scales: {
				xAxes: [{
					type: 'time',
					offset: true,
					time: {
						parser: 'HH',
						unit: 'hour',
						displayFormats: {
							hour: 'HH'
						}
					},
					ticks: {
						padding: 10
					},
					gridLines: {
						display: false,
						tickMarkLength: 0,
					},
					scaleLabel: {
						display: true,
						labelString: 'Daytime'
					}
				}],
				yAxes: [{
					type: 'time',
					offset: true,
					time: {
						unit: 'day',
						parser: 'dddd',
						displayFormats: {
							day: 'ddd'
						}
					},
					position: 'left',
					ticks: {
						reverse: true,
						padding: 10
					},
					gridLines: {
						display: false,
						tickMarkLength: 0
					},
					scaleLabel: {
						display: true,
						labelString: 'Weekday'
					}
				}]
			}
		}
	});
}

function chart_create_daily(canvas_id, labels, data, y_labelString, x_ticks_min, y_ticks_max, y_ticks_postfix, tooltip_pre_label, tooltip_post_label, rangeMin, rangeMax) {
	var new_chart = new Chart(document.getElementById(canvas_id), {
		type: 'line',
		data: {
			labels: labels,
			datasets: [{
				data: data
			}]
		},
		options: {
			maintainAspectRatio: false,
			legend: {display: false},
			scales: {
				yAxes: [{
					scaleLabel: {
						display: true,
						labelString: y_labelString
					},
					ticks: {
						max: y_ticks_max,
						callback: function(value, index, values) {
							return value + y_ticks_postfix;
						}
					}
				}],
				xAxes: [{
					type: 'time',
					time: {
						parser: 'YYYY-MM-DD',
						unit: 'day',
						displayFormats: {
							day: 'DD/MM/YYYY'
						},
						tooltipFormat: 'dddd DD/MM/YYYY'
					},
					scaleLabel: {
						display: true,
						labelString: 'Date'
					},
					ticks: {
						min: x_ticks_min // How much it should starts zoomed in
					}
				}]
			},
			tooltips: {
				displayColors: false,
				callbacks: {
					label: function(tooltipItems, data) {
						return [
							tooltip_pre_label + data.datasets[tooltipItems.datasetIndex].data[tooltipItems.index] + tooltip_post_label,
							'Click to see this date details'
						];
					}
				}
			},
			plugins: {
				zoom: {
					pan: {
						enabled: true,
						mode: 'x',
						rangeMin: {x: rangeMin},
						rangeMax: {x: rangeMax}
					},
					zoom: {
						enabled: true,
						mode: 'x',
						rangeMin: {x: rangeMin},
						rangeMax: {x: rangeMax}
					}
				}
			}
		}
	});
	
	document.getElementById(canvas_id).onclick = function (evt) {
		var clicked_points = new_chart.getElementAtEvent(evt);
		if (clicked_points.length) {
			detailed_view_change_date(new_chart.data.labels[clicked_points[0]._index]);
			$('html,body').animate({
				'scrollTop':   $('#detailed_view_uptime').offset().top-56
			}, 'slow');
		}
	};
	
	return new_chart;
}

/* CHARTS PLUGINS */

function chart_plugin_percentage_in_donut_hole(chart) {
	/* Usage: beforeDraw: function(chart) {chart_plugin_percentage_in_donut_hole(chart);} */
	var width = chart.chart.width,
		height = chart.chart.height,
		ctx = chart.chart.ctx;

	ctx.restore();
	ctx.font = (height / 150).toFixed(2) + "em sans-serif";
	ctx.textBaseline = "middle";
	ctx.fillStyle = chart.options.defaultFontColor;

	var text = Math.round(100/(chart.data.datasets[0].data[0]+chart.data.datasets[0].data[1])*chart.data.datasets[0].data[0])+"%",
		textX = Math.round((width - ctx.measureText(text).width) / 2),
		textY = height / 2 - (chart.titleBlock.height - 3);

	ctx.fillText(text, textX, textY);
	ctx.save();
}

/* COMMON FUNCTIONS */

function start_loading_screen() {
	$('#loading_screen').fadeIn();
}

function stop_loading_screen() {
	$('#loading_screen').fadeOut();
}

/* CHARTS AJAX UPDATE HANDLERS */

function detailed_view_change_date(date) {
	// Start the loading animation
	start_loading_screen();

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
		detailed_view_time_chart.data.labels                 = data.time_graph_labels;
		detailed_view_time_chart.data.datasets[0].data       = data.time_graph_data;
		detailed_view_time_chart.data.datasets[0].reason     = data.time_graph_reason;
		detailed_view_time_chart.data.datasets[0].screenshot = data.time_graph_screenshot;
		detailed_view_time_chart_update();

		// Update date in all h2 titles
		$(".detailed_view_selected_date").text(date);

		// Update the datapicker
		$("#detailed_view_calendar").datepicker("update", date);

		// Stop the loading animation
		stop_loading_screen();
	});
}

/* THEME FUNCTIONS */
function changeTheme(theme) {
	if (theme=='dark') {
		// Apply dark theme
		$('head meta[name=color-scheme]').attr('content', 'dark');
		
		// Enable dark CSS
		$("#bootstrap_dark_css").prop("disabled", false);
		$("#main_dark_css").prop("disabled", false);
		
		// Charts colors
		Chart.defaults.global.defaultFontColor="rgba(255, 255, 255, 0.87)";
		Chart.helpers.each(Chart.instances, function(instance){
			instance.chart.options.defaultFontColor               = 'rgba(255, 255, 255, 0.87)';
			instance.chart.options.elements.arc.borderColor       = '#121212';
			instance.chart.options.elements.line.borderColor      = 'rgba(255,255,255,0.1)';
			instance.chart.options.elements.line.backgroundColor  = 'rgba(255,255,255,0.1)';
			instance.chart.options.elements.point.borderColor     = 'rgba(255,255,255,0.2)';
			instance.chart.options.elements.point.backgroundColor = 'rgba(255,255,255,0.2)';
			if(instance.chart.options.scales) {
				instance.chart.options.scales.xAxes[0].gridLines.color         = 'rgba(255,255,255,0.1)';
				instance.chart.options.scales.xAxes[0].gridLines.zeroLineColor = 'rgba(255,255,255,0.25)';
				instance.chart.options.scales.yAxes[0].gridLines.color         = 'rgba(255,255,255,0.1)';
				instance.chart.options.scales.yAxes[0].gridLines.zeroLineColor = 'rgba(255,255,255,0.25)';
			}
		});
		
		// Save new theme preference
		localStorage.setItem('theme', 'dark');
	} else {
		// Apply light theme
		$('head meta[name=color-scheme]').attr('content', 'light');
		
		// Disable dark CSS
		$("#bootstrap_dark_css").prop("disabled", true);
		$("#main_dark_css").prop("disabled", true);
		
		// Charts colors
		Chart.defaults.global.defaultFontColor="#000";
		Chart.helpers.each(Chart.instances, function(instance){
			instance.chart.options.defaultFontColor               = '#000';
			instance.chart.options.elements.arc.borderColor       = '#FFF';
			instance.chart.options.elements.line.borderColor      = 'rgba(0,0,0,0.1)';
			instance.chart.options.elements.line.backgroundColor  = 'rgba(0,0,0,0.1)';
			instance.chart.options.elements.point.borderColor     = 'rgba(0,0,0,0.1)';
			instance.chart.options.elements.point.backgroundColor = 'rgba(0,0,0,0.1)';
			if(instance.chart.options.scales) {
				instance.chart.options.scales.xAxes[0].gridLines.color         = 'rgba(0,0,0,0.1)';
				instance.chart.options.scales.xAxes[0].gridLines.zeroLineColor = 'rgba(0,0,0,0.25)';
				instance.chart.options.scales.yAxes[0].gridLines.color         = 'rgba(0,0,0,0.1)';
				instance.chart.options.scales.yAxes[0].gridLines.zeroLineColor = 'rgba(0,0,0,0.25)';
			}
		});
		
		// Save new theme preference
		localStorage.setItem('theme', 'light');
	}

	// Force updates to all charts
	Chart.helpers.each(Chart.instances, function(instance) {
		instance.chart.update();
	});
}