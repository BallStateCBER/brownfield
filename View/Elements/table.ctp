<?php if ($this->requestAction("tables/tableExists/$selectedChart")): ?>
	<div id="chart_table">
		<img src="/img/loading.gif" /> Loading table...
	</div>
	<script type="text/javascript">
		<?php echo $ajax->remoteFunction(array( 
			'url' => array('controller' => 'tables', 'action' => $selectedChart, $countyID), 
			'update' => 'chart_table'
		)); ?>
	</script>
<?php else: ?>
	<div>
		<p class="table_unavailable">
			Sorry, but this data table is not currently available. Please check back later.
		</p>
	</div>
<?php endif; ?>