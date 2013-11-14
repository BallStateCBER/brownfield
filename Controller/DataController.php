<?php
class DataController extends AppController {
	public $name = 'Data';
	public $components = array(
		'RequestHandler'
	);
	public $helpers = array(
		'GoogleChart'
	);
	
	public function getGrantsAwarded() {
		return $this->Datum->getGrantsAwarded();	
	}
}