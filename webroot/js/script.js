function setupCollapsibleFieldsets() {
	$('fieldset.collapsible').each(function() {
		var fieldset = $(this);
		var legend = fieldset.find('legend');
		legend.click(function () {
			toggleFieldset(fieldset);
		});
		legend.addClass('fake_link');
		legend.attr('title', 'Click to open/close.');
		fieldset.children('div').wrap('<div class="fieldset_internal"></div>'); // Extra div to make animation smoother
		if (fieldset.hasClass('collapsed')) {
			fieldset.find('div.fieldset_internal').hide();
		}
	});
}

function toggleFieldset(fieldset) {
	var internal = fieldset.find('.fieldset_internal');		
	if (internal.is(':visible')) {
		internal.slideUp(500, function() {
			fieldset.addClass('collapsed');
		});
	} else {
		internal.slideDown(500, function() {
			fieldset.removeClass('collapsed');
		});
	}
}

function onNavSubmenuHandleClick(tab) {
	var nav_submenu_handle = $('#nav_submenu_handle_' + tab);
	var nav_submenu = $('#nav_submenu_' + tab);
	nav_submenu.slideToggle({
		duration: 500,
		start: function() {
			if (nav_submenu.is(':visible')) {
				nav_submenu_handle.attr('class', 'closed');
			} else {
				nav_submenu_handle.attr('class', 'open');
			}
		},
		complete: function() {
			if (nav_submenu.is(':visible')) {
				nav_submenu_handle.attr('class', 'open');
			} else {
				nav_submenu_handle.attr('class', 'closed');
			}
		}
	});
}