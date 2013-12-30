<?php
/* Report error codes:
 *  0: No error
 *  1: Report type not supported for this topic
 *  2: Required data not available for report
 *  3: Error caching
 *  4: Unknown topic
 *  5: Error generating report
 *
 *  Note:
 *  	routes.php needs to be kept aware of the valid report types (chart|table|csv|source|excel5|excel2007)
 *  	so that /:type/:topic/:state/:county paths can be properly routed to ReportsController::switchboard()
 *
 *  How this works:
 *  switchboard($type, $topic, $state, $county)
 *  -	TASK: Populate RSO (Report subclass object) (RSO being something like $this->ChartReport or $this->CsvReport, referenced by $this->{$this->report_subclass})
 *  		__loadFromCache() is checked first for RSO (if $this->cache_reports == TRUE)
 *  		If it is not found in the cache (or caching is disabled), __generateReport() does the following:
 *				Call ReportsController::{$topic}()
 *				Which sets basic information about the topic, then calls
 *				RSO::getOutput($topic), which populates the RSO with data
 *				Then if $this->cache_reports == TRUE, the serialized RSO is cached
 *	-	TASK: Output RSO
 *		If an error has been set,
 *			redirects the user to an error page
 *			OR if requestAction() is used, returns FALSE
 *  	If no error, view($type) is called, which uses the populated RSO to either
 *  		return a value if $this->params['requested']
 *  		OR set view variables,	set the layout, and render a view file
 */
class ReportsController extends AppController {
	public $name = 'Reports';
	public $components = array(
		'RequestHandler'
	);
	public $helpers = array(
		'GoogleChart'
	);
	public $uses = array(
		'Report', 
		'Location'
	);

	public $type; //chart, table, source, csv, etc.
	public $report_subclass; // ChartReport, TableReport, etc.
	public $topic;
	public $state_id;
	public $county_id;

	public $cache_reports = false;

	public function beforeFilter() {
		parent::beforeFilter();
		$this->cache_reports = Configure::read('cache_reports');
	}

	// These are the report subtypes that have topic methods, e.g. ChartReport::population()
	public function __getReportSubclassesWithTopicMethods() {
		return array('ChartReport', 'TableReport', 'CsvReport', 'ExcelReport');
	}

	public function getTopicList($categorized = true) {
		return $this->Report->getTopicList($categorized);
	}

	public function isTopic($topic) {
		return $this->Report->isTopic($topic);
	}

	public function is_caching_enabled() {
		return $this->cache_reports;
	}

	// Used for determining the filename (excluding extension) for spreadsheet downloads
	public function __getFilename() {
		$state = strtolower($this->Location->getStateAbbreviation($this->state_id));
		$county = $this->Location->getCountySimplifiedName($this->county_id, $this->state_id);
		return "{$state}_{$county}_{$this->topic}";
	}

	// Used by CSV reports, which include a list of sources
	public function __getSources() {
		// Fake a requestAction
		$requested_placeholder = isset($this->params['requested']) ? $this->params['requested'] : null;
		$this->params['requested'] = true;

		$sources = $this->switchboard('source', $this->topic, $this->state_id, $this->county_id);

		// Return the 'requested' parameter to its original value
		$this->params['requested'] = $requested_placeholder;

		return $sources;
	}

	/* Checks cache for the report and if none is found, generates/caches.
	 * Returns 0 if a report exists, or a non-zero error code if it doesn't. */
	public function getStatus($type, $topic, $state, $county) {
		// Set required variables and set up $this->{$this->report_subclass} object
		$this->type = $type;
		$this->topic = $topic;
		$this->state_id = is_numeric($state) ? $state : $this->Location->getStateID($state);
		$this->county_id = is_numeric($county) ? $county : $this->Location->getCountyID($county, $this->state_id);
		$this->__initializeSubclass();
		$this->__loadHelpersAndVendors($this->type);

		// Load the report from the cache or generate/cache it if it's not found in the cache
		if (! $this->__loadFromCache($this->type, $this->topic, $this->county_id)) {
			$this->__generateReport();
		}

		if (isset($this->params['requested'])) {
			return $this->{$this->report_subclass}->error;
		}
		$this->layout = 'ajax';
		$verbose_error_codes = array(
			0 => 'No error',
			1 => ucwords($type).' not applicable for this topic',
			2 => 'Required data not available',
			3 => 'Error caching',
			4 => 'Unknown topic',
			5 => isset($this->{$this->report_subclass}->error_message) ? $this->{$this->report_subclass}->error_message : 'Unknown error'
		);
		$error_code = $this->{$this->report_subclass}->error;
		if ($error_code) {
			$message = $error_code.': '.$verbose_error_codes[$error_code];
		} else {
			$message = '0: No error';
		}
		$this->set('message', $message);
		$this->render('/Pages/message');
	}

	/* Takes the serialized and cached data and places it where view() expects it to be.
	 * Returns TRUE if a cached report (or cached error code) was found, FALSE otherwise. */
	public function __loadFromCache($type, $topic, $county_id) {
		// If report caching is disabled
		if (! $this->cache_reports) {
			return false;
		}

		// If the report has not yet been cached
		if (! $cache_result = $this->Report->getCached(compact('type', 'topic', 'county_id'))) {
			return false;
		}

		/* Note that $this->{$this->report_subclass}->error will now be populated
		 * with 0 or an error code reflecting any 'missing data',
		 * 'report type not supported for this topic', etc. errors */
		if ($cache_result['data']) {

			// Populate the report's attributes with the cached variables
			$report_attributes = unserialize($cache_result['data']);
			foreach ($report_attributes as $var => $val) {
				$this->{$this->report_subclass}->$var = $val;
				if (isset($_GET['debug'])) {
					echo "<hr />Contents of $var:<pre>".print_r($val, true).'</pre>';
				}
			}
		}
		if ($cache_result['error']) {
			$this->{$this->report_subclass}->error = $cache_result['error'];
		}
		return true;
	}

	public function __generateReport() {
		// Make sure method matching name of topic exists in this controller
		if (! method_exists($this, $this->topic)) {
			$this->{$this->report_subclass}->error = 4; // Unknown topic
			return;
		}

		// Make sure the report subtype has the same topic method (e.g. ChartReport::population()) if it is expected to
		$report_subclasses_with_topic_methods = $this->__getReportSubclassesWithTopicMethods();
		$check_for_topic_method = in_array($this->report_subclass, $report_subclasses_with_topic_methods);
		$topic_method_exists = method_exists($this->{$this->report_subclass}, $this->topic);
		if ($check_for_topic_method && ! $topic_method_exists) {

			// Selected report type is not supported for this topic (but other report types might be)
			$this->{$this->report_subclass}->error = 1;

		// Call the topic method, which sets some basic info
		// and then calls the report subtype's getOutput() method
		} else {
			$this->{$this->topic}($this->county_id, $this->type);
		}

		if ($this->cache_reports) {
			$this->Report->cache($this->{$this->report_subclass}, $this->type, $this->topic, $this->county_id);
		}
	}

	// Requires that $this->type, $this->topic, $this->state_id, $this->county_id all be set
	public function __initializeSubclass() {

		// Determine the name of the type-specific model used for this report
		switch ($this->type) {
			case 'excel5':		// Two types of Excel reports are
			case 'excel2007':	// derived from the ExcelReport model
				$this->report_subclass = 'ExcelReport';
				break;
			case 'source':
			case 'chart':
			case 'svg_chart':
			case 'table':
			case 'csv':
				$this->report_subclass = Inflector::camelize($this->type).'Report';
				break;
			default:
				// Exit immediately if using a special outlier type
				return;
		}

		// Set up $this->FooReport and all of its necessary attributes
		Controller::loadModel($this->report_subclass);

		/* Reset report
		 *	  Without doing this, on the 'all charts' page, each ChartReport's
		 *	  attributes carry over to subsequent ChartReports on the same page */
		$this->{$this->report_subclass} = new $this->report_subclass;

		// Set needed attributes
		$this->{$this->report_subclass}->Datum = ClassRegistry::init('Datum');
		$this->{$this->report_subclass}->Location = $this->Location;
		$this->{$this->report_subclass}->topic = $this->topic;
		$this->{$this->report_subclass}->state_id = $this->state_id;
		$this->{$this->report_subclass}->county_id = $this->county_id;

		// Special preparation
		switch ($this->type) {
			case 'excel5':
			case 'excel2007':
				// Make sure ExcelReport knows what variety of Excel is being requested
				$this->ExcelReport->excel_type = $this->type;
		}
	}

	public function __loadHelpersAndVendors($type) {
		// Load helpers and vendor files specific to the Report type
		switch ($type) {
			case 'chart':
				$this->helpers[] = 'GoogleChart';
				App::import('Vendor', 'GoogleChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleChart.php'));
				App::import('Vendor', 'GooglePieChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GooglePieChart.php'));
				App::import('Vendor', 'GoogleBarChart', array('file' => 'googlechartphplib'.DS.'lib'.DS.'GoogleBarChart.php'));
				App::import('Vendor', 'GoogleChartTextMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartTextMarker.php'));
				App::import('Vendor', 'GoogleChartRangeMarker', array('file' => 'googlechartphplib'.DS.'lib'.DS.'markers'.DS.'GoogleChartRangeMarker.php'));
				break;
			case 'excel2007':
				App::import('Vendor','PHPExcel', array('file' => 'excel/PHPExcel.php'));
				App::import('Vendor','PHPExcelWriter', array('file' => "excel/PHPExcel/Writer/Excel2007.php"));
				App::import('Vendor','PHPExcelAdvancedValueBinder',array('file' => 'excel/PHPExcel/Cell/AdvancedValueBinder.php'));
				break;
			case 'excel5':
				App::import('Vendor','PHPExcel', array('file' => 'excel/PHPExcel.php'));
				App::import('Vendor','PHPExcelWriter', array('file' => "excel/PHPExcel/Writer/Excel5.php"));
				App::import('Vendor','PHPExcelAdvancedValueBinder',array('file' => 'excel/PHPExcel/Cell/AdvancedValueBinder.php'));
				break;
		}
	}

	public function __setDataCategories($data_categories) {
		$this->{$this->report_subclass}->data_categories = $data_categories;
	}

	/* $locations is an array of $location arrays.
	 * Each $location array contains the following:
	 * 		location type ID (required)
	 * 		location ID (optional, the selected county, state, and country are assumed if blank)
	 * 		location name (optional, will be populated with the default name if no value is provided) */
	public function __setLocations($locations) {
		foreach ($locations as $lkey => $location) {
			// If location ID is not set
			if (! isset($location[1])) {
				switch ($location[0]) {
					case 2: // county
						$location[1] = $this->{$this->report_subclass}->county_id;
						break;
					case 3:	// state
						$location[1] = $this->Location->getStateIDFromCountyID($this->{$this->report_subclass}->county_id);
						break;
					case 4: // country
						$location[1] = 1;
						break;
				}
			}
			// If location name is not set
			if (! isset($location[2])) {
				switch ($location[0]) {
					case 3:
						$location[2] = $this->Location->getStateFullName($location[1]);
						break;
					case 4:
						$location[2] = 'United States';
						break;
					default:
						$location[2] = $this->Location->getLocationName($location[0], $location[1], true);
				}
			}
			$locations[$lkey] = $location;
		}
		$this->{$this->report_subclass}->locations = $locations;
	}

	public function __setDates($dates) {
		if (empty($dates)) {
			return;
		}
		if (is_array($dates)) {
			$this->{$this->report_subclass}->dates = $dates;
		} else {
			$this->{$this->report_subclass}->dates = array($dates);

			// The 'year' attribute is curretly being phased out in favor of just using 'dates'
			$this->{$this->report_subclass}->year = $dates;
		}
	}

	public function __getOutput() {
		return $this->{$this->report_subclass}->getOutput($this->topic);
	}

	// Renders an appropriate view (or returns a value if requestAction() is being used)
	public function switchboard($type, $topic, $state, $county) {
		// Allow manual override of $this->cache_reports value
		if (isset($_GET['reportcache'])) {
			$this->cache_reports = $_GET['reportcache'];
		}

		$this->type = $type;
		$this->topic = $topic;
		$this->state_id = is_numeric($state) ? $state : $this->Location->getStateID($state);
		$this->county_id = is_numeric($county) ? $county : $this->Location->getCountyID($county, $this->state_id);
		$this->__initializeSubclass();
		$this->__loadHelpersAndVendors($this->type);
		if (! $this->__loadFromCache($this->type, $this->topic, $this->county_id)) {
			$this->__generateReport();
		}

		// If an error has been set and a value is being requested, return FALSE
		if (isset($this->params['requested']) && $this->{$this->report_subclass}->error) {
			return false;
		}

		// Otherwise, if an error has been set, redirect to an error page
		switch ($this->{$this->report_subclass}->error) {
			case 1: // selected report type not supported for this topic
				// log this
				$this->redirect("/reports/invalid/$type/$this->county_id");
				break;
			case 2: // data not available for this location
				// log this
				$this->redirect("/reports/data_unavailable/$type/$this->county_id");
				break;
			case 3: // error caching
				// log this
				break;
			case 4: // unknown topic
				// log this
				$this->redirect("/reports/unknown/$type");
				break;
			case 5: // error generating report
				$error_message = "<p>There was an error generating this report. Please <a href=\"/contact\">contact us</a> if you need assistance.</p>";
				if ($this->{$this->report_subclass}->error_message) {
					$error_message .= '<p>Details: '.$this->{$this->report_subclass}->error_message.'</p>';
				} else {
					$error_message .= '<p>Unfortunately, no other details are available for this error.</p>';
				}
				$this->set(array('message' => $error_message));
				$this->render('/Pages/message');
			default:
				// no error
		}

		// Returning the value returned by view() is necessary for
		// the switchboard to be used in conjunction with requestAction()
		return $this->view($type);
	}

	// Assumes that $this->{$this->report_subclass} is already populated with the appropriate data
	public function view($type) {
		if ($type == 'chart') {
			$this->set(array('chart' => $this->ChartReport->gchart));
			if (isset($_GET['debug'])) {
				echo '<pre>Values: '.var_export($this->ChartReport->values, true).'</pre>';
				echo '<pre>$this->chart: '.print_r($this->ChartReport->gchart, true).'</pre>';
				$this->layout = 'default';
			} else {
				$this->layout = 'png';
			}
			$this->render('/Charts/view');
		} elseif ($type == 'table') {
			// This set of values is requested in views/elements/reports/table
			$table_vars = array(
				'title' => $this->TableReport->title,
				'columns' => $this->TableReport->columns,
				'table' => $this->TableReport->table,
				'footnote' => $this->TableReport->footnote,
				'options' => $this->TableReport->options,
				'error' => $this->TableReport->error
			);
			if (isset($this->params['named']['table_version']) && $this->params['named']['table_version'] == 2) {
				return $table_vars;
			}
			
			// Deprecated. Currently being phased out
			$this->set($table_vars);
			$this->render('/Tables/table');
		} elseif ($type == 'source') {
			// Arranged sources interpret line breaks as delimiters between array members
			$arranged_sources = $this->SourceReport->getArrangedSourceArray();

			// Sources can also be returned as an array via requestAction()
			if (isset($this->params['requested'])) {
				return $arranged_sources;
			} else {
				$this->set(array(
					'sources' => $arranged_sources
				));
				$this->render('source');
			}
		} elseif ($type == 'csv') {
			$this->set(array(
				'filename' => $this->__getFilename(),
				'sources' => $this->__getSources(),
				'title' => $this->CsvReport->title,
				'columns' => $this->CsvReport->columns,
				'table' => $this->CsvReport->table,
				'footnote' => $this->CsvReport->footnote,
				'options' => $this->CsvReport->options
			));
			if (isset($_GET['debug'])) {
				$this->layout = 'ajax';
			} else {
				$this->layout = 'reports/csv';
			}
			$this->render('csv');
		} elseif ($type == 'excel5' || $type == 'excel2007') {
			$this->set(array(
				'filename' => $this->__getFilename(),
				'mockup' => $this->ExcelReport->mockup,
				'output_type' => $this->ExcelReport->output_type,
				'values' => $this->ExcelReport->values,
				'objPHPExcel' => $this->ExcelReport->objPHPExcel
			));
			if (isset($_GET['debug'])) {
				$this->layout = 'ajax';
				//echo '<pre>'.print_r($this, true).'</pre>';
			} else {
				$this->layout = "reports/$type";
			}
			$this->render("excel");
		}
	}

	// Invalid report type for topic or invalid topic entirely
	public function invalid($type, $county_id) {
		if ($type == 'chart') { 
			$this->set(array(
				'image' => file_get_contents('../webroot/img/error_chart_not_found.png')
			));
			$this->layout = 'png';
			$this->render('/Charts/error');
		}
	}

	// Data not available for the selected county
	public function data_unavailable($type, $county_id) {
		if ($type == 'chart') {
			$this->set(array(
				'image' => file_get_contents('../webroot/img/county_data_unavailable.png')
			));
			$this->layout = 'png';
			$this->render('/Charts/error');
		}
	}

	// Unknown chart
	public function unknown($type) {
		if ($type == 'chart') {
			$this->set(array(
				'image' => file_get_contents('../webroot/img/error_chart_not_found.png')
			));
			$this->layout = 'png';
			$this->render('/Charts/error');
		}
	}

	public function populate_cache() {
		$this->cacheAction = null;
		$topics = $this->getTopicList(false);
		$states = $this->Location->getStateAbbreviations(true);
		$counties = array();
		foreach ($states as $state_id => $state) {
			$counties[$state_id] = $this->Location->getCountiesFull($state_id);
		}
		$this->set(compact('topics', 'counties', 'states'));
	}

	public function flush_cache() {
		$this->Report->query('TRUNCATE TABLE reports;');
		$this->set('message', 'Reports cache cleared.');
		$this->render('/Pages/message');
	}

	public function all_charts($state, $county) {
		if (is_numeric($state)) {
			$state_id =  $state;
			$state = strtolower($this->Location->getStateAbbreviation($state_id));
		} else {
			$state_id =  $this->Location->getStateID($state);
			$state = strtolower($this->Location->getStateAbbreviation($state));
		}
		if (is_numeric($county)) {
			$county_id =  $county;
			$county = $this->Location->getCountySimplifiedName($county_id, $state_id);
		} else {
			$county_id =  $this->Location->getCountyID($county, $state_id);
			$county = $this->Location->simplify($county);
		}
		$full_county_name = $this->Location->getCountyFullName($county_id, $state_id);
		$title_for_layout = $this->Location->getCountyFullName($county_id, $state_id, true);
		$this->set(compact('county_id', 'state_id', 'county', 'state', 'title_for_layout'));
	}





	/****** ****** Individual reports below ****** ******/



	public function population() {
		$this->__setDataCategories(array(
			'Population' => 1
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(null);
		return $this->__getOutput();
	}

	public function population_growth() {
		$this->__setDataCategories(array(
			'Population' => 1
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(array(1970, 1975, 1980, 1985, 1990, 1995, 2000, 2005, 2009));
		return $this->__getOutput();
	}

	public function density() {
		$this->__setDataCategories(array(
			'Population density' => 5721,
			'Housing units density' => 5722
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function population_age_breakdown() {
		$this->__setDataCategories(array(
			'75 years and older' => 5734,
			'60 to 74 years' => 5733,
			'45 to 59 years' => 5732,
			'25 to 44 years' => 5731,
			'15 to 24 years' => 5730,
			'5 to 14 years' => 5729,
			'Under 5 years' => 363
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function female_age_breakdown() {
		$this->__setDataCategories(array(
			'Young Women (< 15)' => 5738,
			'Women child bearing age (15 to 44)' => 5739,
			'Women (> 44)' => 5740
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function population_by_sex() {
		$this->__setDataCategories(array(
			'Male' => 361,
	 		'Female' => 362
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function dependency_ratios() {
		$this->__setDataCategories(array(
			'Total (< 15 and 65+)' => 5741,
	 		'Child (< age 15)' => 5742,
			'Elderly (65+)' => 5743
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function educational_attainment() {
		$this->__setDataCategories(array(
			'Less than 9th grade, percent' => 5748,
			'9th to 12th grade, no diploma, percent' => 468,
			'High school graduate or equivalent, percent' => 469,
			'Some college, no degree, percent' => 5750,
			'Associate degree, percent' => 472,
			'Bachelor\'s degree, percent' => 473,
			'Graduate or professional degree, percent' => 5752
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function graduation_rate() {
		$this->__setDataCategories(array(
			'High School Graduation Rate' => 5396
		));

		$county_id = $this->{$this->report_subclass}->county_id;
		$school_corps = $this->Location->getCountysSchoolCorps($county_id);
		$locations = array();
		foreach ($school_corps as $corp_name => $corp_id) {
			$locations[] = array(6, $corp_id, $corp_name);
		}
		$locations[] = array(3);
		$this->__setLocations($locations);
		$locations = $this->{$this->report_subclass}->locations;
		$location_count = count($locations);
		$state_name = $locations[$location_count - 1][2];
		$this->{$this->report_subclass}->locations[$location_count - 1][2] = "($state_name average)";

		$this->__setDates(2011);
		return $this->__getOutput();
	}

	public function household_size() {
		$this->__setDataCategories(array(
			'Average household size' => 348
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function households_with_minors() {
		$this->__setDataCategories(array(
			'Households with one or more people under 18 years' => 438
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}

	public function household_types_with_minors() {
		$this->__setDataCategories(array(
			'Married-couple family' => 5762,
			'Male householder, no wife present' => 5764,
			'Female householder, no husband present' => 5766,
	 	 	'Nonfamily households' => 5768,
			'Households with one or more people under 18 years' => 346 //Not part of chart, used for calculation
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}

	public function households_with_over_65() {
		$this->__setDataCategories(array(
			'Percent of households with one or more people 65 years and over' => 439
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}

	public function poverty() {
		$this->__setDataCategories(array(
			'Poverty Percent: All Ages' => 5686,
 	 		'Poverty Percent: Under 18' => 5688
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function lunches() {
		$this->__setDataCategories(array(
			'Free lunches' => 5780,
		 	'Reduced lunches' => 5781,
		 	'Free + reduced' => 5782,
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function disabled() {
		$this->__setDataCategories(array(
			'Percent of population disabled' => 5792
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}

	public function disabled_ages() {
		$this->__setDataCategories(array(
			'5 to 15 years' => 5800,
			'16 to 20 years' => 5801,
			'21 to 64 years' => 5802,
			'65 to 74 years' => 5803,
			'75+ years' => 5804
		));
		$this->__setLocations(array(
			array(2)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}

	public function share_of_establishments() {
		$this->__setDataCategories(array(
			'Percent of establishments: Logistics (Transportation, warehousing, wholsale, retail trade)' => 5813,
			'Percent of establishments: Manufacturing' => 5814
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2007);
		return $this->__getOutput();
	}

	public function employment_growth() {
		$this->__setDataCategories(array(
			'2007-2011' => 5991,
			'2002-2011' => 5992,
			'1997-2011' => 5993,
			'1992-2011' => 5994
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2011);
		return $this->__getOutput();
	}

	public function employment_trend() {
		$this->__setDataCategories(array(
			'Non-farm Employment' => 5815
		));
		$this->__setLocations(array(
			array(2)
		));
		$this->__setDates(null);
		return $this->__getOutput();
	}

	public function unemployment_rate() {
		$this->__setDataCategories(array(
			'Unemployment Rate' => 569
		));
		$this->__setLocations(array(
			array(2), array(3)
		));

		$this->__setDates(null);
		return $this->__getOutput();
	}

	public function personal_and_household_income() {
		$this->__setDataCategories(array(
			'Per capita personal income' => 47,
 	 		'Median household income' => 5689
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function income_inequality() {
		$this->__setDataCategories(array(
			'Income inequality' => 5668
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(array(1970, 1980, 1990, 2000));
		return $this->__getOutput();
	}

	public function birth_rate() {
		$this->__setDataCategories(array(
			'Birth Rate = Live Births per 1,000 population' => 5827
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2006);
		return $this->__getOutput();
	}

	public function birth_rate_by_age() {
		$this->__setDataCategories(array(
			'10 to 49' => 5840,
			'Under 18' => 5841,
			'18 to 39' => 5842,
			'40 to 49' => 5843
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2006);
		return $this->__getOutput();
	}

	public function birth_measures() {
		$this->__setDataCategories(array(
			'Low Birthweight' => 5844, //(less than 2,500 grams)
			'Very Low Birthweight' => 5845, //(less than 1,500 grams)
			'< 37 Weeks Gestation' => 5846,
			'Prenatal Care, 1st Trimester' => 5847,
			'Mother Unmarried' => 5848
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2006);
		return $this->__getOutput();
	}

	public function fertility_rates() {
		$this->__setDataCategories(array(
			'General' => 5849,
			'Total' => 5850
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2006);
		return $this->__getOutput();
	}

	public function deaths_by_sex() {
		$this->__setDataCategories(array(
			'Male' => 5856, // percent of deaths
			'Female' => 5857 // percent of deaths
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2007);
		return $this->__getOutput();
	}

	public function death_rate() {
		$this->__setDataCategories(array(
			'All causes: Death rate, age-adjusted' => 5852
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2007);
		return $this->__getOutput();
	}

	public function infant_mortality() {
		$this->__setDataCategories(array(
			'Infant death rate per 1,000 live births' => 5908
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2007);
		return $this->__getOutput();
	}

	public function life_expectancy() {
		$this->__setDataCategories(array(
			'Average life expectancy' => 5995
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2007);
		$this->{$this->report_subclass}->years_label = '2007';
		return $this->__getOutput();
	}

	public function years_of_potential_life_lost() {
		$this->__setDataCategories(array(
			'Years of potential life lost before age 75 (2006-2008)' => 5996
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2008);
		$this->{$this->report_subclass}->years_label = '2006-2008';
		return $this->__getOutput();
	}

	public function self_rated_poor_health() {
		$this->__setDataCategories(array(
			'Self-Rated Health Status: Fair/Poor (\'04-\'10)' => 5997 //percent
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2010);
		$this->{$this->report_subclass}->years_label = '2004-2010';
		return $this->__getOutput();
	}

	public function unhealthy_days() {
		$this->__setDataCategories(array(
			'Avg number of physically unhealthy days per month (2004 to 2010)' => 5999,
			'Avg number of mentally unhealthy days per month (2004 to 2010)' => 6000
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2010);
		$this->{$this->report_subclass}->years_label = '2004-2010';
		return $this->__getOutput();
	}

	public function death_rate_by_cause() {
		$this->__setDataCategories(array(
			'Malignant neoplasms' => 5868,	// All of these are the death rate, age adjusted
			'Diabetes mellitus' => 5872,
			'Alzheimer\'s disease' => 5876,
			'Major cardiovascular diseases' => 5880,
			'Influenza and pneumonia' => 5884,
			'Chronic lower respiratory diseases' => 5888,
			'Chronic liver disease and cirrhosis' => 5892,
			'Nephritis, nephrotic syndrome, and nephrosis' => 5896,
			'Motor vehicle accidents' => 5900
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2007);
		return $this->__getOutput();
	}

	public function cancer_death_and_incidence_rates() {
		$this->__setDataCategories(array(
			'Incidence Rate: All Cancers' => 6001,
			'Death Rate: All Cancers' => 6003,
			'Incidence Rate: Lung and Bronchus Cancer' => 6005,
			'Death Rate: Lung and Bronchus Cancer' => 6007
		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2008);
		$this->{$this->report_subclass}->years_label = '2004-2008';
		return $this->__getOutput();
	}

	public function lung_diseases() {
		$this->__setDataCategories(array(
			'Total Asthma' => 5834,
			'Pediatric Asthma' => 5835,
			'Adult Asthma' => 5836,
			'Chronic Bronchitis' => 5837,
			'Emphysema' => 5838
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2010);
		return $this->__getOutput();
	}

	public function federal_spending() {
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$county_id = $this->{$this->report_subclass}->locations[0][1];
		$state_id = $this->Location->getStateIDFromCountyID($county_id);
		$total_counties = $this->Location->getCountyCount($state_id);
		$this->__setDataCategories(array(
			'Total Federal Goverment Expenditure' => 5822,
 	 		'% WRT state' => 5823,
 	 		"County Rank out of $total_counties*" => 5824
		));
		$this->__setDates(2008);
		return $this->__getOutput();
	}

	public function public_assistance() {
		$this->__setDataCategories(array(
			'Women, Infants, and Children (WIC) Participants' => 5783,
		 	'Women, Infants, and Children (WIC) Participants Rank' => 5784,
		 	'Monthly Average of Families Receiving TANF' => 5785,
		 	'Monthly Average of Families Receiving TANF Rank' => 5786,
		 	'Monthly Average of Persons Issued Food Stamps (FY)' => 5787,
		 	'Monthly Average of Persons Issued Food Stamps (FY) Rank' => 5788
		));
		$this->__setLocations(array(
			array(2), array(3)
		));
		$this->__setDates(2008);
		return $this->__getOutput();
	}

	public function grants_awarded() {
		return $this->__getOutput();
	}

	/*
	public function __template() {
		$this->__setDataCategories(array(

		));
		$this->__setLocations(array(
			array(2), array(3), array(4)
		));
		$this->__setDates(2000);
		return $this->__getOutput();
	}
	*/
}