<?php pr($this->request->params);
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
	} elseif (in_array($passed[0], $state_abbreviations) && in_array($passed[1], $counties_simplified)) {
		$path = implode('/', array_slice($passed, 2)); // the part of the current path AFTER state and county
	} else {
		$path = '';
	}
?>
<div id="county_selection">
	<p>State</p>
	<select name="state" id="select_state">
		<?php foreach ($states as $state): ?>
			<option value="<?php echo strtolower($state['Location']['abbreviation']); ?>"<?php if ($selected_state = $state['Location']['abbreviation']): ?>selected="selected"<?php endif; ?>>
				<?php echo $state['Location']['name']; ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p>County</p>
	<select name="county" id="select_county">
		<?php if (! $selected_county): ?>
			<option selected="selected"></option>
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
		var state_name = $('#select_state').val();
		var path = '$path';
		window.location = '/' + state_name + '/' + county_name + '/' + path;
	});
"); ?>