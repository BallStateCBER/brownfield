<?php
/**
 * Application level Controller
 *
 * This file is application-wide controller file. You can put all
 * application-wide controller-related methods here.
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

App::uses('Controller', 'Controller');

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @package       app.Controller
 * @link http://book.cakephp.org/2.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller {
	public $helpers = array(
		'Js' => array('Jquery'),
		'Cache'
	);
	public $components = array(
		'DebugKit.Toolbar',
		'DataCenter.Flash',
		'Cookie',
		'RequestHandler'
	);
	
	public function beforeRender() {
		$this->setSidebarVars();
	}
	
	private function setSidebarVars() {
		$retval = array();
		$sidebar_vars = array();
		$this->loadModel('Location');
		$states = $this->Location->getStatesAndAbbreviations();
		$state_abbreviations = array();
		foreach ($states as $state) {
			$id = $state['Location']['id'];
			$ab = $state['Location']['abbreviation'];
			$state_abbreviations[$id] = strtolower($ab);
		}
		
		// Defaults
		$selected_state = $selected_county = $selected_tab = $selected_topic = $profiles_link = null;
		$state_id = 14;
		
		$this->loadModel('Report');
		$topics = $this->Report->getTopicList(true);
		
		// Figure out what page we're on and what should / shouldn't be shown 
		if (count($this->request->params['pass'])) {
			$passed_params = $this->request->params['pass'];
	
			// Is a state selected?
			if (isset($passed_params[0]) && in_array($passed_params[0], $state_abbreviations)) {
				$selected_state = $passed_params[0];
				$state_id = $this->Location->getStateID($selected_state);
			}
			
			// Is a county selected?
			if ($selected_state && isset($passed_params[1])) {
				$counties_simplified = $this->Location->getCountiesSimplified($selected_state);
				if (in_array($passed_params[1], $counties_simplified)) {
					$selected_county = $passed_params[1];
				}
			}
			
			// Is a topic selected?
			if ($selected_state && $selected_county && isset($passed_params[2])) {
				foreach ($topics as $tab => $topics_in_tab) {
					$simple_topic_names = array_keys($topics_in_tab);
					if (in_array($passed_params[2], $simple_topic_names)) {
						$selected_tab = $tab;	// Used to have the correct sub-menu in the sidebar already opened
						$selected_topic = $passed_params[2];
						break;
					}
				}
			}
			
			// Determine the sidebar mode, if not 'home'
			if (isset($selected_county)) {
				$sidebar_mode = 'county';
			} elseif ($passed_params[0] == 'tif' || $passed_params[0] == 'calculators') {
				$sidebar_mode = 'tif';
			}
		}
		
		// Sidebar is in 'county' mode when a state/county has been selected, 'tif' mode for the calculator, and 'home' mode all other times.
		if (! isset($sidebar_mode)) {
			$sidebar_mode = 'home';
		}
		
		// Get link to corresponding County Profiles page
		if (isset($selected_state) && isset($selected_county) && $profiles_url = $this->Location->getCountyProfilesLink($selected_county, $selected_state)) {
			$full_county_name = $this->Location->getCountyFullName($selected_county, $selected_state, true);
			$profiles_link = "<a href=\"$profiles_url\">CBER Profile of $full_county_name</a>";
		}
		
		$retval = compact(
			'states', 
			'state_abbreviations', 
			'selected_state', 
			'selected_county', 
			'selected_tab', 
			'selected_topic', 
			'sidebar_mode', 
			'topics',
			'profiles_link'
		);
		
		if ($sidebar_mode == 'tif') {
			$this->loadModel('Calculator');
			$retval['naics_industries'] = $this->Calculator->getNaicsIndustries();
			$retval['counties'] = $this->Location->getCountiesFull($state_id);
		}
		
		if ($sidebar_mode == 'county' || $sidebar_mode == 'home') {
			if (! isset($counties_simplified)) {
				$counties_simplified = $this->Location->getCountiesSimplified($state_id);
			}
			$retval['counties_full_names'] = $this->Location->getCountiesFull($state_id);
			$retval['counties_simplified'] = $counties_simplified;
		}
		
		$this->set($retval);
	}
}
