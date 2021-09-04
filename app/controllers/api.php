<?php

# API
class api
{
	# Constructor
	public function __construct ($streetfocus)
	{
		# Create properties handles
		$this->streetfocus = $streetfocus;
		$this->settings = $streetfocus->settings;
		$this->databaseConnection = $streetfocus->databaseConnection;
		$this->user = $streetfocus->user;
		$this->userIsAdministrator = $streetfocus->userIsAdministrator;
	}
	
	
	
	# API endpoint
	public function call ()
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
			if (preg_match ('|^(.+)/(.+)$|', $q)) {		// E.g. 19/1780/FUL
				require_once ('app/models/planningapplications.php');
				$planningapplicationsModel = new planningapplicationsModel ($this->settings);
				$data['features'] += $planningapplicationsModel->searchById ($q);
				return $this->asJson ($data);	// Do not search other data sources, so return at this point
			}
		}
		
		# Cyclescape issues search
		if (in_array ('cyclescape', $sources)) {
			require_once ('app/models/ideas.php');
			$ideasModel = new ideasModel ($this->settings, $this->databaseConnection, $this->userIsAdministrator);
			$data['features'] += $ideasModel->searchCyclescapeIssues ($q);
		}
		
		# Geocoder search
		if (in_array ('geocoder', $sources)) {
			require_once ('app/models/geocoder.php');
			$geocoderModel = new geocoderModel ($this->settings);
			$data['features'] += $geocoderModel->search ($q);
		}
		
		# Return the response
		return $this->asJson ($data);
	}
	
	
	# Function to serve data on a planning application
	private function api_planningapplication ()
	{
		# Ensure an application ID is specified
		if (!isSet ($_GET['id']) || !strlen ($_GET['id'])) {
			return $this->errorJson ('No application ID was specified.');
		}
		$id = $_GET['id'];
		
		# Get the planning application
		require_once ('app/models/planningapplications.php');
		$planningapplicationsModel = new planningapplicationsModel ($this->settings);
		$data = $planningapplicationsModel->getOne ($id);
		
		# Determine a BBOX around the planning application, for use in determining nearby ideas
		$distanceKm = 0.1;
		$bbox = $this->pointToBbox ($data['features'][0]['geometry']['coordinates'][1], $data['features'][0]['geometry']['coordinates'][0], $distanceKm);
		
		# Get the ideas, and these to the data
		require_once ('app/models/ideas.php');
		$ideasModel = new ideasModel ($this->settings, $this->databaseConnection, $this->userIsAdministrator);
		$data['features'][0]['properties']['_ideas'] = $ideasModel->getIdeas ($bbox);
		
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
	
	
	# Function to serve ideas data
	private function api_ideas ()
	{
		# Ensure a BBOX is specified
		if (!isSet ($_GET['bbox']) || !strlen ($_GET['bbox']) || !preg_match ('/^([-.0-9]+),([-.0-9]+),([-.0-9]+),([-.0-9]+)$/', $_GET['bbox'])) {
			return $this->errorJson ('No valid BBOX was specified.');
		}
		$bbox = $_GET['bbox'];
		
		# Start a GeoJSON result; the search drivers will return GeoJSON features rather than a full GeoJSON result
		$data = array ('type' => 'FeatureCollection', 'features' => array ());
		
		# Get the ideas
		require_once ('app/models/ideas.php');
		$ideasModel = new ideasModel ($this->settings, $this->databaseConnection, $this->userIsAdministrator);
		$data['features'] = $ideasModel->getIdeas ($bbox);
		
		# Return the data
		return $this->asJson ($data);
	}
	
	
	# API call to get the monitored areas of a user
	private function api_monitors ()
	{
		# End if no user
		if (!$this->user) {
			return $this->errorJson ('You must be logged in to access this data.');
		}
		
		# Get the data
		require_once ('app/models/monitors.php');
		$monitorsModel = new monitorsModel ($this->settings, $this->databaseConnection);
		$monitors = $monitorsModel->forUser ($this->user['email']);		// Will pick up the user's cookie
		
		# Convert each row to a GeoJSON feature
		$features = array ();
		foreach ($monitors as $monitor) {
			$features[] = array (
				'type' => 'Feature',
				'geometry' => json_decode ($monitor['location']),
				'properties' => array (
					'id' => (int) $monitor['id'],
					'type' => ($monitor['type'] ? str_replace (',', ', ', $monitor['type']) : NULL),
					'size' => ($monitor['size'] ? str_replace (',', ', ', $monitor['size']) : NULL),
				),
			);
		}
		
		# Arrange as GeoJSON
		$result = array ('type' => 'FeatureCollection', 'features' => $features);
		
		# Return the data
		return $this->asJson ($result);
	}
}
