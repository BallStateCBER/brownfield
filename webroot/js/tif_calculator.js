function initializeTIFCalculator() {
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
	
	var county_id = $('#calc_county_id').val();
	var industry_id_select = $('#calc_industry_id');
	var industry_index = industry_id_select.selectedIndex;
	var input_options_select = $('#calc_input_options');
	var input_option_index = input_options_select.selectedIndex;
	
	// Handles situations where the user reaching this page by refreshing or going back in their
	// browser history starts the calculator with some selections already made.
	if (county_id) {
		onCountySelection(county_id, (industry_index == 0));
		if (industry_index != 0) {
			onIndustrySelection(input_option_index == 0);
			if (input_option_index != 0) {
				input_options_select.selectedIndex = input_option_index;
				onInputMethodSelection(input_option_index);
				(input_option_index == 1) ? user_input = $('#calc_annual_production').val() : user_input = $('#calc_employees').val();
				if (inputToInt(user_input)) {
					calculateImpact(false);
				}
			}
		}
	}
	
}
	
/* This hides industries that aren't found in the selected county 
 * (or shows all industries if there's an error looking the industries up)
 * and resets both industry selection and input-type selection if reset_subsequent is set to TRUE. */
function onCountySelection(county_id, reset_subsequent) {
	$('#calc_county_id_leading_choice').hide();
	var industry_select = $('#calc_industry_id');
	industry_select.removeAttr('disabled');
	if (reset_subsequent) {
		resetInputOptions();
		$('#calc_input_options').attr('disabled', 'disabled');
		$('#calculate_button').attr('disabled', 'disabled');
	}
	var url = '/calculators/getLocalIndustries/' + county_id;
	$.ajax({
		url: url
		success: function(data) {
			if (data.match('Error')) {
				alert('Error finding industries for this county: ' + data);
				industry_select.children().each(function () {
					$(this).show();
				});
			} else {
				var industry_ids = data.split(' ');
				var local_industry_count = industry_ids.length;
				if (reset_subsequent) {
					industry_select.selectedIndex = 0;
				}
				if (local_industry_count > 0) {
					var options = $('option.foo_option');
					options.each(function (option) {
						var industry_id = option.val();
						pos = industry_ids.indexOf(industry_id);
						if (pos == -1) {
							option.hide();
						} else {
							option.show();
						}
					});
				}

			}
		},
		error: function() {
			var options = $('option.foo_option');
			options.each(function(option) {
				option.show();
			});
		}
	});
}

function onIndustrySelection(reset_subsequent) {
	$('#calc_industry_id_leading_choice').hide();
	$('#calculate_button').attr('disabled', 'disabled');
	var calc_input_options = $('#calc_input_options');
	calc_input_options.removeAttr('disabled');
	if (calc_input_options.selectedIndex == 0) {
		resetInputOptions();
	} else {
		onInputMethodSelection(calc_input_options.selectedIndex);
	}
}

function resetInputOptions() {
	$('#option_a_input').hide();
	$('#option_b_input').hide();
	$('#calc_input_option_leading_choice').show();
	$('#calc_input_options').selectedIndex = 0;
}

function onInputMethodSelection(selected_index) {
	$('#calc_input_option_leading_choice').hide();
	if (selected_index == 1) { // option A
		$('#option_a_input').show();
		$('#option_b_input').hide();
	} else if (selected_index == 2) { // option B
		$('#option_a_input').hide();
		$('#option_b_input').show();
	}
	$('#calculate_button').removeAttr('disabled');
}

function calculateImpact(animate) {
	var county_id = $('#calc_county_id').val();
	var industry_id = $('#calc_industry_id').val();
	var selected_option = $('#calc_input_options').selectedIndex;
	if (! county_id || ! industry_id) {
		return;
	}
	if (selected_option == 1) {
		var annual_production = inputToInt($('#calc_annual_production').val());
		if (! annual_production) {
			return alert('Please enter the expected annual production of this company (in dollars).');
		}
		var url = '/calculators/tif_output/county_id:' + county_id + '/industry_id:' + industry_id + '/annual_production:' + annual_production + '/option:a';
	} else {
		var employees = inputToInt($('#calc_employees').val());
		if (! employees) {
			return alert('Please enter the expected number of employees for this company.');
		}
		var url = '/calculators/tif_output/county_id:' + county_id + '/industry_id:' + industry_id + '/employees:' + employees + '/option:b';
	}
	var container = $('#calc_output_container');
	var calc_loading_graphic_container = $('#calc_loading_graphic_container');
	hideCalcIntroText(animate);
	if (animate && container.is(':visible')) {
		$('.calc_section').each(function () {
			$(this).slideUp(200, function () {
				container.hide();
				updateCalculatorOutput(url, animate);
			});
		});
		/*
		var slide_duration = 0.9;
		Effect.SlideUp(container, {
			queue: {position: 'end', scope: 'calculator', limit: 1},
			duration: slide_duration,
			afterFinish: function() {updateCalculatorOutput(url, animate);}
		});
		*/
	} else {
		updateCalculatorOutput(url, animate);
	}
}

function updateCalculatorOutput(url, animate) {
	var container = $('#calc_output_container');
	var calc_loading_graphic_container = $('#calc_loading_graphic_container');
	if (animate) {
		var slide_duration = 0.8;
		var loading_fade_duration = 0.5;
		calc_loading_graphic_container.fadeIn(
			loading_fade_duration,
			function () {
				$.ajax({
					url: url,
					complete: function (data) {
						container.html(data);
						calc_loading_graphic_container.fadeIn(loading_fade_duration);
						container.slideDown(slide_duration);
					}
				});
			}
		);
	} else {
		calc_loading_graphic_container.show();
		$.ajax({
			url: url,
			complete: function (data) {
				container.html(data);
				calc_loading_graphic_container.hide();
				container.show();
			}
		});
	}
}

function moneyFormat(input) {
	return '$' + addCommas(input);
}

function addCommas(input) {
	input = inputToInt(input);
	for (var i = 0; i < Math.floor((input.length-(1+i))/3); i++) {
		input = input.substring(0,input.length-(4*i+3)) + ',' + input.substring(input.length-(4*i+3));
	}
	return input;
}

function inputToInt(input) {
	// If a decimal point exists in the input,
	// remove it and everything after it
	var index_of_point = input.indexOf('.');
	if (index_of_point > -1) {
		input = input.substring(0, index_of_point);
	}
	return input.replace(/[^0-9]/g, '');
}