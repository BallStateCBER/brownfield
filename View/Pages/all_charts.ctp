<?php foreach ($topics as $tab => $set): ?>
	<h1 style="font-size: 20px; font-weight: bold;">
		<?php echo ucwords($tab); ?>
	</h1>
	<?php foreach ($set as $topic => $topic_title): ?>
		<?php 
			$chart_status = $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'), 
				array('pass' => array('chart', $topic, $state_id, $county_id))
			);
		?>
		<?php if ($chart_status == 0): ?>
			<span style="border: 1px solid black; display: inline-block; margin: 5px;">
				<a href="/<?php echo $state_abbreviation; ?>/<?php echo "$county_name_simplified/$topic"; ?>">					
					<?php echo $this->Html->image(
						Router::url(array(
							'controller' => 'reports', 
							'action' => 'switchboard',
							'type' => 'chart', 
							'topic' => $topic, 
							'state' => $state_abbreviation, 
							'county' => $county_name_simplified
						)),
						array('class' => 'chart')
					); ?>
				</a>
			</span>
		<?php endif; ?>
	<?php endforeach; ?>
<?php endforeach; ?>