<?php 
	function outputCsvLine(&$vals, $key, $filehandler) {
		fputcsv($filehandler, $vals, ',', '"');
	}
	function outputCsv($data) {
		$outstream = fopen("php://output", 'w');
		array_walk($data, 'outputCsvLine', $outstream);
		fclose($outstream);
	}
	
	if (isset($_GET['debug'])) {
		echo '<pre>';
	}
	
	$output = array();
	
	// Topic title
	$output[] = array($title);
	
	// If sources are provided, format and output them
	if (isset($sources) && ! empty($sources)) {
		foreach ($sources as $source_line) {
			$source_line = str_replace(array("\n", "\r"), ' ', $source_line);
			$output[] = array('Source: '.$source_line); 	
		}
	}
	
	// Blank line
	$output[] = array();
	
	
	// Column headers
	if (in_array('hide_first_col', $options)) {
		array_shift($columns);
	}
	$output[] = $columns;
	
	// Row titles and values
	foreach ($table as $row_name => $values) {
		if (in_array('hide_first_col', $options)) {
			$output[] = array_values($values);
		} else {
			$output[] = array_merge(array($row_name), array_values($values));
		}
	}
	
	if (isset($footnote) && $footnote != '') {
		$output[] = array($footnote);
	}
	
	outputCsv($output);
	
	if (isset($_GET['debug'])) {
		echo '</pre>';
	}