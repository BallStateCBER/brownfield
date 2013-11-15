<?php if ($this->requestAction("charts/chartExists/$selectedChart")): ?>
	<img src="/charts/<?php echo $selectedChart; ?>/<?php echo $countyID; ?>" class="chart" />
<?php else: ?>
	<p class="chart_unavailable">
		Sorry, but this chart is not currently available. Please check back later.
	</p>
<?php endif; ?>