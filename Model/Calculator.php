<?php
class Calculator extends AppModel {
	public $useTable = false;
	
	public $rptoutput_multipliers_table = 'calc_rptoutput_multipliers';
	public $rptemployment_multipliers_table = 'calc_rptemployment_multipliers';
	public $rptec_multipliers_table = 'calc_rptec_multipliers';
	public $rptibt_multipliers_table = 'calc_rptibt_multipliers';
	public $ibt_detail_table = 'calc_ibt_detail';
	
	public function getNaicsIndustries() {
		$industries = array(
			1 => 'Agriculture, Forestry, Fishing, and Hunting',
			2 => 'Real Estate, Rental, and Leasing',
			3 => 'Mining, Quarrying, and Oil and Gas Extraction',
			4 => 'Professional, Scientific, and Technical Services',
			5 => 'Utilities',
			6 => 'Management of Companies and Enterprises',
			7 => 'Construction',
			8 => 'Administrative and Support and Waste Management and Remediation Services',
			9 => 'Manufacturing',
			12 => 'Educational Services',
			13 => 'Wholesale Trade',
			14 => 'Health Care and Social Assistance',
			15 => 'Retail Trade',
			17 => 'Arts, Entertainment, and Recreation',
			18 => 'Transportation and Warehousing',
			20 => 'Accommodation and Food Services',
			21 => 'Information',
			22 => 'Other Services (except Public Administration)',
			23 => 'Finance and Insurance',
			24 => 'Public Administration'
		);
		asort($industries);
		return $industries;
	}
	
	public function getCounties() {
		return array(
			1 => 'Adams', 2 => 'Allen', 3 => 'Bartholomew', 
			4 => 'Benton', 5 => 'Blackford', 6 => 'Boone', 7 => 'Brown', 8 => 'Carroll', 9 => 'Cass', 10 => 'Clark',
			11 => 'Clay', 12 => 'Clinton', 13 => 'Crawford', 14 => 'Daviess', 15 => 'Dearborn', 16 => 'Decatur', 17 => 'DeKalb',
			18 => 'Delaware', 19 => 'Dubois', 20 => 'Elkhart', 21 => 'Fayette', 22 => 'Floyd', 23 => 'Fountain', 24 => 'Franklin',
			25 => 'Fulton', 26 => 'Gibson', 27 => 'Grant', 28 => 'Greene', 29 => 'Hamilton', 30 => 'Hancock', 31 => 'Harrison',
			32 => 'Hendricks', 33 => 'Henry', 34 => 'Howard', 35 => 'Huntington', 36 => 'Jackson', 37 => 'Jasper', 38 => 'Jay',
			39 => 'Jefferson', 40 => 'Jennings', 41 => 'Johnson', 42 => 'Knox', 43 => 'Kosciusko', 44 => 'LaGrange', 45 => 'Lake',
			46 => 'LaPorte', 47 => 'Lawrence', 48 => 'Madison', 49 => 'Marion', 50 => 'Marshall', 51 => 'Martin', 52 => 'Miami',
			53 => 'Monroe', 54 => 'Montgomery', 55 => 'Morgan', 56 => 'Newton', 57 => 'Noble', 58 => 'Ohio', 59 => 'Orange', 
			60 => 'Owen', 61 => 'Parke', 62 => 'Perry', 63 => 'Pike', 64 => 'Porter', 65 => 'Posey', 66 => 'Pulaski',
			67 => 'Putnam', 68 => 'Randolph', 69 => 'Ripley', 70 => 'Rush', 71 => 'St. Joseph', 72 => 'Scott', 73 => 'Shelby',
			74 => 'Spencer', 75 => 'Starke', 76 => 'Steuben', 77 => 'Sullivan', 78 => 'Switzerland', 79 => 'Tippecanoe', 
			80 => 'Tipton', 81 => 'Union', 82 => 'Vanderburgh', 83 => 'Vermillion', 84 => 'Vigo', 85 => 'Wabash',
			86 => 'Warren', 87 => 'Warrick', 88 => 'Washington', 89 => 'Wayne', 90 => 'Wells', 91 => 'White', 92 => 'Whitley'
		);	
	}
	
	public function getLocalIndustries($county_id) {
		if (! is_numeric($county_id)) {
			return;
		}
		$table = 'calc_rptec_multipliers';
		$result = $this->query("
			SELECT industry
			FROM $table
			WHERE is_naics = 1
			AND county_id = $county_id
			AND direct_effects <> 0
		");
		$industries = array();
		foreach ($result as $key => $row) {
			$industries[] = $row[$table]['industry'];
		}
		return $industries;
	}
	
	public function getMultiplier($type, $county_id, $industry_id, $option) {
		if ($type == 'output') {
			// "Output" multiplier = rptoutput_multipliers.type_n_multiplier
			$result = $this->query("
				SELECT type_n_multiplier
				FROM $this->rptoutput_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptoutput_multipliers_table]['type_n_multiplier'];
		}
		if ($type == 'direct_jobs') {
			// "Direct jobs" multiplier = rptemployment_multipliers.direct_effects / 1,000,000
			$result = $this->query("
				SELECT direct_effects
				FROM $this->rptemployment_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptemployment_multipliers_table]['direct_effects'] / 1000000;
		}
		if ($type == 'ibt') {
			// "IBT" multiplier = rptibt_multipliers.direct_effects + ...indirect_effects + ...induced_effects
			$result = $this->query("
				SELECT direct_effects, indirect_effects, induced_effects
				FROM $this->rptibt_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return array_sum($result[0][$this->rptibt_multipliers_table]);
		}
		if ($type == 'direct_payroll') {
			$result = $this->query("
				SELECT direct_effects
				FROM $this->rptec_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptec_multipliers_table]['direct_effects'];	
		}
		if ($type == 'direct_ibt') {
			$result = $this->query("
				SELECT direct_effects
				FROM $this->rptibt_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptibt_multipliers_table]['direct_effects'];	
		}
		if ($type == 'jobs') {
			$result = $this->query("
				SELECT type_n_multiplier
				FROM $this->rptemployment_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptemployment_multipliers_table]['type_n_multiplier'];	
		}
		if ($type == 'payroll') {
			$result = $this->query("
				SELECT type_n_multiplier
				FROM $this->rptec_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptec_multipliers_table]['type_n_multiplier'];	

		}
		if ($type == 'total_jobs') {
			$result = $this->query("
				SELECT type_n_multiplier
				FROM $this->rptemployment_multipliers_table
				WHERE county_id = $county_id
				AND industry = $industry_id
				AND is_naics = 1
				LIMIT 1
			");
			return $result[0][$this->rptemployment_multipliers_table]['type_n_multiplier'];	
		}
	}
	
	public function getTaxShares($county_id, $move_sales_tax_into_other) {
		//Indirect Business Tax Impact (Detail)
		$tax_type_key = array(
			15017 => 'Excise Taxes',
			15018 => 'Custom Duty',
			15019 => 'Fed NonTaxes',
			15020 => 'Sales Tax',
			15021 => 'Property Tax',
			15022 => 'Motor Vehicle Lic',
			15023 => 'Severance Tax',
			15024 => 'Other Taxes',
			15025 => 'S/L NonTaxes'
		);
		
		// Get multipliers
		$result = $this->query("
			SELECT tax_type, value
			FROM $this->ibt_detail_table
			WHERE county_id = $county_id
		");
		$tax_multipliers = array();
		foreach ($result as $key => $row) {
			$tax_type_id = $row[$this->ibt_detail_table]['tax_type'];
			$tax_type_name = $tax_type_key[$tax_type_id];
			$value = $row[$this->ibt_detail_table]['value'];
			$tax_multipliers[$tax_type_name] = $value;
		}
		
		// Calculate tax shares
		$total_ibt_value = array_sum($tax_multipliers);
		$tax_shares['federal'] = (
			$tax_multipliers['Excise Taxes'] +
			$tax_multipliers['Custom Duty'] +
			$tax_multipliers['Fed NonTaxes']
		) / $total_ibt_value;
		$tax_shares['property'] = $tax_multipliers['Property Tax'] / $total_ibt_value;
		if ($move_sales_tax_into_other) {
			$tax_shares['other'] = (
				$tax_multipliers['Motor Vehicle Lic'] +
				$tax_multipliers['Severance Tax'] +
				$tax_multipliers['Other Taxes'] +
				$tax_multipliers['Sales Tax'] +
				$tax_multipliers['S/L NonTaxes']
			) / $total_ibt_value;
		} else {
			$tax_shares['sales'] = $tax_multipliers['Sales Tax'] / $total_ibt_value;
			$tax_shares['other'] = (
				$tax_multipliers['Motor Vehicle Lic'] +
				$tax_multipliers['Severance Tax'] +
				$tax_multipliers['Other Taxes'] +
				$tax_multipliers['S/L NonTaxes']
			) / $total_ibt_value;
		}
		return $tax_shares;
	}
	
	public function tifCalculateByProduction($params) {
		// Protect against injection attacks
		foreach ($params as $var => $val) {
			if (! is_numeric($val)) {
				echo "Error: $var is not numeric ($val).";
				return;
			}
		}
		
		// Parameters
		$option = 'production';
		$county_id = $params['county_id'];
		$industry_id = $params['industry_id'];
		$annual_production = $params['annual_production'];
		
		// Get multipliers
		$multiplier_types = array('output', 'direct_jobs', 'total_jobs', 'payroll', 'ibt', 'direct_payroll', 'direct_ibt');
		$multipliers = array();
		foreach ($multiplier_types as $multiplier_type) {
			$multipliers[$multiplier_type] = $this->getMultiplier($multiplier_type, $county_id, $industry_id, $option);
		}
		
		// Calculate impacts
		$impact = array(); 
		$impact['annual_production'] = 	$annual_production;
		$impact['direct_jobs'] 		= $impact['annual_production']	* $multipliers['direct_jobs'];
		$impact['output'] 			= $impact['annual_production']	* $multipliers['output'];
		$impact['total_jobs'] 		= $impact['direct_jobs'] 		* $multipliers['total_jobs'];
		$impact['ibt'] 				= $impact['annual_production']	* $multipliers['ibt'];
		$impact['direct_payroll'] 	= $impact['annual_production']	* $multipliers['direct_payroll'];
		$impact['payroll'] 			= $impact['direct_payroll'] 	* $multipliers['payroll'];
		$impact['average_earnings']	= $impact['direct_payroll']		/ $impact['direct_jobs'];
		$impact['direct_ibt'] 		= $impact['annual_production']	* $multipliers['direct_ibt'];
		$impact['annual_production_per_worker'] = $impact['annual_production'] / $impact['direct_jobs'];
		
		// Should sales tax be part of 'other' tax?
		switch ($industry_id) {
			case 9: // Manufacturing
			case 13: // Health care
			case 14: // Wholesale trade
				$move_sales_tax_into_other = true;
				break;
			default;
				$move_sales_tax_into_other = false;
		}
		
		// Get tax shares
		$tax_shares = $this->getTaxShares($county_id, $move_sales_tax_into_other);
		
		// Calculate tax details
		$impact['tax_detail'] = array(
			'federal' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['federal'],
				'total' => $impact['ibt'] * $tax_shares['federal']
			),
			'sales' => $move_sales_tax_into_other ? null : array(
				'direct' => $impact['direct_ibt'] * $tax_shares['sales'],
				'total' => $impact['ibt'] * $tax_shares['sales']
			),
			'property' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['property'],
				'total' => $impact['ibt'] * $tax_shares['property']
			),
			'other' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['other'],
				'total' => $impact['ibt'] * $tax_shares['other']
			)
		);
		$impact['tax_detail']['total_state_local']['direct'] = $impact['tax_detail']['property']['direct'] + $impact['tax_detail']['other']['direct'];
		$impact['tax_detail']['total_state_local']['total'] = $impact['tax_detail']['property']['total'] + $impact['tax_detail']['other']['total'];
		if (! $move_sales_tax_into_other) {
			$impact['tax_detail']['total_state_local']['direct'] += $impact['tax_detail']['sales']['direct'];
			$impact['tax_detail']['total_state_local']['total'] += $impact['tax_detail']['sales']['total'];
		}
		
		// Format values for output
		$precision = 8;
		foreach ($multipliers as $name => $value) {
			$multipliers[$name] = number_format($value, $precision);
		}
		foreach ($impact as $name => $value) {
			if ($name != 'tax_detail') {
				$impact[$name] = number_format($impact[$name]);
				if ($name != 'direct_jobs' && $name != 'total_jobs') {
					$impact[$name] = '$'.$impact[$name];
				}
			}
		}
		foreach ($impact['tax_detail'] as $category => $category_array) {
			if (! $category_array) {
				continue;
			}
			foreach ($category_array as $scope => $value) {
				$impact['tax_detail'][$category][$scope] = '$'.number_format($impact['tax_detail'][$category][$scope]);
			}
		}
		
		return array($impact, $multipliers);
	}
	
	public function tifCalculateByEmployees($params) {
		// Protect against injection attacks
		foreach ($params as $var => $val) {
			if (! is_numeric($val)) {
				echo "Error: $var is not numeric ($val).";
				return;
			}
		}
		
		// Parameters
		$option = 'employees';
		$county_id = $params['county_id'];
		$industry_id = $params['industry_id'];
		$direct_jobs = $params['employees'];

		// Get multipliers
		$multiplier_types = array('direct_payroll', 'payroll', 'output', 'ibt', 'direct_ibt', 'total_jobs', 'direct_jobs');
		$multipliers = array();
		foreach ($multiplier_types as $multiplier_type) {
			$multipliers[$multiplier_type] = $this->getMultiplier($multiplier_type, $county_id, $industry_id, $option);
		}		
		
		// Calculate impacts
		$impact = array();
		$impact['direct_jobs'] 		 = $direct_jobs;
		$impact['annual_production'] = $impact['direct_jobs']  		/ $multipliers['direct_jobs'];
		$impact['output'] 			 = $impact['annual_production'] * $multipliers['output'];
		$impact['direct_payroll'] 	 = $impact['annual_production'] * $multipliers['direct_payroll'];
		$impact['average_earnings']	 = $impact['direct_payroll']	/ $impact['direct_jobs'];
		$impact['payroll'] 			 = $impact['direct_payroll'] 	* $multipliers['payroll'];
		$impact['total_jobs'] 		 = $impact['direct_jobs'] 		* $multipliers['total_jobs'];
		$impact['ibt'] 				 = $impact['annual_production'] * $multipliers['ibt'];
		$impact['direct_ibt'] 		 = $impact['annual_production'] * $multipliers['direct_ibt'];
		$impact['annual_production_per_worker'] = 1 / $multipliers['direct_jobs'];
		
		// Remove this from the output, as it is effectively 1
		$multipliers['direct_jobs'] = null;
		
		// Should sales tax be part of 'other' tax?
		switch ($industry_id) {
			case 9: // Manufacturing
			case 13: // Health care
			case 14: // Wholesale trade
				$move_sales_tax_into_other = true;
				break;
			default;
				$move_sales_tax_into_other = false;
		}
		
		// Get tax shares
		$tax_shares = $this->getTaxShares($county_id, $move_sales_tax_into_other);
		
		// Calculate tax details
		$impact['tax_detail'] = array(
			'federal' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['federal'],
				'total' => $impact['ibt'] * $tax_shares['federal']
			),
			'sales' => $move_sales_tax_into_other ? null : array(
				'direct' => $impact['direct_ibt'] * $tax_shares['sales'],
				'total' => $impact['ibt'] * $tax_shares['sales']
			),
			'property' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['property'],
				'total' => $impact['ibt'] * $tax_shares['property']
			),
			'other' => array(
				'direct' => $impact['direct_ibt'] * $tax_shares['other'],
				'total' => $impact['ibt'] * $tax_shares['other']
			)
		);
		$impact['tax_detail']['total_state_local']['direct'] = $impact['tax_detail']['property']['direct'] + $impact['tax_detail']['other']['direct'];
		$impact['tax_detail']['total_state_local']['total'] = $impact['tax_detail']['property']['total'] + $impact['tax_detail']['other']['total'];
		if (! $move_sales_tax_into_other) {
			$impact['tax_detail']['total_state_local']['direct'] += $impact['tax_detail']['sales']['direct'];
			$impact['tax_detail']['total_state_local']['total'] += $impact['tax_detail']['sales']['total'];
		}
		
		// Format values for output
		$precision = 8;
		foreach ($multipliers as $name => $value) {
			if (! $value) {
				continue;
			}
			if ($name == 'annual_production') {
				$multipliers[$name] = '$'.number_format(round($value));
			} else {
				$multipliers[$name] = number_format($value, $precision);
			}
		}
		foreach ($impact as $name => $value) {
			if ($name != 'tax_detail') {
				$impact[$name] = number_format($impact[$name]);
				if ($name != 'direct_jobs' && $name != 'total_jobs') {
					$impact[$name] = '$'.$impact[$name];
				}
			}
		}
		foreach ($impact['tax_detail'] as $category => $category_array) {
			if (! $category_array) {
				continue;
			}
			foreach ($category_array as $scope => $value) {
				$impact['tax_detail'][$category][$scope] = '$'.number_format($impact['tax_detail'][$category][$scope]);
			}
		}
		
		return array($impact, $multipliers);
	}
	
	/* $params must be 
	 * 		compact('county_id', 'industry_id', 'option', 'annual_production')
	 *   OR compact('county_id', 'industry_id', 'option', 'employees') */
	public function getOutput($params) {
		extract($params);
		
		// Depending on the input method chosen, get impact values and multipliers
		if ($option == 'a') {
			list($impact, $multipliers) = $this->tifCalculateByProduction(compact('county_id', 'industry_id', 'annual_production'));
		} elseif ($option == 'b') {
			list($impact, $multipliers) = $this->tifCalculateByEmployees(compact('county_id', 'industry_id', 'employees'));
		}
		
		// Start arranging the output
		$output = array();
		
		// Arrange the multipliers section
		$output['multipliers'] = array(
			'title' => 'Economic Multipliers',
			'rows' => array(
				'output' => array('name' => 'Output per dollar of direct output'),
				'direct_jobs' => array('name' => 'Direct jobs per dollar of direct output'),
				'total_jobs' => array('name' => 'Total jobs '.($option == 'production' ? 'per dollar of direct output' : 'per direct job')),
				'direct_payroll' => array('name' => 'Direct payroll per dollar of direct output'),
				'payroll' => array('name' => 'Total payroll '.($option == 'production' ? 'per dollar of direct output' : 'per dollar of direct payroll')),
				'direct_ibt' => array(
					'name' => 'Direct effect of IBT per dollar of direct output',
					'help' => 'IBT: Indirect business taxes',
				),
				'ibt' => array(
					'name' => 'Total effect of IBT per dollar of direct output',
					'help' => 'IBT: Indirect business taxes',
				)
			),
			'footnote' => 'Multipliers calculated from state and county input-output tables.'
		);
		// Add the multiplier values
		foreach ($output['multipliers']['rows'] as $type => $info) {
			if (isset($multipliers[$type])) {
				$output['multipliers']['rows'][$type]['value'] = $multipliers[$type];
			}
		}
		
		// Arrange the direct impact section
		$output['direct_impact'] = array(
			'title' => 'Direct Impact',
			'rows' => array(
				'annual_production' => array('name' => 'Annual production (direct output)'),
				'direct_jobs' => array('name' => 'Direct jobs', 'help' => 'This value is rounded to the nearest whole number of jobs'),
				'average_earnings' => array('name' => 'Average annual earnings per job'),
				'annual_production_per_worker' => array('name' => 'Annual production per worker'),
				'direct_payroll' => array('name' => 'Direct payroll, including benefits'),
				'direct_ibt' => array(
					'name' => 'Direct effect of IBT',
					'help' => 'IBT: Indirect business taxes'	
				)
			)
		);
		// Add the direct impact values
		foreach ($output['direct_impact']['rows'] as $type => $info) {
			if (isset($impact[$type])) {
				$output['direct_impact']['rows'][$type]['value'] = $impact[$type];
			}
		}
		
		// Arrange the total impact section
		$output['total_impact'] = array(
			'title' => 'Total Impact',
			'rows' => array(
				'output' => array(
					'name' => 'Output or sales impact in the county',
					'help' => 'Output: Total domestic or regional production activities plus values of intermediate inputs and imported inputs',
				),
				'total_jobs' => array('name' => 'Total jobs in the county', 'help' => 'This value is rounded to the nearest whole number of jobs'),
				'payroll' => array('name' => 'Payroll in the county (from county average data)'),
				'ibt' => array(
					'name' => 'IBT in the county',
					'help' => 'IBT: Indirect business taxes',
				)
			)
		);
		// Add the total impact values
		foreach ($output['total_impact']['rows'] as $type => $info) {
			if (isset($impact[$type])) {
				$output['total_impact']['rows'][$type]['value'] = $impact[$type];
			}
		}
		
		// Set the value names and help text for the tax impact section
		$impact['tax_detail']['federal']['name'] = 'Federal Government';
		$impact['tax_detail']['federal']['help'] = 'Includes custom duty, excise taxes, and other fines and fees';
		$impact['tax_detail']['total_state_local']['name'] = 'State and Local Governments';
		$impact['tax_detail']['total_state_local']['help'] = 'Includes business motor vehicle license tax, property tax, sales tax, severance tax, and other fines and fees';
		$impact['tax_detail']['sales']['name'] = '&nbsp; &nbsp; &nbsp; Sales Tax';
		$impact['tax_detail']['property']['name'] = '&nbsp; &nbsp; &nbsp; Property Tax';
		$impact['tax_detail']['other']['name'] = '&nbsp; &nbsp; &nbsp; Other Taxes';
		$impact['tax_detail']['other']['help'] = 'State and local governments\' indirect business taxes'; 
		
		// Set the order that tax rows will be displayed in
		$taxesOrder = array('federal', 'total_state_local', 'sales', 'property', 'other');

		return compact('impact', 'output', 'taxesOrder');
	}
	
	// Called by CalculatorsController::tif_basic()
	// $input is expected to be compact('county_id', 'industry_id', 'option', 'amount') 
	public function getOutputBasic($input) {
		extract($input);
		if ($option == 'a') {
			$annual_production = $amount;
			$filtered_input = compact('county_id', 'industry_id', 'option', 'annual_production');
		} elseif ($option == 'b') {
			$employees = $amount;
			$filtered_input = compact('county_id', 'industry_id', 'option', 'employees');
		}
		return $this->getOutput($filtered_input);
	}
}