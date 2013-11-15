<?php
	header("Content-type: application/vnd.ms-excel"); 
	header('Content-Disposition: attachment;filename="'.$title.'.xls"');
	header('Cache-Control: max-age=0');
	echo $content_for_layout;