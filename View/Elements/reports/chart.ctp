<?php //Expects $topic, $state, $county, and $availability ?>
<?php if ($availability == 0): ?>
	<?php echo $this->Html->image(
		Router::url(array(
			'controller' => 'reports', 
			'action' => 'switchboard',
			'type' => 'chart', 
			'topic' => $topic, 
			'state' => $state, 
			'county' => $county
		)),
		array('class' => 'chart')
	); ?>
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