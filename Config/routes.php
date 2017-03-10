<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different urls to chosen controllers and their actions (functions).
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
 * @package       app.Config
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

Router::connect('/', 				array('controller' => 'pages', 'action' => 'home'));
Router::connect("/grants", 			array('controller' => 'pages', 'action' => 'grants_awarded'));
Router::connect("/resources", 		array('controller' => 'pages', 'action' => 'resources'));
Router::connect("/contact", 		array('controller' => 'pages', 'action' => 'contact'));
Router::connect("/testimonials", 	array('controller' => 'pages', 'action' => 'testimonials'));
Router::connect("/admin", 			array('controller' => 'pages', 'action' => 'display', 'admin'));
Router::connect("/tif", 			array('controller' => 'calculators', 'action' => 'tif'));
Router::connect("/tif_basic", 		array('controller' => 'calculators', 'action' => 'tif_basic'));
Router::connect("/login", 			array('controller' => 'users', 'action' => 'login'));
Router::connect("/logout", 			array('controller' => 'users', 'action' => 'logout'));
Router::connect("/register", 		array('controller' => 'users', 'action' => 'register'));
Router::connect("/forgot_password",	array('controller' => 'users', 'action' => 'forgot_password'));
Router::connect("/reset_password/*",	array('controller' => 'users', 'action' => 'reset_password'));

// Routes requests for charts, tables, CSV files, and sources
$states = array('alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado', 'connecticut', 'delaware', 'florida', 'georgia', 'hawaii', 'idaho', 'illinois', 'indiana', 'iowa', 'kansas', 'kentucky', 'louisiana', 'maine', 'maryland', 'massachusetts', 'michigan', 'minnesota', 'mississippi', 'missouri', 'montana', 'nebraska', 'nevada', 'new_hampshire', 'new_jersey', 'new_mexico', 'new_york', 'north_carolina', 'north_dakota', 'ohio', 'oklahoma', 'oregon', 'pennsylvania', 'rhode_island', 'south_carolina', 'south_dakota', 'tennessee', 'texas', 'utah', 'vermont', 'virginia', 'washington', 'west_virginia', 'wisconsin', 'wyoming', 'al', 'ak', 'az', 'ar', 'ca', 'co', 'ct', 'de', 'fl', 'ga', 'hi', 'id', 'il', 'in', 'ia', 'ks', 'ky', 'la', 'me', 'md', 'ma', 'mi', 'mn', 'ms', 'mo', 'mt', 'ne', 'nv', 'nh', 'nj', 'nm', 'ny', 'nc', 'nd', 'oh', 'ok', 'or', 'pa', 'ri', 'sc', 'sd', 'tn', 'tx', 'ut', 'vt', 'va', 'wa', 'wv', 'wi', 'wy');
Router::connect(
	"/:state/:county/:topic/:type",
	array(
		'controller' => 'reports',
		'action' => 'switchboard'
	),
	array(
	 	'state' => '('.implode('|', $states).')',
		'type' => '(chart|svg_chart|table|csv|source|excel5|excel2007)',
		'pass' => array('type', 'topic', 'state', 'county')
	)
);

// State is specified at the beginning of the path
// In PagesController::topic(), the county and topic parameters are checked and an
// appropriate page (possibly error page or default topic page) is rendered
Router::connect(
	"/:state/*",
	array(
		'controller' => 'pages',
		'action' => 'topic'
	),
	array(
		'state' => '('.implode('|', $states).')',
		'pass' => array('state')
	)
);

/**
 * Load all plugin routes.  See the CakePlugin documentation on 
 * how to customize the loading of plugin routes.
 */
	CakePlugin::routes();

/**
 * Load the CakePHP default routes. Remove this if you do not want to use
 * the built-in default routes.
 */
	require CAKE . 'Config' . DS . 'routes.php';
