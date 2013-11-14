<?php
class TablesController extends AppController {
	public $uses = array(
		'Datum', 
		'Table'
	);
	
	public $values = array();		// Single array (for one data set) or multidimensional (for multiple)
	public $dates = array();		// Array of years or YYYYMMDD survey_date codes
	public $year = null;			// Used instead of $this->dates if only a single year is used
	public $category_id = null;
	public $locations = array();	// Each member is an array of [loc_type_id, loc_id]
	
	public $title = '';
	public $columns = array();
	public $table = array();
	public $footnote = '';
	public $options = array();
	
	public function beforeRender() {
		$this->set(array(
			'title' => $this->title, 
			'columns' => $this->columns, 
			'table' => $this->table, 
			'footnote' => $this->footnote,
			'options' => $this->options
		));
		if (isset($_GET['debug'])) {
			echo '<pre>'.print_r($this, true).'</pre>';	
		}
	}
	
	// If no parameters are passed, assumes the first location specified in $this->locations
	private function __getLocName($loc_type_id = null, $loc_id = null, $full_name = false) {
		if ($loc_type_id == null) {
			$loc_type_id = $this->locations[0][0];
			$loc_id = $this->locations[0][1];	
		}
		$location_type_tables = array(
			1 => 'cities', 2 => 'counties', 3 => 'states', 4 => 'countries', 
			5 => 'tax_districts', 6 => 'school_corps', 7 => 'townships'
		);
		if (isset($location_type_tables[$loc_type_id])) {
			$table = $location_type_tables[$loc_type_id];
		} else {
			return;
		}
		$result = $this->Datum->query("SELECT name FROM $table WHERE id = $loc_id LIMIT 1");
		$loc_name = $result[0][$table]['name'];
		if ($full_name && $loc_type_id == 2) {
			$loc_name .= ' County';
		}
		return $result[0][$table]['name'];
	}
	
	// If $this->dates is an array of arrays of dates, merges it into a single array
	// To do: This could be more efficient. Maybe. 
	private function __mergeDates() {
		if (! is_array(reset($this->dates))) {
			return;
		}
		$new_array = array();
		foreach ($this->dates as $date_set) {
			$new_array = array_merge($new_array, $date_set);	
		}
		$new_array = array_unique($new_array, SORT_NUMERIC);
		$this->dates = $new_array;
	}
	
	private function __reverseTimeline() {
		$this->dates = array_reverse($this->dates);
		foreach ($this->values as $setkey => $set) {
			$this->values[$setkey] = array_reverse($set, 1);
		}
	}
	
	private function __formatCell($value, $mode = 'number', $precision = 0) {
		if ($value == '') {
			return $value;
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
	
	private function __getFormattedTableArray($row_labels = null, $values = null, $first_col_format = 'year', $data_format = 'number', $data_precision = 0) {
		if (! $row_labels) {
			$row_labels = $this->dates;
		}
		if (! $values) {
			$values = $this->values;
		}
		$table = array();
		foreach ($row_labels as $row_label) {
			$row_header = $this->__formatCell($row_label, $first_col_format);
			foreach ($values as $column => $set) {
				$cell_contents = $this->__formatCell($set[$row_label], $data_format, $data_precision);
				$table[$row_header][$column] = $cell_contents;
			}
		}
		return $table;
	}
	
	/* Populates the third element of each array in $this->locations with the appropriate location name
	 * and returns a simple array of the same location names. */
	private function __getLocationLabels() {
		$labels = array();
		foreach ($this->locations as $lkey => $loc) {
			if (isset($this->locations[$lkey][2])) {
				$label = $this->locations[$lkey][2];
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
	
	private function __recursiveImplode($glue, $pieces) {
		foreach ($pieces as $r_pieces) {
			if (is_array($r_pieces)) {
				$retVal[] = $this->__recursiveImplode($glue, $r_pieces);
			} else {
				$retVal[] = $r_pieces;
			}
		}
		return implode($glue, $retVal);	
	}
	
	/* Use this instead of $this->render('table') if data may not be available,
	 * depending on the county. The 'data unavailable' message will be displayed instead of 
	 * a table if $values (single or multidimensional array) doesn't contain any numerical values.
	 * (zero still counts as a numerical value, but unavailable data will be represented by FALSE) */
	private function __renderIfDataIsAvailable($values, $view = 'table') {
		if ($this->__recursiveImplode('', $values) != '') {
			$this->render($view);
		} else {
			$this->render('unavailable');
		}
	}
	
	// Returns an array of [corporation name => corporation id] pairs for each school corporation in a given county
	private function __getCountysSchoolCorps($county_id) {
		if (! is_numeric($county_id)) {
			return false;
		}
		$result = $this->Datum->query("
			SELECT id, name 
			FROM school_corps 
			WHERE county_id = $county_id 
			ORDER BY name ASC
		");
		$corp_ids = array();
		foreach ($result as $key => $row) {
			$corp_ids[$row['school_corps']['name']] = $row['school_corps']['id'];
		}
		return $corp_ids;
	}
	
	/* Lists the simple names (which should be found as array keys above) 
	 * of charts that are actually only tables. */
	public function getExclusiveTables() {
		return $this->Table->getExclusiveTables();
	}
	
	public function tableExists($name) {
		return method_exists($this, $name);
	}
	
	public function unavailable() {
		
	}
	
	// Variation: Array of dates instead of a year
	public function population($county = 1) {
		// General parameters
		$this->category_id = array('Population' => 1);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Year'), $location_names);
		
		// Gather, check, and manipulate data
		$category_id = array_pop($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates[$loc_key], $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
		}
		$this->__mergeDates();
		$this->__reverseTimeline();
		
		// Finalize
		$this->title = 'Population';
		$this->table = $this->__getFormattedTableArray($this->dates, $this->values, 'year', 'number', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Growth between years calculated
	public function population_growth($county = 1) {
		// General parameters
		$this->category_id = array('Population' => 1);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->title = 'Population Growth';
		$this->columns = array_merge(array('Period'), $location_names);
		$this->options[] = 'colorcode';
		
		// Gather data
		$dates = array(1969, 1974, 1979, 1984, 1989, 1994, 1999, 2004, 2008);
		$population_values = array();
		$category_id = array_pop($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates, $population_values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $dates);
		}
		
		// Get growth values
		$date_pairs = array(
			array(2004, 2008), array(1999, 2008), array(1994, 2008), array(1989, 2008), 
			array(1984, 2008), array(1979, 2008), array(1974, 2008), array(1969, 2008)
		);
		$row_labels = array();
		foreach ($date_pairs as $date_pair) {
			$row_label = substr($date_pair[0], 0,4)."-".substr($date_pair[1], 0,4);
			$row_labels[] = $row_label;
			$earlier = $date_pair[0].'0000';
			$later = $date_pair[1].'0000';
			foreach ($this->locations as $loc_key => $location) {
				$later_population = $population_values[$loc_key][$later];
				$earlier_population = $population_values[$loc_key][$earlier]; 
				$this->values[$loc_key][$row_label] = (($later_population - $earlier_population) / $earlier_population) * 100;
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray($row_labels, null, 'string', 'percent', 2);
		$this->render('table');	
	}
	
	public function density($county = 1) {
		// General parameters
		$this->category_id = array(
			'Population density' => 5721, 
			'Housing units density' => 5722
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = 'Density Per Square Mile of Land Area';
		$this->year = 2000;
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function population_age_breakdown($county = 1) {
		// General parameters
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
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Age Range'), $location_names);
		$this->year = 2000;
		$this->title = "Population By Age ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 1);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function female_age_breakdown($county = 1) {
		// General parameters
		$this->category_id = array(
			'Young Women (< 15)' => 5738,
			'Women child bearing age (15 to 44)' => 5739,
			'Women (> 44)' => 5740
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Age Range'), $location_names);
		$this->year = 2000;
		$this->title = "Female Age Breakdown For {$this->locations[0][2]} ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function population_by_sex($county = 1) {
		// General parameters
		$this->category_id = array(
	 		'Male' => 361,
	 		'Female' => 362
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->year = 2000;
		$this->title = "Population By Sex ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function dependency_ratios($county = 1) {
		// General parameters
		$this->category_id = array(
		 	'Total (< 15 and 65+)' => 5741,	
	 		'Child (< age 15)' => 5742,
			'Elderly (65+)' => 5743
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Age Group'), $location_names);
		$this->year = 2000;
		$this->title = "Dependency Ratio Per 100 People ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function educational_attainment($county = 1) {
		// General parameters
		$this->category_id = array(
			'Less than 9th grade, percent' => 5748,
			'9th to 12th grade, no diploma, percent' => 468,
			'High school graduate or equivalent, percent' => 469,
			'Some college, no degree, percent' => 5750,
			'Associate degree, percent' => 472,
			'Bachelor\'s degree, percent' => 473,
			'Graduate or professional degree, percent' => 5752
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Education Level'), $location_names);
		$this->year = 2000;
		$this->title = "Educational Attainment, Population 25 Years and Over ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Locations are row headers, single category is a column header
	public function graduation_rate($county = 1) {
		// General parameters
		$this->category_id = array('High School Graduation Rate' => 5396);
		$this->columns = array('School Corporation', 'High School Graduation Rate');
		$county_name = $this->__getLocName(2, $county).' County';
		$this->year = 2009;
		$this->title = "High School Graduation Rate: $county_name ($this->year)";
		
		// Nab relevant school corporations, add on Indiana average
		$school_corps = $this->__getCountysSchoolCorps($county);
		foreach ($school_corps as $corp_name => $corp_id) {
			$this->locations[] = array(6, $corp_id, $corp_name);
			$labels[] = $corp_name;
		}
		$this->locations[] = array(3, $this->requestAction('/data/getStateFromCounty/'.$county), '(Indiana Average)');
		$location_names = array();
		foreach ($this->locations as $loc_key => $location) {
			$location_names[] = $location[2];
		}
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$label][$location[2]] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray($location_names, $this->values, 'string', 'percent', 1);
		$this->render('table');
	}
	
	public function household_size($county = 1) {
		// General parameters
		$this->category_id = array('Average household size' => 348);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->year = 2000;
		$this->title = "Average Household Size ($this->year)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function households_with_minors($county = 1) {
		// General parameters
		$this->category_id = array('Households with one or more people under 18 years' => 438);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->year = 2000;
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Calculation being done to generate values
	public function household_types_with_minors($county = 1) {
		// General parameters
		$this->category_id = array(
	 	 	'Married-couple family' => 5762,
			'Male householder, no wife present' => 5764,
			'Female householder, no husband present' => 5766,
	 	 	'Nonfamily households' => 5768,
			'Households with one or more people under 18 years' => 346 //Not part of chart, used for calculation
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Household Type'), $location_names);
		$this->year = 2000;
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		
		// Gather data
		$total_households_cat_id = array_pop($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $this->year);
			foreach ($this->category_id as $category => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / $total_households;
				$this->values[$loc_key][$category] = $value * 100;
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function households_with_over_65($county = 1) {
		// General parameters
		$this->category_id = array('Percent of households with one or more people 65 years and over' => 439);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->year = 2000;
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function poverty($county = 1) {
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Poverty Percent: All Ages' => 5686,
 	 		'Poverty Percent: Under 18' => 5688
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Percentage of Population in Poverty ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function lunches($county = 1) {
		// General parameters
		$this->year = 2010;
		$this->category_id = array(
		 	'Free lunches' => 5780,
		 	'Reduced lunches' => 5781,
		 	'Free + reduced' => 5782,
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Free and Reduced Lunches ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function disabled($county = 1) {
		// General parameters
		$this->year = 2000;
		$this->category_id = array('Percent of population disabled' => 5792);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Percent of Population Disabled ($this->year)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function disabled_ages($county = 1) {
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
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Age Range'), $location_names);
		$this->title = "Disabled Age Breakdown For $location_names[0] ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function share_of_establishments($county = 1) {
		// General parameters
		$this->year = 2007;
		$this->category_id = array(
			'Logistics (transportation, warehousing, wholsale, retail trade)' => 5813, 
			'Manufacturing' => 5814
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Establishment Type'), $location_names);
		$this->title = "Percent Share of Total Establishments ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function employment_growth($county = 1) {
		// General parameters
		$this->year = 2009;
		$this->category_id = array(
			'2005-2009' => 5817,
			'2000-2009' => 5818,
			'1995-2009' => 5819,
			'1990-2009' => 5820
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county), 'Indiana*'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Period'), $location_names);
		$this->title = "Employment Growth";
		$this->footnote = '* Not seasonally adjusted';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Array of dates instead of a year
	public function employment_trend($county = 1) {
		// General parameters
		$this->category_id = array('Non-farm Employment' => 5815);
		$this->locations = array(array(2, $county));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Year'), $location_names);
		$this->title = "Employment";
		
		// Gather, check, and manipulate data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->category_id), $this->locations[0][0], $this->locations[0][1]);
		$this->__reverseTimeline();
		
		// Finalize
		$this->table = $this->__getFormattedTableArray($this->dates, $this->values, 'year', 'number', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Array of dates instead of a year
	public function unemployment_rate($county = 1) {
		// General parameters
		$this->category_id = array('Unemployment Rate' => 569);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county), 'Indiana*'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Year'), $location_names);
		$this->footnote = '* Not seasonally adjusted';
		$this->title = "Unemployment Rate";
		
		// Gather, check, and manipulate data
		$category_id = array_pop($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates[$loc_key], $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
		}
		$this->__mergeDates();
		$this->__reverseTimeline();
		
		// Finalize
		$this->table = $this->__getFormattedTableArray($this->dates, $this->values, 'year', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function personal_and_household_income($county = 1) {
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Per Capita Personal Income' => 47,
 	 		'Median Household Income' => 5689
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Personal and Household Income ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'currency', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	// Variation: Array of dates instead of a year
	public function income_inequality($county = 1) {
		// General parameters
		$this->category_id = array('Income inequality' => 5668);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Year'), $location_names);
		$this->title = "Income Inequality";
		$this->dates = array(19700000, 19800000, 19900000, 20000000);
		
		// Gather, check, and manipulate data
		$category_id = array_pop($this->category_id);		
		foreach ($this->locations as $loc_key => $location) {
			list($discard_dates, $this->values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $this->dates);
		}
		$this->__reverseTimeline();
		
		// Finalize
		$this->table = $this->__getFormattedTableArray($this->dates, $this->values, 'year', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function birth_rate($county = 1) {
		// General parameters
		$this->year = 2006;
		$this->category_id = array('Birth Rate = Live Births per 1,000 population' => 5827);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Crude Birth Rate* ($this->year)";
		$this->footnote = '* Live births per 1,000 population.';
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function birth_rate_by_age($county = 1) {
		// General parameters
		$this->year = 2006;
		$this->category_id = array(
			'10 to 49' => 5840,
			'Under 18' => 5841,
			'18 to 39' => 5842,
			'40 to 49' => 5843
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Age Group'), $location_names);
		$this->title = "Birth Rate By Age Group ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function birth_measures($county = 1) {
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
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Birth Measures ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function fertility_rates($county = 1) {
		// General parameters
		$this->year = 2006;
		$this->category_id = array(
			'General' => 5849,
			'Total' => 5850
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Fertility Rates ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function deaths_by_sex($county = 1) {
		// General parameters
		$this->year = 2007;
		$this->category_id = array(
			'Male' => 5856, // percent of deaths
			'Female' => 5857 // percent of deaths
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Deaths By Sex ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function death_rate($county = 1) {
		// General parameters
		$this->year = 2007;
		$this->category_id = array('All causes: Death rate, age-adjusted' => 5852);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Age-Adjusted Death Rate* ($this->year)";
		$this->footnote = '* (All causes)';
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function infant_mortality($county = 1) {
		// General parameters
		$this->year = 2007;
		$this->category_id = array('Infant death rate per 1000 live births' => 5908);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Infant Death Rate* ($this->year)";
		$this->footnote = '* (Per 1,000 live births)';
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function life_expectancy($county = 1) {
		// General parameters
		$this->year = 2001;
		$year_label = '1997-2001';
		$this->category_id = array('Average life expectancy (1997-2001)' => 5909);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Average Life Expectancy ($year_label)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 1);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function years_of_potential_life_lost($county = 1) {
		// General parameters
		$this->year = 2006;
		$year_label = '2004-2006';
		$this->category_id = array('Years of potential life lost before age 75 (2004-2006)' => 5910);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Years of Potential Life Lost* ($year_label)";
		$this->footnote = '* Before age 75';
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 0);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function self_rated_poor_health($county = 1) {
		// General parameters
		$this->year = 2008;
		$year_label = '2002-2008';
		$this->category_id = array('Self-Rated Health Status: Fair/Poor (\'02-\'08)' => 5911); //percent
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Self-rated Health Status: Fair/Poor ($year_label)";
		$this->options[] = 'hide_first_col';
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'percent', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function unhealthy_days($county = 1) {
		// General parameters
		$this->year = 2008;
		$year_label = '2002-2008';
		$this->category_id = array(
			'Physically unhealthy' => 5913,
			'Mentally unhealthy' => 5914
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Average Number of Unhealthy\nDays Per Month ($year_label)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function death_rate_by_cause($county = 1) {
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
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Age-Adjusted Death Rate by Cause ($this->year)";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 2);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function cancer_death_and_incidence_rates($county = 1) {
		// General parameters
		$this->year = 2006;
		$period_label = '2002-2006';
		$this->category_id = array(
			'Incidence Rate: All Cancers ^' => 5918,
			'Death Rate: All Cancers *' => 5920, 	 
			'Incidence Rate: Lung and Bronchus Cancer ^' => 5922,
			'Death Rate: Lung and Bronchus Cancer **' => 5924
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(''), $location_names);
		$this->title = "Cancer Incidence and Death Rates ($period_label)";
		$this->footnote = "Healthy people target (all cancers, 2010) = 158.6\nHealthy people target (lung and bronchus cancer, 2010) = 43.3\n^ Rates (cases per 100,000 population per year) are age-adjusted to the 2000 US standard population";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 1);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function lung_diseases($county = 1) {
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
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array('Lung Disease'), $location_names);
		$this->title = "Lung Disease Incidence Rates* ($this->year)";
		$this->footnote = "* Per 1,000 Population";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->table = $this->__getFormattedTableArray(array_keys($this->category_id), $this->values, 'string', 'number', 1);
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
	
	public function federal_spending($county = 1) {
		// General parameters
		$this->year = 2008;
		$this->category_id = array(
			'Total Federal Goverment Expenditure' => 5822,
 	 		'% WRT state' => 5823,
 	 		'County Rank out of 92*' => 5824
		);
		$this->locations = array(array(2, $county), array(3, $this->requestAction('/data/getStateFromCounty/'.$county)), array(4, 1, 'United States'));
		$location_names = $this->__getLocationLabels();
		$this->columns = array_merge(array(' '), $location_names);
		$this->title = "Federal Spending ($this->year)";
		$this->footnote = "Dollar amounts are in thousands of dollars.\n* A rank of 1 corresponds to the highest-spending county in this state.";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize ($this->table is being created manually because of each row having different formatting)
		$row_titles = array_keys($this->category_id);
		foreach ($this->locations as $loc_key => $location) {
			$this->table[$row_titles[0]][$loc_key] = $this->__formatCell($this->values[$loc_key][$row_titles[0]], 'currency');
			$this->table[$row_titles[1]][$loc_key] = $this->__formatCell($this->values[$loc_key][$row_titles[1]], 'percent', 2);
			$this->table[$row_titles[2]][$loc_key] = $this->values[$loc_key][$row_titles[2]];
		}
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
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
		$location_names = $this->__getLocationLabels();
		$this->columns = array(
			' ', 
			"$location_names[0]\n#",
			"$location_names[0]\nRank out of 92*",
			"$location_names[0]\n% of state",
			"$location_names[1]\n#",
		);
		$this->title = "Public Assistance ($this->year)";
		$this->footnote = "* A rank of 1 corresponds to the county that has received the least public assistance.";
		
		// Gather data
		foreach ($this->category_id as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize ($this->table is being created manually because of each column having different formatting)
		$row_titles = array(
			'Women, Infants, and Children (WIC) Participants',
			'Monthly Average of Families Receiving TANF',
			'Monthly Average of Persons Issued Food Stamps (FY)'
		);
		foreach ($row_titles as $row_title) {
			$county_value = $this->values[0][$row_title];
			$state_value = $this->values[1][$row_title];
			$percent = ($county_value / $state_value) * 100;
			$this->table[$row_title] = array(
				$this->__formatCell($county_value),
				$this->values[0]["$row_title Rank"],
				$this->__formatCell($percent, 'percent', 2),
				$this->__formatCell($state_value)
			);
		}
		$this->__renderIfDataIsAvailable($this->values[0], 'table');
	}
}