<?php
class ChartsController extends AppController {
	public $uses = array('Chart', 'Datum', 'Location');
	public $components = array('RequestHandler');
	public $helpers = array('GoogleChart');
	
	// Defaults
	public $default_width = 500;
	public $default_height = 200;
	public $scale_padding = 0.1; // (max_value - min_value) * scale_padding = padding on top and bottom of chart 
	public $colors = array(
		'FFCC33',	// orangish yellow
		'81CF5A',	// green
		'5F8AFF',	// blue
		'FF7F00', 	// orange
		'FF0000', 	// red
		'BF00FF', 	// purple
		'3F00FF'	// blue
	);
	public $pie_colors = array(
		'CF1920',
		'FFB900',
		'8219CF',
		'195BCF',
		'19C2CF',
		'23BF2A',
		'BF9523',
		'8F6635',
		'CFCFCF'	//gray
	);
	
	public $chart = null;			// GoogleChart object
	public $x_axis = null;			// GoogleChartAxis object
	public $y_axis = null;			// GoogleChartAxis object
	public $x_axis_step = false;
	public $y_axis_step = false;	
	public $values = array();		// Single array (for one data set) or multidimensional (for multiple)
	public $dates = array();		// Array of years or YYYYMMDD survey_date codes
	public $year = null;			// Used if only one date is being surveyed 
	public $display_params = array();
	public $category_id = null;
	public $locations = array();	// Each member is an array of [loc_type_id, loc_id]
	public $percent_values_converted = false;
	public $max = null;	// Max Y value
	public $min = null;	// Min Y value
	public $bottom = null;	// Padding applied
	public $top = null;	// Padding applied
	public $data_step = false;	//'Step' for the labels on the data axis (usually Y)
	
	// Set by __setXAxisLabels() and used in writing chart titles
	public $start_date = null;
	public $end_date = null;
	
	public function beforeFilter() {
		App::import('Vendor', 'GoogleChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleChart.php'));
	}
	
	public function beforeRender() {
		if (isset($_GET['debug'])) {
			echo '<pre>Values: '.var_export($this->values, true).'</pre>';
			echo '<pre>$this->chart: '.print_r($this->chart, true).'</pre>';
			$this->layout = 'default';
		} else {
			$this->layout = 'png';
		}
		$this->set(array(
			'chart' => $this->chart
		));
	}
	
	public function error($code = 1) {
		switch ($code) {
			case 1: // Chart not found (invalid county id
			default:
				$error_img_url = '../webroot/img/error_chart_not_found.png';
				break;
		}
		$image = file_get_contents($error_img_url);
		$this->set(array(
			'image' => $image
		));
	}
	
	/* Chart::getChartList() should be updated whenever charts are added or when their method 
	 * names or human-readable titles are changed. This is used by the navigation sidebar and 
	 * to test whether or not user-supplied chart names are valid. */
	public function getAllCharts($tab = null) {
		$charts = $this->Chart->getChartList();
		return ($tab) ? $charts[$tab] : $charts;
	}
	
	private function __getDataScale() {
		if (empty($this->values)) {
			return false;
		}
		if (is_array(reset($this->values))) {
			$mins = $maxes = array();
			foreach ($this->values as $set) {
				$mins[] = min($set);
				$maxes[] = max($set);
			}
			$min = min($mins);
			$max = max($maxes);
		} else {
			$min = min($this->values);
			$max = max($this->values);
		}
		$this->max = $max;
		$this->min = $min;
		$this->top = $max;
		$this->bottom = $min;
		return true;
	}
	
	/* Takes a value like 0.1 and moves the top and bottom bounds of the chart by 10%.
	 * A value of zero will remove padding. */ 
	private function __padDataScale($padding_multiplier = null) {
		if (! $padding_multiplier) {
			$padding_multiplier = $this->scale_padding;
		}
		$span = $this->max - $this->min;
		$padding = $padding_multiplier * $span;
		
		// If the minimum value is non-negative, pad the bottom of the chart to no less than zero
		if ($this->min >= 0) {
			$bottom = ($padding < $this->min) ? $this->min - $padding : 0;
			
		// If minimum value is negative, add padding unconditionally
		} else {
			$bottom = $this->min - $padding;	
		}
		$top = $this->max + $padding;
		
		$this->top = $top;
		$this->bottom = $bottom;
		$this->chart->setScale($this->bottom, $this->top);
	}
	
	// Should be called after all other axis-related methods
	// If __padDataScale is used, it should be called AFTER this method
	private function __startScaleAtZero($axis = 'y') {
		if (! (isset($this->bottom) && isset($this->top))) {
			$this->__getDataScale();
		}
		$this->bottom = $this->min = 0;
		$this->chart->setScale(0, $this->top);
		$axis_var = $axis.'_axis';
		$this->$axis_var->setRange(0, $this->top, $this->data_step);
	}
	
	private function __prepChart($pad = true) {
		// Set scale
		if (! ($this->bottom && $this->top)) {
			$this->__getDataScale();
		}
		if ($pad) {
			$this->__padDataScale();
		}
		$this->chart->setScale($this->bottom, $this->top);
		
		// Set default styling
		$this->chart->setGridLines(10, 10, 1, 5);
	}
	
	// This is generally the Y axis, but is X for horizontal bar charts 
	private function __prepDataAxis($display_type = 'number', $display_precision = 0, $axis = 'y') {
		$axis_var = $axis.'_axis';
		
		// Create axis
		$this->$axis_var = new GoogleChartAxis($axis);

		// Set the axis label styles
		/* Doesn't work with the new array-of-categoryIDs system, kept because this kind of behavior will be
		 * necessary for custom charts. 
		if (! $display_type) {
			$this->loadModel('DataCategory');
			$this->DataCategory->read(array('display_type', 'display_precision'), $this->category_id);
			extract($this->DataCategory->data['DataCategory']);
		}
		*/
		$params = array();
		switch ($display_type) {
			case 'percent':
				$params['num_type'] = 'p';
				break;
			case 'currency':
				$params['num_type'] = 'cUSD';
				break;
		}
		$params['decimal_places'] = $display_precision;
		$this->$axis_var->setLabelFormat($params);
		
		// Set range of y-axis labels
		if (! (isset($this->bottom) && isset($this->top))) {
			$this->__getDataScale();
		}
		
		$this->$axis_var->setRange($this->bottom, $this->top, $this->data_step);
		
		// Add axis to chart
		$this->chart->addAxis($this->$axis_var);
	}
	
	// Usually the X axis, but is Y for horizontal bar charts
	private function __prepLabelAxis($axis = 'x') {
		$axis_var = $axis.'_axis';
		
		// Create axis
		$this->$axis_var = new GoogleChartAxis($axis);
		
		// Set range of x-axis labels
		$this->__setXAxisLabels();
		
		// Add axis to chart
		$this->chart->addAxis($this->$axis_var);
	}
	
	/* Note: This assumes that the granularity implied in the earliest survey_date 
	 * is the granularity used in all following data. This cannot currently properly
	 * display data sets with mixed granularity (yearly, quarterly, monthly, daily) */ 
	private function __setXAxisLabels() {
		$step = $this->x_axis_step;
		$first = reset($this->dates);
		$last = end($this->dates);

		// Yearly granularity
		if (strlen($first) == 4 || substr($first, -4) == '0000') {
			$this->start_date = $begin = substr($first, 0, 4);
			$this->end_date = $end = substr($last, 0, 4);
			$this->x_axis->setLabelFormat(array(
				'num_type' => 'f',
				'decimal_places' => '0',
				'groups_separator' => '', 		//blank or s
			));
			
		// Quarterly granularity
		} elseif (substr($first, -4, 1) == 'Q') {
			// to do
			
		// Monthly granularity
		} elseif (substr($first, -2) == '00') {
			// to do
			
		// Daily granularity
		} else {
			// to do
				
		}
		
		$this->x_axis->setRange($begin, $end, $step);	
	}
	
	private function __convertPercentValues() {
		if ($this->percent_values_converted) {
			return;
		}
		$new_values = array();
		if (is_array(reset($this->values))) {
			foreach ($this->values as $setkey => $set) {
				foreach ($set as $value) {
					$new_values[$setkey][] = $value / 100;
				}
			}
		} else {
			foreach ($this->values as $value) {
				$new_values[] = $value / 100;
			}
		}
		$this->values = $new_values;
		$this->percent_values_converted = true;
	}
	
	// If no parameters are passed, assumes the first location specified in $this->locations
	private function __getLocName($loc_type_id = null, $loc_id = null, $full_name = false) {
		if ($loc_type_id == null) {
			$loc_type_id = $this->locations[0][0];
			$loc_id = $this->locations[0][1];	
		}
		return $this->Location->getLocationName($loc_type_id, $loc_id, $full_name);
	}
	
	// Shade area below 0%
	private function __drawLineAtZero() {
		if ($this->bottom < 0) {
			$value_marker = new GoogleChartRangeMarker();
			$span = $this->top - $this->bottom;
			$range_to = abs(round($this->bottom / $span, 4));
			$range_from = $range_to - 0.005;
			$color = '222222';
			$value_marker->setRangeMarker("r,$color,0,$range_from,$range_to");
			$this->chart->addMarker($value_marker);
		}
	}
	
	// Takes a one-dimensional array and applies a formatting to all of its members
	private function __formatValues($values, $mode = 'number', $precision = null) {
		$new_values = array();
		foreach ($values as $value) {
			$new_values[] = $this->__formatValue($value, $mode, $precision);	
		}
		return $new_values;
	}
	
	// Takes a single value and formats it
	private function __formatValue($value, $mode = 'number', $precision = null) {
		if ($value == '') {
			return $value;
		}
		
		// Computes the precision that $value is currently at
		// This is necessary to force number_format to not alter $value's precision if $precision = null  
		if (is_null($precision)) {
			switch ($mode) {
				case 'number':
				case 'percent':
				case 'currency':
					$value_split = explode('.', $value);
					if (isset($value_split[1])) {
						$precision = strlen($value_split[1]);	
					}
			}
		}
		
		switch ($mode) {
			case 'year':
				return substr($value, 0, 4);
			case 'number':
				return ($value < 1 ? '0.' : '').number_format($value, $precision);
			case 'percent':
				return number_format($value, $precision).'%'; //(($value < 1 && $value != 0) ? '0.' : '').
			case 'currency':
				return '$'.($value < 1 ? '0.' : '').number_format($value, $precision);
			case 'string':
			default:
				return $value;
		}
	}
	
	// Returns an array of [corporation name => corporation id] pairs for each school corporation in a given county
	private function __getCountysSchoolCorps($county_id) {
		return $this->Location->getCountysSchoolCorps($county_id);
	}
	
	// Populates the third element of each array in $this->locations with the appropriate location name
	// and returns a simple array of the same location names
	private function __getLocationLabels() {
		$labels = array();
		foreach ($this->locations as $lkey => $loc) {
			
			// If a label has already been specified, use it
			if (isset($this->locations[$lkey][2])) {
				$label = $this->locations[$lkey][2];
			
			// Otherwise, use these
			} elseif ($loc[0] == 3 && $loc[1] == 14) {
				$label = 'Indiana'; 
			} elseif ($loc[0] == 4 && $loc[1] == 1) {
				$label = 'United States';
			} elseif ($loc[0] == 2) {
				$label = $this->__getLocName().' County';
			}
			$labels[] = $this->locations[$lkey][2] = $label;
		}
		return $labels;	
	}
	
	// Round out the chart data scale / label range
	// (so that the chart's data axis begins and ends on nice, round numbers)
	private function __roundDataScale($data_axis, $round_by, $step = false) {
		$axis_name = $data_axis.'_axis';
		$new_min = floor($this->min * $round_by) / $round_by; 
		$new_max = ceil($this->max * $round_by) / $round_by;
		$this->$axis_name->setRange($new_min, $new_max, $step);
		$this->chart->setScale($new_min, $new_max);
	}
	
	// In a view, a call to $this->requestAction("charts/(chartname)/getSources:1") returns what this
	// function does, which should be an array of each source description associated with this chart,
	// which are retrieved based on the contents of $this->category_id
	private function __getSources() {
		$simple_category_ids = array_values($this->category_id);
		$location_conditions = array();
		foreach ($this->locations as $location_set) {
			$location_conditions[] = array(
				'Datum.loc_type_id' => $location_set[0],
				'Datum.loc_id' => $location_set[1]
			);
		}
		$conditions = array(
			'Datum.category_id' => $simple_category_ids,
			'OR' => $location_conditions
		);
		if ($this->year) {
			$conditions['Datum.survey_date'] = $this->year.'0000';
		}
		$result = $this->Datum->find('all', array(
			'fields' => array('DISTINCT Source.source'),
			'conditions' => $conditions
		));
		$sources = array();
		foreach ($result as $key => $source) {
			$sources[] = $source['Source']['source']; // Sources source the sourcey source sourcilly
		}
		//echo '<pre>'.print_r($conditions, true).'</pre>';
		return $sources;
	}
	
	/* Rationale for this method: Conditionally returned in each method after that chart's category_ids and locations
	 * have been set, since this information is necessary to determine all of the relevant sources. */
	private function __handleRequests() {
		if (isset($this->params['named']['getSources'])) {
			return $this->__getSources();
		}
	}
	
	/* Use this instead of $this->render('index') if data may not be available,
	 * depending on the county. Pass a variable to this method that should be something other
	 * than zero, null, a blank string, or an empty array. If it's not, the 'data unavailable'
	 * image will be rendered instead of the chart. */
	private function __renderIfDataIsAvailable($values) {
		if (empty($values)) {
			$this->render('county_data_unavailable');
		} else {
			$this->render('view');
		}
	}
	
	public function chartExists($name) {
		return method_exists($this, $name);
	}
	
	public function population($county = 1) {
		// General parameters
		$this->category_id = array('Population' => 1);
		$this->locations = array(array(2, $county));
		$county_name = $this->__getLocName();
		$state_name = 'Indiana';
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleChart('lc', $this->default_width, $this->default_height);
		
		// Add line
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->category_id), $this->locations[0][0], $this->locations[0][1]);
		$line = new GoogleChartData($this->values[0]);
		$this->chart->addData($line);
		
		// Default prep
		$this->__prepChart();
		$this->__prepLabelAxis();
		$this->__prepDataAxis();
		
		// Finalize
		$this->chart->setTitle("Population of $county_name County, $state_name ($this->start_date - $this->end_date)");
		$this->render('view');
	}
	
	public function population_growth($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartRangeMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartRangeMarker.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->category_id = array('Population' => 1);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(12)->setBarWidth(22);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Population Growth");
		
		// Gather data
		$dates = array(1969, 1974, 1979, 1984, 1989, 1994, 1999, 2004, 2008);
		$county_growth_values = $state_growth_values = array();
		$category_id = array_pop($this->category_id);
		list($this->dates, $county_values) = $this->Datum->getValues($category_id, $this->locations[0][0], $this->locations[0][1], $dates);
		list($this->dates, $state_values) = $this->Datum->getValues($category_id, $this->locations[1][0], $this->locations[1][1], $dates);
		$date_pairs = array(
			array(2004, 2008), array(1999, 2008), array(1994, 2008), array(1989, 2008), array(1984, 2008), array(1979, 2008), array(1974, 2008), array(1969, 2008)
		);
		foreach ($date_pairs as $date_pair) {
			$label = substr($date_pair[0], 0,4)."-".substr($date_pair[1], 0,4);
			$earlier = $date_pair[0].'0000';
			$later = $date_pair[1].'0000';
			$county_value = ($county_values[$later] - $county_values[$earlier]) / $county_values[$earlier];
			$state_value = ($state_values[$later] - $state_values[$earlier]) / $state_values[$earlier];
			$this->values[0][$label] = $county_value;
			$this->values[1][$label] = $state_value; 
		}
		
		// Draw bars
		foreach ($this->locations as $loc_key => $location) {
			$bar = new GoogleChartData($this->values[$loc_key]);
			$bar->setColor($this->colors[$loc_key]);
			$bar->setLegend($location[2]);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p0y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->__prepLabelAxis();
		$this->__prepDataAxis('percent', 1);
		$this->x_axis->setLabels(array_keys($this->values[0]));
		$this->__drawLineAtZero();
		
		// Finalize
		$this->render('view');
	}
	
	public function density($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
			'Population density' => 5721, 
			'Housing units density' => 5722
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Density Per Square Mile of Land Area ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*f0y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Population density', 'Housing units density'));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 1);
		
		// Finalize
		$this->render('view');
	}
	
	public function population_age_breakdown($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
			'75 years and older' => 5734,
			'60 to 74 years' => 5733,
			'45 to 59 years' => 5732,
			'25 to 44 years' => 5731,
			'15 to 24 years' => 5730,
			'5 to 14 years' => 5729,
			'Under 5 years' => 363
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(1)->setGroupSpacing(5)->setBarWidth(5);
		$this->chart->setLegendPosition('r');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Population By Age ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Draw bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);	
		}
				
		// Scale, axes, labels
		$this->data_step = 0.05;
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		$this->__startScaleAtZero('x');
		
		// Finalize
		$this->render('view');
	}
	
	public function female_age_breakdown($county = 1) {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
			'Young Women (< 15)' => 5738,
			'Women child bearing age (15 to 44)' => 5739,
			'Women (> 44)' => 5740
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GooglePieChart('pc', $this->default_width, $this->default_height);
		$this->chart->setLegendPosition('r');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Female Age Breakdown ($this->year)|Outer: {$this->locations[0][2]}      Inner: {$this->locations[1][2]}");
		$this->chart->setRotationDegree(270);
		
		// Get values
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
				
		// Add the first data set and first legend item
		reset($this->category_id);
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->__formatValues($this->values[0], 'percent', 0));
		next($this->category_id);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		// Add the remaining legend items
		for ($n = 2; $n < count($this->category_id); $n++) {
			next($this->category_id);
			$slice = new GoogleChartData(array());
			$slice->setLegend(key($this->category_id));
			$slice->setColor($this->colors[$n]);
			$this->chart->addData($slice);
		}		

		// Finalize
		$this->render('view');
	}
	
	public function population_by_sex($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
	 		'Male' => 361,
	 		'Female' => 362
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(10)->setBarWidth(20);
		$this->chart->setLegendPosition('r');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Population By Sex ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Draw bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels
		$this->data_step = 0.005;
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		
		// Clean up axis labels
		$this->__roundDataScale('x', 20);
		
		// Finalize
		$this->render('view');
	}
	
	public function dependency_ratios($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->category_id = array(
		 	'Total (< 15 and 65+)' => 5741,	
	 		'Child (< age 15)' => 5742,
			'Elderly (65+)' => 5743
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$this->__getLocationLabels();
		$this->year = 2000;
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(55)->setBarWidth(30);
		$this->chart->setLegendPosition('b');
		$this->chart->setTitle("Dependency Ratio Per 100 People ($this->year)");
		$this->chart->setLegendLabelOrder('l');
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Draw bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}

		// Scale, axes, labels
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->category_id));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 1);
		$this->__startScaleAtZero();
		
		// Finalize
		$this->render('view');
	}
	
	public function educational_attainment($county = 1) {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
			'Less than 9th grade, percent' => 5748,
			'9th to 12th grade, no diploma, percent' => 468,
			'High school graduate or equivalent, percent' => 469,
			'Some college, no degree, percent' => 5750,
			'Associate degree, percent' => 472,
			'Bachelor\'s degree, percent' => 473,
			'Graduate or professional degree, percent' => 5752
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GooglePieChart('pc', 300, 400);
		$this->chart->setLegendPosition('bv');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Educational Attainment ($this->year)|Population 25 Years and Over|Outer: {$this->locations[0][2]}|Middle: Indiana   Inner: United States");
		$this->chart->setRotationDegree(270);
		
		// Get values
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
	
		// Add the first data set and first legend item
		// Note: Inner is added first, then middle, then outer 
		reset($this->category_id);
		$slice = new GoogleChartData($this->values[2]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		next($this->category_id);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->__formatValues($this->values[0], 'percent'));
		next($this->category_id);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		// Add the remaining legend items
		$count = count($this->category_id);
		for ($n = 3; $n <= $count; $n++) {
			$slice = new GoogleChartData(array());
			//$slice->setColor($pie_colors[$n]);
			next($this->category_id);
			$slice->setLegend(key($this->category_id));
			$this->chart->addData($slice);
		}

		// Finalize
		$this->render('view');
	}
	
	public function graduation_rate($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2009;
		$this->category_id = array('High School Graduation Rate' => 5396);
		$this->locations = $labels = array();
		$school_corps = $this->__getCountysSchoolCorps($county);
		foreach ($school_corps as $corp_name => $corp_id) {
			$this->locations[] = array(6, $corp_id, $corp_name);
			$labels[] = $corp_name;
		}
		$this->locations[] = array(3, $this->requestAction('/data/getStateFromCounty/'.$county));
		$labels[] = '(Indiana average)';
		$county_name = $this->__getLocName(2, $county).' County';
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Adapt to the wide range of data and location counts
		$location_count = count($this->locations);
		$bar_width = ($location_count <= 8) ? 20 : 15;
		$chart_height = 70 + (($bar_width + 10) * $location_count);
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $chart_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(10)->setBarWidth($bar_width);
		$this->chart->setLegendPosition('r');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("High School Graduation Rate|$county_name ($this->year)");
		
		// Draw bars
		// County color for all school corporations, state color for average
		$data_set = new GoogleChartData($this->values);
		$colors = array_pad(array(), ($location_count - 1), $this->colors[0]);
		$colors[] = $this->colors[1];
		$data_set->setColor($colors);		
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p1y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
				
		// Scale, axes, labels
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse($labels));
		$count_horizontal_gridlines = 100 / count($this->locations);
		$this->chart->setGridLines(10, $count_horizontal_gridlines, 1, 5);
 
		// Clean up axis labels
		$data_step = 0.02 * floor(($this->max - $this->min) / 0.1);
		$this->__roundDataScale('x', 20, $data_step);
		$this->__padDataScale(0.2);
		
		// Finalize
		$this->render('view');
	}
	
	public function household_size($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array('Average household size' => 348);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(90)->setBarWidth(60);
		$this->chart->setTitle("Average Household Size ($this->year)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 'b', 0, 2, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 2);
		
		// Finalize
		$this->render('view');
	}
	
	public function households_with_minors($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array('Households with one or more people under 18 years' => 438);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhs', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(30)->setGroupSpacing(0)->setBarWidth(30);
		$this->chart->setTitle("Households With One or More People Under 18 Years ($this->year)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
				
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('percent', 2, 'x');		
		$this->__roundDataScale('x', 10);

		// Finalize
		$this->render('view');
	}
	
	public function household_types_with_minors($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
	 	 	'Married-couple family' => 5762,
			'Male householder, no wife present' => 5764,
			'Female householder, no husband present' => 5766,
	 	 	'Nonfamily households' => 5768,
			'Households with one or more people under 18 years' => 346 //Not part of chart, used for calculation
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvs', 400, 500);
		$this->chart->setBarSpacing(50)->setGroupSpacing(100)->setBarWidth(50);
		$this->chart->setTitle("Households With One Or|More People Under 18 Years: | Breakdown of Household Types ($this->year)");
		$this->chart->setLegendPosition('bv');
		$this->chart->setLegendLabelOrder('r');
		$this->chart->setScale(0, 100);
		
		// Gather data
		$total_households_cat_id = array_pop($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $this->year);
			foreach ($this->category_id as $category => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / $total_households;
				$this->values[$category][$location[2]] = $value;
			}
		}
		
		// Draw bars
		$k = 0;
		foreach ($this->category_id as $category_name => $category_id) {
			$bar = new GoogleChartData($this->values[$category_name]);
			$bar->setColor($this->pie_colors[$k]);
			$bar->setLegend($category_name);
			$this->chart->addData($bar);
			$k++;
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'c', 30, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 0, 'y');
		$this->chart->setScale(0, 1);
		$this->y_axis->setRange(0, 1);
		
		// Finalize
		$this->render('view');
	}
	
	public function households_with_over_65($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array('Percent of households with one or more people 65 years and over' => 439);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhs', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(30)->setGroupSpacing(0)->setBarWidth(30);
		$this->chart->setTitle("Households with one or more people 65 years and over ($this->year)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('percent', 2, 'x');		
		$this->__roundDataScale('x', 20);

		// Finalize
		$this->render('view');
	}
	
	public function poverty($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Poverty Percent: All Ages' => 5686,
 	 		'Poverty Percent: Under 18' => 5688
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Percentage of Population in Poverty ($this->year)");
		
		// Draw bars
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->category_id));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 0);
		$this->__roundDataScale('y', 20, 0.05);
		
		// Finalize
		$this->render('view');
	}
	
	public function lunches($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2010;
		$this->category_id = array(
		 	'Free lunches' => 5780,
		 	'Reduced lunches' => 5781,
		 	'Free + reduced' => 5782,
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(65)->setBarWidth(40);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Free and Reduced Lunches ($this->year)");
		
		// Gather data
		foreach ($this->locations as $loc_key => $location) {
			foreach ($this->category_id as $label => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				$this->values[$location[2]][$label] = $value / 100;
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$location_name]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->category_id));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 0);
		$this->__startScaleAtZero('y');
				
		// Finalize
		$this->render('view');
	}
	
	public function disabled($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array('Percent of population disabled' => 5792);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(90)->setBarWidth(60);
		$this->chart->setTitle("Percent of Population Disabled ($this->year)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 't', 0, 10, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 1);
		$this->__roundDataScale('y', 100);
		
		// Finalize
		$this->render('view');
	}
	
	public function disabled_ages($county = 1) {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// General parameters
		$this->year = 2000;
		$this->category_id = array(
			'5 to 15 years' => 5800,
			'16 to 20 years' => 5801,
			'21 to 64 years' => 5802,
			'65 to 74 years' => 5803,
			'75+ years' => 5804
		);
		$this->locations = array(array(2, $county));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GooglePieChart('pc', $this->default_width, $this->default_height);
		$this->chart->setLegendPosition('r');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Disabled Age Breakdown For|{$this->locations[0][2]}, Indiana ($this->year)");
		$this->chart->setRotationDegree(270);
		
		// Get values
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
				
		// Add the first data set and first legend item
		reset($this->category_id);
		$slice = new GoogleChartData($this->values[0]);
		$slice->setLegend(key($this->category_id));
		$slice->setLabels($this->__formatValues($this->values[0], 'percent'));
		$slice->setColor($this->pie_colors[0]);
		$this->chart->addData($slice);
		
		// Add the remaining legend items
		for ($n = 1; $n < count($this->category_id); $n++) {
			next($this->category_id);
			$slice = new GoogleChartData(array());
			$slice->setLegend(key($this->category_id));
			$slice->setColor($this->pie_colors[$n]);
			$this->chart->addData($slice);
		}

		// Finalize
		$this->render('view');
	}
	
	public function share_of_establishments($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2007;
		$this->category_id = array(
			'Percent of establishments: Logistics (Transportation, warehousing, wholsale, retail trade)' => 5813, 
			'Percent of establishments: Manufacturing' => 5814
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Percent Share of Total Establishments ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Logistics', 'Manufacturing'));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 1);
		$this->__startScaleAtZero('y');
		
		// Finalize
		$this->render('view');
	}
	
	public function employment_growth($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartRangeMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartRangeMarker.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->category_id = array(
			'2005-2009' => 5817,
			'2000-2009' => 5818,
			'1995-2009' => 5819,
			'1990-2009' => 5820
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county), 'Indiana (not seasonally adjusted)'));
		$this->__getLocationLabels();
		$this->year = 2009;
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(50)->setBarWidth(30);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Employment Growth");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->category_id));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 1);
		$this->__drawLineAtZero();
		
		// Finalize
		$this->render('view');
	}
	
	public function employment_trend($county = 1) {
		// General parameters
		$this->category_id = array('Non-farm Employment' => 5815);
		$this->locations = array(array(2, $county));
		$this->__getLocationLabels();
		$county_name = $this->locations[0][2];
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleChart('lc', $this->default_width, $this->default_height);
		
		// Add line
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->category_id), $this->locations[0][0], $this->locations[0][1]);
		$line = new GoogleChartData($this->values[0]);
		$line->setColor($this->colors[0]);
		$this->chart->addData($line);
		
		// Default prep
		$this->__prepChart();
		$this->__prepLabelAxis();
		$this->__prepDataAxis();
		
		// Finalize
		$this->chart->setTitle("Employment in $county_name County, Indiana ($this->start_date - $this->end_date)");
		$this->render('view');
	}
	
	public function unemployment_rate($county = 1) {
		// General parameters
		$this->category_id = array('Unemployment Rate' => 569);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county), 'Indiana (not seasonally adjusted)'));
		$this->__getLocationLabels();
		$county_name = $this->locations[0][2];
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleChart('lc', $this->default_width, $this->default_height);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Nonfarm Employment Growth");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				list($this->dates, $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
			}
		}
		$this->__convertPercentValues();
		
		// Add line
		foreach ($this->locations as $loc_key => $location) {
			$line = new GoogleChartData($this->values[$loc_key]);
			$line->setColor($this->colors[$loc_key]);
			$line->setLegend($location[2]);
			$this->chart->addData($line);
		}
		
		// Default prep		
		$this->__prepChart();
		$this->__prepLabelAxis();
		$this->__prepDataAxis('percent', 1);
		$this->__roundDataScale('y', 100);
		
		// Finalize
		$this->chart->setTitle("Unemployment Rate ($this->start_date - $this->end_date)");
		$this->render('view');
	}
	
	public function personal_and_household_income($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Per capita personal income (dollars)' => 47,
 	 		'Median Household Income' => 5689
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Personal and Household Income ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N$*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart(false);
		$this->__padDataScale(0.2);
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Per Capita Personal Income ($)', 'Median Household Income ($)'));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('currency', 1);
		$this->__startScaleAtZero('y');
		
		// Finalize
		$this->render('view');
	}
	
	public function income_inequality($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->category_id = array('Income inequality' => 5668);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		$this->dates = $labels = array(1970, 1980, 1990, 2000);
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(55)->setBarWidth(30);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Income Inequality");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				list($this->dates, $this->values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $this->dates);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 1);
		
		// Finalize
		$this->render('view');
	}
	
	public function birth_rate($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$this->category_id = array('Birth Rate = Live Births per 1,000 population' => 5827);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->chart->setTitle("Crude Birth Rate ($this->year)|(Live Births per 1,000 Population)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();		
		/* Since the data range is usually so small, normal "span * 0.1" padding won't provide 
		 * sufficient space for the markers on the right side of the bars. But just adding one 
		 * to $this-> topdoes the trick. */  
		$this->top++; 
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__startScaleAtZero();
		$this->__prepDataAxis('number', 0, 'x');
		
		// Finalize
		$this->render('view');
	}
	
	public function birth_rate_by_age($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$this->category_id = array(
			'10 to 49' => 5840,
			'Under 18' => 5841,
			'18 to 39' => 5842,
			'40 to 49' => 5843
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(50)->setBarWidth(30);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Birth Rate By Age Group ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		//$this->top += 10;
		//$this->__padDataScale(0.2);
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->category_id));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 0);
		$this->__startScaleAtZero('y');
		
		// Finalize
		$this->render('view');
	}
	
	public function birth_measures($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$this->category_id = array(
			'Low Birthweight' => 5844, //(less than 2,500 grams)
			'Very Low Birthweight' => 5845, //(less than 1,500 grams)
			'< 37 Weeks Gestation' => 5846,
			'Prenatal Care, 1st Trimester' => 5847,
			'Mother Unmarried' => 5848
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(8);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Birth Measures ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		$this->chart->addAxis($this->y_axis);
		$this->__startScaleAtZero();
		$this->__prepDataAxis('percent', 0, 'x');
		
		// Finalize
		$this->render('view');
	}
	
	public function fertility_rates($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$this->category_id = array(
			'General' => 5849,
			'Total' => 5850
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(25)->setBarWidth(20);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Fertility Rates ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		$this->chart->addAxis($this->y_axis);
		$this->__startScaleAtZero();
		$this->__prepDataAxis('number', 0, 'x');
		
		// Finalize
		$this->render('view');
	}
	
	public function deaths_by_sex($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2007;
		$this->category_id = array(
			'Male' => 5856, // percent of deaths
			'Female' => 5857 // percent of deaths
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvs', 300, 500);
		$this->chart->setBarSpacing(80)->setGroupSpacing(80)->setBarWidth(50);
		$this->chart->setTitle("Deaths By Sex ($this->year)");
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('r');
		
		// Gather data
		foreach ($this->locations as $loc_key => $location) {
			foreach ($this->category_id as $category => $category_id) {
				$this->values[$category][$location[2]] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Draw bars
		$k = 0;
		foreach ($this->category_id as $category_name => $category_id) {
			$bar = new GoogleChartData($this->values[$category_name]);
			$bar->setColor($this->pie_colors[$k]);
			$bar->setLegend($category_name);
			$this->chart->addData($bar);
			$k++;
			
			// Markers
			//Note the display-as-percent hack, because 100-0 percent values are used here instead of 1.0-0.0 values
			$marker = new GoogleChartTextMarker('N*n1y*%');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'c', 30, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 2);
		$this->y_axis->setRange(0, 1);
		$this->chart->setScale(0, 100);
		
		// Finalize
		$this->render('view');
	}
	
	public function death_rate($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2007;
		$this->category_id = array('All causes: Death rate, age-adjusted' => 5852);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->chart->setTitle("Age-Adjusted Death Rate ($this->year)|All Causes");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n0y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__startScaleAtZero();
		$this->__padDataScale(0.1);
		$this->__prepDataAxis('number', 0, 'x');
		
		// Finalize
		$this->render('view');
	}
	
	public function infant_mortality($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2007;
		$this->category_id = array('Infant death rate per 1,000 live births' => 5908);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->chart->setTitle("Infant Death Rate Per 1000 Live Births ($this->year)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__startScaleAtZero();
		$this->__padDataScale(0.1);
		$this->__prepDataAxis('number', 0, 'x');
		
		// Finalize
		$this->render('view');
	}
	
	public function life_expectancy($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2001;
		$year_label = '1997-2001';
		$this->category_id = array('Average life expectancy (1997-2001)' => 5909);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->chart->setTitle("Average Life Expectancy ($year_label)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('number', 0, 'x');
		$this->min--;
		$this->max++;
		$this->__roundDataScale('x', 1, 1);
		
		// Finalize
		$this->render('view');
	}
	
	public function years_of_potential_life_lost($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$year_label = '2004-2006';
		$this->category_id = array('Years of potential life lost before age 75 (2004-2006)' => 5910);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->chart->setTitle("Years of Potential Life Lost Before Age 75 ($year_label)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);
				
		// Markers
		$marker = new GoogleChartTextMarker('N*n2sy*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($labels));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('number', 0, 'x');
		$this->__startScaleAtZero('x');
		$this->__padDataScale(0.1);
		
		// Finalize
		$this->render('view');
	}
	
	public function self_rated_poor_health($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2008;
		$this->category_id = array('Self-Rated Health Status: Fair/Poor (\'02-\'08)' => 5911); //percent
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(2)->setGroupSpacing(150)->setBarWidth(75);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Self-rated Health Status: Fair/Poor (2002-2008)");
		
		// Gather data
		$category_id = end($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->chart->addData($data_set);

		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 'b', 0, 2, 'e');
		$this->chart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('percent', 1);
		$this->__startScaleAtZero('y');
		$this->__padDataScale(0.1);
		
		// Finalize
		$this->__renderIfDataIsAvailable($this->values[0]);
	}

	public function unhealthy_days($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2008;
		$date_label = '2002-2008';
		$this->category_id = array(
			'Avg number of physically unhealthy days per month (2002 to 2008)' => 5913,
			'Avg number of mentally unhealthy days per month (2002 to 2008)' => 5914
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->chart->setBarSpacing(5)->setGroupSpacing(130)->setBarWidth(50);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Average Number of Unhealthy Days Per Month ($date_label)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n2y*');
			$marker->setData($bar);
			$marker->setColor('000000');
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Physically Unhealthy', 'Mentally Unhealthy'));
		$this->chart->addAxis($this->x_axis);
		$this->__prepDataAxis('number', 1);
		$this->__startScaleAtZero('y');
		$this->__padDataScale(0.1);
		
		// Finalize
		$this->render('view');
	}
	
	// Variation: Pie
	public function death_rate_by_cause($county = 1) {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// General parameters
		$this->year = 2007;
		$this->category_id = array(
			'Malignant neoplasms' => 5868,	// All of these are the death rate, age adjusted
			'Diabetes mellitus' => 5872,
			'Alzheimer\'s disease' => 5876,
			'Major cardiovascular diseases' => 5880,
			'Influenza and pneumonia' => 5884,
			'Chronic lower respiratory diseases' => 5888,
			'Chronic liver disease and cirrhosis' => 5892,
			'Nephritis, nephrotic syndrome, and nephrosis' => 5896,
			'Motor vehicle accidents' => 5900
 		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GooglePieChart('pc', 300, 400);
		$this->chart->setLegendPosition('bv');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Age-Adjusted Death Rate by Cause ($this->year)|Outer: $location_names[0]    Inner: $location_names[1]");
		$this->chart->setRotationDegree(270);
		
		// Get values
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
	
		// Add the first data set and first legend item
		// Note: Inner is added first
		reset($this->category_id);
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->__formatValues($this->values[0], 'number', 0));
		next($this->category_id);
		$slice->setLegend(key($this->category_id));
		$this->chart->addData($slice);
		
		// Add the remaining legend items
		$count = count($this->category_id);
		for ($n = 2; $n <= $count; $n++) {
			$slice = new GoogleChartData(array());
			//$slice->setColor($pie_colors[$n]);
			next($this->category_id);
			$slice->setLegend(key($this->category_id));
			$this->chart->addData($slice);
		}

		// Finalize
		$this->render('view');
	}
	
	// Variation: Horizontal bar chart
	public function cancer_death_and_incidence_rates($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2006;
		$period_label = '2002-2006';
		$this->category_id = array(
			'Incidence Rate: All Cancers' => 5918,
			'Death Rate: All Cancers' => 5920, 	 
			'Incidence Rate: Lung and Bronchus Cancer' => 5922,
			'Death Rate: Lung and Bronchus Cancer' => 5924
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', 500, 270);
		$this->chart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(8);
		$this->chart->setLegendPosition('bv');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Cancer Incidence and Death Rates|($period_label)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 2, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('number', 0, 'x');
		$this->__startScaleAtZero('x');
		
		// Finalize
		$this->render('view');
	}
	
	// Variation: Horizontal bar chart
	public function lung_diseases($county = 1) {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Total Asthma' => 5834,
			'Pediatric Asthma' => 5835,
			'Adult Asthma' => 5836,
			'Chronic Bronchitis' => 5837,
			'Emphysema' => 5838 
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$labels = $this->__getLocationLabels();
		
		// Handle requests
		if (isset($this->params['requested'])) { 
			return $this->__handleRequests();
		}
		
		// Create chart
		$this->chart = new GoogleBarChart('bhg', 500, 250);
		$this->chart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(12);
		$this->chart->setLegendPosition('b');
		$this->chart->setLegendLabelOrder('l');
		$this->chart->setTitle("Lung Disease Incidence Rates Per 1,000 Population ($this->year)");
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->chart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n2sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->chart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->__prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->category_id)));
		$this->chart->addAxis($this->y_axis);
		$this->__prepDataAxis('number', 0, 'x');
		$this->__startScaleAtZero('x');
		
		// Finalize
		$this->render('view');
	}
	
	// Only displays a table, but this method is present to provide the table's source
	public function federal_spending($county = 1) {
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Total Federal Goverment Expenditure' => 5822,
 	 		'% WRT state' => 5823,
 	 		'County Rank out of 92*' => 5824
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
	}
	
	public function public_assistance($county = 1) {	 	
	 	// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Women, Infants, and Children (WIC) Participants' => 5783,
		 	'Women, Infants, and Children (WIC) Participants Rank' => 5784,
		 	'Monthly Average of Families Receiving TANF' => 5785,
		 	'Monthly Average of Families Receiving TANF Rank' => 5786,
		 	'Monthly Average of Persons Issued Food Stamps (FY)' => 5787,
		 	'Monthly Average of Persons Issued Food Stamps (FY) Rank' => 5788
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		
		// Handle requests
		if (isset($this->params['requested'])) {
			return $this->__handleRequests();
		}
	}
	
	public function grants_awarded() {
		$this->start_date = 1995;
		$this->end_date = 2010;
		
		// Create chart
		$this->chart = new GoogleChart('lc', $this->default_width, $this->default_height);
		
		// Add line
		$grants = $this->requestAction("data/getGrantsAwarded");
		$this->dates = range(1995, 2010);
		foreach ($this->dates as $year) {
			$this->values[] = isset($grants[$year]) ? count($grants[$year]) : 0;	
		}
		$line = new GoogleChartData($this->values);
		$this->chart->addData($line);
		
		// Default prep
		$this->__prepChart();
		$this->__prepLabelAxis();
		$this->__prepDataAxis();
		
		// Finalize
		$this->chart->setTitle("Brownfield Grants Awarded in Indiana ($this->start_date - $this->end_date)");
		$this->render('view');
	}
}