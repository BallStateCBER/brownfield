<?php
App::Import ('model', 'Report');
class ChartReport extends Report {
	public $useTable = false;
	
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
	
	public $gchart = null;			// GoogleChart object
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
	
	// Set by setXAxisLabels() and used in writing chart titles
	public $start_date = null;
	public $end_date = null;
	
	/* Chart::getChartList() should be updated whenever charts are added or when their method 
	 * names or human-readable titles are changed. This is used by the navigation sidebar and 
	 * to test whether or not user-supplied chart names are valid. */
	public function getChartList() {
		return array(
			'demographics' => array(
				'population' => 'Population',
				'population_growth' => 'Population Growth',
				'density' => 'Population and Housing Units Density',
				'population_age_breakdown' => 'Population by Age',
				'female_age_breakdown' => 'Female Age Breakdown',
				'population_by_sex' => 'Population By Sex',
				'dependency_ratios' => 'Dependency Ratios',
				'educational_attainment' => 'Educational Attainment',
				'graduation_rate' => 'High School Graduation Rates',
				'household_size' => 'Average Household Size',
				'households_with_minors' => 'Households With People Under 18',
				'household_types_with_minors' => 'Households With People Under 18, Breakdown By Type',
				'households_with_over_60' => 'Households With People Over 60',
				'poverty' => 'Poverty',
				'lunches' => 'Free and Reduced Lunches',
				'disabled' => 'Disabled Population',
				'disabled_ages' => 'Disabled Age Breakdown',
				'public_assistance' => 'Public Assistance'
			),
			'economy' => array(
				'share_of_establishments' => 'Percent Share of Total Establishments',
				'employment_growth' => 'Employment Growth',
				'employment_trend' => 'Employment Trend',
				'unemployment_rate' => 'Unemployment Rate',
				'personal_and_household_income' => 'Personal and Household Income',
				'income_inequality' => 'Income Inequality',
				'federal_spending' => 'Federal Spending'
			),
			'health' => array(
				'birth_rate' => 'Crude Birth Rate',
				'birth_rate_by_age' => 'Birth Rate by Age Group',
				'birth_measures' => 'Birth Measures',
				'fertility_rates' => 'Fertility Rates',
				'deaths_by_sex' => 'Deaths By Sex',
				'death_rate' => 'Death Rate',
				'death_rate_by_cause' => 'Death Rate By Cause',
				'infant_mortality' => 'Infant Mortality',
				'life_expectancy' => 'Life Expectancy',
				'years_of_potential_life_lost' => 'Years of Potential Life Lost',
				'self_rated_poor_health' => 'Self-Rated Poor Health',
				'unhealthy_days' => 'Average Unhealthy Days per Month',
				'cancer_death_and_incidence_rates' => 'Cancer Death and Incidence Rates',
				'lung_diseases' => 'Lung Diseases'
			)
		);
	}
	
	public function isValidChart($action) {
		$all_charts = $this->getChartList();
		$is_chart = false;
		foreach ($all_charts as $tab => $tabs_charts) {
			foreach ($tabs_charts as $chart_code => $chart_title) {
				if ($chart_code == $action) {
					$is_chart = true;
					break 2;
				}
			}
		}
		return $is_chart;
	}
	
	public function error($code = 1) {
		switch ($code) {
			case 1: // Chart not found (invalid county id
			default:
				$error_img_url = '../webroot/img/error_chart_not_found.png';
				break;
		}
		$image = file_get_contents($error_img_url);
		$this->set(array('image' => $image));
	}
	
	public function getDataScale() {
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
	public function padDataScale($padding_multiplier = null) {
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
		$this->gchart->setScale($this->bottom, $this->top);
	}
	
	// Should be called after all other axis-related methods
	// If padDataScale is used, it should be called AFTER this method
	public function startScaleAtZero($axis = 'y') {
		if (! (isset($this->bottom) && isset($this->top))) {
			$this->getDataScale();
		}
		$this->bottom = $this->min = 0;
		$this->gchart->setScale(0, $this->top);
		$axis_var = $axis.'_axis';
		$this->$axis_var->setRange(0, $this->top, $this->data_step);
	}
	
	public function prepChart($pad = true) {
		// Set scale
		if (! ($this->bottom && $this->top)) {
			$this->getDataScale();
		}
		if ($pad) {
			$this->padDataScale();
		}
		$this->gchart->setScale($this->bottom, $this->top);
		
		// Set default styling
		$this->gchart->setGridLines(10, 10, 1, 5);
	}
	
	// This is generally the Y axis, but is X for horizontal bar charts 
	public function prepDataAxis($display_type = 'number', $display_precision = 0, $axis = 'y') {
		$axis_var = $axis.'_axis';
		
		// Create axis
		$this->$axis_var = new GoogleChartAxis($axis);

		// Set the axis label styles
		/* Doesn't work with the new array-of-categoryIDs system, kept because this kind of behavior will be
		 * necessary for custom charts. 
		if (! $display_type) {
			$this->loadModel('DataCategory');
			$this->DataCategory->read(array('display_type', 'display_precision'), $this->data_categories);
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
			$this->getDataScale();
		}
		
		$this->$axis_var->setRange($this->bottom, $this->top, $this->data_step);
		
		// Add axis to chart
		$this->gchart->addAxis($this->$axis_var);
	}
	
	// Usually the X axis, but is Y for horizontal bar charts
	public function prepLabelAxis($axis = 'x') {
		$axis_var = $axis.'_axis';
		
		// Create axis
		$this->$axis_var = new GoogleChartAxis($axis);
		
		// Set range of x-axis labels
		$this->setXAxisLabels();
		
		// Add axis to chart
		$this->gchart->addAxis($this->$axis_var);
	}
	
	/* Note: This assumes that the granularity implied in the earliest survey_date 
	 * is the granularity used in all following data. This cannot currently properly
	 * display data sets with mixed granularity (yearly, quarterly, monthly, daily) */ 
	public function setXAxisLabels() {
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
	
	// Shade area below 0%
	public function drawLineAtZero() {
		if ($this->bottom < 0) {
			$value_marker = new GoogleChartRangeMarker();
			$span = $this->top - $this->bottom;
			$range_to = abs(round($this->bottom / $span, 4));
			$range_from = $range_to - 0.005;
			$color = '222222';
			$value_marker->setRangeMarker("r,$color,0,$range_from,$range_to");
			$this->gchart->addMarker($value_marker);
		}
	}
	
	// Round out the chart data scale / label range
	// (so that the chart's data axis begins and ends on nice, round numbers)
	public function roundDataScale($data_axis, $round_by, $step = false) {
		$axis_name = $data_axis.'_axis';
		$new_min = floor($this->min * $round_by) / $round_by; 
		$new_max = ceil($this->max * $round_by) / $round_by;
		$this->$axis_name->setRange($new_min, $new_max, $step);
		$this->gchart->setScale($new_min, $new_max);
	}
	
	/* Use this instead of $this->render('/charts/index') if data may not be available,
	 * depending on the county. Pass a variable to this method that should be something other
	 * than zero, null, a blank string, or an empty array. If it's not, the 'data unavailable'
	 * image will be rendered instead of the chart. */
	public function renderIfDataIsAvailable($values) {
		if (empty($values)) {
			$this->render('/charts/county_data_unavailable');
		} else {
			return $this->gchart;
		}
	}
	
	public function getOutput($topic) {
		return $this->{$topic}();
	}
	
	public function getStateName($county_id) {
		$Location = ClassRegistry::init('Location');
		$state_id = $Location->getStateIDFromCountyID($county_id);
		return $Location->getStateFullName($state_id);
	}
	
	/****** ****** Individual topics below ****** ******/
	
	
	
	public function population() {
		// Create chart
		$this->gchart = new GoogleChart('lc', $this->default_width, $this->default_height);
	
		// Gather data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		
		// Add line
		$line = new GoogleChartData($this->values[0]);
		$this->gchart->addData($line);
	
		// Default prep
		$this->prepChart();
		$this->prepLabelAxis();
		$this->prepDataAxis();
		
		// Finalize
		$county_name = $this->locations[0][2];
		$state_name = $this->locations[1][2];
		$this->gchart->setTitle("Population of $county_name, $state_name ({$this->start_date} - {$this->end_date})");
	}
	
	public function population_growth() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartRangeMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartRangeMarker.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(12)->setBarWidth(22);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Population Growth");
		
		// Gather data
		$county_growth_values = $state_growth_values = array();
		$category_id = array_pop($this->data_categories);
		list($this->dates, $county_values) = $this->Datum->getValues($category_id, $this->locations[0][0], $this->locations[0][1], $this->dates);
		list($this->dates, $state_values) = $this->Datum->getValues($category_id, $this->locations[1][0], $this->locations[1][1], $this->dates);
		$date_pairs = array(
			array(2005, 2009), 
			array(2000, 2009), 
			array(1995, 2009), 
			array(1990, 2009), 
			array(1985, 2009), 
			array(1980, 2009), 
			array(1975, 2009), 
			array(1970, 2009)
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p0y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->prepLabelAxis();
		$this->prepDataAxis('percent', 1);
		$this->x_axis->setLabels(array_keys($this->values[0]));
		$this->drawLineAtZero();
	}

	public function density() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Density Per Square Mile of Land Area ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*f0y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Population density', 'Housing units density'));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 1);
	}
	
	public function population_age_breakdown() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(1)->setGroupSpacing(5)->setBarWidth(5);
		$this->gchart->setLegendPosition('r');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Population By Age ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);	
		}
		
		// Scale, axes, labels
		$this->data_step = 0.05;
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->startScaleAtZero('x');
	}
	
	public function female_age_breakdown() {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// Create chart
		$this->gchart = new GooglePieChart('pc', $this->default_width, $this->default_height);
		$this->gchart->setLegendPosition('r');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Female Age Breakdown ($this->year)|Outer: {$this->locations[0][2]}      Inner: {$this->locations[1][2]}");
		$this->gchart->setRotationDegree(270);
		
		// Get values
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
				
		// Add the first data set and first legend item
		reset($this->data_categories);
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->formatValues($this->values[0], 'percent', 0));
		next($this->data_categories);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		// Add the remaining legend items
		for ($n = 2; $n < count($this->data_categories); $n++) {
			next($this->data_categories);
			$slice = new GoogleChartData(array());
			$slice->setLegend(key($this->data_categories));
			$slice->setColor($this->colors[$n]);
			$this->gchart->addData($slice);
		}
	}
	
	public function population_by_sex() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(10)->setBarWidth(20);
		$this->gchart->setLegendPosition('r');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Population By Sex ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels
		$this->data_step = 0.005;
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->roundDataScale('x', 20);
	}
	
	public function dependency_ratios() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(55)->setBarWidth(30);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setTitle("Dependency Ratio Per 100 People ($this->year)");
		$this->gchart->setLegendLabelOrder('l');
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}

		// Scale, axes, labels
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->data_categories));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 1);
		$this->startScaleAtZero();
	}
	
	public function educational_attainment() {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// Create chart
		$this->gchart = new GooglePieChart('pc', 300, 400);
		$this->gchart->setLegendPosition('bv');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Educational Attainment ($this->year)|Population 25 Years and Over|Outer: {$this->locations[0][2]}|Middle: {$this->locations[1][2]}   Inner: United States");
		$this->gchart->setRotationDegree(270);
		
		// Get values
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
	
		// Add the first data set and first legend item
		// Note: Inner is added first, then middle, then outer 
		reset($this->data_categories);
		$slice = new GoogleChartData($this->values[2]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		next($this->data_categories);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->formatValues($this->values[0], 'percent'));
		next($this->data_categories);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		// Add the remaining legend items
		$count = count($this->data_categories);
		for ($n = 3; $n <= $count; $n++) {
			$slice = new GoogleChartData(array());
			//$slice->setColor($pie_colors[$n]);
			next($this->data_categories);
			$slice->setLegend(key($this->data_categories));
			$this->gchart->addData($slice);
		}
	}
	
	public function graduation_rate() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		$county_name = $this->Location->getLocationName(2, $this->county_id, true);
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			if ($value) {
				$this->values[$loc_key] = $value;
			} else {
				unset($this->locations[$loc_key]);
			}
		}
		
		// Adapt to the wide range of data and location counts
		$location_count = count($this->locations);
		$bar_width = ($location_count <= 8) ? 20 : 15;
		$chart_height = 70 + (($bar_width + 10) * $location_count);
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $chart_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(10)->setBarWidth($bar_width);
		$this->gchart->setLegendPosition('r');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("High School Graduation Rate|$county_name ($this->year)");
		
		// Draw bars (county color for all school corporations, state color for average)
		$data_set = new GoogleChartData($this->values);
		$colors = array_pad(array(), ($location_count - 1), $this->colors[0]);
		$colors[] = $this->colors[1];
		$data_set->setColor($colors);		
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p1y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
				
		// Scale, axes, labels
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('percent', 1, 'x');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$count_horizontal_gridlines = 100 / count($this->locations);
		$this->gchart->setGridLines(10, $count_horizontal_gridlines, 1, 5);
		$data_step = 0.02 * floor(($this->max - $this->min) / 0.1);
		$this->roundDataScale('x', 20, $data_step);
		$this->padDataScale(0.2);
	}
	
	public function household_size() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(90)->setBarWidth(60);
		$this->gchart->setTitle("Average Household Size ($this->year)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 'b', 0, 2, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($this->getLocationNames());
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 2);
	}
	
	public function households_with_minors() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhs', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(30)->setGroupSpacing(0)->setBarWidth(30);
		$this->gchart->setTitle("Households With One or More People Under 18 Years ($this->year)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
				
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('percent', 2, 'x');		
		$this->roundDataScale('x', 10);
	}
	
	public function household_types_with_minors() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvs', 400, 500);
		$this->gchart->setBarSpacing(50)->setGroupSpacing(100)->setBarWidth(50);
		$this->gchart->setTitle("Households With One Or|More People Under 18 Years: | Breakdown of Household Types ($this->year)");
		$this->gchart->setLegendPosition('bv');
		$this->gchart->setLegendLabelOrder('r');
		$this->gchart->setScale(0, 100);
		
		// Gather data
		$total_households_cat_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $this->year);
			foreach ($this->data_categories as $category => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / $total_households;
				$this->values[$category][$location[2]] = $value;
			}
		}
		
		// Draw bars
		$k = 0;
		foreach ($this->data_categories as $category_name => $category_id) {
			$bar = new GoogleChartData($this->values[$category_name]);
			$bar->setColor($this->pie_colors[$k]);
			$bar->setLegend($category_name);
			$this->gchart->addData($bar);
			$k++;
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'c', 30, 0, 'e');
			$this->gchart->addMarker($marker);
		}

		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($this->getLocationNames());
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 0, 'y');
		$this->gchart->setScale(0, 1);
		$this->y_axis->setRange(0, 1);
	}
	
	public function households_with_over_60() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhs', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(30)->setGroupSpacing(0)->setBarWidth(30);
		$this->gchart->setTitle("Households with one or more people 60 years and over ($this->year)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('percent', 2, 'x');		
		$this->roundDataScale('x', 20);
	}
	
	public function poverty() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Percentage of Population in Poverty ($this->year)");
		
		// Draw bars
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->data_categories));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 0);
		$this->roundDataScale('y', 20, 0.05);
	}
	
	public function lunches() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(65)->setBarWidth(40);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Free and Reduced Lunches ($this->year)");
		
		// Gather data
		foreach ($this->locations as $loc_key => $location) {
			foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->data_categories));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 0);
		$this->startScaleAtZero('y');
	}
	
	public function disabled() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(90)->setBarWidth(60);
		$this->gchart->setTitle("Percent of Population Disabled ($this->year)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 't', 0, 10, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($this->getLocationNames());
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 1);
		$this->roundDataScale('y', 100);
	}
	
	public function disabled_ages() {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// Create chart
		$this->gchart = new GooglePieChart('pc', $this->default_width, $this->default_height);
		$this->gchart->setLegendPosition('r');
		$this->gchart->setLegendLabelOrder('l');
		$state_name = $this->getStateName($this->locations[0][1]);
		$this->gchart->setTitle("Disabled Age Breakdown For|{$this->locations[0][2]}, $state_name ($this->year)");
		$this->gchart->setRotationDegree(270);
		
		// Get values
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
				
		// Add the first data set and first legend item
		reset($this->data_categories);
		$slice = new GoogleChartData($this->values[0]);
		$slice->setLegend(key($this->data_categories));
		$slice->setLabels($this->formatValues($this->values[0], 'percent'));
		$slice->setColor($this->pie_colors[0]);
		$this->gchart->addData($slice);
		
		// Add the remaining legend items
		for ($n = 1; $n < count($this->data_categories); $n++) {
			next($this->data_categories);
			$slice = new GoogleChartData(array());
			$slice->setLegend(key($this->data_categories));
			$slice->setColor($this->pie_colors[$n]);
			$this->gchart->addData($slice);
		}
	}
	
	public function share_of_establishments() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Percent Share of Total Establishments ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Logistics', 'Manufacturing'));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 1);
		$this->startScaleAtZero('y');
	}
	
	public function employment_growth() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartRangeMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartRangeMarker.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		$this->locations[1][2] .= ' (not seasonally adjusted)';
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(50)->setBarWidth(30);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Employment Growth");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->data_categories));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 1);
		$this->drawLineAtZero();
	}
	
	public function employment_trend() {
		// Create chart
		$this->gchart = new GoogleChart('lc', $this->default_width, $this->default_height);
		
		// Add line
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		$line = new GoogleChartData($this->values[0]);
		$line->setColor($this->colors[0]);
		$this->gchart->addData($line);
		
		// Default prep
		$this->prepChart();
		$this->prepLabelAxis();
		$this->prepDataAxis();
		
		// Finalize
		$county_name = $this->locations[0][2];
		$state_name = $this->getStateName($this->locations[0][1]);
		$this->gchart->setTitle("Employment in $county_name, $state_name ($this->start_date - $this->end_date)");
	}
	
	public function unemployment_rate() {
		$this->locations[1][2] .= ' (not seasonally adjusted)';
		
		// Create chart
		$this->gchart = new GoogleChart('lc', $this->default_width, $this->default_height);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				list($this->dates, $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
			}
		}
		$this->convertPercentValues();
		
		// Add line
		foreach ($this->locations as $loc_key => $location) {
			$line = new GoogleChartData($this->values[$loc_key]);
			$line->setColor($this->colors[$loc_key]);
			$line->setLegend($location[2]);
			$this->gchart->addData($line);
		}
		
		// Default prep		
		$this->prepChart();
		$this->prepLabelAxis();
		$this->prepDataAxis('percent', 1);
		$this->roundDataScale('y', 100);
		$this->gchart->setTitle("Unemployment Rate ($this->start_date - $this->end_date)");
	}
	
	public function personal_and_household_income() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(100)->setBarWidth(40);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Personal and Household Income ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N$*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 't', 0, 10, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart(false);
		$this->padDataScale(0.2);
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Per Capita Personal Income ($)', 'Median Household Income ($)'));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('currency', 1);
		$this->startScaleAtZero('y');
	}
	
	public function income_inequality() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		$labels = $this->dates;
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(55)->setBarWidth(30);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Income Inequality");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($labels);
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 1);
	}
	
	public function birth_rate() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->gchart->setTitle("Crude Birth Rate ($this->year)|(Live Births per 1,000 Population)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();		
		/* Since the data range is usually so small, normal "span * 0.1" padding won't provide 
		 * sufficient space for the markers on the right side of the bars. But just adding one 
		 * to $this-> topdoes the trick. */  
		$this->top++; 
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->startScaleAtZero();
		$this->prepDataAxis('number', 0, 'x');
	}
	
	public function birth_rate_by_age() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(50)->setBarWidth(30);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Birth Rate By Age Group ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n1y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array_keys($this->data_categories));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 0);
		$this->startScaleAtZero('y');
	}
	
	public function birth_measures() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
				
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(8);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Birth Measures ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*p2y*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->gchart->addAxis($this->y_axis);
		$this->startScaleAtZero();
		$this->prepDataAxis('percent', 0, 'x');
	}
	
	public function fertility_rates() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(25)->setBarWidth(20);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Fertility Rates ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->gchart->addAxis($this->y_axis);
		$this->startScaleAtZero();
		$this->prepDataAxis('number', 0, 'x');
	}
	
	public function deaths_by_sex() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvs', 300, 500);
		$this->gchart->setBarSpacing(80)->setGroupSpacing(80)->setBarWidth(50);
		$this->gchart->setTitle("Deaths By Sex ($this->year)");
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('r');
		
		// Gather data
		foreach ($this->locations as $loc_key => $location) {
			foreach ($this->data_categories as $category => $category_id) {
				$this->values[$category][$location[2]] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Draw bars
		$k = 0;
		foreach ($this->data_categories as $category_name => $category_id) {
			$bar = new GoogleChartData($this->values[$category_name]);
			$bar->setColor($this->pie_colors[$k]);
			$bar->setLegend($category_name);
			$this->gchart->addData($bar);
			$k++;
			
			// Markers
			//Note the display-as-percent hack, because 100-0 percent values are used here instead of 1.0-0.0 values
			$marker = new GoogleChartTextMarker('N*n1y*%');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'c', 30, 0, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($this->getLocationNames());
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 2);
		$this->y_axis->setRange(0, 1);
		$this->gchart->setScale(0, 100);
	}
	
	public function death_rate() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->gchart->setTitle("Age-Adjusted Death Rate ($this->year)|All Causes");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n0y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->startScaleAtZero();
		$this->padDataScale(0.1);
		$this->prepDataAxis('number', 0, 'x');
	}
	
	public function infant_mortality() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->gchart->setTitle("Infant Death Rate Per 1000 Live Births ($this->year)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->startScaleAtZero();
		$this->padDataScale(0.1);
		$this->prepDataAxis('number', 0, 'x');
	}
	
	public function life_expectancy() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->gchart->setTitle("Average Life Expectancy ($this->years_label)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
		
		// Markers
		$marker = new GoogleChartTextMarker('N*n2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('number', 0, 'x');
		$this->min--;
		$this->max++;
		$this->roundDataScale('x', 1, 1);
	}
	
	public function years_of_potential_life_lost() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
				
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(40)->setBarWidth(30);
		$this->gchart->setTitle("Years of Potential Life Lost Before Age 75 ($this->years_label)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->values[$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);
				
		// Markers
		$marker = new GoogleChartTextMarker('N*n2sy*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('l', 'v', 2, 0, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse($this->getLocationNames()));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('number', 0, 'x');
		$this->startScaleAtZero('x');
		$this->padDataScale(0.1);
	}
	
	public function self_rated_poor_health() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
			
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(150)->setBarWidth(75);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Self-rated Health Status: Fair/Poor ($this->years_label)");
		
		// Gather data
		$category_id = end($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			if ($value) {
				$this->values[$loc_key] = $value;
			} else {
				$this->error = 2; // Required data unavailable
				return;
			}
		}
		
		// Add bars
		$data_set = new GoogleChartData($this->values);
		$data_set->setColor(array_slice($this->colors, 0, count($this->locations)));
		$this->gchart->addData($data_set);

		// Markers
		$marker = new GoogleChartTextMarker('N*p2y*');
		$marker->setData($data_set);
		$marker->setColor('000000'); 
		$marker->setPlacement('h', 'b', 0, 2, 'e');
		$this->gchart->addMarker($marker);
		
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels($this->getLocationNames());
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('percent', 1);
		$this->startScaleAtZero('y');
		$this->padDataScale(0.1);
	}

	public function unhealthy_days() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bvg', $this->default_width, $this->default_height);
		$this->gchart->setBarSpacing(5)->setGroupSpacing(130)->setBarWidth(50);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Average Number of Unhealthy Days Per Month ($this->years_label)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				if ($value) {
					$this->values[$loc_key][$label] = $value;
				} else {
					$this->error = 2; // Required data unavailable
					return;
				}
			}
		}
		
		// Add bars
		foreach ($this->locations as $key => $set) {
			$location_name = $set[2];
			$bar = new GoogleChartData($this->values[$key]);
			$bar->setColor($this->colors[$key]);
			$bar->setLegend($location_name);
			$this->gchart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n2y*');
			$marker->setData($bar);
			$marker->setColor('000000');
			$marker->setPlacement('h', 'b', 0, 2, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->x_axis = new GoogleChartAxis('x');
		$this->x_axis->setLabels(array('Physically Unhealthy', 'Mentally Unhealthy'));
		$this->gchart->addAxis($this->x_axis);
		$this->prepDataAxis('number', 1);
		$this->startScaleAtZero('y');
		$this->padDataScale(0.1);
	}
	
	// Variation: Pie
	public function death_rate_by_cause() {
		App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
		
		// Create chart
		$this->gchart = new GooglePieChart('pc', 300, 400);
		$this->gchart->setLegendPosition('bv');
		$this->gchart->setLegendLabelOrder('l');
		$location_names = $this->getLocationNames();
		$county_name = $location_names[0];
		$state_name = $location_names[1];
		$this->gchart->setTitle("Age-Adjusted Death Rate by Cause ($this->year)|Outer: $county_name    Inner: $state_name");
		$this->gchart->setRotationDegree(270);
		
		// Get values
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
	
		// Add the first data set and first legend item
		// Note: Inner is added first
		reset($this->data_categories);
		$slice = new GoogleChartData($this->values[1]);
		$slice->setColor($this->pie_colors);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		$slice = new GoogleChartData($this->values[0]);
		$slice->setColor($this->pie_colors);
		$slice->setLabels($this->formatValues($this->values[0], 'number', 0));
		next($this->data_categories);
		$slice->setLegend(key($this->data_categories));
		$this->gchart->addData($slice);
		
		// Add the remaining legend items
		$count = count($this->data_categories);
		for ($n = 2; $n <= $count; $n++) {
			$slice = new GoogleChartData(array());
			//$slice->setColor($pie_colors[$n]);
			next($this->data_categories);
			$slice->setLegend(key($this->data_categories));
			$this->gchart->addData($slice);
		}
	}
	
	// Variation: Horizontal bar chart
	public function cancer_death_and_incidence_rates() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
		
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', 500, 270);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(8);
		$this->gchart->setLegendPosition('bv');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Cancer Incidence and Death Rates|($this->years_label)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);
			
			// Markers
			$marker = new GoogleChartTextMarker('N*n0sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 2, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('number', 0, 'x');
		$this->startScaleAtZero('x');
	}
	
	// Variation: Horizontal bar chart
	public function lung_diseases() {
		App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
		App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
				
		// Create chart
		$this->gchart = new GoogleBarChart('bhg', 500, 250);
		$this->gchart->setBarSpacing(2)->setGroupSpacing(10)->setBarWidth(12);
		$this->gchart->setLegendPosition('b');
		$this->gchart->setLegendLabelOrder('l');
		$this->gchart->setTitle("Lung Disease Incidence Rates Per 1,000 Population ($this->year)");
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
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
			$this->gchart->addData($bar);

			// Markers
			$marker = new GoogleChartTextMarker('N*n2sy*');
			$marker->setData($bar);
			$marker->setColor('000000'); 
			$marker->setPlacement('l', 'v', 2, 0, 'e');
			$this->gchart->addMarker($marker);
		}
				
		// Scale, axes, labels	
		$this->prepChart();
		$this->y_axis = new GoogleChartAxis('y');
		$this->y_axis->setLabels(array_reverse(array_keys($this->data_categories)));
		$this->gchart->addAxis($this->y_axis);
		$this->prepDataAxis('number', 0, 'x');
		$this->startScaleAtZero('x');
	}
	
	public function grants_awarded() {
		$this->start_date = 1995;
		$this->end_date = 2010;
		
		// Create chart
		$this->gchart = new GoogleChart('lc', $this->default_width, $this->default_height);
		
		// Add line
		$grants = ClassRegistry::init('Datum')->getGrantsAwarded();
		$this->dates = range(1995, 2010);
		foreach ($this->dates as $year) {
			$this->values[] = isset($grants[$year]) ? count($grants[$year]) : 0;	
		}
		$line = new GoogleChartData($this->values);
		$this->gchart->addData($line);
		
		// Default prep
		$this->prepChart();
		$this->prepLabelAxis();
		$this->prepDataAxis();
		
		// Finalize
		$state_name = $this->getStateName($this->locations[0][1]);
		$this->gchart->setTitle("Brownfield Grants Awarded in $state_name ($this->start_date - $this->end_date)");
	}
}