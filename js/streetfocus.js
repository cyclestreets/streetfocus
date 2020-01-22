// StreetFocus implementation code

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window */

var streetfocus = (function ($) {
	
	'use strict';
	
	// Settings defaults
	var _settings = {
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: {
			latitude: 52.2053,
			longitude: 0.1218,
			zoom: 17
		},
		
	};
	
	
	// Internal class properties
	var _map = null;
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
			
			// Create the map
			streetfocus.createMap (_settings.defaultLocation, _settings.defaultTileLayer);
			
			// Run the defined action
			_map.on ('load', function () {
				streetfocus[action] ();
			});
		},
		
		
		// Home page
		home: function ()
		{
			//
		},
		
		
		// Function to create the map and related controls
		createMap: function (defaultLocation, defaultTileLayer)
		{
			// Create the map
			mapboxgl.accessToken = _settings.mapboxApiKey;
			_map = new mapboxgl.Map ({
				container: 'map',
				style: 'mapbox://styles/mapbox/streets-v11',
				center: [defaultLocation.longitude, defaultLocation.latitude],
				zoom: defaultLocation.zoom,
				hash: true
			});
		}
	};
	
} (jQuery));

