<h2>
	Welcome!
</h2>

<p class="welcome_message">
	To begin, select a county from the list below...
</p>

<?php
	$vars = compact(
		'selected_state', 
		'selected_county', 
		'states', 
		'state_abbreviations', 
		'counties_full_names', 
		'counties_simplified'
	); 
	echo $this->element('select_county', $vars);
?> 