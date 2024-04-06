<?php

# Monitors model
class monitorsModel
{
	# Class properties
	private $settings;
	private $databaseConnection;
	
	
	# Constructor
	public function __construct ($settings, $databaseConnection)
	{
		# Create properties handles
		$this->settings = $settings;
		$this->databaseConnection = $databaseConnection;
	}
	
	
	
	# Function to add a monitor
	public function add ($bbox, $type, $size, $email)
	{
		# Start an insert
		$insert = array ();
		
		# Handle BBOX
		$w = (float) $bbox['w'];
		$s = (float) $bbox['s'];
		$e = (float) $bbox['e'];
		$n = (float) $bbox['n'];
		$bbox = array (
			'type' => 'Polygon',
			'coordinates' => array (array (
				array ($w, $s),
				array ($e, $s),
				array ($e, $n),
				array ($w, $n),
				array ($w, $s)
			))
		);
		$insert['location'] = 'ST_GeomFromGeoJSON(:bbox)';
		$functionValues = array ('bbox' => json_encode ($bbox));
		
		# Add form values, filling in optional values
		$insert['type'] = $type;
		$insert['size'] = $size;
		
		# Add fixed data
		$insert['email'] = $email;
		$insert['createdAt'] = 'NOW()';
		
		# Add to the database
		$result = $this->databaseConnection->insert ($this->settings['database'], 'monitors', $insert, false, true, false, false, 'INSERT', $functionValues);
		
		# Return the result
		return $result;
	}
	
	
	# Function to get monitors for a user
	public function forUser ($email)
	{
		# Get the data
		$query = "SELECT
				id,
				ST_AsGeoJSON(location) AS location,
				type,
				size
			FROM monitors
			WHERE email = :email
		;";
		$preparedStatementValues = array ('email' => $email);
		$data = $this->databaseConnection->getData ($query, false, true, $preparedStatementValues);
		
		# Return the data
		return $data;
	}
}

?>
