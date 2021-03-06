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

function chart_plugin_percentage_in_donut_hole(chart) {
	/* Usage: beforeDraw: function(chart) {chart_plugin_percentage_in_donut_hole(chart);} */
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