// StreetFocus implementation code

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window */

const streetfocus = (function ($) {
	
	'use strict';
	
	// Settings defaults
	const _settings = {
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: '17/52.2053/0.1218/0/0',	// Zoom, lat, lon, pitch, bearing
		
		// CycleStreets API
		cyclestreetsApiBaseUrl: 'https://api.cyclestreets.net',
		cyclestreetsApiKey: null,
		
		// PlanIt API
		planitStaleAreas: 'https://www.planit.org.uk/api/areas/geojson?area_type=stale',
		planitStaleAreasExclude: {
			'area_type': 'Other Planning Entity'
		},
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Google Maps API key
		gmapApiKey: 'YOUR_GOOGLEMAPS_API_KEY',
		
		// Minimum zoom to show planning applications; this can be reduced as API performance is improved
		minZoom: 12,
		
		// Forced location
		setLocation: false,
		
		// Page-specific data
		pageData: {}
	};
	
	
	// Internal class properties
	let _map = null;
	let _action;
	let _actionUrl;
	let _id;
	let _documentTitle;
	let _isTouchDevice;
	let _mapZoomEnough = false;
	
	// Definitions
	const _colours = {
		planningapplications: {
			field: 'type',
			values: {
				'Full':			'#007cbf',
				'Outline':		'blue',
				'Amendment':	'orange',
				'Heritage':		'brown',
				'Trees':		'green',
				'Advertising':	'red',
				'Telecoms':		'purple',
				'Other':		'gray',
				'Conditions':	'#aed6f1'
			}
		}
	};
	const _sizes = {
		planningapplications: {
			field: 'size',
			values: {
				'Small':		11,
				'Medium':		18,
				'Large':		25
			}
		}
	};
	const _states = {
		planningapplications: {
			field: 'state',
			values: {
				'Undecided':	1,
				'Permitted':	0.3,
				'Conditions':	1,
				'Rejected':		0.3,
				'Withdrawn':	0.1,
				'Other':		1
			}
		}
	};
	const _keyTypes = [		// These are categories defined at the PlanIt end, not name matches locally
		'Design and Access Statement',
	];
			
	
	// Actions creating a map
	const _mapActions = ['planningapplications', 'ideas', 'addidea', 'my', 'addmonitor'];
	
	
	return {
		
		// Main function
		initialise: function (config, action, actionUrl, id)
		{
			// Merge the configuration into the settings
			$.each (_settings, function (setting, value) {
				if (config.hasOwnProperty(setting)) {
					_settings[setting] = config[setting];
				}
			});
			
			// Set the action, the action's URL, and any ID
			_action = action;
			_actionUrl = actionUrl;
			_id = id;
			
			// Get the original title
			_documentTitle = document.title;
			
			// Determine if the device is a touch device
			_isTouchDevice = streetfocus.isTouchDevice ();
			
			// Prevent viewport zooming, which is problematic for iOS Safari
			streetfocus.preventViewportZooming ();
			
			// Create mobile navigation
			streetfocus.createMobileNavigation ();
			
			// Add tooltip support
			streetfocus.tooltips ();
			
			// Create the map for a map action page
			if (_mapActions.includes (action)) {
				if ($('#map').length > 0) {		// Check map present on the page; may be removed if e.g. message shown instead
					streetfocus.createMap ();
					if (typeof streetfocus[action] == 'function') {
						_map.on ('load', function () {
							streetfocus[action] ();
						});
					}
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
			if (!(/iPad|iPhone|iPod/.test (navigator.userAgent))) {return;}		// Apply only on iOS
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
			$('#mobilenav #close').click (function () {
				$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
			});
			
			// Enable implicit click/touch on map as close menu
			$('main, footer').click (function () {
				if ($('#mobilenav').is(':visible')) {
					$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
				};
			});
			
			// Enable closing menu on slide right
			$('#mobilenav').on ('swiperight', function () {
				$('#mobilenav').hide ('slide', {direction: 'right'}, 250);
			});
		},
		
		
		// Function to add tooltips, using the title value
		tooltips: function ()
		{
			// Use jQuery tooltips; see: https://jqueryui.com/tooltip/
			$('#filtering, #details').tooltip ({
				track: true
			});
		},


		// Home page
		home: function ()
		{
			// Add geocoder control
			streetfocus.search ('geocoder,planit', '/map/');
			
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
		planningapplications: function ()
		{
			// Add geocoder control
			streetfocus.search ('geocoder,planit');
			
			// Set parameters for the planning applications layer, e.g. /v2/planningapplications.locations?type=Full,Trees&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076
			const apiUrl = _settings.cyclestreetsApiBaseUrl + '/v2/planningapplications.locations';
			const parameters = {
				key: _settings.cyclestreetsApiKey
			};
			
			// Initialise the filtering form
			streetfocus.initialiseFilteringForm ();
			
			// Handle filtering panel visibility
			$('#filter').click (function (e) {
				$('#filtering').fadeToggle ();
				e.preventDefault ();
			});
			
			// Add close methods (X button, click on map, escape key)
			streetfocus.panelClosing ('#filtering', '#filter');
			
			// Add minZoom state handler
			streetfocus.zoomState ();
			
			// Add the data layer
			streetfocus.addLayer (apiUrl, parameters, '#filtering', null, 'id', 'description');
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/data/allCambridgeCollisions.json', 16);
			
			// Add stale areas layer
			const staleAreasMessageHtml = "<p>Warning: data in this area is currently not being updated because the local council's website is preventing updates. Please see our <a href=\"/about/#stale\">FAQ</a> for details.</p>";
			streetfocus.addStaticPolygonLayer (_settings.planitStaleAreas, 'stale', staleAreasMessageHtml, _settings.planitStaleAreasExclude);
		},
		
		
		// Function to initialise the filtering form
		initialiseFilteringForm: function ()
		{
			// If a filter state cookie is set, set the filtering form values on initial load
			streetfocus.filteringCookieInitialValues ();
			
			// Set checkbox colours
			$.each ($("input[name='type[]']"), function () {
				const value = $(this).val ();
				$(this).parent().parent().css ('background-color', _colours['planningapplications'].values[value]);		// Two parents, as label surrounds
			});
			
			// Convert year range to slider UI
			streetfocus.yearRangeSlider ();
		},
		
		
		// Function to convert year range to slider UI
		yearRangeSlider: function ()
		{
			// Get handles to since/until fields
			const sinceField = $('input[name="since"]');
			const untilField = $('input[name="until"]');
			
			// Add slider; see: https://jqueryui.com/slider/#range
			$('#dateslider').slider ({
				range: true,
				min: parseInt (sinceField.attr ('min')),
				max: parseInt (untilField.attr ('max')),
				values: [parseInt (sinceField.val ()), parseInt (untilField.val ())],
				slide: function (event, ui) {	// Triggered on every mouse move during slide
					sinceField.val (ui.values[0]);
					untilField.val (ui.values[1]);
				},
				stop: function (event, ui) {	// Triggered after the user slides a handle
					sinceField.trigger ('change');	// Trigger change event; see: https://stackoverflow.com/a/3179392/
				}
			});
			
			// Prevent direct changes to the input value; this also changes the style to make these appear as text
			$(sinceField).attr ('readonly', 'readonly');
			$(untilField).attr ('readonly', 'readonly');
		},
		
		
		// Function to get the filtering option values available
		getFilteringOptions: function ()
		{
			// Loop through each input
			const filteringOptions = {};
			$('#filtering :input').each (function () {
				let name = $(this).attr('name');
				const value = $(this).val();
				
				// Extract the name and value, and create a key for the name if not already present
				const type = $(this).attr('type');
				switch (type) {
					case 'checkbox':
						name = name.replace('[]', '');
						if (!filteringOptions.hasOwnProperty (name)) {filteringOptions[name] = [];}		// Initialise array if required
						filteringOptions[name].push (value);
						break;
					case 'number':
						filteringOptions[name] = value;
						break;
				}
			});
			
			// Return the registry
			return filteringOptions;
		},
		
		
		// Function to set the filtering UI initial values; this has to be done after load (rather than server side), so that the reset button will properly reset
		// #!# This is a bit slow because the whole function is running inside map load
		filteringCookieInitialValues: function ()
		{
			// Detect cookie, or end
			const filteringCookie = streetfocus.getCookie ('filtering');
			if (filteringCookie) {
				const filteringDefaults = JSON.parse (filteringCookie);
				streetfocus.setFilteringUiValues (filteringDefaults);
			}
		},
		
		
		// Function to set the filtering UI values
		setFilteringUiValues: function (filteringDefaults)
		{
			// Determine all available values in the checkbox sets
			const filteringOptions = streetfocus.getFilteringOptions ();
			
			// Reset all
			$('#filtering input:checkbox').prop ('checked', false);
			$('#filtering input[type="number"]').val ('');
			
			// Loop through each checkbox set / input
			$.each (filteringOptions, function (parameter, allOptions) {
				
				// Detect whether the parameter is for a scalar type, rather than, e.g. checkboxes
				const inputType = $('input[name="' + parameter + '"').attr ('type');			// Checkboxes like name="foo[]", or <select> element will therefore not match
				const isScalarInputType = (inputType == 'text' || inputType == 'number');
				
				// Scalar input types
				let parameterValue;
				if (isScalarInputType) {
					
					// If this parameter (e.g. type) is present in the defaults, use that; else set empty
					parameterValue = (filteringDefaults.hasOwnProperty (parameter) ? filteringDefaults[parameter] : '');
					
					// Set the value
					$('#filtering input[name="' + parameter + '"]').val (parameterValue);
					
				// Array types, e.g. checkboxes
				} else {
					
					// If this parameter (e.g. type) is present in the defaults, use that; blank means no filtering, i.e. all options
					parameterValue = (filteringDefaults.hasOwnProperty (parameter) ? filteringDefaults[parameter] : allOptions);
					
					// Set each selected value for its checkbox
					$.each (parameterValue, function (index, subValue) {
						$('#filtering input[name="' + parameter + '[]"][value="' + subValue + '"]').trigger ('click');
					});
				}
			});
		},
		
		
		// Function to handle panel close methods
		panelClosing: function (path, mobileButton)
		{
			// If this layer has a mobile button defined, but is not visible (i.e. the user is desktop view), take no action
			if (mobileButton) {
				if (!$(mobileButton).is(':visible')) {
					return;
				}
			}
			
			// Close x button
			$('body').on ('click', path + ' .close', function (e) {
				$(path).fadeToggle ();
				streetfocus.resetUrl ();
				e.preventDefault ();
			});
			
			// Add implied close by clicking on remaining map slither
			_map.on ('click', function (e) {
				if (e.defaultPrevented) {return;}	// See: https://stackoverflow.com/a/61366984
				if ($(path).is(':visible')) {
					$(path).fadeToggle ();
					streetfocus.resetUrl ();
				}
			});
			
			// Register escape key handler to close popups
			$(document).keyup (function (e) {
				if (e.keyCode === 27) {
					$(path).hide ();
					streetfocus.resetUrl ();
				}
			});
		},
		
		
		// Function to reset the URL and title using HTML5 History pushState
		resetUrl: function ()
		{
			const path = _actionUrl;
			const title = _documentTitle;
			streetfocus.updateUrl (path, title);
		},
		
		
		// Function to handle minZoom requirement
		zoomState: function ()
		{
			// Handler function to set state
			const setZoomState = function () {
				_mapZoomEnough = (_map.getZoom () >= _settings.minZoom);
				$('#mappanel').toggleClass ('zoomedout', !_mapZoomEnough);
			};
			
			// Set state on start and upon map move
			setZoomState ();
			_map.on ('moveend', function () {
				setZoomState ();
			});
			
			// If zoomed out, make a click on the map be an implied zoom in
			_map.on ('click', function (e) {
				if (!_mapZoomEnough) {
					const newZoom = Math.min (_map.getZoom () + 2, _settings.minZoom);
					_map.flyTo ({zoom: newZoom, center: e.lngLat});		// #!# Centring issue
				}
			});
		},
		
				
		// Function to perform a form scan for values
		scanForm: function (path)
		{
			// Start a set of parameters
			const parameters = {};
			
			// Scan form widgets
			$(path + ' :input').not ('[type="reset"]').each (function() {
				const name = $(this).attr('name').replace('[]', '');
				const value = $(this).val();
				
				const type = $(this).attr('type');
				switch (type) {
					case 'checkbox':
						if (this.checked) {
							if (!parameters.hasOwnProperty(name)) {parameters[name] = [];}	// Initialise if required
							parameters[name].push (value);
						}
						break;
					case 'number':
						if (value.length > 0) {
							parameters[name] = value;
						}
						break;
				}
			});
			
			// Set the main filtering button state to indicate whether filters are in place
			if ($.isEmptyObject (parameters)) {
				$('#filter').removeClass ('filtersenabled');
			} else {
				$('#filter').addClass ('filtersenabled');
			}
			
			// Set a cookie containing the parameters, to provide state
			const parametersString = JSON.stringify (parameters);
			streetfocus.setCookie ('filtering', parametersString, 7);
			
			// Join each parameter value by comma delimeter
			$.each (parameters, function (name, values) {
				if (Array.isArray (values)) {
					parameters[name] = values.join(',');
				}
			});
			
			// Deal with date fields, which need to be converted from year to date
			if (parameters.hasOwnProperty ('since')) {
				parameters.since += '-01-01';
			}
			if (parameters.hasOwnProperty ('until')) {
				parameters.until += '-12-31';
			}
			
			// Return the values
			return parameters;
		},
		
		
		// Function to populate the planning applications map popup
		populatePopupPlanningapplications: function (element, feature, uniqueIdField)
		{
			// Get the fuller data, syncronously
			// #!# Need to restructure calling code to avoid syncronous request
			const url = '/api/planningapplication?id=' + feature.properties[uniqueIdField];		// Contains fuller data at the application level
			$.ajax ({
				url: url,
				dataType: 'json',
				async: false,
				success: function (data, textStatus, jqXHR) {
					feature = data.features[0];		// Overwrite with this more detailed version
				}
			});
			
			// Get the centre-point of the geometry
			const centre = streetfocus.getCentre (feature.geometry);
			
			// Create the all documents link
			// #!# Other vendors needed
			const vendorUiReplacements = {
				'activeTab=summary': {
					'documents': 'activeTab=documents',
					'comments': 'activeTab=makeComment'
				}
			}
			const vendorUiLinkBase = feature.properties.url;
			const vendorLinks = {};
			$.each (vendorUiReplacements, function (key, values) {
				$.each (values, function (type, value) {
					vendorLinks[type] = vendorUiLinkBase.replace (key, value);
				});
			});
			
			// Determine the key documents list
			const keyDocumentsHtml = streetfocus.keyDocuments (feature.properties.documents, vendorLinks.documents);
			
			// Determine the matching ideas list
			const matchingIdeasHtml = streetfocus.matchingIdeas (feature.properties._ideas);
			
			// Only show ideas matches for medium/large applications
			if (feature.properties.size == 'Medium' || feature.properties.size == 'Large') {
				$(element + ' div.matches').show ();
			} else {
				$(element + ' div.matches').hide ();
			}
			
			// If there are matching ideas, enable the internal div, otherwise confirm none
			if (matchingIdeasHtml) {
				$(element + ' div.matches div.hasmatches').show ();
				$(element + ' div.matches div.nomatches').hide ();
			} else {
				$(element + ' div.matches div.hasmatches').hide ();
				$(element + ' div.matches div.nomatches').show ();
			}
			
			// Hide the call to action if already decided/withdrawn/etc.
			if (feature.properties.state == 'Undecided') {
				$(element + ' p.link').show ();
			} else {
				$(element + ' p.link').hide ();
			}
			
			// Determine state image
			let stateImage = '';
			if (feature.properties.state == 'Permitted') {stateImage = '<img src="/images/permitted.png" /> ';}
			if (feature.properties.state == 'Rejected') {stateImage = '<img src="/images/rejected.png" /> ';}
			
			// Populate the HTML content
			$(element + ' p.applicationid').html (feature.properties.uid);
			$(element + ' p.link a').attr ('href', vendorLinks.comments);
			$(element + ' p.officialplans a').attr ('href', feature.properties.url);
			$(element + ' ul.status li.state').html (stateImage + feature.properties.state + ' application');
			$(element + ' ul.status li.size').text ((feature.properties.size ? feature.properties.size + ' development' : 'Unknown size'));
			$(element + ' ul.status li.type').text (feature.properties.type);
			$(element + ' p.date').html (streetfocus.consultationDate (feature));
			$(element + ' .title').html (streetfocus.htmlspecialchars (streetfocus.truncateString (feature.properties.description, 80)));
			$(element + ' div.description p').html (streetfocus.htmlspecialchars (feature.properties.description));
			$(element + ' div.documents ul').html (keyDocumentsHtml);
			$(element + ' p.alldocuments a').attr ('href', vendorLinks.documents);
			$(element + ' div.matches div.hasmatches ul').html (matchingIdeasHtml);
			$(element + ' p.address').html (streetfocus.htmlspecialchars (feature.properties.address));
			$(element + ' div.streetview').html ('<iframe id="streetview" loading="lazy" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
			
			// For IDOX-based areas, work around the cookie bug
			streetfocus.idoxWorkaroundCookie (vendorLinks.documents, feature.properties.name);
		},
		
		
		// Function to determine the consultation date
		consultationDate: function (feature)
		{
			// Define available fields in the data, and their labels
			const consultationDateFields = {
				'consultation_end_date'				: 'Consultation end date',
				'neighbour_consultation_end_date'	: 'Neighbour consultation end date',
				'site_notice_end_date'				: 'Site notice end date',
				'latest_advertisement_expiry_date'	: 'Latest advertisement expiry date'
			};
			
			// Determine the latest of the fields, allocating the date and the label
			let latestConsultationDate = '';	// String comparison will be done for each date field value
			let latestConsultationDateFieldLabel = 'Deadline';
			$.each (consultationDateFields, function (consultationDateField, consultationDateFieldLabel) {
				if (feature.properties.otherfields.hasOwnProperty (consultationDateField)) {
					if (feature.properties.otherfields[consultationDateField] > latestConsultationDate) {
						latestConsultationDate = feature.properties.otherfields[consultationDateField];
						latestConsultationDateFieldLabel = consultationDateFieldLabel;
					}
				}
			});
			
			// Convert the date to a human-readable string
			const latestConsultationDateFormatted = (latestConsultationDate ? new Date (latestConsultationDate).toDateString () : '?');
			
			// If the application is past, set the label to be closed
			if (feature.properties.state != 'Undecided') {
				latestConsultationDateFieldLabel = 'Date closed';
			}
			
			// Determine number of days before the consultation closes
			let daysRemaining = '';
			if (latestConsultationDate) {
				const today = new Date ();
				const closeDate = new Date(latestConsultationDate);
				const timeDifference = closeDate.setHours(0,0,0,0) - today.setHours(0,0,0,0);		// setHours forces each to midday and casts to Unix timestamp
				const daysDifference = timeDifference / (1000 * 3600 * 24);
				if (daysDifference >= 0) {
					switch (daysDifference) {
						case 0:  daysRemaining = ' (today)'; break;
						case 1:  daysRemaining = ' (tomorrow)'; break;
						default: daysRemaining = ' (' + daysDifference + ' days remaining)';
					}
				}
			}
			
			// Construct the string, as the label with the date
			const consultationDateString = latestConsultationDateFieldLabel + ':&nbsp; ' + latestConsultationDateFormatted + daysRemaining;
			
			// Return the result
			return consultationDateString;
		},
		
		
		// Workaround for IDOX-based areas, requiring a cookie for the main documents list first
		idoxWorkaroundCookie: function (allDocumentsUrl, applicationId)
		{
			// End if not IDOX
			if (!allDocumentsUrl.search(/activeTab=documents/)) {return;}
			
			// Intercept document clicks, so that the main page can be loaded first
			$('body').on ('click', 'div.documents ul li a', function (e) {
				
				// Do not run if the workaround has already been applied
				const cookieName = 'idoxWorkaround';
				const cookieValue = streetfocus.getCookie (cookieName);
				if (cookieValue) {
					if (cookieValue == applicationId) {
						return;
					}
				}
				
				// Obtain the clicked document URL
				const documentUrl = e.target.href;
				
				// Open both windows
				const newWindow = window.open (allDocumentsUrl, '_blank');
				let count = 0;
				let interval = setInterval (function () {
					count += 1;
					if (newWindow.document.readyState === 'complete') {
						
						// Now that the page has loaded, now wait for the cookie to be transferred, and then finally load the required document
						setTimeout (function () {
							newWindow.location.href = documentUrl;
							clearInterval (interval);	// Cancel timer
							streetfocus.setCookie (cookieName, applicationId, 1);	// Set the local cookie
						}, 3000);
						
					} else if (count >= 500) {
						clearInterval (interval);	// Cancel timer
					}
				}, 100);
			});
		},
		
		
		// Helper function to pick out key documents
		keyDocuments: function (documents, allLink)
		{
			// Return empty array if none
			if ($.isEmptyObject (documents)) {
				return '<p><em>Sorry, we don\'t have a record of the documents available for the application. Please check the <a href="' + streetfocus.htmlspecialchars (allLink) + '" target="_blank">page on the council\'s website</a>.</em></p>';
			}
			
			// Start an list of documents to return, ordered by key type
			const keyDocuments = [];
			$.each (documents, function (documentIndex, document) {
				if ($.inArray (document.type, _keyTypes) != -1) {
					keyDocuments.push (documents[documentIndex]);
				}
			});
			
			// Return empty array if no key documents matched
			if ($.isEmptyObject (keyDocuments)) {
				return '<p><em>Sorry, we were unable to work out which are the key documents from the <a href="' + streetfocus.htmlspecialchars (allLink) + '" target="_blank">full list on the council\'s website</a>.</em></p>';
			}
			
			// Convert to HTML
			const listItems = [];
			$.each (keyDocuments, function (index, document) {
				let listItem  = '<li>';
				listItem += '<a href="' + document.url + '" target="_blank">';
				listItem += streetfocus.htmlspecialchars (document.type);
				listItem += ' - ' + streetfocus.htmlspecialchars (document.title);
				listItem += ' &nbsp; <span>(' + streetfocus.htmlspecialchars (document.date) + ')</span>';
				listItem += '</a>';
				listItem += '</li>';
				listItems.push (listItem);
			});
			const listItemsHtml = listItems.join ("\n");
			
			// Return the list
			return listItemsHtml;
		},
		
		
		// Helper function to list matching ideas
		matchingIdeas: function (ideas)
		{
			// Return empty array if none
			if (!ideas) {return [];}
			
			// Convert to HTML
			const listItems = [];
			$.each (ideas, function (index, idea) {
				let listItem  = '<li>';
				listItem += '<a href="/ideas/' + idea.properties.moniker + '/" target="_blank">';
				listItem += streetfocus.htmlspecialchars (idea.properties.title);
				const date = new Date (idea.properties.when * 1000);
				listItem += ' &nbsp; <span>(' + streetfocus.htmlspecialchars (date.getFullYear ()) + ')</span>';
				listItem += '</a>';
				listItem += '</li>';
				listItems.push (listItem);
			});
			const listItemsHtml = listItems.join ("\n");
			
			// Return the list
			return listItemsHtml;
		},
		
		
		// Ideas
		ideas: function ()
		{
			// Add geocoder control
			streetfocus.search ('geocoder');
			
			// Define a callback function to filter out ideas which appear to be an imported planning application
			const callback = function (data) {
				let i = data.features.length;
				while (i--) {		// See: https://stackoverflow.com/a/9882349/180733
					if (data.features[i].properties.title.match(/^Planning application/)) {
						data.features.splice (i, 1);
					}
				}
				return data;
			}
			
			// Add the ideas layer, e.g. /api/ideas?bbox=-0.127902%2C51.503486%2C-0.067091%2C51.512086
			const apiBaseUrl = '/api/ideas';
			const parameters = {};
			streetfocus.addLayer (apiBaseUrl, parameters, null, callback, 'moniker', 'title');
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/data/allCambridgeCollisions.json', 16);
		},
		
		
		// Function to populate the popup
		populatePopupIdeas: function (element, feature, uniqueIdField /* ignored */)
		{
			// Get the centre-point of the geometry
			const centre = streetfocus.getCentre (feature.geometry);
			
			// Populate the static HTML
			$(element + ' p.applicationid span.source').text (feature.properties.source);
			$(element + ' p.applicationid span.id').text (feature.properties.id);
			$(element + ' p.applicationid span.date').text (new Date(feature.properties.when * 1000).toDateString());
			$(element + ' h2.title').text (feature.properties.title);
			$(element + ' p.link a').attr ('href', feature.properties.link);
			$(element + ' div.description').html (feature.properties.description);		// Will be paragraph(s) of HTML
			if (feature.properties.image !== null && feature.properties.image !== 'null') {
				$(element + ' p.image').show ();
				$(element + ' p.image img').attr ('src', feature.properties.image);
			} else {
				$(element + ' p.image').hide ();
			}
			//$(element + ' ul.categories').html ('<ul class="tags"><li>' + JSON.parse(feature.properties.categories).join('</li><li>') + '</li></ul>');
			//$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
		},
		
		
		// Add idea
		addidea: function ()
		{
			// Reset the pitch and bearing
			_map.setPitch (0);
			_map.setBearing (0);
			
			// Add draggable marker which writes to the form values
			streetfocus.formMarkerSetting ('#form_longitude', '#form_latitude');
		},
		
		
		// Function to create a draggable marker which writes to form values
		formMarkerSetting: function (longitudeField, latitudeField)
		{
			// Function to set the form map location
			const setFormLocation = function (lngLat)
			{
				// Set the form value; NB Using attr rather than .val() ensures the console representation is also correct
				$(longitudeField).attr ('value', lngLat.lng.toFixed(5) );
				$(latitudeField).attr ('value', lngLat.lat.toFixed(5) );
			}
			
			// Set the initial location, either from the form (e.g. due to posting incomplete form) or by the map's natural centre
			let initialLocation;
			if ($(longitudeField).val () && $(latitudeField).val ()) {
				initialLocation = {
					lng: parseFloat ($(longitudeField).val ()),
					lat: parseFloat ($(latitudeField).val ())
				};
			} else {
				initialLocation = _map.getCenter ();
			}
			setFormLocation (initialLocation);
			
			// Add the marker to the map, setting it as draggable
			const marker = new mapboxgl.Marker ({draggable: true, color: '#603'})
				.setLngLat (initialLocation)
				.addTo (_map);
			
			// If the marker is dragged or set to a different location, update the input value
			marker.on ('dragend', function (e) {
				const lngLat = marker.getLngLat ();
				setFormLocation (lngLat);
			});
			_map.on ('click', function (e) {
				marker.setLngLat (e.lngLat);	// Move the marker
				setFormLocation (e.lngLat);
			});
		},
		
		
		// Monitors (main page)
		my: function ()
		{
			// Get the data
			$.ajax ({
				url: '/api/monitors',
				dataType: 'json',
				error: function (jqXHR, error, exception) {
					if (jqXHR.statusText != 'abort') {
						const data = $.parseJSON(jqXHR.responseText);
						alert ('Error: ' + data.error);
					}
				},
				success: function (data, textStatus, jqXHR) {
					
					// Add the map data
					const layerId = 'monitors';
					_map.addLayer ({
						id: layerId,
						type: 'fill',
						source: {
							type: 'geojson',
							data: data
						},
						'paint': {
							'fill-color': 'brown',
							'fill-opacity': 0.4
						}
					});
					
					// Zoom to extents; see: https://stackoverflow.com/a/59453955/180733
					let bounds = data.features.reduce (function (bounds, feature) {
						if (!Array.isArray (feature.geometry.coordinates[0])) { 	// Point feature
							return bounds.extend (feature.geometry.coordinates);
						} else {
							return feature.geometry.coordinates.reduce (function (bounds, coord) {
								return bounds.extend (coord);
							}, bounds);
						}
					}, new mapboxgl.LngLatBounds());
					_map.fitBounds (bounds, {padding: 30});

					// Add popups
					_map.on ('click', layerId, function(e) {
						const feature = e.features[0];
						
						// Determine the coordinates
						const centre = streetfocus.getCentre (feature.geometry);
						const coordinates = [centre.lon, centre.lat];
						
						// Ensure that if the map is zoomed out such that multiple copies of the feature are visible, the popup appears over the copy being pointed to.
						while (Math.abs (e.lngLat.lng - coordinates[0]) > 180) {
							coordinates[0] += (e.lngLat.lng > coordinates[0] ? 360 : -360);
						}
						
						// Set the HTML content of the popup
						const filters = [];
						if (feature.properties.type !== 'null') {
							filters.push ('Type: ' + feature.properties.type.replace (', ', ' / ') + ' applications');
						}
						if (feature.properties.size != 'null') {
							filters.push ('Size: ' + feature.properties.size.replace (', ', ' / ') + ' applications');
						}
						let popupHtml = '<p>' + ($.isEmptyObject (filters) ? 'All planning applications in this area.' : 'Planning applications in this area, limited to:') + '</p>';
						$.each (filters, function (index, filter) {
							popupHtml += '<p>' + filter + '</p>';
						});
 						
						// Create the popup
						new mapboxgl.Popup ()
							.setLngLat (coordinates)
							.setHTML (popupHtml)
							.addTo (_map);
					});

					// Change the cursor to a pointer when the mouse is over the places layer, and change back to a pointer when it leaves
					_map.on ('mouseenter', layerId, function() {
						_map.getCanvas().style.cursor = 'pointer';
					});
					_map.on ('mouseleave', layerId, function() {
						_map.getCanvas().style.cursor = '';
					});
				}
			});
		},
		
		
		// Add monitor
		addmonitor: function ()
		{
			// Reset the pitch and bearing
			_map.setPitch (0);
			_map.setBearing (0);
			
			// Initialise the filtering form
			streetfocus.initialiseFilteringForm ();
			
			// Capture map location changes, saving these to the hidden input field
			const bbox = streetfocus.getBbox ();
			$('#bbox').val (bbox);
			_map.on ('moveend', function () {
				const bbox = streetfocus.getBbox ();
				$('#bbox').val (bbox);
			});
		},
		
		
		// Function to create the map and related controls
		createMap: function ()
		{
			// Determine default location
			const initialLocation = streetfocus.getInitialLocation ();
			
			// Create the map
			mapboxgl.accessToken = _settings.mapboxApiKey;
			_map = new mapboxgl.Map ({
				container: 'map',
				style: 'mapbox://styles/mapbox/streets-v11',
				center: [initialLocation.longitude, initialLocation.latitude],
				zoom: initialLocation.zoom,
				pitch: initialLocation.pitch,
				bearing: initialLocation.bearing,
				maxZoom: 18.5,
				hash: true,
				fitBoundsOptions: {padding: (_isTouchDevice ? {} : {right: 280})}
			});
			
			// If a location is set, move the map, thus ignoring the hash
			if (_settings.setLocation) {
				const setLocation = streetfocus.parseLocation (_settings.setLocation);
				_map.setZoom (setLocation.zoom);
				_map.setCenter ([setLocation.longitude, setLocation.latitude]);
			}
			
			// Add navigation (+/-/pitch) controls
			_map.addControl (new mapboxgl.NavigationControl (), 'top-left');
			
			// Add geolocation control
			streetfocus.geolocation ();
			
			// Add buildings
			streetfocus.addBuildings ();
			
			// Enable pitch gestures
			streetfocus.enablePitchGestures ();
			
			// Set cookie on map move, to provide memory between screens
			streetfocus.setMapLocationCookie ();
		},
		
		
		// Function to get the initial map location, whether the default or a map location cookie
		getInitialLocation: function ()
		{
			// Get the map location cookie, if set
			let mapLocation = streetfocus.getCookie ('maplocation');
			
			// Use the default if no cookie
			if (!mapLocation) {
				mapLocation = _settings.defaultLocation;
			}
			
			// Parse out the location (5-value format, including bearing and pitch)
			mapLocation = streetfocus.parseLocation (mapLocation);
			
			// Return the result
			return mapLocation;
		},
		
		
		// Function to parse out a location string
		parseLocation: function (mapLocation)
		{
			// Split into components
			mapLocation = mapLocation.split ('/');
			mapLocation = {
				zoom: mapLocation[0],
				latitude: mapLocation[1],
				longitude: mapLocation[2],
				bearing: mapLocation[3],
				pitch: mapLocation[4],
			}
			
			// Return the result
			return mapLocation;
		},
		
		
		// Function to set a map location cookie
		setMapLocationCookie: function ()
		{
			// On move end, get the location (5-value format, including bearing and pitch)
			_map.on ('moveend', function (e) {
				let centre = _map.getCenter ();
				centre = streetfocus.reduceCoordinateAccuracy (centre);
				const mapLocation = [_map.getZoom (), centre.lat, centre.lng, _map.getBearing (), _map.getPitch ()].join ('/')		// E.g. '18.25/52.204729/0.116882/-59.7/60' as per URL hash format;
				streetfocus.setCookie ('maplocation', mapLocation, 7);
			});
		},
		
		
		// Function to add geolocation
		geolocation: function ()
		{
			// Initialise the control
			const geolocate = new mapboxgl.GeolocateControl ({
				positionOptions: {
					enableHighAccuracy: true
				}
			});
			_map.addControl (geolocate, 'top-right');	// Will be hidden by CSS when external style provides an icon
			
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
				const layers = _map.getStyle().layers;
				
				// Ensure the layer has buildings, or end
				if (!streetfocus.styleHasLayer (layers, 'building')) {return;}

				// Insert the layer beneath any symbol layer.
				let labelLayerId;
				let i;
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
			let i;
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
					const diff = Math.abs(data.points[0].y - data.points[1].y);
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
					const diff = (self.dpPoint.y - data.point.y) * 0.5;
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
			const geocoderApiUrl = '/api/search?sources=' + sources;
			
			// Attach the autocomplete library behaviour to the location control
			autocomplete.addTo ('#geocoder input', {
				sourceUrl: geocoderApiUrl,
				select: function (event, ui) {
					const feature = ui.item.feature;
					
					// Parse the BBOX
					const bbox = feature.properties.bbox.split(',');	// W,S,E,N
					
					// If there is a target URL, go to that
					if (targetUrl) {
						const longitude = (parseFloat(bbox[0]) + parseFloat(bbox[2])) / 2;
						const latitude  = (parseFloat(bbox[1]) + parseFloat(bbox[3])) / 2;
						streetfocus.mapPageLink (longitude, latitude);
						return;
						
					// Otherwise, pan the map
					} else {
						_map.fitBounds (bbox, {
							maxZoom: 16.5,
							duration: 1500,
							padding: (_isTouchDevice ? {} : {right: 280})
						});
						event.preventDefault();
					}
				}
			});
		},
		
		
		// Function to go the map page
		mapPageLink: function (longitude, latitude)
		{
			const zoom = 13;		// #!# Currently fixed - need to compute dynamically, e.g. https://github.com/mapbox/mapbox-unity-sdk/issues/1125
			const targetUrl = '/map/' + '#' + zoom + '/' + latitude.toFixed(6) + '/' + longitude.toFixed(6);
			window.location.href = targetUrl;
		},
		
		
		// Function to add a data layer to the map
		addLayer: function (apiBaseUrl, parameters, filteringFormPath, callback, uniqueIdField, titleField)
		{
			// Compile colour lists and size lists
			const colourPairs = streetfocus.compilePairs (_colours);
			const sizePairs = streetfocus.compilePairs (_sizes);
			const statesPairs = streetfocus.compilePairs (_states);
			
			// Add the source and layer
			_map.addLayer ({
				id: _action,
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
					'circle-color': (
						_colours[_action]
						? [
							'match',
							['get', _colours[_action].field],
							...colourPairs,
							'#ffc300'
						]
						: '#ffc300'
					),
					'circle-radius': (
						_sizes[_action]
						? [
							'match',
							['get', _sizes[_action].field],
							...sizePairs,
							_sizes[_action].values['Medium']
						]
						: 15
					),
					'circle-opacity': (
						_states[_action]
						? [
							'match',
							['get', _states[_action].field],
							...statesPairs,
							_states[_action].values['Undecided']
						]
						: 1
					)
				}
			});
			
			// Register popup handler
			_map.on ('click', _action, function (e) {
				const feature = e.features[0];
				
				// Create the popup
				streetfocus.createPopup (feature, uniqueIdField, titleField);
				
				// Prevent further event propagation, resulting in the map close event auto-closing the panel immediately
				e.preventDefault ();	// See: https://stackoverflow.com/a/61366984 and e.defaultPrevented check elsewhere
			});
			
			// Add close methods (X button, click on map, escape key)
			streetfocus.panelClosing ('#details');
			
			// Change the cursor to a pointer when over a point
			_map.on ('mouseenter', _action, function () {
				_map.getCanvas().style.cursor = 'pointer';
			});
			_map.on ('mouseleave', _action, function () {
				_map.getCanvas().style.cursor = '';
			});
			
			// Add a loading control
			streetfocus.createControl ('loading', 'top-left');
			$('#loading').html ('<img src="/images/ui-anim_basic_16x16.gif" />');
			
			// Get the data
			streetfocus.addData (apiBaseUrl, parameters, filteringFormPath, callback, uniqueIdField, titleField);
			
			// Register to update on map move and form changes
			_map.on ('moveend', function (e) {
				streetfocus.addData (apiBaseUrl, parameters, filteringFormPath, callback);
			});
			
			// If the form is reset, update; this has to intercept the reset as the change event would be fired before the form reset; see: https://stackoverflow.com/a/10319580/180733
			$(filteringFormPath + ' :reset').click (function (e) {
				e.preventDefault ();
				$(filteringFormPath)[0].reset ();
				streetfocus.addData (apiBaseUrl, parameters, filteringFormPath, callback);
			});
			
			// If a form is set to be scanned, update on change
			if (filteringFormPath) {
				$(filteringFormPath + ' :input').change (function (e) {
					streetfocus.addData (apiBaseUrl, parameters, filteringFormPath, callback);
				});
			}
		},
		
		
		// Function to compile configuration pairs for use in a style definition
		compilePairs (property)
		{
			// Add each key and value to a list
			const pairs = [];
			if (property[_action]) {
				$.each (property[_action].values, function (key, value) {
					pairs.push (key);
					pairs.push (value);
				});
			}
			
			// Return the pairs
			return pairs;
		},
		
		
		// Function to create a popup
		createPopup: function (feature, uniqueIdField, titleField)
		{
			// Substitute the content
			const popupFunction = 'populatePopup' + streetfocus.ucfirst (_action);
			streetfocus[popupFunction] ('#popupcontent', feature, uniqueIdField);
			
			// Get the HTML
			const popupHtml = $('#popupcontent').html();
			
			// Show the popup (details) pane
			$('#details').show ();
			
			// Set the HTML of the details pane to be the placeholdered content
			$('#details').html (popupHtml);
			
			// Put the focus on the popup, to ensure keyboard navigation is on the panel, not the map
			const x = window.scrollX;
			const y = window.scrollY;
			$('#details').attr ('tabindex', 2).focus ();
			window.scrollTo (x, y);
			
			// Update the URL using HTML5 History pushState
			const path = _actionUrl + feature.properties[uniqueIdField] + '/';
			const title = _documentTitle + ': ' + streetfocus.truncateString (feature.properties[titleField], 40);
			streetfocus.updateUrl (path, title);
		},
		
		
		// Function to load the data for a layer
		addData: function (apiBaseUrl, parameters, filteringFormPath, callback, uniqueIdField, titleField)
		{
			// End if not zoomed in enough
			if (!_mapZoomEnough) {
				const data = {type: 'FeatureCollection', features: []};		// Empty dataset, to ensure map is cleared
				streetfocus.showCurrentData (data, callback, uniqueIdField, titleField);
				return;
			}
			
			// Start with a fresh set of parameters, to avoid the form scanning setting a reference to the next form iteration
			parameters = Object.assign({}, parameters);

			// Get the map BBOX
			parameters.bbox = streetfocus.getBbox ();
			
			// Obtain the form values
			if (filteringFormPath) {
				const formParameters = streetfocus.scanForm (filteringFormPath);
				$.extend (parameters, formParameters);
			}
			
			// If there is a start date and end date, take no action if in wrong date order
			if (parameters.since && parameters.until) {
				if (parameters.since > parameters.until) {
					return;
				}
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
						const data = $.parseJSON(jqXHR.responseText);
						alert ('Error: ' + data.error);		// #!# Need to check how PlanIt API gives human-readable errors
					}
				},
				success: function (data, textStatus, jqXHR) {
					streetfocus.showCurrentData (data, callback, uniqueIdField, titleField);
					$('#loading').hide ();
				}
			});
		},
		
		
		// Helper funtion to get the map bounds as a string
		getBbox: function ()
		{
			let bbox = _map.getBounds();
			bbox = bbox.getWest() + ',' + bbox.getSouth() + ',' + bbox.getEast() + ',' + bbox.getNorth();
			bbox = streetfocus.reduceBboxAccuracy (bbox);
			return bbox;
		},
		
		
		// Function to add a heatmap layer
		addHeatmapLayer: function (layerId, datasource, preferredZoom)
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
			}, _action);
			
			// Handle visibility
			$('#' + layerId).click (function (e) {
				const visibility = _map.getLayoutProperty (layerId, 'visibility');
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
		
		
		// Function to add a static polygon layer
		addStaticPolygonLayer: function (url, id, popupHtml, exclude)
		{
			// Register the data source
			_map.addSource (id, {
				type: 'geojson',
				data: url,
				tolerance: 3.5		// See: https://docs.mapbox.com/help/troubleshooting/working-with-large-geojson-data/
			});
			
			// Add layer to visualise the data
			_map.addLayer ({
				id: id,
				type: 'fill',
				source: id,
				layout: {},
				paint: {
					'fill-color': '#ffc5c5',
					'fill-opacity': 0.5
				}
			});
			
			// Filter if required
			if (!$.isEmptyObject (exclude)) {
				$.each (exclude, function (key, value) {
					_map.setFilter (id, ['!=', ['get', key], value]);
				});
			}
			
			// Add popup
			_map.on ('click', id, function (e) {
				new mapboxgl.Popup ()
					.setLngLat (e.lngLat)
					.setHTML (popupHtml)
					.addTo (_map);
			});

		},
		
		
		// Function to reduce co-ordinate accuracy of a bbox string
		reduceBboxAccuracy: function (bbox)
		{
			// Split by comma
			let coordinates = bbox.split(',');
			
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
			const accuracy = 6;
			
			// Support lng/lat named key format
			if (coordinates.hasOwnProperty ('lng') && coordinates.hasOwnProperty ('lat')) {
				coordinates['lng'] = parseFloat(coordinates['lng']).toFixed(accuracy);
				coordinates['lat'] = parseFloat(coordinates['lat']).toFixed(accuracy);
			
			// For indexed list format, reduce each value
			} else {
				let i;
				for (i = 0; i < coordinates.length; i++) {
					coordinates[i] = parseFloat(coordinates[i]).toFixed(accuracy);
				}
			}
			
			// Return the modified set
			return coordinates;
		},
		
		
		// Helper function to get the centre-point of a geometry
		getCentre: function (geometry)
		{
			// Determine the centre point
			let centre = {};
			const longitudes = [];
			const latitudes = [];
			switch (geometry.type) {
				
				case 'Point':
					centre = {
						lat: geometry.coordinates[1],
						lon: geometry.coordinates[0]
					};
					break;
					
				case 'LineString':
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
					
				case 'MultiPolygon':
					$.each (geometry.coordinates, function (index, polygon) {
						$.each (polygon, function (index, line) {
							$.each (line, function (index, lonLat) {
								longitudes.push (lonLat[0]);
								latitudes.push (lonLat[1]);
							});
						});
					});
					centre = {
						lat: ((Math.max.apply (null, latitudes) + Math.min.apply (null, latitudes)) / 2),
						lon: ((Math.max.apply (null, longitudes) + Math.min.apply (null, longitudes)) / 2)
					};
					break;
					
				case 'GeometryCollection':
					$.each (geometry.geometries, function (index, geometryItem) {
						centre = streetfocus.getCentre (geometryItem);		// Iterate
						longitudes.push (centre.lon);
						latitudes.push (centre.lat);
					});
					centre = {
						lat: ((Math.max.apply (null, latitudes) + Math.min.apply (null, latitudes)) / 2),
						lon: ((Math.max.apply (null, longitudes) + Math.min.apply (null, longitudes)) / 2)
					};
					break;
					
				default:
					console.log ('Unsupported geometry type: ' + geometry.type, geometry);
			}
			
			// Return the centre
			return centre;
		},
		
		
		// Function to show the data for a layer
		showCurrentData: function (data, callback, uniqueIdField, titleField)
		{
			// If the layer has lines or polygons, reduce to point
			$.each (data.features, function (index, feature) {
				const centre = streetfocus.getCentre (feature.geometry);
				data.features[index].geometry = {
					type: 'Point',
					coordinates: [centre.lon, centre.lat]
				}
			});
			
			// Run the callback, if required, to filter data
			if (callback) {
				data = callback (data);
			}
			
			// Set the data
			_map.getSource (_action).setData (data);
			
			// If an ID is specified, and is present in the data, open its popup
			if (uniqueIdField) {	// If specified, which happens only for the initial loading state
				if (_id) {
					$.each (data.features, function (index, feature) {
						if (feature.properties[uniqueIdField] == _id) {
							streetfocus.createPopup (feature, uniqueIdField, titleField);
						}
					});
				}
			}
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
		
    	
		// Function to set a cookie
		setCookie: function (key, value, expiryDays) {
			const expires = new Date();
			expires.setTime (expires.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
			document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
		},
		
		
		// Function to get a cookie value
		getCookie: function (key) {
			const keyValue = document.cookie.match ('(^|;) ?' + key + '=([^;]*)(;|$)');
			return keyValue ? keyValue[2] : null;
		},
		
		
		// Function to update the URL, to provide persistency when a link is circulated
		updateUrl: function (path, title)
		{
			// End if not supported, e.g. IE9
			if (!history.pushState) {return;}
			
			// Construct the URL
			const url = path + window.location.hash;
			
			// Push the URL state
			history.pushState (url, title, url);
			document.title = title;		// Workaround for poor browser support; see: https://stackoverflow.com/questions/13955520/
		}
	};
	
} (jQuery));

