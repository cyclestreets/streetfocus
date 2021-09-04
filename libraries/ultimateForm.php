<?php

/**
 * A class for the easy creation of webforms.
 * 
 * SUPPORTS:
 * - Form stickyness
 * - All HTML fieldtypes
 * - Preset field types: valid e-mail input field, textarea which must contain at least one line containing two values
 * - Setup error correction: duplicated fields will result in the form not displaying
 * - Output to CSV, e-mail, confirmation, screen and further processing as an array
 * - Display of the form as a series of paragraphs, CSS (using divs and spans) or a table; styles can be set in a stylesheet
 * - Presentation option of whether colons appear between the title and the input field
 * - The ability to add descriptive notes to a field in addition to the title
 * - Specification of required fields
 * - By default, the option to count white-space only as an empty submission (useful when specifying as a required field)
 * - The option to trim white space surrounding submitted fields
 * - Valid XHTML1.0 Transitional code
 * - Accessibility code for form elements
 * - Customisable submit button text accesskey and location (start/end of form)
 * - Regular expression hooks for various widget types
 * - Templating mechanism
 * - The ability to set elements as non-editable
 * - Ability to generate form widgets automatically by reading a database structure (dataBinding facility)
 * - Group validation rules to ensure that at least one field is completed, that all are the same or all are different
 * - GET support available
 * - Uploaded files can be attached to e-mails
 * - Uploaded zip files can be automatically unzipped
 * - UTF-8 character encoding
 * - Unsaved data protection DHTML (if required)
 * - HTML5 widget support (partial)
 * 
 * REQUIREMENTS:
 * - PHP5 or above (PHP4.3 will run with slight modification)
 * - Runs in register_globals OFF mode for security
 * - Requires libraries application.php and pureContent.php
 * 
 * APACHE ENVIRONMENT SETUP
 * 
 * The following are required for the script to work correctly; if not, an error will be shown
 * If attempting to set in .htaccess, remove admin_ from the directives
 * 
 * <code>
 * php_flag display_errors 0
 * php_value error_reporting -1
 * 
 * # If using file uploads also include the following and set a suitable amount in MB; upload_max_filesize must not be more than post_max_size
 * php_admin_flag file_uploads 1
 * php_admin_value upload_max_filesize 10M // Only way of setting the maximum size
 * php_admin_value post_max_size 10M
 * </code>
 * 
 * @package ultimateForm
 * @license	https://opensource.org/licenses/gpl-license.php GNU Public License
 * @author	{@link http://www.geog.cam.ac.uk/contacts/webmaster.html Martin Lucas-Smith}, University of Cambridge
 * @copyright Copyright  2003-21, Martin Lucas-Smith, University of Cambridge
 * @version See $version below
 */
class form
{
	## Prepare variables ##
	
	# Principal arrays
	var $elements = array ();					// Master array of form element setup
	var $form;									// Master array of posted form data
	var $outputData;							// Master array of arranged data for output
	var $outputMethods = array ();				// Master array of output methods
	
	# Main variables
	var $name;									// The name of the form
	var $location;								// The location where the form is submitted to
	var $duplicatedElementNames = array ();		// The array to hold any duplicated form field names
	var $formSetupErrors = array ();			// Array of form setup errors, to which any problems can be added; those whose key is prefixed with _ are warnings
	var $elementProblems = array ();			// Array of submitted element problems
	var $externalProblems = array ();			// Array of external element problems as inserted by the calling applications
	var $validationRules = array ();			// Array of validation rules
	var $databaseConnection = NULL;				// Database connection
	var $html = NULL;							// Compiled HTML, obtained by using $html = $form->getHtml () after $form->process ();
	var $prefixedGroups = array ();				// Groups of element names when using prefixing in dataBinding
	var $attachments = array ();				// Array of attachments
	
	# State control
	var $formPosted;							// Flag for whether the form has been posted
	var $formDisplayed = false;					// Flag for whether the form has been displayed
	var $formDisabled = false;					// Whether the form has been disabled
	var $setupOk = false;						// Flag for whether the form has been set up OK
	var $headingTextCounter = 1;				// Counter to enable uniquely-named fields for non-form elements (i.e. headings), starting at 1 #!# Get rid of this somehow
	var $uploadProperties;						// Data store to cache upload properties if the form contains upload fields
	var $hiddenElementPresent = false;			// Flag for whether the form includes one or more hidden elements
	var $antispamWait = 0;						// Time to wait in the event of spam attempt detection, in seconds
	var $dataBinding = false;					// Whether dataBinding is in use; if so, this will become an array containing connection variables
	var $jQueryLibraries = array ();			// Array of jQuery client library loading HTML tags, if any, which are treated as plain HTML
	var $jQueryCode = array ();					// Array of jQuery client code, if any, which will get wrapped in a script tag
	var $javascriptCode = array ();				// Array of javascript client code, if any, which will get wrapped in a script tag
	var $formSave = false;						// Whether the submission is a save rather than a proper submission
	
	# Output configuration
	var $configureResultEmailRecipient;							// The recipient of an e-mail
	var $configureResultEmailRecipientSuffix;					// The suffix used when a select field is used as the e-mail receipient but the selectable items are only the prefix to the address
	var $configureResultEmailAdministrator;						// The from field of an e-mail
	var $configureResultFileFilename;							// The file name where results are written
	var $configureResultConfirmationEmailRecipient = '';		// The recipient of any confirmation e-mail
	var $configureResultConfirmationEmailAbuseNotice = true;	// Whether to include an abuse report notice in any confirmation e-mail sent
	var $configureResultEmailedSubjectTitle = array ();			// An array to hold the e-mail subject title for either e-mail result type
	var $configureResultScreenShowUnsubmitted;					// Whether, in screen results mode, unsubmitted widgets that are not required will be listed
	var $configureResultEmailShowUnsubmitted;					// Whether, in e-mail results mode, unsubmitted widgets that are not required will be listed
	var $configureResultConfirmationEmailShowUnsubmitted;		// Whether, in e-mail confirmation results mode, unsubmitted widgets that are not required will be listed
	
	# Supported output types
	var $supportedTypes = array ('file', 'email', 'confirmationEmail', 'screen', 'processing', 'database');
	var $displayTypes = array ('tables', 'css', 'paragraphs', 'templatefile');
	
	# Constants
	var $version = '1.27.1';
	var $timestamp;
	var $minimumPhpVersion = 5;	// md5_file requires 4.2+; file_get_contents and is 4.3+; function process (&$html = NULL) requires 5.0
	var $escapeCharacter = "'";		// Character used for escaping of output	#!# Currently ignored in derived code
	
	# Specify available arguments as defaults or as NULL (to represent a required argument)
	var $argumentDefaults = array (
		'get'								=> false,							# Enable GET support instead of (default) POST
		'name'								=> 'form',							# Name of the form
		'id'								=> false,							# Id of the form (or none)
		'div'								=> 'ultimateform',					# The value of <div class=""> which surrounds the entire output (or false for none)
		'displayPresentationMatrix'			=> false,							# Whether to show the presentation defaults
		'displayTitles'						=> true,							# Whether to show user-supplied titles for each widget
		'titleReplacements'					=> array (),						# Global replacement of values in titles (mainly of use when dataBinding)
		'displayDescriptions'				=> true,							# Whether to show user-supplied descriptions for each widget
		'displayRestrictions'				=> true,							# Whether to show/hide restriction guidelines
		'display'							=> 'tables',						# Whether to display the form using 'tables', 'css' (CSS layout) 'paragraphs' or 'template'
		'displayTemplate'					=> '',								# Either a filename or a (long) string containing placemarkers
		'displayTemplatePatternWidget'		=> '{%element}',					# The pattern used for signifying element name widget positions when templating
		'displayTemplatePatternLabel'		=> '{[%element]}',					# The pattern used for signifying element name label positions (optional) when templating
		'displayTemplatePatternSpecial'		=> '{[[%element]]}',				# The pattern used for signifying element name special item positions (e.g. submit, reset, problems) when templating
		'classShowType'						=> true,							# Whether to include the widget type within the class list for the container of the widget (e.g. tr in 'tables' mode)
		'debug'								=> false,							# Whether to switch on debugging
		'displayColons'						=> true,							# Whether to show colons after the initial description
		'whiteSpaceTrimSurrounding'			=> true,							# Whether to trim surrounding white space in any forms which are submitted
		'whiteSpaceCheatAllowed'			=> false,							# Whether to allow people to cheat submitting whitespace only in required fields
		'reappear'							=> false,							# Whether to keep the form visible after successful submission (useful for search forms, etc., that should reappear), either true/false/'disabled' (disables the elements and the submit button but reshows the form as a whole)
		'formCompleteText'					=> 'Many thanks for your input.',	# The form completion text (or false if not to display it at all)
		'submitButtonPosition'				=> 'end',							# Whether the submit button appears at the end or the start/end/both of the form
		'submitButtonText'					=> 'Submit!',						# The form submit button text
		'submitButtonAccesskey'				=> 's',								# The form submit button accesskey
		'submitButtonAccesskeyString'		=> false,							# Whether to show the accesskey string in the submit button
		'submitButtonTabindex'				=> false,							# The form submit button tabindex (if any)
		'submitButtonImage'					=> false,							# Location of an image to replace the form submit button
		'submitButtonClass'					=> 'button',							# Submit button class
		'refreshButton'						=> false,							# Whether to include a refresh button (i.e. submit form to redisplay but not process)
		'refreshButtonAtEnd'				=> true,							# Whether the refresh button appears at the end or the start of the form
		'refreshButtonText'					=> 'Refresh!',						# The form refresh button text
		'refreshButtonAccesskey'			=> 'r',								# The form refresh button accesskey
		'refreshButtonTabindex'				=> false,							# The form refresh button tabindex (if any)
		'refreshButtonImage'				=> false,							# Location of an image to replace the form refresh button
		'resetButton'						=> false,							# Whether the reset button is visible (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'resetButtonText'					=> 'Clear changes',					# The form reset button
		'resetButtonAccesskey'				=> 'r',								# The form reset button accesskey
		'resetButtonTabindex'				=> false,							# The form reset button tabindex (if any)
		'saveButton'						=> false,							# Whether the save button (i.e. save an incomplete form) is visible (note that this must be switched on in the template mode to appear, even if the save placemarker is given)
		'saveButtonText'					=> 'Save and continue later',		# The form save button
		'saveButtonAccesskey'				=> 'c',								# The form save button accesskey
		'saveButtonTabindex'				=> false,							# The form save button tabindex (if any)
		'warningMessage'					=> false,							# The form incompletion message (a specialised default is used)
		'requiredFieldIndicator'			=> true,							# Whether the required field indicator is to be displayed (top / bottom/true / false) (note that this must be switched on in the template mode to appear, even if the reset placemarker is given)
		'requiredFieldClass'				=> 'required',						# The CSS class used to mark a widget as required
		'submitTo'							=> false,							# The form processing location if being overriden
		'nullText'							=> 'Please select',					# The 'null' text for e.g. selection boxes
		'linebreaks' 						=> true,							# Widget-based linebreaks (top level default)
		'labelsSurround' 						=> false,							# Whether to use the surround method of label HTML formatting
		'opening'							=> false,							# Optional starting datetime as an SQL string
		'closing'							=> false,							# Optional closing datetime as an SQL string
		'validUsers'						=> false,							# Optional valid user(s) - if this is set, a user will be required. To set, specify string/array of valid user(s), or '*' to require any user
		'user'								=> false,							# Explicitly-supplied username (if none specified, will check for REMOTE_USER being set)
		'userKey'							=> false,							# Whether to log the username, as the key
		'loggedUserUnique'					=> false,							# Run in user-uniqueness mode, making the key of any CSV the username and checking for resubmissions
		'timestamping'						=> false,							# Add a timestamp to any CSV entry
		'ipLogging'							=> false,							# Add the user IP address to any CSV entry
		'escapeOutput'						=> false,							# Whether to escape output in the processing output ONLY (will not affect other types)
		'emailName'							=> 'Website feedback',				# Name string for emitted e-mails
		'emailIntroductoryText'				=> '',								# Introductory text for e-mail output type
		'emailShowFieldnames'				=> true,							# Whether to show the underlying fieldnames in the e-mail output type
		'confirmationEmailIntroductoryText'	=> '',								# Introductory text for confirmation e-mail output type
		'callback'							=> false,							# Callback function (string name) (NB cannot be $this->methodname) with one integer parameter, so be called just before emitting form HTML - -1 is errors on form, 0 is blank form, 1 is result presentation if any (not called at all if form not displayed)
		'databaseConnection'				=> false,							# Database connection (filename/array/object/resource)
		'truncate'							=> false,							# Whether to truncate the visible part of a widget (global setting)
		'listUnzippedFilesMaximum'			=> 5,								# When auto-unzipping an uploaded zip file, the maximum number of files contained that should be listed (beyond this, just 'x files' will be shown) in any visible result output
		'fixMailHeaders'					=> false,							# Whether to add additional mail headers, for use with a server that fails to add Message-Id/Date/Return-Path; set as (bool) true or (str) application name
		'size'								=> 50,								# Global setting for input widget - size
		'cols'								=> 30,								# Global setting for textarea cols - number of columns
		'rows'								=> 5,								# Global setting for textarea cols - number of rows
		'richtextEditorBasePath'			=> '/_ckeditor/',					# Global default setting for of the editor files
		'richtextEditorToolbarSet'			=> 'pureContent',					# Global default setting for richtext editor toolbar set
		'richtextEditorAreaCSS'				=> '',								# Global default setting for richtext editor CSS
		'richtextEditorConfig.docType'		=> '<!DOCTYPE html>',				# Global default setting for richtext editor config.docType
		'richtextWidth'						=> '100%',							# Global default setting for richtext width; assumed to be px unless % specified
		'richtextHeight'					=> 400,								# Global default setting for richtext height; assumed to be px unless % specified
		'richtextEditorFileBrowser'			=> '/_ckfinder/',					# Global default setting for richtext file browser path (must have trailing slash), or false to disable
		'richtextAutoembedKey'				=> false,							# Autoembed API key from IFramely
		'richtextTemplates'					=> false,							# Path to templates file, also settable on a per-widget basis
		'richtextSnippets'					=> false,							# Array of snippets, as array (title => HTML, ...)
		'mailAdminErrors'					=> false,							# Whether to mail the admin with any errors in the form setup
		'attachments'						=> false,							# Whether to send uploaded file(s) as attachment(s) (they will not be unzipped)
		'attachmentsMaxSize'				=> '10M',							# Total maximum attachment(s) size; attachments will be allowed into an e-mail until they reach this limit
		'attachmentsDeleteIfMailed'			=> true,							# Whether to delete the uploaded file(s) if successfully mailed
		'csvBom'							=> true,							# Whether to write a BOM at the start of a CSV file
		'ip'								=> true,							# Whether to expose the submitter's IP address in the e-mail output format
		'browser'							=> false,							# Whether to expose the submitter's browser (user-agent) string in the e-mail output format
		'passwordGeneratedLength'			=> 6,								# Length of a generated password
		'antispam'							=> false,							# Global setting for anti-spam checking
		'antispamRegexp'					=> '~(a href=|<a |<script|<url|\[link|\[url|Content-Type:)~DsiU',	# Regexp for antispam, in preg_match format
		'antispamUrlsThreshold'				=> 5,								# Number of URLs in a textarea which will trigger an antispam check failure
		'akismetApiKey'						=> false,							# Akismet developer API key, available from https://akismet.com/development/api/
		'applicationName'					=> false,							# Application name
		'picker'							=> false,							# Whether to use the date picker by default when creating date widgets
		'directoryPermissions'				=> 0775,							# Permission setting used for creating new directories
		'prefixedGroupsFilterEmpty'			=> false,							# Whether to filter out empty groups when using group prefixing in dataBinding; currently limited to detecting scalar types only
		'unsavedDataProtection'				=> false,							# Add DHTML to give a warning about unsaved form data if navigating away from the page (false/true/text)
		'jQuery'							=> true,							# If using DHTML features, where to load jQuery from (true = default, or false if already loaded elsewhere on the page)
		'jQueryUi'							=> true,							# If using DHTML features, where to load jQueryUi from (currently only true/false are supported)
		'scripts'							=> false,							# Where to load GitHub files from; false = use default, string = library files in this URL/path location
		'autofocus'							=> false,							# Place HTML5 autofocus on the first widget (true/false)
		'reorderableRows'					=> false,							# Whether to enable drag-and-drop reorderability of rows
		'errorsCssClass'					=> 'error',							# CSS class for div of errors box
		'uploadThumbnailWidth'				=> 300,								# Default upload thumbnail box width
		'uploadThumbnailHeight'				=> 300,								# Default upload thumbnail box height
		'redirectGet'						=> false,							# On successful submission, redirect, simplifying with non-empty values as GET parameters
		#!# This should be made automatic, once the system is used to a parse-settings-then-render pattern
		'enableNativeRequired'				=> false,							# Whether to enable native HTML5 required attributes; this should be disabled when using Save and continue or expandable
	);
	
	
	## Load initial state and assign settings ##
	
	/**
	 * Constructor
	 * @param array $arguments Settings
	 */
	function __construct ($suppliedArguments = array ())
	{
		# Load the application support library which itself requires the pureContent framework file, pureContent.php; this will clean up $_SERVER
		require_once ('application.php');
		
		# Assign constants
		$this->timestamp = date ('Y-m-d H:i:s');
		
		# Import supplied arguments to assign defaults against specified ones available
		foreach ($this->argumentDefaults as $argument => $defaultValue) {
			$this->settings[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
		}
		
		# Set up and check any database connection
		$this->_setupDatabaseConnection ();
		
		# Determine the method in use
		$this->method = ($this->settings['get'] ? 'get' : 'post');
		
		# Define the submission location (as _SERVER cannot be set in a class variable declaration); PATH_INFO attacks (see: http://forum.hardened-php.net/viewtopic.php?id=20 ) are not relevant here for this form usage
		if ($this->settings['submitTo'] === false) {$this->settings['submitTo'] = ($this->method == 'get' ? $_SERVER['SCRIPT_NAME'] : $_SERVER['REQUEST_URI']);}
		
		# Ensure the userlist is an array, whether empty or otherwise
		$this->settings['validUsers'] = application::ensureArray ($this->settings['validUsers']);
		
		# If no user is supplied, attempt to obtain the REMOTE_USER (if one exists) as the default
		if (!$this->settings['user']) {$this->settings['user'] = (isSet ($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : false);}
		
		# Determine the variables collection in use - $_GET or $_POST
		$this->collection = ($this->method == 'get' ? $_GET : $_POST);
		
		# If there are files posted, merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
		$this->mergeFilesIntoPost ();
		
		# Assign whether the form has been posted or not
		$this->formPosted = ($this->settings['name'] ? (isSet ($this->collection[$this->settings['name']])) : !empty ($this->collection));
		
		# Add in the hidden security fields if required, having verified username existence if relevant; these need to go at the start so that any username is set as the key
		$this->addHiddenSecurityFields ();
		
		# Import the posted data if the form is posted; this has to be done initially otherwise the input widgets won't have anything to reference
		if ($this->formPosted) {$this->form = ($this->settings['name'] ? $this->collection[$this->settings['name']] : $this->collection);}
	}
	
	
	## Supported form widget types ##
	
	
	/**
	 * Create a standard input widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function input ($suppliedArguments, $functionName = __FUNCTION__)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'expandable'			=> false,	# Whether the widget can be expanded into subwidgets (whose value is imploded in the result), whose number can be incremented by pressing a + button; either false / true (separator=\n) / separator string
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false) [ignored for e-mail type]
			'size'					=> $this->settings['size'],		# Visible size (optional; defaults to 60)
			'minlength'				=> '',		# Minimum length (optional; defaults to no limit)
			'maxlength'				=> '',		# Maximum length (optional; defaults to no limit)
			// 'min'	 	- implemented below
			// 'max'	 	- implemented below
			// 'step'	 	- implemented below
			// 'roundFloat'	- implemented below; Whether to auto-round a float to the specified number of digits after a decimal point; e.g. 3 would change 0.4567 to 0.457
			'placeholder'			=> '',		# HTML5 placeholder text
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Case-sensitive regular expression against which the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression against which the submission must validate
			'url'					=> false,	# Turns the widget into a URL field where a HEAD request is made to check that the URL exists; either true (which means 200, 302, 304) or a list like array (200, 301, 302, 304, )
			'retrieval'				=> false,	# Turns the widget into a URL field where the specified page/file is then retrieved and saved to the directory stated
			'disallow'				=> false,	# Regular expression against which the submission must not validate
			'antispam'				=> $this->settings['antispam'],		# Whether to switch on anti-spam checking
			'current'				=> false,	# List of current values against which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'confirmation'			=> false,	# Whether to generate a confirmation field
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'multiple'				=> false,	# For e-mail types only: whether the field can accept multiple e-mail addresses (separated with comma-space)
			'autocomplete'			=> false,	# URL of data provider
			'autocompleteOptions'	=> false,	# Autocomplete options; see: http://jqueryui.com/demos/autocomplete/#remote (this is the new plugin)
			'tags'					=> false,	# Tags mode
			'entities'				=> true,	# Convert HTML in value (useful only for editable=false)
			'displayedValue'		=> false,	# When using editable=false, optional text that should be displayed instead of the value; can be made into HTML using entities=false
			'antispamWait'			=> false,	# Antispam wait in the event of any failure
			'_cssHide--DONOTUSETHISFLAGEXTERNALLY'		=> false,	# DO NOT USE - this is present for internal use only and exists prior to refactoring
			'_visible--DONOTUSETHISFLAGEXTERNALLY'		=> true,	# DO NOT USE - this is present for internal use only and exists prior to refactoring
		);
		
		# Add in password-specific defaults
		#!# These blocks ought to be specifiable in the native password()/email()/etc. functions
		if ($functionName == 'password') {
			$argumentDefaults['generate'] = false;		# Whether to generate a password if no value supplied as default
			$argumentDefaults['confirmation'] = false;	# Whether to generate a second confirmation password field
		}
		
		# Add in email-specific defaults
		if ($functionName == 'email') {
			$argumentDefaults['confirmation'] = false;	# Whether to generate a second confirmation e-mail field
		} else {
			$argumentDefaults['multiple'] = false;	# Ensure this option is disabled for non-email types
		}
		
		# Add in URL-specific defaults
		if ($functionName == 'url') {
			$argumentDefaults['regexpi'] = '^(http|https)://(.+)\.(.+)';
		}
		
		# Add in Number-specific defaults
		#!# This needs to have min/max/step value validation and restriction text, and make enforceNumeric set numeric
		if (($functionName == 'number') || ($functionName == 'range')) {
			$argumentDefaults['min'] = false;
			$argumentDefaults['max'] = false;
			$argumentDefaults['step'] = false;
			$argumentDefaults['roundFloat'] = false;
		}
		
		# If an element is expandable, if it is boolean true, convert to default string
		if (isSet ($suppliedArguments['expandable'])) {
			if ($suppliedArguments['expandable'] === true) {
				$suppliedArguments['expandable'] = "\n";
			}
		}
		
		# Add a regexp check if using URL handling (retrieval or URL HEAD check)
		#!# This change in v. 1.13.16 of moving this before the arguments are set, because the defaults get amended, points to the need for auditing of similar cases in case they are not being amended
		if ((isSet ($suppliedArguments['retrieval']) && $suppliedArguments['retrieval']) || (isSet ($suppliedArguments['url']) && $suppliedArguments['url'])) {
			
			# If no regexp has been set, add a basic URL syntax check
			#!# Ideally this should be replaced when multiple regexps allowed
			if (empty ($suppliedArguments['regexp']) && empty ($suppliedArguments['regexpi'])) {
				if (!extension_loaded ('openssl')) {
					$this->formSetupErrors['urlHttps'] = 'URL handling has been requested but the OpenSSL extension is not loaded, meaning that https requests will fail. Either compile in the OpenSSL module, or explicitly set the regexpi for the field.';
				} else {
					$argumentDefaults['regexpi'] = '^(http|https)://(.+)\.(.+)';
				}
			}
		}
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, $functionName);
		
		$arguments = $widget->getArguments ();
		
		# Generate an initial password if required and no default supplied
		if (($functionName == 'password') && $arguments['generate'] && !$arguments['default']) {
			$length = (is_numeric ($arguments['generate']) ? $arguments['generate'] : $this->settings['passwordGeneratedLength']);
			$arguments['default'] = application::generatePassword ($length);
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# If a confirmation field is required, generate it (first) and convert the original one (second) to the confirmation type
		if ($arguments['confirmation'] && $arguments['editable']) {
			if (($functionName == 'password') || ($functionName == 'email')) {
				$arguments['confirmation'] = false;	// Prevent circular reference
				$this->$functionName ($arguments);
				$originalName = $arguments['name'];
				#!# Need to deny this as a valid name elsewhere
				$arguments['name'] .= '__confirmation';
				$arguments['title'] .= ' (confirmation)';
				$arguments['description'] = 'Please retype to confirm.';
				$arguments['discard'] = true;
				$arguments['autofocus'] = false;
				$this->validation ('same', array ($originalName, $arguments['name']));
			}
		}
		
		# Determine the number of subwidgets needed, based on the default supplied value
		$subwidgets = 1;
		if ($arguments['expandable']) {
			$expandableSeparator = $suppliedArguments['expandable'];	// Copy to clearly-named variable
			$subwidgetElementValues = explode ($expandableSeparator, trim ($arguments['default']));
			$subwidgetsDefault = count ($subwidgetElementValues);
			$subwidgets = $this->subwidgetExpandabilityCount ($subwidgetsDefault, $arguments['name'], $arguments['required'], $arguments['autofocus'] /* passed (and altered) by reference */);
		}
		
		# For an expandable element, create the value by imploding the values in the subwidgets
		if ($arguments['expandable']) {
			if ($this->formPosted) {
				$subwidgetElementValues = array ();
				for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
					$subwidgetName = $arguments['name'] . "_{$subwidget}";
					$subwidgetElementValues[] = (isSet ($this->form[$subwidgetName]) ? $this->form[$subwidgetName] : '');
				}
				#!# In the final submission, this ought to remove missing elements in the middle of a set of subwidgets, but currently no way to determine what is the final submission
				$value = implode ($expandableSeparator, $subwidgetElementValues);
			} else {
				$value = '';
			}
		} else {
			$value = (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		}
		
		# Auto-round floats if required
		#!# No support yet for expandable
		if (isSet ($arguments['roundFloat'])) {
			if ($value != '') {
				$value = round ($value, 6);
			}
		}
		
		# Set the value
		$widget->setValue ($value);
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Prevent multi-line submissions
		$widget->preventMultilineSubmissions ();
		
		# Run minlength checking
		$widget->checkMinLength ();
		
		# Run maxlength checking
		$widget->checkMaxLength ();
		
		# Perform pattern checks
		$regexpCheck = $widget->regexpCheck ();
		
		# Clean to numeric if required
		$widget->cleanToNumeric ();
		
		# Perform antispam checks
		$widget->antispamCheck ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		# Add autocomplete functionality if required
		$widget->autocomplete ($arguments, ($arguments['expandable'] ? $subwidgets : false));
		
		# Add tags functionality if required
		$widget->tags ();
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['enforceNumeric'] && ($functionName != 'email')) {$restriction = 'Must be numeric';}
		if ($functionName == 'email') {$restriction = 'Must be valid';}
		if (($arguments['regexp'] || $arguments['regexpi']) && ($functionName != 'email') && ($functionName != 'url')) {$restriction = 'A specific pattern is required';}
		
		# Add a regexp check if using URL handling (retrieval or URL HEAD check)
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Do retrieval if required
		if (($arguments['retrieval'] || $arguments['url']) && $this->form[$arguments['name']] && !$widget->getElementProblems (false)) {
			
			# Do not use with e-mail/password types
			if ($functionName != 'input') {
				$this->formSetupErrors['urlHandlingInputOnly'] = 'URL handling can only be used on a standard input field type.';
				$arguments['retrieval'] = false;
			}
			
			# Do not use with e-mail/password types
			if (!ini_get ('allow_url_fopen')) {
				$this->formSetupErrors['urlHandlingAllowUrlFopenOff'] = 'URL handling cannot be done as the server configuration disallows external file opening.';
			}
			
			# Check that the selected directory exists and is writable (or create it)
			if ($arguments['retrieval']) {
				if (!is_dir ($arguments['retrieval'])) {
					if (!application::directoryIsWritable ($arguments['retrieval'])) {
						$this->formSetupErrors['urlHandlingDirectoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> input URL-retrieval element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
						$arguments['retrieval'] = false;
					} else {
						#!# Third parameter doesn't exist in PHP4 - will this cause a crash?
						umask (0);
						mkdir ($arguments['retrieval'], $this->settings['directoryPermissions'], $recursive = true);
					}
				}
			}
		}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			if ($arguments['expandable']) {
				
				# Generate the subwidgets HTML
				$values = explode ($expandableSeparator, $this->form[$arguments['name']]);
				$subwidgetsHtml = array ();
				for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
					$subwidgetName = $arguments['name'] . "_{$subwidget}";
					$subwidgetElementValue = (isSet ($values[$subwidget]) ? $values[$subwidget] : '');
					$hasAutofocus = ($arguments['autofocus'] === false ? false : (($subwidget + 1) == $arguments['autofocus']));	// $arguments['autofocus'] will be either false or numeric 1...$subwidgets
					#!# Step formatting can end up with exponent if set to 0.00001 or lower; casting as (string) has no effect
					$subwidgetsHtml[$subwidget] = '<input' . $this->nameIdHtml ($subwidgetName) . ' type="' . ($functionName == 'input' ? 'text' : $functionName) . "\" size=\"{$arguments['size']}\"" . ($arguments['maxlength'] != '' ? " maxlength=\"{$arguments['maxlength']}\"" : '') . ($arguments['placeholder'] != '' ? " placeholder=\"{$arguments['placeholder']}\"" : '') . ((isSet ($arguments['min']) && $arguments['min'] !== false) ? " min=\"{$arguments['min']}\"" : '') . ((isSet ($arguments['max']) && $arguments['max'] !== false) ? " max=\"{$arguments['max']}\"" : '') . ((isSet ($arguments['step']) && $arguments['step'] !== false) ? ' step="' . $arguments['step'] . '"' : '') . ($hasAutofocus ? ' autofocus="autofocus"' : '') . ($arguments['multiple'] ? ' multiple="multiple"' : '') . " value=\"" . htmlspecialchars ($subwidgetElementValue) . '"' . $widget->tabindexHtml () . ' />';
					if ($hasAutofocus) {
						$arguments['autofocus'] = false;	// Ensure only one subwidget has autofocus
						$this->clearAnyOtherAutofocus ();
					}
				}
				$widgetHtml = "\n\t\t\t" . implode ("<br />\n\t\t\t", $subwidgetsHtml) . "\n\t\t";
				
				# Add add/subtract button(s)
				if ($arguments['expandable']) {
					$refreshButtonHtml = $this->subwidgetExpandabilityButtons ($subwidgets, $arguments['name'], $arguments['required']);
					$arguments['append'] = $refreshButtonHtml . $arguments['append'];
				}
				
			} else {
				$widgetHtml = '<input' . $this->nameIdHtml ($arguments['name']) . ' type="' . ($functionName == 'input' ? 'text' : $functionName) . "\" size=\"{$arguments['size']}\"" . ($arguments['maxlength'] != '' ? " maxlength=\"{$arguments['maxlength']}\"" : '') . ($this->settings['enableNativeRequired'] && $arguments['required'] ? ' required="required"' : '') . ($arguments['placeholder'] != '' ? " placeholder=\"{$arguments['placeholder']}\"" : '') . ((isSet ($arguments['min']) && $arguments['min'] !== false) ? " min=\"{$arguments['min']}\"" : '') . ((isSet ($arguments['max']) && $arguments['max'] !== false) ? " max=\"{$arguments['max']}\"" : '') . ((isSet ($arguments['step']) && $arguments['step'] !== false) ? ' step="' . $arguments['step'] . '"' : '') . ($arguments['autofocus'] ? ' autofocus="autofocus"' : '') . ($arguments['multiple'] ? ' multiple="multiple"' : '') . " value=\"" . htmlspecialchars ($this->form[$arguments['name']]) . '"' . $widget->tabindexHtml () . ' />';
			}
		} else {
			$displayedValue = ($arguments['displayedValue'] ? $arguments['displayedValue'] : $this->form[$arguments['name']]);
			$widgetHtml  = ($functionName == 'password' ? str_repeat ('*', strlen ($arguments['default'])) : ($arguments['entities'] ? htmlspecialchars ($displayedValue) : $displayedValue));
			#!# Change to registering hidden internally
			$hiddenInput = '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
			$widgetHtml .= $hiddenInput;
		}
		
		# Get the posted data
		if ($this->formPosted) {
			if ($functionName == 'password') {
				$data['compiled'] = $this->form[$arguments['name']];
				$data['presented'] = str_repeat ('*', strlen ($this->form[$arguments['name']]));
			} else {
				$data['presented'] = $this->form[$arguments['name']];
			}
			
			# Do URL retrieval if OK
			#!# This ought to be like doUploads, which is run only at the end
			if ($arguments['retrieval'] && $regexpCheck) {
				$saveLocation = $arguments['retrieval'] . basename ($elementValue);
				#!# This next line should be replaced with some variant of urlencode that doesn't swallow / or :
				$elementValue = str_replace (' ', '%20', $elementValue);
				if (!$fileContents = @file_get_contents ($elementValue)) {
					$elementProblems['retrievalFailure'] = "URL retrieval failed; possibly the URL you quoted does not exist, or the server is blocking file downloads somehow.";
				} else {
					file_put_contents ($saveLocation, $fileContents);
				}
			}
			
			# Do URL HEAD request if required and if the regexp check has passed
			if ($arguments['url'] && $regexpCheck) {
				$urlOk = false;
				$response = false;
				if ($headers = get_headers ($elementValue)) {
					$response = $headers[0];
					if (preg_match ('/ ([0-9]+) /', $response, $matches)) {
						$httpResponse = $matches[1];
						$validResponses = (is_array ($arguments['url']) ? $arguments['url'] : array (200 /* OK */, 302 /* Found */, 304 /* Not Modified */));	// See http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html for responses
						if (in_array ($httpResponse, $validResponses)) {
							$urlOk = true;
						}
					}
				}
				if (!$urlOk) {
					$elementProblems['urlFailure'] = "URL check failed; possibly the URL you quoted does not exist or has a redirection in place." . ($response ? ' The response from the site was: <em>' . htmlspecialchars ($response) . '</em>.' : '') . ' Please check the URL carefully and retry.';
				}
			}
		}
		
		# Check for element problems
		$problems = $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false);
		
		# Add antispam wait if any failured occured
		if ($arguments['antispamWait']) {
			if ($problems) {
				$this->antispamWait += $arguments['antispamWait'];
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => $functionName,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $problems,
			'required' => $arguments['required'],
			'requiredButEmpty' => $widget->requiredButEmpty (),
			'suitableAsEmailTarget' => ($functionName == 'email'),
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR(' . ($arguments['maxlength'] ? $arguments['maxlength'] : '255') . ')') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => ($functionName == 'password' ? 'compiled' : false),
			'after' => $arguments['after'],
			'_cssHide--DONOTUSETHISFLAGEXTERNALLY' => $arguments['_cssHide--DONOTUSETHISFLAGEXTERNALLY'],
		);
		
		#!# Temporary hacking to add hidden widgets when using the _hidden type in dataBinding
		if (!$arguments['_visible--DONOTUSETHISFLAGEXTERNALLY']) {
			$this->elements[$arguments['name']]['_visible--DONOTUSETHISFLAGEXTERNALLY'] = $hiddenInput;
		}
	}
	
	
	# Function to clear any existing autofocus
	#!# This is extremely hacky and not ideal; it relies on ' autofocus="autofocus" not being naturally present; in practice this is a safe assumption
	private function clearAnyOtherAutofocus ()
	{
		# Retrospectively re-write any already-generated element having autofocus
		foreach ($this->elements as $name => $attributes) {
			$this->elements[$name]['html'] = str_replace (' autofocus="autofocus"', '', $this->elements[$name]['html']);
		}
	}
	
	
	/**
	 * Create a password widget (same as an input widget but using the HTML 'password' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function password ($suppliedArguments)
	{
		# Pass through to the standard input widget, but in password mode
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create an input field requiring a syntactically valid e-mail address; if a more specific e-mail validation is required, use $form->input and supply an e-mail validation regexp
	 * @param array $arguments Supplied arguments same as input type, but enforceNumeric and regexp ignored
	 */
	function email ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a URL widget (same as an input widget but using the HTML5 'url' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function url ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a Tel widget (same as an input widget but using the HTML5 'tel' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function tel ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a Search widget (same as an input widget but using the HTML5 'search' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function search ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a Number widget (same as an input widget but using the HTML5 'number' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function number ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a Range widget (same as an input widget but using the HTML5 'range' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function range ($suppliedArguments)
	{
		# Pass through to the standard input widget
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a Color widget (same as an input widget but using the HTML5 'color' type)
	 * @param array $arguments Supplied arguments same as input type
	 */
	function color ($suppliedArguments)
	{
		# Pass through to the standard input widget, but in password mode
		$this->input ($suppliedArguments, __FUNCTION__);
	}
	
	
	/**
	 * Create a textarea box
	 * @param array $arguments Supplied arguments - see template
	 */
	function textarea ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'enforceNumeric'		=> false,	# Whether to enforce numeric input or not (optional; defaults to false)
			'cols'					=> $this->settings['cols'],		# Number of columns (optional; defaults to 30)
			'rows'					=> $this->settings['rows'],		# Number of rows (optional; defaults to 5)
			'wrap'					=> false,	# Value for non-standard 'wrap' attribute
			'placeholder'			=> '',		# HTML5 placeholder text
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> '',		# Default value (optional)
			'regexp'				=> '',		# Case-sensitive regular expression(s) against which all lines of the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression(s) against which all lines of the submission must validate
			'disallow'				=> false,	# Regular expression against which all lines of the submission must not validate
			'mustContain'			=> false,	# String or array of strings that the submission must contain
			'antispam'				=> $this->settings['antispam'],		# Whether to switch on anti-spam checking
			'current'				=> false,	# List of current values which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'mode'					=> 'normal',	# Special mode: normal/lines/coordinates
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'minlength'				=> false,	# Minimum number of characters allowed
			'maxlength'				=> false,	# Maximum number of characters allowed
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'autocomplete'			=> false,	# URL of data provider
			'autocompleteOptions'	=> false,	# Autocomplete options; see: http://jqueryui.com/demos/autocomplete/#remote (this is the new plugin)
			'autocompleteTokenised'	=> false,	# URL of data provider
			'entities'				=> true,	# Convert HTML in value (useful only for editable=false)
			'displayedValue'		=> false,	# When using editable=false, optional text that should be displayed instead of the value; can be made into HTML using entities=false
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Start a list of restrictions
		$restrictions = array ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		#!# Policy issue of whether this should apply on a per-line basis
		$widget->handleWhiteSpace ();
		
		# Clean to numeric if required
		$widget->cleanToNumeric ();
		
		# Enable minlength checking
		$widget->checkMinLength ();
		if (is_numeric ($arguments['minlength'])) {
			$restrictions[] = 'At least ' . number_format ($arguments['minlength']) . ' characters';
		}
		
		# Enable maxlength checking
		$widget->checkMaxLength ();
		if (is_numeric ($arguments['maxlength'])) {
			$restrictions[] = 'Maximum ' . number_format ($arguments['maxlength']) . ' characters, inc. spaces';
		}
		
		# Add jQuery-based checking of maxlength
		if ($arguments['maxlength']) {
			$id = $this->cleanId ("{$this->settings['name']}[{$arguments['name']}]");
			$this->maxLengthJQuery ($id, $arguments['maxlength']);
		}
		
		# Add autocomplete functionality if required
		$widget->autocomplete ($arguments);
		$widget->autocompleteTokenised ($singleLine = false);
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		# Perform antispam checks
		$widget->antispamCheck ();
		
		$elementValue = $widget->getValue ();
		
		# Perform validity tests if anything has been submitted and regexp(s)/disallow are supplied
		#!# Refactor into the widget class by adding multiline capability
		if ($elementValue && ($arguments['regexp'] || $arguments['regexpi'] || $arguments['disallow'] || $arguments['mode'] == 'coordinates')) {
			
			# Branch a copy of the data as an array, split by the newline and check it is complete
			$lines = explode ("\n", $elementValue);
			
			# Split each line into two fields and loop through each line to deal with a mid-line split
			$i = 0;
			foreach ($lines as $line) {
				$i++;
				
				# Trim each line for testing
				$line = trim ($line);
				
				# Add a test for whitespace in coordinates mode
				if ($arguments['mode'] == 'coordinates') {
					if (!preg_match ("/\s/i", $line)) {
						$problemLines[] = $i;
						continue;
					}
				}
				
				# If the line does not validate against a specified regexp, add the line to a list of lines containing a problem then move onto the next line
				if ($arguments['regexp'] || $arguments['regexpi']) {
					if ($arguments['regexp'] && (!application::pereg ($arguments['regexp'], $line))) {
						$problemLines[] = $i;
						continue;
					} else if ($arguments['regexpi'] && (!application::peregi ($arguments['regexpi'], $line))) {
						$problemLines[] = $i;
						continue;
					}
				}
				
				# If the line does not validate against a specified disallow, add the line to a list of lines containing a problem then move onto the next line
				#!# Merge this with formWidget->regexpCheck ()
				#!# Consider allowing multiple disallows, even though a regexp can deal with that anyway
				if ($arguments['disallow']) {
					$disallowRegexp = $arguments['disallow'];
					if (is_array ($arguments['disallow'])) {
						foreach ($arguments['disallow'] as $disallowRegexp => $disallowErrorMessage) {
							break;
						}
					}
					if (application::pereg ($disallowRegexp, $line)) {
						$disallowProblemLines[] = $i;
						continue;
					}
				}
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($problemLines)) {
				$elementProblems['failsRegexp'] = (count ($problemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $problemLines) . (count ($problemLines) > 1 ? ' do not' : ' does not') . ' match a specified pattern required for this section' . (($arguments['mode'] == 'coordinates') ? ', ' . (($arguments['regexp'] || $arguments['regexpi']) ? 'including' : 'namely' ) . ' the need for two co-ordinates per line' : '') . '.';
			}
			
			# If any problem lines are found, construct the error message for this
			if (isSet ($disallowProblemLines)) {
				$elementProblems['failsDisallow'] = (isSet ($disallowErrorMessage) ? $disallowErrorMessage : (count ($disallowProblemLines) > 1 ? 'Rows ' : 'Row ') . implode (', ', $disallowProblemLines) . (count ($disallowProblemLines) > 1 ? ' match' : ' matches') . ' a specified disallowed pattern for this section.');
			}
		}
		
		# Do checks to ensure a string / list of strings are present if required
		if ($elementValue && $arguments['mustContain']) {
			$arguments['mustContain'] = application::ensureArray ($arguments['mustContain']);
			$notFound = array ();
			foreach ($arguments['mustContain'] as $mustContain) {
				if (!substr_count ($elementValue, $mustContain)) {
					$notFound[] = htmlspecialchars ($mustContain);
				}
			}
			if ($notFound) {
				$elementProblems['mustContain'] = 'The ' . (count ($notFound) == 1 ? 'string' : 'strings') . ' <em>' . implode ("</em>, <em>", $notFound) . '</em> must be contained in the text but ' . (count ($mustContain) == 1 ? 'was' : 'were') . ' not found.';
			}
		}
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		#!# Regexp not being listed
		switch ($arguments['mode']) {
			case 'lines':
				#!# Lines possibly currently allows through empty lines
				$restrictions[] = 'Must have one numeric item per line';
				break;
			case 'coordinates':
				$restrictions[] = 'Must have two numeric items (x,y) per line';
				break;
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			$widgetHtml  = '';
			if ($arguments['maxlength']) {
				$widgetHtml .= '<div' . $this->nameIdHtml ($arguments['name'], false, false, false, $idOnly = true, '__info') . ' class="charactersremaininginfo"></div>';
			}
			$widgetHtml .= '<textarea' . $this->nameIdHtml ($arguments['name']) . " cols=\"{$arguments['cols']}\" rows=\"{$arguments['rows']}\"" . ($arguments['maxlength'] ? " maxlength=\"{$arguments['maxlength']}\"" : '') . ($arguments['wrap'] ? " wrap=\"{$arguments['wrap']}\"" : '') . ($arguments['autofocus'] ? ' autofocus="autofocus"' : '') . ($this->settings['enableNativeRequired'] && $arguments['required'] ? ' required="required"' : '') . ($arguments['placeholder'] != '' ? " placeholder=\"{$arguments['placeholder']}\"" : '') . $widget->tabindexHtml () . '>' . htmlspecialchars ($this->form[$arguments['name']]) . '</textarea>';
		} else {
			if ($arguments['displayedValue']) {
				$widgetHtml  = ($arguments['entities'] ? htmlspecialchars ($arguments['displayedValue']) : $arguments['displayedValue']);
			} else {
				$widgetHtml  = str_replace ("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', nl2br (htmlspecialchars ($this->form[$arguments['name']])));
			}
			$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		}
		
		# Get the posted data
		if ($this->formPosted) {
			
			# For presented, assign the raw data directly to the output array
			$data['presented'] = $this->form[$arguments['name']];
			
			# For raw components:
			switch ($arguments['mode']) {
					case 'coordinates':
					
					# For the raw components version, split by the newline then by the whitespace (ensuring that whitespace exists, to prevent undefined offsets), presented as an array (x, y)
					$lines = explode ("\n", $this->form[$arguments['name']]);
					foreach ($lines as $autonumber => $line) {
						if (!substr_count ($line, ' ')) {$line .= ' ';}
						list ($data['rawcomponents'][$autonumber]['x'], $data['rawcomponents'][$autonumber]['y']) = explode (' ', $line);
						ksort ($data['rawcomponents'][$autonumber]);
					}
					break;
				case 'lines':
					# For the raw components version, split by the newline
					$data['rawcomponents'] = explode ("\n", $this->form[$arguments['name']]);
					foreach ($data['rawcomponents'] as $index => $line) {
						$data['rawcomponents'][$index] = trim ($line);
					}
					break;
					
				default:
					# Assign the raw data directly to the output array
					$data['rawcomponents'] = $this->form[$arguments['name']];
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => ($restrictions && $arguments['editable'] ? implode ('; ', $restrictions) : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'BLOB') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'after' => $arguments['after'],
		);
	}
	
	
	
	/**
	 * Create a richtext editor field based on CKEditor
	 * @param array $arguments Supplied arguments - see template
	 */
	# Note: make sure php_value file_uploads is on in the upload location!
	function richtext ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> NULL,		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'regexp'				=> '',		# Case-sensitive regular expression against which the submission must validate
			'regexpi'				=> '',		# Case-insensitive regular expression against which the submission must validate
			'disallow'				=> false,		# Regular expression against which the submission must not validate
			'maxlength'				=> false,	# Maximum number of characters allowed, after HTML markup stripped
			'current'				=> false,	# List of current values which the submitted value must not match
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> '',		# Default value (optional)
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'editorBasePath'					=> $this->settings['richtextEditorBasePath'],	# Location of the editor files
			'editorToolbarSet'					=> $this->settings['richtextEditorToolbarSet'],
			'editorDefaultTableClass'			=> 'lines',
			'editorFileBrowser'					=> $this->settings['richtextEditorFileBrowser'],	// Path (must have trailing slash), or false to disable
			'editorFileBrowserStartupPath'		=> '/',
			'editorFileBrowserACL'				=> false,
			'templates'							=> $this->settings['richtextTemplates'],
			'snippets'							=> $this->settings['richtextSnippets'],
			'width'								=> $this->settings['richtextWidth'],			// Same as config.width
			'height'							=> $this->settings['richtextHeight'],			// Same as config.height
			'config.width'						=> false,										// Takes precedence if 'width' also specified
			'config.height'						=> false,										// Takes precedence if 'height' also specified
			'config.contentsCss'				=> $this->settings['richtextEditorAreaCSS'],	// Or array of stylesheets
			'config.skin'						=> 'moonocolor',								// NB Requires download from http://ckeditor.com/addon/moonocolor
			'config.bodyId'						=> false,										// Apply value of <body id="..."> to editing window
			'config.bodyClass'					=> false,										// Apply value of <body class="..."> to editing window
			'config.format_tags'				=> 'p;h1;h2;h3;h4;h5;h6;pre',
			'config.stylesSet'					=> "[
				{name: 'Warning style (paragraph)', element: 'p', attributes: {'class': 'warning'}},
				{name: 'Success style (paragraph)', element: 'p', attributes: {'class': 'success'}},
				{name: 'Comment text (paragraph)', element: 'p', attributes: {'class': 'comment'}},
				{name: 'Heavily-faded text (paragraph)', element: 'p', attributes: {'class': 'faded'}},
				{name: 'Right-aligned (paragraph)', element: 'p', attributes: {'class': 'alignright'}},
				{name: 'Signature (paragraph)', element: 'p', attributes: {'class': 'signature'}},
				{name: 'Smaller text (paragraph)', element: 'p', attributes: {'class': 'small'}}
			]",
			'config.protectedSource'			=> "[ '/<\?[\s\S]*?\?>/g' ]",					// Protect PHP code
			'config.disableNativeSpellChecker'	=> false,								// Disables the built-in spell checker if the browser provides one
			'config.allowedContent'				=> true,										// http://docs.ckeditor.com/#!/api/CKEDITOR.config-cfg-allowedContent
			'config.docType'					=> $this->settings['richtextEditorConfig.docType'],	// http://docs.ckeditor.com/#!/api/CKEDITOR.config-cfg-docType
			'allowCurlyQuotes'		=> false,
			'protectEmailAddresses'	=> true,	// Whether to obfuscate e-mail addresses
			'externalLinksTarget'	=> '_blank',	// The window target name which will be instanted for external links or false
			'directoryIndex'		=> 'index.html',		// Default directory index name
			'imageAlignmentByClass'	=> true,		// Replace align="foo" with class="foo" for images
			'nofixTag'				=> '<!-- nofix -->',	// Special marker which indicates that the HTML should not be cleaned (or false to disable)
			'removeComments'		=> true,
			'replacements'			=> array (),	// Regexp replacements to add before standard replacements are done
			'after'					=> false,	# Placing the widget after a specific other widget
			'noClickHere'				=> true,	# Disallow 'click here' and 'here' as link text
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		# Handle whitespace issues
		$widget->handleWhiteSpace ();
		
		# Perform pattern checks
		$widget->regexpCheck ();
		
		# Check whether the field satisfies any requirement for a field to be required
		$requiredButEmpty = $widget->requiredButEmpty ();
		
		# Perform uniqueness check
		$widget->uniquenessCheck ();
		
		# Enable maxlength checking
		$widget->checkMaxLength ($stripHtml = true);
		if (is_numeric ($arguments['maxlength'])) {
			$restrictions[] = 'Maximum ' . number_format ($arguments['maxlength']) . ' characters';
		}
		
		$elementValue = $widget->getValue ();
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid), or clean it if posted
		$elementValue = (!$this->formPosted ? $arguments['default'] : $this->richtextClean ($this->form[$arguments['name']], $arguments, $arguments['nofixTag'], 'utf8', $arguments['allowCurlyQuotes']));
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Determine the ID of the element
			$id = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']);
			
			# Clone HTML5 autofocus into the manual CKEditor config
			if ($arguments['autofocus']) {
				$arguments['config.startupFocus'] = true;
			}
			
			# Clone width/height; the config.* one is more specific and will take priority (both are available merely for consistency with both the ultimateForm and CKEditor APIs
			if ($arguments['width']) {
				$arguments['config.width'] = ($arguments['config.width'] ? $arguments['config.width'] : $arguments['width']);
			}
			if ($arguments['height']) {
				$arguments['config.height'] = ($arguments['config.height'] ? $arguments['config.height'] : $arguments['height']);
			}
			
			#!# Enable native support for protectedSource
			
			#!# Image caption and dragging in Chrome: http://ckeditor.com/addon/image2
			
			#!# Keyboard focus bug: http://dev.ckeditor.com/ticket/12259
			
			#!# Clash-renaming feature needed in uploader; see older implementation: http://dev.ckeditor.com/ticket/1651
			
			# Provide pre-configured toolbars
			if ($arguments['editorToolbarSet']) {
				
				# Define available pre-configured toolbars; see: http://ckeditor.com/latest/samples/plugins/toolbar/toolbar.html
				$toolbars = array (
					
					# Do not specify any setting, so that the CKEditor default is used
					'default' => false,		// Will create what is shown at http://ckeditor.com/latest/samples/plugins/toolbar/toolbar.html
					
					# pureContent - cut-down, predominantly semantic toolbar
					'pureContent' => "
						[
							['Templates'],
							['divs'],
							['Cut','Copy','Paste','PasteText','PasteWord','-',],
							['Undo','Redo','-','Find','Replace','-','SelectAll'],
							['Scayt'],
							['Maximize'],
							['Source'],
							['About'],
							'/',
							['BulletedList','NumberedList','-','Outdent','Indent','Blockquote'],
							['Subscript','Superscript','SpecialChar'],
							['HorizontalRule'],
							['ShowBlocks','CreateDiv','Iframe'],
							['Table'],
							['Link','Unlink','Anchor'],
							['Html5video'],
							['Youtube'],
							['Image'],
							'/',
							['Format'],
							['Bold','Italic','Strike','RemoveFormat'],
							['Styles']
						]
					",
					
					# pureContent plus formatting - cut-down, predominantly semantic toolbar, plus formatting
					'pureContentPlusFormatting' => "
						[
							['Templates'],
							['divs'],
							['Cut','Copy','Paste','PasteText','PasteWord','-',],
							['Undo','Redo','-','Find','Replace','-','SelectAll'],
							['Scayt'],
							['Source'],
							['About'],
							'/',
							['BulletedList','NumberedList','-','Outdent','Indent','Blockquote'],
							['Subscript','Superscript','SpecialChar'],
							['HorizontalRule'],
							['ShowBlocks','CreateDiv','Iframe'],
							['Table'],
							['Link','Unlink','Anchor'],
							['Html5video'],
							['Youtube'],
							['Image'],
							'/',
							['Format'],
							['Bold','Italic','Strike'],
							['Styles'],
							['RemoveFormat'],
							[/* 'Font','FontSize', */ 'TextColor' /* ,'BGColor' */ ],
							['JustifyLeft','JustifyCenter','JustifyRight','JustifyFull']
						]
					",
					
					# Basic
					'Basic' => "
						[
							['Bold','Italic'],
							['BulletedList','NumberedList'],
							['Link','Unlink'],
							['About']
						]
					",
					
					# Basic, without links
					'BasicNoLinks' => "
						[
							['Bold','Italic'],
							['BulletedList','NumberedList'],
							['About']
						]
					",
					
					# Basic, plus image
					'BasicImage' => "
						[
							['Bold','Italic'],
							['BulletedList','NumberedList'],
							['Link','Unlink'],
							['Image'],
							['Source'],
							['About']
						]
					",
					
					# A slightly more extensive version of the basic toolbar
					'BasicLonger' => "
						[
							['Format'],
							['Bold','Italic','RemoveFormat'],
							['BulletedList','NumberedList'],
							['Link','Unlink'],
							['Source'],
							['About']
						]
					",
					
					# A slightly more extensive version of the basic toolbar, plus formatting
					'BasicLongerFormat' => "
						[
							['Format','Styles'],
							['Bold','Italic','RemoveFormat'],
							['BulletedList','NumberedList'],
							['Link','Unlink'],
							['Source'],
							['About']
						]
					",
					
				);
				
				# If supported, copy the selected toolbar to the toolbar config setting
				if (isSet ($toolbars[$arguments['editorToolbarSet']]) && $toolbars[$arguments['editorToolbarSet']]) {
					$arguments['config.toolbar'] = $toolbars[$arguments['editorToolbarSet']];
				}
			}
			
			# Start extra plugins
			$extraPlugins = array ();
			
			# Debugging; requires the devtools plugin to be installed; see: https://ckeditor.com/cke4/addon/devtools and https://ckeditor.com/docs/ckeditor4/latest/guide/dev_howtos_dialog_windows.html
			//$extraPlugins[] = 'devtools';
			
			# HTML5 video; see: https://ckeditor.com/cke4/addon/html5video
			$extraPlugins[] = 'html5video,widget,widgetselection,clipboard,lineutils';
			
			# YouTube; see: https://ckeditor.com/cke4/addon/youtube
			$extraPlugins[] = 'youtube';
			// videodetector: Basically doesn't work well, adding a rogue button in
			
			# Auto-embed - resolve URLs like YouTube videos and Twitter postings to HTML
			$extraPlugins[] = 'embed,autoembed';
			$arguments['config.embed_provider'] = '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}';
			if ($this->settings['richtextAutoembedKey']) {
				$arguments['config.embed_provider'] .= '&api_key=' . $this->settings['richtextAutoembedKey'];
			}
			
			# Templates (full-page)
			if ($arguments['templates']) {
				$arguments['config.templates_files'] = array ($arguments['templates']);
			}
			
			# Widgets (HTML snippets)
			if ($arguments['snippets']) {
				$extraPlugins[] = 'htmlbuttons';
				$arguments['config.htmlbuttons'] = $this->richtextSnippetsConfig ($arguments['snippets']);
			} else {
				$arguments['config.toolbar'] = str_replace ("['divs'],", '', $arguments['config.toolbar']);		// Remove from definition
			}
			
			# Add the extra plugins
			$arguments['config.extraPlugins'] = implode (',', $extraPlugins);
			
			# Construct the CKEditor arguments; see: http://docs.ckeditor.com/#!/api/CKEDITOR.editor
			$editorConfig = array ();
			foreach ($arguments as $argument => $argumentValue) {
				if (preg_match ('/^config\.(.+)$/', $argument, $matches)) {
					$editorConfigKey = $matches[1];
					$editorConfig[$editorConfigKey]  = "{$editorConfigKey}: ";
					
					# Add the config argument value, formatted for JS
					if (is_bool ($argumentValue)) {
						$editorConfig[$editorConfigKey] .= ($argumentValue ? 'true' : 'false');	// Appears as native JS true/false type
					} else if (in_array ($editorConfigKey, array ('toolbar', 'stylesSet', ))) {
						$editorConfig[$editorConfigKey] .= $argumentValue;	// Native JS string
					} else if (is_array ($argumentValue)) {
						$editorConfig[$editorConfigKey] .= json_encode ($argumentValue, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

/*
						foreach ($argumentValue as $index => $argumentSubValue) {
							if (is_array ($argumentSubValue)) {
								$argumentValue[$index] = json_encode ($argumentSubValue);
							} else {
								$argumentValue[$index] = "'" . $argumentSubValue . "'";	// Quote each value
							}
						}
						$editorConfig[$editorConfigKey] .= '[' . implode (', ', $argumentValue) . ']';
*/
					} else {
						$editorConfig[$editorConfigKey] .= '"' . str_replace ('"', '\\"', $argumentValue) . '"';	// Appears as quoted string
					}
				}
			}
			
			# Define default dialog box settings; see: http://stackoverflow.com/questions/12464395/ and http://docs.ckeditor.com/#!/guide/dev_howtos_dialog_windows
			# Use the devtools plugin (see above) to determine the internal names
			$dialogBoxSettings = "
				// Dialog box configuration
				CKEDITOR.on( 'dialogDefinition', function( ev ) {
					var dialogName = ev.data.name;
					var dialogDefinition = ev.data.definition;
					
					// Link dialog
					if (dialogName == 'link') {
						var infoTab = dialogDefinition.getContents('info');
						
						// Remove the e-mail type; see: https://ckeditor.com/old/forums/Support/Remove-options-link-drop-down
						var linkOptions = infoTab.get('linkType');
						linkOptions['items'] = [ ['Website (URL)', 'url'], ['Link to anchor in the text', 'anchor'], ['Phone', 'tel'] ];
					}
					
					// Table dialog
					if ( dialogName == 'table' ) {
						
						// Info tab - remove legacy values
						var infoTab = dialogDefinition.getContents( 'info' );
						infoTab.get( 'txtCols' )[ 'default' ] = '3';	// Default columns
						infoTab.get( 'txtWidth' )[ 'default' ] = '';	// Default table width
						infoTab.get( 'txtBorder' )[ 'default' ] = '';	// Default border
						infoTab.get( 'selHeaders' )[ 'default' ] = 'row';	// Default headers
						infoTab.get( 'txtCellSpace' )[ 'default' ] = '';	// Default cellspacing
						infoTab.get( 'txtCellPad' )[ 'default' ] = '';	// Default cellpadding
						
						// Advanced tab - set class=lines
						var advancedTab = dialogDefinition.getContents( 'advanced' );
						advancedTab.get( 'advCSSClasses' )[ 'default' ] = '" . $arguments['editorDefaultTableClass'] . "';	// Default class
					}
					
					// Image dialog
					if ( dialogName == 'image' ) {
						
						// Info tab - improve 'Browse server' button, and remove legacy hspace/vspace
						var infoTab = dialogDefinition.getContents( 'info' );
						infoTab.get( 'browse' )[ 'label' ] = 'Select image from library...';	// Rename 'Browse server'
						infoTab.get( 'browse' )[ 'className' ] = 'cke_dialog_ui_button_ok';	// Make button more obvious
						infoTab.get( 'txtAlt' )[ 'label' ] = '<strong>Alternative text</strong> (for accessibility, slow internet, and Google Image Search)';	// Clearer label
						infoTab.get( 'txtAlt' )[ 'validate' ] = CKEDITOR.dialog.validate.notEmpty('You must provide alternative text!');	// Require alternative text
						infoTab.remove( 'txtHSpace' );
						infoTab.remove( 'txtVSpace' );
						
						// Upload tab - remove entirely
						dialogDefinition.removeContents( 'Upload' );
					}
					
					/*
					// Image dialog
					if ( dialogName == 'image2' ) {
						
						// Info tab - improve 'Browse server' button, and remove legacy hspace/vspace
						var infoTab = dialogDefinition.getContents( 'info' );
						infoTab.get('src')['label'] = 'Select image:';
						infoTab.get( 'browse' )[ 'label' ] = 'Add new image or browse existing...';	// Rename 'Browse server'
						//infoTab.get( 'browse' )[ 'className' ] = 'cke_dialog_ui_button_ok';	// Make button more obvious
						infoTab.get( 'browse' )[ 'style' ] = 'background: yellow !important;';	// Make button more obvious
						infoTab.get( 'alt' )[ 'label' ] = '<strong>Alternative text</strong> (for accessibility / Google Images)';	// Clearer label
						infoTab.get( 'alt' )[ 'validate' ] = CKEDITOR.dialog.validate.notEmpty('Please provide alternative text describing this image, for accessibility reasons.');	// Require alternative text
						
						// Upload tab - remove entirely
						dialogDefinition.removeContents( 'Upload' );
					}
					*/
					
					// Link dialog
					if ( dialogName == 'link' ) {
						
						// Info tab - improve 'Browse server' button, and remove legacy hspace/vspace
						var infoTab = dialogDefinition.getContents( 'info' );
						infoTab.get( 'browse' )[ 'label' ] = 'Select page/file to link to, or add PDF/Word/etc document ...';	// Rename 'Browse server'
						infoTab.get( 'browse' )[ 'className' ] = 'cke_dialog_ui_button_ok';	// Make button more obvious
						
						// Upload tab - remove entirely
						dialogDefinition.removeContents( 'upload' );
					}
					
					// HTML5 video
					if (dialogName == 'html5video') {
						var infoTab = dialogDefinition.getContents( 'info' );
						infoTab.get ('controls')['default'] = true;		// #!# Doesn't actually seem to work
						infoTab.get ('width')['default'] = 600;
						infoTab.get ('align')['default'] = 'none';
						var advancedTab = dialogDefinition.getContents( 'advanced' );
						advancedTab.get ('allowdownload')['default'] = 'yes';
						
						// Upload tab - remove entirely
						dialogDefinition.removeContents( 'Upload' );
					}
					
					// YouTube plugin
					if (dialogName == 'youtube') {
						var youtubeTab = dialogDefinition.getContents( 'youtubePlugin' );
						youtubeTab.get ('chkRelated')['default'] = false;
						youtubeTab.get ('chkPrivacy')['default'] = true;
						dialogDefinition.onFocus = function () {		// https://stackoverflow.com/a/21905673/180733
							this.getContentElement( 'youtubePlugin', 'txtUrl' ).focus();
						}
					}
				});
			";
			
			# Add HTML filtering to deal with <img> tags emitting style=".." rather than height/width/border=".."; see: http://stackoverflow.com/a/11927911
			#!# Border support also needed
			$htmlFilterSettings = "
				// Fix <img> tags to use height/width/border rather than style
				CKEDITOR.on('instanceReady', function (ev) {
					ev.editor.dataProcessor.htmlFilter.addRules({
						elements: {
							$: function (element) {
								// Output dimensions of images as width and height
								if (element.name == 'img') {
									var style = element.attributes.style;
									
									if (style) {
										// Get the width from the style.
										var match = /(?:^|\s)width\s*:\s*(\d+)px/i.exec(style),
											width = match && match[1];
										
										// Get the height from the style.
										match = /(?:^|\s)height\s*:\s*(\d+)px/i.exec(style);
										var height = match && match[1];
										
										// Get the align from the style
										
										var match = /(?:^|\s)float\s*:\s*(left|right)/i.exec(style),
										align = match && match[1];
										
										if (width) {
											element.attributes.style = element.attributes.style.replace(/(?:^|\s)width\s*:\s*(\d+)px;?/i, '');
											element.attributes.width = width;
										}
										
										if (height) {
											element.attributes.style = element.attributes.style.replace(/(?:^|\s)height\s*:\s*(\d+)px;?/i, '');
											element.attributes.height = height;
										}
										
										if (align) {
											element.attributes.style = element.attributes.style.replace(/(?:^|\s)float\s*:\s*(left|right);?/i, '');
											element.attributes.align = align;
										}
									}
								}
								
								if (!element.attributes.style) {
									delete element.attributes.style;
								}
								
								return element;
							}
						}
					});
				});
			";
			
			# Add px to width/height if not specified and not a percentage
			if (ctype_digit ($arguments['config.width'])) {
				$arguments['config.width'] .= 'px';
			}
			$arguments['config.height'] = str_replace ('px', '', $arguments['config.height']);	// Revert to pixels
			if (ctype_digit ($arguments['config.height'])) {
				$arguments['config.height'] = $arguments['config.height'] + 71;		// By trial and error
				$arguments['config.height'] .= 'px';
			}
			
			# Assemble the widget ID for use in script registration
			$widgetId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']);
			
			# Start the widget HTML
			$widgetHtml  = '
			<!-- WYSIWYG editor; replace the <textarea> with a CKEditor instance -->
			<textarea' . $this->nameIdHtml ($arguments['name']) . " style=\"width: {$arguments['config.width']}; height: {$arguments['config.height']}\"" . ($arguments['autofocus'] ? ' autofocus="autofocus"' : '') . '>' . htmlspecialchars ($elementValue) . '</textarea>
			';
			$this->jQueryLibraries['CKEditor'] = '<script src="' . $arguments['editorBasePath'] . 'ckeditor.js"></script>';
			$this->jQueryCode[__FUNCTION__ . $widgetId] = '
				var editor = CKEDITOR.replace("' . $id . '", {
					' . implode (",\n\t\t\t\t\t", $editorConfig) . '
				});
				' . $dialogBoxSettings . '
				' . $htmlFilterSettings . '
			';
			
			# Add the file manager if required; see: http://docs.cksource.com/CKFinder_2.x/Developers_Guide/PHP/CKEditor_Integration and http://docs.cksource.com/ckfinder_2.x_api/symbols/CKFinder.config.html
			if ($arguments['editorFileBrowser']) {
				
				#!# startupFolderExpanded is not clear; see ticket: http://ckeditor.com/forums/Support/Documentation-suggestion-startupFolderExpanded-is-unclear
				$this->jQueryLibraries['CKFinder'] = '<script src="' . $arguments['editorFileBrowser'] . 'ckfinder.js"></script>';
				$this->jQueryCode[__FUNCTION__ . $widgetId] .= '
					// File manager settings
					CKFinder.setupCKEditor( editor, {
						basePath: "' . $arguments['editorFileBrowser'] . '",
						id: "' . $id . '",
						startupPath: "' . $_SERVER['SERVER_NAME'] . ':' . ($arguments['editorFileBrowserStartupPath'] ? $arguments['editorFileBrowserStartupPath'] : '/') . '",
						startupFolderExpanded: true,
						rememberLastFolder: true
					});
				';
				
				# Use the ACL functionality if required, by writing it into the session
				#!# Ideally, CKFinder would have a better way of providing a configuration directly, or pureContentEditor could have a callback that is queried, but this would mean changing all cases of 'echo' and have a non-interactive mode setting in the constructor call
				if ($arguments['editorFileBrowserACL']) {
					if (!isset ($_SESSION)) {session_start ();}
					$_SESSION['CKFinderAccessControl'] = $arguments['editorFileBrowserACL'];
				}
			}
			
		} else {
			$widgetHtml = $this->form[$arguments['name']];
			$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($this->form[$arguments['name']]) . '" />';
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Disallow 'Click here' and variants
		if ($arguments['noClickHere']) {
			if ($elementValue) {
				if (substr_count (strtolower ($elementValue), '>here<') || substr_count (strtolower ($elementValue), '>click here<')) {
					$elementProblems['noClickHere'] = "Please do not use 'click here' or similar - this is not accessible (as people scan pages); please rewrite the link text to be self-explanatory.";
				}
			}
		}
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Get the data
			$data['presented'] = $elementValue;
		}
		
		# Set restrictions
		if (isSet ($restrictions)) {$restrictions = implode (";\n", $restrictions);}
		
		# Send header to avoid ERR_BLOCKED_BY_XSS_AUDITOR warnings / blank screens; requires output buffering
		if (ini_get ('output_buffering')) {
			header ('X-XSS-Protection: 0');
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restrictions) && $arguments['editable'] ? $restrictions : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'TEXT') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'after' => $arguments['after'],
		);
	}
	
	
	# Function to clean the content
	function richtextClean ($content, &$arguments, $nofixTag = '<!-- nofix -->', $charset = 'utf8', $allowCurlyQuotes = false)
	{
		# Determine whether the <!-- nofix --> tag is present at the start and therefore whether the content should be cleaned
		$nofixPresent = ($nofixTag && (substr ($content, 0, strlen ($nofixTag)) == $nofixTag));	// ereg/preg_match are not used as otherwise escaping may be needed
		$cleanHtml = !$nofixPresent;
		
		# Cache wanted characters stripped by tidy's 'bare' option
		$cache = array (
			'&#8211;' => '__NDASH',
			'&#8212;' => '__MDASH',
			'&ndash;' => '__NDASH',
			'&mdash;' => '__MDASH',
			'XXX' => 'YYY',
			'<p style="clear: both;">' => '__PSTYLECLEARBOTH',
			'<p style="clear: left;">' => '__PSTYLECLEARLEFT',
			'<p style="clear: right;">' => '__PSTYLECLEARRIGHT',
		);
		if ($allowCurlyQuotes) {
			$cache += array (
				'&#8216;' => '__U+2018',
				'&lsquo;' => '__entityU+2018',
				'&#8217;' => '__U+2019',
				'&rsquo;' => '__entityU+2019',
				'&#8220;' => '__U+201C',
				'&ldquo;' => '__entityU+201C',
				'&#8221;' => '__U+201D',
				'&rdquo;' => '__entityU+201D',
			);
		}
		if ($cleanHtml) {
			$content = str_replace (array_keys ($cache), array_values ($cache), $content);
		}
		
		# If the tidy extension is not available (e.g. PHP4), perform cleaning with the Tidy API
		if ($cleanHtml && function_exists ('tidy_parse_string')) {
			
			# Set options, as at http://tidy.sourceforge.net/docs/quickref.html
			$parameters = array (
				'output-xhtml' => true,
				'show-body-only'	=> true,
				'clean' => true,	// Note that this also removes style="clear: ..." from e.g. a <p> tag
				'enclose-text'	=> true,
				'drop-proprietary-attributes' => true,
				//'drop-font-tags' => true,		// Deprecated and now removed; see: https://api.html-tidy.org/tidy/quickref_5.4.0.html#drop-font-tags
				'drop-empty-paras' => true,
				'hide-comments' => $arguments['removeComments'],
				'join-classes' => true,
				'join-styles' => true,
				'logical-emphasis' => true,
				'merge-divs'	=> false,
				'word-2000'	=> true,
				'indent'	=> false,
				'indent-spaces'	=> 4,
				'wrap'	=> 0,
				'fix-backslash'	=> false,
				'force-output'	=> true,
				'bare'	=> true,	// Note: this replaces &ndash; and &mdash; hence they are cached above
				# HTML5 support; see: http://stackoverflow.com/questions/11746455/php-tidy-removes-valid-tags
				'new-blocklevel-tags' => 'article aside audio bdi canvas details dialog figcaption figure footer header hgroup main menu menuitem nav section source summary template track video',
				'new-empty-tags' => 'command embed keygen source track wbr',
				'new-inline-tags' => 'audio command datalist embed keygen mark menuitem meter output progress source time video wbr',
			);
			
			# Tidy up the output; see http://www.zend.com/php5/articles/php5-tidy.php for a tutorial
			$content = tidy_parse_string ($content, $parameters, $charset);
			tidy_clean_repair ($content);
			$content = tidy_get_output ($content);
		}
		
		# Resubstitute the cached items
		if ($cleanHtml) {
			$content = str_replace (array_values ($cache), array_keys ($cache), $content);
		}
		
		# Start an array of regexp replacements
		$replacements = $arguments['replacements'];	// By default an empty array
		
		# Protect e-mail spanning from later replacement in the main regexp block
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<span>@</span>' => '<TEMPspan>@</TEMPspan>',
			);
		}
		
		# Define main regexp replacements
		if ($cleanHtml) {
			$replacements += array (
				'<\?xml:namespace([^>]*)>' => '',	// Remove Word XML namespace tags
				'<o:p> </o:p>'	=> '',	// WordHTML characters
				'<o:p></o:p>'	=> '',	// WordHTML characters
				'<o:p />'	=> '',	// WordHTML characters
				' class="c([0-9])"'     => '',  // Word classes
				'<p> </p>'      => '',  // Empty paragraph
				'<div> </div>'  => '',  // Empty divs
				'<span>([^<]*)</span>' => '<TEMP2span>\\1</TEMP2span>',	// Protect FIR-style spans
				"</?span([^>]*)>"	=> '',	// Remove other spans
				'\s*<h([1-6]{1})([^>]*)>\s</h([1-6]{1})>\s*' => '',	// Headings containing only whitespace
				'\s+</li>'     => '</li>',     // Whitespace before list item closing tags
				'\s+</h'       => '</h',       // Whitespace before heading closing tags
				'<h([2-6]+)'	=> "\n<h\\1",	// Line breaks before headings 2-6
				'<br /></h([1-6]+)>'	=> "</h\\1>",	// Pointless line breaks just before a heading closing tag
				'</h([1-6]+)>'	=> "</h\\1>\n",	// Line breaks after all headings
				"<(li|tr|/tr|tbody|/tbody)"	=> "\t<\\1",	// Indent level-two tags
				"<td"	=> "\t\t<td",	// Double-indent level-three tags
				'<h([1-6]+) id="Heading([0-9]+)">'      => '<h\\1>',    // Headings from R2Net converter
				' class="MsoNormal"' => '',	// WordHTML
				' class="MsoNormal c([0-9]+)"' => '',	// WordHTML
				'<li>\s*<p>(.*?)</p>\s*</li>' => '<li>\1</li>',	// Remove paragraph breaks directly within a list item; note ungreedy *? modifier
			);
		}
		
		# Non- HTML-cleaning replacements
		$replacements += array (
			" href=\"{$arguments['editorBasePath']}editor/"	=> ' href=\"',	// Workaround for Editor basepath bug
			' href="([^"]*)/' . $arguments['directoryIndex'] . '"'	=> ' href="\1/"',	// Chop off directory index links
		);
		
		# Obfuscate e-mail addresses
		if ($arguments['protectEmailAddresses']) {
			$replacements += array (
				'<TEMPspan>@</TEMPspan>' => '<span>&#64;</span>',
				'<TEMP2span>([^<]*)</TEMP2span>' => '<span>\\1</span>',	// Replace FIR-style spans back
				'<a([^>]*) href="([^("|@)]+)@([^"]+)"([^>]*)>' => '<a\1 href="mailto:\2@\3"\4>',	// Initially catch badly formed HTML versions that miss out mailto: (step 1)
				'<a href="mailto:mailto:' => '<a href="mailto:',	// Initially catch badly formed HTML versions that miss out mailto: (step 2)
				'<a([^>]*) href="mailto:([^("|@)]+)@([^"]+)"([^>]*)>([^(@|<)]+)@([^<]+)</a>' => '\5<span>&#64;</span>\6',
				'<a([^>]*) href="mailto:([^("|@)]+)@([^"]+)"([^>]*)>([^<]*)</a>' => '\5 [\2<span>&#64;</span>\3]',
				'<span>@</span>' => '<span>&#64;</span>',
				'<span><span>&#64;</span></span>' => '<span>&#64;</span>',
				'([^\s]+)@([^\s]+)' => '\1<span>&#64;</span>\2', // Non-linked, standard text, addresses - basically any non-whitespace text with a @ in the middle
			);
		}
		
		# Ensure links to pages outside the page are in a new window
		if ($cleanHtml && $arguments['externalLinksTarget']) {
			$replacements += array (
				'<a target="([^"]*)" href="([^"]*)"([^>]*)>' => '<a href="\2" target="\1"\3>',	// Move existing target to the end
				'<a href="(http:|https:)//([^"]*)"([^>]*)>' => '<a href="\1//\2" target="' . $arguments['externalLinksTarget'] . '"\3>',	// Add external links
				'<a href="([^"]*)" target="([^"]*)" target="([^"]*)"([^>]*)>' => '<a href="\1" target="\2"\4>',	// Remove any duplication
			);
		}
		
		# Replacement of image alignment with a similarly-named class
		if ($cleanHtml && $arguments['imageAlignmentByClass']) {
			$replacements += array (
				'<img([^>]*) align="(left|middle|center|centre|right)" ([^>]*)class="([^"]*)"([^>]*)>' => '<img\1 class="\4 \2"\5 \3>',
				'<img([^>]*) class="([^"]*)" ([^>]*)align="(left|middle|center|centre|right)"([^>]*)>' => '<img\1 class="\2 \4"\5 \3>',
				'<img([^>]*) align="(left|middle|center|centre|right)"([^>]*)>' => '<img\1 class="\2"\3>',
			);
		}
		
		# Perform the replacements
		foreach ($replacements as $find => $replace) {
			#!# Migrate to direct preg_replace
			$content = application::peregi_replace ($find, $replace, $content);
		}
		
		# Return the tidied and adjusted content
		return $content;
	}
	
	
	# Function to compile richtext snippets configuration
	private function richtextSnippetsConfig ($snippets)
	{
		# Construct the list of items for the drop-down
		$items = array ();
		$i = 0;
		foreach ($snippets as $title => $html) {
			$items[] = array (
				'name'	=> 'item' . $i++,
				'title'	=> $title,
				'html'	=> $html,
				'icon'	=> false,	// Doesn't actually work anyway
			);
		}
		
		# Assemble the configuration, containing all the items
		$configuration = array (	// Array of individual buttons; we create only one with nested items
			array (
				'name'	=> 'divs',	// NB This seems to be a hard-coded name when used as a container for other buttons
				'icon'	=> 'puzzle.png',
				'title'	=> 'Insert items',
				'items' => $items,
			),
		);
		
		# Return the configuration
		return $configuration;
	}
	
	
	/**
	 * Create a select (drop-down) box widget
	 * @param array $arguments Supplied arguments - see template
	 */
	function select ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'values'				=> array (),# Array of selectable values
			'valuesNamesAutomatic'	=> false,	# Whether to create automatic value names based on the value itself (e.g. 'option1' would become 'Option 1')
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'get'					=> false,	# Whether a URL-supplied GET value should be used as the initial value (e.g. 'key' here would look in $_GET['key'] and supply that as the default)
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'multiple'				=> false,	# Whether to create a multiple-mode select box
			'expandable'			=> false,	# Whether a multiple-select box should be converted to a set of single boxes whose number can be incremented by pressing a + button
			'required'		=> 0,		# The minimum number which must be selected (defaults to 0), or true (which will be turned into 1)
			'size'			=> 5,		# Number of rows visible in multiple mode (optional; defaults to 1)
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> array (),# Pre-selected item(s)
			'defaultPresplit'		=> false,	# Whether to pre-split a default that is a string, using the separator and separatorSurround values
			'separator'				=> ",\n",	# Separator used for the compiled and presented output types
			'separatorSurround'		=> false,	# Whether, for the compiled and presented output types, if there are any output values, the separator should also be used to surround the values (e.g. |value1|value2|value3| rather than value1|value2|value3 for separator = '|')
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific widget
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'nullRequiredDefault'	=> true,	# Whether to add an empty value when the field is required and has a default
			'onchangeSubmit'		=> false,	# Whether to submit the form onchange
			'copyTo'				=> false,	# Whether to copy the value, onchange, to another form widget if that widget's value is currently empty
			'autocomplete'			=> false,	# URL of data provider
			'autocompleteOptions'	=> false,	# Autocomplete options; see: http://jqueryui.com/demos/autocomplete/#remote (this is the new plugin)
			'entities'				=> true,	# Convert HTML in label to entity equivalents
			'data'					=> array (),	# Values for data-* attributes
			'tolerateInvalid'		=> false,	# Whether to tolerate an invalid default value, and reset the value to empty
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, $arrayType = true);
		
		$arguments = $widget->getArguments ();
		
		# If pre-splitting is required, split
		#!# Needs to normalise spaces between items, e.g. "|a|b |c|d" doesn't get cleaned up
		if ($arguments['defaultPresplit']) {
			if (is_string ($arguments['default']) && strlen ($arguments['default'])) {
				$splittableString = true;
				if ($arguments['separatorSurround']) {
					$splittableString = false;
					$delimiter = '/';
					$delimiter . '^' . preg_quote ($arguments['separator']) . '(.+)' . preg_quote ($arguments['separator']) . '$' . $delimiter;
					if (preg_match ($delimiter . '^' . preg_quote ($arguments['separator'], $delimiter) . '(.+)' . preg_quote ($arguments['separator'], $delimiter) . '$' . $delimiter, $arguments['default'], $matches)) {
						$splittableString = true;
						$arguments['default'] = $matches[1];
					}
				}
				if ($splittableString) {
					$arguments['default'] = explode ($arguments['separator'], $arguments['default']);
				}
			}
		}
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name'], $arguments['valuesNamesAutomatic']);
		
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		
		# Ensure the 'required' argument is numeric if supplied
		if ($arguments['required'] === true) {$arguments['required'] = 1;}
		
		# For autocomplete, deal with various changes
		$autocompleteAutovaluesMode = false;
		if ($arguments['autocomplete']) {
			
			# Ensure the settings are sensible
			if (($arguments['required'] > 1) && !$arguments['expandable']) {
				$this->formSetupErrors['selectMultipleMismatch'] = "More than one value is set as being required to be selected, and autocomplete is on, but expandable mode is off. Expandable mode needs to be enabled.";
			}
			
			# Set to the specified list if set to boolean true
			if ($arguments['autocomplete'] === true) {
				if (is_array ($arguments['values'])) {
					$arguments['autocomplete'] = array_values ($arguments['values']);
				} else {
					$this->formSetupErrors['autocompleteValuesMismatch'] = "Autocomplete mode is enabled to expect an array of values but the values parameter is not an array";
				}
			}
			
			# If a string (i.e. a URL), make sure the values list is empty, and when confirmed, 
			if (is_string ($arguments['autocomplete'])) {
				if ($arguments['values'] === false) {
					$autocompleteAutovaluesMode = true;
					
					# Create an array of arbitrary values to emulate a fixed supplied set
					$createValues = 200;	// Arbitrarily high number of (arbitrary) values to create; this is a little poor but it is otherwise hard to work out how many to create; it basically needs always to be at least one more than the current number of widgets being displayed
					$arguments['values'] = array_fill (0, $createValues, $arbitraryValue = true);	// Create an arbitrary value(s) list, to ensure that the widget(s) get(s) created
					
				} else {
					$this->formSetupErrors['autocompleteValuesMismatch'] = "Autocomplete from an external data source is enabled for {$arguments['name']}. The values list must therefore be set to false, but this is not the case.";
				}
			}
		}
		
		# If using a expandable widget-set, ensure that other arguments are sane
		$subwidgets = 1;
		if ($arguments['expandable']) {
			if (!$arguments['multiple']) {
				$this->formSetupErrors['expandableNotMultiple'] = 'An expandable select widget-set was requested, but the widget-type is not set as multiple, which is required.';
				$arguments['expandable'] = false;
			}
			if (!$arguments['editable']) {
				$this->formSetupErrors['expandableNotEditable'] = 'An expandable select widget-set was requested, but the widget-type is set as non-editable.';
				$arguments['expandable'] = false;
			}
			
			# Determine the number of widgets to display
			if ($arguments['required']) {
				$subwidgets = $arguments['required'];
			}
			$subwidgets = $this->subwidgetExpandabilityCount ($subwidgets, $arguments['name'], $arguments['required'], $arguments['autofocus'] /* passed (and altered) by reference */);
			$totalAvailableOptions = count ($arguments['values']);
			if (($subwidgets > $totalAvailableOptions) && ($subwidgets != 0)) {	// Ensure there are never any more than the available options
				$subwidgets = $totalAvailableOptions;
			}
		}
		
		# Use the 'get' supplied value if required
		#!# Apply this to checkboxes and radio buttons also
		if ($arguments['get']) {
			$arguments['default'] = application::urlSuppliedValue ($arguments['get'], array_keys ($arguments['values']));
		}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# Increase the number of default widgets to the number of defaults if any are set and the form has not been posted
		if (!$this->formPosted) {
			if ($arguments['expandable']) {
				if ($arguments['default']) {
					$totalDefaults = count ($arguments['default']);
					if ($totalDefaults > $subwidgets) {
						$subwidgets = $totalDefaults;
					}
				}
			}
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Obtain the value of the form submission (which may be empty); if using expandable widgets, emulate the format of a single multiple widget
		if ($arguments['expandable']) {
			$value = array ();
			$allNonZeroSoFar = true;
			for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
				$subwidgetName = $arguments['name'] . ($arguments['expandable'] ? "_{$subwidget}" : '');
				if (isSet ($this->form[$subwidgetName]) && isSet ($this->form[$subwidgetName][0]) && is_string ($this->form[$subwidgetName][0])) {
					$subwidgetValue = $this->form[$subwidgetName][0];
					if ($value && $subwidgetValue && in_array ($subwidgetValue, $value)) {
						$elementProblems['expandableValuesDuplicated'] = "In the <strong>{$arguments['name']}</strong> element, you selected the same value twice.";
					}
					if (!$subwidgetValue) {
						$allNonZeroSoFar = false;
					}
					$value[$subwidget] = $subwidgetValue;
				}
				if (!$allNonZeroSoFar && $subwidgetValue) {
					$elementProblems['expandableValuesMissingInSequence'] = "In the <strong>{$arguments['name']}</strong> element, you left out a value in sequence.";
				}
			}
		} else {
			$value = (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		}
		$widget->setValue ($value);
		
		# Add autocomplete functionality if required
		$widget->autocomplete ($arguments, ($arguments['expandable'] ? $subwidgets : false));
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values']) && !$autocompleteAutovaluesMode) {
			$this->formSetupErrors['selectNoValues'] = "No values have been set as selection items for the <strong>{$arguments['name']}</strong> element.";
			return false;
		}
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		if (isSet ($arguments['_valuesMultidimensional'])) {
			$arguments['_valuesMultidimensional'] = $widget->truncate ($arguments['_valuesMultidimensional']);
		}
		
		# Check that the given minimum required is not more than the number of items actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['selectMinimumMismatch'] = "The required minimum number of items which must be selected (<strong>{$arguments['required']}</strong>) specified is above the number of select items actually available (<strong>$totalSubItems</strong>).";}
		
		# If not using multiple mode, ensure that more than one cannot be set as required
		if (!$arguments['multiple'] && ($arguments['required'] > 1)) {$this->formSetupErrors['selectMultipleMismatch'] = "More than one value is set as being required to be selected but multiple mode is off. One or the other should be changed.";}
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off
		$totalDefaults = count ($arguments['default']);
		if ((!$arguments['multiple']) && ($totalDefaults > 1)) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but the form has been set up to allow only one item to be selected by the user.";
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments, $warningHtml /* returned by reference */);
		
		# Emulate the need for the field to be 'required', i.e. the minimum number of fields is greater than 0
		$required = ($arguments['required'] > 0);
		
		# Loop through each element value to check that it is in the available values, and just discard any that are not, lodging a user error
		if (!$autocompleteAutovaluesMode) {
			foreach ($elementValue as $index => $value) {
				$unavailableSubmitted = array ();
				if ($value != '') {
					if (!array_key_exists ($value, $arguments['values'])) {
						$unavailableSubmitted[] = $elementValue[$index];
						unset ($elementValue[$index]);
					}
				}
				if ($unavailableSubmitted) {
					$totalUnavailableSubmitted = count ($unavailableSubmitted);
					$elementProblems['unavailableSubmitted'] = 'The ' . ($totalUnavailableSubmitted == 1 ? 'value' : 'values') . ' <em>' . htmlspecialchars (implode ('</em>, <em>', $unavailableSubmitted)) . '</em> you submitted ' . ($totalUnavailableSubmitted == 1 ? 'is' : 'are') . ' not in the list of available values.';
				}
			}
		}
		
		# Remove null if it's submitted, so that it can never end up in the results; this is different to radiobuttons, because a radiobutton set can have nothing selected on first load, whereas a select always has something selected, so a null must be present
		foreach ($elementValue as $index => $submittedValue) {
			if ($submittedValue == '') {
				unset ($elementValue[$index]);
			}
		}
		
		# Produce a problem message if the number submitted is fewer than the number required
		$totalSubmitted = count ($elementValue);
		if (($totalSubmitted != 0) && ($totalSubmitted < $arguments['required'])) {
			$elementProblems['insufficientSelected'] = ($arguments['required'] != $totalSubItems ? 'At least' : 'All') . " <strong>{$arguments['required']}</strong> " . ($arguments['required'] > 1 ? 'items' : 'item') . ' must be selected.';
		}
		
		# Prevent multiple submissions when not in multiple mode
		if (!$arguments['multiple'] && ($totalSubmitted > 1)) {$elementProblems['multipleSubmissionsDisallowed'] = 'More than one item was submitted but only one is acceptable';}
		
		# If nothing has been submitted mark it as required but empty
		$requiredButEmpty = (($required) && ($totalSubmitted == 0));
		
		# Assign the initial values if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {$elementValue = $arguments['default'];}
		
		# Describe restrictions on the widget
		if ($arguments['multiple']) {
			$restriction = (($arguments['required'] > 1) ? "Minimum {$arguments['required']} required." : '') . ($arguments['expandable'] ? '' : ' Use Control/Shift for multiple');
		}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# Create each widget for this set (normally one, but could be more if in expandable mode)
			$subwidgetHtml = array ();
			$subwidgetsAreMultiple = ($arguments['expandable'] ? false : $arguments['multiple']);
			for ($subwidget = 0; $subwidget < $subwidgets; $subwidget++) {
				$subwidgetName = $arguments['name'] . ($arguments['expandable'] ? "_{$subwidget}" : '');
				
				# Add a null field to the selection if in multiple mode and a value is required (for single fields, null is helpful; for multiple not required, some users may not know how to de-select a field)
				#!# Creates error if formSetupErrors['selectNoValues'] thrown - shouldn't be getting this far
				if (!$subwidgetsAreMultiple || !$arguments['required']) {
					$arguments['valuesWithNull'] = array ('' => $arguments['nullText']) + $arguments['values'];
					if (isSet ($arguments['_valuesMultidimensional'])) {
						$arguments['_valuesMultidimensional'] = array ('' => $arguments['nullText']) + $arguments['_valuesMultidimensional'];
					}
				}
				
				# In autocomplete mode, create a standard input widget, but with an array submission type as the name
				$hasAutofocus = ($arguments['autofocus'] === true || (is_numeric ($arguments['autofocus']) && ($subwidget + 1) == $arguments['autofocus']));	// True or the subwidget number matches
				if ($hasAutofocus) {
					$this->clearAnyOtherAutofocus ();
				}
				if ($arguments['autocomplete']) {
					$subwidgetHtml[$subwidget] = "\n\t\t\t<input type=\"text\"" . (isSet ($elementValue[$subwidget]) ? ' value="' . htmlspecialchars ($elementValue[$subwidget]) . '"' : '') . $this->nameIdHtml ($subwidgetName, true) . ($hasAutofocus ? ' autofocus="autofocus"' : '') . $widget->tabindexHtml () . '>';
					if ($hasAutofocus) {$arguments['autofocus'] = false;}	// Ensure only one has autofocus
				} else {
					
					# Create the widget; this has to differentiate between a non- and a multi-dimensional array because converting all to the latter makes it indistinguishable from a single optgroup array
					$useArrayFormat = ($arguments['multiple']);	// i.e. form[widgetname][] rather than form[widgetname]
					$subwidgetHtml[$subwidget] = "\n\t\t\t<select" . $this->nameIdHtml ($subwidgetName, $useArrayFormat) . ($subwidgetsAreMultiple ? " multiple=\"multiple\" size=\"{$arguments['size']}\"" : '') . ($hasAutofocus ? ' autofocus="autofocus"' : '') . ($arguments['onchangeSubmit'] ? ' onchange="this.form.submit();"' : '') . $widget->tabindexHtml () . '>';
					if (!isSet ($arguments['_valuesMultidimensional'])) {
						if ($arguments['required'] && $arguments['default'] && !$arguments['nullRequiredDefault']) {
							$arguments['valuesWithNull'] = $arguments['values'];	// Do not add a null entry when a required field also has a default
						} else {
							$arguments['valuesWithNull'] = array ('' => $arguments['nullText']) + $arguments['values'];
						}
						foreach ($arguments['valuesWithNull'] as $availableValue => $visible) {
							$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $availableValue);
							$attributesHtml = $this->dataAttributes ($arguments['data'], $availableValue);
							$subwidgetHtml[$subwidget] .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($availableValue) . '"' . ($isSelected ? ' selected="selected"' : '') . $this->nameIdHtml ($subwidgetName, false, $availableValue, true, $idOnly = true) . $attributesHtml . '>' . str_replace ('  ', '&nbsp;&nbsp;', htmlspecialchars ($visible)) . '</option>';
						}
					} else {
						
						# Multidimensional version, which adds optgroup labels
						foreach ($arguments['_valuesMultidimensional'] as $key => $mainValue) {
							if (is_array ($mainValue)) {
								$subwidgetHtml[$subwidget] .= "\n\t\t\t\t<optgroup label=\"{$key}\">";
								foreach ($mainValue as $availableValue => $visible) {
									$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $availableValue);
									$attributesHtml = $this->dataAttributes ($arguments['data'], $availableValue);
									$subwidgetHtml[$subwidget] .= "\n\t\t\t\t\t" . '<option value="' . htmlspecialchars ($availableValue) . '"' . ($isSelected ? ' selected="selected"' : '') . $attributesHtml . '>' . str_replace ('  ', '&nbsp;&nbsp;', htmlspecialchars ($visible)) . '</option>';
								}
								$subwidgetHtml[$subwidget] .= "\n\t\t\t\t</optgroup>";
							} else {
								$isSelected = $this->select_isSelected ($arguments['expandable'], $elementValue, $subwidget, $key);
								$attributesHtml = $this->dataAttributes ($arguments['data'], $key);
								$subwidgetHtml[$subwidget] .= "\n\t\t\t\t" . '<option value="' . htmlspecialchars ($key) . '"' . ($isSelected ? ' selected="selected"' : '') . $attributesHtml . '>' . str_replace ('  ', '&nbsp;&nbsp;', htmlspecialchars ($mainValue)) . '</option>';
							}
						}
					}
					$subwidgetHtml[$subwidget] .= "\n\t\t\t</select>\n\t\t";
				}
			}
			
			# Add an expansion button at the end
			if ($arguments['expandable']) {
				$refreshButtonHtml = $this->subwidgetExpandabilityButtons ($subwidgets, $arguments['name'], $arguments['required'], $totalAvailableOptions);
				$arguments['append'] = $refreshButtonHtml . $arguments['append'];
			}
			
			# Compile the subwidgets into a single widget HTML block
			$widgetHtml  = implode ("\t<br />", $subwidgetHtml);
			
		} else {	// i.e. Non-editable
			
			# Loop through each default argument (if any) to prepare them
			#!# All this stuff isn't even needed if errors have been found
			#!# Need to double-check that $arguments['default'] isn't being changed above this point [$arguments['default'] is deliberately used here because of the $identifier system above]
			$presentableDefaults = array ();
			foreach ($arguments['default'] as $argument) {
				if (array_key_exists ($argument, $arguments['values'])) {	// This is used rather than isSet ($arguments['values'][$argument]) because the visible value might be unset (hence NULL), resulting in the key not ending up in the eventual data
					$presentableDefaults[$argument] = ($arguments['entities'] ? htmlspecialchars ($arguments['values'][$argument]) : $arguments['values'][$argument]);
				}
			}
			
			# Set the widget HTML
			$widgetHtml  = implode ("<span class=\"comment\">,</span>\n<br />", array_values ($presentableDefaults));
			if (!$presentableDefaults) {
				$widgetHtml .= "\n\t\t\t<span class=\"comment\">(None)</span>";
			} else {
				foreach ($presentableDefaults as $value => $visible) {
					$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], true /* True should be used so that the _POST is the same structure (which is useful if the user is capturing that data before using its API), even though this is actually ignored in processing */) . ' type="hidden" value="' . htmlspecialchars ($value) . '" />';
				}
			}
			
			# Re-assign the values back to the 'submitted' form value
			$elementValue = array_keys ($presentableDefaults);
		}
		
		# If a warning has been generated, show this
		$widgetHtml .= $this->showWarning ($warningHtml);
		
		# Support copyTo - sets the value of another field to the selected option's visible text if it is currently empty or changed again
		$this->copyTo ($arguments);
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null or fake submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# In autocomplete auto-values mode, assume that whatever the user has posted is correct
			if ($autocompleteAutovaluesMode) {
				if ($this->form[$arguments['name']]) {
					$arguments['values'] = array_combine ($this->form[$arguments['name']], $this->form[$arguments['name']]);
				}
			}
			
			# Loop through each defined element name
			#!# Needs to normalise spaces between items, e.g. "|a|b |c|d" doesn't get cleaned up
			$chosenValues = array ();
			$chosenVisible = array ();
			foreach ($arguments['values'] as $value => $visible) {
				
				# Determine if the value has been submitted
				$isSubmitted = (in_array ($value, $this->form[$arguments['name']]));
				
				# rawcomponents is 'An array with every defined element being assigned as itemName => boolean true/false'
				$data['rawcomponents'][$value] = $isSubmitted;
				
				# compiled is 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3'
				# presented is 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value'
				if ($isSubmitted) {
					$chosenValues[] = $value;
					$chosenVisible[] = $visible;
				}
			}
			
			# Assemble the compiled and presented versions
			$data['compiled'] = implode ($arguments['separator'], $chosenValues);
			$data['presented'] = implode ($arguments['separator'], $chosenVisible);
			
			# Add the surround if required
			if ($arguments['separatorSurround']) {
				if (strlen ($data['compiled']))  {$data['compiled']  = $arguments['separator'] . $data['compiled']  . $arguments['separator'];}
				if (strlen ($data['presented'])) {$data['presented'] = $arguments['separator'] . $data['presented'] . $arguments['separator'];}
			}
		}
		
		# Compile the datatype
		$datatype = array ();
		foreach ($arguments['values'] as $key => $value) {
			$datatype[] = str_replace ("'", "\'", $key);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $this->_suitableAsEmailTarget (array_keys ($arguments['values']), $arguments),
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'multiple' => $arguments['multiple'],
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')") . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => 'compiled',
			'after' => $arguments['after'],
		);
	}
	
	
	# Function to render a warning
	private function showWarning ($warningHtml)
	{
		# Show the warning if present
		if (!$warningHtml) {return false;}
		return '<br /><span class="warning"><strong>&#9888; Warning:</strong> ' . $warningHtml . '</span>';
	}
	
	
	# Helper function for generating data-* attributes HTML
	private function dataAttributes ($data, $availableValue)
	{
		# If not present, end
		if (!isSet ($data[$availableValue])) {return false;}
		
		# Loop through each attribute set
		$attributesHtml = array ();
		foreach ($data[$availableValue] as $key => $value) {
			$attributesHtml[] = 'data-' . htmlspecialchars ($key) . '="' . htmlspecialchars ($value) . '"';
		}
		
		# Compile the HTML
		$html = ' ' . implode (' ', $attributesHtml);
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function for generating subwidget add/subtract buttons
	private function subwidgetExpandabilityButtons ($subwidgetsCount, $name, $required, $totalAvailableOptions = false /* false represents no limit */)
	{
		# Add a counter as a hidden field
		$html  = '<input type="hidden" name="' . $this->subwidgetsCounterWidgetName ($name) . '" value="' . $subwidgetsCount . '" />';
		
		# Button for subtracting a widget, if this is possible
		if (($subwidgetsCount > $required) && ($subwidgetsCount > 1)) {
			$html .= '<input type="submit" value="&#10006;" title="Subtract the last item" name="__refresh_subtract_' . $this->cleanId ($name) . '" class="refresh" />';
		}
		
		# Button for addition, if this is possible; if there is no limit, then always allow this
		if (!$totalAvailableOptions || ($subwidgetsCount < $totalAvailableOptions)) {
			$html .= '<input type="submit" value="&#10010;" title="Add another item" name="__refresh_add_' . $this->cleanId ($name) . '" class="refresh" />';
		}
		
		# Register the multiple submit handler
		$this->multipleSubmitReturnHandlerJQuery ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to generate the subwidgets counter widget name
	private function subwidgetsCounterWidgetName ($name)
	{
		#!# NB Need to deny __refresh_add_<cleaned-id>, __refresh_subtract_<cleaned-id>, and __subwidgets_<cleaned-id> as reserved form names
		return '__subwidgets_counter_' . $this->cleanId ($name);
	}
	
	
	# Helper function for determining the count of subwidgets based on manual expansion by the user pressing add/subtract buttons
	private function subwidgetExpandabilityCount ($subwidgetsCount, $name, $required, &$autofocus)
	{
		# Determine if the subwidgets counter field has been submitted, that it is numeric, and if not, return the supplied total unmodified
		$subwidgetsCounterWidgetName = $this->subwidgetsCounterWidgetName ($name);
		if (!isSet ($this->collection[$subwidgetsCounterWidgetName]) || !ctype_digit ($this->collection[$subwidgetsCounterWidgetName])) {
			return $subwidgetsCount;
		}
		
		# Override the supplied default number of subwidgets with the posted numeric value
		$subwidgetsCount = $this->collection[$subwidgetsCounterWidgetName];
		
		# If the 'add' refresh button has been submitted, increment the total by one, and set the autofocus to the new index value
		$checkForRefreshAddWidgetName = '__refresh_add_' . $this->cleanId ($name);
		if (isSet ($this->collection[$checkForRefreshAddWidgetName])) {
			$subwidgetsCount++;
			$autofocus = $subwidgetsCount;	// Autofocus is 1-indexed, i.e. 1,2,3,4 if there are 4 widgets, not 0,1,2,3
		}
		
		# If the 'substract' refresh button has been submitted, decrement the total by one (as long as it is at least the number of required fields), and set the autofocus to the new index value
		$checkForRefreshSubtractWidgetName = '__refresh_subtract_' . $this->cleanId ($name);
		if (isSet ($this->collection[$checkForRefreshSubtractWidgetName])) {
			if (($subwidgetsCount > $required) && ($subwidgetsCount > 1)) {		// Check there are enough initial number of subwidgets to subtract from
				$subwidgetsCount--;
				$autofocus = $subwidgetsCount;	// Autofocus is 1-indexed, i.e. 1,2,3,4 if there are 4 widgets, not 0,1,2,3
			}
		}
		
		# Return the number of subwidgets
		return $subwidgetsCount;
	}
	
	
	# Helper function for select fields to determine whether a value is selected
	function select_isSelected ($expandable, $elementValue, $subwidget, $availableValue)
	{
		if ($expandable) {
			$isSelected = (isSet ($elementValue[$subwidget]) ? ($availableValue == $elementValue[$subwidget]) : false);
		} else {
			$isSelected = (in_array ($availableValue, $elementValue));
		}
		return $isSelected;
	}
	
	
	# Helper function to support the copyTo argument
	function copyTo ($arguments)
	{
		# End if not required
		if (!$arguments['copyTo'] || (!is_string ($arguments['copyTo']))) {return;}
		
		# End if the widget does not exist
		if (!isSet ($this->form[$arguments['copyTo']])) {return false;}
		
		# Determine the IDs of the current widget and the target
		$idThis   = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]"   : $arguments['name']);
		$idTarget = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['copyTo']}]" : $arguments['copyTo']);
		
		# Add the jQuery code
		$this->jQueryCode[__FUNCTION__ . $arguments['name']] = "
			$(document).ready(function(){
				var autosetValue = '';
				$('#{$idThis}').change(function() {
					if($('#{$idTarget}').val() == '' || $('#{$idTarget}').val() == autosetValue) {
						autosetValue = $('#{$idThis} option:selected').text();
						$('#{$idTarget}').val(autosetValue);
					}
				});
			});
		";
	}
	
	
	/**
	 * Create a radio-button widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function radiobuttons ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,		# Name of the element
			'editable'				=> true,		# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'values'				=> array (),	# Simple array of selectable values
			'valuesNamesAutomatic'	=> false,		# Whether to create automatic value names based on the value itself (e.g. 'option1' would become 'Option 1')
			'disabled'				=> array (),	# Whether individual radiobuttons are disabled, either true for all (except for a default one), or false for none, or an array of the values that are disabled
			'title'					=> '',			# Introductory text
			'description'			=> '',			# Description text
			'append'				=> '',			# HTML appended to the widget
			'prepend'				=> '',			# HTML prepended to the widget
			'output'				=> array (),		# Presentation format
			'required'				=> false,		# Whether required or not
			'autofocus'				=> false,		# HTML5 autofocus (true/false)
			'default'				=> array (),	# Pre-selected item
			'linebreaks'			=> $this->settings['linebreaks'],	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
			'forceAssociative'		=> false,		# Force the supplied array of values to be associative
			'nullText'				=> $this->settings['nullText'],	# Override null text for a specific widget (if false, the master value is assumed)
			'discard'				=> false,		# Whether to process the input but then discard it in the results
			'datatype'				=> false,		# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,		# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,		# Placing the widget after a specific other widget
			'entities'				=> true,		# Convert HTML in label to entity equivalents
			'titles'				=> array (),	# Title attribute texts, as array (value => string, ...)
			'tolerateInvalid'		=> false,		# Whether to tolerate an invalid default value, and reset the value to empty
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, NULL, $arrayType = false);
		
		$arguments = $widget->getArguments ();
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {$this->form[$arguments['name']] = $arguments['default'];}
		
		# Do a sanity-check to check that a non-editable field can succeed
		#!# Apply to all cases?
		if (!$arguments['editable'] && $arguments['required'] && !strlen ($arguments['default'])) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, you cannot set a non-editable field to be required but have no initial value.";
		}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : '');
		
		$elementValue = $widget->getValue ();
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {
			$this->formSetupErrors['radiobuttonsNoValues'] = "No values have been set as selection items for the <strong>{$arguments['name']}</strong> element.";
			return false;
		}
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name'], $arguments['valuesNamesAutomatic']);
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		
		# Deal with disabled radio buttons
		#!# This standard processing of 'array()/true/false --> list' should be library code
		if ($arguments['disabled'] === false) {
			$arguments['disabled'] = array ();
		}
		if ($arguments['disabled']) {
			if ($arguments['disabled'] === true) {
				$arguments['disabled'] = array ();
				foreach ($arguments['values'] as $value) {
					if ($value != $arguments['default']) {
						$arguments['disabled'][] = $value;
					}
				}
			}
		}
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Loop through each element value to check that it is in the available values, and just discard without comment any that are not
		if (!array_key_exists ($elementValue, $arguments['values'])) {
			$elementValue = false;
		}
		
		# Check whether the field satisfies any requirement for a field to be required
		#!# Migrate this to using $widget->requiredButEmpty when $widget->setValue uses references not copied values
		$requiredButEmpty = ($arguments['required'] && (strlen ($elementValue) == 0));
		
		# Ensure that there cannot be multiple initial values set if the multiple flag is off; note that the default can be specified as an array, for easy swapping with a select (which in singular mode behaves similarly)
		$arguments['default'] = application::ensureArray ($arguments['default']);
		if (count ($arguments['default']) > 1) {
			$this->formSetupErrors['defaultTooMany'] = "In the <strong>{$arguments['name']}</strong> element, $totalDefaults total initial values were assigned but only one can be set as a default.";
		}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments, $warningHtml /* returned by reference */);
		
		# If the field is not a required field (and therefore there is a null text field), ensure that none of the values have an empty string as the value (which is reserved for the null)
		#!# Policy question: should empty values be allowed at all? If so, make a special constant for a null field but which doesn't have the software name included
		if (!$arguments['required'] && in_array ('', array_keys ($arguments['values']), true)) {
			$this->formSetupErrors['defaultNullClash'] = "In the <strong>{$arguments['name']}</strong> element, one value was assigned to an empty value (i.e. '').";
		}
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			foreach ($arguments['default'] as $elementValue) {}
		}
		
		# Define the widget's core HTML
		$widgetHtml = '';
		$subelementsWidgetHtml = array ();
		if ($arguments['editable']) {
			$subwidgetIndex = 1;
			
			# If it's not a required field, add a null field to the selection
			if (!$arguments['required']) {
				#!# Does the 'withNull' fix made to version 1.0.2 need to be applied here?
				$arguments['values'] = array ('' => $arguments['nullText']) + $arguments['values'];
				/* #!# Enable when implementing fieldset grouping
				if (isSet ($arguments['_valuesMultidimensional'])) {
					$arguments['_valuesMultidimensional'] = array ('' => $arguments['nullText']) + $arguments['_valuesMultidimensional'];
				}
				*/
			}
			
			# Create the widget
			/* #!# Write branching code around here which uses _valuesMultidimensional, when implementing fieldset grouping */
			$firstItem = true;
			foreach ($arguments['values'] as $value => $visible) {
				$elementId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}_{$value}]" : "{$arguments['name']}_{$value}");
				
				# Determine whether to include a title attribute
				$title = ($arguments['titles'] && isSet ($arguments['titles'][$value]) ? $arguments['titles'][$value] : false);
				$titleHtml = ($title ? ' title="' . htmlspecialchars ($title) . '"' : '');
				
				#!# Dagger hacked in - fix properly for other such characters; consider a flag somewhere to allow entities and HTML tags to be incorporated into the text (but then cleaned afterwards when printed/e-mailed)
				$subelementsWidgetHtml[$value]  = '<input type="radio"' . $this->nameIdHtml ($arguments['name'], false, $value) . ' value="' . htmlspecialchars ($value) . '"' . ($value == $elementValue ? ' checked="checked"' : '') . (($arguments['autofocus'] && $firstItem) ? ' autofocus="autofocus"' : '') . (in_array ($value, $arguments['disabled'], true) ? ' disabled="disabled"' : '') . $titleHtml . $widget->tabindexHtml ($subwidgetIndex - 1) . ' />';
				$subelementsWidgetHtml[$value] .= '<label for="' . $elementId . '"' . $titleHtml . '>' . ($arguments['entities'] ? htmlspecialchars ($visible) : $visible) . '</label>';
				$widgetHtml .= "\n\t\t\t" . $subelementsWidgetHtml[$value];
				$firstItem = false;
				
				# Add a line break if required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				$subwidgetIndex++;
			}
			$widgetHtml .= "\n\t\t";
		} else {
			
			# Set the widget HTML if any default is given
			if ($arguments['default']) {
				foreach ($arguments['values'] as $value => $visible) {
					if ($value == $elementValue) {	// This loop is done to prevent offsets which may still arise due to the 'defaultMissingFromValuesArray' error not resulting in further termination of widget production
						#!# Offset generated here if editable false and the preset value not present
						$widgetHtml  = htmlspecialchars ($arguments['values'][$elementValue]);
						$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], false, $elementValue) . ' type="hidden" value="' . htmlspecialchars ($elementValue) . '" />';
					}
				}
			}
		}
		
		# If a warning has been generated, show this
		$widgetHtml .= $this->showWarning ($warningHtml);
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# For the rawcomponents version, create An array with every defined element being assigned as itemName => boolean true/false
			$data['rawcomponents'] = array ();
			foreach ($arguments['values'] as $value => $visible) {
				$data['rawcomponents'][$value] = ($this->form[$arguments['name']] == $value);
			}
			
			# Take the selected option and ensure that this is in the array of available values
			#!# What if it's not? - This check should be moved up higher
			$data['compiled'] = (in_array ($this->form[$arguments['name']], array_keys ($arguments['values'])) ? $this->form[$arguments['name']] : '');
			
			# For the presented version, use the visible text version
			$data['presented'] = (in_array ($this->form[$arguments['name']], array_keys ($arguments['values'])) ? $arguments['values'][$this->form[$arguments['name']]] : '');
		}
		
		# Compile the datatype
		foreach ($arguments['values'] as $key => $value) {
			$datatype[] = str_replace ("'", "\'", $key);
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'subelementsWidgetHtml' => $subelementsWidgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => $arguments['required'],
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . "ENUM ('" . implode ("', '", $datatype) . "')") . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'groupValidation' => 'compiled',
			'after' => $arguments['after'],
		);
	}
	
	
	/**
	 * Create a checkbox(es) widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	function checkboxes ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			#!# Missing this value out causes errors lower
			'values'				=> array (),# Simple array of selectable values; if a string is supplied it will be converted to an array of one item
			'valuesNamesAutomatic'	=> false,	# Whether to create automatic value names based on the value itself (e.g. 'option1' would become 'Option 1')
			'disabled'				=> array (),# Whether individual checkboxes are disabled, formatted as array of value => 0|1 for each checkbox
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> 0,		# The minimum number which must be selected (defaults to 0)
			'maximum'				=> 0,		# The maximum number which must be selected (defaults to 0, i.e. no maximum checking done)
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'			=> array (),	# Pre-selected item(s); if a string is supplied it will be converted to an array of one item
			'defaultPresplit'		=> false,	# Whether to pre-split a default that is a string, using the separator and separatorSurround values
			'separator'				=> ",\n",	# Separator used for the compiled and presented output types
			'separatorSpecialSetdatatype' => ',',	# Separator used for the special-setdatatype output types
			'separatorSurround'		=> false,	# Whether, for the compiled and presented output types, if there are any output values, the separator should also be used to surround the values (e.g. |value1|value2|value3| rather than value1|value2|value3 for separator = '|')
			'forceAssociative'		=> false,	# Force the supplied array of values to be associative
			'labels'				=> true,	# Whether to generate labels
			'labelsSurround'			=> $this->settings['labelsSurround'],	# Whether to use the surround method of label HTML formatting
			'linebreaks'			=> $this->settings['linebreaks'],	# Whether to put line-breaks after each widget: true = yes (default) / false = none / array (1,2,5) = line breaks after the 1st, 2nd, 5th items
			'columns'				=> false,	# Split into columns
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'truncate'				=> $this->settings['truncate'],	# Override truncation setting for a specific widget
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'entities'				=> true,	# Convert HTML in label to entity equivalents
			'tolerateInvalid'		=> false,	# Whether to tolerate an invalid default value, and reset the value to empty
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__, NULL, $arrayType = true);
		
		$arguments = $widget->getArguments ();
		
		# If pre-splitting is required, split
		#!# Ideally this would use separatorSpecialSetdatatype instead but ultimateForm has no way of being sure which format is being supplied
		if ($arguments['defaultPresplit']) {
			if (is_string ($arguments['default']) && strlen ($arguments['default'])) {
				$splittableString = true;
				if ($arguments['separatorSurround']) {
					$splittableString = false;
					$delimiter = '/';
					$delimiter . '^' . preg_quote ($arguments['separator']) . '(.+)' . preg_quote ($arguments['separator']) . '$' . $delimiter;
					if (preg_match ($delimiter . '^' . preg_quote ($arguments['separator'], $delimiter) . '(.+)' . preg_quote ($arguments['separator'], $delimiter) . '$' . $delimiter, $arguments['default'], $matches)) {
						$splittableString = true;
						$arguments['default'] = $matches[1];
					}
				}
				if ($splittableString) {
					$separator = str_replace ("\r\n", "\n", $arguments['separator']);
					$default   = str_replace ("\r\n", "\n", $arguments['default']);
					$arguments['default'] = explode ($separator, $default);
				}
			}
		}
		
		# Ensure the supplied values and initial value(s) are each an array, even if only an empty one, converting if necessary
		$arguments['values'] = application::ensureArray ($arguments['values']);
		$arguments['default'] = application::ensureArray ($arguments['default']);
		
		# Ensure 'disabled' is an array, or disable it
		if (!is_array ($arguments['disabled'])) {$arguments['disabled'] = array ();}
		
		# Obtain the value of the form submission (which may be empty)
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# If the values are not an associative array, convert the array to value=>value format and replace the initial array
		$arguments['values'] = $this->ensureHierarchyAssociative ($arguments['values'], $arguments['forceAssociative'], $arguments['name'], $arguments['valuesNamesAutomatic']);
		
		# Check that the array of values is not empty
		if (empty ($arguments['values'])) {
			$this->formSetupErrors['checkboxesNoValues'] = 'No values have been set for the set of checkboxes for the <em>' . htmlspecialchars ($arguments['name']) . '</em> field.';
			return false;
		}
		
		# Apply truncation if necessary
		$arguments['values'] = $widget->truncate ($arguments['values']);
		
		/* #!# Enable when implementing fieldset grouping
		# If a multidimensional array, cache the multidimensional version, and flatten the main array values
		if (application::isMultidimensionalArray ($arguments['values'])) {
			$arguments['_valuesMultidimensional'] = $arguments['values'];
			$arguments['values'] = application::flattenMultidimensionalArray ($arguments['values']);
		}
		*/
		
		# Check that the given minimum required is not more than the number of checkboxes actually available
		$totalSubItems = count ($arguments['values']);
		if ($arguments['required'] > $totalSubItems) {$this->formSetupErrors['checkboxesMinimumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, The required minimum number of checkboxes (<strong>{$arguments['required']}</strong>) specified is above the number of checkboxes actually available (<strong>$totalSubItems</strong>).";}
		if ($arguments['maximum'] && $arguments['required'] && ($arguments['maximum'] < $arguments['required'])) {$this->formSetupErrors['checkboxesMaximumMismatch'] = "In the <strong>{$arguments['name']}</strong> element, A maximum and a minimum number of checkboxes have both been specified but this maximum (<strong>{$arguments['maximum']}</strong>) is less than the minimum (<strong>{$arguments['required']}</strong>) required.";}
		
		# Ensure that all initial values are in the array of values
		$this->ensureDefaultsAvailable ($arguments, $warningHtml /* returned by reference */);
		
		# Start a tally to check the number of checkboxes checked
		$checkedTally = 0;
		
		# Determine whether to use columns, and ensure there are no more than the number of arguments, then set the number per column
		if ($splitIntoColumns = ($arguments['columns'] && ctype_digit ((string) $arguments['columns']) && ($arguments['columns'] > 1) ? min ($arguments['columns'], count ($arguments['values'])) : false)) {
			$splitIntoColumns = ceil (count ($arguments['values']) / $splitIntoColumns);
		}
		
		# Loop through each pre-defined element subname to construct the HTML
		$widgetHtml = '';
		$subelementsWidgetHtml = array ();
		if ($arguments['editable']) {
			/* #!# Write branching code around here which uses _valuesMultidimensional, when implementing fieldset grouping */
			$subwidgetIndex = 1;
			if ($splitIntoColumns) {$widgetHtml .= "\n\t\t\t<table class=\"checkboxcolumns\">\n\t\t\t\t<tr>\n\t\t\t\t\t<td>";}
			foreach ($arguments['values'] as $value => $visible) {
				
				# If the form is not posted, assign the initial value (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
				if (!$this->formPosted) {
					if (in_array ($value, $arguments['default'])) {
						$elementValue[$value] = true;
					}
				}
				
				# Apply stickyness to each checkbox if necessary
				$stickynessHtml = '';
				if (isSet ($elementValue[$value])) {
					if ($elementValue[$value]) {
						$stickynessHtml = ' checked="checked"';
						
						# Tally the number of items checked
						$checkedTally++;
					}
				} else {
					# Ensure every element is defined (even if empty), so that the case of writing to a file doesn't go wrong
					$elementValue[$value] = '';
				}
				
//				# Construct the element ID, which must be unique
				$elementId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}_{$value}]" : "{$arguments['name']}_{$value}");
				
				# Determine whether to disable this checkbox
				$disabled = ((isSet ($arguments['disabled'][$value]) && $arguments['disabled'][$value]) ? ' disabled="disabled"' : '');
				
				# Create the HTML; note that spaces (used to enable the 'label' attribute for accessibility reasons) in the ID will be replaced by an underscore (in order to remain valid XHTML)
//				//$widgetHtml .= "\n\t\t\t" . '<input type="checkbox" name="' . ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']) . "[{$value}]" . '" id="' . $elementId . '" value="true"' . $stickynessHtml . ' />' . ($arguments['labels'] ? '<label for="' . $elementId . '">' . htmlspecialchars ($visible) . '</label>' : '');
				$label = ($arguments['entities'] ? htmlspecialchars ($visible) : $visible);
				$subelementsWidgetHtml[$value] = '<input type="checkbox"' . $this->nameIdHtml ($arguments['name'], false, $value, true) . ' value="true"' . $stickynessHtml . (($arguments['autofocus'] && $subwidgetIndex == 1)  ? ' autofocus="autofocus"' : '') . $widget->tabindexHtml ($subwidgetIndex - 1) . $disabled . ' />' . ($arguments['labels'] && !$arguments['labelsSurround'] ? '<label for="' . $elementId . '">' . $label . '</label>' : '');
				if ($arguments['labels'] && $arguments['labelsSurround']) {
					$subelementsWidgetHtml[$value] = '<label>' . "\n\t\t\t\t\t" . $subelementsWidgetHtml[$value] . "\n\t\t\t\t\t" . '<span>' . $label . '</span>' . "\n\t\t\t\t" . '</label>';
				}
				$widgetHtml .= "\n\t\t\t\t" . ($splitIntoColumns ? "\t\t" : '') . $subelementsWidgetHtml[$value];
				
				# Add a line/column breaks when required
				if (($arguments['linebreaks'] === true) || (is_array ($arguments['linebreaks']) && in_array ($subwidgetIndex, $arguments['linebreaks']))) {$widgetHtml .= '<br />';}
				if ($splitIntoColumns) {
					if (($subwidgetIndex % $splitIntoColumns) == 0) {
						if ($subwidgetIndex != count ($arguments['values'])) { // Don't add at the end if the number is an exact multiplier
							$widgetHtml .= "\n\t\t\t\t\t</td>\n\t\t\t\t\t<td>";
						}
					}
				}
				$subwidgetIndex++;
			}
			if ($splitIntoColumns) {$widgetHtml .= "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t</table>\n\t\t";}
		} else {
			
			# Loop through each default argument (if any) to prepare them
			#!# Need to double-check that $arguments['default'] isn't being changed above this point [$arguments['default'] is deliberately used here because of the $identifier system above]
			$presentableDefaults = array ();
			foreach ($arguments['default'] as $argument) {
				$presentableDefaults[$argument] = htmlspecialchars ($arguments['values'][$argument]);
			}
			
			# Set the widget HTML
			$widgetHtml  = implode ("<span class=\"comment\">,</span>\n<br />", array_values ($presentableDefaults));
			if (!$presentableDefaults) {
				$widgetHtml .= "\n\t\t\t<span class=\"comment\">(None)</span>";
			} else {
				foreach ($presentableDefaults as $value => $visible) {
					$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name'], false, $value, true) . ' type="hidden" value="true" />';
				}
			}
			
			# Re-assign the values back to the 'submitted' form value
			$elementValue = array ();
			foreach ($arguments['default'] as $argument) {
				$elementValue[$argument] = 'true';
				
				# Tally the number of items 'checked'
				$checkedTally++;
			}
		}
		
		# Make sure the number of checkboxes given is above the $arguments['required']
		if ($checkedTally < $arguments['required']) {
			$elementProblems['insufficientSelected'] = "A minimum of {$arguments['required']} " . ($arguments['required'] == 1 ? 'item' : 'items') . ' must be selected';
		}
		
		# Make sure the number of checkboxes given is above the maximum required
		if ($arguments['maximum']) {
			if ($checkedTally > $arguments['maximum']) {
				$elementProblems['tooManySelected'] = "A maximum of {$arguments['maximum']} " . ($arguments['maximum'] == 1 ? 'item' : 'items') . ' can be selected';
			}
		}
		
		# Describe restrictions on the widget
		#!# Rewrite a more complex but clearer description, e.g. "exactly 3", "between 1 and 3 must", "at least 1", "between 0 and 3 can", etc
		if ($arguments['required']) {$restriction[] = "A minimum of {$arguments['required']} " . ($arguments['required'] == 1 ? 'item' : 'items') . ' must be selected';}
		if ($arguments['maximum']) {$restriction[] = "A maximum of {$arguments['maximum']} " . ($arguments['maximum'] == 1 ? 'item' : 'items') . ' can be selected';}
		if (isSet ($restriction)) {
			$restriction = implode (';<br />', $restriction);
		}
		
		# If a warning has been generated, show this
		$widgetHtml .= $this->showWarning ($warningHtml);
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data; there is no need to clear null or fake submissions because it will just get ignored, not being in the values list
		if ($this->formPosted) {
			
			# Loop through each defined element name
			$chosenValues = array ();
			$chosenVisible = array ();
			foreach ($arguments['values'] as $value => $visible) {
				
				# Determine if the value has been submitted
				$isSubmitted = (isSet ($this->form[$arguments['name']][$value]) && $this->form[$arguments['name']][$value] == 'true');
				
				# If the checkbox is disabled, read the default, therefore ignoring whatever was submitted
				$disabled = (isSet ($arguments['disabled'][$value]) && $arguments['disabled'][$value]);
				if ($disabled) {
					$isSubmitted = (in_array ($value, $arguments['default']));
				}
				
				# rawcomponents is 'An array with every defined element being assigned as itemName => boolean true/false'
				$data['rawcomponents'][$value] = $isSubmitted;
				
				# compiled is 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3'
				# presented is 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value'
				if ($isSubmitted) {
					$chosenValues[] = $value;
					$chosenVisible[] = $visible;
				}
			}
			
			# Assemble the compiled and presented versions
			$data['compiled'] = implode ($arguments['separator'], $chosenValues);
			$data['presented'] = implode ($arguments['separator'], $chosenVisible);
			$data['special-setdatatype'] = implode ($arguments['separatorSpecialSetdatatype'], $chosenValues);
			
			# Add the surround if required
			if ($arguments['separatorSurround']) {
				if (strlen ($data['compiled']))  {$data['compiled']  = $arguments['separator'] . $data['compiled']  . $arguments['separator'];}
				if (strlen ($data['presented'])) {$data['presented'] = $arguments['separator'] . $data['presented'] . $arguments['separator'];}
				if (strlen ($data['special-setdatatype'])) {$data['special-setdatatype'] = $arguments['separatorSpecialSetdatatype'] . $data['special-setdatatype'] . $arguments['separatorSpecialSetdatatype'];}
			}
		}
		
		# Compile the datatype
		$checkboxDatatypes = array ();
		foreach ($arguments['values'] as $key => $value) {
			#!# NOT NULL handling needs to be inserted
			$checkboxDatatypes[] = "`" . /* $arguments['name'] . '-' . */ str_replace ("'", "\'", $key) . "` " . "ENUM ('true', 'false')" . " COMMENT '" . (addslashes ($arguments['title'])) . "'";
		}
		$datatype = implode (",\n", $checkboxDatatypes);
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'subelementsWidgetHtml' => $subelementsWidgetHtml,
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => false, # This is covered by $elementProblems
			#!# Apply $this->_suitableAsEmailTarget () to checkboxes possibly
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'values' => $arguments['values'],
			#!# Not correct - needs multisplit into boolean
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : $datatype),
			'groupValidation' => 'compiled',
			'total' => $checkedTally,
			'after' => $arguments['after'],
		);
	}
	
	
	/**
	 * Create a date/datetime widget set
	 * @param array $arguments Supplied arguments - see template
	 */
	#!# Need to add HTML5 equivalents
	#!# Need to add support for 'current'
	function datetime ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'required'				=> false,	# Whether required or not
			'level'					=> 'date',	# Whether to show 'datetime' / 'date' / 'time' / 'year' widget set
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> '',		# Initial value - either 'timestamp' or an SQL string
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			'autoCenturyConversion'	=> 69,		# The last two figures of the last year where '20' is automatically prepended, or false to disable (and thus require four-digit entry)
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'prefill'				=> false,	# Whether to include pre-fill link: '[Now]'
			'picker'				=> $this->settings['picker'],	# Whether to enable a javascript datepicker for the 'date' level
			'pickerAutosubmit'		=> false,	# Whether to submit the form if a picker value is selected
			#!# Currently min/max are only implemented client-side
			'min'					=> '1970-01-01',	# Minimum date in the picker
			'max'					=> '2069-12-31',	# Maximum date in the picker
		);
		
		# Define the supported levels
		$levels = array (
			'time'		=> 'H:i:s',			// Widget order: t
			'datetime'	=> 'Y-m-d H:i:s',	// Widget order: tdmy
			'date'		=> 'Y-m-d',			// Widget order: dmy
			'year'		=> 'Y',				// Widget order: y
		);
		
		# Load the date processing library
		#!# Ideally this really should be higher up in the class, e.g. in the setup area
		require_once ('timedate.php');
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Check the level is supported
		if (!array_key_exists ($arguments['level'], $levels)) {
			$this->formSetupErrors['levelInvalid'] = "An invalid 'level' (" . htmlspecialchars ($arguments['level']) . ') was specified in the ' . htmlspecialchars ($arguments['name']) . ' datetime widget.';
			#!# Really this should end at this point rather than adding a fake reassignment
			$arguments['level'] = 'datetime';
		}
		
		# If the picker argument has been enabled but the level is not date, disable it
		if ($arguments['picker'] && ($arguments['level'] != 'date')) {$arguments['picker'] = false;}
		
		# Convert the default if using the 'timestamp' keyword; cache a copy for later use; add a null date for the time version
		$isTimestamp = ($arguments['default'] == 'timestamp');
		if ($isTimestamp) {
			$arguments['default'] = date ($levels[$arguments['level']]);
		}
		
		# If the widget is not editable, fix the form value to the default
		if (!$arguments['editable']) {
			$this->form[$arguments['name']] = timedate::getDateTimeArray ((($arguments['level'] == 'time') ? '0000-00-00 ' : '') . $arguments['default']);
		}
		
		# For picker mode, emulate a set of submitted per-part widgets by splitting the submitted ISO string into the constituent parts
		if ($arguments['picker']) {
			if ($arguments['editable']) {
				if (isSet ($this->form[$arguments['name']])) {
					
					# Firstly, convert a jQuery picker -style date like 20/07/2012 to 2012-07-20
					if (preg_match ('~^([0-9]{2})/([0-9]{2})/([0-9]{4})$~', $this->form[$arguments['name']], $matches)) {
						$this->form[$arguments['name']] = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
					}
					
					# Now do the main conversion
					if (preg_match ('~^([0-9]{4})-([0-9]{2})-([0-9]{2})$~', $this->form[$arguments['name']], $matches)) {
						$this->form[$arguments['name']] = array ('year' => $matches[1], 'month' => $matches[2], 'day' => $matches[3]);
					}
				}
			}
		}
		
		# Obtain the value of the form submission (which may be empty)  (ensure that a full date and time array exists to prevent undefined offsets in case an incomplete set has been posted)
		$value = (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		$fields = array ('time', 'day', 'month', 'year', );
		foreach ($fields as $field) {
			if (!isSet ($value[$field])) {
				$value[$field] = '';
			}
		}
		$widget->setValue ($value);
		
		$elementValue = $widget->getValue ();
		
		# Start a flag later used for checking whether all fields are empty against the requirement that a field should be completed
		$requiredButEmpty = false;
		
		# Assign the initial value if the form is not posted (this bypasses any checks, because there needs to be the ability for the initial value deliberately not to be valid)
		if (!$this->formPosted) {
			$elementValue = timedate::getDateTimeArray ((($arguments['level'] == 'time') ? '0000-00-00 ' : '') . $arguments['default']);
		} else {
 			
			# Ensure all numeric fields are numeric, and reset to an empty string if so
			$fields = array ('day', 'month', 'year', );
			foreach ($fields as $field) {
				if (isSet ($elementValue[$field]) && !empty ($elementValue[$field])) {
					$elementValue[$field] = trim ($elementValue[$field]);
					if (!ctype_digit ($elementValue[$field])) {
						$elementValue[$field] = '';
					}
				}
			}
			
			# Check whether all fields are empty, starting with assuming all fields are not incomplete
			#!# This section needs serious (switch-based?) refactoring
			#!# Check about empty(0)
			$allFieldsIncomplete = false;
			if ($arguments['level'] == 'datetime') {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year'])) && (empty ($elementValue['time']))) {$allFieldsIncomplete = true;}
			} else if ($arguments['level'] == 'year') {
				if (empty ($elementValue['year'])) {$allFieldsIncomplete = true;}
				# Emulate the day and month as being the first, to avoid branching the logic
				$elementValue['day'] = 1;
				$elementValue['month'] = 1;
			} else if ($arguments['level'] == 'time') {
				if (empty ($elementValue['time'])) {$allFieldsIncomplete = true;}
			} else {
				if ((empty ($elementValue['day'])) && (empty ($elementValue['month'])) && (empty ($elementValue['year']))) {$allFieldsIncomplete = true;}
			}
			
			# If all fields are empty, and the widget is required, set that the field is required but empty
			if ($allFieldsIncomplete) {
				if ($arguments['required']) {$requiredButEmpty = true;}
			} else {
				
				# Do date-based checks
				if ($arguments['level'] != 'time') {
					
					# If automatic conversion is set and the year is two characters long, convert the date to four years by adding 19 or 20 as appropriate
					if (($arguments['autoCenturyConversion']) && (strlen ($elementValue['year']) == 2)) {
						$elementValue['year'] = (($elementValue['year'] <= $arguments['autoCenturyConversion']) ? '20' : '19') . $elementValue['year'];
					}
					
					# Deal with month conversion by adding leading zeros as required
					if (($elementValue['month'] > 0) && ($elementValue['month'] <= 12)) {$elementValue['month'] = sprintf ('%02s', $elementValue['month']);}
					
					# Check that all parts have been completed
					if ((empty ($elementValue['day'])) || (empty ($elementValue['month'])) || (empty ($elementValue['year'])) || (($arguments['level'] == 'datetime') && (empty ($elementValue['time'])))) {
						$elementProblems['notAllComplete'] = "Not all parts have been completed!";
					} else {
						
						# Check that a valid month (01-12, corresponding to Jan-Dec respectively) has been submitted
						if ($elementValue['month'] > 12) {
							$elementProblems['monthFieldInvalid'] = 'The month part is invalid!';
						}
						
						# Check that the day and year fields are numeric
						if ((!is_numeric ($elementValue['day'])) && (!is_numeric ($elementValue['year']))) {
							$elementProblems['dayYearFieldsNotNumeric'] = 'Both the day and year part must be numeric!';
						} else {
							
							# Check that the day is numeric
							if (!is_numeric ($elementValue['day'])) {
								$elementProblems['dayFieldNotNumeric'] = 'The day part must be numeric!';
							}
							
							# Check that the year is numeric
							if (!is_numeric ($elementValue['year'])) {
								$elementProblems['yearFieldNotNumeric'] = 'The year part must be numeric!';
								
							# If the year is numeric, ensure the year has been entered as a two or four digit amount
							} else {
								if ($arguments['autoCenturyConversion']) {
									if ((strlen ($elementValue['year']) != 2) && (strlen ($elementValue['year']) != 4)) {
										$elementProblems['yearInvalid'] = 'The year part must be either two or four digits!';
									}
								} else {
									if (strlen ($elementValue['year']) != 4) {
										$elementProblems['yearInvalid'] = 'The year part must be four digits!';
									}
								}
							}
						}
						
						# If all date parts have been entered correctly, check whether the date is valid
						if (!isSet ($elementProblems)) {
							if (!checkdate (($elementValue['month']), $elementValue['day'], $elementValue['year'])) {
								$elementProblems['dateInvalid'] = 'An invalid date has been entered!';
							}
						}
					}
				}
				
				# If the time is required in addition to the date, parse the time field, allowing flexible input syntax
				if (($arguments['level'] == 'datetime') || ($arguments['level'] == 'time')) {
					
					# Only do time processing if the time field isn't empty
					if (!empty ($elementValue['time'])) {
						
						# If the time parsing passes, substitute the submitted version with the parsed and corrected version
						if ($time = timedate::parseTime ($elementValue['time'])) {
							$elementValue['time'] = $time;
						} else {
							
							# If, instead, the time parsing fails, leave the original submitted version and add the problem to the errors array
							$elementProblems['timePartInvalid'] = 'The time part is invalid!';
						}
					}
				}
			}
		}
		
/*	Not sufficiently tested - results in 31st November 20xx when all set to 0
		# Prevent mktime parameter problems in date processing
		foreach ($elementValue as $key => $value) {
			if ($value === '') {
				$elementValue[$key] = 0;
			}
		}
*/
		
		# Describe restrictions on the widget
		if (($arguments['level'] == 'datetime') || ($arguments['level'] == 'time')) {$restriction = 'Time can be entered flexibly';}
		
		# Define the widget's core HTML
		if ($arguments['editable']) {
			
			# For the picker version, create an HTML5 date widget, to which we will then attach fallback Javascript
			if ($arguments['picker']) {
				
				# Compile an YYYY-MM-DD (ISO 8601 Extended) version of the element value
				$elementValueIso = $elementValue['year'] . '-' . $elementValue['month'] . '-' . $elementValue['day'];
				
				# Create the basic widget; NB the submission of a type="date" widget will always be YYYY-MM-DD (ISO 8601 Extended) whatever the input GUI format is - see http://dev.w3.org/html5/spec-author-view/forms.html#input-author-notes
				$widgetHtml  = "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name']) . ' type="date" size="20"' . ($arguments['autofocus'] ? ' autofocus="autofocus"' : '') . " value=\"" . htmlspecialchars ($elementValueIso) . '"' . $widget->tabindexHtml () . ($arguments['min'] ? " min=\"{$arguments['min']}\"" : '') . ($arguments['max'] ? " max=\"{$arguments['max']}\"" : '') . ' />';
				
				# Determine min and max dates for the fallback picker
				$minDate = (($arguments['min'] && preg_match ('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $arguments['min'], $matches)) ? "new Date({$matches[1]}, " . ($matches[2] - 1) . ', ' . (int) $matches[3] . ')' : 'null');	// e.g. 2012-07-22 becomes new Date(2012, 6, 22)
				$maxDate = (($arguments['min'] && preg_match ('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $arguments['max'], $matches)) ? "new Date({$matches[1]}, " . ($matches[2] - 1) . ', ' . (int) $matches[3] . ')' : 'null');
				
				# Add jQuery UI javascript for the date picker; see: http://jqueryui.com/demos/datepicker/
				$this->enableJqueryUi ();
				$widgetId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']);
				# NB This has to be done in Javascript rather than PHP because the main part of this is client-side testing of actual browser support rather than just a browser number
				$this->jQueryCode[__FUNCTION__ . $widgetId] = "
				// Date picker
				var i = document.createElement('input');	// Create a bogus element for testing browser support of <input type=date>
				i.setAttribute('type', 'date');
				var html5Support = (i.type !== 'text');
				if (navigator.userAgent.match(/Chrom(e|ium)\//i) && parseInt(navigator.userAgent.match(/Chrom(e|ium)\/([0-9]+)\./i)[2]) < 21) {	// Prior to Chrome 20, the date picker just had an up/down rocker
					html5Support = false;
				}
				if (!navigator.userAgent.match(/Chrom(e|ium)\//i)) {
					if (navigator.userAgent.match(/Safari\//i) && parseInt(navigator.userAgent.match(/Version\/([0-9]+)\./i)[1]) < 6) {	// Safari 5 date picker just had an up/down rocker
						html5Support = false;
					}
				}
				if(!html5Support) {
					var dateDefaultDate_{$arguments['name']} = " . ($elementValue['year'] ? "new Date({$elementValue['year']}, {$elementValue['month']} - 1, {$elementValue['day']})" : 'null') . ";	// http://stackoverflow.com/questions/1953840/datepickersetdate-issues-in-jquery
					$(function() {
						$('#{$widgetId}').datepicker({
							changeMonth: true,
							changeYear: true,
							dateFormat: 'dd/mm/yy',
							defaultDate: dateDefaultDate_{$arguments['name']},
							minDate: {$minDate},
							maxDate: {$maxDate}
						});
						$('#{$widgetId}').datepicker('setDate', dateDefaultDate_{$arguments['name']});
						$('#{$widgetId}').after('<br /><span class=\"small comment\">Enter as dd/mm/yyyy</span>');
						
						// IE fix to avoid picker being in wrong position on page; see: http://stackoverflow.com/a/16925979/180733
						$('#{$widgetId}').on('click', function() {
							if (navigator.userAgent.match(/msie/i)) {
								var self;
								self = $(this);
								$('#ui-datepicker-div').hide();
								setTimeout(function(){
									$('#ui-datepicker-div').css({
										top: self.offset().top + document.body.scrollTop + 30
									});
									$('#ui-datepicker-div').show();
								}, 0);
							}
						});
					});
				}";
				
				# Enable autosubmit if required; see: http://stackoverflow.com/questions/11532433 for the HTML5 picker, and http://stackoverflow.com/questions/6471959 for the jQuery picker
				if ($arguments['pickerAutosubmit']) {
					$this->jQueryCode[__FUNCTION__ . $widgetId] .= "\n
				// Date picker autosubmit (HTML/jQuery picker)
				$(function() {
					if(html5Support) {
						var el = document.getElementById('{$widgetId}');
						el.addEventListener('input', function(e) {	// i.e. oninput
							$('form[name={$this->settings['name']}]').submit();
						}, false);
					} else {
						$('#{$widgetId}').change(function() {
							$('form[name={$this->settings['name']}]').submit();
						});
					}
				});
				";
				}
				
			# Non-picker version - three separate fields for date, month and year
			} else {
				
				$firstSubwidget = true;
				$widgetHtml = '';
				
				# Start with the time if required
				if (substr_count ($arguments['level'], 'time')) {	// datetime or time
					$widgetHtml .= "\n\t\t\t\t" . '<span class="' . (!isSet ($elementProblems['timePartInvalid']) ? 'comment' : 'warning') . '">t:&nbsp;</span>';
					$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, 'time', true) . ' type="text" size="10" value="' . $elementValue['time'] . '"' . (($arguments['autofocus'] && $firstSubwidget) ? ' autofocus="autofocus"' : '') . $widget->tabindexHtml () . ' />';
					$firstSubwidget = false;
				}
				
				# Add the date and month input boxes; if the day or year are 0 then nothing will be displayed
				if (substr_count ($arguments['level'], 'date')) {	// datetime or date
					$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">d:&nbsp;</span><input' . $this->nameIdHtml ($arguments['name'], false, 'day', true) . ' size="2" maxlength="2" value="' . (($elementValue['day'] != '00') ? $elementValue['day'] : '') . '"' . (($arguments['autofocus'] && $firstSubwidget) ? ' autofocus="autofocus"' : '') . ($arguments['level'] == 'date' ? $widget->tabindexHtml () : '') . ' />&nbsp;';
					$firstSubwidget = false;
					$widgetHtml .= "\n\t\t\t\t" . '<span class="comment">m:</span>';
					$widgetHtml .= "\n\t\t\t\t" . '<select' . $this->nameIdHtml ($arguments['name'], false, 'month', true) . '>';
					$widgetHtml .= "\n\t\t\t\t\t" . '<option value="">Select</option>';
					$months = array (1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'June', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
					foreach ($months as $monthNumber => $monthName) {
						$widgetHtml .= "\n\t\t\t\t\t" . '<option value="' . sprintf ('%02s', $monthNumber) . '"' . (($elementValue['month'] == sprintf ('%02s', $monthNumber)) ? ' selected="selected"' : '') . '>' . $monthName . '</option>';
					}
					$widgetHtml .= "\n\t\t\t\t" . '</select>';
				}
				
				# Add the year box
				if ($arguments['level'] != 'time') {
					$widgetHtml .= "\n\t\t\t\t" . ($arguments['level'] != 'year' ? '<span class="comment">y:&nbsp;</span>' : '');
					$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, 'year', true) . ' size="4" maxlength="4" value="' . (($elementValue['year'] != '0000') ? $elementValue['year'] : '') . '" ' . (($arguments['autofocus'] && $firstSubwidget) ? ' autofocus="autofocus"' : '') . ($arguments['level'] == 'year' ? $widget->tabindexHtml () : '') . '/>' . "\n\t\t";
					$firstSubwidget = false;
				}
				
				# Add prefill link if required
				if ($arguments['level'] != 'time') {
					if ($arguments['prefill']) {
						$js  = "\n\tfunction zeroPad(num,count)";
						$js .= "\n\t{";
						$js .= "\n\t\tvar numZeropad = num + '';";
						$js .= "\n\t\twhile(numZeropad.length < count) {";
						$js .= "\n\t\t\tnumZeropad = '0' + numZeropad;";
						$js .= "\n\t\t}";
						$js .= "\n\t\treturn numZeropad;";
						$js .= "\n\t}";
						$js .= "\n\tfunction prefillDate(field)";
						$js .= "\n\t{";
						$js .= "\n\t\tvar currentTime = new Date();";
						$dateTypes = array (
							'time' => "zeroPad((currentTime.getHours() + 1), 2) + ':' + zeroPad((currentTime.getMinutes() + 1), 2) + ':' + zeroPad((currentTime.getSeconds() + 1), 2)",
							'day' => 'currentTime.getDate()',
							'month' => 'zeroPad((currentTime.getMonth() + 1), 2)',
							'year' => 'currentTime.getFullYear()',
						);
						foreach ($dateTypes as $dateType => $javascriptFunction) {
							$js .= "\n\t\tvar fieldId = '" . $this->settings['name'] . "_' + field + '_{$dateType}';";
							$js .= "\n\t\tvar oTarget = document.getElementById(fieldId);";
							$js .= "\n\t\tif (oTarget) {";
							$js .= "\n\t\t\toTarget.value = {$javascriptFunction};";
							$js .= "\n\t\t}";
						}
						$js .= "\n\t}";
						$this->javascriptCode[__FUNCTION__] = $js;
						$widgetHtml .= "\n\t\t\t" . '&nbsp;&nbsp;<a href="#" onclick="prefillDate(\'' . $this->cleanId ($arguments['name']) . '\');return false;" class="prefill"><span class="small comment">[Now]</span></a>';
					}
				}
				
				# Surround with a fieldset if necessary
				if (substr_count ($arguments['level'], 'date')) {	// datetime or date
					$widgetHtml  = "\n\t\t\t<fieldset>" . $widgetHtml . "\n\t\t\t</fieldset>";
				}
			}
			
		} else {
			
			# Non-editable version
			$widgetHtml  = timedate::presentDateFromArray ($elementValue, $arguments['level']) . ($isTimestamp ? '<br /><span class="comment">' . (($arguments['level'] != 'time') ? '(Current date' . (($arguments['level'] == 'datetime') ? ' and time' : '') : '(Current time') . ')' . '</span>' : '');
			$widgetHtml .= "\n\t\t\t" . '<input' . $this->nameIdHtml ($arguments['name']) . ' type="hidden" value="' . htmlspecialchars ($arguments['default']) . '" />';
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Get the posted data
		if ($this->formPosted) {
			
			# Map the components directly and assemble the elements into a string
			if ($arguments['level'] == 'year') {
				unset ($this->form[$arguments['name']]['day']);
				unset ($this->form[$arguments['name']]['month']);
			}
			$data['rawcomponents'] = $this->form[$arguments['name']];
			
			# Ensure there is a presented and a compiled version
			$data['presented'] = '';
			$data['compiled'] = '';
			
			# If all items are not empty then produce compiled and presented versions
			if (!$allFieldsIncomplete && !isSet ($elementProblems)) {
				
				# Make the compiled version be in SQL format, i.e. YYYY-MM-DD HH:MM:SS
				$data['compiled'] = (($arguments['level'] == 'time') ? $this->form[$arguments['name']]['time'] : $this->form[$arguments['name']]['year'] . (($arguments['level'] == 'year') ? '' : '-' . $this->form[$arguments['name']]['month'] . '-' . sprintf ('%02s', $this->form[$arguments['name']]['day'])) . (($arguments['level'] == 'datetime') ? ' ' . $this->form[$arguments['name']]['time'] : ''));
				
				# Make the presented version in english text
				#!# date () corrupts dates after 2038; see php.net/date. Suggest not re-presenting it if year is too great.
				$data['presented'] = timedate::presentDateFromArray ($this->form[$arguments['name']], $arguments['level']);
			}
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restriction) && $arguments['editable'] ? $restriction : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $arguments['required'],
			'requiredButEmpty' => $requiredButEmpty,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => (isSet ($data) ? $data : NULL),
			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . strtoupper ($arguments['level'])) . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'after' => $arguments['after'],
		);
	}
	
	
	/**
	 * Create an upload widget set
	 * Note that, for security reasons, browsers do not support setting an initial value.
	 * @param array $arguments Supplied arguments - see template
	 */
	function upload ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'					=> NULL,	# Name of the element
			'title'					=> '',		# Introductory text
			'description'			=> '',		# Description text
			'autofocus'				=> false,	# HTML5 autofocus (true/false)
			'default'				=> false,	# Default value(s) (optional), i.e. the current filename(s) if any
			'editable'				=> true,	# Whether the widget is editable (if not, a hidden element will be substituted but the value displayed)
			'append'				=> '',		# HTML appended to the widget
			'prepend'				=> '',		# HTML prepended to the widget
			'output'				=> array (),# Presentation format
			'directory'				=> NULL,	# Path on disk to the file; any format acceptable
			'previewLocationPrefix'	=> '',		# Path in URL terms to the folder, to be prefixed to the filename, e.g. foo.jpg could become /url/path/for/foo.jpg
			'subfields'				=> 1,		# The number of widgets within the widget set (i.e. available file slots)
			'required'				=> 0,		# The minimum number which must be selected (defaults to 0)
			'size'					=> 30,		# Visible size (optional; defaults to 30)
			'disallowedExtensions'	=> array (),# Simple array of disallowed file extensions (Single-item string also acceptable)
			'allowedExtensions'		=> array (),# Simple array of allowed file extensions (Single-item string also acceptable; '*' means extension required)
			'mime'					=> false,	# Whether to enable the MIME Type check
			'enableVersionControl'	=> true,	# Whether uploading a file of the same name should result in the earlier file being renamed
			'forcedFileName'		=> false,	# Force to a specific filename
			'appendExtension'		=> false,	# An additional extension which gets added to the filename upon upload; the starting dot is not assumed automatically
			'lowercaseExtension'	=> false,	# Make the eventual file extension lowercased
			'discard'				=> false,	# Whether to process the input but then discard it in the results; note that the file will still be uploaded
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
			#!# Consider a way of adding a checkbox to confirm on a per-widget basis; adds quite a few complications though
			'unzip'					=> false,	# Whether to unzip a zip file on arrival, either true/false or the number of files (defaulting to $this->settings['listUnzippedFilesMaximum']) which should be listed in any visible result output
			'attachments'			=> $this->settings['attachments'],	# Whether to send uploaded file(s) as attachment(s) (they will not be unzipped)
			'attachmentsDeleteIfMailed'	=> $this->settings['attachmentsDeleteIfMailed'],	# Whether to delete the uploaded file(s) if successfully mailed
			#!# Change to default to true in a later release once existing applications migrated over
			'flatten'				=> false,	# Whether to flatten the rawcomponents (i.e. default in 'processing' mode) result if only a single subfield is specified
			'tabindex'				=> false,	# Tabindex if required; replace with integer between 0 and 32767 to create
			'after'					=> false,	# Placing the widget after a specific other widget
			'progressbar'			=> false,	# Whether to enable a progress bar; if so, give the AJAX endpoint providing the data; requires the PECL uploadprogress module
			'thumbnail'				=> false,	# Enable HTML5 thumbnail preview; either true (to auto-create a container div), or jQuery-style selector, specifying an existing element
			'thumbnailExpandable'	=> false,	# Whether the thumbnail preview can be expanded in size; at present this merely opens the image in a new window
			'draganddrop'			=> false,	# Whether to convert the element to be styled as a drag and drop zone
		);
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Automatically enable thumbnail when using drag and drop
		if ($arguments['draganddrop']) {
			$arguments['thumbnail'] = true;
		}
		
		# Deal with handling of default file specification
		if ($arguments['default']) {
			$arguments['default'] = application::ensureArray ($arguments['default']);
			
			# Ensure there are not too many default files
			if (count ($arguments['default']) > $arguments['subfields']) {
				$this->formSetupErrors['uploadsMismatch'] = "More default files than there are fields available were supplied for the <strong>{$arguments['name']}</strong> file upload element.";
				return false;
			}
			
			# Reorganise any defaults into the same hierarchy as would be posted by the form (rather than just being a single-dimensional array of names) and discard all other supplied info
			$confirmedDefault = array ();
			for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
				if (isSet ($arguments['default'][$subfield])) {
					if (strlen ($arguments['default'][$subfield])) {	// i.e. ensure there is actually a filename
						$confirmedDefault[$subfield] = array (
							'name'		=> $arguments['default'][$subfield],
							'type'		=> NULL,
							'tmp_name'	=> NULL,
							'size'		=> NULL,
							'_source'	=> 'default',
						);
					}
				}
			}
			$arguments['default'] = $confirmedDefault;	// Overwrite the original supplied simple array with the new validated multi-dimensional (or empty) array
		}
		
		# Obtain the value of the form submission (which may be empty)
		#!# NB The equivalent of this line was not present before refactoring
		$widget->setValue (isSet ($this->form[$arguments['name']]) ? $this->form[$arguments['name']] : array ());
		
		$elementValue = $widget->getValue ();
		
		# Ensure that the POST method is being used, as apparently required by RFC1867 and by PHP
		if ($this->method != 'post') {
			$this->formSetupErrors['uploadsRequirePost'] = 'File uploads require the POST method to be used in the form, so either the get setting or the upload widgets should be removed.';
			return false;	// Discontinue further checks
		}
		
		# Check whether unzipping is supported
		if ($arguments['unzip'] && !extension_loaded ('zip')) {
			$this->formSetupErrors['uploadUnzipUnsupported'] = 'Unzipping of zip files upon upload was requested but the unzipping module is not available on this server.';
			$arguments['unzip'] = false;
		}
		
		# Ensure the initial value(s) is an array, even if only an empty one, converting if necessary, and lowercase (and then unique) the extensions lists, ensuring each starts with .
		$arguments['disallowedExtensions'] = application::ensureArray ($arguments['disallowedExtensions']);
		foreach ($arguments['disallowedExtensions'] as $index => $extension) {
			$arguments['disallowedExtensions'][$index] = (substr ($extension, 0, 1) != '.' ? '.' : '') . strtolower ($extension);
		}
		$arguments['disallowedExtensions'] = application::ensureArray ($arguments['disallowedExtensions']);
		$arguments['allowedExtensions'] = array_unique ($arguments['allowedExtensions']);
		foreach ($arguments['allowedExtensions'] as $index => $extension) {
			$arguments['allowedExtensions'][$index] = (substr ($extension, 0, 1) != '.' ? '.' : '') . strtolower ($extension);
		}
		$arguments['allowedExtensions'] = array_unique ($arguments['allowedExtensions']);
		
		# Ensure zip files can be uploaded if unzipping is enabled, by adding it to the list of allowed extensions if such a list is defined
		#!# Allowing zip files but having a list of allowed extensions means that people can zip up a non-allowed extension
		if ($arguments['unzip'] && $arguments['allowedExtensions'] && !in_array ('zip', $arguments['allowedExtensions'])) {
			$arguments['allowedExtensions'][] = 'zip';
		}
		
		# Determine whether a file extension must be included - this is if * is the only value for $arguments['allowedExtensions']; if so, also clear the array
		$extensionRequired = false;
		if (count ($arguments['allowedExtensions']) == 1) {
			if ($arguments['allowedExtensions'][0] == '*') {
				$extensionRequired = true;
				$arguments['allowedExtensions'] = array ();
			}
		}
		
		# Do not allow defining of both disallowed and allowed extensions at once, except for the special case of defining disallowed extensions plus requiring an extension
		if ((!empty ($arguments['disallowedExtensions'])) && (!empty ($arguments['allowedExtensions'])) && (!$extensionRequired)) {
			$this->formSetupErrors['uploadExtensionsMismatch'] = "You cannot, in the <strong>{$arguments['name']}</strong> upload element, define <em>both</em> disallowed <em>and</em> allowed extensions.";
		}
		
		# Check that the number of available subfields is a whole number and that it is at least 1 (the latter error message overrides the first if both apply, e.g. 0.5)
		if ($arguments['subfields'] != round ($arguments['subfields'])) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "You specified a non-whole number (<strong>{$arguments['subfields']}</strong>) for the number of file upload widgets in the <strong>{$arguments['name']}</strong> upload element which the form should create.";}
		if ($arguments['subfields'] < 1) {$this->formSetupErrors['uploadSubfieldsIncorrect'] = "The number of files to be uploaded must be at least one; you specified <strong>{$arguments['subfields']}</strong> for the <strong>{$arguments['name']}</strong> upload element.";}
		
		# Explicitly disable flattening if there is not a singular subfield
		if ($arguments['subfields'] != 1) {$arguments['flatten'] = false;}
		
		# Check that the minimum required is a whole number and that it is not greater than the number actually available
		if ($arguments['required'] != round ($arguments['required'])) {$this->formSetupErrors['uploadSubfieldsMinimumIncorrect'] = "You specified a non-whole number (<strong>{$arguments['required']}</strong>) for the number of file upload widgets in the <strong>{$arguments['name']}</strong> upload element which must the user must upload.";}
		if ($arguments['required'] > $arguments['subfields']) {$this->formSetupErrors['uploadSubfieldsMinimumMismatch'] = "The required minimum number of files which the user must upload (<strong>{$arguments['required']}</strong>) specified in the <strong>{$arguments['name']}</strong> upload element is above the number of files actually available to be specified for upload (<strong>{$arguments['subfields']}</strong>).";}
		
		# Check that the selected directory exists and is writable (or create it)
		if ($arguments['directory']) {
			if (!is_dir ($arguments['directory']) || !is_writeable ($arguments['directory'])) {
				if (!application::directoryIsWritable ($arguments['directory'])) {
					$this->formSetupErrors['directoryNotWritable'] = "The directory specified for the <strong>{$arguments['name']}</strong> upload element is not writable. Please check that the file permissions to ensure that the webserver 'user' can write to the directory.";
				} else {
					#!# Third parameter doesn't exist in PHP4 - will this cause a crash?
					umask (0);
					mkdir ($arguments['directory'], $this->settings['directoryPermissions'], $recursive = true);
				}
			}
		}
		
		# Check that, if MIME Type checking is wanted, and the file extension check is in place, that all are supported
		$mimeTypes = array ();
		if ($arguments['mime']) {
			if (!$arguments['allowedExtensions']) {
				$this->formSetupErrors['uploadMimeNoExtensions'] = "MIME Type checking was requested but allowedExtensions has not been set.";
				$arguments['mime'] = false;
			}
			if (!function_exists ('mime_content_type')) {
				$this->formSetupErrors['uploadMimeExtensionsMismatch'] = "MIME Type checking was requested but is not available on this server platform.";
				$arguments['mime'] = false;
			} else {
				$this->mimeTypes = application::mimeTypeExtensions ();
				if ($arguments['allowedExtensions']) {
					$inBoth = array_intersect ($arguments['allowedExtensions'], array_keys ($this->mimeTypes));
					if (count ($inBoth) != count ($arguments['allowedExtensions'])) {
						$arguments['mime'] = false;	// Disable execution of the mime block below
						$this->formSetupErrors['uploadMimeExtensionsMismatch'] = "MIME Type checking was requested for the <strong>{$arguments['name']}</strong> upload element, but not all of the allowedExtensions are supported in the MIME checking list";
					}
				}
				foreach ($arguments['allowedExtensions'] as $extension) {
					$mimeTypes[] = $this->mimeTypes[$extension];
				}
			}
		}
		
		# Prevent more files being uploaded than the number of form elements (this is not strictly necessary, however, as the subfield looping below prevents the excess being processed)
		if (count ($elementValue) > $arguments['subfields']) {
			$elementProblems['subfieldsMismatch'] = 'You appear to have submitted more files than there are fields available.';
		}
		
		# Start the widget HTML
		$widgetHtml = '';
		
		# Add progress bar support if required; currently supported for single upload only
		if ($arguments['progressbar']) {
			
			# Disallow progressbar with more than one subfield
			if ($arguments['subfields'] > 1) {
				$this->formSetupErrors['uploadProgressbarSubfields'] = 'Only one subfield is allowed in an upload widget with a progressbar.';
			}
			
			# This must be before the input field itself
			$uploadProgressIdentifier = bin2hex (random_bytes (16));
			$widgetHtml .= "\n\t\t\t" . '<input type="hidden" name="UPLOAD_IDENTIFIER" value="' . $uploadProgressIdentifier . '">';
		}
		
		# Convert to drag and drop zone if required; this merely styles the input box and does not use HTML5 Drag and Drop; see: https://codepen.io/TheLukasWeb/pen/qlGDa
		if ($arguments['draganddrop']) {
			$thumbnailText = 'Click here to pick photo, or drag and drop into this box.';
			$widgetHtml .= "
				<style type=\"text/css\">
					form tr.upload div.draganddrop {
						width: calc({$this->settings['uploadThumbnailWidth']}px + 4px + 4px);
						height: calc({$this->settings['uploadThumbnailHeight']}px + 4px + 4px);
						border: 4px dashed gray;
					}
					form tr.upload p {
						width: {$this->settings['uploadThumbnailWidth']}px;
						height: {$this->settings['uploadThumbnailHeight']}px;
						text-align: center;
						padding: 15px;
						color: gray;
					}
					form tr.upload div input {
						position: absolute;
						margin: 0;
						padding: 0;
						width: {$this->settings['uploadThumbnailWidth']}px;
						height: {$this->settings['uploadThumbnailHeight']}px;
						outline: none;
						opacity: 0;
					}
				</style>
			";
		}
		
		# If thumbnail viewing is enabled, parse the argument and assemble the HTML5/JS code
		if ($arguments['thumbnail']) {
			$thumbnailHtmlBySubfield = array ();
			
			# Enable jQuery
			#!# Actually this is currently enabling jQuery as well as jQueryUI
			$this->enableJqueryUi ();
			
			# Define the thumbnailing code; this is done once globally
			$this->jQueryCode[__FUNCTION__] = "\n" . $this->thumbWrapperJs ();
			
			# For each subfield, add a thumbnail preview, creating the div if required
			$this->jQueryCode[__FUNCTION__  . $arguments['name']] = '';	// Indexed by element to ensure that multiple upload instances do not overwrite
			for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
				$thumbnailHtml = '';
				
				# Get the widget ID
				$elementId = $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}_{$subfield}]" : "{$arguments['name']}_{$subfield}");
				
				# Assign the thumbnail ID
				$selector = $arguments['thumbnail'];
				if ($arguments['thumbnail'] === true) {
					$thumbnailDivId = $elementId . '_thumbnailpreview';
					$selector = '#' . $thumbnailDivId;
				}
				
				# Create the div if set to boolean true; otherwise the named selector that the client code has created on its page will be used
				#!# Named selector will fail if multiple subfields, as they will all be the same
				if ($arguments['thumbnail'] === true) {
					
					# Open div to contain the thumbnail
					$thumbnailHtml .= "\n\t\t\t\t<div id=\"{$thumbnailDivId}\" style=\"width: {$this->settings['uploadThumbnailWidth']}px; height: {$this->settings['uploadThumbnailHeight']}px;\">";
					
					# Determine whether there is a default image, so that this can be set below
					$createDefaultImage = ($arguments['default'] && isSet ($arguments['default'][$subfield]));
					
					# Add default image, or text
					if ($createDefaultImage) {
						$thumbnailImage = "<img src=\"{$arguments['previewLocationPrefix']}{$arguments['default'][$subfield]['name']}\" style=\"max-width: 100%; max-height: 100%;\" />";
						if ($arguments['thumbnailExpandable']) {
							$thumbnailImage = "<a href=\"{$arguments['previewLocationPrefix']}{$arguments['default'][$subfield]['name']}\" target=\"_blank\">" . $thumbnailImage . '</a>';
						}
						$thumbnailHtml .= $thumbnailImage;
					} else {
						
						# Set the thumbnail text
						if (!isSet ($thumbnailText)) {
							$thumbnailText = '(Thumbnail will appear here.)';
						}
						$thumbnailHtml .= "\n\t\t\t\t\t<p class=\"comment\">{$thumbnailText}</p>";
					}
					
					# Complete div
					$thumbnailHtml .= "\n\t\t\t\t</div>\n";
				}
				
				# Add JS handler to set the thumbnail on file selection
				$this->jQueryCode[__FUNCTION__  . $arguments['name']] .= "\n" . "
				$(document).ready (function () {
					$('#{$elementId}').change (function () {
						thumbWrapper (this.files, '{$selector}');
					});
				});
				";
				
				# Register the thumbnail HTML for this subfield
				$thumbnailHtmlBySubfield[$subfield] = $thumbnailHtml;
			}
		}
		
		
		# Loop through the number of fields required to create the widget
		if ($arguments['subfields'] > 1) {$widgetHtml .= "\n\t\t\t";}
		for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
			
			# Where default file(s) are/is expected, show - for the current subfield - the filename for each file (or that there is no file)
			if ($arguments['default']) {
				if (!$arguments['thumbnail']) {		// In thumbnail mode, default is instead shown in thumbnail box, below
					$widgetHtml .= '<p class="currentfile' . ($subfield > 0 ? ' currentfilenext' : '') . '">' . (isSet ($arguments['default'][$subfield]) ? 'Current file: <span class="filename">' . htmlspecialchars (basename ($arguments['default'][$subfield]['name'])) . '</span>' : '<span class="comment">(No current file)</span>') . "</p>\n\t\t\t";
				}
			}
			
			# Define the widget's core HTML; note that MAX_FILE_SIZE as mentioned in the PHP manual is bogus (non-standard and seemingly not supported by any browsers), so is not supported here - doing so would also require MAX_FILE_SIZE as a disallowed form name, and would expose to the user the size of the PHP ini setting
			// $widgetHtml .= '<input type="hidden" name="MAX_FILE_SIZE" value="' . application::convertSizeToBytes (ini_get ('upload_max_filesize')) . '" />';
			if ($arguments['editable']) {
				if ($arguments['draganddrop']) {
					$widgetHtml .= "\n\t\t\t" . '<div class="draganddrop">' . "\n\t\t\t\t";
				}
				$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, $subfield, true) . " type=\"file\" size=\"{$arguments['size']}\"" . (($arguments['autofocus'] && $subfield == 0) ? ' autofocus="autofocus"' : '') . $widget->tabindexHtml ($subfield) . ($mimeTypes ? ' accept="' . implode (', ', $mimeTypes) . '"' : '') . ' />';
				if ($arguments['thumbnail']) {
					$widgetHtml .= "\n" . $thumbnailHtmlBySubfield[$subfield];
				}
				if ($arguments['draganddrop']) {
					$widgetHtml .= "\n\t\t\t" . '</div>' . "\n\t\t\t";
				}
				$widgetHtml .= (($subfield != ($arguments['subfields'] - 1)) ? "<br />\n\t\t\t" : (($arguments['subfields'] == 1) ? '' : "\n\t\t"));
			} else {
				if ($arguments['default'] && isSet ($arguments['default'][$subfield])) {
					$widgetHtml .= '<input' . $this->nameIdHtml ($arguments['name'], false, $subfield, true) . ' type="hidden" value="' . htmlspecialchars (basename ($arguments['default'][$subfield]['name'])) . '" />' . "\n\t\t\t";
				}
			}
		}
		
		# Progress bar handler
		if ($arguments['progressbar']) {
			$progressbarId = $this->cleanId ($arguments['name'] . '__progressbar');
			$widgetHtml .= "\n\t\t\t<br />\n\t\t\t" . '<div id="' . $progressbarId . '"><progress max="100" value="0"></progress> <span></span></div>';
			$widgetHtml .= "\n\t\t\t<style type=\"text/css\">#{$progressbarId} {display: none;}</style>";		// Hidden by default; shown using show() below on submit
			$widgetHtml .= "\n\t\t\t";
			$this->jQueryCode[__FUNCTION__] = "\n" . "
				$(function () {		// document ready
					$('#{$progressbarId}').closest ('form').submit (function (e) {
						var updateProgressbar = function () {
							$.get ('{$arguments['progressbar']}/{$uploadProgressIdentifier}', function (data) {
								if (data != null) {
									var progress = (data.bytes_uploaded / data.bytes_total) * 100;
									progress = progress.toFixed (0);
									$('#{$progressbarId} progress').val (progress);
									$('#{$progressbarId} span').text (progress + '%');
									if (progress < 100) {
										setTimeout (updateProgressbar, 1000);	// Iterate
									}
								}
							});
						};
						$('#{$progressbarId}').show ();
						setTimeout (updateProgressbar, 1000);
					});
				});
			";
		}
		
		# Loop through the number of fields required to perform checks
		$apparentlyUploadedFiles = array ();
		if ($arguments['default']) {$apparentlyUploadedFiles = $arguments['default'];}	// add in the numerically-indexed defaults (which are then overwritten if more uploaded)
		for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
			
			# Continue further processing if the file has been uploaded
			if (isSet ($elementValue[$subfield]) && is_array ($elementValue[$subfield]) && array_key_exists ('name', $elementValue[$subfield])) {	// 'name' should always exist but it won't if a form spammer submits this as an input rather than upload
				
				# Add the apparently uploaded file (irrespective of whether it passes other checks)
				$elementValue[$subfield]['_directory'] = $arguments['directory'];	// Cache the directory for later use
				$elementValue[$subfield]['_attachmentsDeleteIfMailed'] = $arguments['attachmentsDeleteIfMailed'];
				$apparentlyUploadedFiles[$subfield] = $elementValue[$subfield];
				
				# If an extension is required but the submitted item doesn't contain a dot, throw a problem
				if (($extensionRequired) && (strpos ($elementValue[$subfield]['name'], '.') === false)) {
					$extensionsMissing[] = $elementValue[$subfield]['name'];
				} else {
					
					# If the file is not valid, add it to a list of invalid subfields
					$allowedExtensions = $arguments['allowedExtensions'];
					if (in_array ('.jpg', $allowedExtensions) && !in_array ('.jpeg', $allowedExtensions)) {$allowedExtensions[] = '.jpeg';}		// Treat .jpeg as an alias for .jpg, but avoid listing it explicitly
					if (!application::filenameIsValid ($elementValue[$subfield]['name'], $arguments['disallowedExtensions'], $allowedExtensions)) {
						$filenameInvalidSubfields[] = $elementValue[$subfield]['name'];
					}
				}
			}
		}
		
		# Append the description where default filename(s) are supplied
		if ($arguments['default'] && $arguments['editable']) {
			$arguments['description'] = 'Entering a new file will replace the current reference' . ($arguments['description'] ? ". {$arguments['description']}" : '');	// Note that the form itself does not handle file deletions (except for natural overwrites), because the 'default' is just a string coming from $data
		}
		
		# If fields which don't have a file extension have been found, throw a user error
		if (isSet ($extensionsMissing)) {
			$elementProblems['fileExtensionAbsent'] = (count ($extensionsMissing) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $extensionsMissing) . (count ($extensionsMissing) > 1 ? '</em> have' : '</em> has') . ' no file extension, but file extensions are required for files selected in this section.';
		}
		
		# If fields which have an invalid extension have been found, throw a user error
		if (isSet ($filenameInvalidSubfields)) {
			$elementProblems['fileExtensionMismatch'] = (count ($filenameInvalidSubfields) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $filenameInvalidSubfields) . (count ($filenameInvalidSubfields) > 1 ? '</em> are the wrong type of files' : '</em> is the wrong type of file') . '.';
		}
		
		# If fields which have an invalid MIME Type have been found, throw a user error
		if (isSet ($filenameInvalidMimeTypes)) {
			$elementProblems['fileMimeTypeMismatch'] = (count ($filenameInvalidMimeTypes) > 1 ? 'The files <em>' : 'The file <em>') . implode ('</em>, <em>', $filenameInvalidMimeTypes) . (count ($filenameInvalidMimeTypes) > 1 ? '</em> do not' : '</em> does not') . ' appear to be valid.';
		}
		
		# If any files have been uploaded, the user will need to re-select them.
		$totalApparentlyUploadedFiles = count ($apparentlyUploadedFiles);	// This will include the defaults, some of which might have been overwritten
		if ($totalApparentlyUploadedFiles > 0) {
			$this->elementProblems['generic']['reselectUploads'] = "You will need to reselect the " . ($totalApparentlyUploadedFiles == 1 ? 'file' : "{$totalApparentlyUploadedFiles} files") . " you selected for uploading, because of problems elsewhere in the form. (Re-selection is a security requirement of your web browser.)";
		}
		
		# Check if the field is required (i.e. the minimum number of fields is greater than 0) and, if so, run further checks
		if ($required = ($arguments['required'] > 0)) {
			
			# If none have been uploaded, class this as requiredButEmpty
			if ($totalApparentlyUploadedFiles == 0) {
				$requiredButEmpty = true;
				
			# If too few have been uploaded, produce a individualised warning message
			} else if ($totalApparentlyUploadedFiles < $arguments['required']) {
				$elementProblems['underMinimum'] = ($arguments['required'] != $arguments['subfields'] ? 'At least' : 'All') . " <strong>{$arguments['required']}</strong> " . ($arguments['required'] > 1 ? 'files' : 'file') . ' must be submitted; you will need to reselect the ' . ($totalApparentlyUploadedFiles == 1 ? 'file' : "{$totalApparentlyUploadedFiles} files") . ' that you did previously select, for security reasons.';
			}
		}
		
		# Describe a restriction on the widget for minimum number of uploads
		if ($arguments['required'] > 1) {$restrictions[] = "Minimum {$arguments['required']} items required";}
		
		# Describe extension restrictions on the widget and compile them as a semicolon-separated list
		if ($extensionRequired) {
			$restrictions[] = 'File extensions are required';
		} else {
			if (!empty ($arguments['allowedExtensions'])) {
				$restrictions[] = 'Allowed file extensions: ' . implode (',', $arguments['allowedExtensions']);
			}
		}
		if (!empty ($arguments['disallowedExtensions'])) {
			$restrictions[] = 'Disallowed file extensions: ' . implode (',', $arguments['disallowedExtensions']);
		}
		if ($arguments['unzip']) {
			$restrictions[] = 'Zip files will be automatically unzipped on arrival.';
		}
		if (isSet ($restrictions)) {$restrictions = implode (";\n", $restrictions);}
		
		# Assign half-validated data, for the purposes of the groupValidation check; note that this could be tricked, but should be good enough in most cases, and certainly better than nothing
		$data['presented'] = $totalApparentlyUploadedFiles;
		#!# This is a workaround for when using getUnfinalisedData, to prevent offsets
		$data['rawcomponents'] = $totalApparentlyUploadedFiles;
		
		# Register the attachments, and disable unzipping
		#!# Ideally unzipping should be done after a zip file is e-mailed, but this would require much refactoring of the output processing, i.e. (i) upload, (ii) attach attachments, (iii) unzip
		if ($arguments['attachments']) {
			$this->attachments = array_merge ($this->attachments, $apparentlyUploadedFiles);
			$this->uploadProperties[$arguments['name']]['unzip'] = false;
			$arguments['unzip'] = false;
		}
		
		# Re-assign back the value
		$this->form[$arguments['name']] = $elementValue;
		
		# Cache the upload properties
		$this->uploadProperties[$arguments['name']] = $arguments;
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $arguments['prepend'] . $widgetHtml . $arguments['append'],
			'title' => $arguments['title'],
			'description' => $arguments['description'],
			'restriction' => (isSet ($restrictions) ? $restrictions : false),
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => $required,
			'requiredButEmpty' => (isSet ($requiredButEmpty) ? $requiredButEmpty : false),
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'flatten' => $arguments['flatten'],
			'discard' => $arguments['discard'],
			'editable' => $arguments['editable'],
			'data' => $data,	// Because the uploading can only be processed later, this is set to NULL
			#!# Not finished
#			'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR (255)') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
			'unzip'	=> $arguments['unzip'],
			'progressbar' => $arguments['progressbar'],
			'mime' => $arguments['mime'],
			'subfields' => $arguments['subfields'],
			'default'	=> $arguments['default'],
			'after' => $arguments['after'],
		);
	}
	
	
	# Thumbnail wrapper JS
	private function thumbWrapperJs ()
	{
		# Create the JS
		$js = "
		function thumbWrapper (files, selector) {
			
			thumb (files);
			
			function thumb(files) {
				
				if (files == null || files == undefined) {
					$(selector).html( '<p><em>Unable to show a thumbnail, as this web browser is too old to support this.</em></p>' );
					return false;
				}
				
				for (var i = 0; i < files.length; i++) {
					var file = files[i];
					var imageType = /image.*/;
					
					if (!file.type.match(imageType)) {
						continue;
					}
					
					var reader = new FileReader();
					
					if (reader != null) {
						reader.onload = GetThumbnail;
						reader.readAsDataURL(file);
					}
				}
			}
			
			function GetThumbnail(e) {
				
				var thumbnailCanvas = document.createElement('canvas');
				var img = new Image();
				img.src = e.target.result;
				
				img.onload = function () {
					
					var originalImageWidth = img.width;
					var originalImageHeight = img.height;
					
					thumbnailCanvas.id = 'myTempCanvas';
					thumbnailCanvas.width  = $(selector).width();
					thumbnailCanvas.height = $(selector).height();
					
					// Scale the thumbnail to fit the box
					if (originalImageWidth >= originalImageHeight) {
						scaledWidth = Math.min(thumbnailCanvas.width, originalImageWidth);	// Ensure width is no greater than the available size
						scaleFactor = (scaledWidth / originalImageWidth);
						scaledHeight = Math.round(scaleFactor * originalImageHeight);	// Scale to same proportion, and round
					} else {
						scaledHeight = Math.min(thumbnailCanvas.height, originalImageHeight);
						scaleFactor = (scaledHeight / originalImageHeight);
						scaledWidth = Math.round(scaleFactor * originalImageWidth);
					}
					
					if (thumbnailCanvas.getContext) {
						var canvasContext = thumbnailCanvas.getContext('2d');
						canvasContext.drawImage(img, 0, 0, scaledWidth, scaledHeight);
						var dataURL = thumbnailCanvas.toDataURL();
						
						if (dataURL != null && dataURL != undefined) {
							var nImg = document.createElement('img');
							nImg.src = dataURL;
							$(selector).html(nImg);
						} else {
							$(selector).html( '<p><em>Unable to read the image.</em></p>' );
						}
					}
				}
			}
		}";
		
		# Return the JS
		return $js;
	}
	
	
	# AJAX endpoint function to provide progress upload, which calling code can use
	# See: https://github.com/php/pecl-php-uploadprogress
	# See: https://www.automatem.co.nz/blog/the-state-of-upload-progress-measurement-on-ubuntu-16-04-php7.html
	#!# This fails in Safari for some reason, giving access control errors even on the same domain
	public static function progressbar ()
	{
		# End if not supported
		if (!function_exists ('uploadprogress_get_info')) {
			echo "ERROR: The PECL uploadprogress module is not installed.";
			application::sendHeader (500);
			return false;
		}
		
		# End if ID not supplied
		if (!isSet ($_GET['id']) || !preg_match ('/^([0-9a-f]{32})$/', $_GET['id'])) {return false;}
		
		# Get the data
		$data = uploadprogress_get_info ($_GET['id']);
		
		# Send the response as JSON
		$json = json_encode ($data, JSON_PRETTY_PRINT);
		header ('Content-Type: application/json');
		echo $json;
	}
	
	
	/**
	 * Function to pass hidden data over
	 * @param array $arguments Supplied arguments - see template
	 */
	function hidden ($suppliedArguments)
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'name'			=> 'hidden',				# Name of the element (Optional)
			'values'				=> array (),		# Associative array of selectable values
			'output'				=> array (),		# Presentation format
			'title'					=> 'Hidden data',	# Title (CURRENTLY UNDOCUMENTED)
			'security'				=> true, 			# Whether to ignore posted data and use the internal values set, for security (only of relevance to non- self-processing forms); probably only switch off when using javascript to modify a value and submit that
			'discard'				=> false,	# Whether to process the input but then discard it in the results
			'datatype'				=> false,	# Datatype used for database writing emulation (or caching an actual value)
		);
		
		# Hidden elements are not editable
		$argumentDefaults['editable'] = false;
		
		# Create a new form widget
		$widget = new formWidget ($this, $suppliedArguments, $argumentDefaults, __FUNCTION__);
		
		$arguments = $widget->getArguments ();
		
		# Flag that a hidden element is present
		$this->hiddenElementPresent = true;
		
		# Check that the values array is actually an array, containing elements within it
		if (!is_array ($arguments['values']) || empty ($arguments['values'])) {$this->formSetupErrors['hiddenElementNotArray'] = "The hidden data specified for the <strong>{$arguments['name']}</strong> hidden input element must be an array of values but is not currently.";}
		
		# Create the HTML by looping through the data array; this is only of use to non- self-processing forms, i.e. where the data is sent elsewhere; for self-processing the submitted data is ignored
		$widgetHtml = "\n";
		foreach ($arguments['values'] as $key => $value) {
			$widgetHtml .= "\n\t" . '<input type="hidden"' . $this->nameIdHtml ($arguments['name'], false, $key, true) . ' value="' . $value . '" />';
		}
		$widgetHtml .= "\n";
		
		# Get the posted data
		if ($this->formPosted) {
			
/*
			#!# Removed - needs to be tested properly first
			# Throw a fake submission warning if the posted data (which is later ignored anyway) does not match the assigned data
			if ($arguments['security']) {
				if ($this->form[$arguments['name']] !== $arguments['values']) {
					$elementProblems['hiddenFakeSubmission'] = 'The hidden data which was submitted did not match that which was set. This appears to have been a faked submission.';
				}
			}
*/
			
			# Map the components onto the array directly and assign the compiled version; no attempt is made to combine the data
			$data['rawcomponents'] = ($arguments['security'] ? $arguments['values'] : $this->form[$arguments['name']]);
			
			# The presented version is just an empty string
			$data['presented'] = '';
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements[$arguments['name']] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => $arguments['title'],
			'values' => $arguments['values'],
			'description' => false,
			'restriction' => false,
			'problems' => $widget->getElementProblems (isSet ($elementProblems) ? $elementProblems : false),
			'required' => true,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => $arguments['output'],
			'discard' => $arguments['discard'],
			'editable' => false,
			'data' => (isSet ($data) ? $data : NULL),
			'after' => false,
			#!# Not finished
			#!# 'datatype' => ($arguments['datatype'] ? $arguments['datatype'] : "`{$arguments['name']}` " . 'VARCHAR (255)') . ($arguments['required'] ? ' NOT NULL' : '') . " COMMENT '" . (addslashes ($arguments['title'])) . "'",
		);
	}
	
	
	/**
	 * Function to allow text headings or paragraphs
	 * @param string $level Name of the element Level, e.g. 1 for <h1></h1>, 2 for <h2></h2>, etc., 'p' for <p></p>, or 'text' for text without any markup added
	 * @param string $title Text
	 */
	function heading ($level, $title)
	{
		# Add the headings as text
		switch ($level) {
			case '0':
			case 'p':
				$widgetHtml = "<p>{$title}</p>";
				break;
			case 'text':
			case '':
				$widgetHtml = $title;
				break;
			default:
				$widgetHtml = "<h{$level}>{$title}</h{$level}>";
				break;
		}
		
		# Add the widget to the master array for eventual processing
		$this->elements['_heading' . $this->headingTextCounter++] = array (
			'type' => __FUNCTION__,
			'html' => $widgetHtml,
			'title' => '',
			'description' => false,
			'restriction' => false,
			'problems' => false, #!# Should ideally be getElementProblems but can't create an object as no real parameters to supply
			'required' => false,
			'requiredButEmpty' => false,
			'suitableAsEmailTarget' => false,
			'output' => array (),	// The output specification must always be array
			'discard' => false,
			'data' => (isSet ($data) ? $data : NULL),
			'after' => false,
		);
	}
	
	
	# Function to generate ID and name HTML
	function nameIdHtml ($widgetName, $multiple = false, $subitem = false, $nameAppend = false, $idOnly = false, $idAppend = false)
	{
		# Create the name and ID and compile the HTML
		# http://htmlhelp.com/reference/html40/attrs.html says that "Also note that while NAME may contain entities, the ID attribute value may not."
		# Note also that the <option> tag does not support the NAME attribute
		$widgetNameCleaned = htmlspecialchars ($widgetName);
		$subitemCleaned = htmlspecialchars ($subitem);
		$name = ' name="' .              ($this->settings['name'] ? "{$this->settings['name']}[{$widgetNameCleaned}]" : $widgetName) . ($multiple ? '[]' : '') . ($nameAppend ? "[{$subitemCleaned}]" : '') . '"';
		if ($subitem !== false) {
			$widgetName .= "_{$subitem}";
			if (!strlen ($subitem)) {$widgetName .= '____NULL';}	// #!# Dirty fix - should really have a guarantee of uniqueness
		}
		$id   = ' id="' . $this->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$widgetName}]" : $widgetName) . ($idAppend ? "{$idAppend}" : '') . '"';
		$html = ($idOnly ? '' : $name) . $id;
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to clean an HTML id attribute
	function cleanId ($id)
	{
		# Replace non-allowed characters
		# http://htmlhelp.com/reference/html40/attrs.html states:
		# - "Also note that while NAME may contain entities, the ID attribute value may not."
		# - "The attribute's value must begin with a letter in the range A-Z or a-z and may be followed by letters (A-Za-z), digits (0-9), hyphens ("-"), underscores ("_"), colons (":"), and periods ("."). The value is case-sensitive."
		$id = preg_replace ('/[^-_:.a-zA-Z0-9]/','_', $id);	// The unicode semantics flag /u is NOT enabled, as this makes the function return false when a non-Unicode string is added
		
		# Ensure the first character is valid
		#!# Currently this routine doesn't ensure that the first is A-Z or a-z, though often the elements will have form_ added anyway
		
		# Chop off any trailing _
		while (substr ($id, -1) == '_') {
			$id = substr ($id, 0, -1);
		}
		
		# Return the cleaned ID
		return $id;
	}
	
	
	# Function to inject a jQuery library loading
	function addJQueryLibrary ($id, $code)
	{
		$this->jQueryLibraries[$id] = $code;
	}
	
	
	# Function to inject a jQuery code block
	function addJQueryCode ($id, $code)
	{
		$this->jQueryCode[$id] = $code;
	}
	
	
	# Function to load jQuery UI
	function enableJqueryUi ()
	{
		# Add the libraries, ensuring that the loading respects the protocol type (HTTP/HTTPS) of the current page, to avoid mixed content warnings
		# Need to keep this in sync with a compatible jQuery version
		if ($this->settings['jQueryUi']) {
			$this->jQueryLibraries['jQueryUI'] = '
				<script src="//code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
				<link href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css" rel="stylesheet" type="text/css"/>
			';
		}
	}
	
	
	# Function to add jQuery-based autocomplete; see: http://jqueryui.com/demos/autocomplete/#remote - this is the new jQueryUI plugin, not the old one; see also: http://www.learningjquery.com/2010/06/autocomplete-migration-guide
	function autocompleteJQuery ($id, $data, $options = array (), $subwidgets = false)
	{
		# Ensure that jQuery UI is loaded
		$this->enableJqueryUi ();
		
		# Encode the data, if it is an array of values rather than a URL
		if (is_array ($data)) {
			$data = json_encode ($data);	// NB These are assumed to have entities already encoded if necessary, i.e. if HTML is supplied, the browser will interpret the HTML
		} else {
			$data = "'{$data}'";
		}
		
		# Determine the options, if any
		$optionsList  = '';
		if ($options) {
			foreach ($options as $key => $value) {
				switch (true) {
					case preg_match ('/^function ?\(/', trim ($value)):
						$valueFormatted = $value;
						break;
					case is_bool ($value):
						$valueFormatted = ($value ? 'true' : 'false');
						break;
					case is_string ($value):
						$valueFormatted = "'" . (string) $value . "'";
						break;
					default:
						$valueFormatted = $value;
				}
				$optionsList .= ', ' . $key . ': ' . $valueFormatted;
			}
		}
		
		# Register a new entry (or, for a set of subwidgets, entries) to be added to the document-ready container
		if ($subwidgets) {
			for ($i = 0; $i < $subwidgets; $i++) {
				$this->autocompleteJQueryEntries[] = "$('#" . $id . '_' . $i . "').autocomplete({source: {$data}{$optionsList}});";
			}
		} else {
			$this->autocompleteJQueryEntries[] = "$('#" . $id . "').autocomplete({source: {$data}{$optionsList}});";
		}
		
		# Add/overwrite a per-widget call
		$this->jQueryCode[__FUNCTION__]  = "\n\t$(document).ready(function(){";
		$this->jQueryCode[__FUNCTION__] .= "\n\t\t" . implode ("\n\t\t", $this->autocompleteJQueryEntries);
		$this->jQueryCode[__FUNCTION__] .= "\n\t});";
	}
	
	
	# Function to add jQuery-based autocomplete; see https://github.com/chadisfaction/jQuery-Tokenizing-Autocomplete-Plugin/ which is a bugfixed fork of the loopj version
	function autocompleteTokenisedJQuery ($id, $jsonUrl, $optionsJsString = '', $singleLine = true)
	{
		# Add the main function
		$this->jQueryLibraries[__FUNCTION__] = "\n\t\t\t" . '<script type="text/javascript" src="' . ($this->settings['scripts'] ? $this->settings['scripts'] : 'https://raw.github.com/chadisfaction/jQuery-Tokenizing-Autocomplete-Plugin/master/src/') . 'jquery.tokeninput.js"></script>';
		
		# Add the stylesheet
		$uniqueFunctionId = __FUNCTION__ . ($singleLine ? '_singleline' : '_multiline');
		$this->jQueryLibraries[$uniqueFunctionId] = "\n\t\t\t" . '<link rel="stylesheet" href="' . ($this->settings['scripts'] ? $this->settings['scripts'] : 'https://raw.github.com/chadisfaction/jQuery-Tokenizing-Autocomplete-Plugin/master/styles/') . ($singleLine ? 'token-input-facebook' : 'token-input') . '.css" type="text/css" />';
		
		# Compile the options; they are listed at https://raw.github.com/chadisfaction/jQuery-Tokenizing-Autocomplete-Plugin/master/src/jquery.tokeninput.js ; note that the final item in a list must not have a comma at the end
		$functionOptions = array ();
		if ($singleLine) {
			$functionOptions[] = 'classes: {
						tokenList: "token-input-list-facebook",
						token: "token-input-token-facebook",
						tokenDelete: "token-input-delete-token-facebook",
						selectedToken: "token-input-selected-token-facebook",
						highlightedToken: "token-input-highlighted-token-facebook",
						dropdown: "token-input-dropdown-facebook",
						dropdownItem: "token-input-dropdown-item-facebook",
						dropdownItem2: "token-input-dropdown-item2-facebook",
						selectedDropdownItem: "token-input-selected-dropdown-item-facebook",
						inputToken: "token-input-input-token-facebook"
					}';
		}
		if (strlen ($optionsJsString)) {
			$functionOptions[] = $optionsJsString;
		}
		
		# Add a per-widget call
		$this->jQueryCode[__FUNCTION__ . $id] = "
			$(document).ready(function() {
				$('#" . $id . "').tokenInput('" . $jsonUrl . "', {
					" . implode (",\n\t\t\t\t\t", $functionOptions) . "
				});
			});
		";
	}
	
	
	# Function to add jQuery-based maxlength checking; see http://stackoverflow.com/questions/1588521/
	#!# Replace with HTML5 widget attributes where available
	function maxLengthJQuery ($id, $characters)
	{
		# Add the main function
		$this->jQueryCode[__FUNCTION__] = "
			function limitChars(textid, limit, infodiv)
			{
				var text = $('#'+textid).val(); 
				var textlength = text.length;
				var remaining = limit - textlength;
				if(textlength > limit) {
					$('#' + infodiv).html('You cannot write more then ' + limit + ' characters!');
					$('#'+textid).val(text.substr(0,limit));
					return false;
				} else {
					$('#' + infodiv).html('You have ' + remaining + (remaining == 1 ? ' character' : ' characters') + ' left.');
					return true;
				}
			}
		";
		
		# Add a per-widget call
		$this->jQueryCode[__FUNCTION__ . $id] = "
			$(function(){
				$('#" . $id . "').keyup(function()
				{
					limitChars('" . $id . "', " . $characters . ", '" . $id . "__info');
				})
			});
		";
	}
	
	
	# Function to enable a form with multiple submit buttons to submit the main button, not any others (such as a mid-form refresh)
	function multipleSubmitReturnHandlerJQuery ()
	{
		# Add the main function; see: http://stackoverflow.com/a/5017423/180733
		$this->multipleSubmitReturnHandlerClass = 'defaultsubmitbutton';
		$this->jQueryCode[__FUNCTION__] = "
			$(function() {
				$('form" . ($this->settings['id'] ? "#{$this->settings['id']}" : '') . " input').keypress(function (e) {
					if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
						$('input[type=submit].{$this->multipleSubmitReturnHandlerClass}').click();
						return false;
					} else {
						return true;
					}
				});
			})
		";
	}
	
	
	# Function to ensure that all initial values are in the array of values
	function ensureDefaultsAvailable ($arguments, &$warningHtml = false)
	{
		# Convert to an array (for this local function only) if not already
		if (!is_array ($arguments['default'])) {
			$arguments['default'] = application::ensureArray ($arguments['default']);
		}
		
		# Ensure values are not duplicated
		if (count ($arguments['default']) != count (array_unique ($arguments['default']))) {
			$this->formSetupErrors['defaultContainsDuplicates'] = "In the <strong>{$arguments['name']}</strong> element, the default values contain duplicates.";
		}
		
		# For an array of defaults, check through each
		foreach ($arguments['default'] as $defaultValue) {
			if (!in_array ($defaultValue, array_keys ($arguments['values']))) {
				$missingValues[] = $defaultValue;
			}
		}
		
		# Construct the warning message
		if (isSet ($missingValues)) {
			
			# Construct the message
			$totalMissingValues = count ($missingValues);
			$message = "the default " . ($totalMissingValues > 1 ? 'values ' : 'value ') . '<em>' . htmlspecialchars (implode (', ', $missingValues)) . '</em>' . ($totalMissingValues > 1 ? ' were' : ' was') . ' not found in the list of available items';
			
			# If tolerating invalid values, show a warning to the user
			if ($arguments['tolerateInvalid']) {
				$warningHtml = ucfirst ($message) . '. As such, the value for this field has been reset, but you should review it.';
			}
			
			# Flag the error to the admin
			$errorKey = 'defaultMissingFromValuesArray' . '_' . $arguments['name'];		// Name appended to avoid hiding if multiple widget throw the same error
			if ($arguments['tolerateInvalid']) {$errorKey = '_' . $errorKey;}	// Prefix with _ to indicate warning
			$this->formSetupErrors[$errorKey] = "In the <strong>{$arguments['name']}</strong> element, " . $message . ' for selection by the user.';
		}
	}
	
	
	# Function to ensure that values are associative, even if multidimensional
	#!# This function should be in the widget class but that won't work until formSetupErrors carry back to the main class
	function ensureHierarchyAssociative ($originalValues, $forceAssociative, $elementName, $valuesNamesAutomatic)
	{
		# End if no values
		if (!$originalValues) {return false;}
		
		# If requiring automatic names from a scalar array, e.g. array(option1,option2,option3,option4), create these
		if ($valuesNamesAutomatic) {
			if (!application::isAssociativeArray ($originalValues)) {
				$newValues = array ();
				foreach ($originalValues as $value) {
					$newValues[$value] = application::unCamelCase ($value);
				}
				$originalValues = $newValues;
			}
		}
		
		# Convert the values, at any hierarchical level, to being associative
		if (!$values = application::ensureValuesArrangedAssociatively ($originalValues, $forceAssociative)) {
			$this->formSetupErrors['hierarchyTooDeep'] = "Multidimensionality is supported only to one level deep, but more levels than this were found in the <strong>$elementName</strong> element.";
			return $originalValues;
		}
		
		# Create a list of keys to ensure there are no duplicated keys
		$keys = array ();
		foreach ($values as $key => $value) {
			$keys = array_merge ($keys, (is_array ($value) ? array_keys ($value) : array ($key)));
		}
		if (count ($keys) != count (array_unique ($keys))) {
			$this->formSetupErrors['multidimensionalKeyClash'] = "Some of the multidimensional keys in the <strong>$elementName</strong> element clash with other keys elsewhere in the hierarchy. Fix this by changing the key names, possibly by switching on forceAssociative.";
			return $originalValues;
		}
		
		# Return the arranged values
		return $values;
	}
	
	
	# Function to determine whether an array of values for a select form is suitable as an e-mail target
	function _suitableAsEmailTarget ($values, $arguments)
	{
		# If it's not a required field, it's not suitable
		if (!$arguments['required']) {return 'the field is not set as a required field';}
		
		# If it's multiple and more than one is required, it's not suitable
		if ($arguments['multiple'] && ($arguments['required'] > 1)) {return 'the field allows multiple values to be selected';}
		
		# If it's set as uneditable but there is not exactly one default, it's not suitable
		if (!$arguments['editable'] && count ($arguments['default']) !== 1) {return 'the field is set as uneditable but a single default value has not been supplied';}
		
		# Return true if all e-mails are valid
		if (application::validEmail ($values)) {return true;}
		
		# If any are prefixes which when suffixed would not be valid as an e-mail, then flag this
		foreach ($values as $value) {
			if (!application::validEmail ($value . '@example.com')) {
				return 'not all values available would expand to a valid e-mail address';
			}
		}
		
		# Otherwise return a special keyword that a suffix would be required
		return '_suffixRequired';
	}
	
	
	/**
	 * Function to merge the multi-part posting of files to the main form array, effectively emulating the structure of a standard form input type
	 * @access private
	 */
	function mergeFilesIntoPost ()
	{
		# In _GET mode, do nothing
		if ($this->method == 'get') {return;}
		
		# PHP's _FILES array is (stupidly) arranged differently depending on whether you are using 'formname[elementname]' or just 'elementname' as the element name - see "HTML array feature" note at www.php.net/features.file-upload
		if ($this->settings['name']) {	// i.e. <input name="formname[widgetname]"
			
			# End if no files
			if (empty ($_FILES[$this->settings['name']])) {return;}
			
			# Loop through each upload widget set which has been submitted (even if empty)
			foreach ($_FILES[$this->settings['name']]['name'] as $widgetName => $subElements) {	// 'name' is used but type/tmp_name/error/size could also have been used
				
				# Loop through each upload widget set's subelements (e.g. 4 items if there are 4 input tags within the widget set)
				foreach ($subElements as $elementIndex => $value) {
					
					# Map the file information into the main form element array
					if (!empty ($value)) {
						$this->collection[$this->settings['name']][$widgetName][$elementIndex] = array (
							'name' => $_FILES[$this->settings['name']]['name'][$widgetName][$elementIndex],
							'type' => $_FILES[$this->settings['name']]['type'][$widgetName][$elementIndex],
							'tmp_name' => $_FILES[$this->settings['name']]['tmp_name'][$widgetName][$elementIndex],
							#'error' => $_FILES[$this->settings['name']]['error'][$widgetName][$elementIndex],
							'size' => $_FILES[$this->settings['name']]['size'][$widgetName][$elementIndex],
						);
					}
				}
			}
		} else {	// i.e. <input name="widgetname"
			
			# End if no files
			if (empty ($_FILES)) {return;}
			
			# Loop through each upload widget set which has been submitted (even if empty); note that _FILES is arranged differently depending on whether you are using 'formname[elementname]' or just 'elementname' as the element name - see "HTML array feature" note at www.php.net/features.file-upload
			foreach ($_FILES as $widgetName => $aspects) {
				
				# Loop through each sub element
				foreach ($aspects['name'] as $elementIndex => $value) {
					
					# Map the file information into the main form element array
					if (!empty ($value)) {
						$this->collection[$widgetName][$elementIndex] = array (
							'name' => $_FILES[$widgetName]['name'][$elementIndex],
							'type' => $_FILES[$widgetName]['type'][$elementIndex],
							'tmp_name' => $_FILES[$widgetName]['tmp_name'][$elementIndex],
							#'error' => $_FILES[$widgetName]['error'][$elementIndex],
							'size' => $_FILES[$widgetName]['size'][$elementIndex],
						);
					}
				}
			}
		}
	}
	
	
	## Helper functions ##
	
	
	/**
	 * Wrapper function to dump data to the screen
	 * @access public
	 */
	function dumpData ($data)
	{
		return application::dumpData ($data);
	}
	
	
	/**
	 * Function to show debugging information (configured form elements and submitted form elements) if required
	 * @access private
	 */
	function showDebuggingInformation ()
	{
		# Start the debugging HTML
		$html  = "\n\n" . '<div class="debug">';
		$html .= "\n\n<h2>Debugging information</h2>";
		$html .= "\n\n<ul>";
		$html .= "\n\n\t" . '<li><a href="#configured">Configured form elements - $this->elements</a></li>';
		if ($this->formPosted) {$html .= "\n\n\t" . '<li><a href="#submitted">Submitted form elements - $this->form</a></li>';}
		$html .= "\n\n\t" . '<li><a href="#remainder">Any form setup errors; then: Remainder of form</a></li>';
		$html .= "\n\n</ul>";
		
		# Show configured form elements
		$html .= "\n\n" . '<h3 id="configured">Configured form elements - $this->elements :</h3>';
		$html .= $this->dumpData ($this->elements, false, true);
		
		# Show submitted form elements, if the form has been submitted
		if ($this->formPosted) {
			$html .= "\n\n" . '<h3 id="submitted">Submitted form elements - $this->form :</h3>';
			$html .= $this->dumpData ($this->form, false, true);
		}
		
		# End the debugging HTML
		$html .= "\n\n" . '<a name="remainder"></a>';
		$html .= "\n</div>";
		
		# Add the HTML to the master array
		$this->html .= $html;
	}
	
	
	## Deal with form output ##
	
	/**
	 * Output the result as an e-mail
	 */
	#!# Needs ability to reply-to directory, rather than via a field
	function setOutputEmail ($recipient, $administrator = '', $subjectTitle = 'Form submission results', $chosenElementSuffix = NULL, $replyToField = NULL, $displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['email'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultEmailShowUnsubmitted = $displayUnsubmitted;
		
		# If the recipient is an array, split it into a recipient as the first and cc: as the remainder
		if (is_array ($recipient)) {
			$recipientList = $recipient;
			$recipient = array_shift ($recipientList);
			$this->configureResultEmailCc = $recipientList;
		}
		
		# Assign the e-mail recipient
		$this->configureResultEmailRecipient = $this->_setRecipient ($recipient, $chosenElementSuffix);
		
		# Assign the administrator by default to $administrator; if none is specified, use the SERVER_ADMIN, otherwise use the supplied administrator if that is a valid e-mail address
		$this->configureResultEmailAdministrator = $this->_setAdministrator ($administrator);
		
		# Set the reply-to field if applicable
		$this->configureResultEmailReplyTo = $this->_setReplyTo ($replyToField);
		
		# Assign the subject title, replacing a match for {fieldname} with the contents of the fieldname
		$this->configureResultEmailedSubjectTitle['email'] = $this->_setTitle ($subjectTitle);
		
		#!# This cleaning routine is not a great fix but at least helps avoid ugly e-mail subject lines for now
		//$this->configureResultEmailedSubjectTitle['email'] = html_entity_decode (application::htmlentitiesNumericUnicode ($this->configureResultEmailedSubjectTitle['email']), ENT_COMPAT, 'UTF-8');
	}
	
	
	# Helper function to set the title
	function _setTitle ($title)
	{
		# Assign the subject title, replacing a match for {fieldname} with the contents of the fieldname, which must be an 'input' widget type
		if ($this->formPosted) {	// This only needs to be run when the form is posted; otherwise the replacement by output type will give an offset as there will be no output type when initially showing the form
			if (preg_match_all ('/\{([^\}]+)\}/', $title, $matches)) {
				
				#!# Add more when tested
				$supportedWidgetTypes = array ('input', 'email', 'url', 'tel', 'search', 'number', 'range', 'color', 'select', 'radiobuttons');
				foreach ($matches[1] as $element) {
					
					# Extract any output format specifier
					$placeholder = $element;	// Cache this, as $element may get overwritten
					$outputFormat = 'presented';
					if (substr_count ($element, '|')) {
						list ($element, $outputFormat) = explode ('|', $element, 2);
					}
					
					# Replace this element placeholder in the string
					if (isSet ($this->elements[$element]) && (in_array ($this->elements[$element]['type'], $supportedWidgetTypes))) {
						$title = str_replace ('{' . $placeholder . '}', $this->elements[$element]['data'][$outputFormat], $title);
					}
				}
			}
		}
		
		# Return the title
		return $title;
	}
	
	
	# Helper function called by setOutputEmail to set the recipient
	function _setRecipient ($recipient, $chosenElementSuffix)
	{
		# If the recipient is a valid e-mail address then use that; if not, it should be a field name
		if (application::validEmail ($recipient)) {
			return $recipient;
		}
		
		# If the recipient is supposed to be a form field, check that field exists
		if (!isSet ($this->elements[$recipient])) {
			$this->formSetupErrors['setOutputEmailElementNonexistent'] = "The chosen field (<strong>$recipient</strong>) (which has been specified as an alternative to a valid e-mail address) for the recipient's e-mail does not exist.";
			return false;
		}
		
		# If the field type is not suitable as an e-mail target, throw a setup error
		if (!$this->elements[$recipient]['suitableAsEmailTarget']) {
			$this->formSetupErrors['setOutputEmailElementInvalid'] = "The chosen field (<strong>$recipient</strong>) is not a valid field from which the recipient of the result-containing e-mail can be taken.";
			return false;
		}
		
		# If it is exactly suitable, it's now fine; if not there are requirements which must be fulfilled
		if ($this->elements[$recipient]['suitableAsEmailTarget'] === true) {
			return $recipient;
		}
		
		# If, the element suffix is not valid, then disallow
		if ($this->elements[$recipient]['suitableAsEmailTarget'] === '_suffixRequired') {
			
			# No suffix has been supplied
			if (!$chosenElementSuffix) {
				$this->formSetupErrors['setOutputEmailElementSuffixMissing'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail must have a suffix supplied within the e-mail output specification.";
				return false;
			}
			
			# If a suffix has been supplied, ensure that it will make a valid e-mail address
			if (!application::validEmail ($chosenElementSuffix, true)) {
				$this->formSetupErrors['setOutputEmailElementSuffixInvalid'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail requires a valid @domain suffix.";
				return false;
			}
			
			# As the suffix is confirmed requried and valid, assign the recipient suffix
			$this->configureResultEmailRecipientSuffix = $chosenElementSuffix;
			return $recipient;
		}
		
		# There is therefore some particular configuration that prevents it being so, so explain what this is
		if ($this->elements[$recipient]['suitableAsEmailTarget']) {
			$this->formSetupErrors['setOutputEmailElementWidgetSuffixInvalid'] = "The chosen field (<strong>$recipient</strong>) for the receipient of the result-containing e-mail could not be used because {$this->elements[$recipient]['suitableAsEmailTarget']}.";
			return false;
		}
	}
	
	
	# Helper function called by setOutputEmail to set the administrator
	function _setAdministrator ($administrator)
	{
		# Return the server admin if no administrator supplied
		if (!$administrator) {
			return $_SERVER['SERVER_ADMIN'];
		}
		
		# Add support for "Visible name <name@email>" rather than just "name@email", by extracting the e-mail section itself and caching the supplied string
		$suppliedValue = $administrator;
		if (preg_match ('/^(.+)<([^>]+)>$/', $administrator, $matches)) {
			$administrator = $matches[2];
		}
		
		# If an address is supplied, confirm it's valid
		if (application::validEmail ($administrator)) {
			return $suppliedValue;
		}
		
		# If the non-validated address includes an @ but is not a valid address, state this as an error
		if (strpos ($administrator, '@') !== false) {
			$this->formSetupErrors['setOutputEmailReceipientEmailSyntaxInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) contains an @ symbol but is not a valid e-mail address.";
			return false;
		}
		
		# Given that a field name has thus been supplied, check it exists
		if (!isSet ($this->elements[$administrator])) {
			$this->formSetupErrors['setOutputEmailReceipientInvalid'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is a non-existent field name.";
			return false;
		}
		
		# Check it's a valid type to use
		if ($this->elements[$administrator]['type'] != 'email') {
			$this->formSetupErrors['setOutputEmailReceipientInvalidType'] = "The chosen e-mail sender address (<strong>$administrator</strong>) is not an e-mail type field name.";
			return false;
		}
		
		# Otherwise return what was supplied
		return $suppliedValue;
	}
	
	
	# Helper function called by setOutputEmail to set the reply-to field
	function _setReplyTo ($replyToField)
	{
		# Return if not set
		if (!$replyToField) {
			return false;
		}
		
		# If a field is set but it does not exist, throw an error and null the supplied argument
		if (!isSet ($this->elements[$replyToField])) {
			$this->formSetupErrors['setOutputEmailReplyToFieldInvalid'] = "The chosen e-mail reply-to address (<strong>{$replyToField}</strong>) is a non-existent field name.";
			return NULL;
		}
		
		# If it's not an e-mail or input type, disallow use as the field and null the supplied argument
		if (($this->elements[$replyToField]['type'] != 'email') && ($this->elements[$replyToField]['type'] != 'input')) {
			$this->formSetupErrors['setOutputEmailReplyToFieldInvalidType'] = "The chosen e-mail reply-to address (<strong>{$replyToField}</strong>) is not an e-mail/input type field name.";
			return NULL;
		}
		
		# Return the result
		return $replyToField;
	}
	
	
	/**
	 * Output a confirmation of the submitted results to the submitter
	 */
	function setOutputConfirmationEmail ($chosenelementName, $administrator = '', $subjectTitle = 'Form submission results', $includeAbuseNotice = true, $displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['confirmationEmail'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultConfirmationEmailShowUnsubmitted = $displayUnsubmitted;
		
		# Throw a setup error if the element name for the chosen e-mail field doesn't exist or it is not an e-mail type
		#!# Allow text-field types to be used if a hostname part is specified, or similar
		if (!isSet ($this->elements[$chosenelementName])) {
			$this->formSetupErrors['setOutputConfirmationEmailElementNonexistent'] = "The chosen field (<strong>$chosenelementName</strong>) for the submitter's confirmation e-mail does not exist.";
		} else {
			if ($this->elements[$chosenelementName]['type'] != 'email') {
				$this->formSetupErrors['setOutputConfirmationEmailTypeMismatch'] = "The chosen field (<strong>$chosenelementName</strong>) for the submitter's confirmation e-mail is not an e-mail field type.";
			} else {
				
				# If the form has been posted and the relevant element is assigned, assign the recipient (i.e. the submitter's) e-mail address (which is validated by this point)
				if ($this->formPosted) {
					#!# As noted later on, this really must be replaced with a formSetupErrors call here
					if (!empty ($this->form[$chosenelementName])) {
						$this->configureResultConfirmationEmailRecipient = $this->form[$chosenelementName];
					}
				}
			}
		}
		
		# Assign whether to include an abuse report notice
		$this->configureResultConfirmationEmailAbuseNotice = $includeAbuseNotice;
		
		# Assign the administrator e-mail address
		$this->configureResultConfirmationEmailAdministrator = ($administrator != '' ? $administrator : $_SERVER['SERVER_ADMIN']);
		
		# Assign the subject title, replacing a match for {fieldname} with the contents of the fieldname
		$this->configureResultEmailedSubjectTitle['confirmationEmail'] = $this->_setTitle ($subjectTitle);
	}
	
	
	/**
	 * Output the results to a CSV file
	 */
	function setOutputFile ($filename)
	{
		# Flag that this method is required
		$this->outputMethods['file'] = true;
		
		#!# Need to add a timestamp-writing option
		# If the file does not exist, check that its directory is writable
		if (!file_exists ($filename)) {
			$directory = dirname ($filename);
			if (!application::directoryIsWritable ($directory)) {
				$this->formSetupErrors['resultsFileNotCreatable'] = 'The specified results file cannot be created; please check the permissions for the containing directory.';
			}
			
		# If the file exists, check it is writable
		} else if (!is_writable ($filename)) {
			$this->formSetupErrors['resultsFileNotWritable'] = 'The specified (but already existing) results file is not writable; please check its permissions.';
		}
		
		# Assign the file location
		$this->configureResultFileFilename = $filename;
	}
	
	
	/**
	 * Output (display) the results to a database
	 */
	function setOutputDatabase ($dsn, $table = false)
	{
		# Flag that this method is required
		#!# Change to ->registerOutputMethod ($type) which then does the following line
		$this->outputMethods['database'] = true;
		
		# Set the DSN and table name
		$this->configureResultDatabaseDsn = $dsn;
		$this->configureResultDatabaseTable = ($table ? $table : $this->settings['name']);
	}
	
	
	
	/**
	 * Output (display) the results on screen
	 */
	function setOutputScreen ($displayUnsubmitted = true)
	{
		# Flag that this method is required
		$this->outputMethods['screen'] = true;
		
		# Flag whether to display as empty (rather than absent) those widgets which are optional and have had nothing submitted
		$this->configureResultScreenShowUnsubmitted = $displayUnsubmitted;
	}
	
	
	# Function to return the specification
	#!# This needs to exclude proxied widgets, e.g. password confirmation
	function getSpecification ()
	{
		# Return the elements array
		return $this->elements;
	}
	
	
	# Function to get database column specifications
	function getDatabaseColumnSpecification ($table = false)
	{
		# Loop through the elements and extract the specification
		$columns = array ();
		foreach ($this->elements as $name => $attributes) {
			if (isSet ($attributes['datatype'])) {
				$columns[$name] = $attributes['datatype'];
			}
		}
		
		# Return the result, with the key names in tact
		return $columns;
		
		/*
		# Create the SQL string
		$query = implode (",\n", $columns);
		
		# Add the table specification if necessary
		if ($table) {$query = "CREATE TABLE IF NOT EXISTS {$table} (" . "\n" . $query . "\n)";}
		
		# Return the assembled query
		return $query;
		*/
	}
	
	
	# Function to add built-in hidden security fields
	#!# This and hiddenSecurityFieldSubmissionInvalid () should be refactored into a small class
	function addHiddenSecurityFields ()
	{
		# Firstly (since username may be in use as a key) create a hidden username if required and a username is supplied
		$userCheckInUse = ($this->settings['user'] && $this->settings['userKey']);
		if ($userCheckInUse) {
			$securityFields['user'] = $this->settings['user'];
		}
		
		# Create a hidden timestamp if necessary
		if ($this->settings['timestamping']) {
			$securityFields['timestamp'] = $this->timestamp;
		}
		
		# Create a hidden IP field if necessary
		if ($this->settings['ipLogging']) {
			$securityFields['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		
		# Make an internal call to the external interface
		#!# Add security-verifications as a reserved word
		if (isSet ($securityFields)) {
			$this->hidden (array (
			    'name'	=> 'security-verifications',
				'values'	=> $securityFields,
			));
		}
	}
	
	
	# Add in hidden anti-spam field if required; see: http://stackoverflow.com/questions/2387496/how-to-prevent-robots-from-automatically-filling-up-a-form
	private function addAntiSpamHoneyPot ()
	{
		# End if not required
		if (!$this->settings['antispam']) {return;}
		
		# Add the honeypot field; this is hidden with CSS but marked (for screen-readers) as not for changing; if a value is added, then it can be inferred that it is a non-human submission
		$this->input (array (
			'name'			=> '__token',
			'title'			=> 'Our ref',
			'editable'		=> true,	// Must be editable
			'regexp'		=> '^$',	// Require empty
			'discard'		=> true,	// Throw away in result
			'append'		=> ' Please leave blank - anti-spam measure',
			'antispamWait'	=> 5,
		));
		
		# Add timestamp checking
		$fieldname = '__timestamp';
		$now = time ();
		$this->hidden (array (
			'name'			=> $fieldname,
			'values'		=> array ('time' => $now),
			'discard'		=> true,
			'security'		=> false,
		));
		$secondsMinimum = 4;
		if ($unfinalisedData = $this->getUnfinalisedData ()) {
			if (isSet ($unfinalisedData[$fieldname]['time'])) {
				$timestamp = $unfinalisedData[$fieldname]['time'];
				$regexp = '^([0-9]{' . strlen ($now) . '})$';
				if (preg_match ('/' . $regexp . '/', $timestamp, $matches)) {
					$turnAroundTime = $now - $timestamp;
					if ($turnAroundTime <= $secondsMinimum) {
						$this->registerProblem ('tooquick', 'Please repost again in a few seconds.');
						$this->antispamWait += 3;
					}
				}
			}
		}
		
		# In template mode, add the auto-generated fields
		if ($this->settings['display'] == 'template') {
			$this->settings['displayTemplate'] .= "\n\t<label class=\"__token\">{__token}</label>";
		}
		
		# Register CSS to hide the HTML
		$this->html .= "\n" . '<style type="text/css">form .__token {display: none;}</style>';
	}
	
	
	# Function to perform Akismet anti-spam checking
	private function akismetChecking ()
	{
		# End if not required
		if (!$this->settings['antispam']) {return NULL;}
		
		# End if no API key specified
		if (!$this->settings['akismetApiKey']) {return NULL;}
		
		# Set the user agent, as requested at https://akismet.com/development/api/#detailed-docs
		$userAgent = ($this->settings['applicationName'] ? $this->settings['applicationName'] . ' ' : '') . 'ultimateForm/' . $this->version;
		
		# Validate API key
		$siteUrl = $_SERVER['_SITE_URL'] . '/';
		$postData = array (
			'key'	=> $this->settings['akismetApiKey'],
			'blog'	=> $siteUrl,
		);
		$output = application::file_post_contents ('https://rest.akismet.com/1.1/verify-key', $postData, false, $error, $userAgent);
		if ($output != 'valid') {
			$this->formSetupErrors['akismetKeyInvalid'] = 'The antispam checking API key is not valid.';
			return NULL;
		}
		
		# End if form not posted
		if (!$this->formPosted) {return NULL;}
		
		# Assemble submission for testing; see: https://akismet.com/development/api/#comment-check
		$postData = array (
			'blog'					=> $siteUrl,
			'user_ip'				=> $_SERVER['REMOTE_ADDR'],
			'user_agent'			=> $_SERVER['HTTP_USER_AGENT'],
			'referrer'				=> $_SERVER['HTTP_REFERER'],
			'permalink'				=> $_SERVER['_PAGE_URL'],
			'comment_type'			=> 'contact-form',		// http://blog.akismet.com/2012/06/19/pro-tip-tell-us-your-comment_type/
			'blog_lang'				=> 'en',
			'blog_charset'			=> 'UTF-8',
		);
		
		
		# Determine comment content value, by concatenating all textarea values, ending if none
		$postData['comment_content'] = '';
		foreach ($this->elements as $field => $elementAttributes) {
			if ($elementAttributes['type'] == 'textarea') {
				$postData['comment_content'] .= $elementAttributes['data']['presented'];
			}
		}
		if (!strlen ($postData['comment_content'])) {return NULL;}
		
		# Determine author e-mail value, by looking for a first e-mail field
		foreach ($this->elements as $field => $elementAttributes) {
			if ($elementAttributes['type'] == 'email') {
				if (strlen ($elementAttributes['data']['presented'])) {
					$postData['comment_author_email'] = $elementAttributes['data']['presented'];
					break;
				}
			}
		}
		
		# Determine author name value, by looking for a field called name
		#!# Fieldname should be configurable
		foreach ($this->elements as $field => $elementAttributes) {
			if ($field == 'name') {
				if (strlen ($elementAttributes['data']['presented'])) {
					$postData['comment_author'] = $elementAttributes['data']['presented'];		// Send official value 'viagra-test-123' to force true result
					break;
				}
			}
		}
		
		# Determine URL value, by looking for a first URL field
		foreach ($this->elements as $field => $elementAttributes) {
			if ($elementAttributes['type'] == 'url') {
				if (strlen ($elementAttributes['data']['presented'])) {
					$postData['comment_author_url'] = $elementAttributes['data']['presented'];
					break;
				}
			}
		}
		
		# Submit for testing
		$output = application::file_post_contents ("https://{$this->settings['akismetApiKey']}.rest.akismet.com/1.1/comment-check", $postData, false, $error, $userAgent);
		$isSpam = ($output == 'true');
		
		# If spam, register problem
		if ($isSpam) {
			$this->registerProblem ('apparentlyspam', 'Your message was detected as possible spam. If this is not the case, please accept our apologies and contact us directly.');
			$this->antispamWait += 3;
		}
		
		# Return whether it is spam
		return $isSpam;
	}
	
	
	# Function to validate built-in hidden security fields
	function hiddenSecurityFieldSubmissionInvalid ()
	{
		# End checking if the form is not posted or there is no username
		if (!$this->formPosted || !$this->settings['user'] || !$this->settings['userKey']) {return false;}
		
		# Check for faked submissions
		if ($this->form['security-verifications']['user'] != $this->settings['user']) {
			$this->elementProblems = "\n" . '<p class="warning">The username which was silently submitted (' . $this->form['security-verifications']['user'] . ') does not match the username you previously logged in as (' . $this->settings['user'] . '). This has been reported as potential abuse and will be investigated.</p>';
			error_log ("A potentially fake submission has been made by {$this->settings['user']}, claiming to be {$this->form['security-verifications']['user']}. Please investigate.");
			#!# Should this really force ending of further checks?
			return true;
		}
		
		# If user uniqueness check is required, check that the user has not already made a submission
		if ($this->settings['loggedUserUnique']) {
			$csvData = application::getCsvData ($this->configureResultFileFilename);
			/* #!# Can't enable this section until application::getCsvData recognises the difference between an empty file and an unopenable/missing file
			if (!$csvData) {
				$this->formSetupErrors['csvInaccessible'] = 'It was not possible to make a check for repeat submissions as the data source could not be opened.';
				return true;
			} */
			if (array_key_exists ($this->settings['user'], $csvData)) {
				$this->html .= "\n" . '<p class="warning">You appear to have already made a submission. If you believe this is not the case, please contact the webmaster to resolve the situation.</p>';
				return true;
			}
		}
		
		# Otherwise return false (i.e. that there were no problems)
		return false;
	}
	
	
	/* Result viewing */
	
	# Function to assemble results into a chart
	function resultViewer ($suppliedArguments = array ())
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'ignoreFields' => array (),
			'ignoreHidden' => true,
			'showHeadings' => true,
			'heading' => 'h2',
			'anchors' => true,
			'tableClass' => 'lines',
			'tableChartClass' => 'lines surveyresultschart',
			'ulClass' => 'small compact',
			'ulIgnoreEmpty' => true,
			'showZeroNulls' => true,
			'showTableHeadings' => false,
			'showPercentages' => true,
			'piecharts' => true,
			'piechartStub' => '/images/piechart',
			'piechartWidth' => 250,
			'piechartHeight' => 200,
			'piechartDiv'	 => false,
			'chartPercentagePrecision' => 1,	// Number of decimal places to show for percentages in result charts
		);
		
		# Merge the arguments
		$arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, 'resultViewer');
		foreach ($arguments as $key => $value) {
			$$key = $value;
		}
		
		# Get the results (database storage; preferred to CSV if both in use)
		$dataSource = NULL;
		if ($this->dataBinding) {
			$data = $this->databaseConnection->select ($this->dataBinding['database'], $this->dataBinding['table']);
			$dataSource = 'database';
			
		# Get the results (CSV storage)
		} elseif ($this->configureResultFileFilename) {
			$data = application::getCsvData ($this->configureResultFileFilename, false, false, $keyAsFirstRow = true);
			$dataSource = 'csv';
			
		# End if no data source found
		} else {
			return $html  = "\n<p>No data source could be found.</p>";
		}
		
		# End if no data is available
		if (!$data) {
			return $html  = "\n<p>No submissions have so far been made.</p>";
		}
		
		# Ensure ignore fields is an array if supplied as a string (rather than a boolean or an array)
		if (is_string ($ignoreFields)) {$ignoreFields = application::ensureArray ($ignoreFields);}
		
		# Loop through the data and reverse the table direction (i.e. convert from per-row data to per-column data)
		$rawData = array ();
		foreach ($data as $submissionKey => $record) {
			foreach ($record as $key => $value) {
				$rawData[$key][$submissionKey] = $value;
			}
		}
		
		# If any elements end up with values split into different fields, adjust the results for that field into a hierarchy
		$fields = array ();
		$missingFields = array ();
		$results = array ();
		$nestedFields = array ();
		foreach ($this->elements as $field => $elementAttributes) {
			
			# Skip discarded fields
			if ($elementAttributes['discard']) {continue;}
			
			# Skip headings
			if ($elementAttributes['type'] == 'heading') {continue;}
			
			# Skip hidden fields if required
			if ($ignoreHidden && ($elementAttributes['type'] == 'hidden')) {continue;}
			
			# Checkbox fields require special handling
			if (($elementAttributes['type'] == 'checkboxes')) {
				if ($elementAttributes['values'] && is_array ($elementAttributes['values'])) {
					
					# Checkboxes stored as individual headings in a CSV
					foreach ($elementAttributes['values'] as $value => $visible) {
						$keyNameNestedType = "{$field}: {$value}";	// Emulation of $nestParent handling in application::arrayToCsv ()
						if (isSet ($rawData[$keyNameNestedType])) {
							$results[$field][$value] = $rawData[$keyNameNestedType];
							$nestedFields[$field] = true;
						}
					}
					
					# Otherwise deal with compiled, comma-separated SET lists, by looping through each selection list and break it down into values, to create a tally of the selected values, using the same data structure as above
					if (!isSet ($nestedFields[$field])) {
						foreach ($rawData[$field] as $index => $selectionGroupString) {
							$selections = explode (',', $selectionGroupString);	// Note that SET values cannot contain a comma so this is entirely safe
							foreach ($elementAttributes['values'] as $value => $visible) {
								$results[$field][$value][$index] = (in_array ($value, $selections) ? 1 : NULL);
							}
						}
					}
					
					# Move to the next field
					continue;
				}
			}
			
			# Skip if the field does not exist in the raw data
			if (!isSet ($rawData[$field])) {
				$missingFields[] = $field;
				continue;
			}
			
			# Add the raw data into the results as a normal field
			$results[$field] = $rawData[$field];
		}
		
		# Loop through the fields to compile their records into data
		$output = array ();
		$unknownValues = array ();
		$noResponse = '<span class="comment"><em>[No response]</em></span>';
		foreach ($this->elements as $field => $attributes) {
			
			# Show headings if necessary
			if (($attributes['type'] == 'heading') && $showHeadings) {
				$output[$field]['results'] = $attributes['html'];
				continue;
			}
			
			# Skip if the data is not in the results
			if (!isSet ($results[$field])) {continue;}
			
			# Skip this field if not required
			if ($ignoreFields) {
				if (is_array ($ignoreFields) && in_array ($field, $ignoreFields)) {continue;}
			}
			
			# Get the responses for this field
			$responses = $results[$field];
			
			# Create the heading
			$output[$field]['heading'] = (isSet ($this->elements[$field]['title']) ? $this->elements[$field]['title'] : '[No heading]');
			
			# State if no responses have been found for this field
			if (!$responses) {
				$output[$field]['results'] = "\n<p>No submissions for this question have so far been made.</p>";
				continue;
			}
			
			# Determine if this field is a chart type or a table chart type
			$isPieChartType = (isSet ($this->elements[$field]) && (($this->elements[$field]['type'] == 'radiobuttons') || ($this->elements[$field]['type'] == 'select')));
			$isTableChartType = ($attributes['type'] == 'checkboxes');
			
			# Render the chart types
			if ($isPieChartType) {
				
				# Count the number of instances of each responses; the NULL check is a workaround to avoid the "Can only count STRING and INTEGER values!" error from array_count_values
				#!# Other checks needed for e.g. BINARY values?
				foreach ($responses as $key => $value) {
					if (is_null ($value)) {
						$responses[$key] = '';
					}
				}
				$instances = array_count_values ($responses);
				
				# Determine the total responses
				$totalResponses = count ($responses);
				
				# If there are empty responses add a null response at the end of the values list
				$nullAvailable = false;
				if (!$this->elements[$field]['required']) {
					$nullAvailable = true;
					$this->elements[$field]['values'][''] = $noResponse;
				}
				
				# Check for values in the submissions that are not in the available values and compile a list of these
				if ($differences = array_diff (array_keys ($instances), array_keys ($this->elements[$field]['values']))) {
					foreach ($differences as $key => $value) {
						$unknownValues[] = "{$value} [in {$field}]";
					}
				}
				
				# Compile the table of responses
				$table = array ();
				$respondents = array ();
				$percentages = array ();
				foreach ($this->elements[$field]['values'] as $value => $visible) {
					
					# Determine the numeric number of respondents for this value
					$respondents[$value] = (array_key_exists ($value, $instances) ? $instances[$value] : 0);
					
					# If required, don't add the nulls to the  results table if there have been zero null instances
					if (!$showZeroNulls) {
						if ($nullAvailable && ($value == '') && !$respondents[$value]) {
							continue;
						}
					}
					
					# Create the main columns
					#!# This solution is a little bit hacky
					$table[$value][''] = ($visible == $noResponse ? $visible : htmlspecialchars ($visible));	// Heading would be 'Response'
					$table[$value]['Respondents'] = $respondents[$value];
					
					# Show percentages if required
					$percentages[$value] = round ((($respondents[$value] / $totalResponses) * 100), $chartPercentagePrecision);
					if ($showPercentages) {
						$table[$value]['Percentage'] = ($totalResponses ? $percentages[$value] . '%' : 'n/a');
					}
				}
				
				# Convert the table into HTML
				$output[$field]['results'] = application::htmlTable ($table, array (), $tableClass, $showKey = false, false, $allowHtml = true, false, false, false, array (), false, $showTableHeadings);
				
				# Add a piechart if wanted and wrap it in a div/table as required
				if ($piecharts) {
					
					# Find a suitable separator by checking a string made up of all the keys and values; by default , is used, but if that exists in any string, try others
					$string = '';
					foreach ($percentages as $key => $value) {
						$string .= $key . $value;
					}
					$ok = false;
					$separator = ',';
					$comma = ',';
					while (!$ok) {
						$separator .= $comma;	// Add on another comma
						if (!substr_count ($string, $separator)) {	// If neither key nor value has the separator, then choose it
							$ok = true;
							// Therefore this separator will be used
						}
					}
					$separatorQueryString = ($separator != $comma ? "separator={$separator}&amp;" : '');
					
					# Write the HTML
					if ($piechartDiv) {
						$output[$field]['results'] = "\n<div class=\"surveyresults\">\n\t<div class=\"surveyresultstable\">{$output[$field]['results']}\n\t</div>\n\t<div class=\"surveyresultspiechart\">\n\t\t<img width=\"{$piechartWidth}\" height=\"{$piechartHeight}\" src=\"{$piechartStub}?{$separatorQueryString}values=" . htmlspecialchars (implode ($separator, array_values ($percentages)) . '&desc=' . implode ($separator, array_keys ($percentages))) . "&amp;width={$piechartWidth}&amp;height={$piechartHeight}\" alt=\"Piechart of results\" />\n\t</div>\n</div>";
					} else {
						$output[$field]['results'] = "\n<table class=\"surveyresults\">\n\t<tr>\n\t\t<td class=\"surveyresultstable\">{$output[$field]['results']}</td>\n\t\t<td class=\"surveyresultspiechart\"><img width=\"{$piechartWidth}\" height=\"{$piechartHeight}\" src=\"{$piechartStub}?{$separatorQueryString}values=" . htmlspecialchars (implode ($separator, array_values ($percentages)) . '&desc=' . implode ($separator, array_keys ($percentages))) . "&amp;width={$piechartWidth}&amp;height={$piechartHeight}\" alt=\"Piechart of results\" /></td>\n\t</tr>\n</table>";
					}
				}
				
			# Render the table types
			} else if ($isTableChartType) {
				
				# Compile the results
				$table = array ();
				foreach ($this->elements[$field]['values'] as $value => $visible) {
					
					# Determine the numeric number of respondents for this value
					# Add the value
					$table[$value][''] = htmlspecialchars ($value);
					$table[$value]['respondents'] = (array_key_exists ($value, $responses) ? array_sum ($responses[$value]) : 0);
					
					# Show percentages if required
					$totalResponses = count ($responses[$value]);
					$percentages[$value] = round ((($table[$value]['respondents'] / $totalResponses) * 100), $chartPercentagePrecision);
					if ($showPercentages) {
						$table[$value]['percentage'] = $percentages[$value] . '%';
						$table[$value]['chart'] = "<div style=\"width: {$percentages[$value]}%\">{$percentages[$value]}%</div>";
					}
				}
				
				# Convert the data into an HTML table
				$output[$field]['results'] = application::htmlTable ($table, array (), $tableChartClass, $showKey = false, false, $allowHtml = true, false, $addCellClasses = true, false, array (), false, $showTableHeadings);
				
			# Render the list types
			} else {
				
				foreach ($responses as $index => $value) {
					$responses[$index] = nl2br (htmlspecialchars (trim ($value)));
				}
				$output[$field]['results'] = application::htmlUl ($responses, 1, $ulClass, $ulIgnoreEmpty);
			}
		}
		
		# Throw a setup error if expected fields are not in the CSV (if this happens, it indicates a programming error)
		if ($missingFields) {
			#!# Need to have ->specialchars applied to the fieldnames
			$this->formSetupErrors['resultReaderMissingFields'] = 'The following fields were not found in the result data: <strong>' . implode ('</strong>, <strong>', $missingFields) . '</strong>; please check the data source or consult the author of the webform system.';
		}
		
		# Throw a setup error if unknown values are found
		if ($unknownValues) {
			$this->formSetupErrors['resultReaderUnknownValues'] = 'The following unknown values were found in the result data: <strong>' . htmlspecialchars (implode ('</strong>, <strong>', $unknownValues)) . '</strong>.';
		}
		
		# End if there are form setup errors and report these
		if ($this->formSetupErrors) {
			$this->_setupOk ();
			echo $this->html;
			return false;
		}
		
		# Compile the HTML
		$html  = '';
		foreach ($output as $field => $results) {
			if (isSet ($results['heading'])) {
				$fieldEscaped = htmlspecialchars ($field);
				$html .= "\n\n<{$heading} id=\"{$fieldEscaped}\">" . ($anchors ? "<a href=\"#{$fieldEscaped}\">#</a> " : '') . htmlspecialchars ($results['heading']) . "</{$heading}>";
			}
			$html .= "\n" . $results['results'];
		}
		
		# Return the HTML
		return $html;
	}
	
	
	
	## Main processing ##
	
	
	# Function to return the submitted but pre-finalised data, for use in adding additional checks; effectively this provides a kind of callback facility
	public function getUnfinalisedData ($useDefinedOutputProcessingType = false)
	{
		# Return the form data, or an empty array (evaluating to false) if not posted
		return ($this->formPosted ? $this->getData ($useDefinedOutputProcessingType) : array ());
	}
	
	
	# Function to extract the values from submitted data
	private function getData ($useDefinedOutputProcessingType = false)
	{
		# Get the presentation defaults
		$presentationDefaults = $this->presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false);
		
		# Loop through each field and obtain the value
		$result = array ();
		foreach ($this->elements as $name => $element) {
			if (isSet ($element['data'])) {
				$widgetType = $element['type'];
				$processingPresentationType = $presentationDefaults[$widgetType]['processing'];
				if ($useDefinedOutputProcessingType) {
					if (isSet ($element['output']) && isSet ($element['output']['processing'])) {
						$processingPresentationType = $element['output']['processing'];
					}
				}
				$result[$name] = $element['data'][$processingPresentationType];
			}
		}
		
		# Return the data
		return $result;
	}
	
	
	/**
	 * Process/display the form (main wrapper function)
	 */
	function process (&$html = NULL)	// Note that &$value = defaultValue is not supported in PHP4 - see http://devzone.zend.com/node/view/id/1714#Heading5 point 3; if running PHP4, (a) remove the & and (b) change var $minimumPhpVersion above to 4.3
	{
		# Rearrange the element order if required
		$this->rearrangeElementOrder ();
		
		# Add in hidden anti-spam field at the end, if required
		$this->addAntiSpamHoneyPot ();
		
		# Do Askismet checking if required
		$this->akismetChecking ();
		
		# Perform wait if required
		if ($this->antispamWait) {
			sleep ($this->antispamWait);
		}
		
		# Determine whether the HTML is shown directly
		$showHtmlDirectly = ($html === NULL);
		
		# Prepend the supplied HTML to the main HTML
		if ($html) {$this->html = $html . $this->html;}
		
		# Open the surrounding <div> if relevant
		#!# This should not be done if the form is successful
		$scaffoldHtml  = '';
		if ($this->settings['div']) {
			$scaffoldHtml .= "\n\n<div class=\"{$this->settings['div']}\">";
			$this->html .= $scaffoldHtml;
		}
		
		# Show the presentation matrix if required (this is allowed to bypass the form setup so that the administrator can see what corrections are needed)
		if ($this->settings['displayPresentationMatrix']) {$this->displayPresentationMatrix ();}
		
		# Check if the form and PHP environment has been set up OK
		if (!$this->_setupOk ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Show debugging information firstly if required
		if ($this->settings['debug']) {$this->showDebuggingInformation ();}
		
		# Check whether the user is a valid user (must be before the setupOk check)
		if (!$this->validUser ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Check whether the facility is open
		if (!$this->facilityIsOpen ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Validate hidden security fields
		if ($this->hiddenSecurityFieldSubmissionInvalid ()) {
			if ($this->settings['div']) {$this->html .= "\n</div>";}
			if ($showHtmlDirectly) {echo $this->html;}
			$html = $this->html;
			return false;
		}
		
		# Perform replacement on the description at top-level if required
		if ($this->settings['titleReplacements']) {
			foreach ($this->elements as $name => $elementAttributes) {
				$this->elements[$name]['title'] = str_replace (array_keys ($this->settings['titleReplacements']), array_values ($this->settings['titleReplacements']), $elementAttributes['title']);
			}
		}
		
		# Determine if any kind of refresh button has been selected (either a __refresh or __refresh_add_<cleaned-id> / __refresh_subtract_<cleaned-id> expandable type)
		$formRefreshed = false;
		if (isSet ($this->collection['__refresh'])) {
			$formRefreshed = true;
		} else {
			foreach ($this->elements as $name => $elementAttributes) {
				$checkForRefreshAddWidgetName = '__refresh_add_' . $this->cleanId ($name);	// e.g. if a select widget called 'foo' has the 'expandable' attribute set, then check for __refresh_add_foo
				$checkForRefreshSubtractWidgetName = '__refresh_subtract_' . $this->cleanId ($name);	// e.g. if a select widget called 'foo' has the 'expandable' attribute set, then check for __refresh_subtract_foo
				if (isSet ($this->collection[$checkForRefreshAddWidgetName]) || isSet ($this->collection[$checkForRefreshSubtractWidgetName])) {
					$formRefreshed = true;
					break;
				}
			}
		}
		
		# Determine if the form is being saved (incomplete submission) rather than submitted; note that each element's data will only ever contain either valid or empty data, so the output can safely be transferred to a database
		$this->formSave = ($this->settings['saveButton'] && isSet ($this->collection['__save']));
		
		# If the form is not posted or contains problems, display it and flag that it has been displayed
		$elementProblems = $this->getElementProblems ();
		if ($this->formSave) {$elementProblems = false;}	// A form save bypasses validation
		if (!$this->formPosted || $elementProblems || $formRefreshed || ($this->settings['reappear'] && $this->formPosted && !$elementProblems)) {
			
			# Run the callback function if one is set
			if ($this->settings['callback']) {
				$this->settings['callback'] ($this->elementProblems ? -1 : 0);
			}
			
			# Add a note about refreshing
			if ($formRefreshed) {
				$this->html .= '<p><em>The form below has been refreshed but not yet submitted.</em></p>';
				$this->elementProblems = array ();	// Clear the element problems list in case this is being shown in templating mode
			}
			
			# Is the form successful?
			$formIsUnsuccessful = (!$this->formPosted || $elementProblems || $formRefreshed);
			
			# Should the form be disabled?
			if ($this->settings['reappear'] === 'disabled') {
				$this->formDisabled = (!$formIsUnsuccessful);
			}
			
			# Construct the HTML
			$this->html .= $this->constructFormHtml ($this->elements, $this->elementProblems, $formRefreshed);
			
			# Display the form and any problems then end
			if ($formIsUnsuccessful) {
				#!# This should not be done if the form is successful
				if ($this->settings['div']) {$this->html .= "\n</div>";}
				if ($showHtmlDirectly) {echo $this->html;}
				$html = $this->html;
				return false;
			}
		}
		
		# Process any form uploads
		$this->doUploads ();
		
		# Prepare the data
		$this->outputData = $this->prepareData ();
		
		# If required, display a summary confirmation of the result
		if ($this->settings['formCompleteText']) {$this->html .= "\n" . '<p class="completion">' . $this->settings['formCompleteText'] . ' </p>';}
		
		# Determine presentation format for each element
		$this->mergeInPresentationDefaults ();
		
		# Loop through each of the processing methods and output it based on the requested method
		foreach ($this->outputMethods as $outputType => $required) {
			$this->outputData ($outputType);
		}
		
		# If required, display a link to reset the page
		if ($this->settings['formCompleteText']) {$this->html .= "\n" . '<p><a href="' . $_SERVER['REQUEST_URI'] . '">Click here to reset the page.</a></p>';}
		
		# Close the surrounding <div> if relevant
		#!# This should not be done if the form is successful
		if ($this->settings['div']) {
			$scaffoldHtml .= "\n\n</div>";
			$this->html .= "\n\n</div>";
		}
		
		# If no HTML has been added, clear the surrounding div
		if ($this->html == $html . $scaffoldHtml) {
			$this->html = $html;
		}
		
		# Deal with the HTML
		if ($showHtmlDirectly) {echo $this->html;}
		$html = $this->html;
		// $html;	// Nothing is done with $html - it was passed by reference, if at all
		
		# Get the data
		$data = $this->outputData ('processing');
		
		# For a form save, if there are element problems, wipe out any value for those elements
#!# Unfinished work - need to wipe out the values for all output types properly, and deal with hierarchical ones
		if ($this->formSave) {
			if (isSet ($this->elementProblems['elements'])) {
				foreach ($this->elementProblems['elements'] as $field => $problems) {
					$data[$field] = '';
				}
			}
		}
		
		# If the data is grouped, rearrange it into groups first
		if ($this->prefixedGroups) {
			foreach ($this->prefixedGroups as $group => $fields) {
				$thisGroupEmpty = true;	// Flag to detect all fields in the group being not completed
				foreach ($fields as $field) {
					#!# Currently this will NOT filter data which is in array format, e.g. a select field with the default output type
					if ($data[$field]) {$thisGroupEmpty = false;}
					$unprefixedFieldname = preg_replace ("/^{$group}_/", '', $field);
					$groupedData[$group][$unprefixedFieldname] = $data[$field];
					unset ($data[$field]);
				}
				
				# Omit this group of fields in the output if it is empty
				if ($this->settings['prefixedGroupsFilterEmpty']) {
					if ($thisGroupEmpty) {
						unset ($groupedData[$group]);
					}
				}
			}
			
			# Add on the remainder into a new group, called '0'
			if ($data) {
				$groupedData[0] = $data;
			}
			$data = $groupedData;
		}
		
		# If required, redirect to a URL containing only the non-empty
		if ($this->settings['redirectGet']) {
			if ($_POST) {	// Check avoids redirect loop scenario
				
				# Filter to include only those where the user has specified a value, to keep the URL as short as possible
				$nonemptyValues = array ();
				foreach ($data as $key => $value) {
					if (strlen ($value)) {
						$nonemptyValues[$key] = $value;
					}
				}
				
				# Redirect so that the search parameters can be persistent; SCRIPT_URL is used as it is the location without query string
				$url = $_SERVER['_SITE_URL'] . $_SERVER['SCRIPT_URL'] . '?' . str_replace ('%2C', ',', http_build_query ($nonemptyValues));	// Comma-replacement is to keep the URL easier to read, as it does not need to be encoded, being a sub-delim (and the query component does not use these sub-delims); see: http://stackoverflow.com/a/2375597
				application::sendHeader (302, $url);
			}
		}
		
		# Return the data (whether virgin or grouped)
		return $data;
	}
	
	
	# Getter function to return if this is a save
	public function isSave ()
	{
		return $this->formSave;
	}
	
	
	## Form processing support ##
	
	# Function to rearrange the element order if required
	function rearrangeElementOrder ()
	{
		# Determine if the ordering needs to be changed
		$afters = array ();
		foreach ($this->elements as $name => $elementAttributes) {
			if ($elementAttributes['after']) {
				$goesAfter = $elementAttributes['after'];
				if (!isSet ($this->elements[$goesAfter])) {
					$this->formSetupErrors['formEmpty'] = 'There is no element' . htmlspecialchars ($goesAfter) . ' to place an element after.';
					return false;
				}
				$afters[$name] = $goesAfter;
			}
			unset ($this->elements[$name]['after']);	// Since we don't need this again
		}
		
		# End if no ordering
		if (!$afters) {return;}
		
		# Do the reordering by copying the elements to a new temporary array
		$elementsReordered = array ();
		foreach ($this->elements as $name => $elementAttributes) {
			
			# Skip if the element has already been moved into the array
			if (isSet ($elementsReordered[$name])) {continue;}
			
			# Reinstate the current element in the natural order
			$elementsReordered[$name] = $elementAttributes;
			
			# Determine if anything comes after this element, and if so, apply each in order (there could be more than one, so an array_merge(array_spliceBefore,new,array_spliceAfter) method is not appropriate)
			if (in_array ($name, $afters)) {	// Quick check to avoid pointless looping for each element
				foreach ($afters as $newElement => $goesAfter) {
					if ($goesAfter == $name) {
						$elementsReordered[$newElement] = $this->elements[$newElement];
					}
				}
			}
		}
		
		# Use the new array
		$this->elements = $elementsReordered;
	}
	
	
	# Function to determine whether this facility is open
	function facilityIsOpen ()
	{
		# Check that the opening time has passed, if one is specified, ensuring that the date is correctly specified
		if ($this->settings['opening']) {
			if (time () < strtotime ($this->settings['opening'] . ' GMT')) {
				$this->html .= '<p class="warning">This facility is not yet open. Please return later.</p>';
				return false;
			}
		}
		
		# Check that the closing time has passed
		if ($this->settings['closing']) {
			if (time () > strtotime ($this->settings['closing'] . ' GMT')) {
				$this->html .= '<p class="warning">This facility is now closed.</p>';
				return false;
			}
		}
		
		# Otherwise return true
		return true;
	}
	
	
	# Function to determine if the user is a valid user
	function validUser ()
	{
		# Return true if no users are specified
		if (!$this->settings['validUsers']) {return true;}
		
		# If '*' is specified for valid users, allow any through
		if ($this->settings['validUsers'][0] == '*') {return true;}
		
		# If the username is supplied in a list, return true
		if (in_array ($this->settings['user'], $this->settings['validUsers'])) {return true;}
		
		# Otherwise state that the user is not in the list and return false
		$this->html .= "\n" . '<p class="warning">You do not appear to be in the list of valid users. If you believe you should be, please contact the webmaster to resolve the situation.</p>';
		return false;
	}
	
	
	/**
	 * Function to check for form setup errors
	 * @todo Add all sorts of other form setup checks as flags within this function
	 * @access private
	 */
	function _setupOk ()
	{
		# Check the PHP environment set up is OK
		$this->validEnvironment ();
		
		# Check that there are no namespace clashes against internal defaults
		$this->preventNamespaceClashes ();
		
		# If a user is to be required, ensure there is a server-supplied username
		if ($this->settings['validUsers'] && !$this->settings['user']) {$this->formSetupErrors['usernameMissing'] = 'No username is being supplied, but the form setup requires that one is supplied, either explicitly or implicitly through the server environment. Please check the server configuration.';}
		
		# If a user uniqueness check is required, ensure that the file output mode is in use and that the user is being logged as a CSV key
		if ($this->settings['loggedUserUnique'] && !$this->outputMethods['file']) {$this->formSetupErrors['loggedUserUniqueRequiresFileOutput'] = "The settings specify that usernames are checked for uniqueness against existing submissions, but no log file of submissions is being made. Please ensure that the 'file' output type is enabled if wanting to check for uniqueness.";}
		if ($this->settings['loggedUserUnique'] && !$this->settings['userKey']) {$this->formSetupErrors['loggedUserUniqueRequiresUserKey'] = 'The settings specify that usernames are checked for uniqueness against existing submissions, but usernames are not set to be logged in the data. Please ensure that both are enabled if wanting to check for uniqueness.';}
		
		# Check that an empty form hasn't been requested (i.e. there must be at least one form field)
		#!# This needs to be modified to take account of headers (which should not be included)
		if (empty ($this->elements)) {$this->formSetupErrors['formEmpty'] = 'No form elements have been defined (i.e. the form is empty).';}
		
		# If there are any duplicated keys, list each duplicated key in bold with a comma between (but not after) each
		if ($this->duplicatedElementNames) {$this->formSetupErrors['duplicatedElementNames'] = 'The following field ' . (count (array_unique ($this->duplicatedElementNames)) == 1 ? 'name has' : 'names have been') . ' been duplicated in the form setup: <strong>' . implode ('</strong>, <strong>', array_unique ($this->duplicatedElementNames)) .  '</strong>.';}
		
		# Validate the output format syntax items, looping through each defined element that has an output configuration defined
		#!# Move this block into a new widget object's constructor
		$formatSyntaxInvalidElements = array ();
		$availableOutputFormats = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = false);
		foreach ($this->elements as $name => $elementAttributes) {
			if (!$elementAttributes['output']) {continue;}
			
			# Define the supported formats for this type of element
			$supportedFormats = $availableOutputFormats[$elementAttributes['type']];
			
			# Loop through each output type specified in the form setup
			foreach ($elementAttributes['output'] as $outputFormatType => $outputFormatValue) {
				
				# Check that the type and value are both supported
				if (!array_key_exists ($outputFormatType, $supportedFormats) || !in_array ($outputFormatValue, $supportedFormats[$outputFormatType])) {
					$formatSyntaxInvalidElements[$name] = true;
					break;
				}
			}
		}
		if ($formatSyntaxInvalidElements) {$this->formSetupErrors['outputFormatMismatch'] = 'The following field ' . (count ($formatSyntaxInvalidElements) == 1 ? 'name has' : 'names have') . " an incorrect 'output' setting: <strong>" . implode ('</strong>, <strong>', array_keys ($formatSyntaxInvalidElements)) .  '</strong>; the administrator should switch on the \'displayPresentationMatrix\' option in the settings to check the syntax.';}
		
		# Check templating in template mode
		$this->setupTemplating ();
		
		# Validate the callback mode setup
		if ($this->settings['callback'] && !function_exists ($this->settings['callback'])) {
			$this->formSetupErrors['callback'] = 'You specified a callback function but no such function exists.';
		}
		
		# Check group validation checks are valid
		$this->_checkGroupValidations ();
		
		# If there are any form setup errors - a combination of those just defined and those assigned earlier in the form processing, show them
		$errorTexts = array ();
		$warningTexts = array ();
		if (!empty ($this->formSetupErrors)) {
			
			# Split the setup errors into errors and warnings
			foreach ($this->formSetupErrors as $errorKey => $error) {
				$errorText = "\n- " . strip_tags ($error);
				if (substr ($errorKey, 0, 1) == '_') {
					$warningTexts[] = $errorText;
				} else {
					$errorTexts[] = $errorText;
				}
			}
			
			# Show the setup errors/warnings
			$introductionMessageComponents = array ();
			if ($errorTexts) {
				$introductionMessageComponents[] = (count ($errorTexts) > 1 ? 'various errors' : 'an error');
			}
			if ($warningTexts) {
				$introductionMessageComponents[] = (count ($warningTexts) > 1 ? 'various warnings' : 'a warning');
			}
			$introductionMessage = ucfirst (implode (' and ', $introductionMessageComponents)) . ' ' . (count ($this->formSetupErrors) > 1 ? 'were' : 'was') . ' found in the setup of the form.' . ($warningTexts && !$errorTexts /* Form not shown if any errors, so don't point to warnings in that scenario */ ? ' Please see the warning(s) below.' : '') . ($errorTexts ? " The website's administrator needs to correct the error before the form will work." : '');
			$setupErrorText = application::showUserErrors ($this->formSetupErrors, $parentTabLevel = 1, $introductionMessage, false, $this->settings['errorsCssClass']);
			$this->html .= $setupErrorText;
			
			# E-mail the errors to the admin if wanted
			if ($this->settings['mailAdminErrors']) {
				$administrator = (application::validEmail ($this->settings['mailAdminErrors']) ? $this->settings['mailAdminErrors'] : $_SERVER['SERVER_ADMIN']);
				$message  = "The webform at \n" . $_SERVER['_PAGE_URL'] . "\nreports the following ultimateForm setup misconfiguration:\n";
				if ($errorTexts) {
					$message .= "\n\nERRORS:\n" . implode ("\n", $errorTexts);
				}
				if ($warningTexts) {
					$message .= "\n\nWARNINGS:\n" . implode ("\n", $warningTexts);
				}
				$message .= "\n\n\nIP:    {$_SERVER['REMOTE_ADDR']}\nUser:  {$_SERVER['REMOTE_USER']}";
				application::utf8Mail (
					$administrator,
					'Form setup ' . ($errorTexts ? 'error' : 'warning'),
					wordwrap ($message),
					$additionalHeaders = "From: {$this->settings['emailName']} <" . $administrator . ">\r\n"
				);
			}
		}
		
		# Set that the form has effectively been displayed
		$this->formDisplayed = true;
		
		# Return true (i.e. form set up OK) if the errors (i.e. excluding warnings) array is empty
		return (empty ($errorTexts));
	}
	
	
	# Function to set up a database connection
	function _setupDatabaseConnection ()
	{
		# Nothing to do if no connection supplied
		if (!$this->settings['databaseConnection']) {return;}
		
		# Now that a database connection is confirmed required, set it to be false (rather than NULL) until overriden (this is important for later checking when using the connection)
		$this->databaseConnection = false;
		
		# If the link is not a database resource/object but is an array or a file use open that and end
		#!# Use of is_resource won't properly work yet
		if (/*is_resource ($this->settings['databaseConnection']) || */ is_object ($this->settings['databaseConnection'])) {
			$this->databaseConnection = $this->settings['databaseConnection'];
			return true;
		}
		
		# If it's an array type, assign the array directly
		if (is_array ($this->settings['databaseConnection'])) {
			$credentials = $this->settings['databaseConnection'];
			
		# If it's a file, open it and ensure there is a $credentials array given
		} elseif (is_file ($this->settings['databaseConnection'])) {
			if (!include ($this->settings['databaseConnection'])) {
				$this->formSetupErrors['databaseCredentialsFileNotFound'] ('The database credentials file could not be open or does not exist.');
				return false;
			}
			if (!isSet ($credentials)) {
				$this->formSetupErrors['databaseCredentialsFileNoArray'] ('The database credentials file did not contain a $credentials array.');
				return false;
			}
			
		# If it's none of the above, throw an error
		} else {
			$this->formSetupErrors['databaseCredentialsUnsupported'] = 'The database credentials setting does not seem to be a supported type or is otherwise invalid.';
			return false;
		}
		
		# Create the connection using the credentials array now assigned
		require_once ('database.php');
		$this->databaseConnection = new database ($credentials['hostname'], $credentials['username'], $credentials['password']);
		if (!$this->databaseConnection->connection) {
			$this->formSetupErrors['databaseCredentialsFile'] = 'The database connection failed for some reason.';
			return false;
		}
	}
	
	
	# Function to check templating
	function setupTemplating ()
	{
		# End further checks if not in the display mode
		if ($this->settings['display'] != 'template') {return;}
		
		# Ensure the template pattern includes the placemarker %element
		$placemarker = '%element';
		$checkParameters = array ('displayTemplatePatternWidget', 'displayTemplatePatternLabel', 'displayTemplatePatternSpecial');
		foreach ($checkParameters as $checkParameter) {
			if (strpos ($this->settings[$checkParameter], $placemarker) === false) {
				$this->formSetupErrors["{$checkParameter}Invalid"] = "The <tt>{$checkParameter}</tt> parameter must include the placemarker <tt>{$placemarker}</tt> ; by default the parameter's value is <tt>{$this->argumentDefaults[$checkParameter]}</tt>";
			}
		}
		
		# Check that none of the $checkParameters items are the same
		foreach ($checkParameters as $checkParameter) {
			$values[] = $this->settings[$checkParameter];
		}
		if (count ($values) != count (array_unique ($values))) {
			$this->formSetupErrors['displayTemplatePatternDuplication'] = 'The values of the parameters <tt>' . implode ('</tt>, <tt>', $checkParameters) . '</tt> must all be unique.';
		}
		
		# Determine if the template is a file or string
		if (is_file ($this->settings['displayTemplate'])) {
			
			# Check that the template is readable
			if (!is_readable ($this->settings['displayTemplate'])) {
				$this->formSetupErrors['templateNotFound'] = 'You appear to have specified a template file for the <tt>displayTemplate</tt> parameter, but the file could not be opened.</tt>';
				return false;
			}
			$this->displayTemplateContents = file_get_contents ($this->settings['displayTemplate']);
		} else {
			$this->displayTemplateContents = $this->settings['displayTemplate'];
		}
		
		# Assemble the list of elements and their replacements
		$this->displayTemplateElementReplacements = array ();
		foreach ($this->elements as $element => $attributes) {
			$this->displayTemplateElementReplacements[$element]['widget'] = str_replace ($placemarker, $element, $this->settings['displayTemplatePatternWidget']);
			$this->displayTemplateElementReplacements[$element]['label']  = str_replace ($placemarker, $element, $this->settings['displayTemplatePatternLabel']);
		}
		
		# Parse the template to ensure that all non-hidden elements exist in the template
		$missingElements = array ();
		foreach ($this->displayTemplateElementReplacements as $element => $replacements) {
			if ($this->elements[$element]['type'] == 'hidden') {continue;}
			if (substr_count ($this->displayTemplateContents, $replacements['widget']) !== 1) {
				
				# If the element is not present in the replacements, determine if this is a type of element that contains a sub-elements widgets listing, e.g. radiobuttons, checkboxes
				$isSubelementType = (isSet ($this->elements[$element]['subelementsWidgetHtml']));
				
				# If this is a subelement type, check for presence
				if ($isSubelementType) {
					$subelementSubstitutions = array ();
					foreach ($this->elements[$element]['values'] as $subelementValue => $visible) {
						$subelement = $element . '_' . $subelementValue;	// e.g. selectionlist_option1, selectionlist_someotheroption, etc.
						$subelementSubstitutions[$subelementValue]['widget'] = str_replace ($placemarker, $subelement, $this->settings['displayTemplatePatternWidget']);
						// $subelementSubstitutions[$subelementValue]['label']  = str_replace ($placemarker, $subelement, $this->settings['displayTemplatePatternLabel']);	// There is no concept of a label for sub-elements
						if (substr_count ($this->displayTemplateContents, $subelementSubstitutions[$subelementValue]['widget']) !== 1) {
							break;	// It is not present, so break out of the inner loop, and proceed to register the overall element as a missing element
						}
					}
					
					# If all sub-elements have substitutions, the element is complete, so register the substitutions by overwriting the substitution with an array
					if (count ($subelementSubstitutions) == count ($this->elements[$element]['values'])) {
						$this->displayTemplateElementReplacements[$element] = $subelementSubstitutions;
						continue;	// Proceed ot the next element as we have confirmed that the element is no longer missing since its sub-elements are all present
					}
				}
				
				# Otherwise, register this as a missing element
				$missingElements[] = $replacements['widget'] . ($isSubelementType ? ' (or a list of sub-elements)' : '');
			}
		}
		
		# Construct an array of missing elements if there are any; labels are considered optional
		if ($missingElements) {
			$this->formSetupErrors['templateElementsNotFoundWidget'] = 'The following element ' . ((count ($missingElements) == 1) ? 'string was' : 'strings were') . ' not present once only in the template you specified: ' . implode (', ', $missingElements);
		}
		
		# Define special placemarker names and whether they are required; these can appear more than once
		$specialPlacemarkers = array (
			'PROBLEMS'	=> true,				// Placemarker for the element problems box
			'SUBMIT'	=> true,				// Placemarker for the submit button
			'RESET'		=> $this->settings['resetButton'],	// Placemarker for the reset button - if there is one
			'SAVE'		=> $this->settings['saveButton'],	// Placemarker for the save button - if there is one
			'REQUIRED'	=> false,			// Placemarker for the required fields indicator text
		);
		if ($this->settings['refreshButton']) {
			$specialPlacemarkers['REFRESH'] = false;	// Placemarker for a refresh button
		}
		
		# Loop through each special placemarker, allocating its replacement shortcut and checking it exists if necessary
		$missingElements = array ();
		foreach ($specialPlacemarkers as $specialPlacemarker => $required) {
			$this->displayTemplateElementReplacementsSpecials[$specialPlacemarker] = str_replace ($placemarker, $specialPlacemarker, $this->settings['displayTemplatePatternSpecial']);
			if ($required) {
				if (!substr_count ($this->displayTemplateContents, $this->displayTemplateElementReplacementsSpecials[$specialPlacemarker])) {
					$missingElements[] = $this->displayTemplateElementReplacementsSpecials[$specialPlacemarker];
				}
			}
		}
		
		# Construct an array of missing elements if there are any; labels are considered optional
		if ($missingElements) {
			$this->formSetupErrors['templateElementsNotFoundSpecials'] = 'The following element ' . ((count ($missingElements) == 1) ? 'string was' : 'strings were') . ' not present at least once in the template you specified: ' . implode (', ', $missingElements);
		}
	}
	
	
	/**
	 * Function to perform validity checks to ensure a correct PHP environment
	 * @access private
	 */
	function validEnvironment ()
	{
		# Check the minimum PHP version, to ensure that all required functions will be available
		if (version_compare (PHP_VERSION, $this->minimumPhpVersion, '<')) {$this->formSetupErrors['environmentPhpVersion'] = 'The server must be running PHP version <strong>' . $this->minimumPhpVersion . '</strong> or higher.';}
		
		# Check that global user variables cannot be imported into the program
		if ((bool) ini_get ('register_globals')) {$this->formSetupErrors['environmentRegisterGlobals'] = 'The PHP configuration setting register_globals must be set to <strong>off</strong>.';}
		
		# Check that magic_quotes are switched off; escaping of user input is handled manually
		#!# Replace these with data cleaning methods
		if ((bool) ini_get ('magic_quotes_gpc')) {$this->formSetupErrors['environmentMagicQuotesGpc'] = 'The PHP configuration setting magic_quotes_gpc must be set to <strong>off</strong>.';}
		if ((bool) ini_get ('magic_quotes_sybase')) {$this->formSetupErrors['environmentMagicQuotesSybase'] = 'The PHP configuration setting magic_quotes_sybase must be set to <strong>off</strong>.';}
		
		# Perform checks on upload-related settings if any elements are upload types and the check has not been run
		if ($this->uploadProperties) {
			
			# Ensure file uploads are allowed
			if (!ini_get ('file_uploads')) {
				$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting file_uploads must be set to <strong>on</strong> given that the form includes an upload element.';
			} else {
				
				# If file uploads are being allowed, check that upload_max_filesize and post_max_size are valid
				if ((!preg_match ('/^(\d+)([bkm]*)$/iD', ini_get ('upload_max_filesize'))) || (!preg_match ('/^(\d+)([bkm]*)$/iD', ini_get ('post_max_size')))) {
					$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize/post_max_size must both be valid.';
				} else {
					
					# Given that file uploads are being allowed and the ensure that the upload size is not greater than the maximum POST size
					if (application::convertSizeToBytes (ini_get ('upload_max_filesize')) > application::convertSizeToBytes (ini_get ('post_max_size'))) {$this->formSetupErrors['environmentFileUploads'] = 'The PHP configuration setting upload_max_filesize cannot be greater than post_max_filesize; the form includes an upload element, so this misconfiguration must be corrected.';}
				}
			}
		}
	}
	
	
	# Function to register element names
	function registerElementName ($name)
	{
		# Add the name to the list of duplicated element names if it is already set
		if (isSet ($this->elements[$name])) {$this->duplicatedElementNames[] = $name;}
	}
	
	
	/**
	 * Function to check for namespace clashes against internal defaults
	 * @access private
	 */
	#!# Ideally replace each clashable item with an encoding method somehow or ideally eradicate the restrictions
	function preventNamespaceClashes ()
	{
		# Disallow [ or ] in a form name
		if ((strpos ($this->settings['name'], '[') !== false) || (strpos ($this->settings['name'], ']') !== false)) {
			$this->formSetupErrors['namespaceFormNameContainsSquareBrackets'] = 'The name of the form ('. $this->settings['name'] . ') cannot include square brackets.';
		}
		
		# Disallow valid e-mail addresses as an element name, to prevent setOutputEmail () picking a form element which should actually be an e-mail address
		foreach ($this->elements as $name => $elementAttributes) {
			if (application::validEmail ($name)) {
				$this->formSetupErrors['namespaceelementNameStartDisallowed'] = 'Element names cannot be in the format of an e-mail address.';
				break;
			}
		}
		
		# Disallow _heading at the start of an element
		#!# This will also be listed alongside the 'Element names cannot start with _heading'.. warning
		foreach ($this->elements as $name => $elementAttributes) {
			if (preg_match ('/^_heading/', $name)) {
				if ($elementAttributes['type'] != 'heading') {
					$disallowedelementNames[] = $name;
				}
			}
		}
		if (isSet ($disallowedelementNames)) {
			$this->formSetupErrors['namespaceelementNameStartDisallowed'] = 'Element names cannot start with _heading; the <strong>' . implode ('</strong>, <strong>', $disallowedelementNames) . '</strong> elements must therefore be renamed.';
		}
	}
	
	
	# Function to define DHTML for unsaved data protection - see http://stackoverflow.com/questions/140460/client-js-framework-for-unsaved-data-protection/2402725#2402725
	function unsavedDataProtectionJs ($formId)
	{
		# Determine the text to be used
		$messageText = ($this->settings['unsavedDataProtection'] === true ? 'Leaving this page will cause edits to be lost. Press the submit button on the page if you wish to save your data.' : $this->settings['unsavedDataProtection']);
		
		# Create the jQuery code
		$this->jQueryCode[__FUNCTION__] = "
			
			// Navigate-away protection for general widgets
			function removeCheck () { window.onbeforeunload = null; }
			$(document).ready (function () {
			    $('#{$formId} :input').one ('change', function () {
			        window.onbeforeunload = function () {
			            return '{$messageText}';
			        }
			    });
			    $('#{$formId} input[type=submit]').click (function () { removeCheck () });
			});
			
			// Navigate-away protection for Richtext widgets; see: https://stackoverflow.com/a/25050155
			if (typeof CKEDITOR !== 'undefined') {
				var i;
				var editable = {};
				for (instanceName in CKEDITOR.instances) {
					
					// GUI-based changes
					CKEDITOR.instances[instanceName].on ('change', function () {
						window.onbeforeunload = function () {
							return '{$messageText}';
						}
					});
					
					// Source-based changes
					CKEDITOR.instances[instanceName].on ('mode', function () {
						if (this.mode == 'source') {
							editable[instanceName] = CKEDITOR.instances[instanceName].editable ();
							editable[instanceName].attachListener (editable[instanceName], 'input', function () {
								window.onbeforeunload = function () {
									return '{$messageText}';
								}
							});
						}
					});
				}
			}
		";
	}
	
	
	# Function to define DHTML for drag-and-drop reorderable rows - see: http://www.avtex.com/blog/2015/01/27/drag-and-drop-sorting-of-table-rows-in-priority-order/
	private function reorderableRows ($formId)
	{
		# Create the jQuery code
		$this->jQueryCode[__FUNCTION__] = "
			\$(document).ready(function() {

				// Helper function to keep table row from collapsing when being sorted
				var fixHelperModified = function(e, tr) {
					var \$originals = tr.children();
					var \$helper = tr.clone();
					\$helper.children().each(function(index)
					{
						\$(this).width(\$originals.eq(index).width())
					});
					return \$helper;
				};
					
				// Make table sortable
				\$('#{$formId} table tbody').sortable({
					helper: fixHelperModified,
					stop: function(event,ui) {renumber_table('#{$formId}} table')}
				});
				
				// Set pointer style
				\$('#{$formId} table tr').css({'cursor':'move'});
				\$('#{$formId} table tr:hover').css({'background-color':'#f7f7f7'});
				
				/*
				// Delete button in table rows
				\$('table').on('click','.btn-delete',function() {
					tableID = '#' + \$(this).closest('table').attr('id');
					r = confirm('Delete this item?');
					if(r) {
						\$(this).closest('tr').remove();
						renumber_table(tableID);
					}
				});
				*/
			});
		";
	}
	
	
	/**
	 * Function actually to display the form
	 * @access private
	 */
	function constructFormHtml ($elements, $problems, $formRefreshed)
	{
		# Define various HTML snippets
		$requiredFieldIndicatorHtml = "\n" . '<p class="requiredmessage"><strong>*</strong> Items marked with an asterisk [*] are required fields and must be fully completed.</p>';
		
		# Add the problems list
		if ($this->settings['display'] == 'template') {
			$html = '';
			$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacementsSpecials['PROBLEMS'], $this->problemsList ($problems), $this->displayTemplateContents);
		} else {
			$html  = "\n" . $this->problemsList ($problems);
		}
		
		# Add the required field indicator display message if required
		if (($this->settings['display'] != 'template') && ($this->settings['requiredFieldIndicator'] === 'top')) {$html .= $requiredFieldIndicatorHtml;}
		
		# Add unsaved data protection HTML if required, ensuring that an ID exists for the form tag
		#!# If there is more than one form on the page, unsavedDataProtection does not work, probably because window.onbeforeunload is being set twice
		if ($this->settings['unsavedDataProtection']) {
			#!# This needs to be handled more generically as this code is duplicated
			if (!$this->settings['id']) {
				$this->settings['id'] = 'ultimateForm';
			}
			$this->unsavedDataProtectionJs ($this->settings['id']);
		}
		
		# Add drag-and-drop reorderability of rows if required
		if ($this->settings['reorderableRows']) {
			if (!$this->settings['id']) {
				$this->settings['id'] = 'ultimateForm';
			}
			$html .= $this->reorderableRows ($this->settings['id']);
		}
		
		# Load the jQuery library and client code if a widget/option has enabled its use and the setting for the source URL is specified
		$html .= $this->loadJavascriptCode ();
		
		# Start the constructed form HTML
		$html .= "\n" . '<form' . ($this->settings['id'] ? " id=\"{$this->settings['id']}\"" : '') . ' method="' . $this->method . '" name="' . ($this->settings['name'] ? $this->settings['name'] : 'form') . '" action="' . htmlspecialchars ($this->settings['submitTo']) . '" enctype="' . ($this->uploadProperties ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '" accept-charset="UTF-8">';
		
		# Start the HTML
		$formHtml = '';
		$hiddenHtml = "\n";
		
		# Determine whether to display the descriptions - display if on and any exist
		$displayDescriptions = false;
		if ($this->settings['displayDescriptions']) {
			foreach ($elements as $name => $elementAttributes) {
				if (!empty ($elementAttributes['description'])) {
					$displayDescriptions = true;
					break;
				}
			}
		}
		
		# Loop through each of the elements to construct the form HTML
		foreach ($elements as $name => $elementAttributes) {
			
			# For hidden elements, buffer the hidden HTML then skip remainder of loop execution; for the template type, remove the placemarker also
			if ($elementAttributes['type'] == 'hidden') {
				$hiddenHtml .= $elementAttributes['html'];
				/*
				# Remove any extraneous {hidden} indicators
				if ($this->settings['display'] == 'template') {
					$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacements[$name], '', $this->displayTemplateContents);
					$formHtml = $this->displayTemplateContents;
				}
				*/
				continue;
			}
			
			# Special case (to be eradicated - 'hidden visible' fields due to _hidden in dataBinding)
			if (array_key_exists ('_visible--DONOTUSETHISFLAGEXTERNALLY', $elementAttributes)) {
				$hiddenHtml .= $elementAttributes['_visible--DONOTUSETHISFLAGEXTERNALLY'];
				continue;
			}
			
			# If colons are set to show, add them
			if ($this->settings['displayColons']) {$elementAttributes['title'] .= ':';}
			
			# If the element is required, and indicators are in use add an indicator
			$elementIsRequired = ($this->settings['requiredFieldIndicator'] && $elementAttributes['required']);
			if ($elementIsRequired) {
				$elementAttributes['title'] .= ($elementAttributes['editable'] ? '&nbsp;*' : '<span class="requirednoneditable">&nbsp;*</span>');
			}
			
			# If the form has been posted AND the element has any problems or is empty, add the warning CSS class
			$indicateWarning = ($this->formPosted && !$formRefreshed && (($elementAttributes['problems']) || ($elementAttributes['requiredButEmpty']) || (($elementAttributes['type'] == 'upload') && (isSet ($this->elementProblems['generic']) && isSet ($this->elementProblems['generic']['reselectUploads'])))));
			if ($indicateWarning) {
				if ($this->settings['display'] == 'template') {
					$elementAttributes['html'] = '<span class="unsuccessful">' . $elementAttributes['html'] . '</span>';	// Surround the whole HTML with a new element if it is not a "successful" element
				} else {
					$elementAttributes['title'] = '<span class="warning">' . $elementAttributes['title'] . '</span>';	// Surround the title with a warning
				}
			}
			
			# Select whether to show restriction guidelines
			$displayRestriction = ($this->settings['displayRestrictions'] && $elementAttributes['restriction']);
			
			# Determine whether to hide using CSS; this is intermediate code due to be refactored
			#!# No support yet for templating
			$cssHide = (isSet ($elementAttributes['_cssHide--DONOTUSETHISFLAGEXTERNALLY']) && $elementAttributes['_cssHide--DONOTUSETHISFLAGEXTERNALLY']);
			
			# Clean the ID
			#!# Move this into the element attributes set at a per-element level, for consistency so that <label> is correct
			$id = $this->cleanId ($name);
			
			# Display the display text (in the required format), unless it's a hidden array (i.e. no elementText to appear)
			switch ($this->settings['display']) {
				
				# Display as paragraphs
				case 'paragraphs':
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n" . $elementAttributes['html'];
					} else {
						$formHtml .= "\n" . '<p class="row ' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '"' . ($cssHide ? ' style="display: none;"' : '') . '>';
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= $elementAttributes['title'] . '<br />';
							if ($displayRestriction) {$formHtml .= "<br /><span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ')</span>';}
						}
						$formHtml .= $elementAttributes['html'];
						#!# Need to have looped through each $elementAttributes['description'] and remove that column if there are no descriptions at all
						if ($displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "<br />\n<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
						$formHtml .= "\n</p>";
					}
					break;
					
				# Display using divs for CSS layout mode; this is different to paragraphs as the form fields are not conceptually paragraphs
				case 'css':
					$formHtml .= "\n" . '<div class="row ' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '" id="' . $id . '"' . ($cssHide ? ' style="display: none;"' : '') . '>';
					if ($elementAttributes['type'] == 'heading') {
						$formHtml .= "\n\t<span class=\"title\">" . $elementAttributes['html'] . '</span>';
					} else {
						$formHtml .= "\n\t";
						if ($this->settings['displayTitles']) {
							if ($displayRestriction) {
								$formHtml .= "<span class=\"label\">";
								$formHtml .= "\n\t\t" . $elementAttributes['title'];
								$formHtml .= "\n\t\t<span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ')</span>';
								$formHtml .= "\n\t</span>";
							} else {
								$formHtml .= "<span class=\"label\">" . $elementAttributes['title'] . '</span>';
							}
						}
						$formHtml .= "\n\t<span class=\"data\">" . $elementAttributes['html'] . '</span>';
						if ($displayDescriptions) {if ($elementAttributes['description']) {$formHtml .= "\n\t<span class=\"description\">" . $elementAttributes['description'] . '</span>';}}
					}
					$formHtml .= "\n</div>";
					break;
					
				# Templating - perform each replacement on a per-element basis
				case 'template':
					$standardScalarElementReplacement = (isSet ($this->displayTemplateElementReplacements[$name]['widget']));
					if ($standardScalarElementReplacement) {
						if ($cssHide) {$elementAttributes['html'] = '<span style="display: none;">'  . $elementAttributes['html'] . '</span>';}
						$this->displayTemplateContents = str_replace ($this->displayTemplateElementReplacements[$name] /* i.e. array(widget=>..,label=>...) */, array ($elementAttributes['html'], $elementAttributes['title']), $this->displayTemplateContents);
					} else {
						foreach ($this->displayTemplateElementReplacements[$name] as $subelement => $replacements) {
							if ($cssHide) {$elementAttributes['subelementsWidgetHtml'][$subelement] = '<span style="display: none;">'  . $elementAttributes['subelementsWidgetHtml'][$subelement] . '</span>';}
							$this->displayTemplateContents = str_replace ($replacements['widget'], $elementAttributes['subelementsWidgetHtml'][$subelement], $this->displayTemplateContents);
						}
					}
					$formHtml = $this->displayTemplateContents;
					break;
				
				# Tables
				case 'tables':
				default:
					$formHtml .= "\n\t" . '<tr class="' . $id . ($this->settings['classShowType'] ? " {$elementAttributes['type']}" : '') . ($elementIsRequired ? " {$this->settings['requiredFieldClass']}" : '') . '"' . ($cssHide ? ' style="display: none;"' : '') . '>';
					if ($elementAttributes['type'] == 'heading') {
						# Start by determining the number of columns which will be needed for headings involving a colspan
						$colspan = 1 + ($this->settings['displayTitles']) + ($displayDescriptions);
						$formHtml .= "\n\t\t<td colspan=\"$colspan\">" . $elementAttributes['html'] . '</td>';
					} else {
						$formHtml .= "\n\t\t";
						if ($this->settings['displayTitles']) {
							$formHtml .= "<td class=\"title\">" . ($elementAttributes['title'] == '' ? '&nbsp;' : $elementAttributes['title']);
							if ($displayRestriction) {$formHtml .= "<br />\n\t\t\t<span class=\"restriction\">(" . preg_replace ("/\n/", '<br />', $elementAttributes['restriction']) . ")</span>\n\t\t";}
							$formHtml .= '</td>';
						}
						$formHtml .= "\n\t\t<td class=\"data\">" . $elementAttributes['html'] . '</td>';
						if ($displayDescriptions) {$formHtml .= "\n\t\t<td class=\"description\">" . ($elementAttributes['description'] == '' ? '&nbsp;' : $elementAttributes['description']) . '</td>';}
					}
					$formHtml .= "\n\t</tr>";
			}
		}
		
		# In the table mode, having compiled all the elements surround the elements with the table tag
		if ($this->settings['display'] == 'tables') {$formHtml = "\n\n" . '<table summary="Online submission form">' . $formHtml . "\n</table>";}
		
		# Add in any hidden HTML, between the </table> and </form> tags (this also works for the template, where it is stuck on afterwards
		$formHtml .= $hiddenHtml;
		
		# Add the form button, either at the start or end as required
		#!# submit_x and submit_y should be treated as a reserved word when using submitButtonAccesskey (i.e. generating type="image")
		#!# Accesskey string needs to detect the user's platform and browser type, as Shift+Alt is not always correct, and on a Mac does not exist
		if (!$this->formDisabled) {
			$submitButtonText = $this->settings['submitButtonText'] . ((!empty ($this->settings['submitButtonAccesskey']) && $this->settings['submitButtonAccesskeyString']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['submitButtonAccesskey'] . ']' : '');
			$formButtonHtml = '<input type="' . (!$this->settings['submitButtonImage'] ? 'submit' : "image\" src=\"{$this->settings['submitButtonImage']}\" name=\"submit\" alt=\"{$submitButtonText}") . '" value="' . $submitButtonText . '"' . (!empty ($this->settings['submitButtonAccesskey']) ? " accesskey=\"{$this->settings['submitButtonAccesskey']}\""  : '') . (is_numeric ($this->settings['submitButtonTabindex']) ? " tabindex=\"{$this->settings['submitButtonTabindex']}\"" : '') . ' class="' . ($this->settings['submitButtonClass']) . (isSet ($this->multipleSubmitReturnHandlerClass) ? " {$this->multipleSubmitReturnHandlerClass}" : '') . '" />';
			if ($this->settings['refreshButton']) {
				$refreshButtonText = $this->settings['refreshButtonText'] . (!empty ($this->settings['refreshButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['refreshButtonAccesskey'] . ']' : '');
				#!# Need to deny __refresh as a reserved form name
				$refreshButtonHtml = '<input name="__refresh" type="' . (!$this->settings['refreshButtonImage'] ? 'submit' : "image\" src=\"{$this->settings['refreshButtonImage']}\" name=\"submit\" alt=\"{$refreshButtonText}") . '" value="' . $refreshButtonText . '"' . (!empty ($this->settings['refreshButtonAccesskey']) ? " accesskey=\"{$this->settings['refreshButtonAccesskey']}\""  : '') . (is_numeric ($this->settings['refreshButtonTabindex']) ? " tabindex=\"{$this->settings['refreshButtonTabindex']}\"" : '') . ' class="button" />';
			}
			if ($this->settings['display'] == 'template') {
				$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['SUBMIT'], $formButtonHtml, $formHtml);
				if ($this->settings['refreshButton']) {
					$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REFRESH'], $refreshButtonHtml, $formHtml);
				}
			} else {
				$formButtonHtml = "\n\n" . '<p class="submit">' . $formButtonHtml . '</p>';
				if ($this->settings['refreshButton']) {
					$refreshButtonHtml = "\n\n" . '<p class="refresh">' . $refreshButtonHtml . '</p>';
				}
				switch ($this->settings['submitButtonPosition']) {
					case 'start':
						$formHtml = $formButtonHtml . $formHtml;
						break;
					case 'both':
						$formHtml = $formButtonHtml . $formHtml . $formButtonHtml;
						break;
					case 'end':	// Fall-through
					default:
						$formHtml = $formHtml . $formButtonHtml;
				}
				if ($this->settings['refreshButton']) {
					$formHtml = ((!$this->settings['refreshButtonAtEnd']) ? ($refreshButtonHtml . $formHtml) : ($formHtml . $refreshButtonHtml));
				}
			}
		}
		
		# Add in the required field indicator for the template version
		if (($this->settings['display'] == 'template') && ($this->settings['requiredFieldIndicator'])) {
			$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['REQUIRED'], $requiredFieldIndicatorHtml, $formHtml);
		}
		
		# Add in a reset button if wanted
		if (!$this->formDisabled) {
			if ($this->settings['resetButton']) {
				$resetButtonHtml = '<input value="' . $this->settings['resetButtonText'] . (!empty ($this->settings['resetButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['resetButtonAccesskey'] . ']" accesskey="' . $this->settings['resetButtonAccesskey'] : '') . '" type="reset" class="resetbutton"' . (is_numeric ($this->settings['resetButtonTabindex']) ? " tabindex=\"{$this->settings['resetButtonTabindex']}\"" : '') . ' />';
				if ($this->settings['display'] == 'template') {
					$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['RESET'], $resetButtonHtml, $formHtml);
				} else {
					$formHtml .= "\n" . '<p class="reset">' . $resetButtonHtml . '</p>';
				}
			}
		}
		
		# Add in a save button if wanted
		if (!$this->formDisabled) {
			if ($this->settings['saveButton']) {
				$saveButtonHtml = '<input name="__save" value="' . $this->settings['saveButtonText'] . (!empty ($this->settings['saveButtonAccesskey']) ? '&nbsp; &nbsp;[Shift+Alt+' . $this->settings['saveButtonAccesskey'] . ']" accesskey="' . $this->settings['saveButtonAccesskey'] : '') . '" type="submit"' . (is_numeric ($this->settings['saveButtonTabindex']) ? " tabindex=\"{$this->settings['saveButtonTabindex']}\"" : '') . ' />';
				if ($this->settings['display'] == 'template') {
					$formHtml = str_replace ($this->displayTemplateElementReplacementsSpecials['SAVE'], $saveButtonHtml, $formHtml);
				} else {
					$formHtml .= "\n" . '<p class="save">' . $saveButtonHtml . '</p>';
				}
			}
		}
		
		# Add in the form HTML
		$html .= $formHtml;
		
		# Continue the HTML
		$html .= "\n\n" . '</form>';
		
		# Add the required field indicator display message if required
		if (($this->settings['display'] != 'template') && ($this->settings['requiredFieldIndicator'] === 'bottom') || ($this->settings['requiredFieldIndicator'] === true)) {$html .= $requiredFieldIndicatorHtml;}
		
		# If the form is disabled, disable the elements
		#!# This method is hacky but is the only way without extensive refactoring to enable elements to re-render their HTML retrospectively
		if ($this->formDisabled) {
			$html = preg_replace ('/<(input|select|textarea) /', '<\1 disabled="disabled" ', $html);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to add javascript/jQuery code
	function loadJavascriptCode ()
	{
		# End if no jQuery use
		if (!$this->jQueryLibraries && !$this->jQueryCode && !$this->javascriptCode) {return false;}
		
		# Start the HTML
		$html  = '';
		
		# Add the library if required
		if ($this->jQueryLibraries || $this->jQueryCode) {
			if ($this->settings['jQuery']) {
				if ($this->settings['jQuery'] === true) {	// If not a URL, use the default, respecting HTTP/HTTPS to avoid mixed content warnings
					$this->settings['jQuery'] = '//code.jquery.com/jquery.min.js';
				}
				if ($this->settings['jQuery']) {
					$html .= "\n<script type=\"text/javascript\" src=\"{$this->settings['jQuery']}\"></script>";
				}
			}
		}
		
		# Add plugin libraries
		foreach ($this->jQueryLibraries as $key => $htmlCode) {
			$html .= "\n" . $htmlCode;
		}
		
		# Add each client function
		if ($this->jQueryCode || $this->javascriptCode) {
			$html .= "\n<script type=\"text/javascript\">";
			$html .= "\n\t" . '$(function() {';
			foreach ($this->jQueryCode as $key => $jsCode) {
				$html .= "\n" . $jsCode;
			}
			foreach ($this->javascriptCode as $key => $jsCode) {
				$html .= "\n" . $jsCode;
			}
			$html .= "\n\t" . '});';
			$html .= "\n</script>\n\n";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	/**
	 * Function to prepare a problems list
	 * @access private
	 */
	#!# Make these types generic rather than hard-coded
	function problemsList ($problems)
	{
		# Flatten the multi-level array of problems, starting first with the generic, top-level problems if any exist
		$problemsList = array ();
		if (isSet ($problems['generic'])) {
			foreach ($problems['generic'] as $name => $genericProblem) {
				$problemsList[] = $genericProblem;
			}
		}
		
		# Next, flatten the element-based problems, if any exist, starting with looping through each of the problems
		if (isSet ($problems['elements'])) {
			foreach ($problems['elements'] as $name => $elementProblems) {
				
				# Start an array of flattened element problems
				$currentElementProblemsList = array ();
				
				# Add each problem to the flattened array
				foreach ($elementProblems as $problemKey => $problemText) {
					$currentElementProblemsList[] = $problemText;
				}
				
				# If an item contains two or more errors, compile them and prefix them with introductory text
				$totalElementProblems = count ($elementProblems);
				$introductoryText = 'In the <strong>' . ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name)) . '</strong> section, ' . (($totalElementProblems > 1) ? "$totalElementProblems problems were" : 'a problem was') . ' found:';
				if ($totalElementProblems > 1) {
					$problemsList[] = application::showUserErrors ($currentElementProblemsList, $parentTabLevel = 2, $introductoryText, $nested = true);
				} else {
					
					# If there's just a single error for this element, carry the item through
					#!# Need to lcfirst the $problemtext here
					$problemsList[] = $introductoryText . ' ' . $problemText;
				}
			}
		}
		
		# Next the group if any exist
		if (isSet ($problems['group'])) {
			foreach ($problems['group'] as $name => $groupProblem) {
				$problemsList[] = $groupProblem;
			}
		}
		
		# Next the external problems if any exist
		if (isSet ($problems['external'])) {
			foreach ($problems['external'] as $name => $groupProblem) {
				$problemsList[] = $groupProblem;
			}
		}
		
		# Return a constructed list of problems (or empty string)
		return $html = (($this->formPosted && $problemsList) ? application::showUserErrors ($problemsList, $parentTabLevel = 0, ($this->settings['warningMessage'] ? $this->settings['warningMessage'] : (count ($problemsList) > 1 ? 'Various problems were' : 'A problem was') . ' found with the form information you submitted, as detailed below; please make the necessary corrections and re-submit the form:'), false, $this->settings['errorsCssClass']) : '');
	}
	
	
	/**
	 * Function to prepare completed form data; the data is assembled into a compiled version (e.g. in the case of checkboxes, separated by commas) and a component version (which is an array); in the case of scalars, the component version is set to be the same as the compiled version
	 * @access private
	 */
	function prepareData ()
	{
		# Loop through each element, whether submitted or not (otherwise gaps may be left, e.g. in the CSV writing)
		foreach ($this->elements as $name => $elementAttributes) {
			
			# Discard if required; note that all the processing will have been done on each element; this is useful for e.g. a Captcha, where the submitted data merely needs to validate but is not used
			if ($elementAttributes['discard']) {continue;}
			
			# Add submitted items
			if ($this->elements[$name]['data']) {
				$outputData[$name] = $this->elements[$name]['data'];
			}
		}
		
		# Return the data
		return $outputData;
	}
	
	
	/**
	 * Function to check for problems
	 * @access private
	 */
	#!# The whole problems area needs refactoring
	#!# Replace external access with new function returning bool hasElementProblems ()
	public function getElementProblems ()
	{
		# If the form is not posted, end here
		if (!$this->formPosted) {return false;}
		
		# Loop through each created form element (irrespective of whether it has been submitted or not), run checks for problems, and deal with multi-dimensional arrays
		foreach ($this->elements as $name => $elementAttributes) {
			
			# Check for specific problems which have been assigned in the per-element checks
			if ($this->elements[$name]['problems']) {
				
				# Assign the problem to the list of problems
				$this->elementProblems['elements'][$name] = $this->elements[$name]['problems'];
			}
			
			# Construct a list of required but incomplete fields
			if ($this->elements[$name]['requiredButEmpty']) {
				if (!isSet ($this->elements[$name]['requiredButEmptyNoMessage'])) {
					$incompleteFields[] = ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name));
				}
			}
			
			#!# Do checks on hidden fields
		}
		
		# If there are any incomplete fields, add it to the start of the problems array
		if (!isSet ($this->elementProblems['generic'])) {$this->elementProblems['generic'] = array ();}
		if (isSet ($incompleteFields)) {
			$this->elementProblems['generic']['incompleteFields'] = "You need to enter a value for the following required " . ((count ($incompleteFields) == 1) ? 'field' : 'fields') . ': <strong>' . implode ('</strong>, <strong>', $incompleteFields) . '</strong>.';
		}
		
		# Run checks for multiple validation fields
		#!# Failures in group validation will not appear in the same order as the widgets themselves
		$this->elementProblems['group'] = $this->_groupValidationChecks ();
		
		# Add in externally-supplied problems (where the calling application has inserted data checked against ->getUnfinalisedData(), which by default is an empty array)
		$this->elementProblems['external'] = $this->externalProblems;
		
		# If there are no fields incomplete, remove the requirement to force upload(s) reselection
		$genericProblemsOtherThanUpload = ((count ($this->elementProblems['generic']) > 1) || ($this->elementProblems['generic'] && !isSet ($this->elementProblems['generic']['reselectUploads'])));
		#!# Make $this->elementProblems['elements'] always exist to remove this inconsistency
		if (!$genericProblemsOtherThanUpload && !isSet ($this->elementProblems['elements']) && !$this->elementProblems['group'] && !$this->elementProblems['external']) {
			if (isSet ($this->elementProblems['generic']['reselectUploads'])) {
				unset ($this->elementProblems['generic']['reselectUploads']);
			}
		}
		
		# Return a boolean of whether problems have been found or not
		#!# This needs to be made more generic, by looping through the first-level arrays to see if any second-level items exist; then new types of problems need not be registered here
		#!# Again, make $this->elementProblems['elements'] always exist to remove the isSet/!empty inconsistency
		return $problemsFound = (!empty ($this->elementProblems['generic'])) || (isSet ($this->elementProblems['elements']) || (!empty ($this->elementProblems['group'])) || (!empty ($this->elementProblems['external'])));
	}
	
	
	# Function to register a group validation check
	function validation ($type, $fields, $parameter = false)
	{
		# Register the (now validated) validation rule
		$this->validationRules[] = array ('type' => $type, 'fields' => $fields, 'parameter' => $parameter);
	}
	
	
	# Function to check the group validations are syntactically correct
	function _checkGroupValidations ()
	{
		# End if no rules
		if (!$this->validationRules) {return;}
		
		# Define the supported validation types and the error message (including a placeholder) which should appear if the check fails
		$this->validationTypes = array (
			'different' => 'The values for each of the sections %fields must be unique.',
			'same'		=> 'The values for each of the sections %fields must be the same.',
			'either'	=> 'One of the sections %fields must be completed.',
			'all'		=> 'The values for all of the sections %fields must be completed if one of them is.',
			'master'	=> 'The value for the field %fields must be completed if any of the other %parameter fields are completed.',
			'total'		=> 'In the sections %fields, the total number of items selected must be exactly %parameter.',
			'details'	=> 'In the sections %fields, no details were submitted.',
		);
		
		# Loop through each registered rule to check for setup problems (but do not perform the validations themselves)
		foreach ($this->validationRules as $validationRule) {
			
			# Ensure the validation is a valid type
			if (!array_key_exists ($validationRule['type'], $this->validationTypes)) {
//				$this->formSetupErrors['validationTypeInvalid'] = "The group validation type '<strong>{$validationRule['type']}</strong>' is not a supported type.";
				return;
			}
			
			# Ensure the fields are an array and that there are at least two
			if (!is_array ($validationRule['fields']) || (is_array ($validationRule['fields']) && (count ($validationRule['fields']) < 2))) {
				$this->formSetupErrors['validationFieldsInvalid'] = 'An array of at least two fields must be specified for a group validation rule.';
				return;
			}
			
			# Ensure the specified fields exist
			if ($missing = array_diff ($validationRule['fields'], array_keys ($this->elements))) {
				$this->formSetupErrors['validationFieldsAbsent'] = 'The field ' . (count ($missing) > 1 ? 'names' : 'name') . " '<strong>" . implode ("</strong>', '<strong>", $missing) . "</strong>' " . (count ($missing) > 1 ? 'names' : 'was') . " specified for a validation rule, but no such " . (count ($missing) > 1 ? 'elements exist' : 'element exists') . '.';
			}
			
			# Ensure that the total field has a third parameter and that all the fields being request supply a 'total' parameter in $this->elements
			if ($validationRule['type'] == 'total') {
				if (!is_numeric ($validationRule['parameter'])) {
					$this->formSetupErrors['validationTotalParameterNonNumeric'] = "The 'maximum' validation rule requires a third, numeric parameter.";
				} else {
					foreach ($validationRule['fields'] as $field) {
						if (isSet ($this->elements[$field]) && !array_key_exists ('total', $this->elements[$field])) {
							$this->formSetupErrors['validationTotalFieldMismatch'] = "Not all the fields selected for the 'maximum' validation rule support totals";
						}
					}
				}
			}
		}
	}
	
	
	# Function to register external problems as registered by the calling application
	function registerProblem ($key, $message, $highlightFieldsWarning = false /* false, or string, or array */)
	{
		# Convert the optional parameter for highlighting named field(s) into an array (whether empty or otherwise)
		if ($highlightFieldsWarning) {
			$fields = application::ensureArray ($highlightFieldsWarning);
			foreach ($fields as $field) {
				if (isSet ($this->elements[$field])) {	// Should really throw a form setup error, but this function is only run dynamically in runtime, so the programmer might not notice
					$this->elements[$field]['requiredButEmpty'] = true;
					$this->elements[$field]['requiredButEmptyNoMessage'] = true;	// Stop the message that the field is empty, since we are registering a more specific message below
				}
			}
		}
		
		# Register the problem
		$this->externalProblems[$key] = $message;
	}
	
	
	# Function to run group validation checks
	#!# Refactor this so that each check is its own function
	function _groupValidationChecks ()
	{
		# Don't do any processing if no rules exist
		if (!$this->validationRules) {return array ();}
		
		# Perform each validation and build an array of problems
		$problems = array ();
		foreach ($this->validationRules as $index => $rule) {
			
			# Get the value of each field, using the presented value unless the widget specifies the value to be used
			$values = array ();
			foreach ($rule['fields'] as $name) {
				$values[$name] = ((isSet ($this->elements[$name]['groupValidation']) && $this->elements[$name]['groupValidation']) ? $this->elements[$name]['data'][$this->elements[$name]['groupValidation']] : $this->elements[$name]['data']['presented']);
			}
			
			# Make an array of non-empty values for use with the 'different' check
			$nonEmptyValues = array ();
			$emptyValues = array ();
			foreach ($values as $name => $value) {
				if (empty ($value)) {
					$emptyValues[$name] = $value;
				} else {
					$nonEmptyValues[$name] = $value;
				}
			}
			
			# For the 'total' check, get the totals from each group
			$total = 0;
			if ($rule['type'] == 'total') {
				foreach ($rule['fields'] as $field) {
					$total += $this->elements[$field]['total'];
				}
			}
			
			# For the 'master' check, we are going to need the name of the master field which will be checked against
			if ($rule['type'] == 'master') {
				foreach ($rule['fields'] as $field) {
					$firstField = $field;
					break;
				}
				$rule['parameter'] = count ($rule['fields']) - 1;
				$rule['fields'] = array ($field);	// Overwrite for the purposes of the error message
			}
			
			# Check the rule
			#!# Ideally refactor to avoid duplicating the same list of cases specified as $this->validationTypes
			$validationFailed = false;
			if (
				   ( ($rule['type'] == 'different') && ($nonEmptyValues) && (count ($nonEmptyValues) != count (array_unique ($nonEmptyValues))) )
				|| ( ($rule['type'] == 'same')      && ((count ($values) > 1) && count (array_unique ($values)) != 1) )
				|| ( ($rule['type'] == 'either')    && (application::allArrayElementsEmpty ($values)) )
				|| ( ($rule['type'] == 'all')       && $nonEmptyValues && $emptyValues )
				|| ( ($rule['type'] == 'total')     && ($total != $rule['parameter']) )
				|| ( ($rule['type'] == 'master')    && $nonEmptyValues && array_key_exists ($firstField, $emptyValues) )
				|| ( ($rule['type'] == 'details')   && $nonEmptyValues && $this->elements[$rule['fields'][0]]['data']['presented'] == 'Yes' && !strlen ($this->elements[$rule['fields'][1]]['data']['presented']) )
			) {
				$problems['validationFailed' . ucfirst ($rule['type']) . $index] = str_replace (array ('%fields', '%parameter'), array ($this->_fieldListString ($rule['fields']), $rule['parameter']), $this->validationTypes[$rule['type']]);
				$validationFailed = true;
			}
			
			# Highlight empty fields if validation failed
			#!# Currently only implemented for 'all'/'details' - this must have all highlighted (others are more selective)
			if ($validationFailed) {
				if ($rule['type'] == 'all') {
					foreach (array_keys ($emptyValues) as $emptyField) {
						$this->elements[$emptyField]['requiredButEmpty'] = true;
					}
				}
				if ($rule['type'] == 'details') {
					$emptyField = $rule['fields'][1];
					$this->elements[$emptyField]['requiredButEmpty'] = true;
				}
			}
		}
		
		# Return the problems
		return $problems;
	}
	
	
	# Function to construct a field list string
	function _fieldListString ($fields)
	{
		# Loop through each field name
		foreach ($fields as $name) {
			$names[$name] = ($this->elements[$name]['title'] != '' ? $this->elements[$name]['title'] : ucfirst ($name));
		}
		
		# Construct the list
		$fieldsList = '<strong>' . implode ('</strong> &amp; <strong>', $names) . '</strong>';
		
		# Return the list
		return $fieldsList;
	}
	
	
	/**
	 * Function to output the data
	 * @access private
	 */
	function outputData ($outputType)
	{
		# Assign the presented data according to the output type
		foreach ($this->outputData as $name => $data) {
			$presentedData[$name] = $data[$this->elements[$name]['output'][$outputType]];
		}
		
		# For the processing type, return the results as a raw, uncompiled data array
		if ($outputType == 'processing') {
			return $this->outputDataProcessing ($presentedData);
		}
		
		# Otherwise output the data
		$outputFunction = 'outputData' . ucfirst ($outputType);
		$this->$outputFunction ($presentedData);
	}
	
	
	/**
	 * Set the presentation format for each element
	 * @access private
	 */
	function mergeInPresentationDefaults ()
	{
		# Get the presentation matrix
		$presentationDefaults = $this->presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false);
		
		# Loop through each element
		foreach ($this->elements as $element => $attributes) {
			
			# Skip if the presentation matrix has no specification for the element (only heading should not do so)
			if (!isSet ($presentationDefaults[$attributes['type']])) {continue;}
			
			# Assign the defaults on a per-element basis
			$defaults = $presentationDefaults[$attributes['type']];
			
			# Slightly hacky special case: for a select type in multiple mode, replace in the defaults the multiple output format instead
			if (($attributes['type'] == 'select') && ($attributes['multiple'])) {
				foreach ($defaults as $outputType => $outputFormat) {
					if (preg_match ("/^([a-z]+) \[when in 'multiple' mode\]$/", $outputType, $matches)) {
						$replacementType = $matches[1];
						$defaults[$replacementType] = $defaults[$outputType];
						unset ($defaults[$outputType]);
					}
				}
			}
			
			# Merge the setup-assigned output formats over the defaults in the presentation matrix
			$this->elements[$element]['output'] = array_merge ($defaults, $attributes['output']);
		}
	}
	
	
	/**
	 * Define presentation output formats
	 * @access private
	 */
	function presentationDefaults ($returnFullAvailabilityArray = false, $includeDescriptions = false)
	{
		# NOTE: Order is: presented -> compiled takes presented if not defined -> rawcomponents takes compiled if not defined
		
		# Define the default presentation output formats
		$presentationDefaults = array (
			
			'checkboxes' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'String of checked items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
					'special-setdatatype'		=> 'Chosen items only, listed comma separated with no quote marks',
				),
				'file'				=> array ('rawcomponents', 'compiled', 'presented'),
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented', 'special-setdatatype'),
				'database'			=> array ('compiled'),
			),
			
			'datetime' => array (
				'_descriptions' => array (
					'rawcomponents'	=> "Array containing 'time', 'day', 'month', 'year'",
					'compiled'		=> 'SQL format string of submitted data',
					'presented'		=> 'Submitted data as a human-readable string',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				'email'				=> array ('presented', 'rawcomponents', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'email' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
				),
				'file'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'email'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'rawcomponents', 'compiled'
				'screen'			=> array ('presented'), #, 'rawcomponents', 'compiled'
				'processing'		=> array ('presented'), #, 'rawcomponents', 'compiled'
				'database'			=> array ('presented'),
			),
			
			# heading:
			# Never any output for headings
			
			'hidden' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'The raw array',
					'presented'		=> 'An empty string',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'rawcomponents'),
				'screen'			=> array ('presented', 'rawcomponents'),
				'processing'		=> array ('rawcomponents', 'presented'),
				'database'			=> array ('rawcomponents'),
			),
			
			'input' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
				),
				'file'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'email'				=> array ('presented'), #, 'rawcomponents', 'compiled'
				'confirmationEmail'	=> array ('presented'), #, 'rawcomponents', 'compiled'
				'screen'			=> array ('presented'), #, 'rawcomponents', 'compiled'
				'processing'		=> array ('presented'), #, 'rawcomponents', 'compiled'
				'database'			=> array ('presented'),
			),
			
			// url, tel, etc., are copied below after this block
			
			'password' => array (
				'_descriptions' => array (
					'compiled'		=> 'Show as unaltered string',
					'presented'		=> 'Each character of string replaced with an asterisk (*)',
				),
				'file'				=> array ('compiled', 'presented'), #, 'rawcomponents'
				'email'				=> array ('presented', 'compiled'), #, 'rawcomponents'
				'confirmationEmail'	=> array ('presented', 'compiled'), #, 'rawcomponents'	// Compiled allowed even though this means the administrator is allowing them to get their password in plain text via e-mail
				'screen'			=> array ('presented', 'compiled'), #, 'rawcomponents'
				'processing'		=> array ('compiled'), #, 'rawcomponents'
				'database'			=> array ('presented'),
			),
			
			'radiobuttons' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'The (single) chosen item, if any',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				#!# Probably e-mail should be 'presented' to avoid the need for "'output' => array ('email' => 'presented')"  to be added to such fields
				'email'				=> array ('compiled', 'rawcomponents', 'presented'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('compiled', 'rawcomponents', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'richtext' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string, with HTML code visible',
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('presented'),
				'database'			=> array ('presented'),
			),
			
			'select' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as itemName => boolean true/false',
					'compiled'		=> 'String of selected items only as selectedItemName1\n,selectedItemName2\n,selectedItemName3',
					'presented'		=> 'As compiled, but in the case of an associative array of values being supplied as selectable items, the visible text version used instead of the actual value',
				),
				'file'				=> array ('compiled', 'rawcomponents', 'presented'),
				"file [when in 'multiple' mode]"		=> array ('rawcomponents', 'compiled', 'presented'),
				'email'				=> array ('compiled', 'presented', 'rawcomponents'),
				'confirmationEmail'	=> array ('presented', 'compiled'),
				'screen'			=> array ('presented', 'compiled'),
				'processing'		=> array ('compiled', 'presented', 'rawcomponents'),
				"processing [when in 'multiple' mode]"	=> array ('rawcomponents', 'compiled', 'presented'),
				'database'			=> array ('compiled'),
			),
			
			'textarea' => array (
				'_descriptions' => array (
					'presented'		=> 'Show as unaltered string',
					'rawcomponents'	=> "
						Depends on 'mode' attribute:
						<ul>
							<li>unspecified/default ('normal'): Unaltered string</li>
							<li>'lines': An array with every line being assigned as linenumber => string</li>
							<li>'coordinates': An array with every line being assigned as linenumber => string</li>
						</ul>
					",
				),
				'file'				=> array ('presented'),
				'email'				=> array ('presented'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('rawcomponents', 'presented'),
				'database'			=> array ('presented'),
			),
			
			'upload' => array (
				'_descriptions' => array (
					'rawcomponents'	=> 'An array with every defined element being assigned as autonumber => filename; this will not show files unzipped but only list the main file with a string description of unzipped files for each main file',
					'compiled'		=> 'An array with every successful element being assigned as filename => attributes; this will include any files automatically unzipped if that was requested',
					'presented'		=> 'Submitted files (and failed uploads) as a human-readable string with the original filenames in brackets',
				),
				'file'				=> array ('rawcomponents', 'presented'),
				'email'				=> array ('presented', 'compiled'),
				'confirmationEmail'	=> array ('presented'),
				'screen'			=> array ('presented'),
				'processing'		=> array ('rawcomponents', 'compiled', 'presented'),
				'database'			=> array ('presented'),
			),
		);
		
		# Copy types to avoid re-stating them
		#!# This is weak code as it is liable to become inconsistent
		$copyInputTypes = array ('url', 'tel', 'search', 'number', 'number', 'range', 'color');
		foreach ($copyInputTypes as $copyInputType) {
			$presentationDefaults[$copyInputType] = $presentationDefaults['input'];
		}
		
		# If the array should return only the defaults rather than full availability, remove the non-defaults
		if (!$returnFullAvailabilityArray) {
			foreach ($presentationDefaults as $type => $attributes) {
				foreach ($attributes as $outputFormat => $availableValues) {
					
					# Don't do anything with descriptions
					if ($outputFormat == '_descriptions') {continue;}
					
					# Overwrite the attributes array with the first item in the array as a non-array value
					$presentationDefaults[$type][$outputFormat] = $availableValues[0];
				}
			}
		}
		
		# If descriptions are not required, remove these from the array
		if (!$includeDescriptions) {
			foreach ($presentationDefaults as $type => $attributes) {
				unset ($presentationDefaults[$type]['_descriptions']);
			}
		}
		
		# Return the defaults matrix
		return $presentationDefaults;
	}
	
	
	/**
	 * Show presentation output formats
	 * @access public
	 */
	function displayPresentationMatrix ()
	{
		# Get the presentation matrix
		$presentationMatrix = $this->presentationDefaults ($returnFullAvailabilityArray = true, $includeDescriptions = true);
		
		# Provide alternative names
		$tableHeadingSubstitutions = array (
			'file' => 'CSV file output',
			'email' => 'E-mail output',
			'confirmationEmail' => 'Confirmation e-mail',
			'screen' => 'Output to screen',
			'processing' => 'Internal processing as an array',
		);
		
		# Build up the HTML, starting with the title and an introduction
		$html  = "\n" . '<h1>Output types and defaults</h1>';
		$html .= "\n\n" . "<p>The following is a list of the supported configurations for how the results of a form are sent as output to the different output formats (CSV file, e-mail, a sender's confirmation e-mail, screen and for further internal processing (i.e. embedded mode) by another program. Items are listed by widget type.</p>";
		$html .= "\n\n" . "<p>Each widget type shows a bullet-pointed list of the shortcut names to be used, (rawcomponents/compiled/presented) and what using each of those will produce in practice.</p>";
		$html .= "\n\n" . "<p>There then follows a chart of the default types for each output format and the other types supported. In most cases, you should find the default gives the best option.</p>";
		$html .= "\n\n" . "<p>(Note: in the few cases where an array-type output is assigned for output to an e-mail or file, the array is converted to a text representation of the array.)</p>";
		
		# Add a jumplist to the widget types
		$html .= "\n" . '<ul>';
		foreach ($presentationMatrix as $type => $attributes) {
			$html .= "\n\t" . "<li><a href=\"#$type\">$type</a></li>";
		}
		$html .= "\n" . '</ul>';
		
		# Add output types each widget type
		foreach ($presentationMatrix as $type => $attributes) {
			$html .= "\n\n" . "<h2 id=\"$type\">" . ucfirst ($type) . '</h2>';
			$html .= "\n" . '<h3>Output types available and defaults</h3>';
			$html .= "\n" . '<ul>';
			foreach ($attributes['_descriptions'] as $descriptor => $description) {
				$html .= "\n\t" . "<li><strong>$descriptor</strong>: " . $description . "</li>";
			}
			$html .= "\n" . '</ul>';
			
			# Start the table of presentation formats, laid out in a table with headings
			$html .= "\n" . '<table class="documentation">';
			$html .= "\n\t" . '<tr>';
			$html .= "\n\t\t" . '<th class="displayformat">Display format</th>';
			$html .= "\n\t\t" . '<th>Default output type</th>';
			$html .= "\n\t\t" . '<th>Others permissible</th>';
			$html .= "\n\t" . '</tr>';
			
			# Each presentation format
			unset ($attributes['_descriptions']);
			foreach ($attributes as $displayFormat => $available) {
				$default = $available[0];
				unset ($available[0]);
				sort ($available);
				$others = implode (', ', $available);
				
				$html .= "\n\t" . '<tr>';
				$html .= "\n\t\t" . "<td class=\"displayformat\"><em>$displayFormat</em></td>";
				$html .= "\n\t\t" . "<td class=\"defaultdisplayformat\"><strong>$default</strong><!-- [" . htmlspecialchars ($presentationMatrix[$type]['_descriptions'][$default]) . ']--></td>';
				$html .= "\n\t\t" . "<td>$others</td>";
				$html .= "\n\t" . '</tr>';
			}
			$html .= "\n" . '</table>';
		}
		
		# Show the result
		$this->html .= $html;
	}
	
	
	/**
	 * Function to return the output data as an array
	 * @access private
	 */
	function outputDataProcessing ($presentedData)
	{
		# Escape the output if necessary
		if ($this->settings['escapeOutput']) {
			
			# Set the default escaping type to '
			if ($this->settings['escapeOutput'] === true) {
				$this->settings['escapeOutput'] = $this->escapeCharacter;
			}
			
			# Loop through the data, whether scalar or one-level array
			$presentedData = $this->escapeOutputIterative ($presentedData, $this->settings['escapeOutput']);
		}
		
		# Return the raw, uncompiled data
		return $presentedData;
	}
	
	
	# Function to perform escaping iteratively
	function escapeOutputIterative ($data, $character)
	{
		# For a scalar, return the escaped value
		if (!is_array ($data)) {
			$data = addslashes ($data);
			#!# Consider adding $data = str_replace ('"', '\\"' . $character, $data); when character is a " - needs further research
			
		} else {
			
			# For an array value, iterate instead
			foreach ($data as $key => $value) {
				$data[$key] = $this->escapeOutputIterative ($value, $character);
			}
		}
		
		# Finally, return the escaped data structure
		return $data;
	}
	
	
	/**
	 * Function to display, in a tabular form, the results to the screen
	 * @access private
	 */
	function outputDataScreen ($presentedData)
	{
		# If nothing has been submitted, return the result directly
		if (application::allArrayElementsEmpty ($presentedData)) {
			return $html = "\n\n" . '<p class="success">No information' . ($this->hiddenElementPresent ? ', other than any hidden data, ' : '') . ' was submitted.</p>';
		}
		
		# Introduce the table
		$html  = "\n\n" . '<p class="success">The information submitted is confirmed as:</p>';
		$html .= "\n" . '<table class="results" summary="Table of results">';
		
		# Assemble the HTML, convert newlines to breaks (without a newline in the HTML), tabs to four spaces, and convert HTML entities
		foreach ($presentedData as $name => $data) {
			
			# Remove empty elements from display
			if (empty ($data) && !$this->configureResultScreenShowUnsubmitted) {continue;}
			
			# If the data is an array, convert the data to a printable representation of the array
			if (is_array ($data)) {$data = application::printArray ($data);}
			
			# Compile the HTML
			$html .= "\n\t<tr>";
			$html .= "\n\t\t" . '<td class="key">' . (isSet ($this->elements[$name]['title']) ? $this->elements[$name]['title'] : $name) . ':</td>';
			$html .= "\n\t\t" . '<td class="value' . (empty ($data) ? ' comment' : '') . '">' . (empty ($data) ? ($this->elements[$name]['type'] == 'hidden' ? '(Hidden data submitted)' : '(No data submitted)') : str_replace (array ("\n", "\t"), array ('<br />', str_repeat ('&nbsp;', 4)), htmlspecialchars ($data))) . '</td>';
			$html .= "\n\t</tr>";
		}
		$html .= "\n" . '</table>';
		
		# Show the constructed HTML
		$this->html .= $html;
	}
	
	
	
	/**
	 * Wrapper function to output the data via e-mail
	 * @access private
	 */
	 function outputDataEmail ($presentedData)
	 {
	 	# Pass through
	 	$this->outputDataEmailTypes ($presentedData, 'email', $this->configureResultEmailShowUnsubmitted);
	 }
	 
	 
	/**
	 * Wrapper function to output the data via e-mail
	 * @access private
	 */
	 function outputDataConfirmationEmail ($presentedData)
	 {
	 	# Pass through
	 	$this->outputDataEmailTypes ($presentedData, 'confirmationEmail', $this->configureResultConfirmationEmailShowUnsubmitted);
	 }
	 
	 
	/**
	 * Function to output the data via e-mail for either e-mail type
	 * @access private
	 */
	function outputDataEmailTypes ($presentedData, $outputType, $showUnsubmitted)
	{
		# If, for the confirmation type, a confirmation address has not been assigned, say so and take no further action
		#!# This should be moved up so that a confirmation e-mail widget is a required field
		if ($outputType == 'confirmationEmail') {
			if (empty ($this->configureResultConfirmationEmailRecipient)) {
				$this->html .= "\n\n" . '<p class="error">A confirmation e-mail could not be sent as no address was given.</p>';
				return false;
			}
		}
		
		# Construct the introductory text, including the IP address for the e-mail type
		$introductoryText = ($outputType == 'confirmationEmail' ? $this->settings['confirmationEmailIntroductoryText'] . ($this->settings['confirmationEmailIntroductoryText'] ? "\n\n\n" : '') : $this->settings['emailIntroductoryText'] . ($this->settings['emailIntroductoryText'] ? "\n\n\n" : '')) . ($outputType == 'email' ? 'Below is a submission from the form' :  'Below is a confirmation of' . ($this->settings['user'] ? '' : ' (apparently)') . ' your submission from the form') . " at \n" . $_SERVER['_PAGE_URL'] . "\nmade at " . date ('g:ia, jS F Y') . ($this->settings['ip'] ? ', from the IP address ' . $_SERVER['REMOTE_ADDR'] : '') . ($this->settings['browser'] ? (empty ($_SERVER['HTTP_USER_AGENT']) ? '; no browser type information was supplied.' : ', using the browser "' . $_SERVER['HTTP_USER_AGENT']) . '"' : '') . '.';
		
		# Add an abuse notice if required
		if (($outputType == 'confirmationEmail') && ($this->configureResultConfirmationEmailAbuseNotice)) {$introductoryText .= "\n\n(If it was not you who submitted the form, please report it as abuse to " . $this->configureResultConfirmationEmailAdministrator . ' .)';}
		
		# If nothing has been submitted, return the result directly
		if (application::allArrayElementsEmpty ($presentedData)) {
			$resultLines[] = 'No information' . ($this->hiddenElementPresent ? ', other than any hidden data, ' : '') . ' was submitted.';
		} else {
			
			# Assemble a master array of e-mail text, adding the real element name if it's the result rather than confirmation e-mail type. NB: this used to be using str_pad in order to right-align the names, but it doesn't look all that neat in practice: str_pad ($this->elements[$name]['title'], ($this->longestKeyNameLength ($this->outputData) + 1), ' ', STR_PAD_LEFT) . ': ' . $presentedData
			foreach ($presentedData as $name => $data) {
				
				# Remove empty elements from display
				if (empty ($data) && !$showUnsubmitted) {continue;}
				
				# If the data is an array, convert the data to a printable representation of the array
				if (is_array ($presentedData[$name])) {$presentedData[$name] = application::printArray ($presentedData[$name]);}
				
				# Compile the result line
				$resultLines[] = strip_tags ($this->elements[$name]['title']) . (($this->settings['emailShowFieldnames'] && ($outputType == 'email')) ? " [$name]" : '') . ":\n" . $presentedData[$name];
			}
		}
		
		# Select the relevant recipient; for an e-mail type select either the receipient or the relevant field plus suffix
		if ($outputType == 'email') {
			if (application::validEmail ($this->configureResultEmailRecipient)) {
				$recipient = $this->configureResultEmailRecipient;
			} else {
				#!# Makes the assumption of it always being the compiled item. Is this always true? Check also whether it can be guaranteed earlier that only a single item is going to be selected
				$recipient = $this->outputData[$this->configureResultEmailRecipient]['compiled'] . (!empty ($this->configureResultEmailRecipientSuffix) ? $this->configureResultEmailRecipientSuffix : '');
			}
		} else {
			$recipient = $this->configureResultConfirmationEmailRecipient;
		}
		
		# Add support for "Visible name <name@email>" rather than just "name@email"
		$sender = ($outputType == 'email' ? $this->configureResultEmailAdministrator : $this->configureResultConfirmationEmailAdministrator);
		$emailName = $this->settings['emailName'];
		$emailAddress = $sender;
		if (preg_match ('/^(.+)<([^>]+)>$/', $sender, $matches)) {
			$emailName = $matches[1];
			$emailAddress = $matches[2];
		}
		
		# Define the additional headers
		$additionalHeaders  = "From: {$emailName} <{$emailAddress}>\r\n";
		if (($outputType == 'email') && isSet ($this->configureResultEmailCc)) {$additionalHeaders .= 'Cc: ' . implode (', ', $this->configureResultEmailCc) . "\r\n";}
		
		# Add the reply-to if it is set and is not empty and that it has been completed (e.g. in the case of a non-required field)
		if (isSet ($this->configureResultEmailReplyTo)) {
			if ($this->configureResultEmailReplyTo) {
				if (application::validEmail ($this->outputData[$this->configureResultEmailReplyTo]['presented'])) {
					$additionalHeaders .= 'Reply-To: ' . $this->outputData[$this->configureResultEmailReplyTo]['presented'] . "\r\n";
				}
			}
		}
		
		# Define additional mail headers for compatibility
		$additionalHeaders .= $this->fixMailHeaders ($sender);
		
		# Compile the message text
		$message = wordwrap ($introductoryText . "\n\n\n\n" . implode ("\n\n\n", $resultLines));
		
		# Add attachments if required, to the e-mail type only (not confirmation e-mail type), rewriting the message
		if (($outputType == 'email') && $this->attachments) {
			list ($message, $additionalHeaders) = $this->attachmentsMessage ($message, $additionalHeaders, $introductoryText, $resultLines);
		}
		
		# Determine whether to add plain-text headers
		$includeMimeContentTypeHeaders = ($this->attachments ? false : true);
		
		# Send the e-mail
		#!# Add an @ and a message if sending fails (marking whether the info has been logged in other ways)
		$success = application::utf8Mail (
			$recipient,
			$this->configureResultEmailedSubjectTitle[$outputType],
			$message,
			$additionalHeaders,
			NULL,
			$includeMimeContentTypeHeaders
		);
		
		# Delete the attachments that have been mailed, if required
		if (($outputType == 'email') && $this->attachments && $success) {
			foreach ($this->attachments as $index => $attachment) {
				if ($attachment['_attachmentsDeleteIfMailed']) {
					unlink ($this->attachments[$index]['_directory'] . $this->attachments[$index]['name']);
				}
			}
		}
		
		# Confirm sending (or an error) for the confirmation e-mail type
		if ($outputType == 'confirmationEmail') {
			$this->html .= "\n\n" . '<p class="' . ($success ? 'success' : 'error') . '">' . ($success ? 'A confirmation e-mail has been sent' : 'There was a problem sending a confirmation e-mail') . ' to the address you gave (' . $presentedData[$name] = str_replace ('@', '<span>&#64;</span>', htmlspecialchars ($this->configureResultConfirmationEmailRecipient)) . ').</p>';
		}
	}
	
	
	# Function to add attachments; useful articles explaining the background at www.zend.com/zend/spotlight/sendmimeemailpart1.php and www.hollowearth.co.uk/tech/php/email_attachments.php and http://snipplr.com/view/2686/send-multipart-encoded-mail-with-attachments/
	function attachmentsMessage ($message, $additionalHeaders, $introductoryText, $resultLines)
	{
		# Get the maximum total attachment size, per attachment, converting it to bytes, or explicitly false for no limit
		$attachmentsMaxSize = ($this->settings['attachmentsMaxSize'] ? $this->settings['attachmentsMaxSize'] : ini_get ('upload_max_filesize'));
		$attachmentsMaxSize = application::convertSizeToBytes ($attachmentsMaxSize);
		
		# Read the attachments into memory first, or unset the reference to an unreadable attachment, stopping when the attachment size reaches the total limit
		$totalAttachmentsOriginal = count ($this->attachments);
		$attachmentsTotalSize = 0;	// in bytes
		foreach ($this->attachments as $index => $attachment) {
			$attachmentsTotalSizeProposed = $attachmentsTotalSize + $attachment['size'];
			$attachmentSizeAllowable = ($attachmentsTotalSizeProposed <= $attachmentsMaxSize);
			$filename = $attachment['_directory'] . $attachment['name'];
			if ($attachmentSizeAllowable && file_exists ($filename) && is_readable ($filename)) {
				$this->attachments[$index]['_contents'] = chunk_split (base64_encode (file_get_contents ($filename)));
				$attachmentsTotalSize = $attachmentsTotalSizeProposed;
			} else {
				unset ($this->attachments[$index]);
			}
		}
		
		# Attachment counts
		$totalAttachments = count ($this->attachments);
		$totalAttachmentsDifference = ($totalAttachmentsOriginal - $totalAttachments);
		
		# If attachments were successfully read, add them to the e-mail
		if ($this->attachments) {
			
			# Set the end-of-line
			$eol = "\r\n";
			
			# Set the MIME boundary, a unique string
			$mimeBoundary = '<<<--==+X[' . md5( time ()). ']';
			
			# Add MIME headers
			$additionalHeaders .= "MIME-Version: 1.0" . $eol;
			$additionalHeaders .= "Content-Type: multipart/related; boundary=\"{$mimeBoundary}\"" . $eol;
			
			# Push the attachment stuff into the main message area, starting with the MIME introduction
			$message  = $eol;
			$message .= 'This is a multi-part message in MIME format.' . $eol;
			$message .= $eol;
			$message .= '--' . $mimeBoundary . $eol;
			
			# Main message 'attachment'
			$message .= 'Content-type: text/plain; charset="UTF-8"' . $eol;
			$message .= "Content-Transfer-Encoding: 8bit" . $eol;
			$message .= $eol;
			$message .= wordwrap ($introductoryText . "\n\n" . ($totalAttachments == 1 ? 'There is also an attachment.' : "There are also {$totalAttachments} attachments. Please take care when opening them.") . ($totalAttachmentsDifference ? ' ' . ($totalAttachmentsDifference == 1 ? 'One other submitted file was too large to e-mail, so it has' : "{$totalAttachmentsDifference} other submitted files were too large to e-mail, so they have") . " been saved on the webserver. Please contact the webserver's administrator to retrieve " . ($totalAttachmentsDifference == 1 ? 'it' : 'them') . '.' : '') . "\n\n\n\n" . implode ("\n\n\n", $resultLines)) . "{$eol}{$eol}{$eol}" . $eol;
			$message .= '--' . $mimeBoundary;
			
			# Add each attachment, starting with a mini-header for each
			foreach ($this->attachments as $index => $attachment) {
				$message .= $eol;	// End of previous boundary
				$message .= 'Content-Type: ' . ($attachment['type']) . '; name="' . $attachment['name'] . '"' . $eol;
				$message .= "Content-Transfer-Encoding: base64" . $eol;
				$message .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . $eol;
				$message .= $eol;
				$message .= $attachment['_contents'];
				$message .= $eol;
				$message .= '--' . $mimeBoundary;	// $eol is added in next iteration of loop
			}
			
			# Finish the final boundary
			$message .= '--' . $eol . $eol;
		} else {
			
			# Say that there were no attachments but that the files were saved
			$message  = wordwrap ($introductoryText . "\n\n" . ($totalAttachmentsOriginal == 1 ? 'There is also a submitted file, which was too large to e-mail, so it has' : "There are also {$totalAttachmentsOriginal} submitted files, which were too large to e-mail, so they have") . " been saved on the webserver. Please contact the webserver's administrator to retrieve " . ($totalAttachmentsDifference == 1 ? 'it' : 'them') . '.' . "\n\n\n\n" . implode ("\n\n\n", $resultLines)) . "{$eol}{$eol}{$eol}" . $eol;
		}
		
		# Return the message
		return array ($message, $additionalHeaders);
	}
	
	
	# Function to write additional mailheaders, for when using a mailserver that fails to add key headers
	function fixMailHeaders ($sender)
	{
		# Return an empty string if this functionality is not required
		if (!$this->settings['fixMailHeaders']) {return '';}
		
		# Construct the date, as 'RFC-2822-formatted-date (Timezone)'
		$realDate = date ('r (T)');
		$headers  = "Date: {$realDate}\r\n";
		
		# Construct a message ID, using the application name, defaulting to the current class name
		$applicationName = strtoupper ($this->settings['fixMailHeaders'] === true ? __CLASS__ : $this->settings['fixMailHeaders']);
		$date = date ('YmdHis');
		$randomNumber = mt_rand ();
		if (!isSet ($this->messageIdSequence)) {
			$this->messageIdSequence = 0;
		}
		$this->messageIdSequence++;
		$hostname = $_SERVER['SERVER_NAME'];
		$messageid = "<{$applicationName}.{$date}.{$randomNumber}.{$this->messageIdSequence}@{$hostname}>";
		$headers .= "Message-Id: {$messageid}\r\n";
		
		# Add the return path, being the same as the main sender
		$headers .= "Return-Path: <{$sender}>\r\n";
		
		# Return the headers
		return $headers;
	}
	
	
	/**
	 * Function to write the results to a CSV file
	 * @access private
	 */
	function outputDataFile ($presentedData)
	{
		# Assemble the data into CSV format
		list ($headerLine, $dataLine) = application::arrayToCsv ($presentedData);
		
		# Compile the data, adding in the header if the file doesn't already exist or is empty, and writing a newline after each line
		$data = ((!file_exists ($this->configureResultFileFilename) || filesize ($this->configureResultFileFilename) == 0) ? $headerLine : '') . $dataLine;
		
		# Deal with unicode behaviour
		$unicodeToIso = false;
		$unicodeAddBom = $this->settings['csvBom'];
		
		#!# A check is needed to ensure the file being written to doesn't previously contain headings related to a different configuration
		# Write the data or handle the error
		#!# Replace with file_put_contents when making class PHP5-only
		if (!application::writeDataToFile ($data, $this->configureResultFileFilename, $unicodeToIso, $unicodeAddBom)) {
			$this->html .= "\n\n" . '<p class="error">There was a problem writing the information you submitted to a file. It is likely this problem is temporary - please wait a short while then press the refresh button on your browser.</p>';
		}
	}
	
	
	/**
	 * Function to write the results to a database
	 * @access private
	 */
	#!# Error handling in this function is too basic and needs to be moved higher in the class
	function outputDataDatabase ($presentedData)
	{
		# Connect to the database
		#!# Refactor connectivity as it's now obsolete
		if (! ($this->connection = @mysql_connect ($this->configureResultDatabaseDsn['hostname'], $this->configureResultDatabaseDsn['username'], $this->configureResultDatabaseDsn['password']) && @mysql_select_db ($this->configureResultDatabaseDsn['database']))) {die ('Could not connect: ' . mysql_error());}
#!#		if (!$link = mysql_connect ($this->configureResultDatabaseDsn['hostname'], $this->configureResultDatabaseDsn['username'], $this->configureResultDatabaseDsn['password'])) {die ('Could not connect: ' . mysql_error());}
		mysql_select_db ($this->configureResultDatabaseDsn['database']);
		
		# Design the table schema
		#!# Replace with the output of getDatabaseColumnSpecification()
		$query  = "CREATE TABLE IF NOT EXISTS {$this->configureResultDatabaseTable} (" . "\n";
		$columns[] = '`id` INT AUTO_INCREMENT PRIMARY KEY';
		foreach ($this->elements as $name => $attributes) {
			if (!isSet ($attributes['datatype'])) {continue;}
			$columns[] = "`{$name}` {$attributes['datatype']}" . ($attributes['required'] ? ' NOT NULL' : '') . " COMMENT '{$attributes['title']}'";
		}
		$query .= implode (",\n", $columns);
		$query .= ')';
		
		# Create the table if it doesn't exist
		if (!$result = mysql_query ($query, $link)) {die ('Error creating table: ' . mysql_error ());}
		
		# Compile the result
		$data = array ();
		foreach ($this->elements as $name => $attributes) {
			if (!isSet ($attributes['datatype'])) {continue;}
			$data[$name] = addslashes ((is_array ($this->form[$name]) ? implode ('', $this->form[$name]) : $this->form[$name]));
		}
		#!# Does no data ever arise?
		if ($data) {
			$query  = "INSERT INTO {$this->configureResultDatabaseTable} (" . implode (',', array_keys ($data)) . ") VALUES ('" . implode ("','", array_values ($data)) . "');";
			
			# Add the data
			if (!$result = mysql_query ($query, $link)) {die ('Error inserting data: ' . mysql_error ());}
		}
	}
	
	
	/**
	 * Function to perform the file uploading
	 * @access private
	 */
	function doUploads ()
	{
		# Don't proceed if there are no uploads present
		if (!$this->uploadProperties) {return;}
		
		# Loop through each form element
		foreach ($this->uploadProperties as $name => $arguments) {
			
			# Create arrays of successes and failures
			$successes = array ();
			$failures = array ();
			$actualUploadedFiles = array ();
			
			# Merge the default files list (if there are any such files) into the 'submitted' data, maintaining the indexes but making any new file override the default
			if ($arguments['default']) {
				$this->form[$name] += $arguments['default'];	// += uses right-handed then left-handed - see www.php.net/operators.array , i.e. defaults THEN add on original form[name] (i.e. submitted) value(s)
			}
			
			# Loop through each defined subfield
			for ($subfield = 0; $subfield < $arguments['subfields']; $subfield++) {
				
				# If there is no value for this subfield, skip to the next subfield
				if (!isSet ($this->form[$name][$subfield])) {continue;}
				
				# If the subfield contains merely the default value (i.e. _source = default), then continue
				if (isSet ($this->form[$name][$subfield]['_source']) && ($this->form[$name][$subfield]['_source'] == 'default')) {
					$filename = $this->form[$name][$subfield]['name'];
					$actualUploadedFiles[$arguments['directory'] . $filename] = $this->form[$name][$subfield];
					$successes[$filename]['name'] = $filename;
					$successes[$filename]['namePresented'] = $filename . ' [previously present]';
					continue;
				}
				
				# Get the attributes for this sub-element
				$attributes = $this->form[$name][$subfield];
				
				# Get the file extension preceeded by a dot
				$fileExtension = pathinfo ($attributes['name'], PATHINFO_EXTENSION);
				if (!empty ($fileExtension)) {
					$fileExtension = '.' . $fileExtension;
				}
				
				# Lowercase the extension if necessary
				if ($arguments['lowercaseExtension']) {
					$fileExtension = strtolower ($fileExtension);
				}
				
				# Overwrite the filename if being forced; this always maintains the file extension
				if ($arguments['forcedFileName']) {
					$forcedFilename = $arguments['forcedFileName'];
					if (is_array ($arguments['forcedFileName'])) {
						$forcedFilename = $arguments['forcedFileName'][$subfield];
					}
					
					# If the forced filename is prefixed with a %, look for a field of that name, and use its value (e.g. '%id' will use a forcedFileName that is the value of the submitted 'id' element)
					#!# Currently this doesn't check whether %id is sensible, in terms of a missing/non-required/array-type field (and ideally with a suitable regexp)
					if (preg_match ('/^%(.+)$/', $forcedFilename, $matches)) {
						$matchField = $matches[1];
						if (isSet ($this->elements[$matchField])) {
							if (is_string ($this->form[$matchField])) {		// #!# Support only at present for string types; there needs to be a standard way for elements to give a serialised string representation of their output
								$forcedFilename = $this->form[$matchField];
								$forcedFilename = str_replace (array ('/', '\\'), '_', $forcedFilename);	// Prevent any kind of directory traversal attacks
							}
						}
					}
					
					$attributes['name'] = $forcedFilename . $fileExtension;
				}
				
				# If appendExtension is set, add that on to the filename
				if (strlen ($arguments['appendExtension'])) {
					$attributes['name'] .= $arguments['appendExtension'];
				}
				
				# Create a shortcut for the filename (just the name, not with the path)
				$filename = $attributes['name'];
				
				# If version control is enabled, do checks to prevent overwriting
				if ($arguments['enableVersionControl']) {
					
					# Check whether a file already exists
					if (file_exists ($existingFile = $arguments['directory'] . $filename)) {
						
						# Check whether the existing file has the same checksum as the file being uploaded
						if (md5_file ($existingFile) != md5_file ($attributes['tmp_name'])) {
							
							# Rename the file by appending the date to it
							$timestamp = date ('Ymd-His');
							$renamed = @rename ($existingFile, $existingFile . ".replaced-{$timestamp}");
							
							# If renaming failed, append an explanation+timestamp to the new file
							if (!$renamed) {
								$filename .= '.forRenamingBecauseCannotMoveOld-' . $timestamp;
							}
						}
					}
				}
				
				# Attempt to upload the file to the (now finalised) destination
				$destination = $arguments['directory'] . $filename;
				$uploadedFileMoved = move_uploaded_file ($attributes['tmp_name'], $destination);
				if (!$uploadedFileMoved) {
					
					# Create an array of any failed file uploads
					#!# Not sure what happens if this fails, given that the attributes may not exist
					$failures[$filename] = $attributes;
					
				# Continue if the file upload attempt was successful
				} else {
					
					# Fix up the file permission
					umask (0);
					chmod ($destination, 0664);
					
					# Do MIME Type checks (and by now we can be sure that the extension supplied is in the MIME Types list), doing a mime_content_type() check as the value of $elementValue[$subfield]['type'] is not trustworthy and easily fiddled (changing the file extension is enough to fake this)
					if ($arguments['mime']) {
						$extension = pathinfo ($destination, PATHINFO_EXTENSION);	// Best of methods listed at www.cowburn.info/2008/01/13/get-file-extension-comparison/
						$mimeTypeDeclared = $this->mimeTypes[$extension];
						$mimeTypeActual = mime_content_type ($destination);
						if ($mimeTypeDeclared != $mimeTypeActual) {
							$failures[$filename] = $attributes;
							continue;
						}
					}
					
					# Create an array of any successful file uploads. For security reasons, if the filename is modified to prevent accidental overwrites, the original filename is not modified here
					#!# There needs to be a differential between presented and actual data in cases where a different filename is actually written to the disk
					$successes[$filename] = $attributes;
					
					# Unzip the file if required
					#!# Somehow move this higher up so that the same renaming rules apply
					if ($arguments['unzip'] && substr (strtolower ($filename), -4) == '.zip') {
						if ($unzippedFiles = $this->_unzip ($filename, $arguments['directory'], $deleteAfterUnzipping = true)) {
							$listUnzippedFilesMaximum = (is_numeric ($arguments['unzip']) ? $arguments['unzip'] : $this->settings['listUnzippedFilesMaximum']);
							$totalUnzippedFiles = count ($unzippedFiles);
							
							# Add the directory location into each key name
							$actualUploadedFiles = array ();
							$unzippedFilesListPreRenaming = array ();
							foreach ($unzippedFiles as $unzippedFileName => $unzippedFileAttributes) {
								$unzippedFileLocation = $unzippedFileAttributes['_location'];
								unset ($unzippedFileAttributes['_location']);
								$actualUploadedFiles[$unzippedFileLocation] = $unzippedFileAttributes;
								$actualUploadedFiles[$unzippedFileLocation]['_fromZip'] = $filename;
								$unzippedFilesListPreRenaming[] = (isSet ($unzippedFileAttributes['original']) ? $unzippedFileAttributes['original'] : $unzippedFileAttributes['name']);
							}
							
							# Add the (described) zip file to the list of successes
							$successes[$filename]['name'] .= " [automatically unpacked and containing {$totalUnzippedFiles} " . ($totalUnzippedFiles == 1 ? 'file' : 'files') . ($totalUnzippedFiles > $listUnzippedFilesMaximum ? '' : ': ' . implode ('; ', $unzippedFilesListPreRenaming)) . ']';
						}
					} else {
						# Add the directory location into the key name
						$actualUploadedFiles[$arguments['directory'] . $filename] = $attributes;
					}
				}
			}
			
			# Start results
			$data['presented'] = '';
			$data['compiled'] = array ();
			$filenames = array ();
			$presentedFilenames = array ();
			
			# If there were any succesful uploads, assign the compiled output
			if ($successes) {
				
				# Add each of the files to the master array, appending the location for each
				foreach ($actualUploadedFiles as $actualUploadedFileLocation => $attributes) {
					$data['compiled'][$actualUploadedFileLocation] = $attributes;
				}
				
				# Add each of the files to the master array, appending the location for each
				foreach ($successes as $success => $attributes) {
					$filenames[] = $attributes['name'];
					$presentedFilenames[] = (isSet ($attributes['namePresented']) ? $attributes['namePresented'] : $attributes['name']);
				}
				
				# For the compiled version, give the number of files uploaded and their names
				$totalSuccesses = count ($successes);
				$data['presented'] .= $totalSuccesses . ($totalSuccesses > 1 ? ' files' : ' file') . ' (' . implode ('; ', $presentedFilenames) . ') ' . ($totalSuccesses > 1 ? 'were' : 'was') . ' successfully copied over.';
			}
			
			# If there were any failures, list them also
			if ($failures) {
				$totalFailures = count ($failures);
				$data['presented'] .= ($successes ? ' ' : '') . $totalFailures . ($totalFailures > 1 ? ' files' : ' file') . ' (' . implode ('; ', array_keys ($failures)) . ') unfortunately failed to copy over for some unspecified reason.';
			}
			
			# Pad the rawcomponents array out with empty fields upto the number of created subfields; note this HAS to use the original filenames, because an unzipped version could overrun
			$data['rawcomponents'] = array_pad ($filenames, $arguments['subfields'], false);
			
			# Flatten the rawcomponents array if necessary
			if ($this->elements[$name]['flatten'] && ($this->elements[$name]['subfields'] == 1)) {	// subfields check should not be necessary because it should have been switched off already, but this acts as a safety blanket against offsets
				$data['rawcomponents'] = (isSet ($data['rawcomponents'][0]) ? $data['rawcomponents'][0] : false);
			}
			
			# Assign the output data
			$this->elements[$name]['data'] = $data;
		}
	}
	
	
	# Private function to unzip a file on landing
	function _unzip ($file, $directory, $deleteAfterUnzipping = true, $archiveOverwritableFiles = true)
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
				if (!mkdir ($targetDirectory, $this->settings['directoryPermissions'], true)) {
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
		
		# Delete the submitted file if required
		if ($deleteAfterUnzipping) {
			unlink ($directory . $file);
		}
		
		# Natsort by key
		$unzippedFiles = application::knatsort ($unzippedFiles);
		
		# Sort and return the list of unzipped files
		return $unzippedFiles;
	}
	
	
	# Generic function to generate proxy form widgets from an associated field specification and optional data
	function dataBinding ($suppliedArguments = array ())
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$argumentDefaults = array (
			'database' => NULL,
			'table' => NULL,
			'schema' => array (),		// Directly supply the schema, rather than using the database/table or callback method
			'callback' => array (),		// array (object, dataBindingCallbackMethod), with object containing function dataBindingCallback () returning $fields;
			'attributes' => array (),
			'data' => array (),
			'includeOnly' => array (),
			'exclude' => array (),
			'ordering' => array (),
			'enumRadiobuttons' => false,	// Whether to use radiobuttons for ENUM (true, or set number of value choices up to which they will be used, e.g. 2 means: radiobuttons if <=2 fields but select if >2)
			'enumRadiobuttonsInitialNullText' => array (),	// Whether an initial empty radiobutton should have a label, specified as an array of fieldname=>value
			'int1ToCheckbox' => false,	// Whether an INT/TINYINT/etc(1) field will be converted to a checkbox
			'textAsVarchar' => false,	// Force a TEXT type to be a VARCHAR(255) instead
			'inputAsSearch' => false,	// Set input widgets to be search boxes instead; this is recommended for a multisearch-style interface
			'lookupFunction' => false,
			'simpleJoin' => false,	// Overrides lookupFunction, uses targetId as a join to <database>.target; lookupFunctionParameters can still be used
			'lookupFunctionParameters' => array (),
			'lookupFunctionAppendTemplate' => false,
			'truncate' => 40,
			'size' => $this->settings['size'],		# Visible size (optional; defaults to 60)
			'changeCase' => true,	// Convert 'fieldName' field names in camelCase style to 'Standard text'
			'commentsAsDescription' => false,	// Whether to use column comments for the description field rather than for the title field
			'prefix'	=> false,	// What to prefix all field names with (plus _ implied)
			'prefixTitleSuffix'	=> false,	// What to suffix all field titles with
			'intelligence'	=> false,		// Whether to enable intelligent field setup, e.g. password/file*/photograph* become relevant fields and key fields are handled as non-editable
			'floatChopTrailingZeros' => true,	// Whether to replace trailing zeros at the end of a value where there is a decimal point
			'valuesNamesAutomatic'	=> false,	// For select/radiobuttons/checkboxes, whether to create automatic value names based on the value itself (e.g. 'option1' would become 'Option 1')
			'autocomplete' => false,	// An autocomplete data endpoint URL; if %field is specified, it will be replaced with the fieldname
			'autocompleteOptions' => false,	// Array of options that will be converted to a javascript array - see http://api.jqueryui.com/autocomplete/#options (this is the new plugin)
			'editingUniquenessUniChecking' => true,	// Whether uniqueness checking for editing of a record when a UNI field is found in the database (should be set to false when doing a record clone)
			'notNullFields' => array (),	// Array of elements (or single element as string) that should be treated as NOT NULL, even if the database structure says they are nullable
			'notNullExceptFields' => array (),	// Assume all elements are treated as NOT NULL (even if the database structure says they are nullable), except for these specified elements (or single element as string)
		);
		
		# If a direct schema or callback is supplied, set database and table to be optional
		if ((isSet ($suppliedArguments['schema']) && ($suppliedArguments['schema'])) || (isSet ($suppliedArguments['callback']) && ($suppliedArguments['callback']))) {
			$argumentDefaults['database']	= false;
			$argumentDefaults['table']		= false;
		}
		
		# Merge the arguments
		$arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, 'dataBinding');
		foreach ($arguments as $key => $value) {
			#!# Refactor below to use $arguments array as with other parts of ultimateForm
			$$key = $value;
		}
		
		# If there is a callback, check its existence
		if ($callback) {
			if (!is_callable ($callback)) {
				$this->formSetupErrors['dataBindingCallbackNotCallable'] = 'Data binding has been requested, but the callback is not callable.';
				return false;
			}
			list ($callbackObject, $callbackMethod) = $callback;
		}
		
		# Avoid callback crashes when using lookupFunction/simpleJoin as support is not yet enabled below
		if ($callback && ($lookupFunction || $simpleJoin)) {
			$this->formSetupErrors['dataBindingCallbackAdvanced'] = 'The databinding callback feature is not yet available when using lookupFunction and/or simpleJoin.';
			return false;
		}
		
		# Ensure there is a database connection or exit here (errors will already have been thrown)
		if (!$this->databaseConnection) {
			if (!$schema && !$callback) {	// Unless using schema/callback
				if ($this->databaseConnection === NULL) {	// rather than === NULL, which means no connection requested
					$this->formSetupErrors['dataBindingNoDatabaseConnection'] = 'Data binding has been requested, but no valid database connection has been set up in the main settings.';
				}
				return false;
			}
		}
		
		# Global the dataBinding connection details
		$this->dataBinding = array (
			'database'	=> $database,
			'table'		=> $table,
		);
		
		# If using redirection to simplified GET on success, check for a GET collection and use that in preference
		#!# Currently only fields generated by dataBinding will be captured by redirectGet, not directly-generated fields
		if ($this->settings['redirectGet']) {
			if (!$_POST) {
				$data = $_GET;
			}
		}
		
		# If simple join mode is enabled, proxy in the values for lookupFunction
		if ($simpleJoin) {
			$lookupFunction = array ('database', 'lookup');
/*
Work-in-progress implementation for callback; need to complete: (i) form setup checks to determine whether databaseConnection is needed, and (ii) implementing callback mode for uses of the database connection below
			if ($callback) {
				$callbackMethodTables = $callbackMethod . 'GetTables';
				$tables = $callbackObject->{$callbackMethodTables} ();
			} else {
*/
				$tables = $this->databaseConnection->getTables ($database);	// Table lookup needed for the simple pluraliser which will favour pluralised table names (e.g. field 'caseId' will look for a table 'cases' then 'case')
/*
			}
*/
		}
		
		# Ensure any lookup function has been defined
		if ($lookupFunction && !is_callable ($lookupFunction)) {
			$this->formSetupErrors['dataBindingLookupFunctionInvalid'] = "You specified a lookup function ('<strong>" . (is_array ($lookupFunction) ? implode ('::', $lookupFunction) : $lookupFunction) . "</strong>') for the data binding, but the function does not exist.";
			return false;
		}
		
		# Ensure the user has not set both include and exclude lists
		if ($includeOnly && $exclude) {
			$this->formSetupErrors['dataBindingIncludeExcludeClash'] = 'Values have been set for both includeOnly and exclude when data binding.';
			return false;
		}
		
		# Ensure the user has not set both notNullFields and notNullExceptFields lists, and ensure they are arrays
		if ($notNullFields && $notNullExceptFields) {
			$this->formSetupErrors['dataBindingAutoNullableClash'] = 'Values have been set for both notNullFields and notNullExceptFields when data binding.';
			return false;
		}
		$notNullFields		= application::ensureArray ($notNullFields);
		$notNullExceptFields	= application::ensureArray ($notNullExceptFields);
		
		# Get the database fields
		if ($schema) {
			$fields = $schema;	// Copy directly
		} else if ($callback) {
			$fields = $callbackObject->{$callbackMethod} ();
		} else {
			$fields = $this->databaseConnection->getFields ($database, $table);
		}
		if (!$fields) {
			$this->formSetupErrors['dataBindingFieldRetrievalFailed'] = 'The database fields could not be retrieved. Please check that the database library you are using is supported.';
			return false;
		}
		
		# Reorder if required (explicitly, or implicitly via includeOnly)
		if ($includeOnly && !$ordering) {$ordering = $includeOnly;}
		if ($ordering) {
			$ordering = application::ensureArray ($ordering);
			$newFields = array ();
			foreach ($ordering as $field) {
				if (array_key_exists ($field, $fields)) {
					
					# Move fields if set
					$newFields[$field] = $fields[$field];
					unset ($fields[$field]);
				}
			}
			
			# Merge the new fields and the old, with new taking precedence, and remove the old fields
			$fields = array_merge ($newFields, $fields);
			unset ($newFields);
		}
		
		# Loop through the fields in the data, to add widgets
		foreach ($fields as $fieldName => $fieldAttributes) {
			
			# Skip if either: (i) explicitly excluded; (ii) not specifically included or (iii) marked as NULL in the overload array
			if (is_array ($includeOnly) && $includeOnly && !in_array ($fieldName, $includeOnly)) {continue;}
			if (is_array ($exclude) && $exclude && in_array ($fieldName, $exclude)) {continue;}
			if (is_array ($attributes) && (array_key_exists ($fieldName, $attributes))) {
				if ($attributes[$fieldName] === NULL) {continue;}
			}
			
			# Lookup the value if given; NB this can also be supplied in the attribute overloading as defaults
			$value = ((is_array ($data) && (array_key_exists ($fieldName, $data))) ? $data[$fieldName] : $fieldAttributes['Default']);
			
			# Assign the title to be the fieldname by default
			$title = $fieldName;
			
			# Perform a lookup if necessary
			$lookupValues = false;
			$targetDatabase = false;
			$targetTable = false;
			if ($lookupFunction) {
				$parameters = array ($this->databaseConnection, $title, $fieldAttributes['Type'], $simpleJoin = ($simpleJoin ? array ($database, $table, $tables) : false));
				if ($lookupFunctionParameters) {$parameters = array_merge ($parameters, application::ensureArray ($lookupFunctionParameters));}
				$userFunctionResult = call_user_func_array ($lookupFunction, $parameters);
				if (count ($userFunctionResult) != 4) {	// Should be returning an array of four values as per the list() call below
					$this->formSetupErrors['dataBindingLookupFunctionReturnValuesInvalid'] = "You specified a lookup function ('<strong>" . (is_array ($lookupFunction) ? implode ('::', $lookupFunction) : $lookupFunction) . "</strong>') for the data binding, but the function does not return an array of four values as is required.";
					return false;
				}
				list ($title, $lookupValues, $targetDatabase, $targetTable) = $userFunctionResult;
			}
			
			# Convert title from lowerCamelCase to Standard text if necessary
			if ($changeCase) {
				$title = application::unCamelCase ($title);
			}
			
			# If using table fields comment assign an existent comment as the title, overwriting any amendments to the title already made
			if (!$commentsAsDescription && isSet ($fieldAttributes['Comment']) && $fieldAttributes['Comment']) {
				$title = $fieldAttributes['Comment'];
			}
			
			# Determine whether the field is required
			$required = ($fieldAttributes['Null'] != 'YES');
			if ($notNullFields && (in_array ($fieldName, $notNullFields))) {$required = true;}
			if ($notNullExceptFields && (!in_array ($fieldName, $notNullExceptFields))) {$required = true;}
			
			# Define the standard attributes; fields that don't support a particular option shown here will ignore it
			$standardAttributes = array (
				'name' => $fieldName,	// Internal widget name
				'title' => $title,	// Visible name
				'required' => $required,
				'default' => $value,
				'datatype' => $fieldAttributes['Type'],
				'description' => ($commentsAsDescription && isSet ($fieldAttributes['Comment']) && $fieldAttributes['Comment'] ? $fieldAttributes['Comment'] : ''),
				'autocomplete' => ($autocomplete ? str_replace ('%field', $fieldName, $autocomplete) : false),
				'autocompleteOptions' => ($autocompleteOptions ? $autocompleteOptions : false),
				'valuesNamesAutomatic' => $valuesNamesAutomatic,
			);
			
			# If a link template is supplied, place that in, but if it includes a %table/%database template, put it in only if those exist
			if ($lookupFunctionAppendTemplate) {
				$templateHasDatabase = substr_count ($lookupFunctionAppendTemplate, '%database');
				$templateHasTable = substr_count ($lookupFunctionAppendTemplate, '%table');
				if (!$templateHasDatabase && !$templateHasTable) {$useTemplate = true;}	// Use it if no templating requested
				if (($templateHasDatabase && $targetDatabase) || ($templateHasTable && $targetTable)) {$useTemplate = true;}	// Use it if templating is in use and the target database/table is present
				if (($templateHasDatabase && !$targetDatabase) || ($templateHasTable && !$targetTable)) {$useTemplate = false;}	// Ensure both are present if both in use
				if ($useTemplate) {
					#!# Need to deny __refresh as a reserved form name
					$refreshButton = '<input type="submit" value="&#8635;" title="Refresh options" name="__refresh" class="refresh" />';
					$refreshButtonTabindex999 = '<input type="submit" value="&#8635;" title="Refresh options" name="__refresh" class="refresh" tabindex="999" />';
					$this->multipleSubmitReturnHandlerJQuery ();
					$standardAttributes['append'] = str_replace (array ('%database', '%table', '%refreshtabindex999', '%refresh'), array ($targetDatabase, $targetTable, $refreshButtonTabindex999, $refreshButton), $lookupFunctionAppendTemplate);
				}
			}
			
			# Assuming non-forcing of widget type
			$forceType = false;
			
			# Assume no support for auto-rounding of floats
			$roundFloat = false;
			
			# Add intelligence rules if required
			#!# Bug: $int1ToCheckbox should avoid modifications but currently an int like mailToAdmin INT(1) is wrongly getting converted
			if ($intelligence) {
				
				# Fields with 'email' in become e-mail fields
				if (preg_match ('/email/i', $fieldName)) {
					$forceType = 'email';
				}
				
				# Fields with 'password' in become password fields, with a proxied confirmation widget
				if (preg_match ('/password/i', $fieldName)) {
					$forceType = 'password';
					$standardAttributes['confirmation'] = true;
					if ($data) {
						$standardAttributes['editable'] = false;
					}
				}
				
				# HTML5 search/color fields become native fields
				if (preg_match ('/\bsearch/i', $fieldName)) {
					$forceType = 'search';
				}
				if (preg_match ('/(telephone|^tel$)/i', $fieldName)) {
					$forceType = 'tel';
				}
				if (preg_match ('/(color|colour)/i', $fieldName)) {
					$forceType = 'color';
				}
				
				# Richtext fields - text fields with html/richtext in fieldname; NB if changing the regexp, also change this in the addSettingsTableConfig method in frontControllerApplication.php
				if (preg_match ('/(html|richtext)/i', $fieldName) && (in_array (strtolower ($fieldAttributes['Type']), array ('text', 'tinytext', 'mediumtext', 'longtext')))) {
					$forceType = 'richtext';
					
					# Use basic toolbar set for fieldnames containing 'basic/mini/simple'
					if (preg_match ('/(basic|mini|simple)/i', $fieldName)) {
						$standardAttributes['editorToolbarSet'] = 'Basic';
					}
				}
				
				# Website fields - for fieldnames containing 'url/website/http'
				if (preg_match ('/(website|http)/i', $fieldName) || preg_match ('/.+Url$/', $fieldName) || $fieldName == 'url') {
					$forceType = 'url';
					$standardAttributes['regexp'] = '^(http|https)://';
					$standardAttributes['description'] = 'Must begin https://';	// ' or http://' not added to this description just to keep it simple
				}
				
				# Upload fields - fieldname containing photograph/upload or starting/ending with file/document
				if (preg_match ('/(photograph|upload|^file|^document|file$|document$)/i', $fieldName)) {
					$forceType = 'upload';
					$standardAttributes['flatten'] = true;	// Flatten the output so it's a string not an array
					$standardAttributes['subfields'] = 1;	// Specify 1 subfield (which is already the default anyway)
					//$standardAttributes['directory'] = './uploads/';
				}
				
				# Enable thumbnails for photographs
				if (preg_match ('/(photograph)/i', $fieldName)) {
					$standardAttributes['thumbnail'] = true;
				}
				
				# Make an auto_increment field not appear
				if ($fieldAttributes['Extra'] == 'auto_increment') {
					if (!$value) {
						continue;	// Skip widget creation (and therefore visibility) if no value
					} else {
						$standardAttributes['editable'] = false;
					}
					/*
					$standardAttributes['discard'] = true;
					
					$standardAttributes['editable'] = false;
					if (!$value) {
						# Show '[Automatically assigned]' as text
						#!# Find a better way to do this in the widget code than this workaround method; perhaps create a 'show' attribute
						$forceType = 'select';
						$standardAttributes['discard'] = true;
						$standardAttributes['values'] = array (1 => '<em class="comment">[Automatically assigned]</em>');	// The value '1' is used to ensure it always validates, whatever the field length or other specification is
						$standardAttributes['forceAssociative'] = true;
						$standardAttributes['default'] = 1;
					}
					*/
				}
				
				# Make a timestamp field not appear
				if ((strtolower ($fieldAttributes['Type']) == 'timestamp') && ($fieldAttributes['Default'] == 'CURRENT_TIMESTAMP')) {
					continue;	// Skip widget creation
				}
				
				# Assume that createdAt/createdOn/updatedAt/updatedOn (names borrowed from Rails) are timestamps
				$timestampFieldnames = array ('createdAt', 'createdOn', 'updatedAt', 'updatedOn');
				if (in_array ($fieldName, $timestampFieldnames)) {
					continue;	// Skip widget creation
				}
				
				# Select fields containing Yes/No, with a subsequent field with 'Details' appended to the name, should trigger a 'details' validation rule
				if (is_array ($fieldAttributes['_values']) && in_array ('Yes', $fieldAttributes['_values']) && in_array ('No', $fieldAttributes['_values'])) {
					$detailsField = $fieldName . 'Details';		// e.g. foo and fooDetails
					#!# Ideally this would also check that the details field was the next field, rather than just existing
					if (isSet ($fields[$detailsField])) {
						$this->validation ('details', array ($fieldName, $detailsField));
					}
				}
				
/* Work-in-progress map integration code:
				# Create a map if both latitude and longitude present
				$mapFields = array ('latitude', 'longitude');
				if (in_array ($fieldName, $mapFields) && (!array_diff ($mapFields, array_keys ($fields)))) {
					$standardAttributes['enforceNumeric'] = true;
					$standardAttributes['max'] = ($fieldName == 'latitude' ?  90 :  180);
					$standardAttributes['min'] = ($fieldName == 'latitude' ? -90 : -180);
					$roundFloat = true;
					$standardAttributes['_cssHide--DONOTUSETHISFLAGEXTERNALLY'] = true;
					// NB $floatAttributes below will force the decimal places to be correct, e.g. FLOAT(10,6) will give 6 decimal places, i.e. 10cm resolution; maxlength will also be set automatically
				}
*/
			}
			
			# Add per-widget overloading if attributes supplied by the calling application
			if (is_array ($attributes) && (array_key_exists ($fieldName, $attributes))) {
				
				# Convert to hidden type if forced
				if ($attributes[$fieldName] === 'hidden') {
					$fieldAttributes['Type'] = '_hidden';
				} else {
					
					# Amend the type to a specific widget if set
					if (isSet ($attributes[$fieldName]['type'])) {
						if (method_exists ($this, $attributes[$fieldName]['type'])) {
							$forceType = $attributes[$fieldName]['type'];
						}
					}
				}
				
				# Add any headings (which will appear before creating the widget); In the unlikely event that multiple of the same level are needed, '' => "<h2>Foo</h2>\n<h2>Bar</h2>" would have to be used, or the dataBinding will have to split into multiple dataBinding calls
				if (isSet ($attributes[$fieldName]['heading']) && is_array ($attributes[$fieldName]['heading'])) {
					foreach ($attributes[$fieldName]['heading'] as $level => $title) {
						$this->heading ($level, $title);
					}
				}
				
				# Finally, perform the actual overloading the attribute, if the attributes are an array
				if (is_array ($attributes[$fieldName])) {
					$standardAttributes = array_merge ($standardAttributes, $attributes[$fieldName]);
				}
			}
			
			# Prefix the field name if required
			if ($prefix) {	// This will automatically prevent the string '0' anyway
				if ($prefix === '0') {
					$this->formSetupErrors['dataBindingPrefix'] = "A databinding prefix cannot be called '0'";
				}
				if ($prefixTitleSuffix) {
					$standardAttributes['title'] .= $prefixTitleSuffix;	// e.g. a field whose title is "Name" gets a title of "Name (1)" if prefixTitleSuffix = ' (1)'
				}
				$standardAttributes['name'] = $prefix . '_' . $standardAttributes['name'];
				//$standardAttributes['unprefixed'] = $standardAttributes['name'];
				$this->prefixedGroups[$prefix][] = $standardAttributes['name'];
			}
			
			# Deal with looked-up value sets specially, defaulting to select unless the type is forced
			$skipWidgetCreation = false;
			if ($lookupValues && $fieldAttributes['Type'] != '_hidden') {
				$lookupType = 'select';
				if ($forceType && ($forceType == 'checkboxes' || $forceType == 'radiobuttons')) {
					$lookupType = $forceType;
				}
				$this->$lookupType ($standardAttributes + array (
					'forceAssociative' => true,	// Force associative checking of defaults
					#!# What should happen if there's no data generated from a lookup (i.e. empty database table)?
					'values' => $lookupValues,
					'output' => array ('processing' => 'compiled'),
					'truncate' => $truncate,
				));
				$skipWidgetCreation = true;
			}
			
			# If the inputAsSearch option is on, convert standard text input field to search
			if ($inputAsSearch && !$forceType && (strtolower ($fieldAttributes['Type']) == 'text')) {
				$forceType = 'search';
			}
			
			# If the textAsVarchar option is on, convert the type to VARCHAR(255)
			if ($textAsVarchar && (strtolower ($fieldAttributes['Type']) == 'text')) {$fieldAttributes['Type'] = 'VARCHAR(255)';}
			
			# Obtain the type
			$type = $fieldAttributes['Type'];
			
			# Handle INT types without display width attribute, mapping them to older types, e.g. map INT to INT(11)
			if (in_array ($type, array ('int', 'mediumint', 'smallint', 'bigint'))) {$type = 'int(11)';}
			if (in_array ($type, array ('int unsigned', 'mediumint unsigned', 'smallint unsigned', 'bigint unsigned'))) {$type = 'int(11) unsigned';}
			if ($type == 'tinyint' || $type == 'tinyint unsigned') {$type = 'int(1)';}		// Essentially boolean
			if ($type == 'year') {$type = 'year(4)';}
			
			# Take the type and convert it into a form widget type
			switch (true) {
				
				# Skipping of this element
				case ($skipWidgetCreation):
					break;
					
				# Force to a specified type if required
				case ($forceType):
					if (($forceType == 'checkboxes' || $forceType == 'radiobuttons' || $forceType == 'select') && preg_match ('/(enum|set)\(\'(.*)\'\)/i', $type, $matches)) {
						$values = explode ("','", $matches[2]);
						$this->$forceType ($standardAttributes + array (
							'values' => $values,
						));
					} else {
						$this->$forceType ($standardAttributes);
					}
					break;
				
				# Hidden fields - deny editability
				case ($type == '_hidden'):
					$this->input ($standardAttributes + array (
						'editable' => false,
						'_visible--DONOTUSETHISFLAGEXTERNALLY' => false,
					));
					break;
				
				# FLOAT/DOUBLE (numeric with decimal point) / DECIMAL fields
				case (preg_match ('/(float|decimal|double|double precision)\(([0-9]+),([0-9]+)\)/i', $type, $matches)):
				case (preg_match ('/(float|decimal|double|double precision)$/i', $type, $matches)):
					if ($floatChopTrailingZeros) {
						if (substr_count ($standardAttributes['default'], '.')) {
							$standardAttributes['default'] = preg_replace ('/0+$/', '', $standardAttributes['default']);
							$standardAttributes['default'] = preg_replace ('/\.$/', '', $standardAttributes['default']);
						}
					}
					if (isSet ($matches[2])) { // e.g. FLOAT(7,2)
						$floatAttributes = array (
							'maxlength' => ((int) $matches[2] + 2),	// FLOAT(M,D) means "up to M digits in total, of which D digits may be after the decimal point", so maxlength is M + 1 (for the decimal point) + 1 (for a negative sign)
							'regexp' => '^(-?)([0-9]{0,' . ($matches[2] - $matches[3]) . '})((\.)([0-9]{0,' . $matches[3] . '})$|$)',
						);
						if ($roundFloat) {
							$floatAttributes['roundFloat'] = $matches[3];
						}
					} else {	// e.g. FLOAT or DOUBLE without any size specification
						$floatAttributes = array (
							'regexp' => '^(-?)([0-9]+)((\.)([0-9]+)$|$)',
						);
					}
					$floatAttributes['step'] = 'any';
					$this->number ($standardAttributes + $floatAttributes);
					break;
				
				# CHAR/VARCHAR (character) field
				case (preg_match ('/(char|varchar)\(([0-9]+)\)/i', $type, $matches)):
					$this->input ($standardAttributes + array (
						'maxlength' => $matches[2],
						# Set the size if a (numeric) value is given and the required size is greater than the size specified
						'size' => ($size && (is_numeric ($size)) && ((int) $matches[2] > $size) ? $size : $matches[2]),
					));
					break;
				
				# INT (numeric) field
				case (preg_match ('/(integer)/i', $type, $matches)):
					$matches[2] = 11;
					// Fall through to rest of logic
				case (preg_match ('/(int|tinyint|smallint|mediumint|bigint)\(([0-9]+)\)/i', $type, $matches)):
					$unsigned = substr_count (strtolower ($type), ' unsigned');
					if ($int1ToCheckbox && $matches[2] == '1') {
						if (!$value) {	// i.e. 0 or '0' (or NULL)
							$value = NULL;
							if (!$standardAttributes['default']) {
								$standardAttributes['default'] = NULL;	// Normalise 0 to NULL
							}
						}
						$label = (is_string ($int1ToCheckbox) ? $int1ToCheckbox : '');	// Empty unless the 'int1ToCheckbox' value is a string
						$this->checkboxes ($standardAttributes + array (
							'values' => array ('1' => $label),
							'default' => ($value ? '1' : NULL),
							'output' => array ('processing' => 'special-setdatatype'),
						));
					} else {
						#!# Should be set to be a native numeric input type
						$this->input ($standardAttributes + array (
							'enforceNumeric' => true,
							'regexp' => ($unsigned ? '^([0-9]+)$' : '^(-*[0-9]+)$'),	// e.g. '57' or '-2' but not '-'
							#!# Make these recognise types without the numeric value after
							'maxlength' => $matches[2],
							'size' => $matches[2] + 1,
						));
					}
					break;
				
				# ENUM (selection) field - explode the matches and insert as values
				case (preg_match ('/enum\(\'(.*)\'\)/i', $type, $matches)):
					$values = explode ("','", $matches[1]);
					$useRadiobuttons = (is_int ($enumRadiobuttons) ? (count ($values) <= $enumRadiobuttons) : $enumRadiobuttons);
					foreach ($values as $index => $value) {
						$values[$index] = str_replace ("''", "'", $value);
						if ($useRadiobuttons && $enumRadiobuttonsInitialNullText && is_array ($enumRadiobuttonsInitialNullText) && isSet ($enumRadiobuttonsInitialNullText[$fieldName])) {
							$standardAttributes['nullText'] = $enumRadiobuttonsInitialNullText[$fieldName];
						}
					}
					$widgetType = ($useRadiobuttons ? 'radiobuttons' : 'select');
					$this->$widgetType ($standardAttributes + array (
						'values' => $values,
						'output' => array ('processing' => 'compiled'),
					));
					break;
				
				# SET (multiple item) field - explode the matches and insert as values
				case (preg_match ('/set\(\'(.*)\'\)/i', $type, $matches)):
					$values = explode ("','", $matches[1]);
					$setSupportMax = 64;	// MySQL supports max 64 values for SET; #!# This value should be changeable in settings as different database vendor might be in use
					$setSupportSupplied = count ($values);
					if ($setSupportSupplied > $setSupportMax) {
						$this->formSetupErrors['DatabindingSetExcessive'] = "{$setSupportSupplied} values were supplied for the {$fieldName} dataBinding 'SET' field but a maximum of only {$setSupportMax} are supported.";
					} else {
						$checkboxesAttributes = $standardAttributes + array (
							'values' => $values,
							'output' => array ('processing' => 'special-setdatatype'),
							'default' => ($value ? $value : array ()),	// Value from getData will just be item1,item2,item3
						);
						if (strlen ($checkboxesAttributes['default'])) {	// Don't explode an empty string into array('');
							$checkboxesAttributes['default'] = (is_array ($checkboxesAttributes['default']) ? $checkboxesAttributes['default'] : explode (',', $checkboxesAttributes['default']));
						}
						$this->checkboxes ($checkboxesAttributes);
					}
					break;
				
				# DATE (date) field
				case (preg_match ('/year\(([2|4])\)/i', $type, $matches)):
					$type = 'year';
				case (strtolower ($type) == 'time'):
				case (strtolower ($type) == 'date'):
				case (strtolower ($type) == 'datetime'):
				case (strtolower ($type) == 'timestamp'):
					if (strtolower ($type) == 'timestamp') {
						$type = 'datetime';
						$standardAttributes['default'] = 'timestamp';
						$standardAttributes['editable'] = false;
					}
					$this->datetime ($standardAttributes + array (
						'level' => strtolower ($type),
						#!# Disabled as seemingly incorrect
						/* 'editable' => (strtolower ($type) == 'timestamp'), */
					));
					break;
				
				# BLOB
				case (strtolower ($type) == 'blob'):
				case (strtolower ($type) == 'mediumtext'):
				case (strtolower ($type) == 'longtext'):
				case (strtolower ($type) == 'text'):
					$this->textarea ($standardAttributes + array (
						// 'cols' => 50,
						// 'rows' => 6,
					));
					break;
				
				#!# Add more here as they are found
				
				# Otherwise throw an error
				default:
					$this->formSetupErrors['dataBindingUnsupportedFieldType'] = "An unknown field type ('{$type}') was found while trying to create a form from the data and fields; as such the form could not be created.";
			}
			
			# If the field is unique, add a constraint
			#!# Convert to prepared statements
			if (strtolower ($fieldAttributes['Key']) == 'uni') {
				if ($unfinalisedData = $this->getUnfinalisedData ()) {
					if ($unfinalisedData[$fieldName]) {
						$whereNotCurrent = false;
						if ($editingUniquenessUniChecking && $data && isSet ($data[$fieldName]) && strlen ($data[$fieldName])) {		// If there is existing data (i.e. the user is doing an UPDATE, not an INSERT), exclude this from the lookup
							$whereNotCurrent .= " AND `{$fieldName}` != " . $this->databaseConnection->quote ($data[$fieldName]);
						}
						$query = "SELECT * FROM `{$database}`.`{$table}` WHERE `{$fieldName}` = " . $this->databaseConnection->quote ($unfinalisedData[$fieldName]) . $whereNotCurrent . ' LIMIT 1;';
						if ($existingData = $this->databaseConnection->getData ($query)) {
							$this->registerProblem ($fieldName . 'notunique', "In the <strong>{$fieldName}</strong> element, that value already exists.", $fieldName);
						}
					}
				}
			}
		}
	}
	
	
	# Function to return a list of countries
	#!# Add option to obtain as moniker => name
	public static function getCountries ($additionalStart = array ())
	{
		# Define the main list
		$countries = array (
			'Afghanistan',
			'Aland Islands',
			'Albania',
			'Algeria',
			'American Samoa',
			'Andorra',
			'Angola',
			'Anguilla',
			'Antarctica',
			'Antigua and Barbuda',
			'Argentina',
			'Armenia',
			'Aruba',
			'Australia',
			'Austria',
			'Azerbaijan',
			'Bahamas',
			'Bahrain',
			'Bangladesh',
			'Barbados',
			'Belarus',
			'Belgium',
			'Belize',
			'Benin',
			'Bermuda',
			'Bhutan',
			'Bolivia, Plurinational State of',
			'Bonaire, Saint Eustatius and Saba',
			'Bosnia and Herzegovina',
			'Botswana',
			'Bouvet Island',
			'Brazil',
			'British Indian Ocean Territory',
			'Brunei Darussalam',
			'Bulgaria',
			'Burkina Faso',
			'Burundi',
			'Cambodia',
			'Cameroon',
			'Canada',
			'Cape Verde',
			'Cayman Islands',
			'Central African Republic',
			'Chad',
			'Chile',
			'China',
			'Christmas Island',
			'Cocos (Keeling) Islands',
			'Colombia',
			'Comoros',
			'Congo',
			'Congo, The Democratic Republic of the',
			'Cook Islands',
			'Costa Rica',
			"Cote d'Ivoire",
			'Croatia',
			'Cuba',
			'Curacao',
			'Cyprus',
			'Czech Republic',
			'Denmark',
			'Djibouti',
			'Dominica',
			'Dominican Republic',
			'Ecuador',
			'Egypt',
			'El Salvador',
			'Equatorial Guinea',
			'Eritrea',
			'Estonia',
			'Ethiopia',
			'Falkland Islands (Malvinas)',
			'Faroe Islands',
			'Fiji',
			'Finland',
			'France',
			'French Guiana',
			'French Polynesia',
			'French Southern Territories',
			'Gabon',
			'Gambia',
			'Georgia',
			'Germany',
			'Ghana',
			'Gibraltar',
			'Greece',
			'Greenland',
			'Grenada',
			'Guadeloupe',
			'Guam',
			'Guatemala',
			'Guernsey',
			'Guinea',
			'Guinea-Bissau',
			'Guyana',
			'Haiti',
			'Heard Island and McDonald Islands',
			'Holy See (Vatican City State)',
			'Honduras',
			'Hong Kong',
			'Hungary',
			'Iceland',
			'India',
			'Indonesia',
			'Iran, Islamic Republic of',
			'Iraq',
			'Ireland',
			'Isle of Man',
			'Israel',
			'Italy',
			'Jamaica',
			'Japan',
			'Jersey',
			'Jordan',
			'Kazakhstan',
			'Kenya',
			'Kiribati',
			"Korea, Democratic People's Republic of",
			'Korea, Republic of',
			'Kuwait',
			'Kyrgyzstan',
			"Lao People's Democratic Republic",
			'Latvia',
			'Lebanon',
			'Lesotho',
			'Liberia',
			'Libyan Arab Jamahiriya',
			'Liechtenstein',
			'Lithuania',
			'Luxembourg',
			'Macao',
			'Macedonia, The Former Yugoslav Republic of',
			'Madagascar',
			'Malawi',
			'Malaysia',
			'Maldives',
			'Mali',
			'Malta',
			'Marshall Islands',
			'Martinique',
			'Mauritania',
			'Mauritius',
			'Mayotte',
			'Mexico',
			'Micronesia, Federated States of',
			'Moldova, Republic of',
			'Monaco',
			'Mongolia',
			'Montenegro',
			'Montserrat',
			'Morocco',
			'Mozambique',
			'Myanmar',
			'Namibia',
			'Nauru',
			'Nepal',
			'Netherlands',
			'New Caledonia',
			'New Zealand',
			'Nicaragua',
			'Niger',
			'Nigeria',
			'Niue',
			'Norfolk Island',
			'Northern Mariana Islands',
			'Norway',
			'Occupied Palestinian Territory',
			'Oman',
			'Pakistan',
			'Palau',
			'Panama',
			'Papua New Guinea',
			'Paraguay',
			'Peru',
			'Philippines',
			'Pitcairn',
			'Poland',
			'Portugal',
			'Puerto Rico',
			'Qatar',
			'Reunion',
			'Romania',
			'Russian Federation',
			'Rwanda',
			'Saint Barthelemy',
			'Saint Helena, Ascension and Tristan da Cunha',
			'Saint Kitts and Nevis',
			'Saint Lucia',
			'Saint Martin (French part)',
			'Saint Pierre and Miquelon',
			'Saint Vincent and The Grenadines',
			'Samoa',
			'San Marino',
			'Sao Tome and Principe',
			'Saudi Arabia',
			'Senegal',
			'Serbia',
			'Seychelles',
			'Sierra Leone',
			'Singapore',
			'Sint Maarten (Dutch part)',
			'Slovakia',
			'Slovenia',
			'Solomon Islands',
			'Somalia',
			'South Africa',
			'South Georgia and the South Sandwich Islands',
			'Spain',
			'Sri Lanka',
			'North Sudan',
			'South Sudan',
			'Suriname',
			'Svalbard and Jan Mayen',
			'Swaziland',
			'Sweden',
			'Switzerland',
			'Syrian Arab Republic',
			'Taiwan, Province of China',
			'Tajikistan',
			'Tanzania, United Republic of',
			'Thailand',
			'Timor-Leste',
			'Togo',
			'Tokelau',
			'Tonga',
			'Trinidad and Tobago',
			'Tunisia',
			'Turkey',
			'Turkmenistan',
			'Turks and Caicos Islands',
			'Tuvalu',
			'Uganda',
			'Ukraine',
			'United Arab Emirates',
			'United Kingdom (UK)',
			'United States of America (USA)',
			'United States Minor Outlying Islands',
			'Uruguay',
			'Uzbekistan',
			'Vanuatu',
			'Venezuela, Bolivarian Republic of',
			'Viet Nam',
			'Virgin Islands, British',
			'Virgin Islands, U.S.',
			'Wallis and Futuna',
			'Western Sahara',
			'Yemen',
			'Zambia',
			'Zimbabwe',
		);
		
		# Add any additional to the start
		if ($additionalStart) {
			$additionalStart[] = '---';
			$countries = array_merge ($additionalStart, $countries);
		}
		
		# Return the list
		return $countries;
	}
}



# Subclass to provide a widget
class formWidget
{
	# Class variables
	var $arguments;
	var $settings;
	var $value;
	var $elementProblems = array ();
	var $functionName;
	var $arrayType;
	
	
	# Constructor
	function __construct (&$form, $suppliedArguments, $argumentDefaults, $functionName, $arrayType = false)
	{
		# Inherit the form
		$this->form =& $form;
		
		# Inherit the settings
		$this->settings =& $form->settings;
		
		# Assign the function name
		$this->functionName = $functionName;
		
		# Assign the setup errors array
		$this->formSetupErrors =& $form->formSetupErrors;
		
		# Assign the arguments
		$this->arguments = application::assignArguments ($this->formSetupErrors, $suppliedArguments, $argumentDefaults, $functionName);
		
		# Add autofocus to the first widget if required
		$this->addAutofocusToFirstWidget ();
		
		# Ensure supplied values (values and default are correctly encoded)
		$this->encodeApiSupplied ();
		
		# Register the element name to enable duplicate checking
		$form->registerElementName ($this->arguments['name']);
		
		# Set whether the widget is an array type
		$this->arrayType = $arrayType;
	}
	
	
	# Function to add autofocus to the first widget if required
	#!# If a subsequent widget ends up manually adding autofocus, e.g. due to expandability, that gets ignored because this overrides it; currently clearAnyOtherAutofocus() is a hack to deal with this
	function addAutofocusToFirstWidget ()
	{
		# End if not requiring autofocus functionality
		if (!$this->settings['autofocus']) {return false;}
		
		# End if this current widget is not editable, as that will never have autofocus
		if (!$this->arguments['editable']) {return false;}
		
		# End if there is an editable, non-heading widget already defined
		foreach ($this->form->elements as $name => $attributes) {
			if ($attributes['type'] == 'heading') {continue;}	// Skip headings
			if (!$attributes['editable']) {continue;}			// Skip uneditable widgets
			return false;	// End if the execution has got this far
		}
		
		# If this is the first (non-header) widget, add the autofocus attribute to the widget specification
		$this->arguments['autofocus'] = true;
	}
	
	
	# Function to encode supplied value as supplied through the API; does not affect posted data which should not need charset conversion
	function encodeApiSupplied ()
	{
		# Fix values list if there is one
		if (isSet ($this->arguments['values'])) {
			$this->arguments['values'] = application::convertToCharset ($this->arguments['values'], 'UTF-8', $convertKeys = true);
		}
		
		# Fix default value(s)
		if (isSet ($this->arguments['default'])) {
			$this->arguments['default'] = application::convertToCharset ($this->arguments['default'], 'UTF-8', $convertKeys = true);
		}
	}
	
	
	# Function to set the widget's (submitted) value
	function setValue ($value)
	{
		# If an array type, ensure the value is an array, converting where necessary
		if ($this->arrayType) {$value = application::ensureArray ($value);}
		
		# Set the value
		$this->value = $value;
	}
	
	
	# Function to return the arguments
	function getArguments ()
	{
		return $this->arguments;
	}
	
	
	# Function to return the widget's (submitted but processed) value
	function getValue ()
	{
		return $this->value;
	}
	
	
	# Function to determine if a widget is required but empty
	function requiredButEmpty ()
	{
		# Return the value; note that strlen rather than empty() is used because the PHP stupidly allows the string "0" to be empty()
		return (($this->arguments['required']) && (strlen ($this->value) == 0));
	}
	
	
	# Function to return the widget's problems
	function getElementProblems ($problems)
	{
		#!# Temporary: merge in any problems from the object
		if ($problems) {$this->elementProblems += $problems;}
		
		return $this->elementProblems;
	}
	
	
	/**
	 * Function to clean whitespace from a field where requested
	 * @access private
	 */
	function handleWhiteSpace ()
	{
		# Trim white space if required
		if ($this->settings['whiteSpaceTrimSurrounding']) {$this->value = trim ($this->value);}
		
		# Remove white space if that's all there is
		if (($this->settings['whiteSpaceCheatAllowed']) && (trim ($this->value)) == '') {$this->value = '';}
	}
	
	
	# Function to check the minimum length of what is submitted
	function checkMinLength ()
	{
		#!# Move the is_numeric check into the argument cleaning stage
		if (is_numeric ($this->arguments['minlength'])) {
			if (strlen ($this->value) < $this->arguments['minlength']) {
				$this->elementProblems['belowMinimum'] = 'You submitted fewer characters (<strong>' . strlen ($this->value) . '</strong>) than are allowed (<strong>' . $this->arguments['minlength'] . '</strong>).';
			}
		}
	}
	
	
	# Function to check the maximum length of what is submitted
	function checkMaxLength ($stripHtml = false)
	{
		# Obtain the value, and strip HTML first if required
		$value = $this->value;
		if ($stripHtml) {
			$value = strip_tags ($value);
		}
		
		# Determine the string length
		$length = strlen ($value);
		
		#!# Move the is_numeric check into the argument cleaning stage
		if (is_numeric ($this->arguments['maxlength'])) {
			if ($length > $this->arguments['maxlength']) {
				$this->elementProblems['exceedsMaximum'] = 'You submitted more characters (<strong>' . $length . '</strong>) than are allowed (<strong>' . $this->arguments['maxlength'] . '</strong>).';
			}
		}
	}
	
	
	# Function to add autocomplete functionality
	function autocomplete ($arguments, $subwidgets = false)
	{
		if ($arguments[__FUNCTION__]) {
			$id = $this->form->cleanId ($this->settings['name'] ? "{$this->settings['name']}[{$arguments['name']}]" : $arguments['name']);
			$this->form->autocompleteJQuery ($id, $arguments[__FUNCTION__], $arguments['autocompleteOptions'], $subwidgets);
		}
	}
	
	
	# Function to add tags functionality; uses Tag-it: https://github.com/aehlke/tag-it/ and http://aehlke.github.io/tag-it/examples.html
	function tags ()
	{
		# End if this functionality is not activated
		if (!$this->arguments[__FUNCTION__]) {return;}
		$parameter = $this->arguments[__FUNCTION__];
		
		# Enable jQuery UI
		$this->form->enableJqueryUi ();
		
		# Add the main function
		$this->form->jQueryLibraries[__FUNCTION__]  = "\n\t\t\t" . '<script type="text/javascript" src="' . $this->settings['scripts'] . 'tag-it/js/tag-it.js"></script>';	// https://rawgithub.com/aehlke/tag-it/master/js/tag-it.js
		
		# Add the stylesheets
		$this->form->jQueryLibraries[__FUNCTION__] .= "\n\t\t\t" . '<link rel="stylesheet" href="' . ($this->settings['scripts'] ? $this->settings['scripts'] : 'http://ajax.googleapis.com/ajax/libs') . '/jqueryui/1/themes/flick/jquery-ui.css" type="text/css" />';
		$this->form->jQueryLibraries[__FUNCTION__] .= "\n\t\t\t" . '<link rel="stylesheet" href="' . $this->settings['scripts'] . 'tag-it/css/jquery.tagit.css" type="text/css" />';	// https://rawgithub.com/aehlke/tag-it/master/css/jquery.tagit.css
		$this->form->jQueryLibraries[__FUNCTION__] .= "\n\t\t\t" . '<link rel="stylesheet" href="' . $this->settings['scripts'] . 'tag-it/css/tagit.ui-zendesk.css" type="text/css" />';	// https://rawgithub.com/aehlke/tag-it/master/css/tagit.ui-zendesk.css
		
		# Options
		$functionOptions = array ();
		$functionOptions[] = 'removeConfirmation: true';
		
		# If required, add autocomplete functionality; either a string representing an AJAX endpoint, or an array of values
		if (is_string ($parameter)) {
			/* 
				# The data source needs to emit either:
				- A simple array, json_encode'd:
					json_encode (array (
						'foo',
						'bar',
						// ...
					));
				- Or an associative value/label array, json_encode'd:
					json_encode (array (
						array ('value' => 'foovalue', 'label' => 'foolabel'),
						array ('value' => 'barvalue', 'label' => 'barlabel'),
						// ...
					));
			*/
			$functionOptions['autocomplete'] = "tagSource: function(search, showChoices) {
						$.ajax({
							url: '{$parameter}',
							data: search,
							success: function(data) {
								data = JSON.parse(data);
								showChoices(data);
							}
						});
					}";
		} else if (is_array ($parameter)) {
			$functionOptions['autocomplete'] = 'availableTags: ' . json_encode (array_values ($parameter));
		}
		if (isSet ($functionOptions['autocomplete'])) {
			$functionOptions[] = 'autocomplete: {delay: 0, minLength: 2}';
		}
		
		# Add a per-widget call
		$id = $this->form->cleanId ("{$this->settings['name']}[{$this->arguments['name']}]");
		$this->form->jQueryCode[__FUNCTION__ . $id] = "
			$(document).ready(function() {
				$('#" . $id . "').tagit({
					" . implode (",\n\t\t\t\t\t", $functionOptions) . "
				});
			});
		";
	}
	
	
	# Function to add autocomplete functionality (tokenised version; NB sends q= and requires id,name keys)
	function autocompleteTokenised ($singleLine = true)
	{
		# End if this functionality is not activated
		if (!$this->arguments[__FUNCTION__]) {return;}
		
		# Use the default value if not posted
		$value = ($this->form->formPosted ? $this->value : $this->arguments['default']);
		
		# If a value has been submitted, process it
		$options = array ();
		if (strlen ($value)) {
			
			# Strip the trailing comma that the tokenised jQuery library being used creates when posting
			if ($this->form->formPosted) {
				if (substr ($value, -1) == ',') {
					$value = substr ($value, 0, -1);
					$this->value = $value;	// #!# Not sure if this is necessary
				}
			}
			
			# Pre-populate this list if data has been submitted
			$data = explode (',', $value);
			$values = array ();
			$i = 0;
			foreach ($data as $value) {
				$values[$i]['id'] = $value;
				$values[$i]['name'] = $value;	// Ideally this would have the label, but this data is not available to ultimateForm
				$i++;
			}
			$options[] = 'prePopulate: ' . json_encode ($values);
		}
		
		# Create the widget
		$id = $this->form->cleanId ("{$this->settings['name']}[{$this->arguments['name']}]");
		$options = implode (',', $options);
		$this->form->autocompleteTokenisedJQuery ($id, $this->arguments[__FUNCTION__], $options, $singleLine);
	}
	
	
	# Function to prevent multiline submissions in input elements which shouldn't allow line-breaks
	function preventMultilineSubmissions ()
	{
		# Determine the value(s) to be checked; this takes account of expandable widgets
		#!# This ought to be done on a per-subwidget basis, as currently a subwidget in 'expandable="\n"' mode currently would have a newline allowed through
		$values = array ($this->value);
		if ($this->arguments['expandable']) {
			$expandableSeparator = $this->arguments['expandable'];
			$values = explode ($expandableSeparator, $this->value);
		}
		
		# Throw an error if an \n or \r line break is found
		foreach ($values as $value) {
			if (preg_match ("/([\n\r]+)/", $value)) {
				$this->elementProblems['multilineSubmission'] = 'Line breaks are not allowed in field types that do not support these.';
				return;		// No point checking any more
			}
		}
	}
	
	
	/**
	 * Function to clean input from a field to being numeric only
	 * @access private
	 */
	function cleanToNumeric ()
	{
		# End if not required to enforce numeric
		if (!$this->arguments['enforceNumeric']) {return;}
		
		# Don't clean e-mail types
		if ($this->functionName == 'email') {return;}
		
		# Get the data
		#!# Remove these
		$data = $this->value;
		
		#!# Replace with something like this line? :
		#$this->form[$name] = preg_replace ('/[^0-9\. ]/', '', trim ($this->form[$name]));
		
		# Strip replace windows carriage returns with a new line (multiple new lines will be stripped later)
		$data = str_replace ("\r\n", "\n", $data);
		# Turn commas into spaces
		$data = str_replace (',', ' ', $data);
		# Strip non-numeric characters
		$data = preg_replace ("/[^-0-9\.\n\t ]/", '', $data);
		# Replace tabs and duplicated spaces with a single space
		$data = str_replace ("\t", ' ', $data);
		# Replace tabs and duplicated spaces with a single space
		$data = preg_replace ("/[ \t]+/", ' ', $data);
		# Remove space at the start and the end
		$data = trim ($data);
		# Collapse duplicated newlines
		$data = preg_replace ("/[\n]+/", "\n", $data);
		# Remove any space at the start or end of each line
		$data = str_replace ("\n ", "\n", $data);
		$data = str_replace (" \n", "\n", $data);
		
		# Re-assign the data
		#!# Remove these
		$this->value = $data;
	}
	
	
	# Helper function for creating tabindex HTML
	#!# Add tabindex validation, i.e. accept 0-32767, strip leading zeros and confirm is an integer (without decimal places)
	function tabindexHtml ($subwidgetIndex = false)
	{
		# If it's a scalar widget type, return a string
		if (!$subwidgetIndex) {
			return (is_numeric ($this->arguments['tabindex']) ? " tabindex=\"{$this->arguments['tabindex']}\"" : '');
		}
		
		# Add a tabindex value if necessary; a numeric value just adds a tabindex to the first subwidget; an array instead creates a tabindex for any keys which exist in the array
		$tabindexHtml = '';
		if (is_numeric ($this->arguments['tabindex']) && $subwidgetIndex == 0) {
			$tabindexHtml = " tabindex=\"{$this->arguments['tabindex']}\"";
		} else if (is_array ($this->arguments['tabindex']) && array_key_exists ($subwidgetIndex, $this->arguments['tabindex']) && $this->arguments['tabindex'][$subwidgetIndex]) {
			$tabindexHtml = " tabindex=\"{$this->arguments['tabindex'][$subwidgetIndex]}\"";
		}
		
		# Return the value
		return $tabindexHtml;
	}
	
	
	# Perform truncation on the visible part of an array, with support for multidimensionality
	function truncate ($values)
	{
		# End if no truncation
		if (!$this->arguments['truncate']) {return $values;}
		
		# Ensure it is numeric
		if (!is_numeric ($this->arguments['truncate'])) {return $values;}
		
		# Define a proper unicode ... character (equivalent of &hellip;)
		$hellip = chr(0xe2).chr(0x80).chr(0xa6);
		
		# Apply truncation if required
		foreach ($values as $key => $value) {
			if (is_array ($value)) {	// Recurse if multi-dimensional
				$values[$key] = $this->truncate ($value);
			} else {
				$substrFunction = (function_exists ('mb_substr') ? 'mb_substr' : 'substr');	// Favour mb_string when available
				$values[$key] = $substrFunction ($value, 0, $this->arguments['truncate']) . ((strlen ($value) > $this->arguments['truncate']) ? ' ' . $hellip : '');
			}
		}
		
		# Return the modified array
		return $values;
	}
	
	
	# Perform regexp checks
	#!# Should there be checking for clashes between disallow and regexp, i.e. so that the widget can never submit?
	#!# Should there be checking of disallow and regexp when editable is false, i.e. so that the widget can never submit?
	function regexpCheck ()
	{
		# End if the form is empty; strlen is used rather than a boolean check, as a submission of the string '0' will otherwise fail this check incorrectly
		if (!strlen ($this->value)) {return false;}
		
		# Regexp checks (for non-e-mail types)
		#!# Allow flexible array ($regexp => $errorMessage) syntax, as with disallow
		if (strlen ($this->arguments['regexp'])) {
			if (!application::pereg ($this->arguments['regexp'], $this->value)) {
				$this->elementProblems['failsRegexp'] = "The submitted information did not match a specific pattern required for this section.";
				return false;
			}
		}
		if (strlen ($this->arguments['regexpi'])) {
			if (!application::peregi ($this->arguments['regexpi'], $this->value)) {
				$this->elementProblems['failsRegexp'] = "The submitted information did not match a specific pattern required for this section.";
				return false;
			}
		}
		
		# 'disallow' regexp checks (for text types)
		if ($this->arguments['disallow'] !== false) {
			
			# If the disallow text is presented as an array, convert the key and value to the disallow patterns and descriptive text; otherwise 
			#!# This should be changed to allow multiple checks, as they may have different error messages required
			if (is_array ($this->arguments['disallow'])) {
				foreach ($this->arguments['disallow'] as $disallowRegexp => $disallowErrorMessage) {
					break;
				}
			} else {
				$disallowRegexp = $this->arguments['disallow'];
				$disallowErrorMessage = "The submitted information matched a disallowed pattern for this section.";
			}
			
			# Perform the check
			if (application::pereg ($disallowRegexp, $this->value)) {
				$this->elementProblems['failsDisallow'] = $disallowErrorMessage;
				return false;
			}
		}
		
		# E-mail check (for e-mail type)
		if ($this->functionName == 'email') {
			
			# Do splitting if required, by comma/semi-colon/space with any spaces surrounding
			$addresses = array ($this->value);	// By default, make it a list of one
			if ($this->arguments['multiple']) {
				$addresses = application::emailListStringToArray ($this->value);
			}
			
			# Loop through each address (which may be just one)
			$invalidAddresses = array ();
			foreach ($addresses as $address) {
				if (!application::validEmail ($address)) {
					$invalidAddresses[] = $address;
				}
			}
			
			# Report if invalid
			if ($invalidAddresses) {
				$this->elementProblems['invalidEmail'] = (count ($addresses) == 1 ? 'The e-mail address' : (count ($invalidAddresses) == 1 ? 'An e-mail address' : 'Some e-mail addresses')) . ' (' . htmlspecialchars (implode (', ', $invalidAddresses)) . ') you gave ' . (count ($invalidAddresses) == 1 ? 'appears' : 'appear') . ' to be invalid.';
				return false;
			}
		}
		
		# Otherwise signal OK
		return true;
	}
	
	
	# Function to check for spam submissions
	function antispamCheck ()
	{
		# Antispam checks
		if (!$this->arguments['antispam']) {return;}
		
		# Check for presence
		if (preg_match ($this->settings['antispamRegexp'], $this->value)) {
			$this->elementProblems['failsAntispamTextMatch'] = "The submitted information matched disallowed text for this section.";
			$this->form->antispamWait += 3;
			return;
		}
		
		# Check for excessive numbers of links
		$total = preg_match_all ('~(https?://)~', $this->value);
		if ($total >= $this->settings['antispamUrlsThreshold']) {
			$this->elementProblems['failsAntispamLinkCount'] = "The submitted information exceeded the number of links permitted for this section.";
			$this->form->antispamWait += 3;
			return;
		}
	}
	
	
	# Function to check for uniqueness
	function uniquenessCheck ($caseSensitiveComparison = false, $trim = true)
	{
		# End if no current values supplied
		if (!$this->arguments['current']) {return NULL;}
		
		# End if array is multi-dimensional
		if (application::isMultidimensionalArray ($this->arguments['current'])) {
			$this->formSetupErrors['currentIsMultidimensional'] = "The list of current values pre-supplied for the '{$this->arguments['name']}' field cannot be multidimensional.";
			return false;
		}
		
		# Ensure the current values are an array
		$this->arguments['current'] = application::ensureArray ($this->arguments['current']);
		
		# Trim values
		if ($trim) {
			$this->arguments['current'] = application::arrayTrim ($this->arguments['current']);
		}
		
		# Find clashes
		if ($caseSensitiveComparison) {
			$clash = (strlen ($this->value) && in_array ($this->value, $this->arguments['current']));
		} else {
			$clash = (strlen ($this->value) && application::iin_array ($this->value, $this->arguments['current']));
		}
		
		# Throw user error if any clashes
		if ($clash) {
			$this->elementProblems['valueMatchesCurrent'] = 'This value already exists - please enter another.';
		}
	}
}


#!# Make the file specification of the form more user-friendly (e.g. specify / or ./ options)
#!# Do a single error check that the number of posted elements matches the number defined; this is useful for checking that e.g. hidden fields are being posted
#!# Add form setup checking validate input types like cols= is numeric, etc.
#!# Add a warnings flag in the style of the errors flagging to warn of changes which have been made silently
#!# Need to add configurable option (enabled by default) to add headings to new CSV when created
#!# Ideally add a catch to prevent the same text appearing twice in the errors box (e.g. two widgets with "details" as the descriptive text)
#!# Enable maximums to other fields
#!# Complete the restriction notices
#!# Add a CSS class to each type of widget so that more detailed styling can be applied
#!# Enable locales, e.g. ordering month-date-year for US users
#!# Consider language localisation (put error messages into a global array, or use gettext)
#!# Add in <span>&#64;</span> for on-screen e-mail types
#!# Apache setup needs to be carefully tested, in conjunction with php.net/ini-set and php.net/configuration.changes
#!# Add links to the id="$name" form elements in cases of USER errors (not for the templating mode though)
#!# Need to prevent the form code itself being overwritable by uploads or CSV writing, by doing a check on the filenames
#!# Add <label> and (where appropriate) <fieldset> support throughout - see also http://www.aplus.co.yu/css/styling-form-fields/ ; http://www.bobbyvandersluis.com/articles/formlayout.php ; http://www.simplebits.com/notebook/2003/09/16/simplequiz_part_vi_formatting.html ; http://www.htmldog.com/guides/htmladvanced/forms/ ; checkbox & radiobutton have some infrastructure written (but commented out) already
#!# Full support for all attributes listed at http://www.w3schools.com/tags/tag_input.asp
#!# Number validation: validate numbers with strval() and intval() or floatval() - www.onlamp.com/pub/a/php/2004/08/26/PHPformhandling.html
#!# Move to in_array with strict third parameter (see fix put in for 1.9.9 for radiobuttons)
# Remove display_errors checking misfeature or consider renaming as disableDisplayErrorsCheck
# Enable specification of a validation function (i.e. callback for checking a value against a database)
# Element setup errors should result in not bothering to create the widget; this avoids more offset checking like that at the end of the radiobuttons type in non-editable mode
# Multi-select combo box like at http://cross-browser.com/x/examples/xselect.php
# Consider highlighting in red areas caught by >validation - currently only the 'all' type is implemented
# Optgroup setting to allow multiple appearances of the same item
#!# Deal with encoding problems - see http://skew.org/xml/misc/xml_vs_http/#troubleshooting
#!# $resultLines[] should have the [techName] optional
# Antispam Captcha option
# Support for select:regexp needed - for cases where a particular option needs to become disabled when submitting a dataBinded form
# Consider grouping/fieldset and design issues at http://www.sitepoint.com/print/fancy-form-design-css/
# Deal with widget name conversion of dot to underscore: http://uk2.php.net/manual/en/language.types.array.php#52124
# Check more thoroughly against XSS at http://ha.ckers.org/xss.html
# Add slashes and manual \' replacement need to be re-considered
# Add an 'other' option to radiobuttons/select which proxies a chained 'other' field; should detect cases when dataBinding provides an adjacent database field

# Version 2 feature proposals
#!# Self-creating form mode
#!# Full object orientation - change the form into a package of objects
#!#		Change each input type to an object, with a series of possible checks that can be implemented - class within a class?
#!# 	Change the output methods to objects
#!# Allow multiple carry-throughs, perhaps using formCarried[$formNumber][...]: Add carry-through as an additional array section; then translate the additional array as a this-> input to hidden fields.
#!# Enable javascript as an option
		# On-submit disable switch bouncing
#!# 	Use ideas in http://www.sitepoint.com/article/1273/3 for having js-validation with an icon
		# http://www.tetlaw.id.au/view/javascript/really-easy-field-validation   may be a useful library
#!# 	Style like in http://www.sitepoint.com/examples/simpletricks/form-demo.html [linked from http://www.sitepoint.com/article/1273/3]
#!# Add AJAX validation flag See: http://particletree.com/features/degradable-ajax-form-validation/ (but modified version needed because this doesn't use Unobtrusive DHTML - see also http://particletree.com/features/a-guide-to-unobtrusive-javascript-validation/ )


# Restructuring suggestion notes:
# Widgets shouldn't make a new array at the end, since this mostly copies existing data. Instead enable the settings to be looked up, with only genuinely-new output stuff added
# Widget parameters are of two types: generic (e.g. 'discard') and specific. So the generic stuff should be migrated out of the per-widget handling.
# The $elementValue = $widget->getValue (); passing is confusing

?>
