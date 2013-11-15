<?php
	$quoted_types = array("'chart'", "'table'", "'source'", "'csv'");
	$topic_pairs = array();
	$i = 0;
	foreach ($topics as $topic_simplified => $topic_title) {
		$topic_pairs[] = "\n\t\t\t{'simplified': '$topic_simplified', 'title': '$topic_title'}";
		$i++;
		if ($i > 2) {
			//break; // If topics are going to be limited to just the first few for testing
		}
	}
	$counties_count = 0;
	foreach ($states as $state_id => $state_name) {
		$county_definitions = array();
		$i = 0;
		//$counties_count += count($counties[$state_id]);
		foreach ($counties[$state_id] as $county_id => $county_name) {
			$county_definitions[] = "{'id': '$county_id', 'name': '$county_name'}";
			$counties_count++;
			$i++;
			if ($i > 5) {
				//break; // If counties are going to be limited to just the first few per state for testing
			}
		}
		$state_definitions[] = "{'id': '$state_id', 'name': '$state_name', 'counties': [".implode(', ', $county_definitions)."]}";
	}
	$total_steps = count($states) * count($topic_pairs) * count($quoted_types) * $counties_count;
?>
<style>
	#populate_cache_monitor td {border-bottom: 1px dotted #ccc; padding: 3px; vertical-align: top;}
</style>
<p>
	This process crawls through all of the possible charts, tables, CSV files, and lists of sources, generates 
	them, and makes sure they're cached in the Reports table. Reports are also automatically generated and cached
	when viewed by users, but this process... 
</p>
<ul>
	<li>Keeps users from having to wait an extra split-second (for initial generation and caching) the first time they view a report,</li>
	<li>Points out errors that may need administrators' attention, such as caching errors or 'missing data' errors</li>
</ul>
<p>
	<?php if ($this->requestAction(array('controller' => 'reports', 'action' => 'is_caching_enabled'))): ?>
		Caching is <span style="color: green; font-weight: bold;">ENABLED</span>. You're good to go.
	<?php else: ?>
		Caching is <span style="color: red; font-weight: bold;">DISABLED</span>. Until you switch ReportsController::cache_reports to TRUE in /app/controllers/reports_controller.php, this process is only good for discovering errors.
	<?php endif; ?>
</p>
<input type="button" value="Start" id="activation_button" />
<input type="hidden" value="0" id="process_active" />
<input type="hidden" value="0" id="completed_steps" />
<input type="hidden" value="0" id="time_started" />
<input type="hidden" value="0" id="time_spent" />
<input type="hidden" value="<?php echo $total_steps; ?>" id="total_steps" />
<br />
<table style="width: 100%; height: 5px; border: 1px solid black;"><tr><td id="progress_bar_shaded" style="background-color: black; width: 0%;"></td><td></td></tr></table>
<table id="populate_cache_monitor">
	<tr>
		<td>Total reports: </td>
		<td>
			<input type="text" value="<?php echo number_format($total_steps); ?>" style="width: 200px;" />
			<span style="font-size: 8pt;">
				<?php echo count($states).' states, '.count($topic_pairs).' topics, '.count($quoted_types).' report types, and '.$counties_count.' counties'; ?>
			</span>
		</td>
	</tr>
	<tr><td>Percent Done: </td><td><input type="text" id="percent_done" value="0" style="width: 200px;" /></td></tr>
	<tr><td>Estimated total time: </td><td><input type="text" id="time_total" value="0" style="width: 200px;" /></td></tr>
	<tr><td>Time spent / left: </td><td><input type="text" id="time_spent_display" value="0" style="width: 100px;" />/<input type="text" id="time_left" value="0" style="width: 100px;" /></td></tr>
	<tr><td>State: </td><td><input type="text" id="current_state" value="" style="width: 300px;" /></td></tr>
	<tr><td>County: </td><td><input type="text" id="current_county" value="" style="width: 300px;" /></td></tr>
	<tr><td>Topic: </td><td><input type="text" id="current_topic" value="" style="width: 300px;" /></td></tr>
	<tr><td>Report: </td><td><input type="text" id="current_report" value="" style="width: 150px;" /><input type="text" id="current_error_code" value="" style="width: 150px;" /></td></tr>
	<tr><td>Alerts: </td><td><textarea id="alerts" style="height: 150px; font-size: 10px; width: 500px;"></textarea></td></tr>
</table>

<script type="text/javascript">
	$('process_active').value = $('completed_steps').value = $('time_spent').value = 0;
	$('time_started').value = $('percent_done').value = $('time_left').value = $('time_total').value = $('time_spent_display').value = '';
	$('activation_button').onclick = function() {
		activation_button_onclick();
	}

	var states = [<?php echo implode(', ', $state_definitions); ?>];
	var topics = [<?php echo implode(', ', $topic_pairs); ?>];
	var types = [<?php echo implode(', ', $quoted_types); ?>];

	var state_iter = 0;
	var county_iter = 0;
	var topic_iter = 0;
	var type_iter = 0;

	var active = 1;

	var total_steps = <?php echo $total_steps; ?>;
	var completed_steps = 0;
	
	function activation_button_onclick() {
		//var active = $('process_active');
		if ($('process_active').value == 1) {
			//alert('Currently active. Stopping.');
			$('process_active').value = 0;
			$('activation_button').value = "Start";
		} else {
			//alert('Currently inactive. Starting.');
			$('process_active').value = 1;
			$('activation_button').value = "Pause";
			$('time_started').value = +new Date() - $('time_spent').value;
			populateCache();
		}
	}

	function updateDisplays(message) {
		//$('status').value = message;
		//$('cache_population_status').insert({bottom: message + "\n"});
		//$('cache_population_status').scrollTop = $('cache_population_status').scrollHeight;
	}

	function update_progress_bar() {
		var start = $('time_started').value;
		var end = +new Date();
		var time_spent = end - start;
		var completed_steps = $('completed_steps').value;
		var total_steps = $('total_steps').value;
		var percent = completed_steps / total_steps;
		var percent_display = (Math.round(percent * 10000) / 100) + '%';
		var estimated_total_time = time_spent / percent;
		var time_left = estimated_total_time - time_spent;
		$('percent_done').value = percent_display;
		$('progress_bar_shaded').style.width = percent_display;
		$('time_total').value = describe_time_amount(estimated_total_time);
		$('time_spent').value = time_spent;
		$('time_spent_display').value = describe_time_amount(time_spent);
		$('time_left').value = describe_time_amount(time_left);
	}

	function describe_time_amount(milliseconds) {
		var output = '';
		var hours = Math.floor(milliseconds / 3600000);
		if (hours >= 1) {
			output += hours + 'h ';
			milliseconds -= hours * 3600000;
		}
		var minutes = Math.floor(milliseconds / 60000);
		if (minutes >= 1) {
			output += minutes + 'm ';
			milliseconds -= minutes * 60000;
		}
		var seconds = Math.round(milliseconds / 1000);
		output += seconds + 's';
		return output;
	}
	
	function populateCache() {
		var waiting_for_ajax_request = false;
		if (state_iter > states.length - 1) {
			//updateDisplays('Done.');
			if ($('process_active').value == 1) {
				activation_button_onclick();
			}
			return;
		} else {
			var state = states[state_iter];
			if (county_iter > state.counties.length - 1) {
				state_iter++;
				county_iter = 0;
				topic_iter = 0;
			} else {
				var county = state.counties[county_iter];
				if (topic_iter > topics.length - 1) {
					county_iter++;
					topic_iter = 0;
				} else {
					var topic = topics[topic_iter];
					if (type_iter > types.length - 1) {
						type_iter = 0;
						topic_iter++;
					} else {
						
						// Starting state
						if (! county_iter && ! topic_iter && ! type_iter) {
							//updateDisplays(state.name);
							$('current_state').value = state.name;
						}

						// Starting county
						if (! topic_iter && ! type_iter) {
							//updateDisplays("\t" + county.name);
							$('current_county').value = county.name;
						}

						// Starting topic
						if (! type_iter) {
							//updateDisplays("\t\t" + topic.title);
							$('current_topic').value = topic.title;
						}

						var type = types[type_iter];
						type_iter++;
						var url = '/reports/getStatus/' + type + '/' + topic.simplified + '/' + state.id + '/' + county.id;
						waiting_for_ajax_request = true;
						new Ajax.Request(url, {
							method:'get',
							onSuccess: function(transport) {
								var response = transport.responseText;
								//updateDisplays("\t\t\t" + type.capitalize() + " \t " + response);
								$('current_report').value = type.capitalize();
								$('current_error_code').value = response;
								var error_code = response.substring(0, 1);
								if (error_code > 1 || error_code == '') {
									var alert = state.name + ' / ' + county.name + ' / ' + topic.title + ' / ' + type.capitalize() + ': ' + response + '\n';
									$('alerts').insert({'bottom': alert});
								}
							},
							onFailure: function() {
								//updateDisplays("\t\t\t" + type + " => OH CRAP, SOMETHING BROKE");
								//updateDisplays(state.name + ' / ' + county.name + ' / ' + topic.title + ' / ' + type.capitalize() + ' OH CRAP, SOMETHING BROKE');
								$('current_report').value = type.capitalize();
								$('current_error_code').value = 'OH CRAP, SOMETHING BROKE';
								var alert = state.name + ' / ' + county.name + ' / ' + topic.title + ' / ' + type.capitalize() + ': Failed to get error code';
								$('alerts').insert({'bottom': alert});
							},
							onComplete: function() {
								$('completed_steps').value++;
								update_progress_bar();
								if ($('process_active').value == 1) {
									populateCache();
								}
							}
						});
					}
				}
			}
		}
		if ($('process_active').value == 1 && ! waiting_for_ajax_request) {
			populateCache();
		}
	}
</script>