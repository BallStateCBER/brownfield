<div>
	<?php foreach ($output as $section => $section_info): ?>
		<div class="calc_section">
			<div class="section_header">
				<?php echo $section_info['title']; ?>
			</div>
	
			<?php foreach ($section_info['rows'] as $measure => $measure_info): ?>
				<?php if (isset($measure_info['value'])): ?>
					<div class="row">
						<div class="value_name">
							<div class="output_help_toggler">
								<?php if (isset($measure_info['help'])): ?>
									<img 
										src="/data_center/img/icons/question.png" 
										class="help_toggler" 
										onmouseover="$('calc_<?php echo $measure; ?>_<?php echo $section; ?>_help').show();" 
										onmouseout="$('calc_<?php echo $measure; ?>_<?php echo $section; ?>_help').hide();" 
									/>
								<?php else: ?>
									&nbsp;
								<?php endif; ?>
							</div>
							<?php echo $measure_info['name']; ?>
							<?php if (isset($measure_info['help'])): ?>
								<div id="calc_<?php echo $measure; ?>_<?php echo $section; ?>_help" class="help_text" style="display: none;">
									<?php echo $measure_info['help']; ?>
								</div>
							<?php endif; ?>
						</div>
						<span class="value_amount"><?php echo $measure_info['value']; ?></span>
						<br style="clear: both;" />
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php if (isset($section_info['footnote'])): ?>
				<em>
					<?php echo $section_info['footnote']; ?>
				</em>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<div class="calc_section">
		<div class="section_header" id="tax_impact_section_header">
			<div class="output_help_toggler" style="margin-left:-20px;">
				<img 
					src="/data_center/img/icons/question.png" 
					class="help_toggler" 
					onmouseover="$('calc_taximpact_help').show();" 
					onmouseout="$('calc_taximpact_help').hide();" 
				/>
			</div>
			Indirect Business Tax Impact			
			<div id="calc_taximpact_help" class="help_text" style="display: none; margin-left: 0;">
				IBT <strong>excludes</strong> corporate profit tax, estate and gift tax, income tax, 
				social security taxes, personal motor vehicle license tax, personal property tax, 
				other personal taxes, and fines and fees
			</div>
		</div>
		<div class="direct_total">
			<div class="value_amount_column">Total</div>
			<div class="value_amount_column">Direct</div>
			<br style="clear: both;" />
		</div>
		
		<?php foreach ($taxesOrder as $tax_type): ?>
			<?php $row = $impact['tax_detail'][$tax_type]; ?>
			<?php if (isset($row['total'])): ?>
				<div class="row">
					<div class="value_name">
						<div class="output_help_toggler">
							<?php if (isset($row['help'])): ?>
								<img 
									src="/data_center/img/icons/question.png" 
									class="help_toggler" 
									onmouseover="$('calc_<?php echo $tax_type; ?>_taximpact_help').show();" 
									onmouseout="$('calc_<?php echo $tax_type; ?>_taximpact_help').hide();" 
								/>
							<?php else: ?>
								&nbsp;
							<?php endif; ?>
						</div>
						<?php echo $row['name']; ?>
						<?php if (isset($row['help'])): ?>
							<div id="calc_<?php echo $tax_type; ?>_taximpact_help" class="help_text" style="display: none;">
								<?php echo $row['help']; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="value_amount_column">
						<?php echo $row['total'] ?>
					</div>
					<div class="value_amount_column">
						<?php echo $row['direct'] ?>
					</div>
					<br style="clear: both;" />
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<br style="clear: both;" />
	<?php echo $this->Html->link(
		'Download CSV spreadsheet ',
		array_merge(
			array('controller' => 'calculators', 'action' => 'tif_output_csv'),
			$option == 'a' ? 
				compact('county_id', 'industry_id', 'option', 'annual_production') :
				compact('county_id', 'industry_id', 'option', 'employees')
		),
		array('style' => 'margin-top: 10px;')
	); ?>
</div>