<?php

# Planning applications model, providing an interface to the upstream API
class planningapplicationsModel
{
	# Constructor
	public function __construct ($settings)
	{
		# Create properties handles
		$this->settings = $settings;
	}
	
	
	
	# Function to get a single planning application by full ID (place+id)
	public function getOne ($fullId)
	{
		# Get the data from the CycleStreets API
		$apiUrl = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/planningapplications.location' . "?key={$this->settings['cyclestreetsApiKey']}&id=" . $fullId;
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
		
		# Map each record to a GeoJSON feature in the same format as the Geocoder response
		#!# NB Location data is not always present
		$features = array ();
		foreach ($applications['features'] as $record) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = streetfocus::getCentre ($record['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'name'	=> streetfocus::truncate (streetfocus::reformatCapitalised ($record['properties']['description']), 80),
					'near'	=> $record['properties']['area_name'],
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
