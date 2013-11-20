<?php
class Location extends AppModel {
	public $name = 'Location';
	public $useTable = false;
	
	public $simplified_name;
	public $full_name;
	public $location_type;
	public $id;
		
	public function getCountyID($county, $state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyID($county, $state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$result = array_search($this->simplify($county), $this->getCountiesSimplified($state_id));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyIDFromFips($fips) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyIDFromFips($fips)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;
			}
		}
		$this->setSource('counties');
		$result = $this->find('first', array(
			'conditions' => array('fips' => $fips),
			'contain' => false,
			'fields' => array('id')
		));
		$result = $result ? $result['Location']['id'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyIDFromName($county_name, $state_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyIDFromName($county_name, $state_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('counties');
		$result = $this->find('first', array(
			'conditions' => array('state_id' => $state_id, 'name' => $county_name),
			'contain' => false,
			'fields' => array('id')
		));
		$result = $result ? $result['Location']['id'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getSchoolCorpIdFromNumber($corp_number) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getSchoolCorpIdFromNumber($corp_number)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('school_corps');
		$result = $this->find('first', array(
			'conditions' => array('corp_no' => $corp_number),
			'contain' => false,
			'fields' => array('id')
		));
		$result = $result ? $result['Location']['id'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountiesFull($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountiesFull($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$this->setSource('counties');
		$this->displayField = 'name';
		$result = $this->find('list', array(
			'conditions' => array('state_id' => $state_id)
		));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountiesSimplified($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountiesSimplified($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$this->setSource('counties');
		$this->displayField = 'simplified';
		$result = $this->find('list', array(
			'conditions' => array('state_id' => $state_id)
		));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;	
	}
	
	public function getStateIDFromCountyID($county_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStateIDFromCountyID($county_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('counties');
		$result = $this->find('first', array(
			'conditions' => array('id' => $county_id),
			'contain' => false,
			'fields' => array('state_id')
		));
		$result = $result ? $result['Location']['state_id'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyProfilesLink($county_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyProfilesLink($county_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$result = 'http://profiles.cberdata.org/profiles/introduction/';
		$county_name = $this->getCountyNameFromID($county_id);
		$result .= Inflector::slug($county_name);
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}

	public function getCountySimplifiedName($county, $state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountySimplifiedName($county, $state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$county_id = is_numeric($county) ? $county : $this->getCountyID($county, $state_id);
		$result = $this->simplify($this->getCountyNameFromID($county_id));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyFullName($county, $state, $append = false) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyFullName($county, $state, $append)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$county_id = is_numeric($county) ? $county : $this->getCountyID($county, $state_id);
		$result = $this->getCountyNameFromID($county_id);
		if ($append) {
			$result .= ' '.$this->getCountyAnalogueWord($county_id, $state_id);	
		}
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyNameFromID($county_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyNameFromID($county_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('counties');
		$result = $this->find('first', array(
			'conditions' => array('id' => $county_id),
			'contain' => false,
			'fields' => array('name')
		));
		$result = $result ? $result['Location']['name'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	// Returns an array of [corporation name => corporation id] pairs for each school corporation in a given county
	public function getCountysSchoolCorps($county_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountysSchoolCorps($county_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('school_corps');
		$result = $this->find('all', array(
			'conditions' => array('county_id' => $county_id),
			'fields' => array('id', 'name'),
			'contain' => false,
			'order' => array('name ASC')
		));
		$corp_ids = array();
		foreach ($result as $location) {
			$corp_ids[$location['Location']['name']] = $location['Location']['id'];
		}
		$result = $corp_ids;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	/* $loc_type_id corresponds to one of the location types listed in $location_type_tables
	 * $loc_id is the ID number of county, state, etc.
	 * $append adds ' County', ' Parish', or ' Borough' to the end of the name if appropriate */
	public function getLocationName($loc_type_id, $loc_id, $append = false) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getLocationName($loc_type_id, $loc_id, $append)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$location_type_tables = array(
			1 => 'cities',	
			2 => 'counties', 
			3 => 'states',	
			4 => 'countries',
			5 => 'tax_districts', 
			6 => 'school_corps', 
			7 => 'townships'
		);
		$result = false;
		if (isset($location_type_tables[$loc_type_id])) {
			$this->setSource($location_type_tables[$loc_type_id]);
			$result = $this->find('first', array(
				'conditions' => array('id' => $loc_id),
				'fields' => array('name'),
				'contain' => false
			));
			if ($result) {
				if ($append) {
					$result['Location']['name'] .= ' '.$this->getCountyAnalogueWord($loc_id);	
				}
				$result = $result['Location']['name'];
			}
		}
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	/* Returns the capitalized word 'County' or a state-specific analogue
	 * if $county_id corresponds to Alaska or Louisiana. 
	 * Parameters accepted: ($county_id) or (null, $state_id) */
	public function getCountyAnalogueWord($county_id = null, $state_id = null) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyAnalogueWord($county_id, $state_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		if (! $state_id) {
			$state_id = $this->getStateIDFromCountyID($county_id);
		}
		switch ($state_id) {
			case 2:		// Alaska
				$result = 'Borough';
				break;
			case 18: 	// Louisiana
				$result = 'Parish';
				break;
			default:
				$result = 'County';
		}
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function simplify($location_name) {
		$location_name = trim($location_name);
		$location_name = strtolower($location_name);
		$location_name = str_replace(' ', '_', $location_name);
		return $location_name;	
	}
	
	/* Accepts the following varieties of $state:
	 *		Indiana		West Virginia
	 *		indiana		west_virginia
	 *		IN			WV
	 *		in			wv
	 *		18000 (5-digit FIPS value) 
	 */
	public function getStateID($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStateID($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('states');
		if (is_numeric($state)) {
			$conditions = array('fips' => $state);
		} else {
			$conditions = (strlen($state) == 2) ? 
				array('abbreviation' => strtoupper($state)) : 
				array('name' => $this->humanizeStateName($state));
		}
		$result = $this->find('first', array(
			'conditions' => $conditions,
			'fields' => array('id'),
			'contain' => false
		));
		$result = $result ? $result['Location']['id'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}

	/* Populates the third element of each array in $locations with the appropriate location name */
	public function setReportLocationNames($locations) {
		foreach ($locations as $lkey => $loc) {
			if (isset($locations[$lkey][2])) {				// Manually coded
				$label = $locations[$lkey][2];
			} elseif ($loc[0] == 3) { 						// State
				$label = $this->getStateFullName($loc[1]);
			} elseif ($loc[0] == 4 && $loc[1] == 1) {		// Country
				$label = 'United States';
			} elseif ($loc[0] == 2) {						// County
				$label = $this->getLocationName(2, $loc[1], true);
			}
			$locations[$lkey][2] = $label;
		}
		return $locations;
	}
	
	// Returns an array of location names, which should be the third elements of each array in $locations
	public function getReportLocationNames($locations) {
		$labels = array();
		foreach ($locations as $lkey => $loc) {
			$labels[] = $loc[2];
		}
		return $labels;	
	}
	
	/* Accepts the following varieties of $state:
	 *		Indiana		West Virginia
	 *		indiana		west_virginia
	 *		(state_id) 							*/
	public function getStateAbbreviation($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStateAbbreviation($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		if (! is_numeric($state) && strlen($state) == 2) {
			$result = $state;
		} else {
			$this->setSource('states');
			$conditions = is_numeric($state) ? 
				array('id' => $state) : 
				array('name' => $this->humanizeStateName($state)); 
			$result = $this->find('first', array(
				'conditions' => $conditions,
				'fields' => array('abbreviation'),
				'contain' => false
			));
			$result = $result ? $result['Location']['abbreviation'] : false;
		}
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	// Accepts either state abbreviation or ID as parameter
	public function getStateFullName($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStateFullName($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('states');
		$conditions = is_numeric($state) ? array('id' => $state) : array('abbreviation' => strtoupper($state));
		$result = $this->find('first', array(
			'conditions' => $conditions,
			'fields' => array('name'),
			'contain' => false
		));
		$result = $result ? $result['Location']['name'] : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function humanizeStateName($state_name) {
		// If state name is like 'indiana' or 'west_virginia', transform it to 'Indiana' or 'West Virginia'
		if (strpos($state_name, '_') || $state_name == strtolower($state_name)) {
			$state_name = Inflector::humanize($state_name);
		}
		return $state_name;
	}
	
	public function getStateAbbreviations($lowercase = false) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStateAbbreviations($lowercase)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('states');
		$this->displayField = 'abbreviation';
		$result = $this->find('list', array(
			'conditions' => array('supported' => 1)
		));
		if ($lowercase) {
			foreach ($result as $id => &$state) {
				$state = strtolower($state);
			}
		}
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getStatesAndAbbreviations() {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getStatesAndAbbreviations()";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('states');
		$result = $this->find('all', array(
			'conditions' => array('supported' => 1),
			'fields' => array('id', 'name', 'abbreviation'),
			'contain' => false,
			'order' => array('id ASC')
		));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	// Returns array(id, name) of the (alphabetically) first county for a state
	public function getFirstCounty($state_id) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getFirstCounty($state_id)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$this->setSource('counties');
		$result = $this->find('first', array(
			'conditions' => array('state_id' => $state_id),
			'fields' => array('id', 'name'),
			'order' => 'name ASC',
			'contain' => false
		));
		$result = $result ? array($result['Location']['id'], $result['Location']['name']) : false;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	/* Sets the 'simplified' field in the 'counties' table for each county
	 * using $this->simplify. Overwrites any existing values. Could possibly
	 * time out if the table has a crapton of rows. */ 
	public function setCountySimplifiedNames() {
		$this->setSource('counties');
		$results = $this->find('all', array(
			'contain' => false,
			'fields' => array('id', 'name')
		));
		$conversion = array(); // 'full name' => 'simplified name' pairs
		foreach ($results as $county) {
			$conversion[$county['Location']['name']] = $this->simplify($county['Location']['name']);			
		}
		foreach ($conversion as $name => $simplified) {
			$this->query("
				UPDATE counties
				SET simplified = '$simplified'
				WHERE name = '$name'
			");
		}
	}
	
	// Accepts a simplified county name and a state ID/abbreviation/name and returns TRUE or FALSE 
	public function isCountyInState($county_simplified, $state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "isCountyInState($county_simplified, $state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$this->setSource('counties');
		$result = $this->find('first', array(
			'contain' => false,
			'fields' => array('id'),
			'conditions' => array('state_id' => $state_id, 'simplified' => $county_simplified)
		));
		$result = (boolean) $result;
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;
	}
	
	public function getCountyCount($state) {
		if ($cache = Configure::read('cache_location_queries')) {
			$cache_key = "getCountyCount($state)";
			if ($cached = Cache::read($cache_key)) {
				return $cached;	
			}
		}
		$state_id = is_numeric($state) ? $state : $this->getStateID($state);
		$this->setSource('counties');
		$result = $this->find('count', array(
			'conditions' => array('state_id' => $state_id)
		));
		if ($cache) {
			Cache::write($cache_key, $result);
		}
		return $result;		
	}
}