<h1 class="page_title">
	TIF-in-a-Box
</h1>

<h2>
	Tax Increment Financing
</h2>
<p>
	Tax Increment Financing (TIF) is a tool used by local governments to allocate changes to local taxes caused by new 
	business development for a specific purpose.  In a typical setting, the growth of property taxes associated with a 
	new business will be applied to a purpose that has significant impact on the community.  Examples of these are 
	installation of water or sewer infrastructure, construction or expansion of roadways, or remediation of a brownfield.
</p>

<h2>
	Studies and Legislation
</h2>
<p>
	More information about TIF can be found in studies by 
	<a href="/files/Bartsch and Wells, (2003).pdf">Bartsch and Wells (2003)</a> and 
	<a href="/files/Paull, 2008.pdf">Paull (2008)</a>. These studies were funded by the 
	<a href="http://www.nemw.org/">Northeast-Midwest Institute</a> and enabled Indiana state legislation  
	<a href="/files/IC 36-7-14.html">IC 36-7-14</a>, which deals with TIF and its uses.
</p>

<h2>
	Economic Impact Calculator
</h2>
<p>
	The Economic Impact Calculator is a tool for estimating the economic and fiscal effects of new business development.
	To use the calculator, begin by selecting the company's location in the form below.
</p>

<?php $this->Html->script('tif_calculator', array('inline' => false)); ?>

<?php $this->Form->create(false); ?>
<div id="calc_input_container">
	
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
			'empty' => 'Select an industry...',
			'div' => array(
				'id' => 'calc_industry_id_container'
			)
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
			'empty' => 'Choose one...',
			'div' => array(
				'id' => 'calc_input_options_container'
			)
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
			'label' => 'Annual number of employees (not FTEs)',
			'after' => '<p class="footnote">FTE: Full-time equivalents. This number can be a combination of both full-time and part-time employees.</p>',
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
		),
		'after' => '<img src="/data_center/img/loading_small.gif" id="calc_output_loading_indicator" />'
	)); ?>
</div>

<div id="calc_output_container"></div>

<div id="calc_footer"></div>

<?php $this->Js->buffer("
	var local_industries = ".$this->Js->object($local_industries).";
	initializeTIFCalculator(local_industries);
"); ?>