<?php

# Controller for StreetFocus
class streetfocus
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'planitApiBaseUrl'			=> 'https://www.planit.org.uk/api',
			'cyclescapeApiBaseUrl'		=> 'https://www.cyclescape.org/api',
			'cyclestreetsApiBaseUrl'	=> 'https://api.cyclestreets.net',
			'cyclestreetsApiKey'		=> NULL,
			'mapboxApiKey'				=> NULL,
			'googleApiKey'				=> NULL,
			'autocompleteBbox'			=> '-6.6577,49.9370,1.7797,57.6924',
			'authNamespace'				=> 'streetfocus\\',
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
			'map' => array (
				'description' => 'Planning applications',
				'url' => '/map/',
			),
			'proposals' => array (
				'description' => 'Proposals',
				'url' => '/proposals/',
			),
			'my' => array (
				'description' => 'Monitor areas',
				'url' => '/my/',
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
				'url' => '/api/',
				'data' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Class properties
	private $settings;
	private $baseUrl;
	private $template = array ();
	private $templateFile;
	private $user;
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Set the include path to include libraries
		set_include_path ($_SERVER['DOCUMENT_ROOT'] . '/libraries/' . PATH_SEPARATOR . get_include_path ());
		
		# Load required libraries
		require_once ('application.php');
		
		# Define the location of the stub launching file
		$this->baseUrl = application::getBaseUrl ();
		
		# Assign settings
		if (!$this->settings = application::assignArguments ($errors, $settings, $this->defaults (), get_class ($this), NULL, $handleErrors = true)) {return false;}
		
		# Assign the action, validating against the registry
		$this->action = $_GET['action'];
		$this->actions = $this->actions ();
		if (!isSet ($this->actions[$this->action])) {
			$html = $this->page404 ();
			echo $html;
			return false;
		}
		$this->template['_action'] = $this->action;
		
		# Set the template, being the path from /app/views/, minus .tpl
		$url = $this->actions[$this->action]['url'];
		$templatePath = ltrim ($url, '/') . 'index';
		
		# Load the application JS, including mapping and the menu handling
		$this->template['_settings'] = $this->settings;
		
		# Get the user's details, if authenticated
		require_once ('app/controllers/userAccount.php');
		$this->userAccount = new userAccount ($this->settings, $this->template, $this->baseUrl);
		$this->user = $this->userAccount->getUser ();
		$this->template = $this->userAccount->getTemplate ();
		
		# Perform the action, which will write into the page template array
		$this->{$this->action} ();
		
		# End if the action is a data URL rather than a templatised page
		if (isSet ($this->actions[$this->action]['data']) && $this->actions[$this->action]['data']) {return;}
		
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
		$this->template = $this->userAccount->getTemplate ();
	}
	
	
	# Logout
	private function logout ()
	{
		# Delegate to the user account class and receive the template values
		$this->userAccount->logout ();
		$this->user = $this->userAccount->getUser ();	// Re-query, for menu status
		$this->template = $this->userAccount->getTemplate ();
	}
	
	
	# Register
	private function register ()
	{
		# Delegate to the user account class and receive the template values
		$this->userAccount->register ();
		$this->template = $this->userAccount->getTemplate ();
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
	private function map ()
	{
		//
	}
	
	
	# Proposals map page
	private function proposals ()
	{
		//
	}
	
	
	# Monitor areas page
	private function my ()
	{
		//
	}
	
	
	# Function to load the application JS
	private function applicationJs ()
	{
		# Create the application JS
		$this->template['applicationJs'] = "
			<script>
				$(function() {
					config = {
						planitApiBaseUrl: '{$this->settings['planitApiBaseUrl']}',
						cyclestreetsApiKey: '{$this->settings['cyclestreetsApiKey']}',
						mapboxApiKey: '{$this->settings['mapboxApiKey']}'
					};
					streetfocus.initialise (config, '{$this->action}');
				});
			</script>
		";
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
	
	
	# Helper fucnction to send an API error
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
		
		# If cyclescape issues is a specified datasource, search for an ID first
		if (in_array ('cyclescape', $sources)) {
			$data['features'] += $this->searchCyclescape ($q);
		}
		
		# Geocode search
		if (in_array ('cyclestreets', $sources)) {
			$data['features'] += $this->searchCycleStreets ($q);
		}
		
		# Return the response
		return $this->asJson ($data);
	}
	
	
	# Helper function to do a PlanIt ID search
	private function searchPlanIt ($id)
	{
		# Search PlanIt
		$url = $this->settings['planitApiBaseUrl'] . '/applics/geojson';
		$parameters = array (
			'id_match'	=> $id,
		);
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
	private function searchCyclescape ($q)
	{
		# Search Cyclescape, e.g. /api/issues?per_page=10&term=chisholm%20trail
		$url = $this->settings['cyclescapeApiBaseUrl'] . '/issues';
		$parameters = array (
			'per_page'	=> 10,
			'term'		=> $q,
		);
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
	
	
	# Helper function to do CycleStreets geocoder search
	private function searchCycleStreets ($q)
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
	
	
	# Helper function to get data from an API
	private function getApiData ($url, $parameters)
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
		
		# Return the centre
		return $centre;
	}
}

?>
