<?php

# Planning applications model, providing an interface to the upstream API
class planningapplicationsModel
{
	# Constructor
	public function __construct ($settings)
	{
		# Global the settings
		$this->settings = $settings;
	}
	
	
	# Function to get a single planning application by full ID (place+id)
	public function getOne ($fullId)
	{
		# Get the data from the PlanIt API
		$apiUrl = $this->settings['planitBaseUrl'] . '/planapplic/' . $fullId . '/geojson';
		$application = streetfocus::getApiData ($apiUrl);
		
		# Return the application
		return $application;
	}
	
	
	# Function to get planning applications matching an ID
	public function searchById ($id, $authority = false)
	{
		# Get the data from the PlanIt API
		$url = $this->settings['planitBaseUrl'] . '/api/applics/geojson';
		$parameters = array (
			'id_match'	=> $id,
		);
		if ($authority) {
			$parameters['auth'] = $authority;
		}
		$applications = streetfocus::getApiData ($url, $parameters);
		
		# Return the applications
		return $applications;
	}
	
	
	# Function to get the total number of planning applications
	public function getTotal ()
	{
		# Get the data from the PlanIt API
		$url = $this->settings['planitBaseUrl'] . '/api/applics/json';
		$parameters = array (
			'limit'		=> 1,
			'pg_sz'		=> 1,
			'recent'	=> 100,		// 100 days, just over 14 weeks
			'app_state'	=> 'Undecided',
		);
		$data = streetfocus::getApiData ($url, $parameters);
		
		# Extract the value
		$total = $data['total'];
		
		# Return the result
		return $total;
	}
}

?>