<?php
// This element expects $topic, $state, and $county
$request_response = $this->requestAction(
	array('controller' => 'reports', 'action' => 'switchboard'),
	array(
		'pass' => array('table', $topic, $state, $county),
		'named' => array('table_version' => 2)
	)
);

// If an error is triggered as part of retrieving data, requestAction() will return FALSE
// Error is assumed to be code 2, data unavailable 
if ($request_response) {
	extract($request_response);
} else {
	$error = 2;	
}
?>

<?php if ($request_response && $error == 0): ?>
	<?php
		/*
		Expects the following variables:
		$title
		$footnote (optional)
		$table = array(
			'row name' => array(
				[first column name (usually a location)] => value
				[second column name] => value
				...
			),
			...
		);
		$columns = array(col1 name, col2 name, col3 name...)
		$options

		Produces tables structured like this:

					Title
		(Col1 name)		(Col 2 name (usually a location)	...
		(row1 header)	($values[col2][row1])	...
		(row2 header)	($values[col2][row2])	...
		...				...						...
					Footer
		*/

	// If the option is set to hide the first column
	if (in_array('hide_first_col', $options)) {
		$hide_first_col = true;
		array_shift($columns);
	}
	?>
	<div class="datatable">
		<?php if (count($table) > 1): ?>
			<p class="resorting">
				Click on column headers to sort table.
			</p>
		<?php endif; ?>
		<table class="sortable">
			<thead>
				<?php if ($title): ?>
					<tr class="title">
						<th colspan="<?php echo count($columns); ?>" class="title">
							<?php echo nl2br($title); ?>
						</th>
					</tr>
				<?php endif; ?>
				<tr class="sort_header">
					<?php foreach ($columns as $column): ?>
						<th title="Click to sort">
							<?php echo nl2br($column); ?>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tfoot>
			</tfoot>
			<tbody>
				<?php foreach ($table as $row => $set): ?>
					<tr <?php if (isset($hide_first_col)): ?>class="no_header"<?php endif; // Hides border-left of first cell ?>>
						<?php if (! isset($hide_first_col)): ?>
							<th>
								<?php echo $row; ?>
							</th>
						<?php endif; ?>
						<?php foreach ($set as $column => $data): ?>
							<td <?php if (in_array('colorcode', $options) && $data != 0) echo ($data > 0) ? 'class="increase"' : 'class="decrease"'; ?>>
								<?php echo $data; ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if (isset($footnote) && ! empty($footnote)): ?>
			<p class="footnotes">
				<?php echo nl2br($footnote); ?>
			</p>
		<?php endif; ?>
	</div>
<?php else: ?>
	<div>
		<p class="chart_unavailable">
			<?php switch ($error) {
				case 1:
					echo 'Sorry, but no data table is currently available for this topic.';
					break;
				case 2:
					echo 'Sorry, but this data is not currently available for the selected location. Please check back later.';
					break;
				case 3:
					echo 'Sorry, but there was an error generating this table. Please check back later.';
					break;
				case 4:
					echo 'Sorry, but the requested data table was not found.';
					break;
				case 5: // Is this ever triggered?
					echo 'Sorry, but there was an error generating this table. Please check back later.';
					break;
			} ?>
		</p>
	</div>
<?php endif; ?>
