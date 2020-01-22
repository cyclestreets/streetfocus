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
			// Add the planning applications layer, e.g. /api/applics/geojson?limit=30&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076&recent=188
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
			
			// Add the planning applications layer, e.g. /api/applics/geojson?limit=30&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076&recent=188
			var apiBaseUrl = _settings.planitApiBaseUrl + '/applics/geojson';
			var parameters = {
				recent:	200
			};
			
			// Add the data layer
			streetfocus.addLayer (layerId, apiBaseUrl, parameters);
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
					'circle-color': '#ffc300'
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

