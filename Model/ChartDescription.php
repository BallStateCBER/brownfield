<?php
class ChartDescription extends AppModel {
	public $name = 'ChartDescription';
	public $displayField = 'description';
	
	public function getDescription($chart) {
		$result = $this->find('first', array(
			'conditions' => array(
				'ChartDescription.chart' => $chart
			),
			'fields' => array(
				'ChartDescription.description'
			)
		));
		return $result ? $result['ChartDescription']['description'] : '';
	}
}