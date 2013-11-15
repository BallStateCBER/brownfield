<?php
App::Import ('model', 'Report');
class ExcelReport extends Report {
	public $useTable = false;
	
	public $title = '';
	public $author = 'Center for Business and Economic Research, Ball State University';
	public $columns = array();
	public $footnote = '';
	public $first_col_format = 'year';
	public $data_format = 'number';
	public $data_precision = 0;
	public $excel_type; 		//excel5 or excel2007
	public $output_type; 		//Excel5 or Excel2007
	public $objPHPExcel;
	public $current_row = 1; 	//Row iterator (first row is 1, not 0)
	public $col_labels;		//Set by setColumnLabels() and referenced by setValues 
	public $row_labels;		//Set by setRowLabels() and referenced by setValues
	public $mockup; 			//[col][row] => value array outputted during debugging
	public $table = array(); 

	// Experimental. Example:
	//    $individual_value_formats[$col_num][$row_num] = '#,##0.00';
	// Number formats found in PHPExcel/Style/NumberFormat.php.
	// $col_num and $row_num zero-indexed, relative to the grid of values, not the entire spreadsheet
	// (so the first value in the first row is at [0][0], second is [1][0], etc.)
	public $individual_value_formats = array();
	
	// To do: How will this be outputted?
	// Move this to the main Report class?
	public $error_message = null;
	
	/* This array can contain the following strings:
	 * 		hide_first_col: Hides the first column (row titles)
	 *		colorcode:		Colors each value red, black, or green, depending on whether it's <, =, or > zero
	 */  	
	public $options = array();
	
	public function renderIfDataIsAvailable($values, $view = '/tables/table') {
		if ($this->recursiveImplode('', $values) != '') {
			// data is available
		} else {
			// data not available
		}
	}
	
	
	
	/****** ****** Generation of PHPExcel object ****** ******/
	
	
	// Sets up $this->objPHPExcel so that it's ready for output
	public function getOutput($topic) {
		// Run the topic-specific report-preparation method
		$this->{$topic}();
		
		// Translate the 'report type' value to a PHPExcel output type
		switch ($this->excel_type) {
			case 'excel2007':
				$this->output_type = 'Excel2007';
				break;
			case 'excel5':
				$this->output_type = 'Excel5';
				break;
		}
		
		// Start up
		PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_AdvancedValueBinder());
		$this->objPHPExcel = new PHPExcel();
		$this->objPHPExcel->setActiveSheetIndex(0);
		
		// Populate the spreadsheet
		$this->__setMetaData(array(
			'author' => $this->author,
			'title' => $this->title,
			'description' => ''
		));
		$this->objPHPExcel->getDefaultStyle()->getFont()->setName('Arial');
		$this->objPHPExcel->getDefaultStyle()->getFont()->setSize(11); 
		$this->__setTitle();				// Uses $this->title
		$this->__setSources();				// Uses requestAction()
		$this->__setColumnAndRowLabels();	// Uses $this->columns and $this->row_labels
		$this->__setValues();
		$this->__setFootnote();
		
		// Reduce the width of the first column 
		//   (which contains only the title and sources and overflow over the unoccupied cells to the right)
		$this->objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(1.5);
		
		// Automatically adjust the width of all columns AFTER the first 
		$last_col = count($this->columns);
		for ($c = 1; $c <= $last_col; $c++) {
			$col_letter = $this->__convertNumToLetter($c);
			$this->objPHPExcel->getActiveSheet()->getColumnDimension($col_letter)->setAutoSize(true);
		}
	}
	
	private function __setMetaData($metadata) {
		// Metadata
		$this->objPHPExcel->getProperties()
			->setCreator($metadata['author'])
			->setLastModifiedBy($metadata['author'])
			->setTitle($metadata['title'])
			->setSubject($metadata['title'])
			->setDescription($metadata['description']);
	}
	
	private function __setTitle() {
		// Set title
		$this->__setCell(0, 1, $this->title);
		
		// Style title
		$this->__setStylesFromArray('A1', 'A1', array(
			'font' => array(
				'bold' => true,
				'size' => 24
			)
		));
		
		$this->current_row++;
	}
	
	private function __setSources() {
		$sources = $this->requestAction(
			array('controller' => 'reports', 'action' => 'switchboard'),
			array('pass' => array('source', $this->topic, $this->state_id, $this->county_id))
		);
		$col_count = count($this->columns);
		foreach ($sources as $source) {
			$this->__setCell(0, $this->current_row, "Source: $source");
			$this->current_row++;
		}
		
		// Blank row after sources
		$this->current_row++;
	}
	
	// Note that column headers and values start on the SECOND column
	private function __setColumnAndRowLabels() {
		// Write column labels
		foreach ($this->columns as $key => $column_label) {
			$col = $key + 1;
			$this->__setCell($col, $this->current_row, $column_label);	
		}
		
		// Repeat column labels at top of every printed page
		$this->objPHPExcel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($this->current_row, $this->current_row);
		
		// Style column labels
		$first_cell = 'B'.$this->current_row;
		$last_cell = $this->__convertNumToLetter(count($this->columns)).$this->current_row;
		$this->__setStylesFromArray($first_cell, $last_cell, array(
			'font' => array(
				'bold' => true,
				'size' => 12
			),
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER
			),
			'borders' => array(
				'bottom' => array(
					'style' => PHPExcel_Style_Border::BORDER_THIN
				)
			),
			'fill' => array(
				'type' => PHPExcel_Style_Fill::FILL_GRADIENT_LINEAR,
				'rotation' => 90,
				'startcolor' => array(
					'argb' => 'FFFFFFFF'
				),
				'endcolor' => array(
					'argb' => 'FFDFDFDF'
				)
			)
		));
		
		// Enable autofilter on column headers
		$this->objPHPExcel->getActiveSheet()->setAutoFilter("$first_cell:$last_cell");
		
		$this->current_row++;
		
		// Write row labels
		$type = isset($this->first_col_format) ? $this->first_col_format : 'string';
		$row_iter = $this->current_row;
		foreach ($this->row_labels as $row_label) {
			$row_label = $this->__formatValue($row_label, $type);
			$this->__setCell(1, $row_iter++, $row_label);
		}
		
		// Style row labels
		$first_cell = 'B'.$this->current_row;
		$last_cell = 'B'.($this->current_row + count($this->row_labels) - 1);
		$this->__setStylesFromArray($first_cell, $last_cell, array(
			'font' => array(
				'bold' => true,
				'size' => 12
			),
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT
			),
			'borders' => array(
				'right' => array(
					'style' => PHPExcel_Style_Border::BORDER_THIN
				)
			)
		));
	}
	
	private function __setStylesFromArray($first_cell, $last_cell, $styles) {
		$this->objPHPExcel->getActiveSheet()->getStyle("$first_cell:$last_cell")->applyFromArray($styles);
	}
	
	// Used in converting coordinates (0,0) to Excel cell identifiers (A1)
	// Currently does not work past the 26th column
	private function __convertNumToLetter($number, $capitalize = true) {
		$letters = 'abcdefghijklmnopqrstuvwxyz';
		$letter = substr($letters, $number, 1);
		return $capitalize ? strtoupper($letter) : $letter;
	}
		
	/* Expects $this->values to be populated like this:
	 * 		$this->values[$col_num][$row_label] = $value
	 * with $col_num being zero-indexed. */
	private function __setValues() {
		// Freeze everything above data when scrolling
		$this->objPHPExcel->getActiveSheet()->freezePane('A'.($this->current_row));
		
		// Set values
		$row_count = 0;
		foreach ($this->values as $col_num => $values_in_column) {
			$row_count = max($row_count, count($values_in_column));
			foreach ($values_in_column as $row_label => $value) {

				// Get the proper row number and check for error
				$row_num = array_search($row_label, $this->row_labels);
				if ($row_num === false) {
					$this->error = 5;
					$this->error_message = "ExcelReport::values[$col_num][<b>$row_label</b>] uses an unrecognized row label. Recognized labels: <ul>";
					foreach ($this->row_labels as $row_label) {
						$this->error_message .= '<li>'.$row_label.'</li>';
					}
					$this->error_message .= '</ul>';
					return;
				}
				
				// Adjust column to the right
				$col_num_adjusted = ($col_num + 2);
				
				// Adjust row downward
				$row_num_adjusted = ($row_num + $this->current_row);
				
				// Write value 
				$this->__setCell($col_num_adjusted, $row_num_adjusted, $value);
				
				// Optionally apply a number format
				if (isset($this->individual_value_formats[$col_num][$row_num])) {
					//if (isset($_GET['debug'])) echo "[$col_num][$row_num]: Formatting (".$this->individual_value_formats[$col_num][$row_num].")<br />";
					$this->__applyNumberFormatToCell($col_num_adjusted, $row_num_adjusted, $this->individual_value_formats[$col_num][$row_num]); 	
				} else {
					//if (isset($_GET['debug'])) echo "[$col_num][$row_num]: No formatting<br />";
				}
			}
		}
		
		// Style entire block of values
		$first_cell = 'C'.$this->current_row;
		$last_cell = $this->__convertNumToLetter(count($this->values) + 1).($this->current_row + count($this->row_labels) - 1);
		$this->__setStylesFromArray($first_cell, $last_cell, array(
			'alignment' => array(
				'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT
			)
		));
		
		$this->current_row += $row_count;
	}
	
	// $col_num and $row_num are zero-indexed and relative to the entire spreadsheet
	private function __applyNumberFormatToCell($col_num, $row_num, $format) {
		$excel_cell = $this->__convertNumToLetter($col_num).($row_num);
		$this->objPHPExcel->getActiveSheet()->getStyle($excel_cell)->getNumberFormat()->setFormatCode($format);
	}

	private function __formatValue($value, $mode = 'number', $precision = 0) {
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
	
	// Adds a footnote to the bottom of the spreadsheet
	// If a newline is in the footnote, splits up footnote into multiple rows
	private function __setFootnote() {
		if ($this->footnote) {
			$this->current_row++; // Blank line before footnote
			$footnote_lines = explode("\n", $this->footnote);
			foreach ($footnote_lines as $footnote_line) {
				$this->__setCell(0, $this->current_row, $footnote_line);
				$coordinates = $this->__getExcelCoordinates(0, $this->current_row);
				$this->objPHPExcel->getActiveSheet()->getStyle($coordinates)->getAlignment()->setWrapText(false);
				$this->current_row++;
			}
		}
	}
	
	private function __setCell($col, $row, $value) {
		if ($value !== null && $value !== false) {
			$this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, $value);
		}
		$this->mockup[$col][$row] = $value;
	}
	
	private function __getExcelCoordinates($col, $row) {
		return $this->__convertNumToLetter($col).($row);	
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
		$this->row_labels = $this->dates;
		$this->first_col_format = 'year';
		$this->data_format = 'number';
		$this->data_precision = 0;
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
		$this->row_labels = array();
		$last_date = end($this->dates);
		$this->dates = array_reverse($this->dates);
		foreach ($this->dates as $date) {
			if ($date == $last_date) {
				continue;
			}
			$this->row_labels[] = $row_label = substr($date, 0,4)."-".substr($last_date, 0,4);
			foreach ($this->locations as $loc_key => $location) {
				$earlier_population = $population_values[$loc_key][$date];
				$later_population = $population_values[$loc_key][$last_date]; 
				$this->values[$loc_key][$row_label] = (($later_population - $earlier_population) / $earlier_population);

				// Give percentage value proper formatting
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->title = 'Population Growth';
		$this->columns = array_merge(array('Period'), $this->getLocationNames());
		$this->options[] = 'colorcode';
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function density($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = 'Density Per Square Mile of Land Area';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 0;
	}
	
	public function population_age_breakdown($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Range'), $this->getLocationNames());
		$this->title = "Population By Age ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 1;
	}
	
	public function female_age_breakdown($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Range'), $this->getLocationNames());
		$this->title = "Female Age Breakdown For {$this->locations[0][2]} ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function population_by_sex($county = 1) {		
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Population By Sex ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function dependency_ratios($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Group'), $this->getLocationNames());
		$this->title = "Dependency Ratio Per 100 People ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 0;
	}
	
	public function educational_attainment($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Education Level'), $this->getLocationNames());
		$this->title = "Educational Attainment, Population 25 Years and Over ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	// Variation: Locations are row headers, single category is a column header
	public function graduation_rate($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				
				if ($value) {
					$this->values[$label][$location[2]] = $value;
				} else {
					unset($this->locations[$loc_key]);
				}
				
				$this->individual_value_formats[$label][$loc_key] = '0.0%';
			}
		}
		
		// Finalize
		$county_name = $this->Location->getCountyFullName($this->county_id, $this->state_id);
		$this->title = "High School Graduation Rate: $county_name ($year)";
		$this->columns = array('School Corporation', 'High School Graduation Rate');
		$this->row_labels = array_values($this->getLocationNames());
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 1;
	}
	
	public function household_size($county = 1) {;
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Household Size ($year)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function households_with_minors($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($year)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	// Variation: Calculation being done to generate values
	public function household_types_with_minors($county = 1) {
		// Gather data
		$total_households_cat_id = array_pop($this->data_categories);
		$year = reset($this->dates);
		foreach ($this->locations as $loc_key => $location) {
			$total_households = $this->Datum->getValue($total_households_cat_id, $location[0], $location[1], $year);
			foreach ($this->data_categories as $category => $category_id) {
				$this->values[$loc_key][$category] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / $total_households;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Household Type'), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function households_with_over_65($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Households With One or More People Under 18 Years ($year)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function poverty($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Percentage of Population in Poverty ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function lunches($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Free and Reduced Lunches ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function disabled($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Percent of Population Disabled ($year)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function disabled_ages($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$location_names = $this->getLocationNames();
		$this->columns = array_merge(array('Age Range'), $location_names);
		$this->title = "Disabled Age Breakdown For $location_names[0] ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function share_of_establishments($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Establishment Type'), $this->getLocationNames());
		$this->title = "Percent Share of Total Establishments ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function employment_growth($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->locations[1][2] .= '*';
		$this->columns = array_merge(array('Period'), $this->getLocationNames());
		$this->title = "Employment Growth";
		$this->footnote = '* Not seasonally adjusted';
		$this->options[] = 'colorcode';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	// Variation: Array of dates instead of a year
	public function employment_trend($county = 1) {
		// Gather data
		list($this->dates, $this->values[0]) = $this->Datum->getSeries(array_pop($this->data_categories), $this->locations[0][0], $this->locations[0][1]);
		$this->reverseTimeline();
		
		// Finalize
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->title = 'Employment';
		$this->row_labels = $this->dates;
		$this->first_col_format = 'year';
		$this->data_format = 'number';
		$this->data_precision = 0;
	}
	
	// Variation: Array of dates instead of a year
	public function unemployment_rate($county = 1) {
		// Gather data
		$category_id = array_pop($this->data_categories);
		foreach ($this->locations as $loc_key => $location) {
			list($this->dates[$loc_key], $this->values[$loc_key]) = $this->Datum->getSeries($category_id, $location[0], $location[1]);
		}
		
		// Format values
		foreach ($this->values as $loc_key => &$value_set) {
			foreach ($value_set as $year => &$value) {
				$value /= 100;
				$this->individual_value_formats[$loc_key][] = '0.0%';
			}
		}
		
		$this->mergeDates();
		$this->reverseTimeline();
		
		// Finalize
		$this->locations[1][2] .= '*';
		$this->columns = array_merge(array('Year'), $this->getLocationNames());
		$this->footnote = '* Not seasonally adjusted';
		$this->title = "Unemployment Rate";
		$this->row_labels = $this->dates;
		$this->first_col_format = 'year';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function personal_and_household_income($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Personal and Household Income ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'currency';
		$this->data_precision = 0;
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
		$this->row_labels = $this->dates;
		$this->first_col_format = 'year';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function birth_rate($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Crude Birth Rate* ($year)";
		$this->footnote = '* Live births per 1,000 population.';
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function birth_rate_by_age($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Age Group'), $this->getLocationNames());
		$this->title = "Birth Rate By Age Group ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function birth_measures($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.0%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Birth Measures ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function fertility_rates($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Fertility Rates ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function deaths_by_sex($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Deaths By Sex ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function death_rate($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Age-Adjusted Death Rate* ($year)";
		$this->footnote = '* (All causes)';
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function infant_mortality($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Infant Death Rate* ($year)";
		$this->footnote = '* (Per 1,000 live births)';
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function life_expectancy($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Life Expectancy ($this->years_label)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 1;
	}
	
	public function years_of_potential_life_lost($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Years of Potential Life Lost* ($this->years_label)";
		$this->footnote = '* Before age 75';
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 0;
	}
	
	public function self_rated_poor_health($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $year) / 100;
				if ($value) {
					$this->values[$loc_key][$label] = $value;
				} else {
					$this->error = 2; // Required data unavailable
				}
				
				$this->individual_value_formats[$loc_key][] = '0.00%';
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Self-rated Health Status: Fair/Poor ($this->years_label)";
		$this->options[] = 'hide_first_col';
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'percent';
		$this->data_precision = 2;
	}
	
	public function unhealthy_days($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$value = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
				if ($value) {
					$this->values[$loc_key][$label] = $value;
				} else {
					$this->error = 2; // Required data unavailable
				}
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Average Number of Unhealthy Days Per Month ($this->years_label)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function death_rate_by_cause($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Age-Adjusted Death Rate by Cause ($year)";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 2;
	}
	
	public function cancer_death_and_incidence_rates($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array(''), $this->getLocationNames());
		$this->title = "Cancer Incidence and Death Rates ($this->years_label)";
		$this->footnote = "Healthy people target (all cancers, 2010) = 158.6\nHealthy people target (lung and bronchus cancer, 2010) = 43.3\n^ Rates (cases per 100,000 population per year) are age-adjusted to the 2000 US standard population";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 1;
	}
	
	public function lung_diseases($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize
		$this->columns = array_merge(array('Lung Disease'), $this->getLocationNames());
		$this->title = "Lung Disease Incidence Rates* ($year)";
		$this->footnote = "* Per 1,000 Population";
		$this->row_labels = array_keys($this->data_categories);
		$this->first_col_format = 'string';
		$this->data_format = 'number';
		$this->data_precision = 1;
	}
	
	public function federal_spending($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize ($this->table is being created manually because of each row having different formatting)
		$this->columns = array_merge(array(' '), $this->getLocationNames());
		$this->title = "Federal Spending ($year)";
		$this->footnote = "Dollar amounts are in thousands of dollars.\n* A rank of 1 corresponds to the highest-spending county in this state.";
		$this->first_col_format = 'string';
		$this->row_labels = array_keys($this->data_categories);

		// $this->values is being manipulated because of each column having different formatting
		$rearranged_values = array();
		foreach ($this->locations as $loc_key => $location) {
			$rearranged_values[$loc_key][$this->row_labels[0]] = $this->formatCell($this->values[$loc_key][$this->row_labels[0]], 'currency');
			$rearranged_values[$loc_key][$this->row_labels[1]] = $this->formatCell($this->values[$loc_key][$this->row_labels[1]], 'percent', 2);
			$rearranged_values[$loc_key][$this->row_labels[2]] = $this->values[$loc_key][$this->row_labels[2]];
		}
		// Give percentage value proper formatting
		$this->individual_value_formats[0][1] = '0.00%';
		$this->values = $rearranged_values;
	}
	
	public function public_assistance($county = 1) {
		// Gather data
		$year = reset($this->dates);
		foreach ($this->data_categories as $label => $category_id) {
			foreach ($this->locations as $loc_key => $location) {
				$this->values[$loc_key][$label] = $this->Datum->getValue($category_id, $location[0], $location[1], $year);
			}
		}
		
		// Finalize 
		$location_names = $this->getLocationNames();
		$this->columns = array(
			' ', 
			"$location_names[0]\n#",
			"$location_names[0]\nRank out of 92*",
			"$location_names[0]\n% of state",
			"$location_names[1]\n#",
		);
		$this->title = "Public Assistance ($year)";
		$this->first_col_format = 'string';
		$this->footnote = "* A rank of 1 corresponds to the county that has received the least public assistance.";
		$this->row_labels = array(
			'Women, Infants, and Children (WIC) Participants',
			'Monthly Average of Families Receiving TANF',
			'Monthly Average of Persons Issued Food Stamps (FY)'
		);
		// $this->values is being manipulated because of each column having different 
		// formatting and data being output in a nonstandard arrangement
		$rearranged_values = array();
		foreach ($this->row_labels as $row_key => $row_title) {
			$county_value = $this->values[0][$row_title];
			$state_value = $this->values[1][$row_title];
			$percent = ($county_value / $state_value) * 100;
			$rearranged_values[0][$row_title] = $county_value;
			$rearranged_values[1][$row_title] = $this->values[0]["$row_title Rank"];
			$rearranged_values[2][$row_title] = $percent / 100;
			$rearranged_values[3][$row_title] = $state_value;

			// Give percentage value proper formatting
			$this->individual_value_formats[2][$row_key] = '0.00%';
		}
		$this->values = $rearranged_values;
	}
}