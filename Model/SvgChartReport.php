<?php
App::uses('GoogleCharts', 'GoogleCharts.Lib');
App::uses('Datum', 'Model');
App::uses('Location', 'Model');
App::Import ('model', 'Report');

class SvgChartReport extends Report {
	public $useTable = false;
	
	public $category_id = null;
	public $locations = array();	// Each member is an array of [loc_type_id, loc_id]
	
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
	
	// This is generally the Y axis ($axis == 'v'), but is X ($axis == 'h') for horizontal bar charts 
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
	        	'type' => 'number'
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
			'title' => "Population of $county_name, Indiana (".$year.')',
			'legend' => array(
				'position' => 'none'
			),
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
	        	'type' => 'number'
			),
			'state_value' => array(
	        	'label' => 'Indiana',
	        	'type' => 'number'
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
		
		// Add line
		foreach ($date_pairs as $date_pair) {
			$label = substr($date_pair[0], 0,4)."-".substr($date_pair[1], 0,4);
			$earlier = $date_pair[0].'0000';
			$later = $date_pair[1].'0000';
			$county_value = ($county_values[$later] - $county_values[$earlier]) / $county_values[$earlier];
			$state_value = ($state_values[$later] - $state_values[$earlier]) / $state_values[$earlier];
			$this->chart->addRow(array(
				'category' => implode('-', $date_pair), 
				'county_value' => $county_value,
				'state_value' => $state_value
			)); 
		}
		
		// Finalize
		$this->prepDataAxis('percent', 1);
		$county_name = $this->locations[0][2];
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => "Population Growth (".$year.')',
			'hAxis' => array(
				'textStyle' => array(
					'fontSize' => 10
				)
			)
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
	        	'type' => 'number'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number'
			),
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
				'state_value' => $values[1],
				'country_value' => $values[2]
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
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
	        	'type' => 'number'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number'
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
		foreach ($categories as $category) {
			$values = array();
			foreach ($this->locations as $key => $set) {
				$values[] = $this->values[$key][$category];
			}
			$this->chart->addRow(array(
				'category' => $category, 
				'county_value' => $values[0],
				'state_value' => $values[1],
				'country_value' => $values[2]
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => 'Population By Age ('.$year.')',
			'hAxis' => array(
				'minValue' => 0
			),
			'height' => 500
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
	        	'type' => 'number'
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
				$row["cat_$k"] = $this->values[$loc_key][$category_name];
			}
			$this->chart->addRow($row);
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => 'Female Age Breakdown ('.$year.')',
			'isStacked' => true
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
	        	'type' => 'number'
			),
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number'
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
				'state_value' => $values[1],
				'country_value' => $values[2]
			));
		}
		
		// Finalize
		$min = min($all_values);
		$min = floor($min * 20) / 20;
		$max = max($all_values); 
		$max = ceil($max * 20) / 20;
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => 'Population By Sex ('.$year.')',
			'hAxis' => array(
				'minValue' => $min,
				'maxValue' => $max,
				'viewWindowMode' => 'explicit',
				'viewWindow' => array(
					'min' => $min,
					'max' => $max
				)
			),
			'height' => 500
		));
		$this->prepDataAxis('percent', 0, 'h');
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
			'state_value' => array(
	        	'label' => 'Indiana', 
	        	'type' => 'number'
			),
			'country_value' => array(
	        	'label' => 'United States', 
	        	'type' => 'number'
			),
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
				'state_value' => $values[1],
				'country_value' => $values[2]
			));
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => 'Dependency Ratio Per 100 People ('.$year.')'
		));
	}

	public function educational_attainment() {
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
			$category_name = str_replace('\'', '\\\'', $category_name);
			$category_name = str_replace(', percent', '', $category_name);
	        $columns["cat_$k"] = array(
	        	'label' => $category_name, 
	        	'type' => 'number'
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
				$row["cat_$k"] = $this->values[$loc_key][$category_name];
			}
			$this->chart->addRow($row);
		}
		
		// Finalize
		$year = $this->getYears();
		$this->applyOptions(array(
			'title' => 'Educational Attainment, Population 25 Years and Over ('.$year.')',
			'isStacked' => true,
			'legend' => array(
				'position' => 'right'
			),
			'chartArea' => array(
				'left' => 150,
				'width' => 300
			)
		));
		$this->prepDataAxis('percent', 0, 'h');
	}
}