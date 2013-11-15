<?php
	header("Content-type: application/vnd.ms-excel"); 
	header("Content-disposition: attachment; filename=\"$filename.xls\"");
	echo $content_for_layout;