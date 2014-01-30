<?php
App::uses('GoogleCharts', 'GoogleCharts.Lib');
App::uses('Datum', 'Model');
App::uses('Location', 'Model');
App::Import ('model', 'Report');

class SvgChartReport extends Report {
	public $useTable = false;
	
	public $category_id = null;
	public $locations = array();	// Each member is an array of [loc_type_id, loc_id]
	public $colors = array(
		'#FFCC33',	// orangish yellow
		'#81CF5A',	// green
		'#5F8AFF',	// blue
		'#FF7F00', 	// orange
		'#FF0000', 	// red
		'#BF00FF', 	// purple
		'#3F00FF'	// blue
	);
	public $pie_colors = array(
		'#CF1920',
		'#FFB900',
		'#8219CF',
		'#195BCF',
		'#19C2CF',
		'#23BF2A',
		'#BF9523',
		'#8F6635',
		'#CFCFCF'	//gray
	);
	public $defaultOptions = array(
		'width' => 725,
		'height' => 300,
		'legend' => array(
			'position' => 'bottom',
			'alignment' => 'center'
		),
		'titleTextStyle' => array(
			'color' => '#000'
		),
		'vAxis' => array(
			'minValue' => 0
		)
	);
	
	// Supplied by getTable()'s parameters
	public $segment = null;
	public $data = array();
	public $structure = array();
	
	// Set by segment-specific methods
	public $type = null;		// e.g. BarChart
	public $rows = array();		// array(array('category' => 'Foo', 'value' => 123), ...)
	public $columns = array();	
	public $options = array();	// Includes 'title', etc.
	public $footnote = "";
	public $callbacks = array();// array('eventName' => 'functionName or anonymous function')
	
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
				'households_with_over_65' => 'Households With People Over 65',
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
	
	public function getOutput($topic) {
		if (method_exists($this, $topic)) {
			return $this->{$topic}();
		}
		return false;
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
	
	public function isValidCounty($county_id) {
		return (is_numeric($county_id) && $county_id >= 1 && $county_id <= 92);
	}
	
	private function getStartYear() {
		$dates = array_keys($this->values);
		return substr(reset($dates), 0, 4);
	}
	
	private function getEndYear() {
		$dates = array_keys($this->values);
		return substr(end($dates), 0, 4);
	}
	
	// If no parameters are passed, assumes the first location specified in $this->locations
	private function getLocName($loc_type_id = null, $loc_id = null, $full_name = false) {
		if ($loc_type_id == null) {
			$loc_type_id = $this->locations[0][0];
			$loc_id = $this->locations[0][1];	
		}
		$Location = new Location();
		return $Location->getLocationName($loc_type_id, $loc_id, $full_name);
	}
	
	/**
	 * Returns the appropriate label for the year(s) included in this data set
	 */
	public function getYears() {
		// If 'dates' were not specified (so all available dates are collected),
		// populate $this->dates from the collected data
		if (empty($this->dates)) {
			foreach ($this->data as $category_id => $loc_keys) {
				foreach ($loc_keys as $loc_key => $dates) { 
					foreach ($dates as $date => $value) {
						if (! in_array($date, $this->dates)) {
							$this->dates[] = $date;
						}
					}
				}
			}
		}
		$max_year = substr(max($this->dates), 0, 4);
		$min_year = substr(min($this->dates), 0, 4);
		if ($max_year == $min_year) {
			return $max_year;
		} else {
			return "$min_year-$max_year";	
		}
	}
	
	/**
	 * @param string $segment Equal to the name of a chart-generating method in this class
	 * @param array $data
	 * @param array $segment_params Keys may include 'locations', 'categories', 'dates', and 'county_id'
	 * @param array $structure Optional array passed to Chart to help with rearranging data
	 * @return GoogleCharts
	 */
	public function getChart($segment, $data, $segment_params, $structure) {
		// TODO: Change references to 'segment' (used in County Profiles) into 'topic' (used in this site, more intuitive)
		$this->segment = $segment;
		$this->data = $data;
		$this->segmentParams = $segment_params;
		$this->structure = $structure;
		
		if (! method_exists($this, $segment)) {
			return array();
		}
		
		$this->{$segment}();
		
		$this->options = array_merge($this->defaultOptions, $this->options);
		
		$chart = new GoogleCharts(null, null, null, null, 'chart_'.$this->segment);
		$chart->type($this->type)
		    ->options($this->options) 
		    ->columns($this->columns)
		    ->callbacks($this->callbacks);
		foreach ($this->rows as $row) {
			$chart->addRow($row);
		}
		return array(
			'chart' => $chart,
			'footnote' => $this->footnote
		);
	}
	
	/**
	 * Sets the format for labels on the data axis (generally the vertical axis, except for horizontal bar charts)
	 * @param string $display_type 'number', 'currency', or 'percent'
	 * @param int $display_precision How many digits after the decimal point to show
	 * @param string $axis Either 'v' or 'h'  
	 */ 
	public function prepDataAxis($display_type = 'number', $display_precision = 0, $axis = 'v') {
		switch ($display_type) {
			case 'percent':
				$format = '#,###';
				if ($display_precision) {
					$format .= '.'.str_repeat('0', $display_precision);
				}
				$format .= '%';
				break;
			case 'currency':
				$format = '$';
				if ($display_precision) {
					$format .= '.'.str_repeat('0', $display_precision);
				}
				$format .= '#,###';
				break;
			default:
				$format = '#,###';
		}
		$axis_key = $axis.'Axis';
		$this->applyOptions(array(
			$axis_key => array(
				'format' => $format
			)
		));
	}
	
	private function applyOptions($options) {
		$options = array_replace_recursive($this->chart->options, $options);
		$this->chart->options($options);
	}
	
	private function applyDefaultOptions() {
		$this->applyOptions($this->defaultOptions);
	}
	
	/**
	 * Rounds the ends of an axis so that it begins and ends on nice, round numbers
	 * @param array $values One-dimensional array of all values
	 * @param string $axis Either 'v' or 'h'
	 * @param int $round_by
	 */
	private function roundDataScale($values, $axis = 'v', $round_by = 20) {
		$min = min($values);
		$min = floor($min * $round_by) / $round_by;
		$max = max($values);
		$max = ceil($max * $round_by) / $round_by;
		$axis_key = $axis.'Axis';
		$this->applyOptions(array(
			$axis_key => array(
				'minValue' => $min,
				'maxValue' => $max,
				'viewWindowMode' => 'explicit',
				'viewWindow' => array(
					'min' => $min,
					'max' => $max
				)
			)
		));
	}
	
	public function population() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("LineChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Population', 
	        	'type' => 'number',
	        	'format' => '#,###'
			)
	    ));
		
		// Gather data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		
		// Add line
		foreach ($this->values[0] as $date => $value) {
			$year = substr($date, 0, 4);
			$this->chart->addRow(array(
				'category' => $year, 
				'value' => $value
			));
		}
		
		// Finalize
		$this->prepDataAxis();
		$county_name = $this->locations[0][2];
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array(
				$this->colors[0]
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Population of $county_name, Indiana (".$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
	}
	
	public function population_growth() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Timespan', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
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
		
		// Add bars
		foreach ($date_pairs as $date_pair) {
			$label = substr($date_pair[0], 0,4)."-".substr($date_pair[1], 0,4);
			$earlier = $date_pair[0].'0000';
			$later = $date_pair[1].'0000';
			$county_value = ($county_values[$later] - $county_values[$earlier]) / $county_values[$earlier];
			$state_value = ($state_values[$later] - $state_values[$earlier]) / $state_values[$earlier];
			$this->chart->addRow(array(
				'category' => implode('-', $date_pair), 
				'county_value' => $county_value,
				'county_annotation' => round($county_value * 100).'%',
				'state_value' => $state_value,
				'state_annotation' => round($state_value * 100).'%'
			));
		}
		
		// Finalize
		$this->prepDataAxis('percent', 0);
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'hAxis' => array(
				'textStyle' => array(
					'fontSize' => 10
				)
			),
			'title' => 'Population Growth'
		));
	}

	public function density() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$categories = array('Population density', 'Housing units density');
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => number_format($values[0]),
				'state_value' => $values[1],
				'state_annotation' => number_format($values[1]),
				'country_value' => $values[2],
				'country_annotation' => number_format($values[2])
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Density Per Square Mile of Land Area ('.$year.')'
		));
	}

	public function population_age_breakdown() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Age Range', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}

		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0] * 100, 1).'%',
				'state_value' => $values[1],
				'state_annotation' => round($values[1] * 100, 1).'%',
				'country_value' => $values[2],
				'country_annotation' => round($values[2] * 100, 1).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'bar' => array(
				'groupWidth' => '80%'
			),
			'chartArea' => array(
				'height' => 550
			),
			'colors' => array_slice($this->colors, 0, 3),
			'hAxis' => array(
				'minValue' => 0
			),
			'height' => 650,
			'title' => 'Population By Age ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'h');
	}

	public function female_age_breakdown() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");		
		$columns = array(
	        'category' => array(
	        	'label' => 'category', 
	        	'type' => 'string'
			)
	    );
		$category_names = array_keys($this->data_categories);
		foreach ($category_names as $k => $category_name) {
	        $columns["cat_$k"] = array(
	        	'label' => $category_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			);
			$columns["annotation_$k"] = array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			);
		}
		$this->chart->columns($columns);
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}

		// Add bars
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($category_names as $k => $category_name) {
				$value = $this->values[$loc_key][$category_name];
				$row["cat_$k"] = $value;
				$row["annotation_$k"] = round($value * 100).'%';
			}
			$this->chart->addRow($row);
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'colors' => array_slice($this->colors, 0, 3),
			'isStacked' => true,
			'title' => 'Female Age Breakdown ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'h');
	}

	public function population_by_sex() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Sex', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}

		// Add bars
		$categories = array_keys($this->data_categories);
		$all_values = array();
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$value = $this->values[$key][$category];
				$values[] = $value;
				$all_values[] = $value;
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0] * 100, 2).'%',
				'state_value' => $values[1],
				'state_annotation' => round($values[1] * 100, 2).'%',
				'country_value' => $values[2],
				'country_annotation' => round($values[2] * 100, 2).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'height' => 200
			),
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Population By Sex ('.$year.')',
			'height' => 300
		));
		$this->prepDataAxis('percent', 0, 'h');
		$this->roundDataScale($all_values, 'h', 20);
	}

	public function dependency_ratios() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Sex', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0], 1),
				'state_value' => $values[1],
				'state_annotation' => round($values[1], 1),
				'country_value' => $values[2],
				'country_annotation' => round($values[2], 1)
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Dependency Ratio Per 100 People ('.$year.')'
		));
	}

	public function educational_attainment() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Age Range', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			}
		}

		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$category = addslashes($category);
			$category = str_replace(', percent', '', $category);
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0] * 100, 1).'%',
				'state_value' => $values[1],
				'state_annotation' => round($values[1] * 100, 1).'%',
				'country_value' => $values[2],
				'country_annotation' => round($values[2] * 100, 1).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'bar' => array(
				'groupWidth' => '80%'
			),
			'chartArea' => array(
				'height' => 550,
				'left' => 250,
				'top' => 50
			),
			'colors' => array_slice($this->colors, 0, 3),
			'hAxis' => array(
				'minValue' => 0
			),
			'height' => 650,
			'title' => 'Educational Attainment, Population 25 Years and Over ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'h');
	}

	public function graduation_rate() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'School corporation', 
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Graduation rate', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		$category_id = end($this->data_categories);
		$i = 1;
		foreach ($this->locations as $loc_key => $location) {
			$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
			if ($value) {
				$this->values[$loc_key] = $value;
			} else {
				unset($this->locations[$loc_key]);
			}
			
			$color = $i < count($this->locations) ? $this->colors[0] : $this->colors[1];
			$this->chart->addRow(array(
				'category' => $location[2],
				'value' => $value,
				'annotation' => round($value * 100, 1).'%',
				'colors' => $color
			));
			$i++;
		}

		// Adapt to the wide range of data and location counts
		$location_count = count($this->locations);
		$bar_width = ($location_count <= 8) ? 20 : 15;
		$chart_height = 70 + (($bar_width + 10) * $location_count);
		
		// Finalize
		$year = $this->getYears();
		$county_name = $this->Location->getLocationName(2, $this->county_id, true);
		$colors = array_fill(0, count($this->locations) - 1, $this->colors[0]);
		$colors[] = $this->colors[1];
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 250,
				'height' => $chart_height - 70
			),
			'colors' => $colors,
			'height' => $chart_height,
			'legend' => array(
				'position' => 'none'
			),
			'title' => $county_name.' High School Graduation Rates ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'h');
	}

	public function household_size() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Average Household Size',
	        	'type' => 'number'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$row['value'] = $this->values[$loc_key][$label];
				$row['annotation'] = $this->values[$loc_key][$label];
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Average Household Size ('.$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
	}

	public function households_with_minors() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Percent of Households',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$all_values = array();
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label] / 100;
				$row['value'] = $value;
				$row['annotation'] = round($value * 100, 2).'%';
				$all_values[] = $value;
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Households With One or More People Under 18 Years ('.$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
		$this->prepDataAxis('percent', 0, 'h');
		$this->roundDataScale($all_values, 'h', 10);
	}

	public function household_types_with_minors() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");		
		$columns = array(
	        'category' => array(
	        	'label' => 'category', 
	        	'type' => 'string'
			)
	    );
		$category_names = array_keys($this->data_categories);
		foreach ($category_names as $k => $category_name) {
			/* The fourth category, "Households with one or more people 
			 * under 18 years" doesn't actually show up on the chart. */
			if ($k == 4) {
				break;	
			}
			
			$category_name = str_replace('\'', '\\\'', $category_name);
			$category_name = str_replace(', percent', '', $category_name);
	        $columns["cat_$k"] = array(
	        	'label' => $category_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			);
			$columns["annotation_$k"] = array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			);
		}
		$this->chart->columns($columns);
		
		// Gather data
		$total_households_cat_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $this->year);
			foreach ($this->data_categories as $category => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / $total_households;
				$this->values[$category][$loc_key] = $value;
			}
		}

		// Add bars
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			$k = 0;
			foreach ($this->data_categories as $category => $category_id) {
				$value = $this->values[$category][$loc_key];
				$row["cat_$k"] = $value;
				$annotation = ($value >= .15) ? round($value * 100, 1).'%' : '';
				$row["annotation_$k"] = $annotation;
				$k++;
				if ($k == 4) {
					break;
				}
			}
			$this->chart->addRow($row);
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 50
			),
			'colors' => array_slice($this->colors, 0, 4),
			'isStacked' => true,
			'legend' => array(
				'position' => 'right'
			),
			'title' => 'Households With One Or More People Under 18 Years, Breakdown of Household Types ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
	}

	public function households_with_over_65() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Area',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Percent of households',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			), 
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		$all_values = array();
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				$value = $value / 100;
				$this->values[$loc_key][$label] = $value;
				$all_values[] = $value;
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = round($value * 100, 2).'%';
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Households with one or more people 65 years and over ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
		$this->roundDataScale($all_values);
	}

	public function poverty() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Sex', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		$all_values = array();
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / 100;
				$this->values[$loc_key][$label] = $value;
				$all_values[] = $value;
			}
		}
		
		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0] * 100, 1).'%',
				'state_value' => $values[1],
				'state_annotation' => round($values[1] * 100, 1).'%',
				'country_value' => $values[2],
				'country_annotation' => round($values[2] * 100, 1).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Percentage of Population in Poverty ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
		$this->roundDataScale($all_values, 10);
	}

	public function lunches() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Sex', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				$this->values[$loc_key][$label] = $value / 100;
			}
		}
		
		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0] * 100, 1).'%',
				'state_value' => $values[1],
				'state_annotation' => round($values[1] * 100, 1).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => 'Percentage of Children Receiving Free and Reduced Lunches ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
	}

	public function disabled() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Percent of Population Disabled',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value / 100;
				$row['annotation'] = round($value, 2).'%';
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Percent of Population Disabled ('.$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
		$this->prepDataAxis('percent', 0, 'v');
	}

	public function disabled_ages() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("PieChart");		
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'category', 
	        	'type' => 'string'
			),
			'value' => array(
	        	'label' => 'Disabled', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}

		// Add slices
		foreach ($this->data_categories as $label => $category_id) {
			$this->chart->addRow(array(
				'category' => $label,
				'value' => $this->values[$label] / 100
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->pie_colors, 0, 5),
			'legend' => array(
				'position' => 'right'
			),
			'title' => 'Disabled Age Breakdown, '.$this->locations[0][2].', Indiana ('.$year.')'
		));
	}

	public function share_of_establishments() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$short_label = '';
			foreach (array('Logistics', 'Manufacturing') as $abbrev_category) {
				if (stripos($label, $abbrev_category)) {
					$short_label = $abbrev_category;
				}
			} 
			$this->chart->addRow(array(
				'category' => $short_label, 
				'county_value' => $values[0] / 100,
				'county_annotation' => round($values[0], 2).'%',
				'state_value' => $values[1] / 100,
				'state_annotation' => round($values[1], 2).'%',
				'country_value' => $values[2] / 100,
				'country_annotation' => round($values[2], 2).'%'
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Percent Share of Total Establishments ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
	}

	public function employment_growth() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Timespan', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana (not seasonally adjusted)',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0] / 100,
				'county_annotation' => round($values[0], 1).'%',
				'state_value' => $values[1] / 100,
				'state_annotation' => round($values[1], 1).'%'
			));
		}
		
		// Finalize
		$this->prepDataAxis('percent', 0);
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => 'Employment Growth'
		));
	}

	public function employment_trend() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("LineChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Employment', 
	        	'type' => 'number',
	        	'format' => '#,###'
			)
	    ));
		
		// Gather data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		
		// Add line
		foreach ($this->values[0] as $date => $value) {
			$year = substr($date, 0, 4);
			$this->chart->addRow(array(
				'category' => $year, 
				'value' => $value
			));
		}
		
		// Finalize
		$this->prepDataAxis();
		$county_name = $this->locations[0][2];
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array(
				$this->colors[0]
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Employment in $county_name, Indiana (".$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
	}

	public function unemployment_rate() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("LineChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'state_value' => array(
	        	'label' => 'Indiana (not seasonally adjusted)', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				list($this->dates, $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
			}
		}
		
		// Add line
		foreach ($this->values[0] as $date => $value) {
			$year = substr($date, 0, 4);
			$county_value = $value / 100;
			$state_value = $this->values[1][$date] / 100;
			$this->chart->addRow(array(
				'category' => $year, 
				'county_value' => $county_value,
				'state_value' => $state_value
			));
		}
		
		// Finalize
		$this->prepDataAxis('percent');
		$county_name = $this->locations[0][2];
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => "Unemployment rate (".$year.')',
			'vAxis' => array(
				'minValue' => null
			)
		));
	}

	public function personal_and_household_income() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '$#,###'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number',
	        	'format' => '$#,###'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number',
	        	'format' => '$#,###'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => '$'.number_format($values[0]),
				'state_value' => $values[1],
				'state_annotation' => '$'.number_format($values[1]),
				'country_value' => $values[2],
				'country_annotation' => '$'.number_format($values[2])
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 3),
			'title' => 'Personal and Household Income ('.$year.')'
		));
		$this->prepDataAxis('currency');
	}

	public function income_inequality() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				list($this->dates, $this->values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $this->dates);
			}
		}
		
		// Add bars
		foreach ($this->values[0] as $date => $value) {
			$year = substr($date, 0, 4);
			$county_value = $value;
			$state_value = $this->values[1][$date];
			$this->chart->addRow(array(
				'category' => $year,
				'county_value' => $county_value,
				'county_annotation' => $county_value,
				'state_value' => $state_value,
				'state_annotation' => $state_value
			));
		}
		
		// Finalize
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => 'Income Inequality',
			'vAxis' => array(
				'minValue' => null
			)
		));
	}

	public function birth_rate() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Birth Rate',
	        	'type' => 'number'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = $value;
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Crude Birth Rate ('.$year.")\nLive Births per 1,000 Population",
			'hAxis' => array(
				'minValue' => 0
			)
		));
	}

	public function birth_rate_by_age() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => $values[0],
				'state_value' => $values[1],
				'state_annotation' => $values[1]
			));
		}
		
		// Finalize
		$years = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => "Birth Rate By Age Group ($years)"
		));
	}

	public function birth_measures() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Timespan', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '0.0%'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '0.0%'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0] / 100,
				'county_annotation' => round($values[0], 1).'%',
				'state_value' => $values[1] / 100,
				'state_annotation' => round($values[1], 1).'%'
			));
		}
		
		// Finalize
		$this->prepDataAxis('percent', 0, 'h');
		$years = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 180
			),
			'colors' => array_slice($this->colors, 0, 2),
			'title' => "Birth Measures ($years)"
		));
	}

	public function fertility_rates() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => number_format($values[0]),
				'state_value' => $values[1],
				'state_annotation' => number_format($values[1])
			));
		}
		
		// Finalize
		$years = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 180
			),
			'colors' => array_slice($this->colors, 0, 2),
			'title' => "Fertility Rates ($years)"
		));
	}

	public function deaths_by_sex() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");		
		$columns = array(
	        'category' => array(
	        	'label' => 'category', 
	        	'type' => 'string'
			)
	    );
		$category_names = array_keys($this->data_categories);
		foreach ($category_names as $k => $category_name) {
	        $columns["cat_$k"] = array(
	        	'label' => $category_name.' Deaths', 
	        	'type' => 'number',
	        	'format' => '0.00%'
			);
			$columns["annotation_$k"] = array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			);
		}
		$this->chart->columns($columns);
		
		// Gather data
		foreach ($this->locations as $loc_key => $location) {
			foreach ($this->data_categories as $category => $category_id) {
				$this->values[$category][$loc_key] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}

		// Add bars
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			$k = 0;
			foreach ($this->data_categories as $category => $category_id) {
				$value = $this->values[$category][$loc_key];
				$row["cat_$k"] = $value / 100;
				$row["annotation_$k"] = round($value, 1).'%';
				$k++;
			}
			$this->chart->addRow($row);
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'hAxis' => array(
				'viewWindow' => array(
					'max' => 1
				)
			),
			'isStacked' => true,
			'title' => 'Deaths By Sex ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'h');
	}

	public function death_rate() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Death Rate',
	        	'type' => 'number',
	        	'format' => '#.##'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = round($value);
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'hAxis' => array(
				'minValue' => 0
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Age-Adjusted Death Rate, All Causes ($year)"
		));
	}

	public function death_rate_by_cause() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => str_replace('\'', '\\\'', $label), 
				'county_value' => $values[0],
				'county_annotation' => number_format($values[0]),
				'state_value' => $values[1],
				'state_annotation' => number_format($values[1])
			));
		}
		
		// Finalize
		$years = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 270
			),
			'colors' => array_slice($this->colors, 0, 2),
			'height' => 700,
			'title' => "Age-Adjusted Death Rate by Cause ($years)",
			'vAxis' => array(
				'textStyle' => array(
					'fontSize' => 12
				)
			)
		));
	}

	public function infant_mortality() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Infant Death Rate',
	        	'type' => 'number',
	        	'format' => '#.##'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = round($value, 1);
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'hAxis' => array(
				'minValue' => 0
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Infant Death Rate Per 1,000 Live Births ($year)"
		));
		$this->prepDataAxis('number', 0, 'h');
	}

	public function life_expectancy() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Average Life Expectancy',
	        	'type' => 'number',
	        	'format' => '#.##'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = round($value, 1);
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'hAxis' => array(
				'minValue' => null
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Average Life Expectancy ($year)"
		));
		$this->prepDataAxis('number', 0, 'h');
	}

	public function years_of_potential_life_lost() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Years of Potential Life Lost',
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value;
				$row['annotation'] = number_format($value);
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'hAxis' => array(
				'minValue' => null
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Years of Potential Life Lost Before Age 75 ($year)"
		));
		$this->prepDataAxis('number', 0, 'h');
	}

	public function self_rated_poor_health() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Location',
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Percent',
	        	'type' => 'number',
	        	'format' => '0.00%'
			),
			'annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'colors' => array(
				'label' => 'Colors',
				'type' => 'string',
				'role' => 'style'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$i = 0;
		foreach ($this->locations as $loc_key => $location) {
			$row = array(
				'category' => $location[2]
			);
			foreach ($this->data_categories as $label => $category_id) {
				$value = $this->values[$loc_key][$label];
				$row['value'] = $value / 100;
				$row['annotation'] = round($value, 2).'%';
			}
			$row['colors'] = $this->colors[$i];
			$this->chart->addRow($row);
			$i++;
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'legend' => array(
				'position' => 'none'
			),
			'title' => 'Self-rated Health Status: Fair/Poor ('.$year.')'
		));
		$this->prepDataAxis('percent', 0, 'v');
	}

	public function unhealthy_days() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("ColumnChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Sex', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		$categories = array_keys($this->data_categories);
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			if (stripos($category, 'physically') !== false) {
				$label = 'Physically Unhealthy';
			} else {
				$label = 'Mentally Unhealthy';
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0], 1),
				'state_value' => $values[1],
				'state_annotation' => round($values[1], 1)
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'colors' => array_slice($this->colors, 0, 2),
			'title' => 'Average Number of Unhealthy Days Per Month (2004 - '.$year.')'
		));
	}

	public function cancer_death_and_incidence_rates() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'country_value' => array(
	        	'label' => 'United States',
	        	'type' => 'number',
	        	'format' => '#,###'
			),
			'country_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => number_format($values[0]),
				'state_value' => $values[1],
				'state_annotation' => number_format($values[1]),
				'country_value' => $values[1],
				'country_annotation' => number_format($values[1])
			));
		}
		
		// Finalize
		$years = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 270
			),
			'colors' => array_slice($this->colors, 0, 3),
			'height' => 500,
			'title' => "Cancer Incidence and Death Rates ($years)"
		));
	}

	public function lung_diseases() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("BarChart");
		$county_name = $this->locations[0][2];
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'county_value' => array(
	        	'label' => $county_name, 
	        	'type' => 'number',
	        	'format' => '#.##'
			),
			'county_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number',
	        	'format' => '#.##'
			),
			'state_annotation' => array(
				'label' => 'Annotation',
				'type' => 'string',
				'role' => 'annotation'
			)
	    ));
		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Add bars
		foreach ($this->data_categories as $label => $category_id) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$label];
			}
			$this->chart->addRow(array(
				'category' => $label, 
				'county_value' => $values[0],
				'county_annotation' => round($values[0], 2),
				'state_value' => $values[1],
				'state_annotation' => round($values[1], 2)
			));
		}
		
		// Finalize
		$years = $this->getYears();
		$this->applyOptions(array(
			'chartArea' => array(
				'left' => 150
			),
			'colors' => array_slice($this->colors, 0, 2),
			'title' => "Lung Disease Incidence Rates Per 1,000 Population ($years)"
		));
	}

	public function grants_awarded() {
		// Create chart
		$this->chart = new GoogleCharts();
		$this->applyDefaultOptions();
		$this->chart->type("LineChart");
		$this->chart->columns(array(
	        'category' => array(
	        	'label' => 'Category', 
	        	'type' => 'string'
			),
	        'value' => array(
	        	'label' => 'Grants Awarded', 
	        	'type' => 'number',
	        	'format' => '#,###'
			)
	    ));
		
		// Gather data
		$this->dates = range(1995, 2011);
		$this->values[0] = array(
			1995 => 2,
			1996 => 1,
			1997 => 2,
			1998 => 1,
			1999 => 2,
			2000 => 1,
			2001 => 2,
			2002 => 1,
			2003 => 4,
			2004 => 5,
			2005 => 2,
			2006 => 5,
			2007 => 10,
			2008 => 6,
			2009 => 4,
			2010 => 7,
			2011 => 10
		);
		
		// Add line
		foreach ($this->values[0] as $date => $value) {
			$this->chart->addRow(array(
				'category' => $date, 
				'value' => $value
			));
		}
		
		// Finalize
		$this->prepDataAxis();
		$this->applyOptions(array(
			'colors' => array(
				$this->colors[0]
			),
			'legend' => array(
				'position' => 'none'
			),
			'title' => "Brownfield Grants Awarded in Indiana (1995-2011)",
			'vAxis' => array(
				'minValue' => null
			)
		));
	}
}