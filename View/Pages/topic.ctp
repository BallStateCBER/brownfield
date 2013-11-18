<?php /*
	Available to this view: 
		county_id
		state_id
		state_abbreviation
		county_name_simplified
		selected_topic
		description
		sources
		title_for_layout
		topic_full_name
		chart_availability
		csv_availability
		excel5_availability
		excel2007_availability
		source_availability
		sources
*/ ?>
<h1 class="page_title">
	<?php echo $topic_full_name; ?>
</h1>
<div class="topic">
	<?php
		$element_cache_key = "$selected_topic.$state_abbreviation.$county_name_simplified";
		$description = '<p class="description">'.$this->Text->autoLink(nl2br($description)).'</p>';
		
		$chart = $this->element('reports/chart', array(
			'topic' => $selected_topic, 
			'state' => $state_abbreviation, 
			'county' => $county_name_simplified, 
			'availability' => $chart_availability
		));
		
		$table = $this->element('reports/table', array(
			'topic' => $selected_topic, 
			'state' => $state_abbreviation, 
			'county' => $county_name_simplified,
			'cache' => array(
				'key' => $element_cache_key,
				'time' => '+1 year'
			)
		));
		
		$source_element = $this->element('reports/source', array(
			'topic' => $selected_topic, 
			'state' => $state_abbreviation, 
			'county' => $county_name_simplified, 
			'availability' => $source_availability,
			'sources' => $sources
		));
	?>
	
	<?php if ($chart_availability == 1): // Chart not supported for this topic ?>
		<?php echo $table; ?>
		<?php echo $description; ?>
	<?php else: ?>
		<?php echo $chart ?>
		<?php echo $description; ?>
		<fieldset class="collapsible collapsed">
			<legend>
				Data Table
			</legend>
			<?php echo $table; ?>
		</fieldset>
	<?php endif; ?>
	
	<?php if ($csv_availability == 0 || $excel5_availability == 0 || $excel2007_availability == 0): ?>
		<fieldset class="collapsible collapsed">
			<legend>
				Download Spreadsheet
			</legend>
			<div>
				<ul class="download_options">
					<?php if ($excel2007_availability == 0): ?>
						<li>
							<?php echo $this->Html->link(
								$this->Html->image('document-excel-table.png').' Excel 2007',
								array(
									'controller' => 'reports', 
									'action' => 'switchboard', 
									'type' => 'excel2007', 
									'topic' => $selected_topic, 
									'state' => $state_abbreviation, 
									'county' => $county_name_simplified
								),
								array(
									'escape' => false, 
									'title' => 'Download Excel 2007 spreadsheet'
								)
							); ?>
						</li>
					<?php endif; ?>
					
					<?php if ($excel5_availability == 0): ?>
						<li>
							<?php echo $this->Html->link(
								$this->Html->image('document-excel-table.png').' Excel 5.0',
								array(
									'controller' => 'reports', 
									'action' => 'switchboard', 
									'type' => 'excel5', 
									'topic' => $selected_topic, 
									'state' => $state_abbreviation, 
									'county' => $county_name_simplified
								),
								array(
									'escape' => false, 
									'title' => 'Download Excel 5.0 spreadsheet'
								)
							); ?>
						</li>
					<?php endif; ?>
					
					<?php if ($csv_availability == 0): ?>
						<li>
							<?php echo $this->Html->link(
								$this->Html->image('document-excel-csv.png').' CSV',
								array(
									'controller' => 'reports', 
									'action' => 'switchboard', 
									'type' => 'csv', 
									'topic' => $selected_topic, 
									'state' => $state_abbreviation, 
									'county' => $county_name_simplified
								),
								array(
									'escape' => false, 
									'title' => 'Download CSV (comma-separated values) spreadsheet'
								)
							); ?>
						</li>
					<?php endif; ?>
				</ul>
			</div>
		</fieldset>
	<?php endif; ?>
	
	<fieldset class="collapsible collapsed">
		<legend>
			Source
		</legend>
		<?php echo $source_element; ?>
	</fieldset> 
</div>

<script type="text/javascript">setupCollapsibleFieldsets();</script>