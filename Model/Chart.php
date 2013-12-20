<?php
App::uses('GoogleCharts', 'GoogleCharts.Lib');
App::uses('Datum', 'Model');
App::uses('Location', 'Model');

class Chart extends AppModel {
	public $useTable = false;
	
	public $category_id = null;
	public $locations = array();	// Each member is an array of [loc_type_id, loc_id]
	
	public $defaultOptions = array(
		'width' => 510,
		'height' => 300,
		'legend' => array(
			'position' => 'bottom',
			'alignment' => 'center'
		),
		'titleTextStyle' => array(
			'color' => 'black',
			'fontSize' => 16
		),
		'vAxis' => array(
			'textStyle' => array(
				'fontSize' => 12
			)
		)
	);
	
	// Supplied by getTable()'s parameters
	public $segment = null;
	public $data = array();
	public $segmentParams = array();
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
	 * Returns GoogleCharts object with default options
	 *
	 * @return GoogleCharts
	 */
	private function getChartObject() {
		$chart = new GoogleCharts();
		$chart->options(array(
			'legend' => 'bottom'
		));
		return $chart;	
	}
	
	public function getChart($segment, $data, $segment_params, $structure) {
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
	
	public function population($county = 1) {
		// General parameters
		$this->category_id = array('Population' => 1);
		$this->locations = array(array(2, $county));
		$county_name = $this->getLocName();
		
		// Create chart object
		$chart = $this->getChartObject();
		$chart->type('LineChart');
		$chart->columns(array(
			'year' => array(
				'type' => 'number',
				'label' => 'Year'
			),
			'values' => array(
				'type' => 'number',
				'label' => 'Population'
			)
		));
		
		// Get data
		//TODO: Add dates, values to class attributes
		$Datum = new Datum();
		list($this->dates, $this->values) = $Datum->getSeries(
			array_pop($this->category_id), 
			$this->locations[0][0], 
			$this->locations[0][1]
		);

		// Add data to chart
		foreach ($this->values as $year => $value) {
			$row = array(
				'year' => substr($year, 0, 4),
				'values' => $value
			);
			$chart->addRow($row);
		}
		
		$chart->options(array(
			'seriesType' => 'line',
			'hAxis' => array(
				'format' => '####',
				'gridlines' => array('color' => 'transparent'),
				'slantedText' => false
			),
			'title' => "Population of $county_name County, Indiana (".$this->getStartYear().' - '.$this->getEndYear().')'
		));
		
		return $chart;
	}
}