<?php if (isset($invalidChartSelected)): ?>
	<p class="error_message">
		It looks like you selected an invalid chart (<?php echo Inflector::humanize($invalidChartSelected); ?>).
		<br />If you typed in the address to this page manually, make sure that you spelled it correctly. If you need help, <a href="mailto:gtwatson@bsu.edu">contact the web developer</a>.
	</p> 
<?php else: ?>
	<?php $tabs = array('demographics', 'economy', 'health'); ?>
	<?php foreach ($tabs as $tab): ?>
		<img src="/img/<?php echo $tab; ?>.png" id="nav_submenu_graphical_handle_<?php echo $tab; ?>" class="nav_submenu_graphical_handle" />
		<script type="text/javascript">
			$('nav_submenu_graphical_handle_<?php echo $tab; ?>').onclick = function() {
				Effect.toggle('nav_submenu_<?php echo $tab; ?>', 'slide', {
					duration: 0.5,
					queue: {
						position: 'end', 
						scope: 'nav_submenu',
						limit: 1
					},
					beforeStart: function() {
						var handle = $('nav_submenu_handle_<?php echo $tab; ?>');
						var submenu = $('nav_submenu_<?php echo $tab; ?>');
						//if (handle.className == 'open') {
						if (submenu.visible()) {
							handle.className = 'closed';
						} else {
							handle.className = 'open';
						}
					},
					afterFinish: function() {
						var handle = $('nav_submenu_handle_<?php echo $tab; ?>');
						var submenu = $('nav_submenu_<?php echo $tab; ?>');
						//if (handle.className == 'open') {
						if (! submenu.visible()) {
							handle.className = 'closed';
						} else {
							handle.className = 'open';
						}
					}
				});
			}
		</script>
	<?php endforeach; ?>
	<script type="text/javascript">
		$('sidebar_topper').hide();
	</script>
<?php endif; ?>