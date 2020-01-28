<?php

# Controller for StreetFocus
class streetfocus
{
	# Settings
	function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'planitApiBaseUrl'		=> 'https://www.planit.org.uk/api',
			'cyclestreetsApiKey'	=> NULL,
			'mapboxApiKey'			=> NULL,
			'googleApiKey'			=> NULL,
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
}

?>
