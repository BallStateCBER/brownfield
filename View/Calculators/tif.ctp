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

<?php $this->Form->create(false); ?>
<div id="calc_input_container">
	<h2>
		Economic Impact Calculator
	</h2>
	
	<p>
		Enter Company Information...
	</p>
	
	<?php echo $this->Form->input(
		'county_id',
		array(
			'type' => 'select',
			'id' => 'calc_county_id',
			'options' => $counties,
			'label' => 'County',
			'empty' => 'Select an Indiana county...'
		)
	); ?>

	<?php echo $this->Form->input(
		'industry_id',
		array(
			'type' => 'select',
			'id' => 'calc_industry_id',
			'options' => $naics_industries,
			'label' => 'Industrial classification',
			'disabled' => true,
			'empty' => 'Select an industry...'
		)
	); ?>
	
	<?php echo $this->Form->input(
		'option',
		array(
			'type' => 'select',
			'id' => 'calc_input_options',
			'options' => array(
				'a' => 'Annual Production',
				'b' => 'Number of Employees'
			),
			'label' => 'Input method',
			'disabled' => true,
			'empty' => 'Choose one...'
		)
	); ?>

	<?php echo $this->Form->input(
		'annual_production',
		array(
			'id' => 'calc_annual_production',
			'label' => 'Annual production (sales, in dollars)',
			'div' => array(
				'id' => 'option_a_input'
			)
		)
	); ?>

	<?php echo $this->Form->input(
		'employees',
		array(
			'id' => 'calc_employees',
			'label' => '
				<img src="/data_center/img/icons/question.png" id="calc_employees_help_toggler" class="help_toggler" />
				Annual number of employees (not FTEs)
				<div id="calc_employees_help" class="help_text">
					FTE: Full-time equivalents<br />
					This number can be a combination of both full-time and part-time employees.
				</div>
			',
			'div' => array(
				'id' => 'option_b_input'
			)
		)
	); ?>
	
	<?php echo $this->Form->end(array(
		'label' => 'Calculate Impact',
		'id' => 'calculate_button',
		'disabled' => true,
		'div' => array(
			'id' => 'calculate_button_container'
		)
	)); ?>
	
	<div id="calc_loading_graphic_container">
		<img src="/img/loading2.gif" />
	</div>
</div>

<p class="basic_calculator_note">
	You can also use the <a href="/tif_basic">'Basic' version of this calculator</a>, meant for older browsers and browsers with Javascript disabled
</p>

<div id="calc_output_container"></div>

<div id="calc_footer"></div>

<?php $this->Js->buffer("
	initializeTIFCalculator();
"); ?>