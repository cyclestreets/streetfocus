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
			'ideas' => array (
				'description' => 'Ideas for street changes',
				'url' => '/ideas/',
			),
			'addidea' => array (
				'description' => 'Add an idea',
				'url' => '/ideas/add/',
				'authentication' => true,
			),
			'my' => array (
				'description' => 'Monitor areas',
				'url' => '/my/',
			),
			'addmonitor' => array (
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
			  `type` SET('Full','Outline','Amendment','Conditions','Heritage','Trees','Advertising','Telecoms','Other') DEFAULT NULL COMMENT 'Type(s)',
			  `size` SET('Small','Medium','Large') DEFAULT NULL COMMENT 'Size(s)',
			  `email` VARCHAR(255) NOT NULL COMMENT 'E-mail',
			  `createdAt` DATETIME NOT NULL COMMENT 'Created at'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monitors set by users';
			
			-- External ideas
			CREATE TABLE `ideassexternal` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ideas data from external sources';
			
			-- Internal ideas (directly added)
			CREATE TABLE ideasinternal LIKE ideasexternal;
			ALTER TABLE ideasinternal COMMENT 'Ideas data (directly added)';
			ALTER TABLE ideasinternal CHANGE id id INT(11) NOT NULL AUTO_INCREMENT;
			ALTER TABLE ideasinternal CHANGE source source VARCHAR(255) NOT NULL DEFAULT 'Internal';
			ALTER TABLE ideasinternal CHANGE link link VARCHAR(255) NULL DEFAULT NULL;
			ALTER TABLE ideasinternal CHANGE `when` `when` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
		";
	}
	
	
	# Class properties
	public $settings;
	public $databaseConnection;
	private $baseUrl;
	private $template = array ();
	private $templateFile;
	public $user;
	public $userIsAdministrator;
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
				$this->template['reason'] = lcfirst ($this->actions[$this->action]['description']);
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
	
	
	
	# Home page
	private function home ()
	{
		# Get the total number of planning applications
		require_once ('app/models/planningapplications.php');
		$planningapplicationsModel = new planningapplicationsModel ($this->settings);
		$this->template['totalApplications'] = $planningapplicationsModel->getTotal ();
		
		# Get total matched ideas
		// #!# Example data at present - needs API integration
		$this->template['matchedIdeas'] = '253';
	}
	
	
	# Planning applications map page
	private function planningapplications ()
	{
		# If an ID is specified, determine the map location, so that the item is present in the area data
		if ($this->id) {
			if (preg_match ('|^(.+)/(.+)$|', $this->id)) {
				list ($authority, $id) = explode ('/', $this->id, 2);
				require_once ('app/models/planningapplications.php');
				$planningapplicationsModel = new planningapplicationsModel ($this->settings);
				if ($planningApplication = $planningapplicationsModel->searchById ($id, $authority)) {
					$this->setLocationFromFeature ($planningApplication[0]);
				}
			}
		}
	}
	
	
	# Ideas map page
	private function ideas ()
	{
		# If an ID is specified, determine the map location, so that the item is present in the area data
		if ($this->id) {
			if (preg_match ('|^(.+)/(.+)$|', $this->id)) {
				list ($source, $id) = explode ('/', $this->id, 2);
				
				# Load the ideas model
				require_once ('app/models/ideas.php');
				$ideasModel = new ideasModel ($this->settings, $this->databaseConnection, $this->userIsAdministrator);
				
				# Select source
				switch ($source) {
					
					# Cyclescape
					case 'cyclescape':
						if ($issue = $ideasModel->searchCyclescapeIssues (false, $id)) {
							$this->setLocationFromFeature ($issue[0]);
						}
						break;
						
					# External
					case 'external':
						if ($issue = $ideasModel->getExternalIssues (false, $id)) {
							$this->setLocationFromFeature ($issue[0]);
						}
						break;
				}
			}
		}
	}
	
	
	# Function to add an idea
	private function addidea ()
	{
		# Load the ideas model
		require_once ('app/models/ideas.php');
		$ideasModel = new ideasModel ($this->settings, $this->databaseConnection, $this->userIsAdministrator);
		
		//
	}
	
	
	# Function to set a location from a feature
	private function setLocationFromFeature ($feature)
	{
		$zoom = 17;
		$latitude  = $feature['geometry']['coordinates'][1];
		$longitude = $feature['geometry']['coordinates'][0];
		$this->setLocation = "{$zoom}/{$latitude}/{$longitude}/0/0";
	}
	
	
	# Monitor areas page
	private function my ()
	{
		# Determine whether to the map
		$this->template['user'] = ($this->user);
	}
	
	
	# Add a monitor page
	private function addmonitor ()
	{
		# Set user e-mail for the template
		$this->template['email'] = $this->user['email'];
		
		# End if BBOX not posted; this field must be present but others are optional
		$bboxRegexp = '^([-\.0-9]+),([-\.0-9]+),([-\.0-9]+),([-\.0-9]+)$';
		if (!isSet ($_POST['bbox']) || !preg_match ("/{$bboxRegexp}/", $_POST['bbox'], $matches)) {return;}
		$bbox = array ();
		list ($ignore, $bbox['w'], $bbox['s'], $bbox['e'], $bbox['n']) = $matches;
		
		# Obtain type and size
		$type = (isSet ($_POST['type']) ? implode (',', $_POST['type']) : NULL);
		$size = (isSet ($_POST['size']) ? implode (',', $_POST['size']) : NULL);
		
		# Load the monitors model
		require_once ('app/models/monitors.php');
		$monitorsModel = new monitorsModel ($this->settings, $this->databaseConnection);
		$result = $monitorsModel->add ($bbox, $type, $size, $this->user['email']);
		
		# Set the outcome
		$this->template['result'] = (bool) $result;
	}
	
	
	# Function to load the application JS
	private function applicationJs ()
	{
		# Create the application JS
		return trim ("
			$(function() {
				config = {
					defaultLocation: '{$this->settings['defaultLocation']}',
					cyclestreetsApiBaseUrl: '{$this->settings['cyclestreetsApiBaseUrl']}',
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
	
	
	# API endpoint
	private function api ()
	{
		# Subclass
		require_once ('app/controllers/api.php');
		$api = new api ($this);
		return $api->call ();
	}
	
	
	# 404 page
	private function page404 ()
	{
		// Static page
	}
	
	
	# Helper function to get data from an API
	public static function getApiData ($url, $parameters = array ())
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
	public static function truncate ($string, $length)
	{
		if (mb_strlen ($string) > $length) {
			$string = mb_substr ($string, 0, $length) . '…';
		}
		return $string;
	}
	
	
	# Function to reformat capitalised text
	public static function reformatCapitalised ($string)
	{
		# Convert to sentence case if no lower-case letters present and a group of two or more upper-case letters are present
		if (!preg_match ('/[a-z]/', $string) && preg_match ('/[A-Z]{2,}/', $string)) {
			return mb_ucfirst (mb_strtolower ($string));	// Provided by application.php
		}
		
		# Else return unmodified
		return $string;
	}
	
	
	# Helper function to get the centre-point of a geometry
	public static function getCentre ($geometry, &$bbox = array ())
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
					$centroid = self::getCentre ($geometryItem, $bboxItem);	// Iterate
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
}

?>
