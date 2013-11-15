<?php
class DataCategory extends AppModel {
    public $name = 'DataCategory';
    public $displayField = 'name';
    public $actsAs = array(
    	'Containable', 
    	'Tree'
	);
    public $order = 'DataCategory.lft ASC';
}