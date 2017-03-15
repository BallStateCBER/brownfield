<?php
App::Import ('model', 'Report');
class TableReport extends Report {
	public $useTable = false;
	
	public $title = '';
	public $columns = array();
	public $table = array();
	public $footnote = '';
	
	/* This array can contain the following strings:
	 * 		hide_first_col: Hides the first column (row titles)
	 *		colorcode:		Colors each value red, black, or green, depending on whether it's <, =, or > zero
	 */  	
	public $options = array();
	
	public function getFormattedTableArray($row_labels = null, $values = null, $first_col_format = 'year', $data_format = 'number', $data_precision = 0) {
		if (! $row_labels) $row_labels = $this->dates;
		if (! $values) $values = $this->values;
		$table = array();
		foreach ($row_labels as $row_label) {
			$row_header = $this->formatCell($row_label, $first_col_format);
			foreach ($values as $column => $set) {
				$cell_contents = $this->formatCell($set[$row_label], $data_format, $data_precision);
				$table[$row_header][$column] = $cell_contents;
			}
		}
		return $table;
	}
	
	public function renderIfDataIsAvailable($values, $view = 'table') {
		if ($this->recursiveImplode('', $values) != '') {
			// data is available
		} else {
			// data not available
		}
	}
	
	/* Lists the simple names (which should be found as array keys in Chart::getChartList()) 
	 * of charts that are actually only tables. */
	public function getExclusiveTables() {
		return array(
			'federal_spending',
			'public_assistance'
		);
	}
	
	public function getOutput($topic) {
		return $this->{$topic}();
	}
	
	
	
	/****** ****** Individual topics below ****** ******/
	
	

	// Variation: Array of dates instead of a year
	public function population($county = 1) {
		// Gather data
		$category_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates[$loc_key], $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
		}
		
		// Finalize
		$this->mergeDates();
		$this->reverseTimeline();
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->title = 'Population';
		$this->table = $this->getFormattedTableArray($this->dates, $this->values, 'year', 'number', 0);
	}
	
	// Variation: Growth between years calculated
	public function population_growth($county = 1) {		
		// Gather data
		$population_values = array();
		$category_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates, $population_values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $this->dates);
		}
		
		// Get growth values
		$row_labels = array();
		$last_date = end($this->dates);
		$this->dates = array_reverse($this->dates);
		foreach ($this->dates as $date) {
			if ($date == $last_date) {
				continue;
			}
			$row_labels[] = $row_label = substr($date, 0,4)."-".substr($last_date, 0,4);
			foreach ($this->locations as $loc_key => $location) {
				$earlier_population = $population_values[$loc_key][$date];
				$later_population = $population_values[$loc_key][$last_date]; 
				$this->values[$loc_key][$row_label] = (($later_population - $earlier_population) / $earlier_population) * 100;
			}
		}
		
		// Finalize
		$this->title = 'Population Growth';
		$this->columns = array_merge(array('Period'), $this->getLocationNames());
		$this->options[] = 'colorcode';
		$this->table = $this->getFormattedTableArray($row_labels, null, 'string', 'percent', 2);
	}
	
	public function density($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = 'Density Per Square Mile of Land Area';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 0);
	}
	
	public function population_age_breakdown($county = 1) {
		// Gather data
        $totals = [];
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$totals[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}

		// Generate percent values
        $categories = array_keys($this->data_categories);
        array_shift($categories); // Remove 'total population'
        foreach ($categories as $label) {
            foreach ($this->locations as $loc_key => $location) {
                $percent = ($totals[$loc_key][$label] / $totals[$loc_key]['Total']);
                $percent = round($percent * 100, 1);
                $this->values[$loc_key][$label] = $percent;
            }
        }
		
		// Finalize
		$this->columns = array_merge(array('Age Range'), $this->getLocationNames());
		$this->title = "Population By Age ($this->year)";
		$this->table = $this->getFormattedTableArray($categories, $this->values, 'string', 'percent', 1);
	}
	
	public function female_age_breakdown($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Range'), $this->getLocationNames());
		$this->title = "Female Age Breakdown For {$this->locations[0][2]} ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function population_by_sex($county = 1) {		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Population By Sex ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function dependency_ratios($county = 1) {
		// Gather data
        $totals = [];
        foreach ($this->data_categories as $label => $category_id) {
            foreach ($this->locations as $loc_key => $location) {
                $totals[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
            }
        }

        // Create "per 100 people" values
        foreach ($this->locations as $loc_key => $location) {
            $youngTotal = $totals[$loc_key]['Total 0 to 14 years old'];
            $oldTotal = $totals[$loc_key]['Total Over 65 years old'];
            $totalPopulation = $totals[$loc_key]['Total Population'];
            $youngPercent = round(($youngTotal / $totalPopulation) * 100, 1);
            $oldPercent = round(($oldTotal / $totalPopulation) * 100, 1);
            $this->values[$loc_key]['Child (< age 15)'] = $youngPercent;
            $this->values[$loc_key]['Elderly (65+)'] = $oldPercent;
            $this->values[$loc_key]['Total (< 15 and 65+)'] = $youngPercent + $oldPercent;
        }
		
		// Finalize
        $categories = [
            'Child (< age 15)',
            'Elderly (65+)',
            'Total (< 15 and 65+)'
        ];
		$this->columns = array_merge(array('Age Group'), $this->getLocationNames());
		$this->title = "Dependency Ratio Per 100 People ($this->year)";
		$this->table = $this->getFormattedTableArray($categories, $this->values, 'string', 'number', 1);
	}
	
	public function educational_attainment($county = 1) {
		// Gather data
        $totals = [];
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
                $value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			    $totals[$loc_key][$label] = $value;
			}
		}

        // Calculate percentages
        $totalPopulationCategory = array_keys($this->data_categories)[0];
        array_shift($this->data_categories);
        foreach ($this->data_categories as $label => $category_id) {
            foreach ($this->locations as $loc_key => $location) {
                $percent = $totals[$loc_key][$label] / $totals[$loc_key][$totalPopulationCategory];
                $this->values[$loc_key][$label] = $percent * 100;
            }
        }
		
		// Finalize
		$this->columns = array_merge(array('Education Level'), $this->getLocationNames());
		$this->title = "Educational Attainment, Population 25 Years and Over ($this->year)";
		$this->table = $this->getFormattedTableArray(
		    array_keys($this->data_categories),
            $this->values,
            'string',
            'percent',
            2
        );
	}
	
	// Variation: Locations are row headers, single category is a column header
	public function graduation_rate($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				if ($value) {
					$this->values[$label][$location[2]] = $value;
				} else {
					unset($this->locations[$loc_key]);
				}
			}
		}
		
		// Finalize
		$county_name = $this->Location->getCountyFullName($this->county_id, $this->state_id);
		$this->title = "High School Graduation Rate: $county_name ($this->year)";
		$this->columns = array('School Corporation', 'High School Graduation Rate');
		$this->table = $this->getFormattedTableArray($this->getLocationNames(), $this->values, 'string', 'percent', 1);
	}
	
	public function household_size($county = 1) {;
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Household Size ($this->year)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function households_with_minors($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	// Variation: Calculation being done to generate values
	public function household_types_with_minors($county = 1) {
		// Gather data
		$total_households_cat_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $this->year);
			foreach ($this->data_categories as $category => $category_id) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year) / $total_households;
				$this->values[$loc_key][$category] = $value * 100;
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Household Type'), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function households_with_over_65($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($this->year)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function poverty($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Percentage of Population in Poverty ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function lunches($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Free and Reduced Lunches ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function disabled($county = 1) {
		// Gather data
        $totals = [];
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$totals[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}

		// Calculate percent values
        $category_name = 'Percent of population with a disability';
        foreach ($this->locations as $loc_key => $location) {
            $percent = $totals[$loc_key]['Total population with a disability'] / $totals[$loc_key]['Population'];
            $this->values[$loc_key][$category_name] = $percent * 100;
        }
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Percent of Population Disabled ($this->year)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray([$category_name], $this->values, 'string', 'percent', 2);
	}
	
	public function disabled_ages($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			    $this->values[$loc_key][$label] = $value;
			}
		}
		
		// Finalize
		$location_names = $this->getLocationNames();
		$this->columns = array_merge(array('Age Range'), $location_names);
		$this->title = "Disabled Age Breakdown For $location_names[0] ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string');
	}
	
	public function share_of_establishments($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Establishment Type'), $this->getLocationNames());
		$this->title = "Percent Share of Total Establishments ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function employment_growth($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->locations[1][2] .= '*';
		$this->columns = array_merge(array('Period'), $this->getLocationNames());
		$this->title = "Employment Growth";
		$this->footnote = '* Not seasonally adjusted';
		$this->options[] = 'colorcode';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	// Variation: Array of dates instead of a year
	public function employment_trend($county = 1) {
		// Gather data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		$this->reverseTimeline();
		
		// Finalize
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->title = 'Employment';
		$this->table = $this->getFormattedTableArray($this->dates, $this->values, 'year', 'number', 0);
	}
	
	// Variation: Array of dates instead of a year
	public function unemployment_rate($county = 1) {
		// Gather data
		$category_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates[$loc_key], $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
		}
		$this->mergeDates();
		$this->reverseTimeline();
		
		// Finalize
		$this->locations[1][2] .= '*';
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->footnote = '* Not seasonally adjusted';
		$this->title = "Unemployment Rate";
		$this->table = $this->getFormattedTableArray($this->dates, $this->values, 'year', 'percent', 2);
	}
	
	public function personal_and_household_income($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Personal and Household Income ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'currency', 0);
	}
	
	// Variation: Array of dates instead of a year
	public function income_inequality($county = 1) {
		$this->expandDateCodes();
		
		// Gather, check, and manipulate data
		$category_id = array_pop($this->data_categories);		
		foreach ($this->locations as $loc_key => $location) {
			list($discard_dates, $this->values[$loc_key]) = $this->Datum->getValues($category_id, $location[0], $location[1], $this->dates);
		}
		$this->reverseTimeline();
		
		// Finalize
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->title = "Income Inequality";
		$this->table = $this->getFormattedTableArray($this->dates, $this->values, 'year', 'number', 2);
		//$cell_contents = $this->formatCell($set[$row_label], $data_format, $data_precision);
	}
	
	public function birth_rate($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Crude Birth Rate* ($this->year)";
		$this->footnote = '* Live births per 1,000 population.';
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function birth_rate_by_age($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Group'), $this->getLocationNames());
		$this->title = "Birth Rate By Age Group ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function birth_measures($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Birth Measures ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function fertility_rates($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Fertility Rates ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function deaths_by_sex($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Deaths By Sex ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function death_rate($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Age-Adjusted Death Rate* ($this->year)";
		$this->footnote = '* (All causes)';
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function infant_mortality($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Infant Death Rate* ($this->year)";
		$this->footnote = '* (Per 1,000 live births)';
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function life_expectancy($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Life Expectancy ($this->years_label)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 1);
	}
	
	public function years_of_potential_life_lost($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Years of Potential Life Lost* ($this->years_label)";
		$this->footnote = '* Before age 75';
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 0);
	}
	
	public function self_rated_poor_health($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				if ($value) {
					$this->values[$loc_key][$label] = $value;
				} else {
					$this->error = 2; // Required data unavailable
				}
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Self-rated Health Status: Fair/Poor ($this->years_label)";
		$this->options[] = 'hide_first_col';
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'percent', 2);
	}
	
	public function unhealthy_days($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
				if ($value) {
					$this->values[$loc_key][$label] = $value;
				} else {
					$this->error = 2; // Required data unavailable
				}
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Number of Unhealthy\nDays Per Month ($this->years_label)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function death_rate_by_cause($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Age-Adjusted Death Rate by Cause ($this->year)";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 2);
	}
	
	public function cancer_death_and_incidence_rates($county = 1) {		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Cancer Incidence and Death Rates ($this->years_label)";
		//$this->footnote = "* Healthy people target (2010) = 158.6\n** Healthy people target (2010) = 43.3\n^ Rates (cases per 100,000 population per year) are age-adjusted to the 2000 US standard population";
		$this->footnote = "Healthy people target (all cancers, 2010) = 158.6\nHealthy people target (lung and bronchus cancer, 2010) = 43.3\n^ Rates (cases per 100,000 population per year) are age-adjusted to the 2000 US standard population";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 1);
	}
	
	public function lung_diseases($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Lung Disease'), $this->getLocationNames());
		$this->title = "Lung Disease Incidence Rates* ($this->year)";
		$this->footnote = "* Per 1,000 Population";
		$this->table = $this->getFormattedTableArray(array_keys($this->data_categories), $this->values, 'string', 'number', 1);
	}
	
	public function federal_spending($county = 1) {		
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize ($this->table is being created manually because of each row having different formatting)
		$this->columns = array_merge(array(' '), $this->getLocationNames());
		$this->title = "Federal Spending ($this->year)";
		$this->footnote = "Dollar amounts are in thousands of dollars.\n* A rank of 1 corresponds to the highest-spending county in this state.";
		$row_titles = array_keys($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			$this->table[$row_titles[0]][$loc_key] = $this->formatCell($this->values[$loc_key][$row_titles[0]], 'currency');
			$this->table[$row_titles[1]][$loc_key] = $this->formatCell($this->values[$loc_key][$row_titles[1]], 'percent', 2);
			$this->table[$row_titles[2]][$loc_key] = $this->values[$loc_key][$row_titles[2]];
		}
	}
	
	public function public_assistance($county = 1) {
		// Gather data
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $this->year);
			}
		}
		
		// Finalize ($this->table is being created manually because of each column having different formatting)
		$location_names = $this->getLocationNames();
		$county_id = $this->locations[0][1];
		$state_id = $this->Location->getStateIDFromCountyID($county_id);
		$total_counties = $this->Location->getCountyCount($state_id); 
		$this->columns = array(
			' ', 
			"$location_names[0]\n#",
			"$location_names[0]\nRank out of $total_counties*",
			"$location_names[0]\n% of state",
			"$location_names[1]\n#",
		);
		$this->title = "Public Assistance ($this->year)";
		$this->footnote = "* A rank of 1 corresponds to the county that has received the least public assistance.";
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
				$this->formatCell($county_value),
				$this->values[0]["$row_title Rank"],
				$this->formatCell($percent, 'percent', 2),
				$this->formatCell($state_value)
			);
		}
	}
}