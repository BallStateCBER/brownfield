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
}
