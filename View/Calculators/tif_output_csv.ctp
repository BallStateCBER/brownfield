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
		echo "Filename: $filename<br />";
		foreach (array('county_id', 'industry_id', 'option', 'annual_production', 'employees') as $var) {
			echo ucwords($var).': ';
			if (isset($$var)) {
				echo $$var;
			} else {
				echo "(not set)";
			}
			echo '<br />';
		}
		echo '<pre>';
	}

	$csv_output = array();

	// Title
	$csv_output[] = array("Brownfield Grant Writers' Toolbox");
	$csv_output[] = array("TIF-in-a-Box Economic Impact Calculator");
	$csv_output[] = array("http://brownfield.cberdata.org/tif");

	// Blank line
	$csv_output[] = array();

	// Input
	$csv_output[] = array('INPUT');
	$csv_output[] = array('State: ', $state_name);
	$csv_output[] = array('County: ', $county_name);
	$csv_output[] = array('Industry: ', $industry_name);
	if ($option == 'a') {
		$csv_output[] = array('Annual Production: ', $annual_production);
	} else {
		$csv_output[] = array('Employees: ', $employees);
	}

	// Blank line
	$csv_output[] = array();

	foreach ($output as $section => $section_info) {

		// Section title
		$csv_output[] = array(strtoupper($section_info['title']));

		// Each row
		foreach ($section_info['rows'] as $measure => $measure_info) {
			if (isset($measure_info['value'])) {
				$csv_output[] = array($measure_info['name'], $measure_info['value']);
			}
		}

		// Blank line
		$csv_output[] = array();
	}

	// IBT Impact
	$csv_output[] = array(strtoupper('Indirect Business Tax Impact'));
	$csv_output[] = array('', 'Direct', 'Total');
	foreach ($taxesOrder as $tax_type) {
		$row = $impact['tax_detail'][$tax_type];
		if (isset($row['total'])) {
			$csv_output[] = array(str_replace('&nbsp;', ' ', $row['name']), $row['total'], $row['direct']);
		}
	}

	outputCsv($csv_output);

	if (isset($_GET['debug'])) {
		echo '</pre>';
	}