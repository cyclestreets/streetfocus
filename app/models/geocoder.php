<?php

# Geocoder model, providing an interface to the upstream API
class geocoderModel
{
	# Class properties
	private $settings;
	
	
	# Constructor
	public function __construct ($settings)
	{
		# Create properties handles
		$this->settings = $settings;
	}
	
	
	
	# Function to do geocoder search (part of the CycleStreets API suite)
	public function search ($q)
	{
		# Assemble the request
		$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/geocoder';
		$parameters = array (
			'key'		=> $this->settings['cyclestreetsApiKey'],
			'bounded'	=> 1,
			'bbox'		=> $this->settings['autocompleteBbox'],
			'limit'		=> 12,
			'countrycodes'	=> 'gb,ie',
			'q'		=> $q,
			'fields'	=> 'name,near,type,bbox',
		);
		
		# Obtain the data
		$data = streetfocus::getApiData ($url, $parameters);
		
		# Return the features
		return $data['features'];
	}
}

?>
