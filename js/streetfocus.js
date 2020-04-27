// StreetFocus implementation code

/*jslint browser: true, white: true, single: true, for: true */
/*global $, alert, console, window */

var streetfocus = (function ($) {
	
	'use strict';
	
	// Settings defaults
	var _settings = {
		
		// Initial lat/lon/zoom of map and tile layer
		defaultLocation: '17/52.2053/0.1218/0/0',	// Zoom, lat, lon, pitch, bearing
		
		// PlanIt API
		planitApiBaseUrl: 'https://www.planit.org.uk/api',
		planitEarliestYear: 2000,
		
		// Mapbox API key
		mapboxApiKey: 'YOUR_MAPBOX_API_KEY',
		
		// Google Maps API key
		gmapApiKey: 'YOUR_GOOGLEMAPS_API_KEY',
		
		// Forced location
		setLocation: false,
		
		// Page-specific data
		pageData: {}
	};
	
	
	// Internal class properties
	var _map = null;
	var _action;
	var _actionUrl;
	var _id;
	var _documentTitle;
	var _isTouchDevice;
	
	// Default filters
	var _filteringDefaults = {
		//app_size:
		//app_type:
		//start_date:
		//end_date:
		app_state: ['Undecided']
	};
	
	// Definitions
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
				'Other':		'gray',
				'Conditions':	'#aed6f1'
			}
		}
	};
	var _sizes = {
		planningapplications: {
			field: 'app_size',
			values: {
				'Small':		11,
				'Medium':		18,
				'Large':		25
			}
		}
	};
	var _states = {
		planningapplications: {
			field: 'app_state',
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
	var _keyTypes = [
		'Design and Access Statement',
	];
			
	
	// Actions creating a map
	var _mapActions = ['planningapplications', 'proposals', 'my', 'add'];
	
	
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
			
			// Add the planning applications layer, e.g. /api/applics/geojson?limit=30&bbox=0.132162%2C52.189131%2C0.147603%2C52.196076&recent=188&app_type=Full,Trees
			var apiBaseUrl = _settings.planitApiBaseUrl + '/applics/geojson';
			var parameters = {
				limit: 250,
				pg_sz: 250
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
			
			// Add the data layer
			streetfocus.addLayer (apiBaseUrl, parameters, '#filtering', null, 'name', 'description');
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/allCambridgeCollisions.geojson', 16);
		},
		
		
		// Function to initialise the filtering form
		initialiseFilteringForm: function ()
		{
			// Set min and max dates
			var yearRange = {
				min: _settings.planitEarliestYear,
				max: new Date().getFullYear()
			};
			$('input[name="start_date"], input[name="end_date"]').attr ('min', yearRange.min);
			$('input[name="start_date"], input[name="end_date"]').attr ('max', yearRange.max);
			
			// Set checkbox colours
			var value;
			$.each ($("input[name='app_type[]']"), function () {
				value = $(this).val ();
				$(this).parent().parent().css ('background-color', _colours['planningapplications'].values[value]);		// Two parents, as label surrounds
			});
			
			// If a filter state cookie is set, set the filtering form values on initial load
			streetfocus.filteringInitialValues ();
		},
		
		
		// Function to get the filtering option values available
		getFilteringOptions: function ()
		{
			// Loop through each input
			var filteringOptions = {};
			var name, value, type;
			$('#filtering :input').each (function () {
				name = $(this).attr('name');
				value = $(this).val();
				
				// Extract the name and value, and create a key for the name if not already present
				type = $(this).attr('type');
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
		
		
		// Function to set the filtering UI initial values
		// #!# This is a bit slow because the whole function is running inside map load
		filteringInitialValues: function ()
		{
			// Use default filters
			var filteringDefaults = _filteringDefaults;
			
			// Detect cookie, or end
			var filteringCookie = streetfocus.getCookie ('filtering');
			if (filteringCookie) {
				filteringDefaults = JSON.parse (filteringCookie);
			}
			
			// Set the values
			streetfocus.setFilteringUiValues (filteringDefaults);
			
			// Add a handler for resetting the defaults
			$('#filtering p.reset a').click (function (e) {
				streetfocus.setFilteringUiValues (_filteringDefaults);
				e.preventDefault ();
			});
		},
		
		
		// Function to set the filtering UI values
		setFilteringUiValues: function (filteringDefaults)
		{
			// Determine all available values in the checkbox sets
			var filteringOptions = streetfocus.getFilteringOptions ();
			
			// Reset all
			$('#filtering input[type="number"]').val ('');
			$('#filtering input:checkbox').prop ('checked', false);
			
			// Loop through each checkbox set / input
			var inputType;
			var isScalarInputType;
			var parameterValue;
			$.each (filteringOptions, function (parameter, allOptions) {
				
				// Detect whether the parameter is for a scalar type, rather than, e.g. checkboxes
				inputType = $('input[name="' + parameter + '"').attr ('type');			// Checkboxes like name="foo[]", or <select> element will therefore not match
				isScalarInputType = (inputType == 'text' || inputType == 'number');
				
				// Scalar input types
				if (isScalarInputType) {
					
					// If this parameter (e.g. app_type) is present in the defaults, use that; else set empty
					parameterValue = (filteringDefaults.hasOwnProperty (parameter) ? filteringDefaults[parameter] : '');
					
					// Set the value
					$('#filtering input[name="' + parameter + '"]').val (parameterValue);
					
				// Array types, e.g. checkboxes
				} else {
					
					// If this parameter (e.g. app_type) is present in the defaults, use that; blank means no filtering, i.e. all options
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
			var path = _actionUrl;
			var title = _documentTitle;
			streetfocus.updateUrl (path, title);
		},
		
				
		// Function to perform a form scan for values
		scanForm: function (path)
		{
			// Start a set of parameters
			var parameters = {};
			
			// Scan form widgets
			var name, value, type;
			$(path + ' :input').each (function() {
				name = $(this).attr('name').replace('[]', '');
				value = $(this).val();
				
				type = $(this).attr('type');
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
			var parametersString = JSON.stringify (parameters);
			streetfocus.setCookie ('filtering', parametersString, 7);
			
			// Join each parameter value by comma delimeter
			$.each (parameters, function (name, values) {
				if (Array.isArray (values)) {
					parameters[name] = values.join(',');
				}
			});
			
			// Deal with date fields, which need to be converted from year to date
			if (parameters.hasOwnProperty ('start_date')) {
				parameters.start_date += '-01-01';
			}
			if (parameters.hasOwnProperty ('end_date')) {
				parameters.end_date += '-12-31';
			}
			
			// Return the values
			return parameters;
		},
		
		
		// Function to populate the planning applications map popup
		populatePopupPlanningapplications: function (element, feature)
		{
			// Get the fuller data, syncronously
			// #!# Need to restructure calling code to avoid syncronous request
			var url = '/api/planningapplication?id=' + feature.properties.name;		// Contains fuller data at the application level
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
			var vendorUiReplacements = {
				'activeTab=summary': {
					'documents': 'activeTab=documents',
					'comments': 'activeTab=makeComment'
				}
			}
			var vendorUiLinkBase = feature.properties.url;
			var vendorLinks = {};
			$.each (vendorUiReplacements, function (key, values) {
				$.each (values, function (type, value) {
					vendorLinks[type] = vendorUiLinkBase.replace (key, value);
				});
			});
			
			// Determine the key documents list
			var keyDocumentsHtml = streetfocus.keyDocuments (feature.properties.docs);
			
			// Determine the matching proposals list
			var matchingProposalsHtml = streetfocus.matchingProposals (feature.properties._proposals);
			
			// Only show proposal matches for medium/large applications
			if (feature.properties.app_size == 'Medium' || feature.properties.app_size == 'Large') {
				$(element + ' div.matches').show ();
			} else {
				$(element + ' div.matches').hide ();
			}
			
			// Hide the call to action if already decided/withdrawn/etc.
			if (feature.properties.app_state == 'Undecided') {
				$(element + ' p.link').show ();
			} else {
				$(element + ' p.link').hide ();
			}
			
			// Populate the HTML content
			$(element + ' p.applicationId').html (feature.properties.uid);
			$(element + ' p.link a').attr ('href', vendorLinks.comments);
			$(element + ' p.officialplans a').attr ('href', feature.properties.url);
			$(element + ' ul.status li.state').text (feature.properties.app_state + ' application');
			$(element + ' ul.status li.size').text ((feature.properties.app_size ? feature.properties.app_size + ' development' : 'Unknown size'));
			$(element + ' ul.status li.type').text (feature.properties.app_type);
			$(element + ' p.date').html (streetfocus.consultationDate (feature));
			$(element + ' .title').html (streetfocus.htmlspecialchars (streetfocus.truncateString (feature.properties.description, 80)));
			$(element + ' div.description p').html (streetfocus.htmlspecialchars (feature.properties.description));
			$(element + ' div.documents ul').html (keyDocumentsHtml);
			$(element + ' div.matches ul').html (matchingProposalsHtml);
			$(element + ' p.alldocuments a').attr ('href', vendorLinks.documents);
			$(element + ' p.address').html (streetfocus.htmlspecialchars (feature.properties.address));
			$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
			
			// For IDOX-based areas, work around the cookie bug
			streetfocus.idoxWorkaroundCookie (vendorLinks.documents, feature.properties.name);
		},
		
		
		// Function to determine the consultation date
		consultationDate: function (feature)
		{
			// Define available fields in the data, and their labels
			var consultationDateFields = {
				'consultation_end_date'				: 'Consultation end date',
				'neighbour_consultation_end_date'	: 'Neighbour consultation end date',
				'site_notice_end_date'				: 'Site notice end date',
				'latest_advertisement_expiry_date'	: 'Latest advertisement expiry date'
			};
			
			// Determine the latest of the fields, allocating the date and the label
			var latestConsultationDate = '';	// String comparison will be done for each date field value
			var latestConsultationDateFieldLabel = 'Deadline';
			$.each (consultationDateFields, function (consultationDateField, consultationDateFieldLabel) {
				if (feature.properties.other_fields.hasOwnProperty (consultationDateField)) {
					if (feature.properties.other_fields[consultationDateField] > latestConsultationDate) {
						latestConsultationDate = feature.properties.other_fields[consultationDateField];
						latestConsultationDateFieldLabel = consultationDateFieldLabel;
					}
				}
			});
			
			// Convert the date to a human-readable string
			var latestConsultationDateFormatted = (latestConsultationDate ? new Date (latestConsultationDate).toDateString () : '?');
			
			// If the application is past, set the label to be closed
			if (feature.properties.app_state != 'Undecided') {
				latestConsultationDateFieldLabel = 'Date closed';
			}
			
			// Determine number of days before the consultation closes
			var daysRemaining = '';
			if (latestConsultationDate) {
				var today = new Date();
				var closeDate = new Date(latestConsultationDate);
				var timeDifference = closeDate.setHours(0,0,0,0) - today.setHours(0,0,0,0);		// setHours forces each to midday and casts to Unix timestamp
				var daysDifference = timeDifference / (1000 * 3600 * 24);
				if (daysDifference >= 0) {
					switch (daysDifference) {
						case 0:  daysRemaining = ' (today)'; break;
						case 1:  daysRemaining = ' (tomorrow)'; break;
						default: daysRemaining = ' (' + daysDifference + ' days remaining)';
					}
				}
			}
			
			// Construct the string, as the label with the date
			var consultationDateString = latestConsultationDateFieldLabel + ':&nbsp; ' + latestConsultationDateFormatted + daysRemaining;
			
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
				var cookieName = 'idoxWorkaround';
				var cookieValue;
				if (cookieValue = streetfocus.getCookie (cookieName)) {
					if (cookieValue == applicationId) {
						return;
					}
				}
				
				// Obtain the clicked document URL
				var documentUrl = e.target.href;
				
				// Open both windows
				var newWindow = window.open (allDocumentsUrl, '_blank');
				var count = 0;
				var interval = setInterval (function () {
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
			var listItem;
			$.each (keyDocuments, function (index, document) {
				listItem  = '<li>';
				listItem += '<a href="' + document.doc_url + '" target="_blank">';
				listItem += streetfocus.htmlspecialchars (document.doc_type);
				listItem += ' - ' + streetfocus.htmlspecialchars (document.doc_title);
				listItem += ' &nbsp; <span>(' + streetfocus.htmlspecialchars (document.doc_date) + ')</span>';
				listItem += '</a>';
				listItem += '</li>';
				listItems.push (listItem);
			});
			var listItemsHtml = listItems.join ("\n");
			
			// Return the list
			return listItemsHtml;
		},
		
		
		// Helper function to list matching proposals
		matchingProposals: function (proposals)
		{
			// Return empty array if none
			if (!proposals) {return [];}
			
			// Convert to HTML
			var listItems = [];
			var listItem;
			var date;
			$.each (proposals, function (index, proposal) {
				listItem  = '<li>';
				listItem += '<a href="/proposals/' + proposal.properties.moniker + '/" target="_blank">';
				listItem += streetfocus.htmlspecialchars (proposal.properties.title);
				date = new Date (proposal.properties.when * 1000);
				listItem += ' &nbsp; <span>(' + streetfocus.htmlspecialchars (date.getFullYear ()) + ')</span>';
				listItem += '</a>';
				listItem += '</li>';
				listItems.push (listItem);
			});
			var listItemsHtml = listItems.join ("\n");
			
			// Return the list
			return listItemsHtml;
		},
		
		
		// Proposals
		proposals: function ()
		{
			// Add geocoder control
			streetfocus.search ('geocoder');
			
			// Define a callback function to filter out proposals which appear to be an imported planning application
			var callback = function (data) {
				var i = data.features.length;
				while (i--) {		// See: https://stackoverflow.com/a/9882349/180733
					if (data.features[i].properties.title.match(/^Planning application/)) {
						data.features.splice (i, 1);
					}
				}
				return data;
			}
			
			// Add the proposals layer, e.g. /api/proposals?bbox=-0.127902%2C51.503486%2C-0.067091%2C51.512086
			var apiBaseUrl = '/api/proposals';
			var parameters = {};
			streetfocus.addLayer (apiBaseUrl, parameters, null, callback, 'moniker', 'title');
			
			// Add collisions heatmap layer support
			// /v2/collisions.locations?fields=severity&boundary=[[0.05,52.15],[0.05,52.25],[0.2,52.25],[0.2,52.15],[0.05,52.15]]&casualtiesinclude=cyclist'
			streetfocus.addHeatmapLayer ('collisions', 'https://www.cyclestreets.net/allCambridgeCollisions.geojson', 16);
		},
		
		
		// Function to populate the popup
		populatePopupProposals: function (element, feature)
		{
			// Get the centre-point of the geometry
			var centre = streetfocus.getCentre (feature.geometry);
			
			// Populate the static HTML
			$(element + ' p.applicationid span.source').text (feature.properties.source);
			$(element + ' p.applicationid span.id').text (feature.properties.id);
			$(element + ' p.applicationid span.date').text (new Date(feature.properties.when * 1000).toDateString());
			$(element + ' h2.title').text (feature.properties.title);
			$(element + ' p.link a').attr ('href', feature.properties.link);
			$(element + ' div.description').html (feature.properties.description);		// Will be paragraph(s) of HTML
			if (feature.properties.image !== 'null') {
				$(element + ' p.image').show ();
				$(element + ' p.image img').attr ('src', feature.properties.image);
			} else {
				$(element + ' p.image').hide ();
			}
			//$(element + ' ul.categories').html ('<ul class="tags"><li>' + JSON.parse(feature.properties.categories).join('</li><li>') + '</li></ul>');
			//$(element + ' div.streetview').html ('<iframe id="streetview" src="/streetview.html?latitude=' + centre.lat + '&amp;longitude=' + centre.lon + '">Street View loading &hellip;</iframe>');
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
						var data = $.parseJSON(jqXHR.responseText);
						alert ('Error: ' + data.error);
					}
				},
				success: function (data, textStatus, jqXHR) {
					
					// Add the map data
					var layerId = 'monitors';
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
						var feature = e.features[0];
						
						// Determine the coordinates
						var centre = streetfocus.getCentre (feature.geometry);
						var coordinates = [centre.lon, centre.lat];

						// Ensure that if the map is zoomed out such that multiple copies of the feature are visible, the popup appears over the copy being pointed to.
						while (Math.abs (e.lngLat.lng - coordinates[0]) > 180) {
							coordinates[0] += (e.lngLat.lng > coordinates[0] ? 360 : -360);
						}
						
						// Set the HTML content of the popup
						var filters = [];
						if (feature.properties.app_type !== 'null') {
							filters.push ('Type: ' + feature.properties.app_type.replace (', ', ' / ') + ' applications');
						}
						if (feature.properties.app_size != 'null') {
							filters.push ('Size: ' + feature.properties.app_size.replace (', ', ' / ') + ' applications');
						}
						var popupHtml = '<p>' + ($.isEmptyObject (filters) ? 'All planning applications in this area.' : 'Planning applications in this area, limited to:') + '</p>';
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
		add: function ()
		{
			// Reset the pitch and bearing
			_map.setPitch (0);
			_map.setBearing (0);
			
			// Initialise the filtering form
			streetfocus.initialiseFilteringForm ();
			
			// Capture map location changes, saving these to the hidden input field
			var bbox = streetfocus.getBbox ();
			$('#bbox').val (bbox);
			_map.on ('moveend', function () {
				bbox = streetfocus.getBbox ();
				$('#bbox').val (bbox);
			});
		},
		
		
		// Function to create the map and related controls
		createMap: function ()
		{
			// Determine default location
			var initialLocation = streetfocus.getInitialLocation ();
			
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
				hash: true
			});
			
			// If a location is set, move the map, thus ignoring the hash
			if (_settings.setLocation) {
				var setLocation = streetfocus.parseLocation (_settings.setLocation);
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
			var mapLocation = streetfocus.getCookie ('maplocation');
			
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
				var centre = _map.getCenter ();
				centre = streetfocus.reduceCoordinateAccuracy (centre);
				var mapLocation = [_map.getZoom (), centre.lat, centre.lng, _map.getBearing (), _map.getPitch ()].join ('/')		// E.g. '18.25/52.204729/0.116882/-59.7/60' as per URL hash format;
				streetfocus.setCookie ('maplocation', mapLocation, 7);
			});
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
					var feature = ui.item.feature;
					
					// Parse the BBOX
					var bbox = feature.properties.bbox.split(',');	// W,S,E,N
					
					// If there is a target URL, go to that
					if (targetUrl) {
						var longitude = (parseFloat(bbox[0]) + parseFloat(bbox[2])) / 2;
						var latitude  = (parseFloat(bbox[1]) + parseFloat(bbox[3])) / 2;
						streetfocus.mapPageLink (longitude, latitude);
						return;
						
					// Otherwise, pan the map
					} else {
						_map.fitBounds(bbox);
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
		addLayer: function (apiBaseUrl, parameters, filteringFormPath, callback, uniqueIdField, titleField)
		{
			// Compile colour lists and size lists
			var colourPairs = streetfocus.compilePairs (_colours);
			var sizePairs = streetfocus.compilePairs (_sizes);
			var statesPairs = streetfocus.compilePairs (_states);
			
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
				var feature = e.features[0];
				
				// Create the popup
				streetfocus.createPopup (feature, uniqueIdField, titleField);
				
				// Prevent further event propagation, resulting in the map close event auto-closing the panel immediately
				e.stopPropagation ();
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
			
			// If a form is set to be scanned, update on change
			if (filteringFormPath) {
				$(filteringFormPath + ' :input').click (function (e) {
					streetfocus.addData (apiBaseUrl, parameters, filteringFormPath, callback);
				});
			}
		},
		
		
		// Function to compile configuration pairs for use in a style definition
		compilePairs (property)
		{
			// Add each key and value to a list
			var pairs = [];
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
			var popupFunction = 'populatePopup' + streetfocus.ucfirst (_action);
			streetfocus[popupFunction] ('#popupcontent', feature);
			
			// Get the HTML
			var popupHtml = $('#popupcontent').html();
			
			// Show the popup (details) pane
			$('#details').show ();
			
			// Set the HTML of the details pane to be the placeholdered content
			$('#details').html (popupHtml);
			
			// Put the focus on the popup, to ensure keyboard navigation is on the panel, not the map
			var x = window.scrollX, y = window.scrollY;
			$('#details').attr ('tabindex', 2).focus ();
			window.scrollTo (x, y);
			
			// Update the URL using HTML5 History pushState
			var path = _actionUrl + feature.properties[uniqueIdField] + '/';
			var title = _documentTitle + ': ' + streetfocus.truncateString (feature.properties[titleField], 40);
			streetfocus.updateUrl (path, title);
		},
		
		
		// Function to load the data for a layer
		addData: function (apiBaseUrl, parameters, filteringFormPath, callback, uniqueIdField, titleField)
		{
			// Start with a fresh set of parameters, to avoid the form scanning setting a reference to the next form iteration
			parameters = Object.assign({}, parameters);

			// Get the map BBOX
			parameters.bbox = streetfocus.getBbox ();
			
			// Obtain the form values
			if (filteringFormPath) {
				var formParameters = streetfocus.scanForm (filteringFormPath);
				$.extend (parameters, formParameters);
			}
			
			// If there is a start date and end date, take no action if in wrong date order
			if (parameters.start_date && parameters.end_date) {
				if (parameters.start_date > parameters.end_date) {
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
						var data = $.parseJSON(jqXHR.responseText);
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
			var bbox = _map.getBounds();
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
			
			// Support lng/lat named key format
			if (coordinates.hasOwnProperty ('lng') && coordinates.hasOwnProperty ('lat')) {
				coordinates['lng'] = parseFloat(coordinates['lng']).toFixed(accuracy);
				coordinates['lat'] = parseFloat(coordinates['lat']).toFixed(accuracy);
			
			// For indexed list format, reduce each value
			} else {
				var i;
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
					
				case 'MultiPolygon':
					var longitudes = [];
					var latitudes = [];
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
					var longitudes = [];
					var latitudes = [];
					var centre;
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
			var centre;
			$.each (data.features, function (index, feature) {
				centre = streetfocus.getCentre (feature.geometry);
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
			var expires = new Date();
			expires.setTime (expires.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
			document.cookie = key + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
		},
		
		
		// Function to get a cookie value
		getCookie: function (key) {
			var keyValue = document.cookie.match ('(^|;) ?' + key + '=([^;]*)(;|$)');
			return keyValue ? keyValue[2] : null;
		},
		
		
		// Function to update the URL, to provide persistency when a link is circulated
		updateUrl: function (path, title)
		{
			// End if not supported, e.g. IE9
			if (!history.pushState) {return;}
			
			// Construct the URL
			var url = path + window.location.hash;
			
			// Push the URL state
			history.pushState (url, title, url);
			document.title = title;		// Workaround for poor browser support; see: https://stackoverflow.com/questions/13955520/
		}
	};
	
} (jQuery));

