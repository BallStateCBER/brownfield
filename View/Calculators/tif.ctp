<h1 class="page_title">
	TIF-in-a-Box
</h1>

<div id="calc_intro_text">
	<div>
		<p>
			<strong>Tax Increment Financing (TIF)</strong> is a tool used by local governments to allocate changes to local taxes caused by new 
			business development for a specific purpose.  In a typical setting, the growth of property taxes associated with a 
			new business will be applied to a purpose that has significant impact on the community.  Examples of these are 
			installation of water or sewer infrastructure, construction or expansion of roadways, or remediation of a brownfield.
		</p>
		<p>
			This section provides <strong>some basic material on TIFs</strong> found in studies by 
			<a href="/files/Bartsch and Wells, (2003).pdf">Bartsch and Wells (2003)</a> and 
			<a href="/files/Paull, 2008.pdf">Paull (2008)</a> funded by the 
			<a href="http://www.nemw.org/">Northeast-Midwest Institute</a> and the enabling legislation from Indiana contained in 
			<a href="/files/IC 36-7-14.html">IC 36-7-14</a>, which deals with TIFs and their uses.  
		</p>
		<p>
			This section also includes our <strong>Economic Impact Calculator</strong>, a tool for estimating the economic and fiscal effects of new business development.
			To use the Economic Impact Calculator, enter company information into the form below.  
		</p>
	</div>
</div>

<?php $this->Html->script('tif_calculator', array('inline' => false)); ?>

<form method="get">
	<div id="calc_input_container">
		<h2>
			Economic Impact Calculator
		</h2>
		
		<p>
			Enter Company Information...
		</p>
		<div class="field_block">
			<div class="field_name">
				County
			</div>
			<select name="county_id" id="calc_county_id">
				<option value="" id="calc_county_id_leading_choice">
					Select a county...
				</option>
				<?php foreach ($counties as $id => $name): ?>
			 		<option value="<?php echo $id; ?>">
			 			<?php echo $name; ?>
			 		</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="field_block">
			<div class="field_name">
				Industrial classification
			</div>
			<select name="industry_id" id="calc_industry_id" disabled="disabled">
				<option value="" id="calc_industry_id_leading_choice">
					Select an industry...
				</option>
				<?php foreach ($naics_industries as $industry_id => $industry_name): ?>
			 		<option class="foo_option" value="<?php echo $industry_id; ?>">
			 			<?php echo $industry_name; ?>
			 		</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="field_block">
			<div class="field_name">
				Choose input method
			</div>
			<select name="option" id="calc_input_options" disabled="disabled">
				<option value="" id="calc_input_option_leading_choice">
					Choose one...
				</option>
				<option value="a">
					Annual Production
				</option>
				<option value="b">
					Number of Employees
				</option>
			</select>
		</div>

		<div class="field_block" id="option_a_input" style="display: none;">
			<div class="field_name">
				Annual production (sales, in dollars):
			</div>
			<input type="text" name="annual_production" id="calc_annual_production" />
		</div>

		<div id="option_b_input" style="display: none;">
			<div class="field_block">
				<div class="field_name">
					<img src="/data_center/img/icons/question.png" id="calc_employees_help_toggler" class="help_toggler" />
					Annual number of employees (not FTEs):
					<div id="calc_employees_help" class="help_text" style="display: none;">
						FTE: Full-time equivalents<br />
						This number can be a combination of both full-time and part-time employees.
					</div>
				</div>
				<input type="text" name="employees" id="calc_employees" />
			</div>
		</div>
		<div id="calculate_button_container">
			<input id="calculate_button" type="button" disabled="disabled" value="Calculate Impact &rarr;" />
			<div id="calc_loading_graphic_container" style="display: none;">
				<img src="/img/loading2.gif" />
			</div>
		</div>
	</div>
</form>

<p style="font-size: 0.9em">
	You can also use the <a href="/tif_basic">'Basic' version of this calculator</a>, meant for older browsers and browsers with Javascript disabled
</p>

<?php $this->Js->buffer("
	var calc_industry_id = $('#calc_industry_id');
	calc_industry_id.change(function () {
		onIndustrySelection(true);
	});
	$('#calc_county_id').change(function () {
		var value = $(this).val();
		onCountySelection(value, true);
	});
	$('#calc_input_options').change(function () {
		onInputMethodSelection(this.selectedIndex);
	});
	$('#calc_annual_production').change(function () {
		$(this).val(moneyFormat($(this).val()));
	});
	var calc_employees_help_toggler = $('#calc_employees_help_toggler');
	calc_employees_help_toggler.mouseover(function () {
		$('#calc_employees_help').show();
	});
	calc_employees_help_toggler.mouseout(function () {
		$('#calc_employees_help').hide();
	});
	$('#calculate_button').click(function () {
		calculateImpact(true);
	});
	$('#calc_employees').change(function () {
		$(this).val(addCommas($(this).val()));
	});
"); ?>

<div id="calc_output_container" style="display: none;"></div>

<div id="calc_footer"></div>

<?php $this->Js->buffer("
	initializeTIFCalculator();
"); ?>