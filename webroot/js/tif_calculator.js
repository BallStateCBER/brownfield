var TifCalculator = {
	local_industries: null,
	
	initialize: function (local_industries) {
		this.local_industries = local_industries;
		
		$('#calc_industry_id').change(function () {
			TifCalculator.onIndustrySelection(true);
		});
		$('#calc_county_id').change(function () {
			var value = $(this).val();
			TifCalculator.onCountySelection(value, true);
		});
		$('#calc_input_options').change(function () {
			TifCalculator.onInputMethodSelection($(this).val());
		});
		$('#calc_annual_production').change(function () {
			$(this).val(TifCalculator.moneyFormat($(this).val()));
		});
		$('#calculate_button').click(function (event) {
			event.preventDefault();
			TifCalculator.calculateImpact();
		});
		$('#calc_employees').change(function () {
			$(this).val(TifCalculator.addCommas($(this).val()));
		});
		
		var county_id = $('#calc_county_id').val();
		var industry = $('#calc_industry_id').val();
		var input_option = $('#calc_input_options').val();
		
		// Handles situations where the user reaching this page by refreshing or going back in their
		// browser history starts the calculator with some selections already made.
		if (county_id) {
			var industry_is_selected = (industry != ''); 
			this.onCountySelection(county_id, ! industry_is_selected);
			if (industry_is_selected) {
				var input_option_is_selected = (input_option != '');
				this.onIndustrySelection(! input_option_is_selected);
				if (input_option != '') {
					this.onInputMethodSelection(input_option);
					var user_input = (input_option == 'a') 
						? $('#calc_annual_production').val() 
						: $('#calc_employees').val();
					if (inputToInt(user_input)) {
						this.calculateImpact();
					}
				}
			}
		}
	},
		
	onCountySelection: function (county_id) {
		if (county_id == '') {
			$('#calc_industry_id_container').hide();
			$('#calc_input_options_container').hide();
			$('#calculate_button_container').hide();
		} else {
			$('#calc_industry_id_container').show();
		}
		
		$('#calc_county_id option:first-child').hide();
		var industry_select = $('#calc_industry_id');
		industry_select.removeAttr('disabled');
		if ($('#calc_industry_id').val() == '') {
			this.resetInputOptions();
			$('#calc_input_options').attr('disabled', 'disabled');
			$('#calculate_button').attr('disabled', 'disabled');
		}
		
		// Only show industries that correspond to the selected county
		industry_select.children().each(function () {
			var option = $(this);
			var industry_id = option.val();
			if (industry_id == '') {
				return;
			}
			var industries_in_county = TifCalculator.local_industries[county_id];
			if (industries_in_county.indexOf(industry_id) == -1) { 
				option.hide();
			} else {
				option.show();
			}
		});
	},

	onIndustrySelection: function (reset_subsequent) {
		$('#calc_industry_id option:first-child').hide();
		$('#calculate_button').attr('disabled', 'disabled');
		$('#calc_input_options_container').show();
		var calc_input_options = $('#calc_input_options');
		calc_input_options.removeAttr('disabled');
		var selected_option = calc_input_options.val();
		if (selected_option == '') {
			this.resetInputOptions();
		} else {
			this.onInputMethodSelection(selected_option);
		}
	},

	resetInputOptions: function () {
		$('#option_a_input').hide();
		$('#option_b_input').hide();
		$('#calc_input_option_leading_choice').show();
		$('#calc_input_options').selectedIndex = 0;
	},

	onInputMethodSelection: function (method) {
		$('#calc_input_options option:first-child').hide();
		if (method == 'a') {
			$('#option_a_input').show();
			$('#option_b_input').hide();
		} else if (method == 'b') {
			$('#option_a_input').hide();
			$('#option_b_input').show();
		}
		$('#calculate_button_container').show();
		$('#calculate_button').removeAttr('disabled');
	},

	calculateImpact: function () {
		var county_id = $('#calc_county_id').val();
		var industry_id = $('#calc_industry_id').val();
		var selected_option = $('#calc_input_options').val();
		if (! county_id || ! industry_id || ! selected_option) {
			return;
		}
		if (selected_option == 'a') {
			var annual_production = this.inputToInt($('#calc_annual_production').val());
			if (! annual_production) {
				return alert('Please enter the expected annual production of this company (in dollars).');
			}
			var url = '/calculators/tif_output/county_id:' + county_id + '/industry_id:' + industry_id + '/annual_production:' + annual_production + '/option:a';
		} else {
			var employees = this.inputToInt($('#calc_employees').val());
			if (! employees) {
				return alert('Please enter the expected number of employees for this company.');
			}
			var url = '/calculators/tif_output/county_id:' + county_id + '/industry_id:' + industry_id + '/employees:' + employees + '/option:b';
		}
		var container = $('#calc_output_container');
		var calc_loading_graphic_container = $('#calc_loading_graphic_container');
		if (container.is(':visible')) {
			$('.calc_section').each(function () {
				$(this).slideUp(200, function () {
					container.hide();
					TifCalculator.updateCalculatorOutput(url);
				});
			});
		} else {
			this.updateCalculatorOutput(url);
		}
	},

	updateCalculatorOutput: function (url) {
		$('#calc_output_loading_indicator').show();
		$.ajax({
			url: url,
			success: function (data) {
				$('#calc_output_loading_indicator').hide();
				var container = $('#calc_output_container');
				container.html(data);
				container.slideDown(500);
			}
		});
	},

	moneyFormat: function (input) {
		return '$' + this.addCommas(input);
	},

	addCommas: function (input) {
		input = inputToInt(input);
		for (var i = 0; i < Math.floor((input.length-(1+i))/3); i++) {
			input = input.substring(0,input.length-(4*i+3)) + ',' + input.substring(input.length-(4*i+3));
		}
		return input;
	},

	inputToInt: function (input) {
		// If a decimal point exists in the input,
		// remove it and everything after it
		var index_of_point = input.indexOf('.');
		if (index_of_point > -1) {
			input = input.substring(0, index_of_point);
		}
		return input.replace(/[^0-9]/g, '');
	}
};