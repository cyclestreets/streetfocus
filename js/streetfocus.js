// StreetFocus implementation code

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window */

var streetfocus = (function ($) {
	
	'use strict';
	
	// Settings defaults
	var _settings = {
		
	};
	
	
	return {
		
		// Main function
		initialise: function (config, action)
		{
			// Merge the configuration into the settings
			$.each (_settings, function (setting, value) {
				if (config.hasOwnProperty(setting)) {
					_settings[setting] = config[setting];
				}
			});
			
			// Run the defined action
			_map.on ('load', function () {
				streetfocus[action] ();
			});
		},
		
		
		// Home page
		home: function ()
		{
			//
		}
	};
	
} (jQuery));

