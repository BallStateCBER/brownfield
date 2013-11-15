<?php 
	if (isset($_GET['debug'])) {
		echo '<pre>';
	}
	
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output'); 
	
	if (isset($_GET['debug'])) {
		echo '</pre>';
	}