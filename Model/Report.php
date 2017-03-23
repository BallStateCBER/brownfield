<?php
/* Report: A display of information associated with a topic, such as a chart, table, or CSV file.
 * 		Each report method sets its data categories, locations, and optional year / dates, which are automatically
 * 		imported into the appropriate Report subclass (Chart, Table, Csv, etc.) before type-specific processing 
 * 		takes place and $output is returned.
 * 		$type refers to the type of report (chart, table, csv, etc.) and (usually) is associated with a Report subclass
 * 		$output refers to the object or array used to display the report
 * 			ChartReports output $this->Chart->gchart (GoogleChart object)
 * 			TableReports output 
 * 			CsvReports output
 * 		   
 * Topic: What a report (or a page on the website) is about (such as 'educational attainment'). May contain multiple data categories.  
 * 
 */
class Report extends AppModel {
    public $name = 'Report';
    public $displayField = 'topic';
    public $actsAs = array('Containable');
    public $useTable = 'reports';
	 
    public $data_categories; 	// array of name => id pairs
	public $locations; 			// array of [loc_type_id, loc_id, loc_name] arrays
	public $location_names; 	// array of location names
	public $year;
	public $dates; 				// array of YYYY or YYYYMMDD integers
	public $values; 			// $values[location][topic]
	public $error = 0;
	public $serialized = null;
	
	// Set by ReportsController::__initializeSubclass()
	public $topic;
	public $state_id;
	public $county_id;
	
	// e.g. Life expectancy data are each for 1997-2001, but stored with survey_date 2001
	// so a separate explicitly-written label is needed to display the actual span of surveyed
	// dates in the report's title. 
	public $years_label;  
	
	public function getCached($params) {
		extract($params);
		$results = Cache::read("$type.$topic.$county_id");
		if ($results) {
			return $results['Report']; //array('data' => {serialized data}, 'error' => {error code})
		} else {
			return false;
		}
	}
	
	/* This has to be called as $Report->cache, rather than $ReportSubtype->cache 
	 * or else CakePHP will try to look up the wrong table. */
	public function cache($report_obj, $type, $topic, $county_id) {
		$error = $report_obj->error;
		if ($error) {
			$data = null;
		} else {
			/* These are the attributes of the report object that are
			 * packed into an array, serialized, and cached in 
			 * the reports table. */
			$cached_report_attributes = array(
				'chart' => array(
					'gchart',
				),
				'table' => array(
					'title', 'columns', 'table', 'footnote', 'options'
				),
				'source' => array(
					'source'
				),
				'csv' => array(
					'title', 'columns', 'table', 'footnote', 'options'
				),
				'excel2007' => array(
					'mockup', 'output_type', 'values', 'objPHPExcel'
				)
			);
			$data = array();
			foreach ($cached_report_attributes[$type] as $attribute) {
				$data[$attribute] = $report_obj->$attribute;
			}
			$data = serialize($data);
		}
		
		Cache::write("$type.$topic.$county_id", array('Report' => compact('type', 'topic', 'county_id', 'data', 'error')));
		return true;
	}
	
	// If $this->dates is an array of arrays of dates, merges it into a single array
	// To do: This could be more efficient. Maybe. 
	public function mergeDates() {
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
	
	/* Returns an array of location names, which should be the third elements of each array in $locations
	 * (assumes that these names have already been set) */
	public function getLocationNames() {
		$labels = array();
		foreach ($this->locations as $lkey => $loc) {
			$labels[] = $loc[2];
		}
		return $labels;	
	}
		
	public function convertPercentValues() {
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
	
	// Takes a one-dimensional array and applies a formatting to all of its members
	public function formatValues($values, $mode = 'number', $precision = null) {
		$new_values = array();
		foreach ($values as $value) {
			$new_values[] = $this->formatValue($value, $mode, $precision);	
		}
		return $new_values;
	}
	
	// Takes a single value and formats it
	public function formatValue($value, $mode = 'number', $precision = null) {
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
				return number_format($value, $precision).'%';
			case 'currency':
				return '$'.($value < 1 ? '0.' : '').number_format($value, $precision);
			case 'string':
			default:
				return $value;
		}
	}
	
	public function reverseTimeline() {
		$this->dates = array_reverse($this->dates);
		foreach ($this->values as $setkey => $set) {
			$this->values[$setkey] = array_reverse($set, 1);
		}
	}
	
	public function formatCell($value, $mode = 'number', $precision = 0) {
		if ($value == '') {
			return $value;
		}
		switch ($mode) {
			case 'year':
				return substr($value, 0, 4);
			case 'number':
				return ($value < 1 ? '0.' : '').number_format($value, $precision);
			case 'percent':
				return number_format($value, $precision).'%';
			case 'currency':
				return '$'.($value < 1 ? '0.' : '').number_format($value, $precision);
			case 'string':
			default:
				return $value;
		}
	}
	
	public function recursiveImplode($glue, $pieces) {
		foreach ($pieces as $r_pieces) {
			if (is_array($r_pieces)) {
				$retVal[] = $this->recursiveImplode($glue, $r_pieces);
			} else {
				$retVal[] = $r_pieces;
			}
		}
		return implode($glue, $retVal);	
	}
	
	// Takes $this->year and/or $this->dates and pads the values on the right with zeroes to fit the YYYYMMDD format
	public function expandDateCodes() {
		$this->year = str_pad($this->year, 8, '0');
		foreach ($this->dates as $key => $date) {
			$this->dates[$key] = str_pad($date, 8 , '0');
		}
	}
	
	/* This method should be updated whenever topics are added or when their method 
	 * names or human-readable titles are changed. This is used by the navigation sidebar and 
	 * to test whether or not user-supplied chart names (like those in URLs) are valid.
	 *  
	 * REMEMBER to update /views/elements/sidebar_county -> $list_heights when this list is changed. */
	public function getTopicList($categorized = true) {
		$topics = array(
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
		if ($categorized) {
			return $topics;	
		}
		
		// Otherwise, return a flattened array
		$flattened_array = array();
		foreach ($topics as $tab => $tabs_topics) {
			$flattened_array = array_merge($flattened_array, $tabs_topics);
		}
		return $flattened_array;
	}
	
	public function isTopic($topic) {
		$all_topics = array_keys($this->getTopicList(false));
		return in_array($topic, $all_topics);
	}
	
	public function getFirstTopic() {
		return reset(array_keys($this->getTopicList(false)));
	}
	
	public function getTopicFullName($topic) {
		$all_topics = $this->getTopicList(false);
		return $all_topics[$topic];
	}

	public function getCountyRank($categoryId, $countyId, $year, $reverse = false) {
        $allCountyValues = $this->Datum->find('all', [
            'fields' => ['loc_id', 'value'],
            'conditions' => [
                'category_id' => $categoryId,
                'survey_date' => $year . '0000',
                'loc_type_id' => 2
            ],
            'order' => [
                $reverse ? 'value DESC' : 'value ASC'
            ]
        ]);
        $rank = 0;
        $skipped = 0;
        $previousValue = null;
        foreach ($allCountyValues as $result) {
            if ($result['Datum']['value'] == $previousValue) {
                $skipped++;
            } else {
                $rank++;
                $rank += $skipped;
                $skipped = 0;
            }
            if ($result['Datum']['loc_id'] == $countyId) {
                return $rank;
            }
            $previousValue = $result['Datum']['value'];
        }

        return null;
    }
}