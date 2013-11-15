<?php 
	if (isset($_GET['debug'])):
		//echo '<pre>'.print_r($objPHPExcel, true).'</pre>';
		$last_col = max(array_keys($mockup));
		$last_row = 0;
		foreach ($mockup as $col => $rows) {
			$last_row_in_column = max(array_keys($rows));
			if ($last_row_in_column > $last_row) {
				$last_row = $last_row_in_column;
			}
		}
?>

	<style>
		#excel_mockup {border-collapse: collapse; font-family: Arial; font-size: 0.7em;}
		#excel_mockup td {border: 1px solid #333; padding: 3px;}
		#excel_mockup td div {max-height: 100px; max-width: 100px; overflow: auto;}
		#excel_mockup td.unset {background-color: #AAA;}
	</style>	

	Mockup of Excel document (note that the first row should be empty):
	<table id="excel_mockup">
		<?php for ($r = 0; $r <= $last_row; $r++): ?>
			<tr>
				<?php for ($c = 0; $c <= $last_col; $c++): ?>
					<?php if (isset($mockup[$c][$r])): ?>
						<td><div><?php echo $mockup[$c][$r]; ?></div></td>
					<?php else: ?>
						<td class="unset"></td>
					<?php endif; ?>
				<?php endfor; ?>
			</tr>
		<?php endfor; ?>
	</table>
	
<?php 
	else: 
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, $output_type);
		$objWriter->save('php://output');
	endif;