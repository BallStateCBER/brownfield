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
		$description = '<p class="description">'.nl2br($this->Text->autoLink($description)).'</p>';
		
		$svgchart = $this->element('reports/svgchart', array(
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
		
		$this->Html->script('sorttable', array('inline' => false));
	?>
	
	<?php if ($chart_availability == 1): // Chart not supported for this topic ?>
		<section>
			<?php echo $table; ?>
			<?php echo $description; ?>
		</section>
	<?php else: ?>
		<section>
			<?php echo $svgchart; ?>
			<?php echo $description; ?>
		</section>
		
		<section class="collapsable">
			<h2>
				<a href="#">
					Data Table
				</a>
			</h2>
			<div>
				<?php echo $table; ?>
			</div>
		</section>
	<?php endif; ?>
	
	<?php if ($csv_availability == 0 || $excel2007_availability == 0): ?>
		<section class="collapsable">
			<h2>
				<a href="#">
					Download Spreadsheet
				</a>
			</h2>
			<div>
				<ul class="download_options">
					<?php if ($excel2007_availability == 0): ?>
						<li>
							<?php echo $this->Html->link(
								$this->Html->image('/data_center/img/icons/document-excel-table.png') . ' Office Open XML Workbook (Microsoft Excel)',
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
									'title' => 'Download OOXML spreadsheet'
								)
							); ?>
						</li>
					<?php endif; ?>
					
					<?php if ($csv_availability == 0): ?>
						<li>
							<?php echo $this->Html->link(
								$this->Html->image('/data_center/img/icons/document-excel-csv.png').' CSV',
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
		</section>
	<?php endif; ?>
	
	<section class="collapsable">
		<h2>
			<a href="#">
				Source
			</a>
		</h2>
		<div>
			<?php echo $source_element; ?>
		</div> 
	</section>
</div>

<?php $this->Js->buffer("setupCollapsibleTopicSections();"); ?>