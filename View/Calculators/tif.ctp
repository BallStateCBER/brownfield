<h1 class="page_title">
	TIF-in-a-Box
</h1>

<div id="calc_intro_text_teaser"  style="display: none;">
	Tax Increment Financing (TIF) is a tool used by local governments to... 
	<span class="fake_link" id="calc_intro_text_open_handle">
		(read on)
	</span>
</div>

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
			To use the Economic Impact Calculator, enter company information into the form to the left.  
		</p>
		<span class="fake_link" id="calc_intro_text_close_handle">
			(collapse this text)
		</span>
	</div>
</div>

<div id="calc_output_container" style="display: none;"></div>

<div id="calc_footer"></div>

<?php $this->Js->buffer("
	$('#calc_intro_text_open_handle').click(function (event) {
		event.preventDefault();
		showCalcIntroText();
	});
	$('#calc_intro_text_close_handle').click( function(event) {
		event.preventDefault();
		hideCalcIntroText(true);
	});
	initializeTIFCalculator();
"); ?>