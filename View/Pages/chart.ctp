<?php /*
	Available to this view: 
		county_id
		state_id
		state_name_simplified
		county_name_simplified
		selected_topic
		description
		sources
		title_for_layout
		topic_full_name
*/ ?>
<h1 class="page_title"><?php echo $topic_full_name; ?></h1>
<div class="topic">
	<?php if (! isset($_GET['v1'])): ?>
		<?php
			$topic = $selected_topic;
			$description = '<p class="description">'.$this->Text->autoLink(nl2br($description)).'</p>';
			
			$chart_availability = $this->requestAction("/reports/getStatus/chart/$topic/$state_id/$county_id");
			$chart = $this->element('reports/chart', array(
				'topic' => $selected_topic, 
				'state' => $state_name_simplified, 
				'county' => $county_name_simplified, 
				'availability' => $chart_availability
			));
			
			$table_availability = $this->requestAction("/reports/getStatus/table/$topic/$state_id/$county_id");
			$table = $this->element('reports/table', array(
				'topic' => $selected_topic, 
				'state' => $state_name_simplified, 
				'county' => $county_name_simplified, 
				'availability' => $table_availability
			));
			
			$csv_availability = $this->requestAction("/reports/getStatus/csv/$topic/$state_id/$county_id");
			$csv_link = "/csv/$selected_topic/$state_name_simplified/$county_name_simplified";
			
			$source_availability = $this->requestAction("/reports/getStatus/source/$topic/$state_id/$county_id");
			$source_element = $this->element('reports/source', array(
				'topic' => $selected_topic, 
				'state' => $state_name_simplified, 
				'county' => $county_name_simplified, 
				'availability' => $source_availability,
				'sources' => $this->requestAction("/reports/switchboard/source/$topic/$state_id/$county_id")
			));
		?>
		<?php if ($chart_availability == 1): // Chart not supported for this topic ?>
			<?php echo $table; ?>
			<?php echo $description; ?>
		<?php else: ?>
			<?php echo $chart ?>
			<?php echo $description; ?>
			<fieldset class="collapsible collapsed">
				<legend>Data Table</legend>
				<?php echo $table; ?>
			</fieldset>
		<?php endif; ?>
		<?php if ($csv_availability == 0): ?>
			<fieldset class="collapsible collapsed">
				<legend>Download</legend>
				<div>
					<a href="<?php echo $csv_link; ?>">
						Download CSV spreadsheet
					</a>
				</div>
			</fieldset>
		<?php endif; ?>
		<fieldset class="collapsible collapsed">
			<legend>Source</legend>
			<?php echo $source_element; ?>
		</fieldset> 
	<?php else: //Old version of this page, accessed by tacking ?v1 at the end of the URL  ?>
		<?php if ($onlyTable): ?>
			<?php echo $this->element('table', array('selectedChart' => $selected_topic, 'county_id' => $county_id)); ?>
			<p class="description"><?php echo $text->autoLink(nl2br($description)); ?></p>
		<?php else: ?>
			<?php if ($this->requestAction("charts/chartExists/$selected_topic")): ?>
				<img src="/charts/<?php echo $selected_topic; ?>/<?php echo $county_id; ?>" class="chart" />
			<?php else: ?>
				<p class="chart_unavailable">
					Sorry, but this chart is not currently available. Please check back later.
				</p>
			<?php endif; ?>
			<p class="description"><?php echo $this->Text->autoLink(nl2br($description)); ?></p>
			<fieldset class="collapsible collapsed">
				<legend>Data Table</legend>
				<?php echo $this->element('table', array('selectedChart' => $selected_topic, 'county_id' => $county_id)); ?>
			</fieldset>
		<?php endif; ?>
		<fieldset class="collapsible collapsed">
			<legend>Source<?php if (isset($sources) && count($sources) > 1): ?>s<?php endif; ?></legend>
			<div class="source">
				<?php if (isset($sources) && ! empty($sources)): ?>
					<ul>
						<?php foreach ($sources as $source): ?>
							<li><?php echo $this->Text->autoLink(nl2br($source)); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else: ?>
					(There was an error retrieving the sources for this data set.)
				<?php endif; ?>
			</div>
		</fieldset>
	<?php endif; ?>
</div>

<script type="text/javascript">setupCollapsibleFieldsets();</script>