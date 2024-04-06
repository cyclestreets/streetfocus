<?php

# Planning applications model, providing an interface to the upstream API
class planningapplicationsModel
{
	# Class properties
	private $settings;
	
	
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
	public function searchById ($uid)
	{
		# Construct the API request URL and parameters
		$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/planningapplications.locations';
		$parameters = array (
			'key'		=> $this->settings['cyclestreetsApiKey'],
			'bbox'		=> $this->settings['autocompleteBbox'],
			'uid'		=> $uid,
		);
		
		# Get the data
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
					'near'	=> $record['properties']['area'],
					'type'	=> 'Planning application',
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
	#!# Is a bit slow - ideally add caching, or get the upstream API to have bbox optional, as is a pointless constraint
	public function getTotal ()
	{
		# Construct the API request URL and parameters
		$url = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/planningapplications.statistics';
		$parameters = array (
			'key'		=> $this->settings['cyclestreetsApiKey'],
			'bbox'		=> $this->settings['autocompleteBbox'],
			'since'		=> date ('Y-m-d', strtotime ("-{$this->settings['daysRecent']} days")),
			'state'		=> 'Undecided',
		);
		
		# Get the data
		$data = streetfocus::getApiData ($url, $parameters);
		
		# Extract the value
		$total = $data['total'];
		
		# Return the result
		return $total;
	}
}

?>
