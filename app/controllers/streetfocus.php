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
			'cyclestreetsApiBaseUrl'	=> 'https://api.cyclestreets.net',
			'cyclestreetsApiKey'		=> NULL,
			'mapboxApiKey'				=> NULL,
			'googleApiKey'				=> NULL,
			'autocompleteBbox'			=> '-6.6577,49.9370,1.7797,57.6924',
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
		
		# If planning application is a specified datasource, search for an ID first
		if (in_array ('planit', $sources)) {
			if (preg_match ('|^[0-9]{2}/[0-9]{4}/[A-Z]+$|', $q)) {		// E.g. 19/1780/FUL
				
				# Search PlanIt
				$url = $this->settings['planitApiBaseUrl'] . '/applics/json';
				$parameters = array (
					'id_match'	=> $q,
				);
				$applications = $this->getApiData ($url, $parameters);
				
				# Map each record to a GeoJSON feature in the same format as the Geocoder response
				$data = array ('type' => 'FeatureCollection', 'features' => array ());
				foreach ($applications['records'] as $record) {
					$data['features'][] = array (
						'type'			=> 'Feature',
						'properties'	=> array (
							'name'	=> $this->truncate ($this->reformatCapitalised ($record['description']), 80),
							'near'	=> $record['authority_name'],
							'bbox'	=> "{$record['lng']},{$record['lat']},{$record['lng']},{$record['lat']}",
						),
						'geometry'	=> array (
							'type'			=> 'Point',
							'coordinates'	=> array ($record['lng'], $record['lat']),
						),
					);
				}
				
				# Return the GeoJSON response
				return $this->asJson ($data);
			}
		}
		
		# Geocode search
		if (in_array ('cyclestreets', $sources)) {
			$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/geocoder';
			$parameters = array (
				'key'			=> $this->settings['cyclestreetsApiKey'],
				'bounded'		=> 1,
				'bbox'			=> $this->settings['autocompleteBbox'],
				'limit'			=> 12,
				'countrycodes'	=> 'gb,ie',
				'q'				=> $q,
			);
			$data = $this->getApiData ($url, $parameters);
		}
		
		# Return the response
		return $this->asJson ($data);
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
}

?>
