<?php
	/* Variables provided:
	 * 		$states
	 * 		$selected_state
	 * 		$selected_county
	 * 		$counties_full_names
	 * 		$counties_simplified
	 * 		$state_abbreviations
	 */
	if (! isset($selected_county)) {
		$selected_county = null;
	}
	if (! isset($selected_state)) {
		$selected_state = null;
	}
	
	$passed = $this->request->params['pass'];
	if (isset($passed[1]) && $passed[1] == 'all_charts') {
		$path = 'all_charts';
	} elseif (count($passed) >= 2 && in_array($passed[0], $state_abbreviations) && in_array($passed[1], $counties_simplified)) {
		$path = implode('/', array_slice($passed, 2)); // the part of the current path AFTER state and county
	} else {
		$path = '';
	}
?>
<div id="county_selection">
	<p>
		Indiana Counties
	</p>

	<select name="county" id="select_county">
		<?php if (! $selected_county): ?>
			<option selected="selected">Select a county...</option>
		<?php endif; ?>
		
		<?php foreach ($counties_full_names as $id => $name): ?>
			<option 
				value="<?php echo $counties_simplified[$id]; ?>" 
				<?php if ($selected_county == $counties_simplified[$id]): ?>
					selected="selected"
				<?php endif; ?>
			>
				<?php echo $name; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<?php $this->Js->buffer("
	$('#select_county').change(function () {
		var county_name = $('#select_county').val();
		var path = '$path';
		window.location = '/in/' + county_name + '/' + path;
	});
"); ?>