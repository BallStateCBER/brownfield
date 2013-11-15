<h1 class="page_title">
	Basic TIF Calculator
</h1>

<p class="notification_message">
	This is the <strong>basic version</strong> of our TIF Calculator, meant for use with older browsers and browsers that have Javascript disabled. Users with modern browsers are invited to use the <a href="/tif">full version of the TIF Calculator</a>.
</p>

<?php if (isset($calc_output_vars)): ?>
	<p>
		Here is the estimated economic impact of the company that you described. If you have another company that you would like to know the economic impact of, <a href="#input">enter its information below</a>.
	</p>
	<div id="calc_output_container">
		<?php echo $this->element('calculators/output', $calc_output_vars); ?>
	</div>
	
	<hr id="basic_calc_input_output_separator" />
<?php endif; ?>

<p>
	Enter company information below and we'll calculate that company's economic impact.
</p>
<form method="post">
	<div id="basic_calc_input_container">
		<a name="input"></a>
		<?php if (isset($calc_error_messages) && ! empty($calc_error_messages)): ?>
			<div class="error_message">
				<ul>
					<?php foreach ($calc_error_messages as $msg): ?>
						<li>
							<?php echo $msg; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	
		<div class="field_block">
			<div class="field_name">State</div>
			<select>
				<option>Indiana</option>
			</select>
		</div>
		
		<div class="field_block">
			<div class="field_name">County</div>
			<select name="county_id" id="calc_county_id">
				<option value="" id="calc_county_id_leading_choice">Select a county...</option>
				<?php foreach ($calc_counties as $id => $name): ?>
			 		<option value="<?php echo $id; ?>" <?php if ($id == $calc_selected_county): ?>selected="selected"<?php endif; ?>><?php echo $name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="field_block">
			<div class="field_name">Industrial classification</div>
			<select name="industry_id" id="calc_industry_id">
				<option value="" id="calc_industry_id_leading_choice">Select an industry...</option>
				<?php foreach ($calc_naics_industries as $id => $name): ?>
			 		<option value="<?php echo $id; ?>" <?php if ($id == $calc_selected_industry): ?>selected="selected"<?php endif; ?>><?php echo $name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="field_block">
			<div class="field_name">Choose one</div>
			<table class="choose_option">
				<tr>
					<th>
						<input type="radio" name="option" value="a" id="basic_calc_option_a" <?php if ($calc_selected_option == 'a'): ?>checked="checked"<?php endif; ?> />
					</th>
					<td> 
						<label for="basic_calc_option_a">
							Annual production (sales, in dollars)
						</label>
					</td>
				</tr>
				<tr>
					<th>
						<input type="radio" name="option" value="b" id="basic_calc_option_b" <?php if ($calc_selected_option == 'b'): ?>checked="checked"<?php endif; ?> />
					</th>
					<td>
						<label for="basic_calc_option_b">
							Annual number of employees (not <acronym title="Full-time equivalents">FTEs</acronym>)
							<br />
							<span class="note">
								(This number can be a combination of both full-time and part-time employees.)
							</span>
						</label>
					</td>
				</tr>
			</table>
		</div>
		
		<div class="field_block">
			<div class="field_name">Enter your company's annual production in whole dollars OR its annual number of employees</div>
			<input type="text" name="amount" value="<?php echo $calc_amount; ?>" />
		</div>

		<input type="hidden" name="input_given" value="1" />
		<input type="submit" value="Calculate Impact" />

	</div>
</form>