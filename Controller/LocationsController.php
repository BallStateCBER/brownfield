<?php
// This controller is exclusively used for requestActions that return values
class LocationsController extends AppController {
	public $name = 'Locations';
		
	public function getCountyID($county, $state) {
		return $this->Location->getCountyID($county, $state);
	}
	
	public function getCountiesFull($state) {
		return $this->Location->getCountiesFull($state);
	}
	
	public function getCountiesSimplified($state) {
		return $this->Location->getCountiesSimplified($state);
	}
	
	public function getCountyProfilesLink($county, $state) {
		return $this->Location->getCountyProfilesLink($county, $state);
	}
	
	public function getCountysSchoolCorps($county_id) {
		return $this->Location->getCountysSchoolCorps($county_id);
	}
	
	public function getLocationName($loc_type_id, $loc_id, $append = false) {
		return $this->Location->getLocationName($loc_type_id, $loc_id, $append = false);
	}
	
	public function simplify($location_name) {
		return $this->Location->simplify($location_name);
	}
	
	public function getStateID($state_name) {
		return $this->Location->getStateID($state_name);
	}
	
	public function getStateFullName($state) {
		return $this->Location->getStateFullName($state);
	}
	
	public function setReportLocationNames($locations) {
		return $this->Location->setReportLocationNames($locations);
	}
	
	public function getReportLocationNames($locations) {
		return $this->Location->getReportLocationNames($locations);
	}
	
	public function getCountySimplifiedName($county, $state) {
		return $this->Location->getCountySimplifiedName($county, $state);
	}
	
	public function getCountyFullName($county, $state, $append = false) {
		return $this->Location->getCountyFullName($county, $state, $append);
	}
	
	public function getStateAbbreviation($state) {
		return $this->Location->getStateAbbreviation($state);
	}
	
	public function getStateAbbreviations($lowercase = false) {
		return $this->Location->getStateAbbreviations($lowercase);
	}
	
	public function getStatesAndAbbreviations() {
		return $this->Location->getStatesAndAbbreviations();
	}
	
	public function setCountySimplifiedNames() {
		$this->Location->setCountySimplifiedNames();
		$this->render('/');
	}
}