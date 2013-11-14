<?php
/**
 * Static content controller.
 *
 * This file will render views from views/pages/
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.Controller
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('AppController', 'Controller');

/**
 * Static content controller
 *
 * Override this controller by placing a copy in controllers directory of an application
 *
 * @package       app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers/pages-controller.html
 */
class PagesController extends AppController {
	public $name = 'Pages';
	public $helpers = array('Html', 'Session');
	public $uses = array();
	
	public function home() {
		$this->loadModel('Report');
		$this->set(array(
			'title_for_layout' => '',
			'use_shadowbox' => true,
			'topics' => $this->Report->getTopicList()
		));
	}
	
	public function select_tab($county = null) {
		$this->set(array(
			'county' => $county, 
			'title_for_layout' => null
		));
	}

	// Redirects /:state to /:state/:first-county/:first-topic
	public function select_county($state) {
		$this->loadModel('Location');
		$state_id = $this->Location->getStateID($state);
		list($county_id, $county_name) = $this->Location->getFirstCounty($state_id);
		$state_abbrev = strtolower($this->Location->getStateAbbreviation($state));
		$this->redirect("/$state_abbrev/$county_name/population");
	}

	public function select_chart($state, $county) {
		$this->redirect("/$state/$county/population");
	}
	
	/* Accepts $state and $county as either strings (simplified names) or integers (ID #s)
	 * If no topic is provided, will default to the first topic listed in ReportsController::getTopicList() */
	public function topic($state = null) {
		// Collect parameters from the URL
		$path_parts = explode('/', $this->request->params['url']['url']);
		$county = isset($path_parts[1]) ? $path_parts[1] : null;
		$topic = (isset($path_parts[2]) && ! empty($path_parts[2])) ? $path_parts[2] : null;

		// Validate county
		$state_id = is_numeric($state) ? $state : $this->Location->getStateID($state);
		$state_full_name = $this->Location->getStateFullName($state_id);
		if (! $this->Location->isCountyInState($county, $state_id)) {
			$this->Flash->error("Error: County '$county[1]' not found in $state_full_name.");
			$this->redirect('/');
		}

		// Redirect to the first topic if none is specified
		$this->loadModel('Report');
		if (! $topic) {
			$this->redirect('/'.$path_parts[0].'/'.$path_parts[1].'/'.$this->Report->getFirstTopic());
		}

		// Validate topic
		if ($topic != 'all_charts' && ! $this->Report->isTopic($topic)) {
			$this->set(array(
				'invalidTopicSelected' => $topic
			));
			$this->render('/errors/invalid_topic');
			return false;
		}

		// Get human-readable state, county, and topic names to put into this page's title
		$state_abbreviation = $this->Location->getStateAbbreviation($state_id);
		$county_id = is_numeric($county) ? $county : $this->Location->getCountyID($county, $state_id);
		$county_full_name = $this->Location->getCountyFullName($county_id, $state_id);

		// Set variables used in either topic page or all-charts page
		$this->set(array(
			'county_id' => $county_id,
			'state_id' => $state_id,
			'state_abbreviation' => strtolower($state_abbreviation),
			'county_name_simplified' => $this->Location->simplify($county_full_name)
		));

		// 'All charts' page
		if ($topic == 'all_charts') {
			$this->set(array(
				'title_for_layout' => "$county_full_name, $state_abbreviation - All Charts",
				'topics' => $this->Report->getTopicList(true) // Categorized topic list
			));
			return $this->render('all_charts');
		}

		// Set variables and render topic page
		$this->loadModel('ChartDescription');
		$topic_full_name = $this->Report->getTopicFullName($topic);
		$this->set(array(
			'selected_topic' => $topic,
			'description' => $this->ChartDescription->getDescription($topic),
			'title_for_layout' => "$county_full_name, $state_abbreviation $topic_full_name",
			'topic_full_name' => $topic_full_name,
			'chart_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('chart', $topic, $state_id, $county_id))
			),
			'table_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('table', $topic, $state_id, $county_id))
			),
			'csv_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('csv', $topic, $state_id, $county_id))
			),
			'excel5_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('excel5', $topic, $state_id, $county_id))
			),
			'excel2007_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('excel2007', $topic, $state_id, $county_id))
			),
			'source_availability' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'getStatus'),
				array('pass' => array('source', $topic, $state_id, $county_id))
			),
			'sources' => $this->requestAction(
				array('controller' => 'reports', 'action' => 'switchboard'),
				array('pass' => array('source', $topic, $state_id, $county_id))
			)
		));
	}

	public function grants_awarded() {
		$this->loadModel('Datum');
		$this->set(array(
			'grants' => $this->Datum->getGrantsAwarded(),
			'title_for_layout' => 'Brownfield Grants Awarded in Indiana'
		));
	}

	public function resources() {
		$this->set('title_for_layout', 'Additional Resources');
	}
	
	public function clear_cache() {
		Cache::clear();
		clearCache();
		$this->Flash->success('Cache cleared');
		$this->render('home');
	}

	public function testimonials() {
		$this->set('title_for_layout', 'Testimonials');
	}
}