<?php
	header("Content-type: text/csv");
	header("Content-disposition: attachment; filename=\"$filename.csv\"");
	echo $content_for_layout;