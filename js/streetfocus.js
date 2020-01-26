// StreetFocus implementation code

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window */

var streetfocus = (function ($) {
	
	'use strict';
	
	// Settings defaults
	var _settings = {
		
		// PlanIt API
		planitApiBaseUrl: 'https://www.planit.org.uk/api',
		
		// Cyclescape API
		cyclescapeBaseUrl: 'https://www.cyclescape.org',
		cyclescapeApiBaseUrl: 'https://www.cyclescape.org/api',
		
		// CycleStreets API; obtain a key at https://www.cyclestreets.net/api/apply/
		cyclestreetsApiBaseUrl: 'https://api.cyclestreets.net',
		cyclestreetsApiKey: 'YOUR_CYCLESTREETS_API_KEY',
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Google Maps API key
		gmapApiKey: 'YOUR_GOOGLEMAPS_API_KEY',
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: {
			latitude: 52.2053,
			longitude: 0.1218,
			zoom: 17
		},
		
		// Geocoder API URL; re-use of settings values represented as placeholders {%apiBaseUrl}, {%apiKey}, {%autocompleteBbox}, are supported
		geocoderApiUrl: '{%cyclestreetsApiBaseUrl}/v2/geocoder?key={%cyclestreetsApiKey}&bounded=1&bbox={%autocompleteBbox}',
		
		// BBOX for autocomplete results biasing
		autocompleteBbox: '-6.6577,49.9370,1.7797,57.6924',
		
	};
	
	
	// Internal class properties
	var _map = null;
	var _colours = {
		planningapplications: {
			field: 'app_type',
			values: {
				'Full':			'#007cbf',
				'Outline':		'blue',
				'Amendment':	'orange',
				'Heritage':		'brown',
				'Trees':		'green',
				'Advertising':	'red',
				'Telecoms':		'purple',
				'Other':		'gray'
			}
		}
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
			
			// Prevent viewport zooming, which is problematic for iOS Safari
			streetfocus.preventViewportZooming ();
			
			// Create mobile navigation
			streetfocus.createMobileNavigation ();
			
			// Create the map
			streetfocus.createMap (_settings.defaultLocation, _settings.defaultTileLayer);
			
			// Run the defined action
			_map.on ('load', function () {
				streetfocus[action] ();
			});
		},
		
		
		// Prevent viewport zooming, which is problematic for iOS Safari; see: https://stackoverflow.com/questions/37808180/
		preventViewportZooming: function ()
		{
			document.addEventListener ('touchmove', function (event) {
				if (event.scale !== 1) {
					event.preventDefault ();
				}
			}, {passive: false});
		},
		
		
		// Create mobile navigation
		createMobileNavigation: function ()
		{
			// Create a side panel containing the menu items
			$('body').append ('<div id="mobilenav"></div>');
			$('#mobilenav').html ( $('nav ul').wrap('<div />').parent().html() );	// https://stackoverflow.com/a/6459969/180733
			$('#mobilenav').prepend('<p id="close"><a href="#">×</a></p>');
			
			// Toggle visibility clickable
			$('header nav img').click(function () {
				if ($('#mobilenav').is(':visible')) {
					$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
				} else {
					$('#mobilenav').animate ({width: 'toggle'}, 250);
				}
			});
			
			// Close x button
			$('#mobilenav #close').click(function () {
				$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
			});
			
			// Enable implicit click/touch on map as close menu
			$('main, footer').click(function () {
				if ($('#mobilenav').is(':visible')) {
					$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
				};
			});
			
			// Enable closing menu on slide right
			$('#mobilenav').on('swiperight', function () {
				$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
			});
		},
		
		
		// Home page
		home: function ()
		{
			// Add the planning applications layer, e.g. /api/applics/geojson?limit=30&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076&recent=188&app_type=Full,Trees
			var apiBaseUrl = _settings.planitApiBaseUrl + '/applics/geojson';
			var parameters = {
				recent:	200
			};
			streetfocus.addLayer ('planningapplications', apiBaseUrl, parameters);
		},
		
		
		// Planning applications
		map: function ()
		{
			// Set the layer ID
			var layerId = 'planningapplications';
			
			// Add the planning applications layer, e.g. /api/applics/geojson?limit=30&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076&recent=188&app_type=Full,Trees
			var apiBaseUrl = _settings.planitApiBaseUrl + '/applics/geojson';
			var parameters = {
				recent:	200
			};
			
			// Handle filtering panel visibility
			$('#filter').click (function () {
				$('#filtering').fadeToggle ();
			});
			
			// Set checkbox colours
			var value;
			$.each ($("input[name='app_type']"), function () {
				value = $(this).val ();
				$(this).parent().parent().css ('background-color', _colours[layerId].values[value]);		// Two parents, as label surrounds
			});
			
			// Handle filtering panel options
			$('#filtering #type input').click (function (e) {
				var types = [];
				$.each ($("input[name='app_type']:checked"), function() {
					types.push ($(this).val ());
				});
				parameters.app_type = types.join (',');
				
				// Redraw if already present
				if (_map.getLayer (layerId)) {
					streetfocus.addData (layerId, apiBaseUrl, parameters);
				}
				
				// Auto-close
				$('#filtering').fadeToggle ();
			});
			
			// Add the data layer
			streetfocus.addLayer (layerId, apiBaseUrl, parameters);
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/allCambridgeCollisions.geojson');
		},
		
		
		// Function to populate the popup
		populatePopupPlanningapplications: function (element, feature)
		{
			// Get the centre-point of the geometry
			var centre = streetfocus.getCentre (feature.geometry);
			
			// Populate the HTML content
			$(element + ' p.applicationId').html (feature.properties.uid);
			$(element + ' p.officialplans a').attr ('href', feature.properties.url);
			$(element + ' p.developmentsize span').html ('Unknown size');
			$(element + ' p.type span').html (feature.properties.app_type);
			$(element + ' p.state span').html (feature.properties.app_state);
			$(element + ' p.deadline span').html ('X weeks from ' + feature.properties.start_date);
			$(element + ' h3.title').html (streetfocus.htmlspecialchars (streetfocus.truncateString (feature.properties.description, 40)));
			$(element + ' p.description').html (streetfocus.htmlspecialchars (feature.properties.description));
			$(element + ' p.address').html (streetfocus.htmlspecialchars (feature.properties.address));
			$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
		},
		
		
		// Proposals
		proposals: function ()
		{
			// Add the proposals layer, e.g. /api/issues.json?page=1&per_page=100&bbox=-0.127902%2C51.503486%2C-0.067091%2C51.512086
			var apiBaseUrl = _settings.cyclescapeApiBaseUrl + '/issues.json';
			var parameters = {
				page:		1,
				per_page:	200
			};
			streetfocus.addLayer ('proposals', apiBaseUrl, parameters);
		},
		
		
		// Function to populate the popup
		populatePopupProposals: function (element, feature)
		{
			// Get the centre-point of the geometry
			var centre = streetfocus.getCentre (feature.geometry);
			
			// Populate the static HTML
			$(element + ' p.id').html ('#' + feature.properties.id);
			$(element + ' p.link a').attr ('href', feature.properties.cyclescape_url);
			$(element + ' p.date span').html (new Date(feature.properties.created_at * 1000).toDateString());
			$(element + ' h3.title').html (feature.properties.title);
			$(element + ' p.description').html (feature.properties.description);
			$(element + ' p.image img').attr ('src', _settings.cyclescapeBaseUrl + feature.properties.photo_thumb_url);
			$(element + ' ul.tags').html ('<ul class="tags"><li>' + JSON.parse(feature.properties.tags).join('</li><li>') + '</li></ul>');
			$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
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
			
			// Add navigation (+/-) controls
			_map.addControl(new mapboxgl.NavigationControl ());
			
			// Add geolocation control
			streetfocus.geolocation ();
			
			// Add geocoder control
			streetfocus.geocoder ();
		},
		
		
		// Function to add geolocation
		geolocation: function ()
		{
			// Initialise the control
			var geolocate = new mapboxgl.GeolocateControl ({
				positionOptions: {
					enableHighAccuracy: true
				}
			});
			_map.addControl (geolocate, 'bottom-right');	// Will be hidden by CSS
			
			// Add manual trigger from custom image
			$('#geolocation').on ('click', function () {
				geolocate.trigger ();
			});
		},
		
		
		// Wrapper function to add a geocoder control
		geocoder: function ()
		{
			// Geocoder URL; re-use of settings values is supported, represented as placeholders {%apiBaseUrl}, {%apiKey}, {%autocompleteBbox}
			var geocoderApiUrl = streetfocus.settingsPlaceholderSubstitution (_settings.geocoderApiUrl, ['cyclestreetsApiBaseUrl', 'cyclestreetsApiKey', 'autocompleteBbox']);
			
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo ('#geocoder input', {
				sourceUrl: geocoderApiUrl,
				select: function (event, ui) {
					var bbox = ui.item.feature.properties.bbox.split(',');	// W,S,E,N
					_map.fitBounds(bbox, {maxZoom: 17});
					event.preventDefault();
				}
			});
		},
		
		
		// Helper function to implement settings placeholder substitution in a string
		settingsPlaceholderSubstitution: function (string, supportedPlaceholders)
		{
			// Substitute each placeholder
			var placeholder;
			$.each(supportedPlaceholders, function (index, field) {
				placeholder = '{%' + field + '}';
				string = string.replace(placeholder, _settings[field]);
			});
			
			// Return the modified string
			return string;
		},
		
		
		// Function to add a data layer to the map
		addLayer: function (layerId, apiBaseUrl, parameters)
		{
			// Compile colour lists
			var colourPairs = [];
			if (_colours[layerId]) {
				$.each (_colours[layerId].values, function (key, value) {
					colourPairs.push (key);
					colourPairs.push (value);
				});
			}
			
			// Add the source and layer
			_map.addLayer ({
				id: layerId,
				type: 'circle',
				source: {
					type: 'geojson',
					generateId: true,
					data: {		// Empty GeoJSON to start
						type: 'FeatureCollection',
						features: []
					}
				},
				paint: {
					'circle-radius': 20,
					'circle-color': (
						_colours[layerId]
						? [
							'match',
							['get', _colours[layerId].field],
							...colourPairs,
							'#ffc300'
						]
						: '#ffc300'
					)
				}
			});
			
			// Register popup handler
			var popup;
			_map.on ('click', layerId, function (e) {
				
				// Substitute the content
				var feature = e.features[0];
				var popupFunction = 'populatePopup' + streetfocus.ucfirst (layerId);
				streetfocus[popupFunction] ('#popupcontent', feature);
				
				// Get the HTML
				var popupHtml = $('#popupcontent').html();
				
				// Create the HTML
				popup = new mapboxgl.Popup ({className: layerId})
					.setLngLat (e.lngLat)
					.setHTML (popupHtml)
					.addTo (_map);
			});
			
			// Register escape key handler to close popups
			$(document).keyup(function(e) {
				if (e.keyCode === 27) {
					popup.remove ();
				}
			});
			
			// Change the cursor to a pointer when over a point
			_map.on ('mouseenter', layerId, function () {
				_map.getCanvas().style.cursor = 'pointer';
			});
			_map.on ('mouseleave', layerId, function () {
				_map.getCanvas().style.cursor = '';
			});
			
			// Get the data, and register to update on map move
			streetfocus.addData (layerId, apiBaseUrl, parameters);
			_map.on ('moveend', function (e) {
				streetfocus.addData (layerId, apiBaseUrl, parameters);
			});
		},
		
		
		// Function to load the data for a layer
		addData: function (layerId, apiBaseUrl, parameters)
		{
			// Get the map BBOX
			var bbox = _map.getBounds();
			bbox = bbox.getWest() + ',' + bbox.getSouth() + ',' + bbox.getEast() + ',' + bbox.getNorth();
			bbox = streetfocus.reduceBboxAccuracy (bbox);
			parameters.bbox = bbox;
			
			// Request the data
			$.ajax ({
				url: apiBaseUrl,
				dataType: 'json',
				data: parameters,
				error: function (jqXHR, error, exception) {
					if (jqXHR.statusText != 'abort') {
						var data = $.parseJSON(jqXHR.responseText);
						alert ('Error: ' + data.error);		// #!# Need to check how PlanIt API gives human-readable errors
					}
				},
				success: function (data, textStatus, jqXHR) {
					streetfocus.showCurrentData (layerId, data);
				}
			});
		},
		
		
		// Function to add a heatmap layer
		addHeatmapLayer: function (layerId, datasource)
		{
			// Add the data source
			_map.addSource (layerId, {
				type: 'geojson',
				data: datasource,
			});
			
			// Add heatmap layer
			_map.addLayer ({
				id: layerId,
				type: 'heatmap',
				source: layerId,
				maxzoom: 17,
				'layout': {
					'visibility': 'none'
				},
				paint: {
					// Increase weight as severity increases
					'heatmap-weight': [
						"interpolate",
						["linear"],
						["get", "sev"],
						0, 0.02,
						1, 0.3,
						2, 1
					],
					// Increase intensity as zoom level increases
					'heatmap-intensity': {
						stops: [
							[8, 1],
							[9, 1.25],
							[10, 1.5],
							[11, 2.5],
							[15, 5]
						]
					},
					// Assign color values be applied to points depending on their density
					'heatmap-color': [
						'interpolate',
						['linear'],
						['heatmap-density'],
						// http://colorbrewer2.org/?type=sequential&scheme=OrRd&n=5
						// Only the first should be rgba() rest use rgb()
						0,   'rgba(254,240,217,0)',
						0.2, 'rgb(253,204,138)',
						0.4, 'rgb(252,141,89)',
						0.6, 'rgb(227,74,51)',
						0.8, 'rgb(179,0,0)'
					],
					// Increase radius as zoom increases
					'heatmap-radius': {
						stops: [
							[11, 15],
							[15, 20]
						]
					}
					/* ,
					// Decrease opacity to transition into the circle layer
					'heatmap-opacity': {
						default: 1,
						stops: [
							[14, 1],
							[17, 0]
						]
					},
					*/
				}
			}, 'planningapplications');
			
			// Handle visibility
			$('#' + layerId).click (function (e) {
				var visibility = _map.getLayoutProperty (layerId, 'visibility');
				if (visibility === 'visible') {
					_map.setLayoutProperty (layerId, 'visibility', 'none');
				} else {
					_map.setLayoutProperty (layerId, 'visibility', 'visible');
				}
			});
			
		},
		
		
		// Function to reduce co-ordinate accuracy of a bbox string
		reduceBboxAccuracy: function (bbox)
		{
			// Split by comma
			var coordinates = bbox.split(',');
			
			// Reduce accuracy of each coordinate
			coordinates = streetfocus.reduceCoordinateAccuracy (coordinates);
			
			// Recombine
			bbox = coordinates.join(',');
			
			// Return the string
			return bbox;
		},
		
		
		// Function to reduce co-ordinate accuracy to avoid pointlessly long URLs
		reduceCoordinateAccuracy: function (coordinates)
		{
			// Set 0.1m accuracy; see: https://en.wikipedia.org/wiki/Decimal_degrees
			var accuracy = 6;
			
			// Reduce each value
			var i;
			for (i = 0; i < coordinates.length; i++) {
				coordinates[i] = parseFloat(coordinates[i]).toFixed(accuracy);
			}
			
			// Return the modified set
			return coordinates;
		},
		
		
		// Helper function to get the centre-point of a geometry
		getCentre: function (geometry)
		{
			// Determine the centre point
			var centre = {};
			switch (geometry.type) {
				
				case 'Point':
					centre = {
						lat: geometry.coordinates[1],
						lon: geometry.coordinates[0]
					};
					break;
					
				case 'LineString':
					var longitudes = [];
					var latitudes = [];
					$.each (geometry.coordinates, function (index, lonLat) {
						longitudes.push (lonLat[0]);
						latitudes.push (lonLat[1]);
					});
					centre = {
						lat: ((Math.max.apply (null, latitudes) + Math.min.apply (null, latitudes)) / 2),
						lon: ((Math.max.apply (null, longitudes) + Math.min.apply (null, longitudes)) / 2)
					};
					break;
					
				case 'MultiLineString':
				case 'Polygon':
					var longitudes = [];
					var latitudes = [];
					$.each (geometry.coordinates, function (index, line) {
						$.each (line, function (index, lonLat) {
							longitudes.push (lonLat[0]);
							latitudes.push (lonLat[1]);
						});
					});
					centre = {
						lat: ((Math.max.apply (null, latitudes) + Math.min.apply (null, latitudes)) / 2),
						lon: ((Math.max.apply (null, longitudes) + Math.min.apply (null, longitudes)) / 2)
					};
					break;
			}
			
			// Return the centre
			return centre;
		},
		
		
		// Function to show the data for a layer
		showCurrentData: function (layerId, data)
		{
			// If the layer has lines or polygons, reduce to point
			$.each (data.features, function (index, feature) {
				if (feature.geometry.type != 'Point') {
					data.features[index].geometry = {
						type: 'Point',
						coordinates: streetfocus.getCentre (feature.geometry)
					}
				}
			});
			
			// Set the data
			_map.getSource (layerId).setData (data);
		},
		
		
		// Function to upper-case the first character
		ucfirst: function (string)
		{
			if (typeof string !== 'string') {return string;}
			return string.charAt(0).toUpperCase() + string.slice(1);
		},
		
		
		// Function to make data entity-safe
		htmlspecialchars: function (string)
		{
			if (typeof string !== 'string') {return string;}
			return string.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		},
		
		
		// Function to truncate a string
		truncateString: function (string, length)
		{
			if (string.length > length) {
				string = string.substring (0, length) + '…';
			}
			return string;
		}
	};
	
} (jQuery));

