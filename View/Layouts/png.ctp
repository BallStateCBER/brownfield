<?php 
	header('Content-Type: image/png');
	echo $content_for_layout; 
	Configure::write('debug', 0);