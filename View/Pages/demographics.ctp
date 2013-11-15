<?php 
	/* Available to this view:
	 * 		$selectedChart
	 * 		$countyID
	 * 		$description
	 * 		$sources
	 */
?>

<div class="chart">
	<img src="/charts/<?php echo $selectedChart; ?>/<?php echo $countyID; ?>" />
	<p class="description">
		<?php echo $description; ?>
	</p>
	<div class="source">
		<?php if (isset($sources) && ! empty($sources)): ?>
			Source<?php if (count($sources) > 1): ?>s<?php endif; ?>
			<ul>		
				<?php foreach ($sources as $source): ?>
					<li>
						<?php echo $text->autoLink(nl2br($source)); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else: ?>
			(There was an error retrieving the sources for this data set.)
		<?php endif; ?>
	</div>
</div>