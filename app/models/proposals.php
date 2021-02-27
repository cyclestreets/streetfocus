<?php

# Proposals model, providing an interface to the upstream APIs
class proposalsModel
{
	# Constructor
	public function __construct ($settings, $databaseConnection)
	{
		# Create properties handles
		$this->settings = $settings;
		$this->databaseConnection = $databaseConnection;
	}
	
	
	
	# Helper function to do a Cyclescape issues search
	public function searchCyclescapeIssues ($q = false, $id = false)
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
		$issues = streetfocus::getApiData ($url, $parameters);
		
		# Remove duplicated values, pending https://github.com/cyclestreets/cyclescape/issues/921#issuecomment-583544405
		$issues['features'] = array_intersect_key ($issues['features'], array_unique (array_map ('serialize', $issues['features'])));
		
		# Map each record to a GeoJSON feature in the same format as the Geocoder response
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = streetfocus::getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
			# Register this feature
			#!# 'when' is added as a minimal extra field for populating popup - need to align the geocoder and map formats
			$features[] = array (
				'type'			=> 'Feature',
				'properties'	=> array (
					'name'	=> $issue['properties']['title'],
					'near'	=> streetfocus::truncate (strip_tags ($issue['properties']['description']), 80),
					'when'	=> $issue['properties']['created_at'],
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
	
	
	# Helper function to get Cyclescape issues within a BBOX
	public function getCyclescapeIssues ($bbox)
	{
		# Search Cyclescape, e.g. /api/issues?per_page=10&term=chisholm%20trail
		$url = $this->settings['cyclescapeApiBaseUrl'] . '/issues';
		$parameters = array (
			'per_page'			=> 200,
			'bbox'				=> $bbox,
			'order'				=> 'id',
			'order_direction'	=> 'desc',
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
		$issues = streetfocus::getApiData ($url, $parameters);
		
		# Simplify the output, converting geometries to Point, and removing non-needed properties
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Get the BBOX
			streetfocus::getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
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
	public function getCyclestreetsIssues ($bbox)
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
		$issues = streetfocus::getApiData ($url, $parameters);
		
		# Simplify the output, converting geometries to Point, and removing non-needed properties
		$features = array ();
		foreach ($issues['features'] as $issue) {
			
			# Convert geometry to BBOX and centrepoint
			$centroid = streetfocus::getCentre ($issue['geometry'], $bbox /* returned by reference */);
			
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
	public function getExternalIssues ($bbox = false, $id = false)
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
				$record['title'] = streetfocus::truncate ($record['description'], 40);
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
}

?>