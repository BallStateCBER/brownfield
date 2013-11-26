<?php
class CalculatorsController extends AppController {
	public $components = array('RequestHandler');

	public function getLocalIndustries($county) {
		$this->set(array(
			'industries' => $this->Calculator->getLocalIndustries($county)
		));
		$this->layout = 'ajax';
	}

	public function getNaicsIndustries() {
		return $this->Calculator->getNaicsIndustries();
	}

	public function tif() {
		$this->loadModel('Location');
		$this->set(array(
			'title_for_layout' => 'TIF-in-a-Box',
			'naics_industries' => $this->Calculator->getNaicsIndustries(),
			'counties' => $this->Location->getCountiesFull(14)
		));
	}

	public function tif_basic() {
		// Set all the variables according to user input or their default values
		$this->set('title_for_layout', 'Basic TIF Calculator');
		$selected_state = 14;
		$selected_county = isset($_POST['county_id']) ? $_POST['county_id'] : null;
		$selected_industry = isset($_POST['industry_id']) ? $_POST['industry_id'] : null;
		$selected_option = isset($_POST['option']) ? $_POST['option'] : null;
		$amount = isset($_POST['amount']) ? preg_replace('/\D/', '', $_POST['amount']) : null;
		$counties = $this->Calculator->getCounties($selected_state);
		$naics_industries = $this->Calculator->getNaicsIndustries();
		
		/* These variables are prefixed with calc_ to avoid conflicts with other 
		 * similarly-named variables used in the sidebar. */
		$this->set(array(
			'calc_selected_state' => $selected_state, 
			'calc_selected_county' => $selected_county, 
			'calc_selected_industry' => $selected_industry, 
			'calc_selected_option' => $selected_option, 
			'calc_amount' => $amount, 
			'calc_counties' => $counties,
			'calc_naics_industries' => $naics_industries,
		));
				
		// No input? Then no output 
		if (! isset($_POST['input_given'])) {
			return;
		}
		
		// Pass an array of error messages
		$this->loadModel('Location');
		$error_messages = array();
		if (! $selected_state) {
			$error_messages[] = 'Please select a state.';
		} elseif (! $selected_county) {
			$error_messages[] = 'Please select a county.';
		} elseif (! $selected_industry) {
			$error_messages[] = 'Please select the industry that this company falls within.';
		} elseif ($selected_county && $selected_industry) {
			$local_industries = $this->Calculator->getLocalIndustries($selected_county);
			if (! in_array($selected_industry, $local_industries)) {
				$industry_name = $naics_industries[$selected_industry];
				$county_name = $this->Location->getCountyFullName($selected_county, $selected_state, true);
				$error_messages[] = "Sorry, but we don't have enough information about the <em>$industry_name</em> industry in $county_name to process your request.";
			} elseif (! $selected_option) {
				$error_messages[] = 'Please select either the \'annual production\' or \'annual employees\' input option.';
			} elseif (! $amount) {
				if ($selected_option == 'a') {
					$error_messages[] = 'Please enter this company\'s annual production.';
				} elseif ($selected_option == 'b') {
					$error_messages[] = 'Please enter this company\'s annual number of employees.';
				}
			}
		}
		
		// If no errors, then set the variables used to generate the output table
		if (empty($error_messages)) {
			$this->set(array(
				'calc_output_vars' => $this->Calculator->getOutputBasic(
					array(
						'county_id' => $selected_county, 
						'industry_id' => $selected_industry, 
						'option' => $selected_option, 
						'amount' => $amount
					)
				),
				'county_id' => $selected_county, 
				'industry_id' => $selected_industry, 
				'option' => $selected_option, 
				'annual_production' => $selected_option == 'a' ? $amount : null,
				'employees' => $selected_option == 'a' ? null: $amount
			));
		
		// Otherwise, let them know what went wrong 
		} else {
			$this->set(array(
				'calc_error_messages' => $error_messages
			));
		}
	}
	
	// This is the action that the calculator (full version) invokes when its form is submitted 
	public function tif_output() {
		$county_id = $this->request->params['named']['county_id'];
		$industry_id = $this->request->params['named']['industry_id'];
		$option = $this->request->params['named']['option'];
		if ($option == 'a') {
			$annual_production = $this->request->params['named']['annual_production'];
			$input_vars = compact('county_id', 'industry_id', 'option', 'annual_production');
		} elseif ($option == 'b') {
			$employees = $this->request->params['named']['employees'];
			$input_vars = compact('county_id', 'industry_id', 'option', 'employees');
		}
		
		// Used to create output table
		// Contents: $impact, $output, $taxesOrder
		$this->set($this->Calculator->getOutput($input_vars));
		
		// Used to create "download as CSV file" link (which needs to duplicate the user's input)
		// Contents: $county_id, $industry_id, $option, $annual_production and/or $employees
		//			 May contain other irrelevant named parameters.
		$this->set($this->request->params['named']);
	}

	// Renders a CSV file (or debug page)
	public function tif_output_csv() {
		extract($this->request->params['named']);
		$state_id = 14;
		$industries = $this->Calculator->getNaicsIndustries();
				
		if ($this->request->params['named']['option'] == 'a') {
			$annual_production = $this->request->params['named']['annual_production'];
			$input_vars = compact('county_id', 'industry_id', 'option', 'annual_production');
		} else {
			$employees = $this->request->params['named']['employees'];
			$input_vars = compact('county_id', 'industry_id', 'option', 'employees');
		}
		
		$this->set($input_vars);
		
		// Used to create output table. Contents: $impact, $output, $taxesOrder
		$this->set($this->Calculator->getOutput($input_vars));
		$this->loadModel('Location');
		$county_id = $this->request->params['named']['county_id'];
		$county_name = $this->Location->getCountyFullName($county_id, $state_id, false);
		$industry_id = $this->request->params['named']['industry_id'];
		$this->set(array(
			'county_name' => $county_name,
			'state_name' => 'Indiana',
			'industry_name' => $industries[$industry_id],
			'filename' => 'econ_impact_'.date('ymd_His')
		));
		if (isset($_GET['debug'])) {
			$this->layout = 'ajax';
		} else {
			$this->layout = 'reports/csv';
		}
	}
}