<?php

/*
 * Coding copyright Martin Lucas-Smith, University of Cambridge, 2003-21
 * Version 1.12.4
 * Distributed under the terms of the GNU Public Licence - www.gnu.org/copyleft/gpl.html
 * Requires PHP 5.3
 * Download latest from: https://download.geog.cam.ac.uk/projects/purecontent/
 */


# Clean the server globals: this is the ONE exception to the rule that a library should not run things at top-level
pureContent::cleanServerGlobals ();


# Define a class containing website generation static methods
class pureContent {
	
	# Function to clean and standardise server-generated globals
	public static function cleanServerGlobals ($directoryIndex = 'index.html')
	{
		# Assign the server root path, non-slash terminated
		$_SERVER['DOCUMENT_ROOT'] = ((substr ($_SERVER['DOCUMENT_ROOT'], -1) == '/') ? substr ($_SERVER['DOCUMENT_ROOT'], 0, -1) : $_SERVER['DOCUMENT_ROOT']);
		
		# Assign the server root path
		// $_SERVER['SCRIPT_FILENAME'];
		
		# Assign the domain name
		if (!isSet ($_SERVER['SERVER_NAME'])) {$_SERVER['SERVER_NAME'] = 'localhost';}	// Emulation for CGI/CLI mode
		
		# Assign the page location (i.e. the actual script opened), with index.html removed if it exists, starting from root
		$_SERVER['PHP_SELF'] = preg_replace ('~' . '/' . preg_quote ($directoryIndex) . '$' . '~', '/', $_SERVER['PHP_SELF']);
		$_SERVER['SCRIPT_NAME'] = preg_replace ('~' . '/' . preg_quote ($directoryIndex) . '$' . '~', '/', $_SERVER['SCRIPT_NAME']);
		
		# Assign the page location (i.e. the page address requested) with query, removing double-slashes and the directory index
		if (!isSet ($_SERVER['REQUEST_URI'])) {$_SERVER['REQUEST_URI'] = preg_replace ('/^' . preg_quote ($_SERVER['DOCUMENT_ROOT'], '/') . '/', '', $_SERVER['SCRIPT_FILENAME']);}	// Emulation for CGI/CLI mode
		$parts = explode ('?', $_SERVER['REQUEST_URI'], 2);	// Break off the query string so that we can make double-slash replacements safely, before reassembling
		$currentPath = preg_replace ('~' . '/' . preg_quote ($directoryIndex) . '$' . '~', '/', $parts[0]);
		while (strpos ($currentPath, '//') !== false) {$currentPath = str_replace ('//', '/', $currentPath);}
		$_SERVER['REQUEST_URI'] = $currentPath;
		if (isSet ($parts[1])) {$_SERVER['REQUEST_URI'] .= '?' . $parts[1];}	// Reinstate the query string
		
		# Assign the current server protocol type and version
		if (!isSet ($_SERVER['SERVER_PROTOCOL'])) {$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';}	// Emulation for CGI-CLI mode
		list ($protocolType, $_SERVER['_SERVER_PROTOCOL_VERSION']) = explode ('/', $_SERVER['SERVER_PROTOCOL']);
		$_SERVER['_SERVER_PROTOCOL_TYPE'] = ((isSet ($_SERVER['HTTPS']) && (strtolower ($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http');
		
		# Assign the site URL
		$_SERVER['_SITE_URL'] = $_SERVER['_SERVER_PROTOCOL_TYPE'] . '://' . $_SERVER['SERVER_NAME'];
		
		# Assign the complete page URL (i.e. the full page address requested), with index.html removed if it exists, starting from root
		if (!isSet ($_SERVER['SERVER_PORT'])) {$_SERVER['SERVER_PORT'] = 80;}	// Emulation for CGI/CLI mode
		$_SERVER['_PAGE_URL'] = $_SERVER['_SITE_URL'] . (($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) ? ':' . $_SERVER['SERVER_PORT'] : '') . $_SERVER['REQUEST_URI'];
		
		# Ensure SCRIPT_URL is present
		if (!isSet ($_SERVER['SCRIPT_URL'])) {
			$_SERVER['SCRIPT_URL'] = $parts[0];
		}
		
		# Assign the query string (for the few cases, e.g. a 404, where a REDIRECT_QUERY_STRING is generated instead
		$_SERVER['QUERY_STRING'] = (isSet ($_SERVER['REDIRECT_QUERY_STRING']) ? $_SERVER['REDIRECT_QUERY_STRING'] : (isSet ($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''));
		
		# Assign the referring page
		$_SERVER['HTTP_REFERER'] = (isSet ($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		
		# Assign the user's IP address
		// $_SERVER['REMOTE_ADDR'];
		
		# Assign the username
		$_SERVER['REMOTE_USER'] = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : (isSet ($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : NULL));
		
		# Assign the user's browser
		$_SERVER['HTTP_USER_AGENT'] = (isSet ($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
	}
	
	
	/**
	 * Creates a navigation (breadcrumb) trail, assign a browser title and the correct menu, based on the present URL
	 *
	 * @param string $dividingTextOnPage		// The text which goes between each link on the page
	 * @param string $dividingTextInBrowserLine	// The text between each link in the browser title bar; examples are &raquo; | || -  &#187; ; note that \ / < > shouldn't be used (they won't be bookmarked on Windows)
	 * @param string $introductoryText			// What appears at the start of the line
	 * @param string $homeText					// Text for the first link: to the home page
	 * @param bool $enforceStrictBehaviour		// Whether to allow missing title files within the hierarchy
	 * @param bool $browserlineFullHierarchy	// Whether to show the whole hierarchy or only the last one in the browser title bar
	 * @param string $homeLocation				// The location of the home page (the first link) starting from / [trailing slash here is optional]
	 * @param string $sectionTitleFile			// The filename for the section information placed in each directory
	 * @param string $menuTitleFile				// The filename for the submenu placed in each top-level directory
	 */
	public static function assignNavigation ($dividingTextOnPage = ' &#187; ', $dividingTextInBrowserLine = ' &#187; ', $introductoryText = 'You are in:  ', $homeText = 'Home', $enforceStrictBehaviour = false, $browserlineFullHierarchy = false, $homeLocation = '/', $sectionTitleFile = '.title.txt', $menuTitleFile = '.menu.html', $tildeRoot = '/home/', $behaviouralHackFile = '/sitetech/assignNavigationHack.html', $linkToCurrent = false)
	{
		# Start an array of the navigation hierarchy
		$navigationHierarchy = array ();
		
		# Ensure the home location and tilde root ends with a trailing slash
		if (substr ($homeLocation, -1) != '/') {$homeLocation .= '/';}
		if (substr ($tildeRoot, -1) != '/') {$tildeRoot .= '/';}
		
		# Clean up the current page location
		$currentPath = preg_replace ('/' . '^' . preg_quote ($homeLocation, '/') . '/', '', $_SERVER['REQUEST_URI']);
		$currentPath = str_replace ('../', '', $currentPath);
		while (strpos ($currentPath, '//') !== false) {$currentPath = str_replace ('//', '/', $currentPath);}
		
		# Create an array of the subdirectories of it, chopping off the last item (something.html or empty)
		$subdirectories = explode ('/', $currentPath);
		array_pop ($subdirectories);
		
		# Set a flag for being a tilde site
		$tildeSite = (substr ($homeLocation, 0, 2) == '/~');
		
		# Set the root of the site
		$serverRoot = (!$tildeSite ? $_SERVER['DOCUMENT_ROOT'] : $tildeRoot . substr ($homeLocation, 2) . 'public_html/');
		
		# If there are no subdirectories, assign the results immediately
		if (empty ($subdirectories)) {
			$browserline = '';
			$locationline = '&nbsp;';
			$menusection = '';
			$menufile = '';
		} else {
			
			# Start the location line and browserline
			$locationline = str_replace ('  ', '&nbsp; ', $introductoryText) . "<a href=\"$homeLocation\">$homeText</a>";
			$navigationHierarchy[$homeLocation] = $homeText;
			$browserline = '';
			
			# Assign the starting point for the links
			$link = (!$tildeSite ? $homeLocation : '');
			
			# Go through each subdirectory and assign the text and link
			foreach ($subdirectories as $subdirectory) {
				
				# Prepend the previous subdirectory and append a trailing slash to make the link
				$link .= $subdirectory . '/';
				
				# Extract the text from the 'title' file
				$filename = $serverRoot . $link . $sectionTitleFile;
				
				# Check whether the file exists; stop the loop if strict hierarchy mode is on
				if (!is_readable ($filename)) {
					if ($enforceStrictBehaviour) {break;}
				} else {
					
					# Obtain the contents of the file
					$contents = file_get_contents ($filename);
					
					# Trim white space and convert HTML entities
					$contents = htmlspecialchars (trim ($contents));
					
					# Build up the text and links in the location line, preceeded by the dividing text, adding a link unless on the current page and linkToCurrent being off
					$target = ($tildeSite ? $homeLocation : '') . $link;
					$locationline .= $dividingTextOnPage . (!$linkToCurrent && ($target == $_SERVER['REQUEST_URI']) ? $contents : '<a href="' . $target . '">' . $contents . '</a>');
					
					# Build up the text for the browser title
					$browserline = ($browserlineFullHierarchy ? $browserline : '') . $dividingTextInBrowserLine . $contents;
					
					# Allow the behaviour to be overridden by including a behavioural hack file
					if ($behaviouralHackFile && file_exists ($_SERVER['DOCUMENT_ROOT'] . $behaviouralHackFile)) {
						include ($_SERVER['DOCUMENT_ROOT'] . $behaviouralHackFile);
					}
					
					# Add navigation hierarchy item
					$navigationHierarchy[$link] = $contents;
				}
			}
			
			# $menusection which is used for showing the correct menu, stripping off the trailing slash in it
			$menusection = $subdirectories[0];
			$menufile = $serverRoot . $homeLocation . $menusection . '/' . $menuTitleFile;
		}
		
		# Return the properties
		return array ($browserline, $locationline, $menusection, $menufile, $navigationHierarchy);
	}
	
	
	# Define a function to generate the menu
	public static function generateMenu ($menu, $cssSelected = 'selected', $parentTabLevel = 2, $orphanedDirectories = array (), $menufile = '', $id = NULL, $class = NULL, $returnNotEcho = false, $addSubmenuClass = false, $submenuDuplicateFirstLink = false)
	{
		# Start the HTML
		$html  = '';
		
		# Ensure the orphanedDirectories supplied is an array
		if (!is_array ($orphanedDirectories)) {$orphanedDirectories = array ();}
		
		# Loop through each menu item to match the starting location but take account of lower-level subdirectories override higher-level directories
		$match = '';
		foreach ($menu as $location => $description) {
			if ($location == '/') {continue;}	// Do not permit matching of / at this stage
			if (($location == (substr ($_SERVER['REQUEST_URI'], 0, strlen ($location)))) && (strlen ($location) > strlen ($match))) {
				$match = $location;
			}
		}
		
		# If no match has been found, check whether the requested page is an orphaned directory (i.e. has no menu item)
		if (!$match) {
			foreach ($orphanedDirectories as $orphanedDirectory => $orphanAssignment) {
				if (($orphanedDirectory == (substr ($_SERVER['REQUEST_URI'], 0, strlen ($orphanedDirectory)))) && (strlen ($orphanedDirectory) > strlen ($match))) {
					$match = $orphanAssignment;
				}
			}
		}
		
		# If still no match, and / is present, permit that
		if (!$match) {
			if (isSet ($menu['/'])) {
				$match = '/';
			}
		}
		
		# Define the starting tab level
		$tabs = str_repeat ("\t", ($parentTabLevel));
		
		# Create the HTML
		$ulStart = "\n$tabs<ul" . ($id ? " id=\"{$id}\"" : '') . ($class ? " class=\"{$class}\"" : '') . '>';
		if ($returnNotEcho) {$html .= $ulStart;} else {echo $ulStart;}	// Has to be done this way due to the include() below
		$spaced = false;
		foreach ($menu as $location => $description) {
			
			# Set the spacer flag if necessary
			if ((substr ($location, 0, 6) == 'spacer') && ($description == '_')) {
				$spaced = true;
				continue;
			}
			
			# Show the link
			$class = str_replace (array ('/', ':', '.'), array ('', '-', '-'), $location);
			$liStart = "\n$tabs\t" . '<li class="' . $class . ($match == $location ? " $cssSelected" : '') . (($spaced) ? ' spaced' : '') . "\"><a class=\"{$class}\" href=\"$location\">$description</a>";
			if ($returnNotEcho) {$html .= $liStart;} else {echo $liStart;}
			
			# Reset the spacer flag
			$spaced = false;
			
			# Include the menu file
			if (!empty ($menufile)) {
				if ($match == $location || $menufile == '*') {
					#!# Hacked in 060222 - deals with non-top level sections like /foo/bar/ but hard-codes .menu.html ... ; arguably this is a more sensible system though, and avoids passing menu file along a chain
					$menufileFilename = $_SERVER['DOCUMENT_ROOT'] . $location . '/.menu.html';
					if (file_exists ($menufileFilename)) {
						if ($returnNotEcho) {
							$menuFileHtml = file_get_contents ($menufileFilename);
							if ($submenuDuplicateFirstLink) {
								$menuFileHtml = str_replace ('<ul>', '<ul>' . "\n\t<li><a href=\"{$location}\">" . $description . (is_string ($submenuDuplicateFirstLink) ? ' ' . $submenuDuplicateFirstLink : '') . '</a></li>', $menuFileHtml);
							}
							if ($addSubmenuClass) {
								$menuFileHtml = str_replace ('<ul>', "<ul class=\"{$addSubmenuClass}\">", $menuFileHtml);
							}
							$html .= $menuFileHtml;
						} else {
							include ($menufileFilename);
						}
					}
				}
			}
			
			# End the menu item
			$liEnd = '</li>';
			if ($returnNotEcho) {$html .= $liEnd;} else {echo $liEnd;}
		}
		$ulEnd = "\n$tabs</ul>";
		if ($returnNotEcho) {$html .= $ulEnd;} else {echo $ulEnd;}
		
		# Return the HTML if required
		if ($returnNotEcho) {
			return $html;
		}
	}
	
	
	# Function to process an HTML (not PHP) submenu to add an 'active' class
	public static function processHtmlSubmenu ($menufile, $cssSelected = 'selected', $parentTabLevel = 2, $orphanedDirectories = array ())
	{
		# Read the contents of the file
		$html = file_get_contents ($menufile);
		
		# Strip out comments first, so that commented-out items do not reappear
		$html = preg_replace ('/<!--(.*)-->/Uis', '', $html);
		
		# Parse the contents
		if (preg_match ('@^(.*)(<ul[^>]+>)(.+)(</ul>)(.*)$@s', $html, $matches)) {
			
			# Get the individual list items
			if (preg_match_all ('@<li><a href="([^"]+)">(.+)</a></li>@sU', $matches[3], $listItems, PREG_SET_ORDER)) {
				
				# Re-construct into a fresh list
				$menu = array ();
				foreach ($listItems as $listItem) {
					$url = $listItem[1];
					$menu[$url] = $listItem[2];
				}
				
				# Get the ID and class, if any, from the original <ul> tag
				$id = NULL;
				if (preg_match ('/id="([^"]+)"/', $matches[2], $idMatches)) {
					$id = $idMatches[1];
				}
				$class = NULL;
				if (preg_match ('/class="([^"]+)"/', $matches[2], $classMatches)) {
					$class = $classMatches[1];
				}
				
				# Generate the menu
				$menu = self::generateMenu ($menu, $cssSelected, $parentTabLevel, $orphanedDirectories, '', $id, $class, $return = true);
				
				# Reconstruct the HTML
				$html  = $matches[1];	// Any HTML before <ul>
				$html .= $menu;
				$html .= $matches[5];	// Any HTML after <ul>
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create an id and class for the body tag, useful for CSS selectors
	public static function bodyAttributes ($addSiteUrl = true, $additionalClass = false)
	{
		# The REQUEST_URI will have been cleaned by pureContent::cleanServerGlobals already to implode // into /
		// No action
		
		# Start with the site URL if wanted
		$bodyAttributes  = ($addSiteUrl ? ' id="' . htmlspecialchars (self::bodyAttributesId ()) . '"' : '');
		
		# Add the class
		$class  = self::bodyAttributesClass ();
		if ($additionalClass) {
			$class .= ($class ? ' ' : '') . $additionalClass;
		}
		$bodyAttributes .= ' class="' . htmlspecialchars ($class) . '"';
		
		# Return the compiled string
		return $bodyAttributes;
	}
	
	
	# Function to obtain id for bodyAttributes
	public static function bodyAttributesId ()
	{
		return str_replace ('.', '-', $_SERVER['SERVER_NAME']);
	}
	
	
	# Function to obtain class for bodyAttributes
	public static function bodyAttributesClass ()
	{
		# Return 'home' if no subdirectory
		if (substr_count ($_SERVER['SCRIPT_URL'], '/') < 2) {return 'homepage';}
		
		# Split the URL into pieces, and remove the blank start and the .html (or empty) end
		$urlParts = explode ('/', $_SERVER['SCRIPT_URL']);	// which will not have the query string
		array_pop ($urlParts);
		array_shift ($urlParts);
		
		# Return the first as well as the constructed string if there are more than one
		return ((count ($urlParts) > 1) ? $urlParts[0] . ' ' : '') . implode ('-', $urlParts);
	}
	
	
	# Function to provide an edit link if using pureContentEditor
	public static function editLink ($internalHostRegexp /* regexp for host, or bool to show/hide */, $port = 8080, $class = 'editlink', $tag = 'p')
	{
		# End if web application area
		if (isSet ($_SERVER['PURECONTENT_EDITING_DISABLED'])) {return false;}
		
		# Determine matching visibility
		if (is_string ($internalHostRegexp)) {
			$isVisible = preg_match ('/' . addcslashes ($internalHostRegexp, '/') . '/', gethostbyaddr ($_SERVER['REMOTE_ADDR'])) || isSet ($_COOKIE['purecontenteditorlink']);
		} else /* i.e. is bool */ {
			$isVisible = ($internalHostRegexp);
		}
		
		# If the host/user matches and the port is not the edit port, give a link
		if ($isVisible) {
			if ($_SERVER['SERVER_PORT'] != $port) {
				$url = "https://{$_SERVER['SERVER_NAME']}:{$port}" . htmlspecialchars ($_SERVER['REQUEST_URI']) . '?edit';
				$label = 'Edit page';
				if (isSet ($_SERVER['PURECONTENT_EDITING_WORDPRESS'])) {
					$url = $_SERVER['PURECONTENT_EDITING_WORDPRESS'] . 'login';
					$label = 'Wordpress editor';
				}
				return "<{$tag} class=\"" . ($class ? "{$class} " : '') . "noprint\"><a href=\"" . $url . '" title="Switch to the editing side of the website"><img src="/images/icons/page_edit.png" class="icon" /> ' . $label . "</a></{$tag}>";
			} else {
				return "<{$tag} class=\"" . ($class ? "{$class} " : '') . "noprint\"><a href=\"https://{$_SERVER['SERVER_NAME']}" . htmlspecialchars ($_SERVER['SCRIPT_NAME']) /* i.e. without query string */ . "\" title=\"Switch back to the live, public side of the website\">[Return to live]</a></{$tag}>";
			}
		}
		
		# Otherwise return an empty string
		return '';
	}
	
	
	# Function to provide an SSO link area
	public static function ssoLinks ($ssoBrandName = false, $profileUrl = false, $profileName = 'My profile', $superusersSwitching = array (), $internalHostRegexp = false)
	{
		# End if SSO not enabled (defined and equal to 1, in the server environment)
		if (!isSet ($_SERVER['SINGLE_SIGN_ON_ENABLED']) || !$_SERVER['SINGLE_SIGN_ON_ENABLED']) {return false;}
		
		# Set to show the links by default
		$showLinks = true;
		
		# If limited by host, do not show by default but enable if matching
		if ($internalHostRegexp) {
			$showLinks = false;		// Disable by default
			if (preg_match ('/' . addcslashes ($internalHostRegexp, '/') . '/', gethostbyaddr ($_SERVER['REMOTE_ADDR']))) {
				$showLinks = true;	// Show if matched
			}
		}
		
		# Check for a cookie, representing an internal user who has previously authenticated; this takes precedence over internal host checks
		$cookieName = 'showssolinks';
		$hasCookieRenewAt = false;
		if (isSet ($_COOKIE[$cookieName])) {
			$showLinks = true;
			$hasCookieRenewAt = $_COOKIE[$cookieName];
		}
		
		# If there is an authenticated user, set a cookie for future showing away from internal host
		if ($_SERVER['REMOTE_USER']) {
			
			# By default, assume setting a cookie for future visibility of the links
			$setCookie = true;
			
			# Don't bother renewing the cookie if it hasn't reached renewal time
			if ($hasCookieRenewAt) {
				if (time () < $hasCookieRenewAt) {
					$setCookie = false;
				}
			}
			
			# Set the cookie if required
			if ($setCookie) {
				$expireInMonths = 6;
				$oneMonth = 60*60*24*30;
				$expiresAt = time () + ($oneMonth * $expireInMonths);
				$renewalAt = time () + ($oneMonth * ($expireInMonths / 2));		// Set renewal to be from half-way
				setcookie ($cookieName, $renewalAt, $expiresAt, '/');
			}
			
			# Show links
			$showLinks = true;	// Show if matched
		}
		
		# End if set not to show the links
		if (!$showLinks) {return false;}
		
		# Start the HTML by opening a list
		$html  = "\n\t<ul>";
		
		# Determine any return-to appended reference
		$returnTo = ($_SERVER['REQUEST_URI'] && ($_SERVER['REQUEST_URI'] != '/') ? '?' . htmlspecialchars ($_SERVER['REQUEST_URI']) : '');
		
		# Enable superusers to switch to another user
		$userSwitchingEnabled = (isSet ($_SERVER['REMOTE_USER']) && strlen ($_SERVER['REMOTE_USER']) && in_array ($_SERVER['REMOTE_USER'], $superusersSwitching, true));
		$userSwitching = self::userSwitching ($userSwitchingEnabled);
		
		# Show the links
		if (preg_match ('|^/logout|', $_SERVER['REQUEST_URI'])) {
			$html .= "\n\t\t<li><span>Logging out&hellip;</span></li>";	// NB the user will actually have been logged out already
		} else if ($_SERVER['REMOTE_USER']) {
			$html .= "\n\t\t<li class=\"submenu\">";
			$html .= ($userSwitching ? '<span class="impersonation">Impersonating' : '<span>Logged in as') . ' <strong>' . htmlspecialchars ($_SERVER['REMOTE_USER']) . "</strong> &#9660;</span>";
			$html .= "\n\t\t<ul>";
			if ($profileUrl) {
				$html .= "\n\t\t\t<li><a href=\"{$profileUrl}\">{$profileName}</a></li>";
			}
			if ($userSwitchingEnabled) {
				$html .= "\n\t\t\t<li>";
				$html .= '<form name="switchuser" action="" method="post"><input type="search" name="switchuser[username]" value="' . htmlspecialchars ($userSwitching) . '" placeholder="Switch user" size="10" /> <input type="submit" value="Go!"></form>';
				$html .= '</li>';
			}
			$html .= "\n\t\t\t<li><a href=\"/logout/{$returnTo}\">Logout</a></li>";	// Note that this will not maintain any #anchor, because the server doesn't see any hash: https://stackoverflow.com/questions/940905
			$html .= "\n\t\t</ul>";
			$html .= "</li>";
		} else {
			$html .= "\n\t\t<li><a href=\"/login/{$returnTo}\"><strong>Login</strong>" . ($ssoBrandName ? " with {$ssoBrandName}" : '') . "</a></li>";
		}
		
		# Complete the list
		$html .= "\n\t</ul>";
		
		# Surround with a div
		$html = "\n<div id=\"ssologin\">" . $html . "\n</div>";
		
		# Disable the standard link
		$html .= "\n" . '<style type="text/css">p.loggedinas {display: none;}</style>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function to implement user switching
	private static function userSwitching ($userSwitchingEnabled, $usernameRegexp = '/^([a-z0-9]+)$/')
	{
		# End if not enabled
		if (!$userSwitchingEnabled) {return false;}
		
		# Assume disabled by default
		$userSwitching = false;
		
		# Start the session
		if (!session_id ()) {session_start ();}
		
		# Maintain an existing session
		if (isSet ($_SESSION['switchuser']) && isSet ($_SESSION['switchuser']['username']) && preg_match ($usernameRegexp, $_SESSION['switchuser']['username'])) {
			$userSwitching = $_SESSION['switchuser']['username'];
		}
		
		# If the form is posted, select or clear the user
		if (isSet ($_POST['switchuser']) && isSet ($_POST['switchuser']['username'])) {
			if (strlen ($_POST['switchuser']['username']) && preg_match ($usernameRegexp, $_POST['switchuser']['username']) && ($_POST['switchuser']['username'] != $_SERVER['REMOTE_USER'])) {
				$userSwitching = trim ($_POST['switchuser']['username']);
				$_SESSION['switchuser']['username'] = $userSwitching;
			} else {
				unset ($_SESSION['switchuser']);
				$userSwitching = false;
			}
			header ("Location: {$_SERVER['_PAGE_URL']}");	// 302 Redirect (temporary)
		}
		
		# Switch the user, and refresh the page to avoid POST warnings
		if ($userSwitching) {
			$_SERVER['REMOTE_USER'] = $userSwitching;
		}
		
		# Return the user switching status (false or username)
		return $userSwitching;
	}
	
	
	# Function to show page last updated date
	public static function lastUpdated ()
	{
		# Determine the current file, based on the URL rather than file, which is more reliable when redirects used
		$file = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];
		
		# If a directory, add on index file
		$file .= (substr ($_SERVER['SCRIPT_NAME'], -1) == '/' ? 'index.html' : '');
		
		# If the computed file location doesn't exist, fall back to the script filename, which will likely indicate rewriting
		if (!file_exists ($file)) {
			$file = $_SERVER['SCRIPT_FILENAME'];
		}
		
		# Return the date
		return date ('jS F Y', filemtime ($file));
	}
	
	
	# Function to combine a set of files having a submenu into a single page
	public static function autocombine ($menufile = './menu.html', $div = 'autocombined', $removeHeadings = true)
	{
		# Get the links
		$menu = file_get_contents ($menufile);
		$links = preg_match_all ('|<a href="([^"]+)">([^<]+)</a>|', $menu, $linkMatches);
		
		# Start the HTML
		$html = '';
		
		# Get each file's contents
		foreach ($linkMatches[1] as $file) {
			if ($file == './') {$file = 'index.html';}
			$contents = file_get_contents ($file);
			
			# Deal with headings
			if ($removeHeadings) {
				
				# Cache the main title (using the first file)
				if (!isSet ($mainTitle)) {
					$title = preg_match ('|<h1([^>]*)>([^<]+)</h1>|', $contents, $headingMatches);
					$mainTitle = $headingMatches[2];
				}
				
				# Remove the heading if required
				if ($removeHeadings) {
					$contents = preg_replace ('|<h1([^>]*)>([^<]+)</h1>|', '', $contents);
				}
			}
			
			# Append the HTML
			$html .= $contents;
		}
		
		# Add the cached title
		if ($removeHeadings) {
			$html = "<h1>{$mainTitle}</h1>" . $html;
		}
		
		# Surround with a div if required
		if ($div) {
			$html = "<div class=\"{$div}\">{$html}</div>";
		}
		
		# Show the HTML
		return $html;
	}
	
	
	# Function to create tabs and assign the current tab
	public static function tabs ($pages, $selectedClass = 'selected', $class = 'tabs', $indent = 0, $orphaned = array ())
	{
		# Create the tabs
		$tabs = array ();
		foreach ($pages as $page => $label) {
			$isSelected = false;
			if ($page == $_SERVER['REQUEST_URI']) {
				$isSelected = true;
			}
			if ($orphaned && isSet ($orphaned[$_SERVER['REQUEST_URI']]) && ($orphaned[$_SERVER['REQUEST_URI']] == $page)) {
				$isSelected = true;
			}
			$selectedHtml = ($isSelected ? " class=\"{$selectedClass}\"" : '');
			$tabs[] = "<li{$selectedHtml}><a href=\"{$page}\">{$label}</a></li>";
		}
		
		# Compile the HTML
		$html  = "\n<ul class=\"{$class}\">" . implode ("\n\t", $tabs) . "\n</ul>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to switch stylesheet style via a cookie
	public static function switchStyle ($stylesheets, $directory)
	{
		# Allow the style to be set via a URL
		if (isSet ($_GET['style'])) {
			
			# Set the cookie
			setcookie ('style', $_GET['style'], time() + 31536000, '/', $_SERVER['SERVER_NAME'], '0');
			
			# Send the user back to the previous page (or the front page if not set); NB: the previous page cannot have had ?style=[whatever] in it because that would have been redirected
			$referrer = (isSet ($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'http://' . $_SERVER['SERVER_NAME'] . '/');
			header ("Location: $referrer");
		}
		
		# Assign the cookie style if that is set and it exists
		if (isSet ($_COOKIE['style'])) {
			foreach ($stylesheets as $stylesheet => $name) {
				if ($_COOKIE['style'] == $stylesheet) {
					$style = $_COOKIE['style'];
					break;
				}
			}
		}
		
		# Otherwise set the default (first) style in the array
		$temp = each ($stylesheets);
		$style = (isSet ($style) ? $style : $temp['key']);
		
		# Start the HTML
		$html['header'] = '<style type="text/css" media="all" title="User Defined Style">@import "' . $directory . $style . '.css";</style>';
		$html['links']  = "\n\t" . '<ul class="switch">';
		$html['links'] .= "\n\t\t" . '<li>Switch style:</li>';
		
		# Add in the other links
		foreach ($stylesheets as $file => $name) {
			
			# Add in the header links (but not to the present one)
			if ($style != $file) {
				$html['header'] .= "\n\t" . '<link rel="alternate stylesheet" type="text/css" href="' . $directory . $file . '.css" title="' . $name . '" />';
			}
			
			# Add in the on-page links (including the present one for page stability)
			$html['links']  .= "\n\t\t" . '<li><a href="?style=' . $file . '" title="Switch style (requires cookies)">' . $name . '</a></li>';
		}
		
		# Finish off the HTML
		$html['header'] .= "\n";
		$html['links']  .= "\n\t</ul>";
		
		# Return the HTML
		return $html;
	}
	
	
	
	# Wrapper function to provide search term highlighting
	public static function highlightSearchTerms ()
	{
		# Echo the result
		return highlightSearchTerms::main ();
	}
	
	
	# Function to create a basic threading system to enable easy previous/index/next links
	public static function thread ($pages)
	{
		# Loop through the list of pages numerically to find a match
		$totalPages = count ($pages);
		$foundPage = false;
		for ($page = 0; $page < $totalPages; $page++) {
			
			# If there's a match with the current page, break out of the loop and assign the previous/next links
			if ($pages[$page] == $_SERVER['REQUEST_URI']) {
				$foundPage = true;	// And $page will now represent the page number
				break;
			}
		}
		
		# End if no found page
		if (!$foundPage) {return false;}
		
		# Construct the HTML
		$html  = "\n" . '<ul class="thread">';
		$html .= (isSet ($pages[$page - 1]) ? "\n\t" . '<li><a href="' . $pages[$page - 1] . '">&lt; Previous</a></li>' : '');
		$html .= (isSet ($pages[0]) ? "\n\t" . '<li><a href="' . $pages[0] . '">Home</a></li>' : '');
		$html .= (isSet ($pages[$page + 1]) ? "\n\t" . '<li><a href="' . $pages[$page + 1] . '">Next &gt;</a></li>' : '');
		$html .= "\n" . '</ul>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to add social networking links
	public static function socialNetworkingLinks ($twitterName = false, $prefixText = false)
	{
		# End if server port doesn't match, as this can cause JS warnings on modern browser
		if (($_SERVER['SERVER_PORT'] != '80') && ($_SERVER['SERVER_PORT'] != '443')) {return false;}
		
		# Build the HTML
		$html  = "\n<p id=\"socialnetworkinglinks\">";
		if ($prefixText) {$html .= $prefixText;}
		$html .= "\n\t" . '<a class="twitter" href="//twitter.com/home?status=Loving+' . rawurlencode ($_SERVER['_PAGE_URL']) . ($twitterName ? rawurlencode (" from @{$twitterName}!") : '') . '" title="Follow us on Twitter"><img src="/images/general/twitter.png" alt="Icon" title="Twitter" width="55" height="20" /></a>';
		$html .= "\n\t" . '<iframe src="//www.facebook.com/plugins/like.php?href=' . rawurlencode ($_SERVER['_PAGE_URL']) . '&amp;send=false&amp;layout=button_count&amp;show_faces=true&amp;action=like&amp;colorscheme=light&amp;font&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; height:20px;"></iframe>';
		$html .= "\n</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Social networking metadata
	public static function socialNetworkingMetadata ($siteName, $twitterHandle /* handle or false */, $imageLocation /* Starting / */, $description, $title = false, $imageWidth = false, $imageHeight = false, $pageUrl = false)
	{
		# Start the HTML
		$html = '';
		
		# Ensure there is an imageLocation
		if (!$imageLocation) {return false;}
		
		# Start an array of meta attributes
		$attributes = array ();
		
		# Type
		$attributes['og:type'] = 'website';
		if ($twitterHandle) {
			$attributes['twitter:card'] = 'photo';
		}
		
		# Site name
		$attributes['og:site_name'] = $siteName;
		if ($twitterHandle) {
			$attributes['twitter:site'] = $twitterHandle;
		}
		
		# Image
		$attributes['og:image'] = $_SERVER['_SITE_URL'] . $imageLocation;
		if ($imageWidth) {$attributes['og:image:width'] = $imageWidth;}
		if ($imageHeight) {$attributes['og:image:height'] = $imageHeight;}
		
		# Text
		if ($title) {$attributes['og:title'] = (strlen ($title) > 80 ? substr ($title, 0, 80) . '&hellip' : $title);}
		// $attributes['og:description'] = substr ($description, 0, 220);	// Twitter will then truncate this to 201
		
		# Page URL
		$attributes['og:url'] = ($pageUrl ? $pageUrl : $_SERVER['_PAGE_URL']);
		
		# Compile the HTML
		$metaEntries = array ();
		foreach ($attributes as $key => $value) {
			$value = htmlspecialchars ($value, ENT_NOQUOTES);
			if (preg_match ('/^twitter:/', $key)) {
				$metaEntries[] = '<meta name="' . $key . '" content="' . $value . '" />';
			} else {
				$metaEntries[] = '<meta property="' . $key . '" content="' . $value . '" />';
			}
		}
		$html = "\n\t\t" . implode ("\n\t\t", $metaEntries);
		
		# Return the HTML, to put in the <head>
		return $html;
	}
	
	
	# Function to set a cookie for content-negotiation language setting
	public static function languageSelection ($languages = array ('en' => 'English', ), $postName = 'language', $imageLocation = '/images/flags/', $ulClass = 'language', $cookieName = 'language', $cookieDays = 30, $cookiePath = '/')
	{
		/*  NB Apache would need something like:
			<Directory /path/to/webroot/>
				Options +MultiViews
				DirectoryIndex index
			</Directory>
			LanguagePriority en es it de hr
			ForceLanguagePriority Fallback
			SetEnvIf Cookie "language=([-a-z]+)" prefer-language=$1
		*/
		
		# Set the language if the form has been posted and the language is supported
		$languageClicked = false;
		foreach ($languages as $language => $label) {
			$key = "{$postName}_{$language}";	// This whole checking of _x and _y and checking the key rather than value is due to IE6/7 bugs in <input type="image"> and <button>
			if (isSet ($_POST[$key . '_x']) && isSet ($_POST[$key . '_y'])) {
				$languageClicked = $language;
			}
		}
		
		# Set the cookie if the language is valid, otherwise ignore this as post spam
		if ($languageClicked) {
			if (array_key_exists ($languageClicked, $languages)) {
				setcookie ($cookieName, $languageClicked, time()+60*60*24*$cookieDays, $cookiePath);
				header ('Location: ' . $_SERVER['_PAGE_URL']);
			}
		}
		
		# Obtain the languages set in the environment, which will be used to mark the current language visually and set a global
		$cookieLanguage = (isSet ($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : NULL);
		if ($cookieLanguage) {$cookieLanguage = (isSet ($languages[$cookieLanguage]) ? $cookieLanguage : NULL);}
		$browserLanguage = (isSet ($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? preg_replace ('^~([a-z]+)(,|-)(.+)$~', '$1', $_SERVER['HTTP_ACCEPT_LANGUAGE']) : NULL);
		if ($browserLanguage) {$browserLanguage = (isSet ($languages[$browserLanguage]) ? $browserLanguage : NULL);}
		$serverPreferLanguage = (isSet ($_SERVER['prefer-language']) ? $_SERVER['prefer-language'] : NULL);
		if ($serverPreferLanguage) {$serverPreferLanguage = (isSet ($languages[$serverPreferLanguage]) ? $serverPreferLanguage : NULL);}
		$defaultLanguage = NULL;
		foreach ($languages as $language => $label) {
			$defaultLanguage = $language;
			break;
		}
		
		# Determine which language is set, defaulting to the first one
		$currentLanguage = ($cookieLanguage ? $cookieLanguage : ($browserLanguage ? $browserLanguage : ($serverPreferLanguage ? $serverPreferLanguage : $defaultLanguage)));
		
		# Export the current language into the global server scope
		$_SERVER['_LANGUAGE'] = $currentLanguage;
		
		# Construct the HTML
		$html  = "\n\t\t\t\t" . '<form action="' . htmlspecialchars ($_SERVER['REQUEST_URI']) . '" method="post">';
		$html .= "\n\t\t\t\t\t<ul class=\"{$ulClass}\">";
		$html .= "\n\t\t\t\t\t\t<li>Language:</li>";
		foreach ($languages as $language => $label) {
			$html .= "\n\t\t\t\t\t\t" . '<li' . ($language == $currentLanguage ? ' class="selected"' : '') . '><input type="image" name="' . htmlspecialchars ($postName) . '_' . htmlspecialchars ($language) . '" value="' . $language . '" src="' . $imageLocation . $language . '.png" title="' . $label . '" /></li>';
		}
		$html .= "\n\t\t\t\t\t</ul>";
		$html .= "\n\t\t\t\t</form>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to track redirects from one site to another, inserted into the prepended file
	/* Assumes that some server configuration like the following exists for the site's virtualHost:
	ServerAlias www.oldsite.example.com
	RewriteCond %{HTTP_HOST}   !^www.newsite.example.com [NC]
	RewriteRule ^/(.*) http://www.newsite.example.com/$1?newsite [L,R]
	*/
	public static function trackRedirects ($filename, $email, $queryString = 'newsite')
	{
		if ($_SERVER['QUERY_STRING'] == $queryString) {
			$newUrl = str_replace ('?'. $queryString, '', $_SERVER['_PAGE_URL']);
			if ($_SERVER['HTTP_REFERER']) {
				$contents = file_get_contents ($filename);
				if (strpos ($contents, $_SERVER['HTTP_REFERER']) === false) {
					file_put_contents ($filename, 'Uncontacted' . "\t" . $newUrl . "\t" . $_SERVER['HTTP_REFERER'] . "\n", FILE_APPEND);
					# NB This does not use application::utf8Mail to avoid creating a dependency
					mail ($email, "Out-of-date link to be corrected for {$_SERVER['SERVER_NAME']}", "Referrer:\n{$_SERVER['HTTP_REFERER']}\n\nShould now link to:\n{$newUrl}", "From: {$email}");
				}
			}
			header ("Location: {$newUrl}");
		}
	}
	
	
	# Function to run Wordpress-style shortcode handling to enable application embedding, by scanning the page for supported shortcodes, and replacing the content
	# E.g. [my_form foo="bar" size="5"] runs my_form.php and replaces any %attributes placeholder in that file with $foo = 'bar', $size = '5'
	public static function shortcodeHandledContent ()
	{
		# Define supported shortcodes, which are listed as files in the shortcodes directory
		$directory = $_SERVER['DOCUMENT_ROOT'] . '/sitetech/shortcodes/';
		$files = array_values (preg_grep ('/(.+)\.php$/', scandir ($directory)));	// array_values just reindexes
		$shortcodes = array ();
		foreach ($files as $file) {
			$shortcode = pathinfo ($file, PATHINFO_FILENAME);
			$shortcodes[$shortcode] = $directory . $file;
		}
		
		# Start a list of instances of shortcodes and their (optional) attributes on the page
		$instances = array ();
		
		# If a shortcode is present, run the file
		foreach ($shortcodes as $shortcode => $file_ignored) {
			
			# Load the current page
			$currentPage = $_SERVER['SCRIPT_FILENAME'];
			$currentFileContents = file_get_contents ($currentPage);
			
			# Scan for the current shortcode tag, in basic format (i.e. without attributes), e.g. [my_form], on the page, and register it once (however many times it appears, as each will result in the same replacement)
			$tag = '[' . $shortcode . ']';
			if (substr_count ($currentFileContents, $tag)) {
				$instances[$tag] = array (
					'shortcode' 	=> $shortcode,
					'attributes'	=> array (),
				);
			}
			
			# Also scan for the current shortcode tag, but with attributes (double-quoted), e.g. [my_form foo="bar"], on the page
			if (preg_match_all ("/\[{$shortcode} ([^\]]+)\]/", $currentFileContents, $matches, PREG_SET_ORDER)) {
				
				# Unique the list, as duplicates will result in the same replacements
				$matches = array_unique ($matches, SORT_REGULAR);
				
				# Register each match
				foreach ($matches as $match) {
					$tag = $match[0];
					$attributesString = trim ($match[1]);
					
					# Decode entities; i.e. assume the page was generated by a richtext editor (so & will have become &amp; when editing)
					$attributesString = html_entity_decode ($attributesString);
					
					# Fix up encoded e-mail strings
					$attributesString = str_replace ('<span>&#64;</span>', '@', $attributesString);
					
					# Parse out attributes
					preg_match_all ('/\b([^=]+)=("|&quot;)(.*)("|&quot;)/U', $attributesString, $tokenMatches, PREG_SET_ORDER);
					$attributes = array ();
					foreach ($tokenMatches as $tokenMatch) {
						$key = $tokenMatch[1];
						$value = $tokenMatch[3];
						$attributes[$key] = $value;
					}
					
					# Register this instance
					$instances[$tag] = array (
						'shortcode' 	=> $shortcode,
						'attributes'	=> $attributes,
					);
				}
			}
		}
		
		# End if no instances, as no special handling needed
		if (!$instances) {return false;}
		
		# For each instance, load the plugin and set the attributes
		$replacements = array ();
		foreach ($instances as $tag => $instance) {
			$shortcode = $instance['shortcode'];
			$attributes = $instance['attributes'];
			
			# Load this shortcode's plugin, with the correct directory context
			$plugin = $shortcodes[$shortcode];
			$shortcodeContent = file_get_contents ($plugin);
			
			# If an attributes placeholder is specified, replace with values
			if (substr_count ($shortcodeContent, '%attributes')) {
				$attributesStrings = array ();
				foreach ($attributes as $key => $value) {
					$attributesStrings[] = "\${$key} = '" . str_replace ("'", "\\'", $value) . "'";
				}
				$attributesString = implode (', ', $attributesStrings);
				$shortcodeContent = str_replace ('%attributes', $attributesString, $shortcodeContent);
			}
			
			# If the tag has become surrounded with a paragraph tag by the WYSIWYG editor, include that surrounding tag in the replacement
			if (preg_match ('/(<p[^>]*>' . preg_quote ($tag, '/') . '<\/p>)/', $currentFileContents, $matches)) {
				$tag = $matches[0];
			}
			
			# Replace the shortcode in the page content with the generated content
			$replacements[$tag] = $shortcodeContent;
		}
		
		//var_dump ($replacements);
		
		# Perform replacements, so that the page now has the shortcodes replaced with the real PHP
		$content = strtr ($currentFileContents, $replacements);
		
		# Determine the shadow file; NB We cannot remove the DOCUMENT_ROOT from $currentPage as the page may be aliased, so instead we look at the local path
		$scriptName = str_replace (array ('../', '..\\'), '', $_SERVER['SCRIPT_NAME']) . (substr ($_SERVER['SCRIPT_NAME'], -1) == '/' ? 'index.html' : '');
		$shadowFile = $_SERVER['DOCUMENT_ROOT'] . '/sitetech/shortcodes-cache' . $scriptName;
		
		# Determine whether to write the shadow file, or use an existing file if present
		$writeShadowFile = true;
		if (file_exists ($shadowFile)) {
			if (md5_file ($shadowFile) == md5 ($content)) {
				$writeShadowFile = false;
			}
		}
		
		# Save the processed file as a shadow page, if required
		if ($writeShadowFile) {
			$dirname = pathinfo ($shadowFile, PATHINFO_DIRNAME);
			$umaskBefore = umask (0);
			if (!is_dir ($dirname)) {
				mkdir ($dirname, 0775, true);	// rwx, rwx, rx
			}
			file_put_contents ($shadowFile, $content);
			chmod ($shadowFile, 0664);	// rw, rw, r
			umask ($umaskBefore);	// Reset
		}
		
		# Import (maintain) variables in global scope, so that the environment is the same (e.g. appended.html receives variables defined in prepended.html)
		foreach ($GLOBALS as $key => $value) {
			if (preg_match ('/^_/', $key)) {continue;}	// Skip superglobals as they don't need to be recreated
			if ($key == 'GLOBALS') {continue;}	// Skip self
			${$key} = $value;
		}
		
		# Run the page; the PHP enviroment, e.g. REQUEST_URI will remain unamended with the original filename
		include ($shadowFile);
		
		# Include the footer then end, to avoid the main content running naturally
		require_once ('sitetech/appended.html');
		die;
	}
}


# Class for highlighting words from a search engine's referring page which includes search terms in the URL
class highlightSearchTerms
{
	# Quasi-constructor
	public static function main ()
	{
		# Only run the buffer if there is an outside referer, to save processing speed
		if (empty ($_SERVER['HTTP_REFERER'])) {return;}
		
		# Get the referer
		if (!$referer = @parse_url ($_SERVER['HTTP_REFERER'])) {return;}
		
		# Buffer the output
		if (isSet ($referer['host'])) {
			if ($referer['host'] != $_SERVER['HTTP_HOST']) {
				ob_start (array ('highlightSearchTerms', 'outsideWrapper'));
				ob_get_clean ();
			}
		}
	}
	
	
	# List the supported search engines (which use & as the splitter and + between query words) as hostname core => query variable in URL.
	public static function supportedSearchEngines ()
	{
		# Return an array of search engines
		return $searchEngines = array (
			'google' => 'q',	// Increasingly this won't work as google are using intermediate links
			'yahoo' => 'p',
			'bing' => 'q',
			'altavista' => 'q',
			'lycos' => 'query',
			'alltheweb' => 'q',
			'teoma' => 'q',
			'cam.ac' => 'qt',
		);
	}
	
	
	# List the available colours for highlighting, or enter 'highlight' to use class="highlight"
	#!# This should be set in the options instead of as a method
	public static function availableColours ()
	{
		# Return an array of available colours
		return $colours = array (
			'referer',
		);
	}
	
	
	# Outside wrapper as ob_start seems to have issues with multiple arguments
	public static function outsideWrapper ($string) {
		return self::wrapper ($string);
	}
	
	
	# Wrapper function
	public static function wrapper ($string, $searchEngines = array ())
	{
		# Get the list of search engines and colours
		if (!$searchEngines) {$searchEngines = self::supportedSearchEngines ();}
		$colours = self::availableColours ();
		
		# Obtain the query words (if any) from the referring page
		if ($queryWords = self::obtainQueryWords ($searchEngines)) {
			
			# Modify the HTML
			$html = self::replaceHtml ($string, $queryWords, $colours);
			
			# Return the HTML
			return $html;
		}
		
		# Otherwise return the unmodified HTML
		return $string;
	}
	
	
	# Obtain the query words
	public static function obtainQueryWords ($searchEngines)
	{
		# Parse the URL so that the hostname can be obtained
		$referer = parse_url ($_SERVER['HTTP_REFERER']);
		
		# Continue if the referer contains a query term
		if (isSet ($referer['query'])) {
			
			# Loop through each of the search engines to determine if the previous page is from one of them
			$matched = false;
			foreach ($searchEngines as $vendor => $queryVariable) {
				
				# Run a match against the search engine's name with a dot either side, e.g. .google.[com]
				#!# NB this could be subverted by e.g. www.google.foobar.com
				if (strpos ($referer['host'], ('.' . $vendor . '.')) !== false) {
					
					# Flag the match then break so that the selected search engine is held in the array $searchEngine
					$matched = true;
					break;
				}
			}
			
			# If matched, obtain the query string used in the referring page
			if ($matched) {
				
				# Make an array of the previous page's query terms
				$queryTerms = explode ('&', $referer['query']);
				
				# Loop through each of the query terms until the relevant one is found
				$queryTermMatched = false;
				foreach ($queryTerms as $queryTerm) {
					
					# Do a match against the relevant query term e.g. q= at the start
					if (preg_match ('/^' . $queryVariable . '=' . '/i', $queryTerm)) {
						
						# Flag the match then break so that the search query term is held in the variable $queryTerm
						$queryTermMatched = true;
						break;
					}
				}
				
				# If there is a match, obtain the query phrase from the query term (i.e. the words after the =
				if ($queryTermMatched) {
					list ($discarded, $queryPhrase) = explode ('=', $queryTerm);
					
					# End if there is no query phrase, e.g. ?..q=&foo=bar
					if (!strlen ($queryPhrase)) {return false;}
					
					# Strip " (which is encoded as %22) from the query
					$queryPhrase = trim (str_replace ('%22', '', $queryPhrase));
					
					# Split the query phrase into words demarcated by +
					$queryWords = explode ('+', $queryPhrase);
					
					# Return the result
					return $queryWords;
				}
			}
		}
		
		# Otherwise return false
		return false;
	}
	
	
	# Function to highlight search terms very loosely based on GPL'ed script by Eric Bodden - see www.bodden.de/legacy/php-scripts/
	public static function replaceHtml ($html, $searchWords, $colours = 'yellow', $sourceAsTextOnly = false, $showIndication = true, $unicode = true)
	{
		# Assign the colours to be used, into an array
		if (!is_array ($colours)) {
			$temporary = $colours;
			unset ($colours);
			$colours[] = $temporary;
		}
		
		# Count the number of colours available
		$totalColours = count ($colours);
		
		# Unique the words to make the regexp more efficient then ensure they are in string-length order (so that e.g. 'and' will match before 'an')
		$searchWords = array_unique ($searchWords);
		usort ($searchWords, function ($a, $b) {
			return strlen ($b) - strlen ($a);
		});
		
		# Escape slashes to prevent PCRE errors as listed on www.php.net/pcre.pattern.syntax and ensure alignment with word boundaries
		foreach ($searchWords as $index => $searchWord) {
			if ($unicode) {$searchWord = html_entity_decode (preg_replace ("/%u([0-9a-f]{3,4})/i" . ($unicode ? 'u' : ''), "&#x\\1;", urldecode ($searchWord)), NULL, 'UTF-8');}	// UTF8-compliant version of urldecode
			$searchWords[$index] = preg_quote (trim ($searchWord), '/');
		}
		
		# Remove empty search words (i.e. whitespace) to prevent timeouts
		foreach ($searchWords as $index => $phrase) {
			if (trim ($phrase) == '') {
				unset ($searchWords[$index]);
			}
		}
		
		/*
		# Prevent timeouts with large numbers of words in large documents
		$length = strlen ($html);
		foreach ($searchWords as $index => $phrase) {
			if ((strlen ($html) > 100000) && ($index > 6)) {
				unset ($searchWords[$index]);
			}
		}
		*/
		
		# Prepare the regexp
		$regexpStart = ($sourceAsTextOnly ? '\b(' : '>[^<]*\b(');
		$regexpEnd = ($sourceAsTextOnly ? ')\b' : ')\b[^<]*<');
		$searchWords = implode ('|', $searchWords);
		$phraseRegexp = $regexpStart . $searchWords . $regexpEnd;
		
		# Exclude <script> sections from the HTML to look against
		$testAgainstHtml = $html;
		$testAgainstHtml = preg_replace ('|<script (.+)</script>|is' . ($unicode ? 'u' : ''), '', $testAgainstHtml);
		
		# Perform a regexp match to extract the matched phrases or end at this point if none found
		if (!preg_match_all (('/' . $phraseRegexp . '/i' . ($unicode ? 'u' : '')), $testAgainstHtml, $phrases, PREG_PATTERN_ORDER)) {
			return $html;
		}
		
		# Determine the regexp to match words in each pre-matched phrase
		$wordRegexp = '\b(' . $searchWords . ')\b';
		
		# Loop through each matched phrase
		$replacements = array ();
		$phrases[0] = array_unique ($phrases[0]);
		foreach ($phrases[0] as $index => $phrase) {
			
			# Assign whether to use class or span in the referrer
			$highlightCodeStart = ($colours[0] == 'referer' ? '<span class="referer">' : '<span style="background-color: ' . $colours[($index % $totalColours)] . ';">');
			
			# Match the words
			$replacements[$index] = preg_replace ('/' . $wordRegexp . '/i' . ($unicode ? 'u' : ''), "{$highlightCodeStart}\\1</span>", $phrase);
		}
		
		# Globally replace each phrase with each replacements back into the overall HTML; for text-only (i.e. non-HTML) matching, add word-boundaries to prevent 'a' etc being picked up in <span> etc.
		if ($sourceAsTextOnly) {
			foreach ($phrases[0] as $index => $phrase) {
				$phrases[0][$index] = '/\b' . preg_quote ($phrase, '/') . '\b/' . ($unicode ? 'u' : '');
			}
			$html = preg_replace ($phrases[0], $replacements, $html);
		} else {
			$html = str_replace ($phrases[0], $replacements, $html);
		}
		
		# Introduce the HTML
		if ($showIndication) {
			$html = '<p class="referer">Words you searched for have been highlighted.</p>' . "\n" . $html;
		}
		
		# Return the result
		return $html;
	}
}

?>
