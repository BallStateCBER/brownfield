<?php
class Source extends AppModel {
    public $name = 'Source';
    public $displayField = 'source';
    public $actsAs = array(
    	'Containable'
	);
}
