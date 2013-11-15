<?php 
	/* Creates a space-delimited list of applicable industry IDs that can then be parsed by the
	 * loadLocalIndustries() Javascript function. */ 
	foreach ($industries as $industry) {
		echo "$industry ";
	}