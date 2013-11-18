<?php
// Prepare variables used in the sidebar and on topic pages
function getSharedVariables($params) {
	$retval = array();
	$Location = ClassRegistry::init('Location');
	$sidebar_vars = array();
	$states = $Location->getStatesAndAbbreviations();
	$state_abbreviations = array();
	foreach ($states as $state) {
		$id = $state['Location']['id'];
		$ab = $state['Location']['abbreviation'];
		$state_abbreviations[$id] = strtolower($ab);
	}
	
	// Defaults
	$selected_state = $selected_county = $selected_tab = $selected_topic = $topics = $profiles_link = null;
	$state_id = 14;
	
	// Figure out what page we're on and what should / shouldn't be shown 
	if (isset($params['url']['url'])) {
		$split_url = explode('/', $params['url']['url']);

		// Is a state selected?
		if (isset($split_url[0]) && in_array($split_url[0], $state_abbreviations)) {
			$selected_state = $split_url[0];
			$state_id = $Location->getStateID($selected_state);
		}
		
		// Is a county selected?
		if ($selected_state && isset($split_url[1])) {
			$counties_simplified = $Location->getCountiesSimplified($selected_state);
			if (in_array($split_url[1], $counties_simplified)) {
				$selected_county = $split_url[1];
			}
		}
		
		// Is a topic selected?
		if ($selected_state && $selected_county && isset($split_url[2])) {
			$Report = ClassRegistry::init('Report');
			$topics = $Report->getTopicList(true);
			foreach ($topics as $tab => $topics_in_tab) {
				$simple_topic_names = array_keys($topics_in_tab);
				if (in_array($split_url[2], $simple_topic_names)) {
					$selected_tab = $tab;	// Used to have the correct sub-menu in the sidebar already opened
					$selected_topic = $split_url[2];
					break;
				}
			}
		}
		
		// Determine the sidebar mode, if not 'home'
		if (isset($selected_county)) {
			$sidebar_mode = 'county';
		} elseif ($split_url[0] == 'tif' || $split_url[0] == 'calculators') {
			$sidebar_mode = 'tif';
		}
	}
	
	// Sidebar is in 'county' mode when a state/county has been selected, 'tif' mode for the calculator, and 'home' mode all other times.
	if (! isset($sidebar_mode)) {
		$sidebar_mode = 'home';
	}
	
	// Get link to corresponding County Profiles page
	if (isset($selected_state) && isset($selected_county) && $profiles_url = $Location->getCountyProfilesLink($selected_county, $selected_state)) {
		$full_county_name = $Location->getCountyFullName($selected_county, $selected_state, true);
		$profiles_link = "<a href=\"$profiles_url\">CBER Profile of $full_county_name</a>";
	}
	
	$retval = compact(
		'states', 
		'state_abbreviations', 
		'selected_state', 
		'selected_county', 
		'selected_tab', 
		'selected_topic', 
		'sidebar_mode', 
		'topics',
		'profiles_link'
	);
	
	if ($sidebar_mode == 'tif') {
		$retval['naics_industries'] = ClassRegistry::init('Calculator')->getNaicsIndustries();
		$retval['counties'] = $Location->getCountiesFull($state_id);
	}
	
	if ($sidebar_mode == 'county' || $sidebar_mode == 'home') {
		if (! isset($counties_simplified)) {
			$counties_simplified = $Location->getCountiesSimplified($state_id);
		}
		$retval['counties_full_names'] = $Location->getCountiesFull($state_id);
		$retval['counties_simplified'] = $counties_simplified;
	}
	
	return $retval;
}
?>