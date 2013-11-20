<div id="submenus">
	<?php foreach ($topics as $tab => $topics_set): ?>
		<h2 
			class="<?php echo $selected_tab == $tab ? 'open' : 'closed'; ?>" 
			id="nav_submenu_handle_<?php echo $tab; ?>" 
			title="Click to open/close"
		>
			<a href="#">
				<?php echo ucwords($tab); ?>
			</a>
		</h2>
		<?php $this->Js->buffer("
			$('#nav_submenu_handle_$tab a').click(function (event) {
				event.preventDefault();
				onNavSubmenuHandleClick('$tab');
			});
		"); ?>
		<div 
			class="submenu" 
			id="nav_submenu_<?php echo $tab; ?>" 
			<?php if ($selected_tab != $tab): ?>
				style="display: none;"
			<?php endif; ?>
		>
			<div>
				<ul>
					<?php foreach ($topics_set as $topic_name => $topic_title): ?>
						<li <?php if ($selected_topic == $topic_name): ?>class="selected"<?php endif; ?>>
							<a href="/<?php echo "$selected_state/$selected_county/$topic_name"; ?>">
								<?php echo $topic_title; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endforeach; ?>
	
	<h2 class="all_charts">
		<a href="/<?php echo "$selected_state/$selected_county"; ?>/all_charts">
			All Charts
		</a>
	</h2>
</div>

<hr />

<?php 
	$vars = compact(
		'selected_state', 
		'selected_county', 
		'states', 
		'state_abbreviations', 
		'counties_full_names', 
		'counties_simplified'
	);
	echo $this->element('select_county', $vars);
?>