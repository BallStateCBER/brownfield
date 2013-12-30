<?php //Expects $topic, $state, $county, and $availability ?>
<?php if ($availability == 0): ?>
	<?php
		$chart = $this->requestAction(array(
			'controller' => 'reports', 
			'action' => 'switchboard',
			'type' => 'svgchart', 
			'topic' => $topic, 
			'state' => $state, 
			'county' => $county
		));
	?>
	<div id="chart_div">
		<?php if ($chart): ?>
			<?php $this->GoogleCharts->createJsChart($chart); ?>
		<?php else: ?>
			$chart is empty
		<?php endif; ?>
	</div>
<?php else: ?>
	<div>
		<p class="chart_unavailable">
			<?php switch ($availability) {
				case 1:
					echo 'Sorry, but no chart is currently available for this topic.';
					break;
				case 2: 
					echo 'Sorry, but this data is not currently available for the selected location. Please check back later.';
					break;
				case 3: 
					echo 'Sorry, but there was an error generating this chart. Please check back later.';
					break;
				case 4:
					echo 'Sorry, but the requested chart was not found.';
					break; 
			} ?>
		</p>
	</div>
<?php endif; ?>