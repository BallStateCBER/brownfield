<?php 
	if (isset($_GET['debug'])) {
		echo '<pre>';
	}
	
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
	$objWriter->save('php://output');
	
	if (isset($_GET['debug'])) {
		echo '</pre>';
	}