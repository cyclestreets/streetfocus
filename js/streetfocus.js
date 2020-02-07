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
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Google Maps API key
		gmapApiKey: 'YOUR_GOOGLEMAPS_API_KEY',
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: {
			latitude: 52.2053,
			longitude: 0.1218,
			zoom: 17
		}
	};
	
	
	// Internal class properties
	var _map = null;
	var _isTouchDevice;
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
	var _keyTypes = [
		'Design and Access Statement',
	];
			
	
	// Actions creating a map
	var _mapActions = ['map', 'proposals'];
	
	
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
			
			// Determine if the device is a touch device
			_isTouchDevice = streetfocus.isTouchDevice ();
			
			// Prevent viewport zooming, which is problematic for iOS Safari
			streetfocus.preventViewportZooming ();
			
			// Create mobile navigation
			streetfocus.createMobileNavigation ();
			
			// Create the map for a map action page
			if (_mapActions.includes (action)) {
				streetfocus.createMap (_settings.defaultLocation, _settings.defaultTileLayer);
				if (typeof streetfocus[action] == 'function') {
					_map.on ('load', function () {
						streetfocus[action] ();
					});
				}
			} else {
				
				// Run the defined action
				if (typeof streetfocus[action] == 'function') {
					streetfocus[action] ();
				}
			}
		},
		
		
		// Function to determine if the device is a touch device
		isTouchDevice: function ()
		{
			// See https://stackoverflow.com/a/13470899/180733
			return 'ontouchstart' in window || navigator.msMaxTouchPoints;		// https://stackoverflow.com/a/13470899/180733
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
			// Add geocoder control
			streetfocus.search ('cyclestreets,planit', '/map/');
			
			// Add geolocation
			$('#geolocation, #staticmap a').on ('click', function (e) {
				e.preventDefault ();	// Prevent link
				
				// If not supported, treat as link to the map page
				if (!navigator.geolocation) {
					window.location.href = '/map/';
					return;
				}
				
				// Locate the user
				navigator.geolocation.getCurrentPosition (
					function (position) {
						streetfocus.mapPageLink (position.coords.longitude, position.coords.latitude);
					},
					function (error) {		// E.g. geolocation denied
						window.location.href = '/map/';
						return;
					}
				);
			});
		},
		
		
		// Planning applications
		map: function ()
		{
			// Add geocoder control
			streetfocus.search ('cyclestreets,planit');
			
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
			
			// Close x button
			$('#filtering .close').click(function () {
				$('#filtering').fadeToggle ();
			});
			
			// Add implied close by clicking on remaining map slither
			_map.on ('click', function (e) {
				if ($('#filtering').is(':visible')) {
					$('#filtering').fadeToggle ();
				}
			});
			
			// Set checkbox colours
			var value;
			$.each ($("input[name='app_type']"), function () {
				value = $(this).val ();
				$(this).parent().parent().css ('background-color', _colours[layerId].values[value]);		// Two parents, as label surrounds
			});
			
			/*
			// Auto-close panel
			$('#filtering input').click (function (e) {
				$('#filtering').fadeToggle ();
			});
			*/
			
			// Add the data layer
			streetfocus.addLayer (layerId, apiBaseUrl, parameters, '#filtering');
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/allCambridgeCollisions.geojson', layerId, 16);
		},
		
		
		// Function to perform a form scan for values
		scanForm: function (path)
		{
			// Start a set of parameters
			var parameters = {};
			
			// Scan form widgets
			$(path + ' :input').each(function() {
				var name = $(this).attr('name');
				var value = $(this).val();
				if (this.checked) {
					if (!parameters.hasOwnProperty(name)) {parameters[name] = [];}	// Initialise if required
					parameters[name].push (value);
				}
			});
			
			// Join each parameter value by comma delimeter
			$.each (parameters, function (name, values) {
				parameters[name] = values.join(',');
			});
			
			// Return the values
			return parameters;
		},
		
		
		// Function to populate the popup
		populatePopupPlanningapplications: function (element, feature)
		{
			// Get the fuller data, syncronously
			// #!# Need to restructure calling code to avoid syncronous request
			var url = feature.properties.link + 'geojson';		// Contains fuller data at the application level
			$.ajax ({
				url: url,
				dataType: 'json',
				async: false,
				success: function (data, textStatus, jqXHR) {
					feature = data;		// Overwrite with this more detailed version
				}
			});
			
			// Get the centre-point of the geometry
			var centre = streetfocus.getCentre (feature.geometry);
			
			// Create the all documents link
			// #!# Other vendors needed
			var allDocumentsReplacements = {
				'activeTab=summary': 'activeTab=documents'
			}
			var allDocumentsUrl = feature.properties.url;
			$.each (allDocumentsReplacements, function (key, value) {
				allDocumentsUrl = allDocumentsUrl.replace(key, value);
			});
			
			// Determine the key documents list
			var keyDocumentsHtml = streetfocus.keyDocuments (feature.properties.docs);
			
			// Populate the HTML content
			$(element + ' p.applicationId').html (feature.properties.uid);
			$(element + ' p.officialplans a').attr ('href', feature.properties.url);
			$(element + ' ul.status li.state').text (feature.properties.app_state + ' application');
			$(element + ' ul.status li.size').text (feature.properties.app_size + ' development');
			$(element + ' ul.status li.type').text (feature.properties.app_type);
			$(element + ' p.date span.type').text ( (feature.properties.app_state == 'Undecided' ? 'Deadline' : 'Date closed') );
			$(element + ' p.date span.when').text ('X weeks from ' + feature.properties.start_date);
			$(element + ' .title').html (streetfocus.htmlspecialchars (streetfocus.truncateString (feature.properties.description, 40)));
			$(element + ' p.description').html (streetfocus.htmlspecialchars (feature.properties.description));
			$(element + ' div.documents ul').html (keyDocumentsHtml);
			$(element + ' p.alldocuments a').attr ('href', allDocumentsUrl);
			$(element + ' p.address').html (streetfocus.htmlspecialchars (feature.properties.address));
			$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
			
			// For IDOX-based areas, work around the cookie bug
			streetfocus.idoxWorkaroundCookie (allDocumentsUrl, feature.properties.name);
		},
		
		
		// Workaround for IDOX-based areas, requiring a cookie for the main documents list first
		idoxWorkaroundCookie (allDocumentsUrl, applicationId)
		{
			// End if not IDOX
			if (!allDocumentsUrl.search(/activeTab=documents/)) {return;}
			
			// Intercept document clicks, so that the main page can be loaded first
			$('body').on ('click', 'div.documents ul li a', function (e) {
				
				// Do not run if the workaround has already been applied
				var cookieName = 'idoxWorkaround';
				var cookieValue;
				if (cookieValue = streetfocus.getCookie (cookieName)) {
					if (cookieValue == applicationId) {
						return;
					}
				}
				
				// Open the main all documents URL, and self-close after 5 seconds (assumed to be enough time - cannot detect onload for another site)
				var newWindow = window.open (allDocumentsUrl, 'idoxWorkaround', 'toolbar=1,location=1,directories=1,status=1,menubar=1,scrollbars=1,resizable=1');
				window.focus ();	// Regain focus of main window
				setTimeout(function () {
					streetfocus.setCookie (cookieName, applicationId, 1);
					newWindow.close();
				}, 5000);
				
				// Browsers will probably show a popup warning, as second link; users can then accept or just retry the link
			});
		},
		
		
		// Helper function to pick out key documents
		keyDocuments: function (documents)
		{
			// Return empty array if none
			if (!documents) {return [];}
			
			// Start an list of documents to return, ordered by key type
			var keyDocuments = [];
			$.each (_keyTypes, function (typeIndex, type) {
				$.each (documents, function (index, document) {
					if (document.doc_type == type) {
						keyDocuments.push (documents[index]);
					}
				});
			});
			
			// Convert to HTML
			var listItems = [];
			$.each (keyDocuments, function (index, document) {
				listItems.push ('<li><a href="' + document.doc_url + '" target="_blank">' + document.doc_type + '</li>');
			});
			var listItemsHtml = listItems.join ("\n");
			
			// Return the key documents list
			return listItemsHtml;
		},
		
		
		// Proposals
		proposals: function ()
		{
			// Add geocoder control
			streetfocus.search ('cyclescape,cyclestreets');
			
			// Set the layer ID
			var layerId = 'proposals';
			
			// Add the proposals layer, e.g. /api/issues.json?page=1&per_page=100&bbox=-0.127902%2C51.503486%2C-0.067091%2C51.512086
			var apiBaseUrl = _settings.cyclescapeApiBaseUrl + '/issues.json';
			var parameters = {
				page:		1,
				per_page:	200
			};
			streetfocus.addLayer (layerId, apiBaseUrl, parameters);
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/allCambridgeCollisions.geojson', layerId, 16);
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
			
			// Add navigation (+/-/pitch) controls
			_map.addControl(new mapboxgl.NavigationControl (), 'top-left');
			
			// Add geolocation control
			streetfocus.geolocation ();
			
			// Add buildings
			streetfocus.addBuildings ();
			
			// Enable pitch gestures
			streetfocus.enablePitchGestures ();
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
		
		
		// Buildings layer; see: https://www.mapbox.com/mapbox-gl-js/example/3d-buildings/
		addBuildings: function ()
		{
			// The 'building' layer in the mapbox-streets vector source contains building-height data from OpenStreetMap.
			_map.on('style.load', function() {

				// Get the layers in the source style
				var layers = _map.getStyle().layers;

				// Ensure the layer has buildings, or end
				if (!streetfocus.styleHasLayer (layers, 'building')) {return;}

				// Insert the layer beneath any symbol layer.
				var labelLayerId;
				var i;
				for (i = 0; i < layers.length; i++) {
					if (layers[i].type === 'symbol' && layers[i].layout['text-field']) {
						labelLayerId = layers[i].id;
						break;
					}
				}

				// Add the layer
				_map.addLayer ({
					'id': '3d-buildings',
					'source': 'composite',
					'source-layer': 'building',
					'filter': ['==', 'extrude', 'true'],
					'type': 'fill-extrusion',
					'minzoom': 15,
					'paint': {
						'fill-extrusion-color': '#aaa',

						// Use an 'interpolate' expression to add a smooth transition effect to the buildings as the user zooms in
						'fill-extrusion-height': [
							"interpolate", ["linear"], ["zoom"],
							15, 0,
							15.05, ["get", "height"]
						],
						'fill-extrusion-base': [
							"interpolate", ["linear"], ["zoom"],
							15, 0,
							15.05, ["get", "min_height"]
						],
						'fill-extrusion-opacity': 0.6
					}
				}, labelLayerId);
			});
		},


		// Function to test whether a style has a layer
		styleHasLayer: function (layers, layerName)
		{
			// Ensure the layer has buildings, or end
			var i;
			for (i = 0; i < layers.length; i++) {
				if (layers[i].id == layerName) {
					return true;
				}
			}

			// Not found
			return false;
		},


		// Enable pitch gesture handling
		// See: https://github.com/mapbox/mapbox-gl-js/issues/3405#issuecomment-449059564
		enablePitchGestures: function ()
		{
			// Only enable on a touch device
			if (!_isTouchDevice) {return;}
			
			// Two-finger gesture on mobile for pitch; see: https://github.com/mapbox/mapbox-gl-js/issues/3405#issuecomment-449059564
			_map.on ('touchstart', function (data) {
				if (data.points.length == 2) {
					var diff = Math.abs(data.points[0].y - data.points[1].y);
					if (diff <= 50) {
						data.originalEvent.preventDefault();	//prevent browser refresh on pull down
						_map.touchZoomRotate.disable();	 //disable native touch controls
						_map.dragPan.disable();
						self.dpPoint = data.point;
						self.dpPitch = _map.getPitch();
					}
				}
			});
			
			_map.on ('touchmove', function (data) {
				if (self.dpPoint) {
					data.preventDefault();
					data.originalEvent.preventDefault();
					var diff = (self.dpPoint.y - data.point.y) * 0.5;
					_map.setPitch(self.dpPitch + diff);
				}
			});
			
			_map.on ('touchend', function (data) {
				 if (self.dpPoint) {
					_map.touchZoomRotate.enable();
					_map.dragPan.enable();
				}
				self.dpPoint = null;
			});
			
			_map.on ('touchcancel', function (data) {
				if (self.dpPoint) {
					_map.touchZoomRotate.enable();
					_map.dragPan.enable();
				}
				self.dpPoint = null;
			});
		},
		
		
		// Wrapper function to add a search control
		search: function (sources, targetUrl)
		{
			// Geocoder URL
			var geocoderApiUrl = '/api/search?sources=' + sources;
			
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo ('#geocoder input', {
				sourceUrl: geocoderApiUrl,
				select: function (event, ui) {
					
					// Parse the BBOX
					var bbox = ui.item.feature.properties.bbox.split(',');	// W,S,E,N
					
					// If there is a target URL, go to that
					if (targetUrl) {
						var longitude = (parseFloat(bbox[0]) + parseFloat(bbox[2])) / 2;
						var latitude  = (parseFloat(bbox[1]) + parseFloat(bbox[3])) / 2;
						streetfocus.mapPageLink (longitude, latitude);
						return;
						
					// Otherwise, pan the map
					} else {
						_map.fitBounds(bbox, {maxZoom: 17});
						event.preventDefault();
					}
				}
			});
		},
		
		
		// Function to go the map page
		mapPageLink: function (longitude, latitude)
		{
			var zoom = 13;		// #!# Currently fixed - need to compute dynamically, e.g. https://github.com/mapbox/mapbox-unity-sdk/issues/1125
			var targetUrl = '/map/' + '#' + zoom + '/' + latitude.toFixed(6) + '/' + longitude.toFixed(6);
			window.location.href = targetUrl;
		},
		
		
		// Function to add a data layer to the map
		addLayer: function (layerId, apiBaseUrl, parameters, filteringFormPath, callback)
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
			
			// Add a loading control
			streetfocus.createControl ('loading', 'top-left');
			$('#loading').html ('<img src="/images/ui-anim_basic_16x16.gif" />');
			
			// Get the data
			streetfocus.addData (layerId, apiBaseUrl, parameters, filteringFormPath, callback);
			
			// Register to update on map move and form changes
			_map.on ('moveend', function (e) {
				streetfocus.addData (layerId, apiBaseUrl, parameters, filteringFormPath, callback);
			});
			
			// If a form is set to be scanned, update on change
			if (filteringFormPath) {
				$(filteringFormPath + ' :input').click (function (e) {
					streetfocus.addData (layerId, apiBaseUrl, parameters, filteringFormPath, callback);
				});
			}
		},
		
		
		// Function to load the data for a layer
		addData: function (layerId, apiBaseUrl, parameters, filteringFormPath, callback)
		{
			// Get the map BBOX
			var bbox = _map.getBounds();
			bbox = bbox.getWest() + ',' + bbox.getSouth() + ',' + bbox.getEast() + ',' + bbox.getNorth();
			bbox = streetfocus.reduceBboxAccuracy (bbox);
			parameters.bbox = bbox;
			
			// Obtain the form values
			if (filteringFormPath) {
				var formParameters = streetfocus.scanForm (filteringFormPath);
				$.extend (parameters, formParameters);
			}
			
			// Show loading spinner, if not already visible
			$('#loading').show ();
			
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
					streetfocus.showCurrentData (layerId, data, callback);
					$('#loading').hide ();
				}
			});
		},
		
		
		// Function to add a heatmap layer
		addHeatmapLayer: function (layerId, datasource, beforeLayerId, preferredZoom)
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
			}, beforeLayerId);
			
			// Handle visibility
			$('#' + layerId).click (function (e) {
				var visibility = _map.getLayoutProperty (layerId, 'visibility');
				if (visibility === 'visible') {
					_map.setLayoutProperty (layerId, 'visibility', 'none');
				} else {
					if (_map.getZoom () > preferredZoom) {
						_map.flyTo ({zoom: preferredZoom});
					}
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
		showCurrentData: function (layerId, data, callback)
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
			
			// Run the callback, if required, to filter data
			if (callback) {
				data = callback (data);
			}
			
			// Set the data
			_map.getSource (layerId).setData (data);
		},
		
		
		// Function to create a control in a corner
		// See: https://www.mapbox.com/mapbox-gl-js/api/#icontrol
		createControl: function (id, position, className)
		{
			function myControl() { }
			
			myControl.prototype.onAdd = function(_map) {
				this._map = map;
				this._container = document.createElement('div');
				this._container.setAttribute ('id', id);
				this._container.className = 'mapboxgl-ctrl-group mapboxgl-ctrl local';
				if (className) {
					this._container.className += ' ' + className;
				}
				return this._container;
			};
			
			myControl.prototype.onRemove = function () {
				this._container.parentNode.removeChild(this._container);
				this._map = undefined;
			};
			
			// Instiantiate and add the control
			_map.addControl (new myControl (), position);
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
		},
		
    	
		setCookie: function (key, value, expiry) {
			var expires = new Date();
			expires.setTime (expires.getTime() + (expiry * 24 * 60 * 60 * 1000));
			document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
		},
		
		
		getCookie: function (key) {
			var keyValue = document.cookie.match ('(^|;) ?' + key + '=([^;]*)(;|$)');
			return keyValue ? keyValue[2] : null;
		}
	};
	
} (jQuery));

