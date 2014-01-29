<div id="all_charts">
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
				<?php echo $this->element('reports/svgchart', array(
					'topic' => $topic, 
					'state' => $state_abbreviation, 
					'county' => $county_name_simplified, 
					'availability' => $chart_status,
					'div_id' => $topic.'_chart'
				)); ?>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>