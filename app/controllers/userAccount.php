<?php

# User account class
class userAccount
{
	# Class properties
	private $settings;
	private $baseUrl;
	private $user;
	private $userIsAdministrator;
	private $template = array ();
	
	
	# Constructor
	public function __construct ($settings, $baseUrl)
	{
		# Assign properties
		$this->settings = $settings;
		$this->baseUrl = $baseUrl;
		
		# Begin the session
		$this->sessionInit ();
		
		# Get the user status
		$this->user = $this->getUser ();
	}
	
	
	# Getter for template
	public function getTemplate ($template)
	{
		$template = array_merge ($template, $this->template);
		return $template;
	}
	
	public function getUserIsAdministrator ()
	{
		return $this->userIsAdministrator;
	}
	
	
	# Login page
	public function login ()
	{
		# Start the HTML
		$html = '';
		
		# If the user is logged in, state this
		if ($this->user) {
			$this->template['message']  = "<p>You are signed in as " . $this->user['email'] . " .</p>";
			$this->template['message'] .= "\n<p>You can <a href=\"{$this->baseUrl}/logout/\">sign out</a> if you wish.</p>";
			$this->template['form'] = false;
			if ($returnPath = preg_replace ('|/login/\??|', '', $_SERVER['REQUEST_URI'])) {
				$redirectTo = $_SERVER['_SITE_URL'] . $returnPath;
				application::sendHeader (302, $redirectTo, true);
			}
		} else {
			
			# Login form; if successful, log the user in
//			$html .= "\n<p><strong>Please sign in (or first create an account) below to access this section:</strong></p>";
//			$this->template['message'] = $html;
			$formHtml = '';
			if ($result = $this->loginForm ($formHtml)) {
				$this->doLogin ($result);	// $result now contains the user details (username, email, name, privileges)
			}
			$this->template['form'] = $formHtml;
		}
	}
	
	
	# Login form
	private function loginForm (&$html, $autofocus = true)
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'div'						=> 'user',
			'display'					=> 'paragraphs',
			'displayTitles'				=> false,
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> 'Login',
			'submitButtonAccesskey'		=> false,
		));
		
		# Widgets
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'E-mail address',
			'placeholder'	=> 'E-mail address',
			'required'		=> true,
			'autofocus'		=> true,
		));
		$form->password (array (
			'name'			=> 'password',
			'title'			=> 'Password',
			'placeholder'	=> 'Password',
			'required'		=> true,
		));
		
		# Validate the login
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if (strlen ($unfinalisedData['email']) && strlen ($unfinalisedData['password'])) {
				if (!$result = $this->doAuthentication ($unfinalisedData['email'], $unfinalisedData['password'], $error)) {
					$form->registerProblem ('authfail', $error);
				}
			}
		}
		
		# Process the form
		if (!$form->process ($html)) {return false;}
		
		# Return the result
		return $result;
	}
	
	
	# Authentication
	private function doAuthentication ($email, $password, &$error = '')
	{
		# Assemble the data to post
		$postData = array (
			'identifier'	=> $this->settings['authNamespace'] . $email,
			'password'	=> $password,
		);
		
		# Post to the user authentication API
		$apiUrl = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/user.authenticate' . '?key=' . $this->settings['cyclestreetsApiKey'];
		$resultJson = application::file_post_contents ($apiUrl, $postData, $error);
		if ($error) {
			// echo $error;		// Debug
			$error = 'Sorry, a technical error occured trying to validate the details you gave. Please try again later.';
			return false;
		}
		
		# Unpack the response
		$result = json_decode ($resultJson, true);
		
		# Detect unparsable JSON (e.g. the API is not properly installed)
		if ($result === NULL && json_last_error () !== JSON_ERROR_NONE) {
			$error = 'Sorry, a technical error occured trying to validate the details you gave. Please try again later.';
			return false;
		}
		
		# If there is an error, pass on the text and return false
		if (isSet ($result['error'])) {
			$error = $result['error'];
			return false;
		}
		
		# Otherwise return the account details
		return $result;
	}
	
	
	# Get user details (from the session)
	public function getUser ()
	{
		# Set the top-right login area
		// At present, the login box is not shown
		$this->template['login-status'] = '';
		
		# Get the user login status
		$user = $this->sessionGet ('user');
		
		# Set CSS classes where the template supports this
		if ($user) {
			$this->template['css'] = '
			<style type="text/css">
				nav li.login, span.login {display: none;}
				nav li.register {display: none;}
			</style>
			';
		} else {
			$this->template['css'] = '
			<style type="text/css">
				nav li.profile, span.profile {display: none;}
			</style>
			';
		}
		
		# Return false if no user
		if (!$user) {
			$this->template['_user'] = false;
			return false;
		}
		
		# Determine privileges
		$this->userIsAdministrator = $this->userIs ('administrator');
		
		# Write the login status in the top-right
		$loginStatusHtml  = "\n<p style=\"text-align: right\"><span style=\"color: #ccc;\">Signed in as: </span>" . htmlspecialchars ($user['email']);
//		if ($this->userIsAdministrator) {
//			$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/settings/\">Settings</a>";
//		}
		
		$loginStatusHtml .= " | <a href=\"{$this->baseUrl}/logout/\">Sign out</a></p>";
		$this->template['login-status'] = $loginStatusHtml;
		
		# Remove namespace
		$user['email'] = str_replace ($this->settings['authNamespace'], '', $user['email']);
		$user['username'] = str_replace ($this->settings['authNamespace'], '', $user['username']);
		
		# Set the template value
		$this->template['_user'] = $user['email'];
		
		# Return the user details
		return $user;
	}
	
	
	# Function to parse a list of e-mails to check for privilege
	private function userIs ($right)
	{
		# Return false if no user
		if (!$this->user) {return false;}
		
		# Return whether the right is listed in the privileges
		return (in_array ($right, $this->user['privileges']));
	}
	
	
	# Function to log the user in
	private function doLogin ($result)
	{
		# Regenerate the session ID
		session_regenerate_id ($deleteOldSession = true);
		
		# Create the session entry
		$this->sessionWrite ('user', $result);
		
		# Refresh the page to ensure the session cookie is written
		application::sendHeader ('refresh');
	}
	
	
	# Function to log the user out
	public function logout ()
	{
		# Start the HTML
		$html = '';
		
		# Cache whether the user presented session data
		$userHadSessionData = ($this->sessionGet ('user'));
		
		# Explicitly destroy the session
		$this->sessionDestroy ('user');
		
		# Confirm logout if there was a session, and redirect the user to the login page if necessary
		$loginLocation = $this->baseUrl . '/login/';
		if ($userHadSessionData) {
			$html .= "\n<p>You have been successfully signed out.</p>";
			$html .= "\n<p>You can <a href=\"" . htmlspecialchars ($loginLocation) . '">sign in again</a> if you wish.</p>';
			$this->user = false;
			$this->user = $this->getUser ();	// Hides the profile link
			$this->template['adminMenuLink'] = '';
			$this->template['adminLink'] = '';
		} else {
			header ('Location: https://' . $_SERVER['SERVER_NAME'] . $this->baseUrl . $loginLocation);
			$html .= "\n<p>You are not signed in.</p>";
			$html .= "\n<p><a href=\"" . htmlspecialchars ($loginLocation) . '">Please click here to continue.</a></p>';
		}
		
		# Clear the login status indicator
		$this->template['login-status'] = '';
		
		# Register the HTML
		$this->template['message'] = $html;
	}
	
	
	# Function to start session handling if not already running
	private function sessionInit ()
	{
		# Lock down PHP session management
		ini_set ('session.name', 'session');
		ini_set ('session.use_only_cookies', 1);
		
		# Extend session time from 24 minutes
		ini_set ('session.gc_maxlifetime', 60*60*24*7);
		
		# Start the session handling
		if (!session_id ()) {session_start ();}
	}
	
	
	# Function to get the current session data
	private function sessionGet ($field)
	{
		# End session if basic fingerprint match fails
		if (!isSet ($_SESSION['_fingerprint'])) {return false;}
		if ($_SESSION['_fingerprint'] != md5 ($_SERVER['HTTP_USER_AGENT'])) {
			$this->sessionDestroy ($field);
			return false;
		}
		
		# Return the field's data if present
		return (isSet ($_SESSION[$field]) ? $_SESSION[$field] : false);
	}
	
	
	# Function to write into the session
	private function sessionWrite ($field, $data)
	{
		# Add/update fingerprint
		$_SESSION['_fingerprint'] = md5 ($_SERVER['HTTP_USER_AGENT']);
		
		# Write the value
		$_SESSION[$field] = $data;
	}
	
	
	# Function to destroy a session
	private function sessionDestroy ($field)
	{
		# Remove the field
		unset ($_SESSION[$field]);
		
		# If the session is now empty, destroy it entirely
		if (!$_SESSION) {
			
			# Regenerate the session ID
			session_regenerate_id ($deleteOldSession = true);
			
			# Destroy the session cookie
			session_unset ();
			session_destroy ();
			$params = session_get_cookie_params ();
			setcookie (session_name (), '', time () - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}
	}
	
	
	# Registration page
	public function register ()
	{
		
		
		# If the user is already logged in, end
		if ($this->user) {
			$this->template['form'] = '<p>You are already signed in.</p>';
			return;
		}
		
		# If a token is specified, trigger this upstream
		if (isSet ($_GET['token']) && preg_match ('/^[a-f0-9]{24}$/', $_GET['token'])) {
			#!# Should be a proper API call upstream
			$url = 'https://www.cyclestreets.net/signin/register/' . $_GET['token'] . '/';
			$webpage = file_get_contents ($url);
			if (substr_count ($webpage, 'has now been validated')) {
				$unicodeTick = chr(0xe2).chr(0x9c).chr(0x94);	// https://www.fileformat.info/info/unicode/char/2714/
				$this->template['message'] = "<p>{$unicodeTick} Thank you for validating the account. Please <a href=\"/login/\">sign in</a> to continue.</p>";
			}
			if (substr_count ($webpage, 'were not correct')) {
				$unicodeCross = chr(0xe2).chr(0x9c).chr(0x96);	// https://www.fileformat.info/info/unicode/char/2716/
				$this->template['message'] = "<p>{$unicodeCross} The details you supplied were not correct. Please check the link given in the e-mail and try again.</p>";
			}
			return;
		}
		
		# Create the form
		$formHtml = '';
		if (!$data = $this->profileForm ($formHtml)) {
			$this->template['form'] = $formHtml;
			return;
		}
		
		# Namespace the fields
		$data['email'] = $this->settings['authNamespace'] . $data['email'];
		$data['username'] = $this->settings['authNamespace'] . application::generatePassword (15);	// Random username
		
		# Create the account, which will use the name,email,password fields
		$apiUrl = $this->settings['cyclestreetsApiBaseUrl'] . '/v2/user.create' . '?key=' . $this->settings['cyclestreetsApiKey'] . "&urlprefix={$_SERVER['_SITE_URL']}";
		$result = application::file_post_contents ($apiUrl, $data);
		$result = json_decode ($result, true);
		if (isSet ($result['error'])) {
			$this->template['message'] = '<p>Error: ' . $result['error'] . '</p>';
			return false;
		}
		
		# Confirm that the user should check their inbox
		#!# Link needs to be local
		$this->template['message'] = '<p>Many thanks. Please check your e-mail and click on the confirmation link we have sent you.</p>';
		return true;
	}
	
	
	# Profile form
	private function profileForm (&$html, $update = false, $data = array ())
	{
		# Start the HTML
		$html = '';
		
		# Create a new form
		require_once ('ultimateForm.php');
		$form = new form (array (
			'div'						=> 'user',
			'display'					=> 'paragraphs',
			'displayTitles'				=> false,
			'displayRestrictions'		=> false,
			'formCompleteText'			=> false,
			'requiredFieldIndicator'	=> false,
			'submitButtonText'			=> ($update ? 'Update' : 'Create account'),
			'submitButtonAccesskey'		=> false,
			'autofocus'					=> true,
			'displayDescriptions'		=> false,
		));
		
		# Widgets
		$form->email (array (
			'name'			=> 'email',
			'title'			=> 'E-mail address',
			'placeholder'	=> 'E-mail address',
			'required'		=> true,
			'autofocus'		=> true,
		));
		$form->password (array (
			'name'			=> 'password',
			'title'			=> 'Password',
			'placeholder'	=> 'Password',
			'required'		=> true,
			'confirmation'	=> true,
		));
		
		# Process the form
		if (!$result = $form->process ($html)) {return false;}
		
		# Return the result
		return $result;
	}
}

?>
