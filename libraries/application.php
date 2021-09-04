<?php

/*
 * Coding copyright Martin Lucas-Smith, University of Cambridge, 2003-21
 * Version 1.6.0
 * Distributed under the terms of the GNU Public Licence - https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP 4.1+ with register_globals set to 'off'
 * Download latest from: https://download.geog.cam.ac.uk/projects/application/
 */


# Ensure the pureContent framework is loaded and clean server globals
require_once ('pureContent.php');


# Class containing general application support static methods
class application
{
	# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
	public static function assignArguments (&$errors, $suppliedArguments, $argumentDefaults, $functionName, $subargument = NULL, $handleErrors = false)
	{
		# Merge the defaults: ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		$arguments = array ();
		foreach ($argumentDefaults as $argument => $defaultValue) {
			if (is_null ($defaultValue)) {
				if (!isSet ($suppliedArguments[$argument])) {
					$errors['absent' . ucfirst ($functionName) . ucfirst ($argument)] = "No '<strong>{$argument}</strong>' has been set in the '<strong>{$functionName}</strong>' specification.";
					$arguments[$argument] = $defaultValue;
				} else {
					$arguments[$argument] = $suppliedArguments[$argument];
				}
				
			# If a subargument is supplied, deal with subarguments
			} elseif ($subargument && ($argument == $subargument)) {
				foreach ($defaultValue as $subArgument => $subDefaultValue) {
					if (is_null ($subDefaultValue)) {
						if (!isSet ($suppliedArguments[$argument][$subArgument])) {
							$errors['absent' . ucfirst ($fieldType) . ucfirst ($argument) . ucfirst ($subArgument)] = "No '<strong>$subArgument</strong>' has been set for a '<strong>$argument</strong>' argument in the $fieldType specification.";
							$arguments[$argument][$subArgument] = $fieldType;
						} else {
							$arguments[$argument][$subArgument] = $suppliedArguments[$argument][$subArgument];
						}
					} else {
						$arguments[$argument][$subArgument] = (isSet ($suppliedArguments[$argument][$subArgument]) ? $suppliedArguments[$argument][$subArgument] : $subDefaultValue);
					}
				}
				
			# Otherwise assign argument as normal
			} else {
				$arguments[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
			}
		}
		
		# Handle the errors directly if required if any arise
		if ($handleErrors) {
			if ($errors) {
				echo self::showUserErrors ($errors);
				return false;
			}
		}
		
		# Return the arguments
		return $arguments;
	}
	
	
	# Generalised support function to display errors
	public static function showUserErrors ($errors, $parentTabLevel = 0, $heading = '', $nested = false, $divClass = 'error')
	{
		# Convert the error(s) to an array if it is not already
		$errors = self::ensureArray ($errors);
		
		# Build up a list of errors if there are any
		$html = '';
		if (count ($errors) > 0) {
			if (!$nested) {$html .= "\n" . str_repeat ("\t", ($parentTabLevel)) . "<div class=\"{$divClass}\">";}
			if ($heading != '') {$html .= ((!$nested) ? "\n" . str_repeat ("\t", ($parentTabLevel + 1)) . '<p>' : '') . $heading . ((!$nested) ? '</p>' : '');}
			$html .= "\n" . str_repeat ("\t", ($parentTabLevel + 1)) . '<ul>';
			foreach ($errors as $error) {
				$html .= "\n" . str_repeat ("\t", ($parentTabLevel + 2)) . '<li>' . $error . '</li>';
			}
			$html .= "\n" . str_repeat ("\t", ($parentTabLevel + 1)) . '</ul>';
			if (!$nested) {$html .= "\n" . str_repeat ("\t", ($parentTabLevel)) . '</div>' . "\n";}
		}
		
		# Return the result
		return $html;
	}
	
	
	# Function to get the base URL (non-slash terminated)
	public static function getBaseUrl ()
	{
		# Obtain the value
		$baseUrl = dirname (substr ($_SERVER['SCRIPT_FILENAME'], strlen ($_SERVER['DOCUMENT_ROOT'])));
		
		# Convert backslashes to forwarded slashes if necessary
		$baseUrl = str_replace ('\\', '/', $baseUrl);
		
		# Deal with the special case of an application at top-level
		if ($baseUrl == '/') {$baseUrl = '';}
		
		# Return the value
		return $baseUrl;
	}
	
	
	# Function to send an HTTP header such as a 404; note that output buffering must have been switched on at server level
	public static function sendHeader ($statusCode /* or keyword 'refresh' */, $url = false, $redirectMessage = false)
	{
		# Determine whether to use a redirect message
		if ($redirectMessage) {
			if ($redirectMessage === true) {	// Convert (bool)true to a default string
				$redirectMessage = "\n" . '<p><a href="%s">Click here to continue to the next page.</a></p>';
			}
			#!# If using 'refresh' this will be invalid
			$redirectMessage = sprintf ($redirectMessage, $url);
		}
		
		# Select the appropriate header
		switch ($statusCode) {
			
			case 'refresh':
				$url = $_SERVER['_PAGE_URL'];
				// Fall through to 301
				
			case '301':
				header ('HTTP/1.1 301 Moved Permanently');
				header ("Location: {$url}");
				return $redirectMessage;
				
			case '302':
				header ("Location: {$url}");
				return $redirectMessage;
				
			case '400':
				header ('HTTP/1.0 400 Bad Request');
				break;
				
			case '401':
				header ('HTTP/1.0 401 Authorization Required');
				break;
				
			case '403':
				header ('HTTP/1.0 403 Forbidden');
				break;
				
			case '404':
				header ('HTTP/1.0 404 Not Found');
				break;
				
			case '410':
				header ('HTTP/1.0 410 Gone');
				break;
				
			case '422':
				header ('HTTP/1.0 422 Unprocessable Entity');
				break;
				
			case '500':
				header ('HTTP/1.1 500 Internal Server Error');
				break;
		}
	}
	
	
	# Function to serve cache headers (304 Not modified header) instead of a resource; based on: http://www.php.net/header#61903
	public static function preferClientCache ($path)
	{
		# The server file path must exist and be readable
		if (!is_readable ($path)) {return;}
		
		# Currently only supported on Apache
		if (!function_exists ('apache_request_headers')) {return;}
		
		# Get headers sent by the client
		$headers = apache_request_headers ();
		
		# End if no cache request header specified
		if (!isSet ($headers['If-Modified-Since'])) {return;}
		
		# Is the client's local cache current?
		if (strtotime ($headers['If-Modified-Since']) != filemtime ($path)) {return;}
		
		# Client's cache is current, so just respond '304 Not Modified'
		header ('Last-Modified: '. gmdate ('D, d M Y H:i:s', filemtime ($path)) . ' GMT', true, 304);
		
		# End all execution
		exit (0);
	}
	
	
	# Function to set a flash message
	#!# Currently also does a redirect, which is probably best separated out, or the function renamed to setFlashRedirect
	public static function setFlashMessage ($name, $value, $redirectToPath, $redirectMessage = false, $path = '/')
	{
		# Set the cookie
		setcookie ("flashredirect_{$name}", $value, time () + (60*5), $path);
		
		# Redirect to the specified location
		$html = self::sendHeader (302, $_SERVER['_SITE_URL'] . $redirectToPath, $redirectMessage);
		
		# Return the HTML which will be displayed as the fallback
		return $html;
	}
	
	
	# Function to get a flash message
	public static function getFlashMessage ($name, $path = '/')
	{
		# End if there is no such cookie
		if (!isSet ($_COOKIE["flashredirect_{$name}"])) {return false;}
		
		# Get the message
		$message = $_COOKIE["flashredirect_{$name}"];
		
		# Destroy the cookie
		setcookie ("flashredirect_{$name}", '0', 0, $path);	// '0' for the value is the only way of deleting the cookie - the docs suggest '' is OK but this doesn't actually work
		
		# Return true to signify the message should be shown
		return $message;
	}
	
	
	# Generalised support function to allow quick dumping of form data to screen, for debugging purposes
	public static function dumpData ($data, $hide = false, $return = false, $htmlspecialchars = true)
	{
		# End if debugging is supressed via a constant which is set to true
		if (defined ('SUPPRESS_DEBUG') && (SUPPRESS_DEBUG)) {return false;}
		
		# Start the HTML
		$html = '';
		
		# Show the data
		if ($hide) {$html .= "\n<!--";}
		$html .= "\n" . '<pre class="debug"><strong>DEBUG:</strong> ';
		if (is_array ($data)) {
			$data = print_r ($data, true);
		}
		if ($htmlspecialchars) {
			if (!defined ('ENT_SUBSTITUTE')) {define ('ENT_SUBSTITUTE', 8);}	// See http://hakre.wordpress.com/2011/08/31/substitutes-for-php-5-4s-htmlspecialchars/ and http://www.php.net/htmlspecialchars#106188
			$data = htmlspecialchars ($data, ENT_QUOTES | ENT_SUBSTITUTE);
		}
		$html .= $data;
		$html .= "\n</pre>";
		if ($hide) {$html .= "\n-->";}
		
		# Return or show the HTML
		if (!$return) {
			echo $html;
		} else {
			return $html;
		}
	}
	
	
	# Function to present an array with arrows (like print_r but better formatted)
	public static function printArray ($array)
	{
		# If the input is not an array, convert it
		$array = self::ensureArray ($array);
		
		# Loop through each item
		$hash = array ();
		foreach ($array as $key => $value) {
			if ($value === false) {$value = '0';}
			$hash[] = "$key => $value";
		}
		
		# Assemble the text as a single string
		$text = implode (",\n", $hash);
		
		# Return the text
		return $text;
	}
	
	
	# Function to check whether all elements of an array are empty
	public static function allArrayElementsEmpty ($array)
	{
		# Ensure the variable is an array if not already
		$array = self::ensureArray ($array);
		
		# Return false if a non-empty value is found
		foreach ($array as $key => $value) {
			#!# Consider changing to casting as string then doing a strlen
			if ($value !== '') {	// Native empty() regards 0 and '0' as empty which is stupid
				return false;
			}
		}
		
		# Return true if no values have been found
		return true;
	}
	
	
	# Generalised support function to ensure a variable is an array
	public static function ensureArray ($variable)
	{
		# If the initial value is empty, convert it to an empty array
		if ($variable == '') {$variable = array ();}
		
		# Convert the initial value(s) to an array if it is not already
		if (!is_array ($variable)) {
			$temporaryArray = $variable;
			unset ($variable);
			$variable[] = $temporaryArray;
		}
		
		# Return the array
		return $variable;
	}
	
	
	# Function to check whether an array is associative, i.e. whether any keys are not numeric
	public static function isAssociativeArray ($array)
	{
		# Return false if not an array
		if (!is_array ($array)) {return false;}
		
		# Loop through each and check each key
		$index = 0;
		foreach ($array as $key => $value) {
			
			# If the key does not exactly match the index (i.e. is not numeric type or is not in pure order of 0, 1, 2, ...), then it is associative
			if ($key !== $index) {return true;}
			$index++;
		}
		
		# Otherwise return false as all keys are integers in natural order
		return false;
	}
	
	
	# Function to determine if an array is multidimensional; returns 1 if all are multidimensional, 0 if not at all, -1 if mixed
	public static function isMultidimensionalArray ($array)
	{
		# Return NULL if not an array
		if (!is_array ($array)) {return NULL;}
		
		# Loop through the array and find cases where the elements are multidimensional or non-multidimensional
		$multidimensionalFound = false;
		$nonMultidimensionalFound = false;
		foreach ($array as $key => $value) {
			if (is_array ($value)) {
				$multidimensionalFound = true;
			} else {
				$nonMultidimensionalFound = true;
			}
		}
		
		# Return the outcome
		if ($multidimensionalFound && $nonMultidimensionalFound) {return -1;}	// Mixed array (NB: a check for if(-1) evaluates to TRUE)
		if ($multidimensionalFound) {return 1;}	// All elements multi-dimensional
		if ($nonMultidimensionalFound) {return 0;}	// Non-multidimensional
	}
	
	
	# Iterative function to ensure a hierarchy of values (for either a simple array or a one-level multidimensional array) is arranged associatively
	public static function ensureValuesArrangedAssociatively ($originalValues, $forceAssociative, $canIterateFurther = true)
	{
		# Loop through each value and determine whether the non-multidimensional elements should be treated as associative or not
		$scalars = array ();
		foreach ($originalValues as $key => $value) {
			if (!is_array ($value)) {
				$scalars[$key] = $value;
			}
		}
		$scalarsAreAssociative = ($forceAssociative || self::isAssociativeArray ($scalars));
		
		# Loop through each value
		$values = array ();
		foreach ($originalValues as $key => $value) {
			
			# If the value is an array but further iteration is disallowed, return false
			#!# This could be supported if iteratively applied and then display is supported higher up in the class hierarchy
			if (is_array ($value) && !$canIterateFurther) {return false;}
			
			# If the value is not an array, assign the index or the value to be used as the key, and add the value to the array
			if (!is_array ($value)) {
				$key = ($scalarsAreAssociative ? $key : $value);
				$values[$key] = $value;
			} else {
				
				# For an array, iterate to obtain the values, carrying back any thrown error
				if (!$value = self::ensureValuesArrangedAssociatively ($value, $forceAssociative, false)) {
					return false;
				}
			}
			
			# Add the value (or array of subvalues) to the array, in the same structure
			$values[$key] = $value;
		}
		
		# Return the values
		return $values;
	}
	
	
	# Function to flatten a one-level multidimensional array
	#!# This could be made properly iterative
	public static function flattenMultidimensionalArray ($values)
	{
		# Arrange the values as a simple associative array
		foreach ($values as $key => $value) {
			if (!is_array ($value)) {
				$flattenedValues[$key] = $value;
			} else {
				foreach ($value as $subKey => $subValue) {
					$flattenedValues[$subKey] = $subValue;
				}
			}
		}
		
		# Return the flattened version
		return $flattenedValues;
	}
	
	
	/*
	# Function to get the longest key name length in an array
	public static function longestKeyNameLength ($array)
	{
		# Assign 0 as the initial longest length
		$longestLength = 0;
		
		# Loop through each array item and reassign the longest length if it's longer
		foreach ($array as $key => $data) {
			$keyLength = strlen ($key);
			if ($keyLength > $longestLength) {
				$longestLength = $keyLength;
			}
		}
		
		# Return the value
		return $longestLength;
	}
	*/
	
	
	# Trucation algorithm; this is multibyte safe and uses mb_
	public static function str_truncate ($string, $characters, $moreUrl, $override = '<!--more-->', $respectWordBoundaries = true, $htmlMode = true)
	{
		# End false if $characters is non-numeric or zero
		if (!$characters || !is_numeric ($characters)) {return false;}
		
		# Return the string without modification if it is under the character limit
		if ($characters > mb_strlen ($string)) {return $string;}
		
		# If the override string is there, break at that point
		if ($override && substr_count ($string, $override)) {
			$newString = preg_replace ('|' . preg_quote ($override) . '(.*)$|s', '', $string);
			
		} else {
			
			# Word boundary mode
			if ($respectWordBoundaries) {
				
				# Chunk string, then reassemble, and check at each rechunking that the character limit has not been breached
				$pieces = explode (' ', $string);
				$newString = '';
				$approvedPieces = array ();
				foreach ($pieces as $piece) {
					$approvedPieces[] = $piece;
					$newString = implode (' ', $approvedPieces);
					if (mb_strlen ($newString) >= $characters) {
						break;	// Stop adding more pieces
					}
				}
				
			# Simple character mode
			} else {
				$newString = mb_substr ($string, 0, $characters);
			}
		}
		
		# Add the more link (except if the word chunking is just over the boundary resulting in the string being the same)
		if (mb_strlen ($newString) != mb_strlen ($string)) {
			if ($htmlMode) {
				$moreHtml = " <span class=\"comment\">...&nbsp;<a href=\"{$moreUrl}\">[more]</a></span>";
			} else {
				$moreHtml = '...';
			}
			$newString .= $moreHtml;
		}
		
		# Return the string
		return $newString;
	}
	
	
	# String highlighting, based on http://aidanlister.com/repos/v/function.str_highlight.php
	public static function str_highlight ($text, $needle, $forceWordBoundaryIfNo = false)
	{
		# Default highlighting
		$highlight = '<strong>\1</strong>';
		
		# Pattern
		$pattern = '#(%s)#';
		
		# Apply case insensitivity
		$pattern .= 'i';
		
		# Escape characters
		$needle = preg_quote ($needle);
		
		# Escape needle with whole word check
		if (!$forceWordBoundaryIfNo || ($forceWordBoundaryIfNo && !substr_count ($needle, $forceWordBoundaryIfNo))) {
			$needle = '\b' . $needle . '\b';
		}
		
		# Perform replacement
		$regex = sprintf ($pattern, $needle);
		$text = preg_replace ($regex, $highlight, $text);
		
		# Return the text
		return $text;
	}
	
	
	# Function to return the start,previous,next,end items in an array
	public static function getPositions ($keys, $item)
	{
		# Reindex the keys
		$new = array ();
		$i = 0;
		foreach ($keys as $key => $value) {
			$new[$i] = $value;
			$i++;
		}
		$keys = $new;
		
		# Ensure that the value exists in the array
		if (!in_array ($item, $keys)) {
			return NULL;
		}
		
		# Get the index position of the current value
		foreach ($keys as $key => $value) {
			if ($value == $item) {
				$index['current'] = (int) $key;
				break;
			}
		}
		
		# Assign the index positions of the other types
		$index['previous'] = (array_key_exists (($index['current'] - 1), $keys) ? ($index['current'] - 1) : NULL);
		$index['next'] = (array_key_exists (($index['current'] + 1), $keys) ? ($index['current'] + 1) : NULL);
		$index['start'] = 0;
		$index['end'] =  count ($keys) - 1;
		
		# Change the index with the actual value
		$result = array ();
		foreach ($index as $type => $position) {
			$result[$type] = ($position !== NULL ? $keys[$position] : NULL);
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to ksort an array recursively
	public static function ksortRecursive (&$array)
	{
		ksort ($array);
		$keys = array_keys ($array);
		foreach ($keys as $key) {
			if (is_array ($array[$key])) {
				self::ksortRecursive ($array[$key]);
			}
		}
	}
	
	
	# Function to natsort an array by key; note that this does not return by reference (unlike natsort)
	public static function knatsort ($array)
	{
		$keys = array_keys ($array);
		natsort ($keys);
		$items = array ();
		foreach ($keys as $key) {
			$items[$key] = $array[$key];
		}
		
		# Return the sorted list
		return $items;
	}
	
	
	# Function to natsort an array by key; note that this does not return by reference (unlike natsort)
	public static function natsortField ($array, $fieldname)
	{
		# Create a function which creates an array of the two values, then compares the original array with a natsorted copy
		$functionCode = '
			$original = array ($a[\'' . $fieldname . '\'], $b[\'' . $fieldname . '\']);
			$copy = $original;
			natsort ($copy);
			return ($copy === $original ? -1 : 1);
		';
		
		# Do the comparison
		$natsortFieldFunction = create_function ('$a,$b', $functionCode);
		uasort ($array, $natsortFieldFunction);
		
		# Return the sorted list
		return $array;
	}
	
	
	# Function to perform resorting with a start list
	public static function resortStartOrder ($list, $startOrder)
	{
		# Return what is supplied if the list is not an array
		if (!is_array ($list) || empty ($list)) {return $list;}
		
		# Start an array of items
		$resortedList = array ();
		
		# Add each item in the list, ordered by the start order
		foreach ($startOrder as $item) {
			if (isSet ($list[$item])) {
				$resortedList[$item] = $list[$item];
				unset ($list[$item]);
			}
		}
		
		# Add on the remainder not in the start order list
		if (!empty ($list)) {
			$resortedList += $list;
		}
		
		# Return the newly sorted list
		return $resortedList;
	}
	
	
	# Function to get the first value in an array, whether the array is associative or not
	#!# PHP 7.3 now has array_value_first - should replace this function with a global function and migrate callers
	public static function array_first_value ($array)
	{
		return reset ($array);	// Safe to do as this function receives a copy of the array
	}
	
	
 	# Function to get the last value in an array, whether the array is associative or not
	#!# PHP 7.3 now has array_value_last - should replace this function with a global function and migrate callers
	public static function array_last_value ($array)
	{
		return end ($array);    // Safe to do as this function receives a copy of the array
	}
	
	
	# Function to trim all values in an array; recursive values are also handled
	public static function arrayTrim ($array, $trimKeys = false)
	{
		# Return the value if not an array
		if (!is_array ($array)) {return $array;}
		
		# Loop and replace
		$cleanedArray = array ();
		foreach ($array as $key => $value) {
			
			# Deal with recursive arrays
			if (is_array ($value)) {$value = self::arrayTrim ($value);}
			
			# Trim the key if requested
			if ($trimKeys) {$key = trim ($key);}
			
			# Trim value
			$cleanedArray[$key] = trim ($value);
		}
		
		# Return the new array
		return $cleanedArray;
	}
	
	
	# Function to return only the specified fields of an existing array, returning in the order of the specified fields
	public static function arrayFields ($array, $fields)
	{
		# Return unamended if not an array
		if (!is_array ($array)) {return $array;}
		
		# Ensure the fields are an array
		$fields = self::ensureArray ($fields);
		
		# Loop through the array and extract only the wanted fields, returning in the order of the specified fields
		$filteredArray = array ();
		foreach ($fields as $field) {
			if (array_key_exists ($field, $array)) {
				$filteredArray[$field] = $array[$field];
			}
		}
		
		# Return the filtered array
		return $filteredArray;
	}
	
	
	# Function to filter an array by a list of keys
	public static function array_filter_keys ($array, $keys)
	{
		# Return unamended if not an array
		if (!is_array ($array)) {return $array;}
		
		# Ensure the keys list is an array
		$keys = self::ensureArray ($keys);
		
		# Add those values of the $array whose key is in $keys
		$result = array ();
		foreach ($array as $key => $value) {
			if (in_array ($key, $keys)) {
				$result[$key] = $value;
			}
		}
		
		# Return the resulting array
		return $result;
	}
	
	
	# Function to return the duplicate values in an array
	public static function array_duplicate_values ($array)
	{
		$checkKeysUniqueComparison = create_function ('$value', 'if ($value > 1) return true;');
		$result = array_keys (array_filter (array_count_values ($array), $checkKeysUniqueComparison));
		return $result;
	}
	
	
	# Function to return an associative array of all values in an array that have duplicates; based on: http://stackoverflow.com/a/6461117
	public static function array_duplicate_values_all_keyed ($array)
	{
		# Get the unique values, preserving keys; this effectively eliminates later items whose value was present earlier in the array
		$unique = array_unique ($array);
		
		# Get the duplicate values; this effectively gives the later items whose value was present earlier in the array
		$duplicates = array_diff_assoc ($array, $unique);
		
		# Filter out any value which is in the duplicates list; this fully clears the array of any values that exist more than once
		$duplicates = array_intersect ($array, $duplicates);
		
		# Return the array of the items that have duplicates, with both (or more) present
		return $duplicates;
	}
	
	
	# Function to get the name of the nth key in an array (first is 1, not 0)
	public static function arrayKeyName ($array, $number = 1, $multidimensional = false)
	{
		# Convert to multidimensional if not already
		if (!$multidimensional) {
			$dataset[] = $array;
		}
		
		# Loop through the multidimensional array
		foreach ($array as $index => $data) {
			
			# Return false if not an array
			if (!is_array ($data)) {return $array;}
			
			# Ensure the number is not greater than the number of keys
			$totalFields = count ($data);
			if ($number > $totalFields) {return false;}
			
			# Loop through the data and construct
			$i = 0;
			foreach ($data as $key => $value) {
				$i++;
				if ($i == $number) {
					return $key;
				}
			}
		}
	}
	
	
	# Function to create an array of all combinations in a set of associative arrays, acting on their keys (not their value labels); adapted from https://gist.github.com/cecilemuller/4688876
	public static function array_key_combinations ($arrays, $keyConcatCharacter = '_', $valueConcatCharacter = ' - ')
	{
		# End if none
		if (!$arrays) {return array ();}
		
		# Create combinations
		$result = array (array ());
		foreach ($arrays as $property => $property_values) {
			$tmp = array ();
			foreach ($result as $result_item) {
				foreach ($property_values as $key => $value) {
					$result_item[$property] = $key;
					$tmp[] = $result_item;
				}
			}
			$result = $tmp;
		}
		
		# Reindex with a concatenation character
		$resultKeyed = array ();
		foreach ($result as $index => $fields) {
			$key = implode ($keyConcatCharacter, $fields);
			$resultKeyed[$key] = $fields;
		}
		
		# Compile the labels
		foreach ($resultKeyed as $key => $fields) {
			foreach ($fields as $field => $keyValue) {
				$fields[$field] = $arrays[$field][$keyValue];	// Substitute in the label
			}
			$resultKeyed[$key] = implode ($valueConcatCharacter, $fields);
		}
		
		# Return the result
		return $resultKeyed;
	}
	
	
	# Function to decode HTML entity values recursively through an associative array of any structure; NB this only changes values, not keys
	public static function array_html_entity_decode ($array)
	{
		# Loop through the array, and recurse where a value is associative, otherwise decode
		foreach ($array as $key => $value) {
			if (!is_array ($value)) {
				$array[$key] = html_entity_decode ($value);
			} else {
				$array[$key] = self::array_html_entity_decode ($value);
			}
		}
		
		# Return the modified array
		return $array;
	}
	
	
	# Function to convert booleans to ticks in a data table
	public static function booleansToTicks ($data, $fields)
	{
		# Determine the boolean fields
		$booleanFields = array ();
		foreach ($fields as $field => $attributes) {
			if (($attributes['Type'] == 'int(1)') || ($attributes['Type'] == 'tinyint')) {	// TINYINT for MySQL >=8
				$booleanFields[] = $field;
			}
		}
		
		# Convert 1 to tick
		if ($booleanFields) {
			foreach ($data as $index => $record) {
				foreach ($record as $field => $value) {
					if (in_array ($field, $booleanFields)) {
						if ($value == '1') {
							$data[$index][$field] = "\u{2714}";	// tick
						}
					}
				}
			}
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to clear empty rows
	public static function clearEmptyRows ($data)
	{
		# Work through each row
		foreach ($data as $id => $row) {
			
			# Determine if any column is empty
			$allEmpty = true;
			foreach ($row as $key => $value) {
				if (strlen ($value)) {
					$allEmpty = false;
					break;	// Stop if value found
				}
			}
			
			# If all are empty, unset the row
			if ($allEmpty) {
				unset ($data[$id]);
			}
		}
		
		# Return the data
		return $data;
	}
	
	
	# Function to construct a string (from a simple array) as 'A, B, and C' rather than 'A, B, C'
	public static function commaAndListing ($list, $stripStartingWords = array ())
	{
		# If a starting word list is defined, strip these words from each entry
		if ($stripStartingWords) {
			foreach ($list as $index => $entry) {
				foreach ($stripStartingWords as $stripStartingWord) {
					$list[$index] = preg_replace ('/^' . preg_quote ($stripStartingWord . ' ', '/') . '/', '', $entry);
				}
			}
		}
		
		# If there is more than one item, extract the last item
		$totalItems = count ($list);
		$moreThanOneItem = ($totalItems > 1);
		if ($moreThanOneItem) {
			$lastItem = array_pop ($list);
		}
		
		# Implode the remaining item(s) in the list
		$string = implode (', ', $list);
		
		# Add on the last item if it exists
		if ($moreThanOneItem) {
			$string .= ' and ' . $lastItem;
		}
		
		# Return the string
		return $string;
	}
	
	
	# Function to return a correctly supplied URL value
	public static function urlSuppliedValue ($urlArgumentKey, $available)
	{
		# If the $urlArgumentKey is defined in the URL and it exists in the list of available items, return its value
		if (isSet ($_GET[$urlArgumentKey])) {
			if (in_array ($_GET[$urlArgumentKey], $available)) {
				return $_GET[$urlArgumentKey];
			}
		}
		
		# Otherwise return an empty string
		return '';
	}
	
	
	# Function to clean up text
	public static function cleanText ($record, $entityConversion = true)
	{
		# Define conversions
		$convertFrom = "\x82\x83\x84\x85\x86\x87\x89\x8a\x8b\x8c\x8e\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9e\x9f";
		$convertTo = "'f\".**^\xa6<\xbc\xb4''\"\"---~ \xa8>\xbd\xb8\xbe";
		
		# If not an array, clean the item
		if (!is_array ($record)) {
			$record = strtr ($record, $convertFrom, $convertTo);
			if ($entityConversion) {$record = htmlspecialchars ($record);}
		} else {
			
			# If an array, clean each item
			foreach ($record as $name => $details) {
				$record[$name] = strtr ($details, $convertFrom, $convertTo);
				if ($entityConversion) {$record[$name] = htmlspecialchars ($record[$name]);}
			}
		}
		
		# Return the record
		return $record;
	}
	
	
	# Function to convert the character set, mainly intended for ISO-8859-1 to UTF-8 conversions, with string/array/multi-dimensional-array (including array key conversion) support
	public static function convertToCharset ($variable, $outputCharset = 'UTF-8', $convertKeys = true)
	{
		# If the value is a scalar, convert directly and return
		if (!is_array ($variable)) {
			return self::convertToCharset_scalar ($variable, $outputCharset);
		}
		
		# Loop through the array and convert both key and value to entity-safe characters
		$cleanedArray = array ();
		foreach ($variable as $key => $value) {
			if ($convertKeys) {$key = self::convertToCharset ($key, $outputCharset);}
			$value = self::convertToCharset ($value);
			$cleanedArray[$key] = $value;
		}
		
		# Return the cleaned array
		return $cleanedArray;
	}
	
	
	
	# Function wrapped by convertToCharset
	public static function convertToCharset_scalar ($string, $outputCharset = 'UTF-8', $iconvIgnore = true)
	{
		# End if iconv support is not available
		if (!function_exists ('iconv')) {return $string;}
		
		# If a specific charset is mentioned in a meta tag, extract this as the input charset
		if (preg_match ('|<meta [^>]+content="[^"]+charset=([^"]+)" />|', $string, $matches)) {
			$inputCharset = $matches[1];
		} else {
			
			# Detect the input encoding, using mb_ extension by preference if it is available
			if (function_exists ('mb_detect_encoding')) {
				$inputCharset = mb_detect_encoding ($string, 'UTF-8, ISO-8859-1, ISO-8859-15');	// Note UTF-8 must precede others
			} else {
				
				# If the mb_ extension is not available, check for UTF-8 and assume ISO-8859-1 otherwise; see http://www.w3.org/International/questions/qa-forms-utf-8.en.php
				if (strlen ($string) != 0) {
					$isUtf8 = true;
				} else {
					$isUtf8 = (preg_match ('/^.{1}/us', $string) == 1);	// See 'function utf8_compliant' on http://www.phpwact.org/php/i18n/charsets
				}
				$inputCharset = ($isUtf8 ? 'UTF-8' : 'ISO-8859-1');
			}
		}
		
		# Perform no conversion if the input and output character sets match
		if ($inputCharset == $outputCharset) {return $string;}
		
		# Convert the string; not sure why this works but see www.php.net/function.iconv#59030
		if ($iconvIgnore) {$outputCharset .= '//IGNORE';}
		if (!$string = iconv ($inputCharset, $outputCharset, $string)) {
			error_log ('PHP Iconv failed: ' . $outputCharset . ' on URL: ' . $_SERVER['_PAGE_URL'] . ' for string: ' . $string);
		}
		
		# Return the string
		return $string;
	}
	
	
	# Function to format free text
	public static function formatTextBlock ($text, $paragraphClass = NULL)
	{
		# Do nothing if the text is empty
		if (!strlen ($text)) {return $text;}
		
		# Remove any windows line breaks
		$text = str_replace ("\r\n", "\n", $text);
		
		# Perform the conversion
		$text = trim ($text);
		
		$text = str_replace ("\n\n", '</p><p' . ($paragraphClass ? " class=\"{$paragraphClass}\"" : '' ) .'>', $text);
		$text = str_replace ("\n", '<br />', $text);
		$text = str_replace (array ('</p>', '<br />'), array ("</p>\n", "<br />\n"), $text);
		$text = '<p' . ($paragraphClass ? " class=\"{$paragraphClass}\"" : '' ) .">$text</p>";
		
		# Return the text
		return $text;
	}
	
	
	# Generic function to convert a box with URL[whitespace]description lines to a list
	public static function urlReferencesBox ($string)
	{
		# Loop through each line
		$lines = explode ("\n", $string);
		foreach ($lines as $index => $line) {
			
			# Default to the line as-is
			$list[$index] = $line;
			
			# Explode by the first space (after the first URL) if it exists
			$parts = preg_split ("/[\s]+/", $line, 2);
			if (count ($parts) == 2) {
				$list[$index] = "<a href=\"{$parts[0]}\" target=\"_blank\">{$parts[1]}</a>";
			}
		}
		
		# Compile the list
		$html  = self::htmlUl ($list);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to rawurlencode a path but leave the / slash characters in tact
	public static function rawurlencodePath ($path)
	{
		# Do the encoding
		$encoded = implode ('/', array_map ('rawurlencode', explode ('/', $path)));
		
		# Return the encoded path
		return $encoded;
	}
	
	
	# Function to format a minimised URL (e.g. www.site.com/subdirectory rather than http://www.site.com/subdirectory/)
	public static function urlPresentational ($url)
	{
		# Trim whitespace
		$url = trim ($url);
		
		# Remove trailing slash if there is only one subdirectory (or none)
		if (substr_count ($url, '/') <= 4) {
			if (substr ($url, -1) == '/') {$url = substr ($url, 0, -1);}
		}
		
		# Remove http:// from the start if followed by www
		if (substr ($url, 0, 10) == 'http://www') {$url = substr ($url, 7);}
		
		# Replace %20 with a space
		$url = str_replace ('%20', ' ', $url);
		
		# Return the result
		return $url;
	}
	
	
	# Function to send administrative alerts
	public static function sendAdministrativeAlert ($administratorEmail, $applicationName, $subject, $message, $cc = false)
	{
		# Define standard e-mail headers
		$mailheaders = "From: {$applicationName} <" . $administratorEmail . ">\n";
		if ($cc) {$mailheaders .= "Cc: {$cc}\n";}
		
		# Send the message
		self::utf8Mail ($administratorEmail, $subject, wordwrap ($message), $mailheaders);
	}
	
	
	# Function to e-mail changes between two arrays
	public static function mailChanges ($administratorEmail, $changedBy, $before, $after, $databaseReference, $emailSubject, $applicationName = false, $replyTo = false, $extraText = false)
	{
		# End if no changes
		if (!$changedFields = self::array_changed_values_fields ($before, $after)) {return;}
		
		# Report changes to the administrator for info
		$beforeChanged = self::arrayFields ($before, $changedFields);
		$afterChanged = self::arrayFields ($after, $changedFields);
		
		# Construct the e-mail message
		$message  = "\nUser {$changedBy} has amended {$databaseReference}\nwith the following fields being changed:";
		$message .= "\n\n\nBefore:";
		$message .= "\n\n" . print_r ($beforeChanged, true);
		$message .= "\n\n\nAfter:";
		$message .= "\n\n" . print_r ($afterChanged, true);
		
		# Add extra text if required
		if ($extraText) {
			$message .= "\n\n" . $extraText;
		}
		
		# Send the e-mail
		$mailheaders  = 'From: ' . ($applicationName ? $applicationName : __CLASS__) . ' <' . $administratorEmail . '>';
		if ($replyTo) {$mailheaders .= "\n" . 'Reply-To: ' . $replyTo;}
		self::utf8Mail ($administratorEmail, $emailSubject, wordwrap ($message), $mailheaders);
	}
	
	
	# Wrapper for mail to make it UTF8 Unicode - see http://www.php.net/mail#92976 ; note that the From/To/Subject headers are only encoded to UTF-8 when they include non-ASCII characters (so that filtering is more likely to work)
	public static function utf8Mail ($to, $subject, $message, $extraHeaders = false, $additionalParameters = NULL, $includeMimeContentTypeHeaders = true /* Set to true, the type, or false */)
	{
		# If the message is text+html, supplied as array('text'=>$text,'html'=>$htmlVersion), set this up; see http://krijnhoetmer.nl/stuff/php/html-plain-text-mail/
		$isMultipart = false;
		if (is_array ($message) && (count ($message) == 2) && isSet ($message['text']) && isSet ($message['html'])) {
			$isMultipart = true;
			$boundary = uniqid ('np');
			$includeMimeContentTypeHeaders = 'multipart/alternative;boundary=' . $boundary;
			
			# Compile the message
			$multipartMessage  = 'This is a MIME encoded message.';
			$multipartMessage .= "\r\n\r\n--" . $boundary . "\r\n";
			$multipartMessage .= "Content-type: text/plain;charset=utf-8\r\n\r\n";
			$multipartMessage .= $message['text'];
			$multipartMessage .= "\r\n\r\n--" . $boundary . "\r\n";
			$multipartMessage .= "Content-type: text/html;charset=utf-8\r\n\r\n";
			$multipartMessage .= $message['html'];
			$multipartMessage .= "\r\n\r\n--" . $boundary . '--';
			$message = $multipartMessage;
		}
		
		# Add headers
		$headers  = '';
		if ($includeMimeContentTypeHeaders) {
			if ($includeMimeContentTypeHeaders === true) {
				$includeMimeContentTypeHeaders = 'text/plain';
			}
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= 'Content-type: ' . $includeMimeContentTypeHeaders . ($isMultipart ? '' : '; charset=UTF-8') . "\r\n";
		}
		if ($extraHeaders) {
			$headers .= $extraHeaders;
		}
		
		# Convert the subject
		if (!preg_match ('/^([[:alnum:]|[:punct:]|[:blank:]]+)$/', $subject)) {
			$subject = '=?UTF-8?B?' . base64_encode ($subject) . '?=';
		}
		
		# Convert the To field, if supplied as "Name <email>"; /u is not used as this might reject strings, and the splitting seems to work safely without it
		if (preg_match ('/(.+) <(.+)>/', $to, $matches)) {
			if (!preg_match ('/^([[:alnum:]|[:punct:]|[:blank:]]+)$/', $matches[1])) {
				$to = '=?UTF-8?B?' . base64_encode ($matches[1]) . '?=' . ' ' . "<{$matches[2]}>";
			}
		}
		
		# Convert a From: header, if it exists, when supplied as "Name <email>"
		if (!preg_match ('/^From: ([[:alnum:]|[:punct:]|[:blank:]]+)(\r?)$/m', $headers)) {
			$callbackFunction = create_function ('$matches', 'return "From: =?UTF-8?B?" . base64_encode ($matches[1]) . "?=" . " <{$matches[2]}>{$matches[3]}";');
			$headers = preg_replace_callback ('/^From: (.+) <([^>]+)>(\r?)$/m', $callbackFunction, $headers);
		}
		
		# If using SMTP auth, use the PEAR::Mail module
		$useSmtpAuth = (isSet ($_SERVER['SMTP_HOST']) && isSet ($_SERVER['SMTP_PORT']) && isSet ($_SERVER['SMTP_USERNAME']) && isSet ($_SERVER['SMTP_PASSWORD']));
		if ($useSmtpAuth) {
			
			# Define the SMTP Auth credentials
			$smtpAuthCredentials = array (
				'auth' => true,
				'host' => $_SERVER['SMTP_HOST'],
				'port' => (int) $_SERVER['SMTP_PORT'],
				'username' => $_SERVER['SMTP_USERNAME'],
				'password' => $_SERVER['SMTP_PASSWORD'],
			);
			
			# Assemble the headers
			$headersArray = array (
				'To' => $to,
				'Subject' => $subject,
			);
			$extraHeaders = explode ("\n", trim ($headers));
			foreach ($extraHeaders as $header) {
				list ($key, $value) = explode (':', trim ($header), 2);
				$key = trim ($key);
				$value = trim ($value);
				$headersArray[$key] = $value;
			}
			
			# Create the mail object using the Mail::factory method 
			require_once ('Mail.php');
			$mailObject = Mail::factory ('smtp', $smtpAuthCredentials);
			
			# Send the mail
			$mailObject->send ($to, $headersArray, $message);
			$isError = (bool) PEAR::isError ($mailObject);
			
			# Return the outcome
			return (!$isError);
			
		# Otherwise use the native PHP method
		} else {
			
			# Send the mail and return the outcome
			return mail ($to, $subject, $message, $headers, $additionalParameters);
		}
	}
	
	
	# Function to show mail that has been sent
	public static function showMail ($to, $subject, $message, $extraHeaders, $prefix = 'The message has been sent, as follows:', $divClass = 'graybox')
	{
		# Compile the text, adding headers to the start
		$text = "{$extraHeaders}\nTo: {$to}\nSubject: {$subject}\n" . $message;
		
		# Compile the HTML
		$html  = "\n<p><strong>{$prefix}</strong></p>";
		$html .= "\n<div class=\"{$divClass}\">";
		$html .= "\n<pre>" . self::makeClickableLinks (htmlspecialchars ($text), false, false, $target = false) . '</pre>';
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get a list of fields that have changed values (this is not the same as array_diff - see comments on www.php.net/array_diff page)
	public static function array_changed_values_fields ($array1, $array2)
	{
		# Start an array of changed field names whose value has changed
		$changedFields = array ();
		
		# Find the differences from array1 to array2, avoiding offsets caused by missing fields
		foreach ($array1 as $key => $value) {
			if (!array_key_exists ($key, $array2)) {continue;}
			if ($array1[$key] != $array2[$key]) {
				$changedFields[$key] = $key;
			}
		}
		
		# Find the differences from array2 to array1, avoiding offsets caused by missing fields
		foreach ($array2 as $key => $value) {
			if (!array_key_exists ($key, $array1)) {continue;}
			if ($array2[$key] != $array1[$key]) {
				$changedFields[$key] = $key;
			}
		}
		
		# Return the changed field names
		return $changedFields;
	}
	
	
	# Function to get the common domain between two domain names; e.g. "www.example.com" and "foo.example.com" would return "example.com"
	public static function commonDomain ($domain1, $domain2)
	{
		# Tokenise by .
		$domain1 = explode ('.', $domain1);
		$domain2 = explode ('.', $domain2);
		
		# Reverse order
		$domain1 = array_reverse ($domain1);
		$domain2 = array_reverse ($domain2);
		
		# Traverse through the two lists
		$i = 0;
		$commonDomainList = array ();	// Empty by default
		while (true) {
			
			# Compile the domain to this point as a string
			$commonDomain = implode ('.', array_reverse ($commonDomainList));
			
			# If either are not present, end at this point
			if (!isSet ($domain1[$i]) || !isSet ($domain2[$i])) {
				return $commonDomain;
			}
			
			# If they do not match, end at this point
			if ($domain1[$i] != $domain2[$i]) {
				return $commonDomain;
			}
			
			# Iterate to next, registering the path so far
			$commonDomainList[] = $domain1[$i];
			$i++;
		}
	}
	
	
	# Function to check that an e-mail address (or all addresses) are valid
	#!# Consider a more advanced solution like www.linuxjournal.com/article/9585 which is more RFC-compliant
	public static function validEmail ($email, $domainPartOnly = false)
	{
		# Define the regexp; regexp taken from www.zend.com/zend/spotlight/ev12apr.php but with ' added to local part
		# TLD lengths: https://jasontucker.blog/8945/what-is-the-longest-tld-you-can-get-for-a-domain-name
		$regexp = '^' . ($domainPartOnly ? '[@]?' : '[\'-_a-z0-9\$\+]+(\.[\'-_a-z0-9\$\+]+)*@') . '[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,24})$';
		
		# If not an array, perform the check and return the result
		if (!is_array ($email)) {
			return preg_match ('/' . $regexp . '/i', $email);
		}
		
		# If an array, check each and return the flag
		$allValidEmail = true;
		foreach ($email as $value) {
			if (!preg_match ('/' . $regexp . '/i', $value)) {
				$allValidEmail = false;
				break;
			}
		}
		return $allValidEmail;
	}
	
	
	# Function to check that a list of e-mail addresses (comma-space only by default, in line with HTML5 <input type="email" multiple="multiple" /> but space/semi-colon/comma-separated can be enabled)
	public static function emailListStringToArray ($string, $allowSpace = true)
	{
		# Determine the match
		$match = ($allowSpace ? ",\s?" : ',');
		
		# Split
		#!# NB This will catch addresses that contain a comma or semi-colon, but those should be very rare even if they are allowed in the RFC!
		$addresses = preg_split ("/{$match}/", trim ($string), NULL, PREG_SPLIT_NO_EMPTY);
		
		# Return the array
		return $addresses;
	}
	
	
	# Function to provide a mail quoting algorithm
	public static function emailQuoting ($message, $quoteString = '> ')
	{
		# Start an array of lines to hold the quoted message
		$quotedMessage = array ();
		
		# Wordwrap the message
		$message = wordwrap ($message, (75 - strlen ($quoteString) - 1));
		
		# Explode the message and add quote marks
		$lines = explode ("\n", $message);
		foreach ($lines as $line) {
			$quotedMessage[] = $quoteString . $line;
		}
		
		# Reassemble the message
		$quotedMessage = implode ("\n", $quotedMessage);
		
		# Return the quoted message
		return $quotedMessage;
	}
	
	
	# Function to encode an e-mail address
	public static function encodeEmailAddress ($email)
	{
		# Return the string
		return str_replace ('@', '<span>&#64;</span>', $email);
	}
	
	
	# Function to make links clickable: from www.totallyphp.co.uk/code/convert_links_into_clickable_hyperlinks.htm
	#!# Need to disallow characters such as ;.) at end of links
	#!# Target could potentially be derived from a regexp instead
	public static function makeClickableLinks ($text, $addMailto = false, $replaceVisibleUrlWithText = false, $target = '_blank')
	{
		$delimiter = '!';
		$text = preg_replace ($delimiter . '(((ftp|http|https)://)[-a-zA-Z0-9@:%_\+.,~#?&//=;]+[-a-zA-Z0-9@:%_\+~#?&//=]+)' . "{$delimiter}i", '<a' . ($target ? " target=\"{$target}\"" : '') . ' href="$1">' . ($replaceVisibleUrlWithText ? $replaceVisibleUrlWithText : '$1') . '</a>', $text);
		$text = preg_replace ($delimiter . '([\s()[{}])(www.[-a-zA-Z0-9@:%_\+.,~#?&//=;]+[-a-zA-Z0-9@:%_\+~#?&//=]+)' . "{$delimiter}i", '$1<a' . ($target ? " target=\"{$target}\"" : '') . ' href="http://$2">' . ($replaceVisibleUrlWithText ? $replaceVisibleUrlWithText : '$2') . '</a>', $text);
		if ($addMailto) {$text = preg_replace ($delimiter . '([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,3})' . "{$delimiter}i", '<a href="mailto:$1">$1</a>', $text);}
		return $text;
	}
	
	
	# Function to generate a password
	public static function generatePassword ($length = 6 /* For generating tokens, 24 is recommended instead */, $numeric = false)
	{
		# Generate a numeric password if that is what is required
		if ($numeric) {
			
			# Start a string and build up the password
			$password = '';
			for ($i = 0; $i < $length; $i++) {
				$password .= rand (0, 9);
			}
			return $password;
			
		# Otherwise do an alphanumeric password; code from http://www.php.net/openssl-random-pseudo-bytes#96812
		} else {
			
			# Prefer OpenSSL implementation
			if (function_exists ('openssl_random_pseudo_bytes')) {
				$password = bin2hex (openssl_random_pseudo_bytes ($length, $strong));	// bin2hex used so that characters guaranteed to be 0-9a-f, easily usable in a URL
				if ($strong == TRUE) {
					return substr ($password, 0, $length);
				}
			}
			
			# Fallback to mt_rand if PHP <5.3 or no OpenSSL available
			#!# This block can be removed now that PHP 5.3+ is widespread; also the characters list is inconsistent with bin2hex anyway and should be [a-f]
			$characters = '0123456789';
			$characters .= 'abcdef'; 
			$charactersLength = strlen ($characters) - 1;
			$password = '';
			for ($i = 0; $i < $length; $i++) {
				$password .= $characters[mt_rand (0, $charactersLength)];
			}
			return $password;
		}
	}
	
	
	# Function to check that a URL-supplied activation key is valid
	public static function validPassword ($length = 6)
	{
		# Check that the URL contains an activation key string of exactly 6 lowercase letters/numbers
		return (preg_match ('/^[a-z0-9]{' . $length . '}$/D', (trim ($_SERVER['QUERY_STRING']))));
	}
	
	
	# Function to perform a very basic check whether a URL is valid
	#!# Consider developing this further
	public static function urlSyntacticallyValid ($url)
	{
		# Return true if the URL is valid following basic checks
		return preg_match ('/^(ftp|http|https)/i', $url);
	}
	
	
	# Function to determine whether a URL is internal to the site
	public static function urlIsInternal ($url)
	{
		# Return true if the full URL starts with the site URL
		return preg_match ('/^' . addcslashes ($_SERVER['_SITE_URL'], '/') . '/i', $url);
	}
	
	
	# Function to extract the title of the page in question by opening the first $startingCharacters characters of the file and extracting what's between the <$tag> tags
	#!# $startingCharacters is ignored
	public static function getTitleFromFileContents ($html, $startingCharacters = 100, $tag = 'h1')
	{
		# Define the starting and closing tags
		$startingTag = "<{$tag}[^>]*>";
		$closingTag = "</{$tag}>";
		
		# Do a case-insensitive match or end, using a negative lookahead regexp
		if (!$result = preg_match ("~{$startingTag}(?!{$closingTag})(.+){$closingTag}~i", $html, $temporary)) {return '';}
		
		# Trim
		$title = trim ($temporary[1]);
		
		# Strip tags
		$title = strip_tags ($title);
		
		# Un-decode entities
		$title = htmlspecialchars_decode ($title);
		
		# Send the title back as the result
		return $title;
	}
	
	
	# Function to add a class to tags within some HTML, combining with existing classes if present
	public static function addClassesToTags ($html, $tag, $class)
	{
		# Add a class to each
		$html = str_replace ("<{$tag}", "<{$tag} class=\"{$class}\"", $html);
		
		# Get each opening tag of the specified type, so that it can be modified
		$delimiter = '@';
		if ($result = preg_match_all ($delimiter . addcslashes ('<' . $tag . '([^>]+)class="' . $class . '"([^>]+)class="([^"]+)"([^>]+)>', $delimiter) . $delimiter, $html, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$original = $match[0];
				$replacement = str_replace ('  ', ' ', "<{$tag}{$match[1]}class=\"{$class} {$match[3]}\"{$match[2]}{$match[4]}>");
				$replacements[$original] = $replacement;
			}
			
			# Perform the substitutions
			$html = str_replace (array_keys ($replacements), array_values ($replacements), $html);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to dump data from an associative array to a table
	public static function htmlTable ($array, $tableHeadingSubstitutions = array (), $class = 'lines', $keyAsFirstColumn = true, $uppercaseHeadings = false, $allowHtml = false /* true/false/array(field1,field2,..) */, $showColons = false, $addCellClasses = false, $addRowKeyClasses = false, $onlyFields = array (), $compress = false, $showHeadings = true, $encodeEmailAddress = true)
	{
		# Check that the data is an array
		if (!is_array ($array)) {return $html = "\n" . '<p class="warning">Error: the supplied data was not an array.</p>';}
		
		# Return nothing if no data
		if (empty ($array)) {return '';}
		
		# Assemble the data cells
		$dataHtml = '';
		foreach ($array as $key => $value) {
			if (!$value || !is_array ($value)) {return $html = "\n" . '<p class="warning">Error: the supplied data was not a multi-dimensional array.</p>';}
			$headings = $value;
			$dataHtml .= "\n\t" . '<tr' . ($addRowKeyClasses ? ' class="' . htmlspecialchars ($key) . '"' : '') . '>';
			if ($keyAsFirstColumn) {
				$thisCellClass = ($addCellClasses ? htmlspecialchars ($key) . ((is_array ($addCellClasses) && isSet ($addCellClasses[$key])) ? ' ' . $addCellClasses[$key] : '') : '') . ($keyAsFirstColumn ? ($addCellClasses ? ' ' : '') . 'key' : '');
				$dataHtml .= ($compress ? '' : "\n\t\t") . (strlen ($thisCellClass) ? "<td class=\"{$thisCellClass}\">" : '<td>') . "<strong>{$key}</strong></td>";
			}
			$i = 0;
			foreach ($value as $valueKey => $valueData) {
				if ($onlyFields && !in_array ($valueKey, $onlyFields)) {continue;}	// Skip if not in the list of onlyFields if that is supplied
				$i++;
				$data = $array[$key][$valueKey];
				$thisCellClass = ($addCellClasses ? htmlspecialchars ($valueKey) . ((is_array ($addCellClasses) && isSet ($addCellClasses[$valueKey])) ? ' ' . $addCellClasses[$valueKey] : '') : '') . ((($i == 1) && !$keyAsFirstColumn) ? ($addCellClasses ? ' ' : '') . 'key' : '');
				$htmlAllowed = (is_array ($allowHtml) ? (in_array ($valueKey, $allowHtml)) : $allowHtml);	// Either true/false or an array of permitted fields where HTML is allowed
				$cellContents = ($htmlAllowed ? $data : htmlspecialchars ($data));
				$dataHtml .= ($compress ? '' : "\n\t\t") . (strlen ($thisCellClass) ? "<td class=\"{$thisCellClass}\">" : '<td>') . ($encodeEmailAddress ? self::encodeEmailAddress ($cellContents) : $cellContents) . (($showColons && ($i == 1) && $data) ? ':' : '') . '</td>';
			}
			$dataHtml .= ($compress ? '' : "\n\t") . '</tr>';
		}
		
		# Construct the heading HTML
		$headingHtml  = '';
		if ($tableHeadingSubstitutions !== false) {
			$headingHtml .= "\n\t" . '<tr>';
			if ($keyAsFirstColumn) {$headingHtml .= "\n\t\t" . '<th></th>';}
			$columns = array_keys ($headings);
			foreach ($columns as $column) {
				if ($onlyFields && !in_array ($column, $onlyFields)) {continue;}	// Skip if not in the list of onlyFields if that is supplied
				$columnTitle = (empty ($tableHeadingSubstitutions) ? $column : (isSet ($tableHeadingSubstitutions[$column]) ? $tableHeadingSubstitutions[$column] : $column));
				$headingHtml .= "\n\t\t" . ($addCellClasses ? '<th class="' . $column . ((is_array ($addCellClasses) && isSet ($addCellClasses[$column])) ? ' ' . $addCellClasses[$column] : '') . '">' : '<th>') . ($uppercaseHeadings ? ucfirst ($columnTitle) : $columnTitle) . '</th>';
			}
			$headingHtml .= "\n\t" . '</tr>';
		}
		
		# Construct the overall heading
		$html  = "\n\n" . "<table class=\"{$class}\">";
		if ($showHeadings) {$html .= $headingHtml;}
		$html .= $dataHtml;
		$html .= "\n" . '</table>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a keyed HTML table; $dlFormat is PRIVATE and should not be used externally
	public static function htmlTableKeyed ($array, $keySubstitutions = array (), $omitEmpty = true, $class = 'lines', $allowHtml = false, $showColons = true, $addRowKeyClasses = false, $dlFormat = false)
	{
		# Check that the data is an array
		if (!is_array ($array)) {return $html = "\n" . '<p class="warning">Error: the supplied data was not an array.</p>';}
		
		# Ensure key substitution is an array
		if (!$keySubstitutions) {$keySubstitutions = array ();}
		
		# Perform conversions
		foreach ($array as $key => $value) {
			
			# Skip keys in the array
			if ($keySubstitutions && is_array ($keySubstitutions) && array_key_exists ($key, $keySubstitutions) && $keySubstitutions[$key] === NULL) {
				unset ($array[$key]);
				continue;
			}
			
			# Omit empty or substitute for a string (as required) if there is no value
			if ($omitEmpty) {
				if (($value === '') || ($value === false) || (is_null ($value))) {	// == '' is used because $value of 0 would otherwise be empty
					if (is_string ($omitEmpty)) {
						$array[$key] = $omitEmpty;
						$value = $omitEmpty;
					} else {
						unset ($array[$key]);
						continue;
					}
				}
			}
		}
		
		# Return if no data
		if (!$array) {
			return false;
		}
		
		# Construct the table and add the data in
		$html  = "\n\n<" . ($dlFormat ? 'dl' : 'table') . " class=\"$class\">";
		foreach ($array as $key => $value) {
			if (!$dlFormat) {$html .= "\n\t" . '<tr' . ($addRowKeyClasses ? ' class="' . htmlspecialchars ($key) . '"' : '') . '>';}
			$label = ($keySubstitutions && is_array ($keySubstitutions) && array_key_exists ($key, $keySubstitutions) ? $keySubstitutions[$key] : $key);
			$html .= "\n\t\t" . ($dlFormat ? '<dt>' : "<td class=\"key\">") . $label . ($showColons && strlen ($label) ? ':' : '') . ($dlFormat ? '</dt>' : '</td>');
			$html .= "\n\t\t" . ($dlFormat ? '<dd>' : "<td class=\"value\">") . (!$allowHtml ? nl2br (htmlspecialchars ($value)) : $value) . ($dlFormat ? '</dd>' : '</td>');
			if (!$dlFormat) {$html .= "\n\t" . '</tr>';}
		}
		$html .= "\n" . ($dlFormat ? '</dl>' : '</table>');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a definition list
	public static function htmlDl ($array, $keySubstitutions = array (), $omitEmpty = true, $class = 'lines', $allowHtml = false, $showColons = true, $addRowKeyClasses = false)
	{
		return self::htmlTableKeyed ($array, $keySubstitutions, $omitEmpty, $class, $allowHtml, $showColons, $addRowKeyClasses, $dlFormat = true);
	}
	
	
	# Function to convert Unicode to ISO; see http://www.php.net/manual/en/function.mb-convert-encoding.php#78033
	public static function unicodeToIso ($string)
	{
		# Return the string without alteration if the multibyte extension is not present
		if (!function_exists ('mb_convert_encoding')) {return $string;}
		
		# Return the result
		return mb_convert_encoding ($string, 'ISO-8859-1', mb_detect_encoding ($string, 'UTF-8, ISO-8859-1, ISO-8859-15', true));
	}
	
	
	# Function to write data to a file (first creating it if it does not exist); returns true if successful or false if there was a problem
	public static function writeDataToFile ($data, $file, $unicodeToIso = false, $unicodeAddBom = false)
	{
		# Down-conversion from Unicode to (Excel-readable) ISO
		if ($unicodeToIso) {
			$data = self::unicodeToIso ($data);
		}
		
		# Add the Unicode Byte Order Mark if required to a new file; useful to ensure that Excel understands the CSV as UTF-8
		if ($unicodeAddBom && !$unicodeToIso) {
			if (!file_exists ($file)) {
				$data = "\xEF\xBB\xBF" . $data;
			}
		}
		
		# Use file_put_contents if using PHP5
		return file_put_contents ($file, $data, FILE_APPEND);
	}
	
	
	# Function to create a file based on a full path supplied
	public static function createFileFromFullPath ($file, $data, $addStamp = false, $user = false)
	{
		# Determine the new file's directory location
		$newDirectory = dirname ($file);
		
		# Iteratively create the directory if it doesn't already exist
		if (!is_dir ($newDirectory)) {
			umask (0);
			if (strstr (PHP_OS, 'WIN')) {$newDirectory = str_replace ('/', '\\', $newDirectory);}
			if (!mkdir ($newDirectory, 0775, $recursive = true)) {
				#!# Consider better error handling here
				#echo "<p class=\"error\">There was a problem creating folders in the filestore.</p>";
				return false;
			}
		}
		
		# Add either '.old' (for '.old') or username.timestamp (for true) to the file if required
		if ($addStamp) {
			$file .= ($addStamp === '.old' ? '.old' : '.' . date ('Ymd-His') . ($user ? ".{$user}" : ''));
		}
		
		# Write the file
		#!# The @ is acceptable assuming all calling programs eventually log this problem somehow; it is put here because those which do will end up having errors thrown into the logs when they are actually being handled
		if (!@file_put_contents ($file, $data)) {
			#!# Consider better error handling here; the following line also removed
			#!# echo "<p class=\"error\">There was a problem creating a new file in the filestore.</p>";
			return false;
		}
		
		# Return the filename (which will equate to boolean true) if everything worked
		return $file;
	}
	
	
	# Function to check whether an area is writable; provides facilities additional to is_writable; works by moving back from the proposed location until it finds a folder and then checks if that is writable
	public static function directoryIsWritable ($location, $root = '/')
	{
		# If there is a trailing slash, remove it
		if (substr ($location, -1) == '/') {$location = substr ($location, 0, -1);}
		
		# If not starting with a slash or a dot, prepend the location
		if ((substr ($location, 0, 1) != '/') && (substr ($location, 0, 1) != '.')) {
			$location = dirname ($_SERVER['SCRIPT_FILENAME']) . '/' . $location;
		}
		
		# Split the directories up, removing the opening slash
		if (substr ($location, 0, 1) == '/') {$location = substr ($location, 1);}
		$directories = explode ('/', $location);
		
		# Loop through the directories in the list
		while ($directories) {
			
			# Re-compile the location
			$directory = $root . implode ('/', $directories);
			
			# If the directory exists, test for its writability; this will get called at least once because the root location will get tested at some point
			if (is_dir ($directory)) {
				return (is_writable ($directory));
			}
			
			# Remove the last directory in the list
			array_pop ($directories);
		}
		
		# Otherwise return true as nothing has been found
		return true;
	}
	
	
	# Function to create a case-insensitive version of in_array
	public static function iin_array ($needle, $haystack, $unsupportedArgument = NULL /* ignored for future implementation as $strict */, &$matchedValue = NULL)
	{
		# Return true if the needle is in the haystack
		foreach ($haystack as $item) {
			if (strtolower ($item) == strtolower ($needle)) {
				$matchedValue = $item;
				return true;
			}
		}
		
		# Otherwise return false
		return false;
	}
	
	
	# Function to move an array item to the start
	public static function array_move_to_start ($array, $newFirstName)
	{
		# Check whether the array is associative
		if (self::isAssociativeArray ($array)) {
			
			# Extract the first item
			$firstItem[$newFirstName] = $array[$newFirstName];
			
			# Unset the item from the main array
			unset ($array[$newFirstName]);
			
			# Reinsert the item at the start of the main array
			$array = $firstItem + $array;
			
		# If not associative, loop through until the item is found, remove then reinsert it
		#!# This assumes there is only one instance in the array
		} else {
			foreach ($array as $key => $value) {
				if ($value == $newFirstName) {
					unset ($array[$key]);
					array_unshift ($array, $newFirstName);
					break;
				}
			}
		}
		
		# Return the reordered array
		return $array;
	}
	
	
	# Function to insert a value before another in order; either afterField or beforeField must be specified
	public static function array_insert_value ($array, $newFieldKey, $newFieldValue, $afterField = false, $beforeField = false)
	{
		# Throw error if neither or both of after/before supplied
		if (!$afterField && !$beforeField) {return false;}
		if ($afterField && $beforeField) {return false;}
		
		# Insert the new value
		$data = array ();
		foreach ($array as $key => $value) {
			
			# Add field in 'before' mode
			if ($beforeField) {
				if ($key == $beforeField) {
					$data[$newFieldKey] = $newFieldValue;
				}
			}
			
			# Carry across current data
			$data[$key] = $value;
			
			# Add field in 'after' mode
			if ($afterField) {
				if ($key == $afterField) {
					$data[$newFieldKey] = $newFieldValue;
				}
			}
		}
		
		# Return the modified array
		return $data;
	}
	
	
	# Function to add a value to the array if not already present, returning the new number of elements in the array
	public static function array_push_new (&$array, $value, $strict = false)
	{
		# Add if not already present
		if (!in_array ($value, $array, $strict)) {
			$array[] = $value;
		}
		
		# Returns the new number of elements in the array
		return count ($array);
	}
	
	
	# Iterative function to rewrite key names in an array iteratively
	public static function array_key_str_replace ($search, $replace, $array)
	{
		# Work through each array element at the current level
		foreach ($array as $key => $value) {
			
			# Perform substitution if needed
			if (substr_count ($key, $search)) {
				unset ($array[$key]);	// Remove current element
				$key = str_replace ($search, $replace, $key);
				$array[$key] = $value;
			}
			
			# Iterate if required
			if (is_array ($value)) {
				$array[$key] = self::array_key_str_replace ($search, $replace, $value);
			}
		}
		
		# Return the modified array
		return $array;
	}
	
	
	# Function to rename fields in a dataset
	public static function array_rename_dataset_fields ($dataset, $substitutions)
	{
		# Work through each record
		$datasetAmended = array ();
		foreach ($dataset as $key => $record) {
			foreach ($record as $field => $value) {
				
				# Determine the new field if available
				if (array_key_exists ($field, $substitutions)) {
					if (is_null ($substitutions[$field])) {continue;}	// Skip fields marked as NULL
					$field = $substitutions[$field];
				}
				
				# Register in the result array
				$datasetAmended[$key][$field] = $value;
			}
		}
		
		# Return the amended dataset
		return $datasetAmended;
	}
	
	
	# Function to extract a single field from a dataset, returning an array of the values
	public static function array_extract_dataset_field ($dataset, $field)
	{
		# Extract the values
		$data = array ();
		foreach ($dataset as $key => $record) {
			$data[$key] = (array_key_exists ($field, $record) ? $record[$field] : NULL);	// Return NULL for this key if the field is not found
		}
		
		# Return the array
		return $data;
	}
	
	
	# Function to check the fieldnames in an associative array are consistent, and to return a list of them
	public static function arrayFieldsConsistent ($dataSet, &$failureAt = array ())
	{
		# Return an empty array if the dataset is empty
		if (!$dataSet) {return array ();}
		
		# Loop through the dataset
		foreach ($dataSet as $key => $data) {
			$fieldnames = array_keys ($data);
			
			# Check that the field list (including order) is consistent across every record
			if (isSet ($cachedFieldList)) {
				if ($fieldnames !== $cachedFieldList) {
					$failureAt = $data;
					return false;
				}
			}
			$cachedFieldList = $fieldnames;
		}
		
		# Return the fieldnames list
		return $fieldnames;
	}
	
	
	# Function to count the number of regexp matches in an array of values
	public static function array_preg_match_total ($pattern, $subjectArray)
	{
		# Find matches
		$matches = 0;
		foreach ($subjectArray as $value) {
			if (preg_match ($pattern, $value)) {
				$matches++;
			}
		}
		
		# Return the total matches
		return $matches;
	}
	
	
	# Function to find a regexp match in an array of values
	public static function preg_match_array ($pattern, $subjectArray, $returnKey = false)
	{
		# Search for a match
		foreach ($subjectArray as $key => $value) {
			$delimiter = '@';
			if (preg_match ($delimiter . addcslashes ($pattern, $delimiter) . $delimiter, $value)) {
				return ($returnKey ? $key : $value);
			}
		}
		
		# No match
		return false;
	}
	
	
	# Function to add an ordinal suffix to a number from 0-99 [from http://forums.devshed.com/t43304/s.html]
	public static function ordinalSuffix ($number)
	{
		# Return the value unmodified if it is empty
		if (!strlen ((string) $number)) {return $number;}
		
		# Obtain the last character in the number
		$last = substr ($number, -1);
		
		# Obtain the penultimate number
		if (strlen ($number) < 2) {
			$penultimate = 0;
		} else {
			$penultimate = substr ($number, -2);
		}
		
		# Assign the suffix
		if ($penultimate >= 10 && $penultimate < 20) {
			$suffix = 'th';
		} else if ($last == 1) {
			$suffix = 'st';
		} else if ($last == 2) {
			$suffix = 'nd';
		} else if ($last == 3) {
			$suffix = 'rd';
		} else {
			$suffix = 'th';
		}
		
		# Return the result
		return number_format ($number) . $suffix;
	}
	
	
	# Function to convert camelCase to standard text
	#!# Accept an array so it loops through all
	#!# Doesn't deal well with letters following numbers, e.g. 'option12foo' becomes 'Option 12foo'
	public static function unCamelCase ($string)
	{
		# Special case certain words
		$replacements = array (
			'url' => 'URL',
			'email' => 'e-mail',
		);
		
		# Perform the conversion; based on www.php.net/ucwords#49303
		$string = ucfirst ($string);
		$parts = preg_split ('/([A-Z]|[0-9]+)/', $string, false, PREG_SPLIT_DELIM_CAPTURE);
		$words = array ();
		array_shift ($parts);
		$count = count ($parts);
		for ($i = 0; $i < $count; ++$i) {
			if ($i % 2) {
				$word = strtolower ($parts[$i - 1] . $parts[$i]);
				$word = str_replace (array_keys ($replacements), array_values ($replacements), $word);
				$words[] = $word;
			}
		}
		
		# Compile the words
		$string = ucfirst (implode (' ', $words));
		
		# Return the string
		return $string;
	}
	
	
	# Function to convert an ini_ setting size to bytes
	public static function convertSizeToBytes ($string)
	{
		# Split the supplied size into a number and a unit
		$parts = array ();
		preg_match ('/^(\d+)([bkm]*)$/iD', $string, $parts);
		
		# Convert the size to a double and the unit to lower-case
		$size = (double) $parts[1];
		$unit = strtolower ($parts[2]);
		
		# Convert the number based on the unit
		switch ($unit) {
			case 'm':
				return ($size * (double) 1048576);
			case 'k':
				return ($size * (double) 1024);
			case 'b':
			default:
				return $size;
		}
	}
	
	
	# Function to format bytes
	public static function formatBytes ($bytes)
	{
		# Select either MB or KB
		if ($bytes > (1024*1024)) {
			$result = max (1, round ($bytes / (1024*1024), 1)) . 'MB';
		} else {
			$result = max (1, round ($bytes / 1024)) . 'KB';
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to regroup a data set into separate groups
	public static function regroup ($dataSet, $regroupByField, $removeGroupField = true, $regroupedColumnKnownUnique = false)
	{
		# Return the data unmodified if not an array or empty
		if (!is_array ($dataSet) || empty ($dataSet)) {return $dataSet;}
		
		# Rearrange the data
		$rearrangedData = array ();
		foreach ($dataSet as $recordId => $record) {
			$grouping = $record[$regroupByField];
			if ($removeGroupField) {
				unset ($dataSet[$recordId][$regroupByField]);
			}
			
			# Add the data; if the regroup-by column is known to be unique, then don't create a nested array
			if ($regroupedColumnKnownUnique) {
				$rearrangedData[$grouping] = $dataSet[$recordId];
			} else {
				$rearrangedData[$grouping][$recordId] = $dataSet[$recordId];
			}
		}
		
		# Return the data
		return $rearrangedData;
	}
	
	
	# Function to reindex a dataset by a specified key within each record
	public static function reindex ($dataSet, $reindexByField, $removeIndexField = true)
	{
		# Return the data unmodified if not an array or empty
		if (!is_array ($dataSet) || empty ($dataSet)) {return $dataSet;}
		
		# Rearrange the data
		$rearrangedData = array ();
		foreach ($dataSet as $recordId => $record) {
			$newRecordId = $record[$reindexByField];
			if ($removeIndexField) {
				unset ($record[$reindexByField]);
			}
			$rearrangedData[$newRecordId] = $record;
		}
		
		# Return the data
		return $rearrangedData;
	}
	
	
	# Function to create an unordered HTML list
	public static function htmlUl ($array, $parentTabLevel = 0, $className = NULL, $ignoreEmpty = true, $sanitise = false, $nl2br = false, $liClass = false, $selected = false)
	{
		# Return an empty string if no items
		if (!is_array ($array) || empty ($array)) {return '';}
		
		# Prepare the tab string
		$tabs = str_repeat ("\t", ($parentTabLevel));
		
		# Build the list
		$html = "\n$tabs<ul" . ($className ? " class=\"{$className}\"" : '') . '>';
		foreach ($array as $key => $item) {
			
			# Skip an item if the item is empty and the flag is set to ignore these
			if (($ignoreEmpty) && (empty ($item))) {continue;}
			
			# Add the item to the HTML
			if ($sanitise) {$item = htmlspecialchars ($item);}
			if ($nl2br) {$item = nl2br ($item);}
			
			# Determine a class
			$class = array ();
			if ($selected !== false && ($selected == $key)) {$class[] = 'selected';}	// !== is used so that it can match the empty string
			if ($liClass) {
				$class[] = ($liClass === true ? $key : $liClass);
			}
			$class = ($class ? ' class="' . implode (' ', $class) . '"' : '');
			
			# Assign the HTML
			$html .= "\n$tabs\t<li" . $class . '>' . $item . '</li>';
		}
		$html .= "\n$tabs</ul>";
		
		# Return the result
		return $html;
	}
	
	
	# Function to create an ordered HTML list
	public static function htmlOl ($array, $parentTabLevel = 0, $className = NULL, $ignoreEmpty = true, $sanitise = false, $nl2br = false, $liClass = false)
	{
		# Get the HTML as an unordered list
		$html = self::htmlUl ($array, $parentTabLevel = 0, $className, $ignoreEmpty = true, $sanitise = false, $nl2br = false, $liClass = false);
		
		# Convert to an ordered list
		$html = str_replace (array ('<ul', '</ul>'), array ('<ol', '</ol>'), $html);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to convert a hierarchy into a hierarchical list; third argument will produce level:text (if set to true) or will carry over text as textFrom0:textFrom1:textFrom2 ... as the link (if set to false)
	public static function htmlUlHierarchical ($unit, $class = 'pde', $carryOverQueryText = false, $lastOnly = true, $lowercaseLinks = true, $level = 0, $baseUrl = '')
	{
		# Work out the tab HTML
		$tabs = str_repeat ("\t", $level);
		
		# Start the HTML
		$class = ($class ? " class=\"{$class}\"" : '');
		$html  = "\n{$tabs}<ul{$class}>";
		
		# Loop through each level, assembling either the query text or level:text as the link
		foreach ($unit as $name => $contents) {
			$last = $lastOnly && is_array ($contents) && (empty ($contents));
			$queryText = ($last ? '' : ($carryOverQueryText ? ($level != 0 ? $carryOverQueryText . ':' : '') : ($level + 1) . ':')) . str_replace (' ', '+', strtolower ($name));
			$link = ($last /*(substr ($name, 0, 1) != '<')*/ ? "<a href=\"{$baseUrl}/{$queryText}/\">" : '');
			$html .= "\n\t{$tabs}<li>{$link}" . htmlspecialchars ($name) . ($link ? '</a>' : '');
			if (is_array ($contents) && (!empty ($contents))) {
				$html .= self::htmlUlHierarchical ($contents, false, ($carryOverQueryText ? $queryText : false), $lastOnly, $lowercaseLinks, ($level + 1), $baseUrl);
			}
			$html .= '</li>';
		}
		
		# Complete the HTML
		$html .= "\n{$tabs}</ul>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a listing to the results page
	public static function splitListItems ($listItems, $columns = 2, $class = 'splitlist', $byStrlen = false, $firstColumnHtml = false)
	{
		# Work out the maximum number of items in a column
		$maxPerColumn = ceil (count ($listItems) / $columns);
		if ($byStrlen) {$maxPerColumn = ceil (strlen (implode ($listItems)) / $columns);}
		
		# Create the list
		$html  = "\n<table class=\"{$class}\">";
		$html .= "\n\t<tr>";
		if ($firstColumnHtml) {
			$html .= "\n\t\t<td>";
			$html .= "\n\t\t\t" . $firstColumnHtml;
			$html .= "\n\t\t</td>";
		}
		$html .= "\n\t\t<td>";
		$html .= "\n\t\t\t<ul>";
		$i = 0;
		$strlen = 0;
		$totalListItems = count ($listItems);
		foreach ($listItems as $listItem) {
			$html .= "\n\t\t\t\t" . $listItem;
			
			# Do not split if there are fewer items than columns
			if ($totalListItems < $columns) {continue;}
			
			# Start a new column when the limit is reached
			$i++;
			$strlen += strlen ($listItem);
			if (($byStrlen ? $strlen : $i) >= $maxPerColumn) {
				$html .= "\n\t\t\t</ul>";
				$html .= "\n\t\t</td>";
				$html .= "\n\t\t<td>";
				$html .= "\n\t\t\t<ul>";
				$i = 0;
				$strlen = 0;
			}
		}
		$html .= "\n\t\t\t</ul>";
		$html .= "\n\t\t</td>";
		$html .= "\n\t</tr>";
		$html .= "\n</table>\n";
		
		# Return the constructed HTML
		return $html;
	}
	
	
	# Generalised support function to check whether a filename is valid given a list of disallowed and allowed extensions, with the extension checked case insensitively; both the checked filename and the allowed/disallowed extensions must start with a .
	public static function filenameIsValid ($name, $disallowedExtensions = array (), $allowedExtensions = array ())
	{
		# Determine the extension of the file
		$extension = '.' . pathinfo ($name, PATHINFO_EXTENSION);
		
		# Check for a disallowed extension
		if ($disallowedExtensions) {
			if (self::iin_array ($extension, $disallowedExtensions)) {
				return false;
			}
		}
		
		# Check whether it's an allowed extension if a list has been supplied
		if ($allowedExtensions) {
			return (self::iin_array ($extension, $allowedExtensions));
		}
		
		# Otherwise pass
		return true;
	}
	
	
	# Function to help with mimeType/extension lookups
	public static function mimeTypeExtensions ()
	{
		# Define the MIME Types; list taken from www.mimetype.org
		$mimeTypes = '
		application/SLA	stl
		application/STEP	step
		application/STEP	stp
		application/acad	dwg
		application/andrew-inset	ez
		application/clariscad	ccad
		application/drafting	drw
		application/dsptype	tsp
		application/dxf	dxf
		application/excel	xls
		application/i-deas	unv
		application/java-archive	jar
		application/mac-binhex40	hqx
		application/mac-compactpro	cpt
		application/vnd.ms-powerpoint	pot
		application/vnd.ms-powerpoint	pps
		application/vnd.ms-powerpoint	ppt
		application/vnd.ms-powerpoint	ppz
		application/msword	doc
		application/octet-stream	bin
		application/octet-stream	class
		application/octet-stream	dms
		application/octet-stream	exe
		application/octet-stream	lha
		application/octet-stream	lzh
		application/oda	oda
		application/ogg	ogg
		application/ogg	ogm
		application/pdf	pdf
		application/pgp	pgp
		application/postscript	ai
		application/postscript	eps
		application/postscript	ps
		application/pro_eng	prt
		application/rtf	rtf
		application/set	set
		application/smil	smi
		application/smil	smil
		application/solids	sol
		application/vda	vda
		application/vnd.mif	mif
		application/vnd.ms-excel	xlc
		application/vnd.ms-excel	xll
		application/vnd.ms-excel	xlm
		application/vnd.ms-excel	xls
		application/vnd.ms-excel	xlw
		application/vnd.rim.cod	cod
		application/x-arj-compressed	arj
		application/x-bcpio	bcpio
		application/x-cdlink	vcd
		application/x-chess-pgn	pgn
		application/x-cpio	cpio
		application/x-csh	csh
		application/x-debian-package	deb
		application/x-director	dcr
		application/x-director	dir
		application/x-director	dxr
		application/x-dvi	dvi
		application/x-freelance	pre
		application/x-futuresplash	spl
		application/x-gtar	gtar
		application/x-gunzip	gz
		application/x-gzip	gz
		application/x-hdf	hdf
		application/x-ipix	ipx
		application/x-ipscript	ips
		application/x-javascript	js
		application/x-koan	skd
		application/x-koan	skm
		application/x-koan	skp
		application/x-koan	skt
		application/x-latex	latex
		application/x-lisp	lsp
		application/x-lotusscreencam	scm
		application/x-mif	mif
		application/x-msdos-program	bat
		application/x-msdos-program	com
		application/x-msdos-program	exe
		application/x-netcdf	cdf
		application/x-netcdf	nc
		application/x-perl	pl
		application/x-perl	pm
		application/x-rar-compressed	rar
		application/x-sh	sh
		application/x-shar	shar
		application/x-shockwave-flash	swf
		application/x-stuffit	sit
		application/x-sv4cpio	sv4cpio
		application/x-sv4crc	sv4crc
		application/x-tar-gz	tar.gz
		application/x-tar-gz	tgz
		application/x-tar	tar
		application/x-tcl	tcl
		application/x-tex	tex
		application/x-texinfo	texi
		application/x-texinfo	texinfo
		application/x-troff-man	man
		application/x-troff-me	me
		application/x-troff-ms	ms
		application/x-troff	roff
		application/x-troff	t
		application/x-troff	tr
		application/x-ustar	ustar
		application/x-wais-source	src
		application/x-zip-compressed	zip
		application/zip	zip
		audio/TSP-audio	tsi
		audio/basic	au
		audio/basic	snd
		audio/midi	kar
		audio/midi	mid
		audio/midi	midi
		audio/mpeg	mp2
		audio/mpeg	mp3
		audio/mpeg	mpga
		audio/ulaw	au
		audio/x-aiff	aif
		audio/x-aiff	aifc
		audio/x-aiff	aiff
		audio/x-mpegurl	m3u
		audio/x-ms-wax	wax
		audio/x-ms-wma	wma
		audio/x-pn-realaudio-plugin	rpm
		audio/x-pn-realaudio	ram
		audio/x-pn-realaudio	rm
		audio/x-realaudio	ra
		audio/x-wav	wav
		chemical/x-pdb	pdb
		chemical/x-pdb	xyz
		image/cmu-raster	ras
		image/gif	gif
		image/ief	ief
		image/jpeg	jpe
		image/jpeg	jpeg
		image/jpeg	jpg
		image/png	png
		image/tiff	tif tiff
		image/tiff	tif
		image/tiff	tiff
		image/x-cmu-raster	ras
		image/x-portable-anymap	pnm
		image/x-portable-bitmap	pbm
		image/x-portable-graymap	pgm
		image/x-portable-pixmap	ppm
		image/x-rgb	rgb
		image/x-xbitmap	xbm
		image/x-xpixmap	xpm
		image/x-xwindowdump	xwd
		model/iges	iges
		model/iges	igs
		model/mesh	mesh
		model/mesh	msh
		model/mesh	silo
		model/vrml	vrml
		model/vrml	wrl
		text/css	css
		text/html	htm
		text/html	html htm
		text/html	html
		text/plain	asc txt
		text/plain	asc
		text/plain	c
		text/plain	cc
		text/plain	f90
		text/plain	f
		text/plain	h
		text/plain	hh
		text/plain	m
		text/plain	txt
		text/richtext	rtx
		text/rtf	rtf
		text/sgml	sgm
		text/sgml	sgml
		text/tab-separated-values	tsv
		text/vnd.sun.j2me.app-descriptor	jad
		text/x-setext	etx
		text/xml	xml// This is disabled because XML has several different MIME Types
		video/dl	dl
		video/fli	fli
		video/flv	flv
		video/gl	gl
		video/mpeg	mp2
		video/mpeg	mpe
		video/mpeg	mpeg
		video/mpeg	mpg
		video/quicktime	mov
		video/quicktime	qt
		video/vnd.vivo	viv
		video/vnd.vivo	vivo
		video/x-fli	fli
		video/x-ms-asf	asf
		video/x-ms-asx	asx
		video/x-ms-wmv	wmv
		video/x-ms-wmx	wmx
		video/x-ms-wvx	wvx
		video/x-msvideo	avi
		video/x-sgi-movie	movie
		www/mime	mime
		x-conference/x-cooltalk	ice
		x-world/x-vrml	vrm
		x-world/x-vrml	vrml';
		
		# Parse the list as array ($extension => $mimeType, ... )
		$list = array ();
		$mimeTypes = explode ("\n", trim ($mimeTypes));
		foreach ($mimeTypes as $index => $line) {
			list ($mimeType, $extension) = explode ("\t", trim ($line), 2);
			if (substr_count ($extension, ' ')) {continue;}	// Limit of 2 for some extensions in the source listing have two listed, e.g. "asc txt"
			$list[$extension] = $mimeType;
		}
		
		# Return the list
		return $list;
	}
	
	
	# Wrapper function to get CSV data
	#!# Not finished - needs file handling
	#!# Need to merge this with csv::getData
	public static function getCsvData ($filename, $getHeaders = false, $assignKeys = false, $keyAsFirstRow = false)
	{
		# Make sure the file exists
		if (!is_readable ($filename)) {return false;}
		
		# Open the file
		if (!$fileHandle = fopen ($filename, 'rb')) {return false;}
		
		# Determine the longest line length
		$longestLineLength = 1000;
		$array = file ($filename);
		$count = count ($array);
		for ($i = 0; $i < $count; $i++) {
			if ($longestLineLength < strlen ($array[$i])) {
				$longestLineLength = strlen ($array[$i]);
			}
		}
		unset ($array);
		
		# Get the column names
		if (!$mappings = fgetcsv ($fileHandle, $longestLineLength + 1)) {return false;}
		
		# Start a counter if assigning keys
		if ($assignKeys) {$assignedKey = 0;}
		
		# Loop through each line of data
		$data = array ();
		while ($csvData = fgetcsv ($fileHandle, filesize ($filename))) {
			
			# Check the first item exists and set it as the row key then unset it
			if ($firstRowCell = $csvData[0]) {
				if (!$keyAsFirstRow) {unset ($csvData[0]);}
				
				# Determine the key name to use
				$rowKey = ($assignKeys ? $assignedKey++ : $firstRowCell);
				
				# Loop through each item of data
				#!# What should happen if a row has fewer columns than another? If there are fields missing, then it may be better to allow offsets to be generated as otherwise the data error may not be known. Filling in the remaining fields is probably wrong as we don't know which are missing.
				foreach ($csvData as $key => $value) {
					
					# Assign the entry into the table
					if (isSet ($mappings[$key])) {$data[$rowKey][$mappings[$key]] = $value;}
				}
			}
		}
		
		# Close the file
		fclose ($fileHandle);
		
		# Return the result
		return $data;
	}
	
	
	# Wrapper function to turn a (possibly multi-dimensional) associative array into a correctly-formatted CSV format (including escapes)
	public static function arrayToCsv ($array, $delimiter = ',', $nestParent = false)
	{
		# Start an array of headers and the data
		$headers = array ();
		$data = array ();
		
		# Loop through each key value pair
		foreach ($array as $key => $value) {
			
			# If the associative array is multi-dimensional, iterate and thence add the sub-headers and sub-values to the array
			if (is_array ($value)) {
				list ($subHeaders, $subData) = self::arrayToCsv ($value, $delimiter, $key);
				
				# Merge the headers and subkeys
				$headers[] = $subHeaders;
				$data[] = $subData;
				
			# If the associative array is multi-dimensional, assign directly
			} else {
				
				# In nested mode, prepend the each key name with the parent name
				if ($nestParent) {$key = "$nestParent: $key";}
				
				# Add the key and value to arrays of the headers and data
				$headers[] = self::csvSafeDataCell ($key, $delimiter);
				$data[] = self::csvSafeDataCell ($value, $delimiter);
			}
		}
		
		# Compile the header and data lines, placing a delimeter between each item
		$headerLine = implode ($delimiter, $headers) . (!$nestParent ? "\n" : '');
		$dataLine = implode ($delimiter, $data) . (!$nestParent ? "\n" : '');
		
		# Return the result
		return array ($headerLine, $dataLine);
	}
	
	
	# Helper function to parse out blocks in a text file to an array
	public static function parseBlocks ($string, $fieldnames /* to allocate, in order of appearance in each block */, $firstFieldIsId, &$error = false)
	{
		# Strip comments (hash then space)
		$string = preg_replace ("/^#\s+(.*)$/m", '', $string);
		
		# Normalise to single line between each block
		$string = str_replace ("\r\n", "\n", $string);
		while (substr_count ($string, "\n\n\n")) {
			$string = str_replace ("\n\n\n", "\n\n", trim ($string));
		}
		
		# Parse out to blocks
		$blocks = explode ("\n\n", $string);
		
		# Count fieldnames to enable a count that each block matches
		$totalFieldnames = count ($fieldnames);
		
		# Parse out each test block
		$results = array ();
		foreach ($blocks as $index => $block) {
			$result = array ();
			$lines = explode ("\n", $block, count ($fieldnames));
			if (count ($lines) != $totalFieldnames) {
				$error = 'In block #' . ($index + 1) . ', the number of fields was incorrect.';
				return false;
			}
			foreach ($fieldnames as $index => $fieldname) {
				$result[$fieldname] = $lines[$index];
			}
			
			# Index by IDs defined in data or index
			$id = ($firstFieldIsId ? $lines[0] : $index);
			
			# Register the result
			$results[$id] = $result;
		}
		
		# Return the results
		return $results;
	}
	
	
	# Function to convert a text block to a list
	public static function textareaToList ($string, $isFile = false, $stripComments = false, $longerFirst = false)
	{
		# Load as a file instead of string if required
		if ($isFile) {
			$string = file_get_contents ($string);
		}
		
		# Trim the value
		$string = trim ($string);
		
		# End if none
		if (!strlen ($string)) {return array ();}
		
		# Split by newline
		$string = str_replace ("\r\n", "\n", $string);
		$list = explode ("\n", $string);
		
		# Trim each line
		foreach ($list as $index => $line) {
			$list[$index] = trim ($line);
		}
		
		# Strip empty lines
		foreach ($list as $index => $line) {
			if (!strlen ($line)) {unset ($list[$index]);}
		}
		
		# Strip comments if required
		if ($stripComments) {
			foreach ($list as $index => $line) {
				if (preg_match ('/^#/', $line)) {unset ($list[$index]);}
			}
		}
		
		# If required, order the values so that longer (string-length) values come first, making it safe for multiple replacements
		if ($longerFirst) {
			usort ($list, array ('self', 'lengthDescValueSort'));
		}
		
		# Reindex to ensure starting from 0, following line stripping and possible longer-first operations
		$list = array_values ($list);
		
		# Return the list
		return $list;
	}
	
	
	# Helper function to sort by string length descending then by value, for use in a callback; see: https://stackoverflow.com/a/16311030/180733
	private static function lengthDescValueSort ($a, $b)
	{
		# Obtain the lenghts
		$la = mb_strlen ($a);
		$lb = mb_strlen ($b);
		
		# If same length, compare by value; uses case-insensitive searching - not actually necessary, just nicer for debugging
		if ($la == $lb) {
			return strcasecmp ($a, $b);		// Is binary-safe
		}
		
		# Otherwise compare by string length descending
		return $lb - $la;
	}
	
	
	# Function to parse a set of numeric items, e.g. '12,134-6' => array(12,134,135,136)
	public static function parseRangeList ($string, &$errorMessage)
	{
		# End if not syntactically valid
		if (!preg_match ('/^[-,0-9]*[0-9]$/', $string)) {	// * is used otherwise a single, one-digit number, e.g. '4', won't be accepted
			$errorMessage = 'The set of location numbers you specified is not syntactically valid.';
			return false;
		}
		
		# Start an array of IDs
		$ids = array ();
		
		# Split the list into a set of tokens separated by comma
		$tokens = explode (',', $string);
		foreach ($tokens as $token) {
			
			# Put single IDs directly into the list
			if (ctype_digit ($token)) {
				$ids[] = $token;
				continue;
			}
			
			# The remainder must be ranges, i.e. xxxx-yyyy (though an invalid xxxx-yyyy-zzzz could be present), so expand these, ensuring that there are not too many
			if (!$token = self::expandRange ($token, $errorMessage)) {
				return false;
			}
			$list = explode (' ', $token);
			$ids = array_merge ($ids, $list);
		}
		
		# Remove duplicates
		$ids = array_unique ($ids);
		
		# Return the list of IDs
		return $ids;
	}
	
	
	/**
	 * Function to string-replace cases of e.g. '9532-4' to '9532 9533 9534' within a longer string
	 *
	 */
	public static function expandRange ($string, &$errorMessage)
	{
		# Find any matches (ending if none), and extract the first and last location number for each
		if (!preg_match_all ('/^([0-9]+)-([0-9]+)$/', $string, $matches, PREG_SET_ORDER)) {
			$errorMessage = 'The range you specified is not valid.';	// e.g. caused by '1-2-3'
			return false;
		}
		
		# Deal with each match
		foreach ($matches as $match) {
			
			# Compute the difference
			$start	= $match[1];
			$end	= $match[2];
			$difference = $end - $start;
			
			# If the difference is negative, and the start string is of fewer characters than the end string, then this is in the format 980-95 meaning 980-995
			if (($difference < 0) && (strlen ($start) > strlen ($end))) {
				
				# Add add on the starting figures from the start to produce the new end, e.g. '9'.'95' makes 995, and recompute the difference
				$addCharactersFromStart = strlen ($start) - strlen ($end);
				$end = substr ($start, 0, $addCharactersFromStart) . $end;
				$difference = $end - $start;
			}
			
			# If the difference is still negative, end
			if ($difference < 0) {
				$errorMessage = 'The range you specified must begin with a smaller number.';
				return false;
			}
			
			# If there is a difference
			if ($difference) {
				$maximumTotal = 50;
				if (($difference + 1) > $maximumTotal) {	// E.g. 12-17 is a difference of 5 but is 6 photos, hence the +1
					$errorMessage = 'Sorry, the total number of items (' . ($difference + 1) . ") in the range you specified ({$start}-{$end}) is too great; a maximum of {$maximumTotal} items in a range is allowed. Please check and try again.";
					return false;
				}
				$new = array ();
				for ($i = $start; $i <= $end; $i++) {
					$new[] = $i;
				}
				
				# Perform the substitution
				$string = preg_replace ("/\b{$match[0]}\b/", implode (' ', $new), $string);
			}
		}
		
		# Trim surrounding whitespace
		$string = trim ($string);
		
		# Return the replaced string
		return $string;
	}
	
	
	# Helper function to convert a list of, or string containing, pipe-surrounded tokens to a uniqued list, e.g. array('|a|b|','|c|') becomes array ('a','b','c'); if a value has no separator, this will be returned as a single-value array; in lookup mode, retain the originals as the keys and provide a list instead
	public static function splitCombinedTokenList ($data, $separator = '|', $lookupMode = false)
	{
		# End if an empty array or zero-length string; this will not corrupt string "0"
		if ($data == array () || (is_string ($data) && !strlen ($data))) {return array ();}
		
		# If a string, convert to an array
		if (is_string ($data)) {$data = array ($data);}
		
		# Extract each item
		$list = array ();
		foreach ($data as $item) {
			$tokens = explode ($separator, trim ($item));	// Will ensure that a value of 'foo' (rather than '|foo|') still enters the result list
			foreach ($tokens as $token) {
				$token = trim ($token);
				if ($token == $separator || !strlen ($token)) {continue;}
				if ($lookupMode) {
					$list[$item][] = $token;	// This will automatically avoid duplicates because of the keying
				} else {
					$list[] = $token;
				}
			}
		}
		
		# Unique the list if required
		if (!$lookupMode) {
			$list = array_unique ($list);
		}
		
		# Return the list
		return $list;
	}
	
	
	# Called function to make a data cell CSV-safe
	public static function csvSafeDataCell ($data, $delimiter = ',')
	{
		#!# Consider a flag for HTML entity cleaning so that e.g. " rather than &#8220; appears in Excel
		
		# Double any quotes existing within the data
		$data = str_replace ('"', '""', $data);
		
		# Strip carriage returns to prevent textarea breaks appearing wrongly in a CSV file opened in Windows in e.g. Excel
		$data = str_replace ("\r", '', $data);
		#!# Try replacing the above line with the more correct
		# $data = str_replace ("\r\n", "\n", $data);
		
		# If an item contains the delimiter or line breaks, surround with quotes
		if ((strpos ($data, $delimiter) !== false) || (strpos ($data, "\n") !== false) || (strpos ($data, '"') !== false)) {$data = '"' . $data . '"';}
		
		# Return the cleaned data cell
		return $data;
	}
	
	
	# Function to provide a preference type switcher
	public static function preferenceSwitcher (&$preferenceSwitcherHtml = '', $preferenceTypes = array (), $id = 'listingtypeswitcher', $baseUrl = '')
	{
		/* 
		# Requires an array of modes, e.g.
		$listingTypes = array (
			'listing'	=> 'application_view_columns',
			'records'	=> 'application_tile_vertical',
		);
		*/
		
		# End if no modes set
		if (!$preferenceTypes) {return false;}
		
		# Start the HTML for the record listing
		$preferenceSwitcherHtml = '';
		
		# Create the icons, with links for each
		$currentPage = htmlspecialchars ($_SERVER['SCRIPT_URL']);
		
		# Define the modes
		$parameter = 'viewmode';
		$choices = array ();
		foreach ($preferenceTypes as $mode => $icon) {
			$choices[$mode] = "<a href=\"{$currentPage}?{$parameter}={$mode}\" rel=\"nofollow\"><img src=\"/images/icons/{$icon}.png\" class=\"icon\" title=\"" . htmlspecialchars (ucfirst ($mode) . ' mode') . "\" /></a>";
		}
		$default = key ($choices);
		
		# If a requested type has been selected, set a cookie for the requested type, and redirect to the same URL but without the query string
		$requested = ((isSet ($_GET[$parameter]) && array_key_exists ($_GET[$parameter], $choices)) ? $_GET[$parameter] : false);
		if ($requested) {
			$thirtyDays = 7 * 24 * 60 * 60;
			setcookie ($parameter, $requested, time () + $thirtyDays, $baseUrl . '/', $_SERVER['SERVER_NAME']);
			$preferenceSwitcherHtml = self::sendHeader (301, $_SERVER['SCRIPT_URL'], $redirectMessage = true);
			return $preferenceSwitcherHtml;
		}
		
		# Read the cookie
		$selected = ((isSet ($_COOKIE[$parameter]) && array_key_exists ($_COOKIE[$parameter], $choices)) ? $_COOKIE[$parameter] : $default);
		
		# Compile the HTML, highlighting the selected type
		$preferenceSwitcherHtml = self::htmlUl ($choices, 0, 'inline', true, false, false, false, $selected);
		
		# Surround with a div
		$preferenceSwitcherHtml = "\n<div id=\"{$id}\">" . $preferenceSwitcherHtml . "\n</div>";
		
		# Return the selection
		return $selected;
	}
	
	
	# Function to create a cloud tag; based on comment posted under http://www.hawkee.com/snippet.php?snippet_id=1485
	public static function tagCloud ($tags, $classBase = 'tagcloud', $sizes = 5)
	{
		# End if no tags
		if (!$tags) {return false;}
		
		# Sort the tags
		asort ($tags);
		
		// Start with the sorted list of tags and divide by the number of font sizes (buckets). Then proceed to put an even number of tags into each bucket. The only restriction is that tags of the same count can't span 2 buckets, so some buckets may have more tags than others. Because of this, the sorted list of remaining tags is divided by the remaining 'buckets' to evenly distribute the remainder of the tags and to fill as many 'buckets' as possible up to the largest font size.
		$total_tags = count ($tags);
		$min_tags = $total_tags / $sizes;
		
		$bucket_count = 1;
		$bucket_items = 0;
		$tags_set = 0;
		foreach ($tags as $tag => $count) {
			
			// If we've met the minimum number of tags for this class and the current tag does not equal the last tag, we can proceed to the next class.
			if (($bucket_items >= $min_tags) && ($last_count != $count) && ($bucket_count < $sizes)) {
				$bucket_count++;
				$bucket_items = 0;
				
				// Calculate a new minimum number of tags for the remaining classes.
				$remaining_tags = $total_tags - $tags_set;
				$min_tags = $remaining_tags / $bucket_count;
			}
			
			// Set the tag to the current class.
			$finalised[$tag] = $classBase . $bucket_count;
			$bucket_items++;
			$tags_set++;
			
			$last_count = $count;
		}
		
		# Sort the list
		ksort ($finalised);
		
		# Return the list
		return $finalised;
	}
	
	
	# Function to create a URL slug
	#!# Solution based on www.thinkingphp.org/2006/10/19/title-to-url-slug-conversion/ ; consider instead using something more like Wordpress' sanitize_title, as here: http://trac.wordpress.org/browser/trunk/wp-includes/functions-formatting.php?rev=1481
	public static function createUrlSlug ($string)
	{
		# Trim the string
		$string = trim ($string);
		
		# Lower-case the string
		$string = strtolower ($string);
		
		# Conversion chart taken from http://bakery.cakephp.org/articles/view/slug-behavior
		$utf8Conversions = array (
			// Decompositions for Latin-1 Supplement
			chr(195).chr(128) => 'A',
			chr(195).chr(129) => 'A',
			chr(195).chr(130) => 'A',
			chr(195).chr(131) => 'A',
			chr(195).chr(132) => 'A',
			chr(195).chr(133) => 'A',
			chr(195).chr(135) => 'C',
			chr(195).chr(136) => 'E',
			chr(195).chr(137) => 'E',
			chr(195).chr(138) => 'E',
			chr(195).chr(139) => 'E',
			chr(195).chr(140) => 'I',
			chr(195).chr(141) => 'I',
			chr(195).chr(142) => 'I',
			chr(195).chr(143) => 'I',
			chr(195).chr(145) => 'N',
			chr(195).chr(146) => 'O',
			chr(195).chr(147) => 'O',
			chr(195).chr(148) => 'O',
			chr(195).chr(149) => 'O',
			chr(195).chr(150) => 'O',
			chr(195).chr(153) => 'U',
			chr(195).chr(154) => 'U',
			chr(195).chr(155) => 'U',
			chr(195).chr(156) => 'U',
			chr(195).chr(157) => 'Y',
			chr(195).chr(159) => 's',
			chr(195).chr(160) => 'a',
			chr(195).chr(161) => 'a',
			chr(195).chr(162) => 'a',
			chr(195).chr(163) => 'a',
			chr(195).chr(164) => 'a',
			chr(195).chr(165) => 'a',
			chr(195).chr(167) => 'c',
			chr(195).chr(168) => 'e',
			chr(195).chr(169) => 'e',
			chr(195).chr(170) => 'e',
			chr(195).chr(171) => 'e',
			chr(195).chr(172) => 'i',
			chr(195).chr(173) => 'i',
			chr(195).chr(174) => 'i',
			chr(195).chr(175) => 'i',
			chr(195).chr(177) => 'n',
			chr(195).chr(178) => 'o',
			chr(195).chr(179) => 'o',
			chr(195).chr(180) => 'o',
			chr(195).chr(181) => 'o',
			chr(195).chr(182) => 'o',
			chr(195).chr(182) => 'o',
			chr(195).chr(185) => 'u',
			chr(195).chr(186) => 'u',
			chr(195).chr(187) => 'u',
			chr(195).chr(188) => 'u',
			chr(195).chr(189) => 'y',
			chr(195).chr(191) => 'y',
			// Decompositions for Latin Extended-A
			chr(196).chr(128) => 'A',
			chr(196).chr(129) => 'a',
			chr(196).chr(130) => 'A',
			chr(196).chr(131) => 'a',
			chr(196).chr(132) => 'A',
			chr(196).chr(133) => 'a',
			chr(196).chr(134) => 'C',
			chr(196).chr(135) => 'c',
			chr(196).chr(136) => 'C',
			chr(196).chr(137) => 'c',
			chr(196).chr(138) => 'C',
			chr(196).chr(139) => 'c',
			chr(196).chr(140) => 'C',
			chr(196).chr(141) => 'c',
			chr(196).chr(142) => 'D',
			chr(196).chr(143) => 'd',
			chr(196).chr(144) => 'D',
			chr(196).chr(145) => 'd',
			chr(196).chr(146) => 'E',
			chr(196).chr(147) => 'e',
			chr(196).chr(148) => 'E',
			chr(196).chr(149) => 'e',
			chr(196).chr(150) => 'E',
			chr(196).chr(151) => 'e',
			chr(196).chr(152) => 'E',
			chr(196).chr(153) => 'e',
			chr(196).chr(154) => 'E',
			chr(196).chr(155) => 'e',
			chr(196).chr(156) => 'G',
			chr(196).chr(157) => 'g',
			chr(196).chr(158) => 'G',
			chr(196).chr(159) => 'g',
			chr(196).chr(160) => 'G',
			chr(196).chr(161) => 'g',
			chr(196).chr(162) => 'G',
			chr(196).chr(163) => 'g',
			chr(196).chr(164) => 'H',
			chr(196).chr(165) => 'h',
			chr(196).chr(166) => 'H',
			chr(196).chr(167) => 'h',
			chr(196).chr(168) => 'I',
			chr(196).chr(169) => 'i',
			chr(196).chr(170) => 'I',
			chr(196).chr(171) => 'i',
			chr(196).chr(172) => 'I',
			chr(196).chr(173) => 'i',
			chr(196).chr(174) => 'I',
			chr(196).chr(175) => 'i',
			chr(196).chr(176) => 'I',
			chr(196).chr(177) => 'i',
			chr(196).chr(178) => 'IJ',
			chr(196).chr(179) => 'ij',
			chr(196).chr(180) => 'J',
			chr(196).chr(181) => 'j',
			chr(196).chr(182) => 'K',
			chr(196).chr(183) => 'k',
			chr(196).chr(184) => 'k',
			chr(196).chr(185) => 'L',
			chr(196).chr(186) => 'l',
			chr(196).chr(187) => 'L',
			chr(196).chr(188) => 'l',
			chr(196).chr(189) => 'L',
			chr(196).chr(190) => 'l',
			chr(196).chr(191) => 'L',
			chr(197).chr(128) => 'l',
			chr(197).chr(129) => 'L',
			chr(197).chr(130) => 'l',
			chr(197).chr(131) => 'N',
			chr(197).chr(132) => 'n',
			chr(197).chr(133) => 'N',
			chr(197).chr(134) => 'n',
			chr(197).chr(135) => 'N',
			chr(197).chr(136) => 'n',
			chr(197).chr(137) => 'N',
			chr(197).chr(138) => 'n',
			chr(197).chr(139) => 'N',
			chr(197).chr(140) => 'O',
			chr(197).chr(141) => 'o',
			chr(197).chr(142) => 'O',
			chr(197).chr(143) => 'o',
			chr(197).chr(144) => 'O',
			chr(197).chr(145) => 'o',
			chr(197).chr(146) => 'OE',
			chr(197).chr(147) => 'oe',
			chr(197).chr(148) => 'R',
			chr(197).chr(149) => 'r',
			chr(197).chr(150) => 'R',
			chr(197).chr(151) => 'r',
			chr(197).chr(152) => 'R',
			chr(197).chr(153) => 'r',
			chr(197).chr(154) => 'S',
			chr(197).chr(155) => 's',
			chr(197).chr(156) => 'S',
			chr(197).chr(157) => 's',
			chr(197).chr(158) => 'S',
			chr(197).chr(159) => 's',
			chr(197).chr(160) => 'S',
			chr(197).chr(161) => 's',
			chr(197).chr(162) => 'T',
			chr(197).chr(163) => 't',
			chr(197).chr(164) => 'T',
			chr(197).chr(165) => 't',
			chr(197).chr(166) => 'T',
			chr(197).chr(167) => 't',
			chr(197).chr(168) => 'U',
			chr(197).chr(169) => 'u',
			chr(197).chr(170) => 'U',
			chr(197).chr(171) => 'u',
			chr(197).chr(172) => 'U',
			chr(197).chr(173) => 'u',
			chr(197).chr(174) => 'U',
			chr(197).chr(175) => 'u',
			chr(197).chr(176) => 'U',
			chr(197).chr(177) => 'u',
			chr(197).chr(178) => 'U',
			chr(197).chr(179) => 'u',
			chr(197).chr(180) => 'W',
			chr(197).chr(181) => 'w',
			chr(197).chr(182) => 'Y',
			chr(197).chr(183) => 'y',
			chr(197).chr(184) => 'Y',
			chr(197).chr(185) => 'Z',
			chr(197).chr(186) => 'z',
			chr(197).chr(187) => 'Z',
			chr(197).chr(188) => 'z',
			chr(197).chr(189) => 'Z',
			chr(197).chr(190) => 'z',
			chr(197).chr(191) => 's',
			// Euro Sign
			chr(226).chr(130).chr(172) => 'E'
		);
		$string = str_replace (array_keys ($utf8Conversions), array_keys ($utf8Conversions), $string);
		
		# Convert any remaining characters
		$string = preg_replace ('|[^a-z0-9-]|', '-', $string);
		
		# Replace double-hyphens
		while (substr_count ($string, '--')) {
			$string = str_replace ('--', '-', $string);
		}
		
		# Convert -s- (resulting from "'s ") to s-
		$string = str_replace ('-s-', 's-', $string);
		
		# Remove hyphens from the start or end
		$string = preg_replace ('/(^-|-$)/', '', $string);
		
		# Return the value
		return $string;
	}
	
	
	# Function create a zip/gzip file on-the-fly; see: http://stackoverflow.com/questions/1061710/
	public static function createZip ($inputFile /* Or, for zip format, an array of files, as array (asFilename => inputFile) */, $asFilename, $saveToDirectory = false /* or full directory path, slash-terminated */, $format = 'zip' /* or gz */)
	{
		# Prepare file, using a tempfile
		$tmpFile = tempnam (sys_get_temp_dir(), 'temp' . self::generatePassword ($length = 6, true));
		
		# Create depending on format
		switch ($format) {
			
			# Gzip; on Linux shell out as this is more memory efficient
			case 'gz':
				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
					#!# Needs to be replaced with chunk-based implementation for memory efficiency; see: http://stackoverflow.com/questions/6073397/how-do-you-create-a-gz-file-using-php
					$gzip = gzopen ($tmpFile, 'w9');	// w9 is highest compression
					gzwrite ($gzip, file_get_contents ($inputFile));	// #!# Will fail if input file is >2GB as that is max byte size of a PHP string
					gzclose ($gzip);
				} else {
					copy ($inputFile, $tmpFile);	// Clone file to temp area as gzipping will otherwise remove the original file
					$command = "gzip {$tmpFile}";
					exec ($command);
					rename ($tmpFile . '.gz', $tmpFile);	// Rename back, as gzip will have added .gz, so that the tempfile is at a predictable location
				}
				$mimeType = 'application/gzip';
				break;
				
			# Zip
			case 'zip':
				$zip = new ZipArchive ();
				$zip->open ($tmpFile, ZipArchive::OVERWRITE);
				if (is_array ($inputFile)) {
					foreach ($inputFile as $asFilenameEntry => $inputFileEntry) {
						$zip->addFile ($inputFileEntry, $asFilenameEntry);
					}
				} else {
					$zip->addFile ($inputFile, $asFilename);
				}
				$zip->close ();
				$mimeType = 'application/zip';
				break;
		}
		
		# If a directory path for save is specified, write the file to its final location, give it group writability and return its path, leaving it in place
		if ($saveToDirectory) {
			$filename = $saveToDirectory . $asFilename . '.' . $format;
			rename ($tmpFile, $filename);
			$originalUmask = umask (0000);
			chmod ($filename, 0664);
			umask ($originalUmask);
			return $filename;
		}
		
		# Serve the file
		header ('Content-Type: ' . $mimeType);
		header ('Content-Length: ' . filesize ($tmpFile));
		header ("Content-Disposition: attachment; filename=\"{$asFilename}.{$format}\"");		// e.g. filename.ext.zip
		readfile ($tmpFile);
		
		# Remove the tempfile
		unlink ($tmpFile);
	}
	
	
	# Function to unzip a zip file
	public static function unzip ($file, $directory, $deleteAfterUnzipping = true, $archiveOverwritableFiles = true, $expectTotal = false, $expectTotalForcedFilenames = array (), $directoryPermissions = 0775)
	{
		# Open the zip
		if (!$zip = @zip_open ($directory . $file)) {return false;}
		
		# Loop through each file
		$unzippedFiles = array ();
		while ($zipEntry = zip_read ($zip)) {
			if (!zip_entry_open ($zip, $zipEntry, 'r')) {continue;}
			
			# Read the contents
			$contents = zip_entry_read ($zipEntry, zip_entry_filesize ($zipEntry));
			
			# Determine the zip entry name
			$zipEntryName = zip_entry_name ($zipEntry);
			
			# Ensure the directory exists
			$targetDirectory = dirname ($directory . $zipEntryName) . '/';
			if (!is_dir ($targetDirectory)) {
				umask (0);
				if (!mkdir ($targetDirectory, $directoryPermissions, true)) {
					$deleteAfterUnzipping = false;	// Don't delete the source file if this fails
					continue;
				}
			}
			
			# Skip if the entry itself is a directory (the contained file will have a directory created for it)
			if (substr ($zipEntryName, -1) == '/') {continue;}
			
			# Archive (by appending a timestamp) an existing file if it exists and is different
			$filename = $directory . $zipEntryName;
			$originalIsRenamed = false;
			if ($archiveOverwritableFiles && file_exists ($filename)) {
				if (md5_file ($filename) != md5 ($contents)) {
					$timestamp = date ('Ymd-His');
					# Rename the file, altering the filename reference (using .= )
					$originalIsRenamed = $filename;
					#!# Error here - seems to rename the new rather than the original
					rename ($filename, $filename .= '.replaced-' . $timestamp);
				}
			}
			
			# (Over-)write the new file
			file_put_contents ($filename, $contents);
			
			# Close the zip entry
			zip_entry_close ($zipEntry);
			
			# Assign the files to the master array, emulating a native upload
			$baseFilename = basename ($filename);
			$unzippedFiles[$baseFilename] = array (
				'name' => $baseFilename,
				'type' => (function_exists ('finfo_file') ? finfo_file (finfo_open (FILEINFO_MIME), $filename) : NULL),	// finfo_file is unfortunately a replacement for mime_content_type which is now deprecated
				'tmp_name' => $file,
				'size' => filesize ($filename),
				'_location' => $filename,
			);
			
			# If the original has been renamed, add that
			if ($originalIsRenamed) {
				$unzippedFiles[$baseFilename]['original'] = basename ($originalIsRenamed);
			}
		}
		
		# Close the zip
		zip_close ($zip);
		
		# Return false if an expected total is required
		if ($expectTotal) {
			if (count ($unzippedFiles) != $expectTotal) {
				return false;
			}
		}
		
		# Delete the submitted file if required
		if ($deleteAfterUnzipping) {
			unlink ($directory . $file);
		}
		
		# Natsort by key
		$unzippedFiles = self::knatsort ($unzippedFiles);
		
		# Force the filenames if specified, in the list order
		if ($expectTotal && $expectTotalForcedFilenames) {
			if (is_string ($expectTotalForcedFilenames)) {
				$expectTotalForcedFilenames = array ($expectTotalForcedFilenames);
			}
			$i = 0;
			$unzippedFilesMoved = array ();
			foreach ($unzippedFiles as $unzippedFile => $attributes) {
				$newFilename = $expectTotalForcedFilenames[$i];
				$moveTo = $directory . $newFilename;
				rename ($attributes['_location'], $moveTo);
				$attributes['name'] = $newFilename;	// Overwrite the local name
				$attributes['_location'] = $moveTo;	// Overwrite the full path
				$unzippedFilesMoved[$newFilename] = $attributes;	// Register with the new filename as the key
				$i++;
			}
			$unzippedFiles = $unzippedFilesMoved;
		}
		
		# Sort and return the list of unzipped files
		return $unzippedFiles;
	}
	
	
	# Function to pluralise a signular word; currently only basic support
	public static function pluralise ($singularWord)
	{
		# Pluralise
		switch (true) {
			case preg_match ('/(.+)y$/', $singularWord, $matches):
				return $matches[1] . 'ies';
			case preg_match ('/(.+)s$/', $singularWord, $matches):
				return $matches[1] . "s'";
			default:
				return $singularWord . 's';
		}
	}
	
	
	# Function to singularise a plural word; currently only basic support
	public static function singularise ($pluralWord)
	{
		# Singularise
		switch (true) {
			case preg_match ('/(.+)ies$/', $pluralWord, $matches):
				return $matches[1] . 'y';
			case preg_match ('/(.+)s\'$/', $pluralWord, $matches):
				return $matches[1] . 's';
			case preg_match ('/(.+)s$/', $pluralWord, $matches):
				return $matches[1];
		}
		
		# Return unmodified if no match found
		return $pluralWord;
	}
	
	
	# Equivalent of file_get_contents but for POST rather than GET
	public static function file_post_contents ($url, $postData, $multipart = false, &$error = '', $userAgent = 'Proxy for: %HTTP_USER_AGENT')
	{
		# Define the user agent
		$userAgent = str_replace ('%HTTP_USER_AGENT', $_SERVER['HTTP_USER_AGENT'], $userAgent);
		
		# If not requiring multipart, avoid requirement for cURL
		if (!$multipart) {
			
			# Set the stream options
			$streamOptions = array (
				'http' => array (
					'method'		=> 'POST',
					'header'		=> 'Content-type: application/x-www-form-urlencoded',
					'user_agent'	=> $userAgent,
					'content'		=> http_build_query ($postData),
				)
			);
			
			# Post the data and return the result
			return file_get_contents ($url, false, stream_context_create ($streamOptions));
		}
		
		# Create a CURL instance
		$handle = curl_init ();
		curl_setopt ($handle, CURLOPT_URL, $url);
		
		# Set the user agent
		curl_setopt ($handle, CURLOPT_USERAGENT, $userAgent);
		
		# Build the POST query
		curl_setopt ($handle, CURLOPT_POST, 1);
		curl_setopt ($handle, CURLOPT_POSTFIELDS, $postData);
		
		# Obtain the original page HTML
		curl_setopt ($handle, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec ($handle);
		$error = curl_error ($handle);
		curl_close ($handle);
		
		# Return the original page HTML
		return $output;
	}
	
	
	# Function to convert an HTML extract to a PDF; uses http://wkhtmltopdf.org/
	public static function html2pdf ($html, $filename /* Either a filename used for temp download, or a trusted full path where the file will be saved; NB filename will be picked up by the browser if doing a save from an embedded PDF viewer */)
	{
		# Create the HTML as a tempfile
		$inputFile = tempnam (sys_get_temp_dir (), 'tmp') . '.html';	// wkhtmltopdf requires a .html extension for the input file
		file_put_contents ($inputFile, $html);
		
		# Determine whether to output the file; if this is a filename without a directory name, output to browser; if there is a directory path, treat as a save
		#!# Need to enable $filename to be false for a temporary file, e.g. by running $filename = application::generatePassword (20);
		$save = ($filename != basename ($filename));	// Determine if there is a directory component
		
		# Determine location of the PDF output file (which may be a tempfile)
		if ($save) {
			$outputFile = $filename;
		} else {
			$outputFile = tempnam (sys_get_temp_dir (), 'tmp');		// Define a tempfile location for the created PDF
		}
		
		# Convert to PDF; see options at http://wkhtmltopdf.org/usage/wkhtmltopdf.txt
		$command = "wkhtmltopdf --encoding 'utf-8' --print-media-type {$inputFile} {$outputFile}";
		exec ($command, $output, $returnValue);
		$result = (!$returnValue);
		
		# Remove the input HTML tempfile
		unlink ($inputFile);
		
		# End if error
		if (!$result) {
			if (file_exists ($outputFile)) {
				unlink ($outputFile);
			}
			echo "\n<p class=\"warning\">Sorry, an error occurred creating the PDF file.</p>";
			return false;
		}
		
		# Deal with on-the-fly distribution scenario
		if (!$save) {
			
			# Send browser headers
			header ('Content-type: application/pdf');
			//header ('Content-Transfer-Encoding: binary');
			header ('Content-Disposition: inline; filename="' . $filename . '"');
			header ('Content-Length: ' . filesize ($outputFile));
			
			# Emit the file, using output buffering to avoid any previous HTML output (e.g. from auto_prepend_file) being included
			ob_clean ();
			flush ();
			readfile ($outputFile);
			
			# Remove the output PDF tempfile
			unlink ($outputFile);
		}
		
		# Return success
		return true;
	}
	
	
	# Function to provide spell-checking of a dataset and provide alternatives
	# Package dependencies: php5-enchant hunspell-ru
	public static function spellcheck ($strings, $languageTag, $protectedSubstringsRegexp = false, $databaseConnection = false /* for caching */, $database = false, $enableSuggestions = true, $addToDictionary = array ())
	{
		# Prevent timeouts for large datasets
		if (count ($strings) > 50) {
			set_time_limit (0);
		}
		
		# Initialise the spellchecker
		$r = enchant_broker_init ();
		// application::dumpData (enchant_broker_describe ($r));	// List available backends
		// application::dumpData (enchant_broker_list_dicts ($r));	// List available dictionaries; should have "ru_RU": "Myspell Provider" present (which seems to be the same thing as hunspell)
		if (!enchant_broker_dict_exists ($r, $languageTag)) {
			echo "<p class=\"warning\">The spell-checker could not be initialised.</p>";
			return $strings;
		}
		$d = enchant_broker_request_dict ($r, $languageTag);
		
		# If additional words to add to the dictionary are specified, add them to this session
		if ($addToDictionary) {
			foreach ($addToDictionary as $word) {
				enchant_dict_add_to_session ($d, $word);
			}
		}
		
		# Use a database cache if required
		$cache = array ();
		if ($databaseConnection) {
			
			# Initialise a cache table; this can be persistent across imports
			$sql = "
				CREATE TABLE IF NOT EXISTS spellcheckcache (
				`id` VARCHAR(255) COLLATE utf8_bin NOT NULL COMMENT 'Word',		/* utf8_bin needed to ensure case-sensitivity in a unique column */
				`isCorrect` INT(1) NULL COMMENT 'Whether the word is correct',
				`suggestions` VARCHAR(255) COLLATE utf8_unicode_ci NULL COMMENT 'Suggestions, pipe-separated',
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Spellcheck cache';";
			$databaseConnection->execute ($sql);
			
			# Load the cache
			require_once ('database.php');
			$cache = $databaseConnection->select ($database, 'spellcheckcache');
			$originalCacheSize = count ($cache);
		}
		
		# Loop through each record
		foreach ($strings as $id => $string) {
			
			# Branch
			$relevantString = $string;
			
			# If substring protection is required, strip from consideration
			if ($protectedSubstringsRegexp) {
				$relevantString = preg_replace ('/' . addcslashes ($protectedSubstringsRegexp, '/') . '/', '', $relevantString);
			}
			
			# Strip punctuation characters connected to word boundaries
			$relevantString = preg_replace ("/(^)\p{P}/u", '\1', $relevantString);
			$relevantString = preg_replace ("/(\s)\p{P}/u", '\1', $relevantString);
			$relevantString = preg_replace ("/\p{P}(\s)/u", '\1', $relevantString);
			$relevantString = preg_replace ("/\p{P}($)/u", '\1', $relevantString);
			
			# Extract words from the string words, splitting by whitespace
			$words = preg_split ('/\s+/', trim ($relevantString), -1, PREG_SPLIT_NO_EMPTY);
			
			# Work through each word and attach to the main data
			$substitutions = array ();
			foreach ($words as $word) {
				
				# Skip where the 'word' is just a punctuation mark or number / number range
				if (preg_match ('/^([-:;()0-9])+$/', $word)) {continue;}
				
				# Initialise the cache container for this entry if not already present; the cache is indexed by word, to avoid unnecessary calls to enchant_dict_check/enchant_dict_suggest
				if (!isSet ($cache[$word])) {$cache[$word] = array ('id' => $word);}	// id passed through in structure field makes databasing the cache easier
				
				# Determine if the word is correct
				if (isSet ($cache[$word]['isCorrect'])) {
					$isCorrect = $cache[$word]['isCorrect'];	// Read from cache if present
				} else {
					$isCorrect = enchant_dict_check ($d, $word);
					$cache[$word]['isCorrect'] = ($isCorrect ? '1' : '0');	// Add to cache; cast boolean as numeric for INT(1) storage
					if ($isCorrect) {
						$cache[$word]['suggestions'] = NULL;	// Since the $enableSuggestions phase will not be reached, leaving holes in some array entries
					}
				}
				
				# Skip further processing if correct
				if ($isCorrect) {continue;}
				
				# Find alternative suggestions
				$suggestions = false;
				if ($enableSuggestions) {
					
					# Determine suggestions
					if (isSet ($cache[$word]['suggestions'])) {
						$suggestions = $cache[$word]['suggestions'];	// Read from cache if present
					} else {
						$suggestions = enchant_dict_suggest ($d, $word);	// Returns either array of values or NULL if no values
						if ($suggestions) {
							$suggestions = implode ('|', $suggestions);		// Convert to string before any use; pipe-separator used for optimal database storage; later unpacked for presentation by $suggestionsImplodeString
						}
						$cache[$word]['suggestions'] = $suggestions;	// Add to cache, either string or NULL
					}
					
					# Format
					$suggestionsImplodeString = '&#10;';
					$suggestions = ($suggestions ? 'Suggestions:' . $suggestionsImplodeString . implode ($suggestionsImplodeString, explode ('|', $suggestions)) : '[No suggestions]');
				}
				
				# Highlight in HTML the present word and add suggestions
				$substitutions[$word] = '<span class="spelling"' . ($suggestions ? " title=\"{$suggestions}\"" : '') . ">{$word}</span>";
			}
			
			# Overwrite with the spellchecked HTML version
			$strings[$id] = strtr ($string, $substitutions);	// 'The longest keys will be tried first.' - http://php.net/strtr ; also seems to be multibyte-safe
		}
		
		# If database caching is enabled, replace the database's cache with the new cache dataset if it has grown
		if ($databaseConnection) {
			if (count ($cache) > $originalCacheSize) {
				$databaseConnection->truncate ($database, 'spellcheckcache', true);
				$databaseConnection->insertMany ($database, 'spellcheckcache', array_values ($cache), $chunking = 500);		// Use of array_values avoids bound parameter naming problems
			}
		}
		
		# Unload the dictionary
		enchant_broker_free_dict ($d);
		enchant_broker_free ($r);
		
		# Return the modified list of strings
		return $strings;
	}
	
	
	
	# Function to convert a number to a Roman numeral; see: http://php.net/base-convert#92960
	public static function romanNumeral ($integer)
	{
		# Convert the number
		$table = array ('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
		$result = '';
		while ($integer > 0) {
			foreach ($table as $roman => $arabic) {
				if ($integer >= $arabic) {
					$result .= $roman;
					$integer -= $arabic;
					break;
				}
			}
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to convert a Roman numeral to an integer; see: https://stackoverflow.com/a/6266158/180733
	public static function romanNumeralToInt ($romanNumeralString)
	{
		# Define roman numeral combinations, in precedence order
		$romans = array(
		    'M'		=> 1000,
		    'CM'	=> 900,
		    'D'		=> 500,
		    'CD'	=> 400,
		    'C'		=> 100,
		    'XC'	=> 90,
		    'L'		=> 50,
		    'XL'	=> 40,
		    'X'		=> 10,
		    'IX'	=> 9,
		    'V'		=> 5,
		    'IV'	=> 4,
		    'I'		=> 1,
		);
		
		# Work from the left and increment the result
		$result = 0;
		foreach ($romans as $key => $value) {
		    while (strpos ($romanNumeralString, $key) === 0) {
		        $result += $value;
		        $romanNumeralString = substr ($romanNumeralString, strlen ($key));
		    }
		}
		
		# Return the result
		return $result;
	}
	
	
	# Function to handle running a command process securely without writing out any files
	public static function createProcess ($command, $string)
	{
		# Set the descriptors
		$descriptorspec = array (
			0 => array ('pipe', 'r'),  // stdin is a pipe that the child will read from
			1 => array ('pipe', 'w'),  // stdout is a pipe that the child will write to
			// 2 => array ('file', '/tmp/error-output.txt', 'a'), // stderr is a file to write to - uncomment this line for debugging
		);
		
		# Assume failure unless the command works
		$returnStatus = 1;
		
		# Create the process
		$command = str_replace ("\r\n", "\n", $command);	// Standardise to Unix newlines
		$process = proc_open ($command, $descriptorspec, $pipes);
		if (is_resource ($process)) {
			fwrite ($pipes[0], $string);
			fclose ($pipes[0]);
			$output = stream_get_contents ($pipes[1]);
			fclose ($pipes[1]);
			$returnStatus = proc_close ($process);
		}
		
		# Return false as the output if the return status is a failure
		if ($returnStatus) {return false;}	// Unix return status >0 is failure
		
		# Return the output
		return $output;
	}
	
	
	# Function to create a jumplist form
	#!# Needs support for nested lists
	public static function htmlJumplist ($values /* will have htmlspecialchars applied to both keys and values */, $selected = '', $action = '', $name = 'jumplist', $parentTabLevel = 0, $class = 'jumplist', $introductoryText = 'Go to:', $valueSubstitution = false, $onchangeJavascript = true)
	{
		# Return an empty string if no items
		if (empty ($values)) {return '';}
		
		# Prepare the tab string
		$tabs = str_repeat ("\t", ($parentTabLevel));
		
		# Build the list; note that the visible value can never have tags within (e.g. <span>): https://stackoverflow.com/questions/5678760
		foreach ($values as $value => $visible) {
			$fragments[] = '<option value="' . ($valueSubstitution ? str_replace ('%value', htmlspecialchars ($value), $valueSubstitution) : htmlspecialchars ($value)) . '"' . ($value == $selected ? ' selected="selected"' : '') . '>' . htmlspecialchars ($visible) . '</option>';
		}
		
		# Construct the HTML
		$html  = "\n\n$tabs" . "<div class=\"$class\">";
		$html .= "\n\n$tabs" . $introductoryText;
		$html .= "\n$tabs\t" . '<form method="post" action="' . htmlspecialchars ($action) . "\" name=\"$name\">";
		$html .= "\n$tabs\t\t" . "<select name=\"$name\"" . ($onchangeJavascript ? ' onchange="window.location.href/*stopBots*/=this[selectedIndex].value"' : '') . '>';	// The inline 'stopBots' javascript comment is an attempt to stop rogue bots picking up the "href=" text
		$html .= "\n$tabs\t\t\t" . implode ("\n$tabs\t\t\t", $fragments);
		$html .= "\n$tabs\t\t" . '</select>';
		$html .= "\n$tabs\t\t" . '<noscript><input type="submit" value="Go!" class="button" /></noscript>';
		$html .= "\n$tabs\t" . '</form>';
		$html .= "\n$tabs" . '</div>' . "\n";
		
		# If posted, jump, adding the current site's URL if the target doesn't start with http(s)://
		if (isSet ($_POST[$name])) {
			$location = (preg_match ('~(http|https)://~i', $_POST[$name]) ? '' : $_SERVER['_SITE_URL']) . $_POST[$name];
			$html = self::sendHeader (302, $location, $redirectMessage = true);
		}
		
		# Return the result
		return $html;
	}
	
	
	# Function to convert ereg to preg
	public static function pereg ($pattern, $string)
	{
		$preg = '/' . addcslashes ($pattern, '/') . '/';
		return preg_match ($preg, $string);
	}
	
	
	# Function to convert eregi to preg
	public static function peregi ($pattern, $string)
	{
		$preg = '/' . addcslashes ($pattern, '/') . '/i';
		return preg_match ($preg, $string);
	}
	
	
	# Function to convert eregi_replace to preg_replace
	public static function peregi_replace ($pattern, $replace, $string)
	{
		// $pattern = str_replace ('[[:space:]]', '\s', $pattern);
		$preg = '/' . addcslashes ($pattern, '/') . '/i';
		$preplace = preg_replace ('/\x5c(\d)/', '\$$1', $replace);	// \x5c is a backslash and \d is number, i.e. \2 gets converted to $2
		return preg_replace ($preg, $preplace, $string);
	}
}



# Define an emulated mime_content_type function (if not using Windows) - taken from http://cvs.php.net/viewvc.cgi/pear/PHP_Compat/Compat/Function/mime_content_type.php?revision=1.6&view=markup
if (!function_exists ('mime_content_type') && (!strstr (PHP_OS, 'WIN')))
{
	function mime_content_type ($filename)
	{
	    // Sanity check
	    if (!file_exists($filename)) {
	        return false;
	    }
	
	    $filename = escapeshellarg($filename);
	    $out = `file -iL $filename 2>/dev/null`;
	    if (empty($out)) {
	        return 'application/octet-stream';
	    }
	
	    // Strip off filename
	    $t = substr($out, strpos($out, ':') + 2);
	
	    if (strpos($t, ';') !== false) {
	        // Strip MIME parameters
	        $t = substr($t, 0, strpos($t, ';'));
	    }
	
	    // Strip any remaining whitespace
	    return trim($t);
	}
}


# Polyfill for str_contains (natively available from PHP 8.0); see: https://php.watch/versions/8.0/str_contains
if (!function_exists ('str_contains')) {
    function str_contains (string $haystack, string $needle)
	{
        return (('' === $needle) || (false !== strpos ($haystack, $needle)));
    }
}

# Emulation of mb_strtolower for UTF-8 compliance (natively available if Multibyte String extension installed); based on http://www.php.net/strtolower#90871
if (!function_exists ('mb_strtolower'))
{
	function mb_strtolower ($string, $encoding)
	{
		return utf8_encode (strtolower (utf8_decode ($string)));
	}
}


# Missing mb_ucfirst function; based on http://www.php.net/ucfirst#84122
if (!function_exists ('mb_ucfirst')) {
	if (function_exists ('mb_substr')) {
		function mb_ucfirst ($string) {
			return mb_strtoupper (mb_substr ($string, 0, 1)) . mb_substr ($string, 1);
		}
	}
}

# Missing mb_str_split function (natively available from PHP 7.4 if Multibyte String extension installed); based on http://php.net/str-split#117112
if (!function_exists ('mb_str_split')) {
    if (function_exists ('mb_substr')) {
		function mb_str_split ($string, $split_length = 1)
	    {
	        if ($split_length == 1) {
	            return preg_split ("//u", $string, -1, PREG_SPLIT_NO_EMPTY);
	        } elseif ($split_length > 1) {
	            $return_value = [];
	            $string_length = mb_strlen ($string, 'UTF-8');
	            for ($i = 0; $i < $string_length; $i += $split_length) {
	                $return_value[] = mb_substr ($string, $i, $split_length, "UTF-8");
	            }
	            return $return_value;
	        } else {
	            return false;
	        }
	    }
	}
}

?>
