<?php
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header("Content-disposition: attachment; filename=\"$filename.xlsx\"");
	echo $content_for_layout;