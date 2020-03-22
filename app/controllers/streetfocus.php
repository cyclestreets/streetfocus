<?php

# Controller for StreetFocus
class streetfocus
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'defaultLocation'			=> '17/52.2053/0.1218/0/0',		// Zoom, lat, lon, pitch, bearing
			'planitBaseUrl'				=> 'https://www.planit.org.uk',
			'cyclescapeApiBaseUrl'		=> 'https://www.cyclescape.org/api',
			'cyclescapeBaseUrl'			=> 'https://www.cyclescape.org',
			'cyclestreetsApiBaseUrl'	=> 'https://api.cyclestreets.net',
			'cyclestreetsApiKey'		=> NULL,
			'mapboxApiKey'				=> NULL,
			'googleApiKey'				=> NULL,
			'autocompleteBbox'			=> '-6.6577,49.9370,1.7797,57.6924',
			'authNamespace'				=> 'streetfocus\\',
			'hostname'					=> 'localhost',
			'database'					=> 'streetfocus',
			'username'					=> NULL,
			'password'					=> NULL,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Register actions
	private function actions ()
	{
		# Specify available actions; URL refers both to the public URL and the template location
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '/',
			),
			'about' => array (
				'description' => 'About',
				'url' => '/about/',
			),
			'planningapplications' => array (
				'description' => 'Planning applications',
				'url' => '/map/',
			),
			'proposals' => array (
				'description' => 'Proposals for street changes',
				'url' => '/proposals/',
			),
			'my' => array (
				'description' => 'Monitor areas',
				'url' => '/my/',
			),
			'add' => array (
				'description' => 'Monitor an area',
				'url' => '/my/add/',
				'useTab' => 'my',
				'authentication' => true,
			),
			'privacy' => array (
				'description' => 'Privacy',
				'url' => '/privacy/',
			),
			'contacts' => array (
				'description' => 'Contact us',
				'url' => '/contacts/',
			),
			'login' => array (
				'description' => 'Sign in',
				'url' => '/login/',
			),
			'logout' => array (
				'description' => 'Sign out',
				'url' => '/logout/',
			),
			'register' => array (
				'description' => 'Register',
				'url' => '/register/',
			),
			'api' => array (
				'description' => false,
				'url' => '/api/',
				'data' => true,
			),
			'page404' => array (
				'description' => '404 page not found',
				'url' => '/page404/',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			-- Monitors
			CREATE TABLE `monitors` (
			  `id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			  `location` GEOMETRY COMMENT 'Location',
			  `app_type` SET('Full','Outline','Amendment','Heritage','Trees','Advertising','Telecoms','Other') DEFAULT NULL COMMENT 'Type(s)',
			  `app_size` SET('Small','Medium','Large') DEFAULT NULL COMMENT 'Size(s)',
			  `email` VARCHAR(255) NOT NULL COMMENT 'E-mail',
			  `createdAt` DATETIME NOT NULL COMMENT 'Created at'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monitors set by users';
			
			-- External proposals
			CREATE TABLE `proposalsexternal` (
			  `id` varchar(255) PRIMARY KEY NOT NULL,
			  `source` varchar(255) NOT NULL,
			  `title` varchar(512) DEFAULT NULL,
			  `description` text NOT NULL,
			  `image` varchar(255) DEFAULT NULL,
			  `link` varchar(255) NOT NULL,
			  `categories` varchar(255) DEFAULT NULL,
			  `address` varchar(255) DEFAULT NULL,
			  `agree` int(11) DEFAULT NULL,
			  `user` varchar(255) DEFAULT NULL,
			  `when` datetime NOT NULL,
			  `longitude` decimal(9,6) NOT NULL,
			  `latitude` decimal(8,6) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proposals data from external sources';
		";
	}
	
	
	# Class properties
	private $settings;
	private $baseUrl;
	private $template = array ();
	private $templateFile;
	private $user;
	private $setLocation;
	private $pageData = array ();
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Set the include path to include libraries
		set_include_path ($_SERVER['DOCUMENT_ROOT'] . '/libraries/' . PATH_SEPARATOR . get_include_path ());
		
		# Load required libraries
		require_once ('application.php');
		require_once ('database.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Assign settings
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults (), get_class ($this), NULL, $handleErrors = true)) {return false;}
		
		# Connect to the database
		$this->databaseConnection = new database ($this->settings['hostname'], $this->settings['username'], $this->settings['password'], $this->settings['database']);

		# Assign the action, validating against the registry
		$this->action = $_GET['action'];
		if ($this->action == 'map') {$this->action = 'planningapplications';}	// Special case, to keep .htaccess rules simple
		$this->actions = $this->actions ();
		if (!isSet ($this->actions[$this->action])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$this->template['_action'] = $this->action;
		
		# Get the user's details, if authenticated
		require_once ('app/controllers/userAccount.php');
		$this->userAccount = new userAccount ($this->settings, $this->baseUrl);
		$this->user = $this->userAccount->getUser ();
		$this->userIsAdministrator = $this->userAccount->getUserIsAdministrator ();
		$this->template = $this->userAccount->getTemplate ($this->template);
		
		# Set the page title
		$this->template['_title'] = 'StreetFocus - Helping local communities benefit from new developments';
		if ($this->actions[$this->action]['description']) {
			$this->template['_title'] = 'StreetFocus - ' . htmlspecialchars ($this->actions[$this->action]['description']);
		}
		
		# Ensure authentication if required
		if (isSet ($this->actions[$this->action]['authentication']) && $this->actions[$this->action]['authentication']) {
			if (!$this->user) {
				$this->action = 'login';
			}
		}
		
		# Assign the ID, if any
		$this->id = (isSet ($_GET['id']) ? $_GET['id'] : false);
		
		# Perform the action, which will write into the page template array
		$this->{$this->action} ();
		
		# If not logged in, set the login return-to link
		$this->template['_returnToUrl'] = false;
		if (!$this->user) {
			if ($this->action != 'login') {
				$this->template['_returnToUrl'] = '?' . $this->actions[$this->action]['url'];
			}
		}
		
		# End if the action is a data URL rather than a templatised page
		if (isSet ($this->actions[$this->action]['data']) && $this->actions[$this->action]['data']) {return;}
		
		# Make the settings available to the template
		$this->template['_settings'] = $this->settings;
		
		# Add the application JS to the template
		$this->template['_applicationJs'] = $this->applicationJs ();
		
		# Set the template path, being the path from /app/views/, minus .tpl
		$url = $this->actions[$this->action]['url'];
		$templatePath = ltrim ($url, '/') . 'index';
		
		# Templatise
		$html = $this->renderTemplate ($templatePath);
	}
	
	
	# Function to render a template
	private function renderTemplate ($templatePath)
	{
		# Load Smarty
		require_once ('libraries/smarty/libs/Smarty.class.php');
		$smarty = new Smarty;
		$smarty->template_dir = 'app/views/';
		$smarty->setCompileDir ('./tmp/templates_c');
		
		# Set the template path
		$smarty->assign ('_template', $templatePath);
		
		# Assign values to the template
		foreach ($this->template as $key => $value) {
			$smarty->assign ($key, $value);
		}
		
		# Execute the template
		$html = $smarty->fetch ('_layouts/index.tpl');
		
		# Show the HTML
		echo $html;
	}
	
	
	# Login
	private function login ()
	{
		# Delegate to the user account class and receive the template values
		$this->userAccount->login ();
		$this->template = $this->userAccount->getTemplate ($this->template);
	}
	
	
	# Logout
	private function logout ()
	{
		# Delegate to the user account class and receive the template values
		$this->userAccount->logout ();
		$this->user = $this->userAccount->getUser ();	// Re-query, for menu status
		$this->template = $this->userAccount->getTemplate ($this->template);
	}
	
	
	# Register
	private function register ()
	{
		# Delegate to the user account class and receive the template values
		$this->userAccount->register ();
		$this->template = $this->userAccount->getTemplate ($this->template);
	}
	
	
	
	# Home page
	private function home ()
	{
		# Add totals
		// #!# Example data at present - needs API integration
		$this->template['totalApplications'] = '32306';
		$this->template['matchedProposals'] = '253';
	}
	
	
	# Planning applications map page
	private function planningapplications ()
	{
		# If an ID is specified, determine the map location, so that the item is present in the area data
		if ($this->id) {
			if (preg_match ('|^(.+)/(.+)$|', $this->id, $matches)) {
				list ($authority, $id) = explode ('/', $this->id, 2);
				if ($planningApplication = $this->searchPlanIt ($id, $authority)) {
					$this->setLocationFromFeature ($planningApplication[0]);
				}
			}
		}
	}
	
	
	# Proposals map page
	private function proposals ()
	{
		# If an ID is specified, determine the map location, so that the item is present in the area data
		if ($this->id) {
			if (preg_match ('|^(.+)/(.+)$|', $this->id, $matches)) {
				list ($source, $id) = explode ('/', $this->id, 2);
				
				# Select source
				switch ($source) {
					
					# Cyclescape
					case 'cyclescape':
						if ($issue = $this->searchCyclescape (false, $id)) {
							$this->setLocationFromFeature ($issue[0]);
						}
						break;
						
					# External
					case 'external':
						if ($issue = $this->getExternalIssues (false, $id)) {
							$this->setLocationFromFeature ($issue[0]);
						}
						break;
				}
			}
		}
	}
	
	
	# Function to set a location from a feature
	private function setLocationFromFeature ($feature)
	{
		$latitude  = $feature['geometry']['coordinates'][1];
		$longitude = $feature['geometry']['coordinates'][0];
		$this->setLocation = "17/{$latitude}/{$longitude}/0/0";
	}
	
	
	# Monitor areas page
	private function my ()
	{
		# Determine whether to the map
		$this->template['user'] = ($this->user);
	}
	
	
	# Add a monitor page
	private function add ()
	{
		# Set user e-mail for the template
		$this->template['email'] = $this->user['email'];
		
		# End if BBOX not posted; this field must be present but others are optional
		$bboxRegexp = '^([-\.0-9]+),([-\.0-9]+),([-\.0-9]+),([-\.0-9]+)$';
		if (!isSet ($_POST['bbox']) || !preg_match ("/{$bboxRegexp}/", $_POST['bbox'], $matches)) {return;}
		
		# Start an insert
		$insert = array ();
		
		# Handle BBOX
		list ($ignore, $w, $s, $e, $n) = $matches;
		$w = (float) $w;
		$s = (float) $s;
		$e = (float) $e;
		$n = (float) $n;
		$bbox = array (
			'type' => 'Polygon',
			'coordinates' => array (array (
				array ($w, $s),
				array ($e, $s),
				array ($e, $n),
				array ($w, $n),
				array ($w, $s)
			))
		);
		$insert['location'] = 'ST_GeomFromGeoJSON(:bbox)';
		$functionValues = array ('bbox' => json_encode ($bbox));
		
		# Add form values, filling in optional values
		$fields = array ('app_type', 'app_size');	// app_state (i.e. current/historical) is obviously not relevant for alerting of new applications
		foreach ($fields as $field) {
			$insert[$field] = (isSet ($_POST[$field]) ? implode (',', $_POST[$field]) : NULL);
		}
		
		# Add fixed data
		$insert['email'] = $this->user['email'];
		$insert['createdAt'] = 'NOW()';
		
		# Add to the database
		$result = $this->databaseConnection->insert ($this->settings['database'], 'monitors', $insert, false, true, false, false, 'INSERT', $functionValues);
		
		# Confirm the outcome
		if ($result) {
			$outcome = '✓ - Your new monitor has been created. We will let you know when new planning applications appear in that area.';
		} else {
			$outcome = 'Apologies - there was a problem saving this monitor. Please try again later.';
		}
		$this->template['outcome'] = $outcome;
	}
	
	
	# Function to load the application JS
	private function applicationJs ()
	{
		# Create the application JS
		return trim ("
			$(function() {
				config = {
					defaultLocation: '{$this->settings['defaultLocation']}',
					planitApiBaseUrl: '{$this->settings['planitBaseUrl']}/api',
					cyclestreetsApiKey: '{$this->settings['cyclestreetsApiKey']}',
					mapboxApiKey: '{$this->settings['mapboxApiKey']}',
					setLocation: '{$this->setLocation}',
					pageData: " . json_encode ($this->pageData) . "
				};
				streetfocus.initialise (config, '{$this->action}', '{$this->actions[$this->action]['url']}', '{$this->id}');
			});
		");
	}
	
	
	# About page
	private function about ()
	{
		// Static page
	}
	
	
	# Privacy page
	private function privacy ()
	{
		// Static page
	}
	
	
	# Contact us page
	private function contacts ()
	{
		// Static page
	}
	
	
	# 404 page
	private function page404 ()
	{
		// Static page
	}
	
	
	# API endpoint
	private function api ()
	{
		# Ensure an API call is specified
		if (!isSet ($_GET['call']) || !strlen ($_GET['call'])) {
			return $this->errorJson ('No API call was specified.');
		}
		
		# Check the specified call is supported
		$call = $_GET['call'];
		$method = 'api_' . $call;
		if (!method_exists ($this, $method)) {
			return $this->errorJson ('Unsupported method call.');
		}
		
		# Get the data
		return $this->{$method} ();
	}
	
	
	# Helper function to send an API error
	private function errorJson ($errorText)
	{
		# Assemble the error repsonse
		$data = array ('error' => $errorText);
		
		# Send the respose
		application::sendHeader (400);	// Bad Request
		return $this->asJson ($data);
	}
	
	
	# Helper function to render results as JSON output
	private function asJson ($data)
	{
		# Render as JSON
		header ('Content-type: application/json; charset=UTF-8');
		echo json_encode ($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
	}
	
	
	# Search API
	private function api_search ()
	{
		# Ensure there is a query
		if (!isSet ($_GET['q']) || !strlen ($_GET['q'])) {
			return $this->errorJson ('No search term was specified.');
		}
		$q = $_GET['q'];
		
		# Get the sources
		$sources = (isSet ($_GET['sources']) ? explode (',', $_GET['sources']) : array ());
		if (!$sources) {
			return $this->errorJson ('No sources were specified.');
		}
		
		# Start a GeoJSON result; the search drivers will return GeoJSON features rather than a full GeoJSON result
		$data = array ('type' => 'FeatureCollection', 'features' => array ());
		
		# If planning application is a specified datasource, search for an ID first
		if (in_array ('planit', $sources)) {
			if (preg_match ('|^[0-9]{2}/[0-9]{4}/[A-Z]+$|', $q)) {		// E.g. 19/1780/FUL
				$data['features'] += $this->searchPlanIt ($q);
				return $this->asJson ($data);	// Do not search other data sources, so return at this point
			}
		}
		
		# Cyclescape issues search
		if (in_array ('cyclescape', $sources)) {
			$data['features'] += $this->searchCyclescape ($q);
		}
		
		# Geocoder search
		if (in_array ('geocoder', $sources)) {
			$data['features'] += $this->searchGeocoder ($q);
		}
		
		# Return the response
		return $this->asJson ($data);
	}
	
	
	# Helper function to do a PlanIt ID search
	private function searchPlanIt ($id, $authority = false)
	{
		# Search PlanIt
		$url = $this->settings['planitBaseUrl'] . '/api/applics/geojson';
		$parameters = array (
			'id_match'	=> $id,
		);
		if ($authority) {
			$parameters['auth'] = $authority;
		}
		$applications = $this->getApiData ($url, $parameters);
		
		# Map each record to a GeoJSON feature in the same format as the Geocoder response
		#!# NB Location data is not always present
		$features = array ();
		foreach ($applications['features'] as $record) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = $this->getCentre ($record['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'name'	=> $this->truncate ($this->reformatCapitalised ($record['properties']['description']), 80),
					'near'	=> $record['properties']['authority_name'],
					'bbox'	=> $bbox,
				),
				'geometry'	=> array (
					'type'			=> 'Point',
					'coordinates'	=> array ($centroid['lon'], $centroid['lat']),
				),
			);
		}
		
		# Return the features
		return $features;
	}
	
	
	# Helper function to do a Cyclescape issues search
	private function searchCyclescape ($q = false, $id = false)
	{
		# Search Cyclescape, e.g. /api/issues?per_page=10&term=chisholm%20trail
		$url = $this->settings['cyclescapeApiBaseUrl'] . '/issues';
		$parameters = array (
			'per_page'	=> 10,
		);
		if ($q) {
			$parameters['term'] = $q;
		}
		if ($id) {
			$parameters['id'] = $id;
		}
		$issues = $this->getApiData ($url, $parameters);
		
		# Remove duplicated values, pending https://github.com/cyclestreets/cyclescape/issues/921#issuecomment-583544405
		$issues['features'] = array_intersect_key ($issues['features'], array_unique (array_map ('serialize', $issues['features'])));
		
		# Map each record to a GeoJSON feature in the same format as the Geocoder response
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = $this->getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'name'	=> $issue['properties']['title'],
					'near'	=> $this->truncate (strip_tags ($issue['properties']['description']), 80),
					'bbox'	=> $bbox,
				),
				'geometry'	=> array (
					'type'			=> 'Point',
					'coordinates'	=> array ($centroid['lon'], $centroid['lat']),
				),
			);
		}
		
		# Return the features
		return $features;
	}
	
	
	# Helper function to do geocoder search (part of the CycleStreets API suite)
	private function searchGeocoder ($q)
	{
		# Assemble the request
		$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/geocoder';
		$parameters = array (
			'key'			=> $this->settings['cyclestreetsApiKey'],
			'bounded'		=> 1,
			'bbox'			=> $this->settings['autocompleteBbox'],
			'limit'			=> 12,
			'countrycodes'	=> 'gb,ie',
			'q'				=> $q,
		);
		
		# Obtain the data
		$data = $this->getApiData ($url, $parameters);
		
		# Return the features
		return $data['features'];
	}
	
	
	# Function to serve data on a planning application
	private function api_planningapplication ()
	{
		# Ensure an application ID is specified
		if (!isSet ($_GET['id']) || !strlen ($_GET['id'])) {
			return $this->errorJson ('No application ID was specified.');
		}
		$id = $_GET['id'];
		
		# Get the data from PlanIt
		$apiUrl = $this->settings['planitBaseUrl'] . '/planapplic/' . $id . '/geojson';
		$data = $this->getApiData ($apiUrl);
		
		# Return the data
		return $this->asJson ($data);
	}
	
	
	# Function to get a BBOX surrounding a point
	private function pointToBbox ($latitude, $longitude, $distance /* in km */)
	{
		# Work through each bearing to assign a point
		# See: https://www.sitepoint.com/community/t/adding-distance-to-gps-coordinates-to-get-bounding-box/5820/11
		$coordinates = array ();
		$bearings = array ('w' => 270, 's' => 180, 'e' => 90, 'n' => 0);
		foreach ($bearings as $direction => $bearing) {
			$radius = 6371;
			$newLatitude = rad2deg (asin (sin (deg2rad ($latitude)) * cos ($distance / $radius) + cos (deg2rad ($latitude)) * sin ($distance / $radius) * cos (deg2rad ($bearing))));
			$newLongitude = rad2deg (deg2rad ($longitude) + atan2 (sin (deg2rad ($bearing)) * sin ($distance / $radius) * cos (deg2rad ($latitude)), cos ($distance / $radius) - sin (deg2rad ($latitude)) * sin (deg2rad ($newLatitude))));
			$coordinates[$direction] = array ($newLatitude, $newLongitude);
		}
		
		# Construct the BBOX
		$bbox = array (
			'w'	=> round ($coordinates['w'][1], 6),
			's'	=> round ($coordinates['s'][0], 6),
			'e'	=> round ($coordinates['e'][1], 6),
			'n'	=> round ($coordinates['n'][0], 6),
		);
		
		# Implode to string
		$bbox = implode (',', $bbox);
		
		# Return the result
		return $bbox;
	}
	
	
	# Function to serve proposals data
	private function api_proposals ()
	{
		# Ensure a BBOX is specified
		if (!isSet ($_GET['bbox']) || !strlen ($_GET['bbox']) || !preg_match ('/^([-.0-9]+),([-.0-9]+),([-.0-9]+),([-.0-9]+)$/', $_GET['bbox'])) {
			return $this->errorJson ('No valid BBOX was specified.');
		}
		$bbox = $_GET['bbox'];
		
		# Start a GeoJSON result; the search drivers will return GeoJSON features rather than a full GeoJSON result
		$data = array ('type' => 'FeatureCollection', 'features' => array ());
		
		# Get the proposals
		$data['features'] = $this->getProposals ($bbox);
		
		# Return the data
		return $this->asJson ($data);
	}
	
	
	# Function to get proposals
	private function getProposals ($bbox)
	{
		# Start a data array
		$data = array ();
		
		# Get the Cyclescape issues within the specified BBOX
		$data += $this->getCyclescapeIssues ($bbox);
		
		# Get the CycleStreets Photomap issues within the specified BBOX
		if ($this->userIsAdministrator) {
			$data = array_merge ($data, $this->getCyclestreetsIssues ($bbox));
		}
		
		# If signed in as an administrator, get the external issues within the specified BBOX
		if ($this->userIsAdministrator) {
			$data = array_merge ($data, $this->getExternalIssues ($bbox));
		}
		
		# Return the data
		return $data;
	}
	
	
	# Helper function to get Cyclescape issues within a BBOX
	private function getCyclescapeIssues ($bbox)
	{
		# Search Cyclescape, e.g. /api/issues?per_page=10&term=chisholm%20trail
		$url = $this->settings['cyclescapeApiBaseUrl'] . '/issues';
		$parameters = array (
			'per_page'			=> 200,
			'bbox'				=> $bbox,
			'excluding_tags'	=> json_encode (array (
				'planning',			// Omit planning applications imported as issues
				'consultation',		// Omit council consultations discussed as issues
				'maintenance',		// Omit maintenance issues, as these require revenue funding and therefore out of scope for S106
				'enforcement',		// Omit enforcement-related issues, as these do not relate to physical infrastructure
				'event',			// Omit events, as they are one-off
				'temporary',		// Omit other temporary matters
			)),
			'open_threads'		=> true,
		);
		$issues = $this->getApiData ($url, $parameters);
		
		# Simplify the output, converting geometries to Point, and removing non-needed properties
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Get the BBOX
			$this->getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'id'				=> $issue['properties']['id'],
					'moniker'			=> 'cyclescape/' . $issue['properties']['id'],
					'source'			=> 'Cyclescape',
					'title'				=> $issue['properties']['title'],
					'description'		=> str_replace ('<a href=', '<a target="_blank" href=', $issue['properties']['description']),
					'image'				=> ($issue['properties']['photo_thumb_url'] ? $this->settings['cyclescapeBaseUrl'] . $issue['properties']['photo_thumb_url'] : NULL),
					'link'				=> $issue['properties']['cyclescape_url'],
					'categories'		=> $issue['properties']['tags'],
					'address'			=> NULL,
					'when'				=> $issue['properties']['created_at'],
					'bbox'				=> $bbox,
				),
				'geometry'	=> array (
					'type'			=> 'Point',
					'coordinates'	=> array ($issue['properties']['centre']['lon'], $issue['properties']['centre']['lat']),
				),
			);
		}
		
		# Return the features
		return $features;
	}
	
	
	# Helper function to get CycleStreets Photomap issues within a BBOX
	private function getCyclestreetsIssues ($bbox)
	{
		# Search the CycleStreets Photomap, e.g. /v2/photomap.locations?fields=id,title,caption,thumbnailUrl,url,tags,datetime&limit=150&thumbnailsize=800&category=cycleparking&metacategory=bad&bbox=0.137134,52.202825,0.152265,52.205950
		$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/photomap.locations';
		$parameters = array (
			'key'			=> $this->settings['cyclestreetsApiKey'],
			'fields'		=> 'id,title,caption,thumbnailUrl,url,tags,datetime',
			'limit'			=> 150,
			'thumbnailsize'	=> 800,
			'category'		=> 'cycleparking',		// #!# Need to opt-in other categories, e.g. obstructions, pending API support for multiple categories
			'metacategory'	=> 'bad',
			'bbox'			=> $bbox,
		);
		$issues = $this->getApiData ($url, $parameters);
		
		# Simplify the output, converting geometries to Point, and removing non-needed properties
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = $this->getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'id'				=> $issue['properties']['id'],
					'moniker'			=> 'cyclestreets/' . $issue['properties']['id'],
					'source'			=> 'CycleStreets',
					'title'				=> $issue['properties']['title'],
					'description'		=> $issue['properties']['caption'],
					'image'				=> $issue['properties']['thumbnailUrl'],
					'link'				=> $issue['properties']['url'],
					'categories'		=> $issue['properties']['tags'],
					'address'			=> NULL,
					'when'				=> $issue['properties']['datetime'],
					'bbox'				=> $bbox,
				),
				'geometry'	=> array (
					'type'			=> 'Point',
					'coordinates'	=> array ($centroid['lon'], $centroid['lat']),
				),
			);
		}
		
		# Return the features
		return $features;
	}
	
	
	# Helper function to get external issues
	private function getExternalIssues ($bbox = false, $id = false)
	{
		# Start a list of features
		$features = array ();
		
		# Start a list of constraints
		$where = array ();
		$preparedStatementValues = array ();
		
		# BBOX support
		if ($bbox) {
			list ($w, $s, $e, $n) = explode (',', $bbox);
			$where[] = 'MBRCONTAINS(ST_LINESTRINGFROMTEXT(:linestring), POINT(longitude, latitude))';
			$preparedStatementValues['linestring'] = "LINESTRING({$w} {$s}, {$e} {$n})";
		}
		
		# ID support
		if ($id) {
			$where[] = 'id = :id';
			$preparedStatementValues['id'] = $id;
		}
		
		# Get the features from the database
		$query = 'SELECT
			*
			FROM proposalsexternal
			WHERE
			' . implode (' AND ', $where) . '
		;';
		$data = $this->databaseConnection->getData ($query, false, true, $preparedStatementValues);
		
		# Arrange as GeoJSON features
		foreach ($data as $record) {
			
			# Extract the co-ordinates
			$coordinates = array ($record['longitude'], $record['latitude']);
			unset ($record['longitude']);
			unset ($record['latitude']);
			
			# Remove unwanted fields
			unset ($record['agree']);
			unset ($record['user']);
			
			# Emulate title from description where not present
			if (!$record['title']) {
				$record['title'] = $this->truncate ($record['description'], 40);
			}
			
			# Convert time
			$record['when'] = strtotime ($record['when']);
			
			# Add a unique moniker
			$record['moniker'] = 'external/' . $record['id'];
			
			# Register the feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> $record,
				'geometry'	=> array (
					'type'			=> 'Point',
					'coordinates'	=> $coordinates,
				),
			);
		}
		
		# Return the features
		return $features;
	}
	
	
	# Helper function to get data from an API
	private function getApiData ($url, $parameters = array ())
	{
		# Construct the URL
		if ($parameters) {
			$url .= '?' . http_build_query ($parameters);
		}
		
		# Get the data
		$data = file_get_contents ($url);
		$data = json_decode ($data, true);
		
		# Return the data
		return $data;
	}
	
	
	# Function to truncate a string
	private function truncate ($string, $length)
	{
		if (mb_strlen ($string) > $length) {
			$string = mb_substr ($string, 0, $length) . '…';
		}
		return $string;
	}
	
	
	# Function to reformat capitalised text
	private function reformatCapitalised ($string)
	{
		# Convert to sentence case if no lower-case letters present and a group of two or more upper-case letters are present
		if (!preg_match ('/[a-z]/', $string) && preg_match ('/[A-Z]{2,}/', $string)) {
			return mb_ucfirst (mb_strtolower ($string));	// Provided by application.php
		}
		
		# Else return unmodified
		return $string;
	}
	
	
	# Helper function to get the centre-point of a geometry
	private function getCentre ($geometry, &$bbox = array ())
	{
		# Determine the centre point
		switch ($geometry['type']) {
			
			case 'Point':
				$centre = array (
					'lat'	=> $geometry['coordinates'][1],
					'lon'	=> $geometry['coordinates'][0]
				);
				$bbox = implode (',', array ($centre['lon'], $centre['lat'], $centre['lon'], $centre['lat']));
				break;
				
			case 'LineString':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['coordinates'] as $lonLat) {
					$longitudes[] = $lonLat[0];
					$latitudes[] = $lonLat[1];
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				$bbox = implode (',', array (min ($longitudes), min ($latitudes), max ($longitudes), max ($latitudes)));
				break;
				
			case 'MultiLineString':
			case 'Polygon':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['coordinates'] as $line) {
					foreach ($line as $lonLat) {
						$longitudes[] = $lonLat[0];
						$latitudes[] = $lonLat[1];
					}
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				$bbox = implode (',', array (min ($longitudes), min ($latitudes), max ($longitudes), max ($latitudes)));
				break;
				
			case 'MultiPolygon':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['coordinates'] as $polygon) {
					foreach ($polygon as $line) {
						foreach ($line as $lonLat) {
							$longitudes[] = $lonLat[0];
							$latitudes[] = $lonLat[1];
						}
					}
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				$bbox = implode (',', array (min ($longitudes), min ($latitudes), max ($longitudes), max ($latitudes)));
				break;
				
			case 'GeometryCollection':
				$longitudes = array ();
				$latitudes = array ();
				foreach ($geometry['geometries'] as $geometryItem) {
					$centroid = $this->getCentre ($geometryItem, $bboxItem);	// Iterate
					$longitudes[] = $centroid['lon'];
					$latitudes[] = $centroid['lat'];
				}
				$centre = array (
					'lat'	=> ((max ($latitudes) + min ($latitudes)) / 2),
					'lon'	=> ((max ($longitudes) + min ($longitudes)) / 2)
				);
				$bbox = implode (',', array (min ($longitudes), min ($latitudes), max ($longitudes), max ($latitudes)));	// #!# Need to iterate BBOX items instead
				break;
		}
		
		# Reduce decimal places for output brevity
		$centre['lon'] = (float) number_format ($centre['lon'], 6);
		$centre['lat'] = (float) number_format ($centre['lat'], 6);
		
		# Return the centre
		return $centre;
	}
	
	
	# API call to get the monitored areas of a user
	private function api_monitors ()
	{
		# End if no user
		if (!$this->user) {
			return $this->errorJson ('You must be logged in to access this data.');
		}
		
		# Get the data
		$query = "SELECT
				id,
				ST_AsGeoJSON(location) AS location,
				app_type,
				app_size
			FROM monitors
			WHERE email = :email
		;";
		$preparedStatementValues = array ('email' => $this->user['email']);		// Will pick up the user's cookie
		$data = $this->databaseConnection->getData ($query, false, true, $preparedStatementValues);
		
		# Convert each row to a GeoJSON feature
		$features = array ();
		foreach ($data as $monitor) {
			$features[] = array (
				'type' => 'Feature',
				'geometry' => json_decode ($monitor['location']),
				'properties' => array (
					'id' => (int) $monitor['id'],
					'app_type' => ($monitor['app_type'] ? str_replace (',', ', ', $monitor['app_type']) : NULL),
					'app_size' => ($monitor['app_size'] ? str_replace (',', ', ', $monitor['app_size']) : NULL),
				),
			);
		}
		
		# Arrange as GeoJSON
		$result = array ('type' => 'FeatureCollection', 'features' => $features);
		
		# Return the data
		return $this->asJson ($result);
	}
}

?>
