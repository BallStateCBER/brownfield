<?php
App::Import ('model', 'Report');
class SourceReport extends Report {
	public $useTable = false;
	public $source = array(); // Holds the array of source strings
	
	public function getOutput($topic) {
		return $this->getSources();
	}
	
	public function getSources() {
		$location_conditions = array();
		foreach ($this->locations as $location_set) {
			$location_conditions[] = array(
				'Datum.loc_type_id' => $location_set[0],
				'Datum.loc_id' => $location_set[1]
			);
		}
		$conditions = array(
			'Datum.category_id' => array_values($this->data_categories),
			'OR' => $location_conditions
		);
		if ($this->year) {
			$conditions['Datum.survey_date'] = strlen($this->year) == 8 ? $this->year : $this->year.'0000';
		} elseif (! empty($this->dates)) {
			$dates = array();
			foreach ($this->dates as $date) {
				$dates[] = strlen($date) == 8 ? $date : $date.'0000';
			}
			$conditions['Datum.survey_date'] = $dates;
		}
		$result = $this->Datum->find('all', array(
			'fields' => array('DISTINCT Source.source'),
			'conditions' => $conditions
		));
		foreach ($result as $key => $source) {
			$this->source[] = $source['Source']['source']; // Sources source the sourcey source sourcilly
		}
	}
	
	/* This takes an array of sources (passed, or assumed to be $this->source), 
	 * and if a source has line breaks, separates each line into its own 
	 * array member. The end effect is that each separate line will be its own list
	 * item when the list of sources is displayed. */  
	public function getArrangedSourceArray($sources = null) {
		if (! $sources) {
			$sources = $this->source;
		}
		$sources_processed = array();
		foreach ($sources as $source) {
			if (strpos($source, "\n") === false) {
				$sources_processed[] = $source;
			} else {
				$sources_processed = array_merge($sources_processed, explode("\n", $source));
			}
		}
		return array_unique($sources_processed);
	}
}