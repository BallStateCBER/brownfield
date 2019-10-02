<?php
class Datum extends AppModel {
    public $name = 'Datum';
    public $displayField = 'value';
    public $actsAs = array(
    	'Containable', 
    	'Tree'
	);
	public $belongsTo = array(
		'Source' => array(
			'className' => 'Source',
			'foreignKey' => 'source_id'
		),
		'DataCategory' => array(
			'className' => 'DataCategory',
			'foreignKey' => 'category_id'
		)
	);
	public $hasAndBelongsToMany = array();
	public $validate = array();
	
	// Returns dates/values for a continuous series
	public function getSeries($category_id, $loc_type_id, $loc_id, $date_begin = null, $date_end = null) {
		$conditions = array(
			'Datum.category_id' => $category_id,
			'Datum.loc_type_id' => $loc_type_id,
			'Datum.loc_id' => $loc_id 
		);
		if ($date_begin && $date_end) {
			$conditions['Datum.survey_date BETWEEN ? AND ? '] = array($date_begin, $date_end); 
		} elseif ($date_begin) {
			$conditions['Datum.survey_date >= '] = $date_begin;
		} elseif ($date_end) {
			$conditions['Datum.survey_date <= '] = $date_end;
		}
		$results = $this->find('all', array(
			'conditions' => $conditions,
			'fields' => array(
				'Datum.survey_date', 
				'Datum.value'
			),
			'order' => array(
				'Datum.survey_date ASC'
			)
		));
		
		$values = $dates = array();
		foreach ($results as $result) {
			$date = $result['Datum']['survey_date'];
			$dates[] = $date;
			
			// Remove trailing zeros (and trailing decimal point) to minimize query length
			$values[$date] = rtrim(trim($result['Datum']['value'], '0'), '.');
		}
		return array($dates, $values);
	}
	
	// Gets values corresponding to a specific set of dates
	// $dates can either be YYYYMMDD or YYYY formats
	public function getValues($category_id, $loc_type_id, $loc_id, $dates) {
		// Convert if dates are in YYYY format instead of YYYYMMDD
		if (strlen($dates[0]) == 4) {
			foreach ($dates as $key => $date) {
				$dates[$key] = $date.'0000';
			}
		}
		
		$results = $this->find('all', array(
			'conditions' => array(
				'Datum.category_id' => $category_id,
				'Datum.loc_type_id' => $loc_type_id,
				'Datum.loc_id' => $loc_id,
				'Datum.survey_date' => $dates
			),
			'fields' => array(
				'Datum.survey_date', 
				'Datum.value'
			),
			'order' => array(
				'Datum.survey_date ASC'
			)
		));
		
		$values = $dates = array();
		foreach ($results as $result) {
			$date = $result['Datum']['survey_date'];
			$dates[] = $date;
			
			// Remove trailing zeros (and trailing decimal point) to minimize query length
			$values[$date] = rtrim(trim($result['Datum']['value'], '0'), '.');
		}
		return array($dates, $values);
	}
	
	// Gets a single datum corresponding to a specific date
	public function getValue($category_id, $loc_type_id, $loc_id, $date) {
		// Convert if dates are in YYYY format instead of YYYYMMDD
		if (strlen($date) == 4) {
			$date = $date.'0000';
		}
		
		$results = $this->find('all', array(
			'conditions' => array(
				'Datum.category_id' => $category_id,
				'Datum.loc_type_id' => $loc_type_id,
				'Datum.loc_id' => $loc_id,
				'Datum.survey_date' => $date
			),
			'fields' => array(
				'Datum.survey_date', 
				'Datum.value'
			),
			'order' => array(
				'Datum.survey_date ASC'
			),
			'limit' => 1
		));

		if (empty($results) || !isset($results[0]['Datum']['value'])) {
		    return false;
        }

        return $results[0]['Datum']['value'];
	}
	
	public function getGrantsAwarded() {
		$result = $this->query("
			SELECT * 
			FROM brownfield_grants
			ORDER BY year DESC, recipient
		");
		$grants = array();
		foreach ($result as $row => $rowset) {
			foreach ($rowset as $table_name => $table_set) {
				$year = $table_set['year'];
				$recipient = $table_set['recipient'];
				$grants[$year][$recipient][] = array(
					'type' => $table_set['type'],
					'url' => $table_set['url']
				);
			}
		}
		return $grants;	
	}
}