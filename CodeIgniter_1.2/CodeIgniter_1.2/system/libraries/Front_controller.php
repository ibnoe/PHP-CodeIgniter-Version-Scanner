<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
|==========================================================
| Code Igniter - by pMachine
|----------------------------------------------------------
| www.codeignitor.com
|----------------------------------------------------------
| Copyright (c) 2006, pMachine, Inc.
|----------------------------------------------------------
| This library is licensed under an open source agreement:
| www.codeignitor.com/docs/license.html
|----------------------------------------------------------
| File: index.php
|----------------------------------------------------------
| Purpose: System front controller
|==========================================================
*/

define('APPVER', '1.2');

// Some PHP versions (4.3.1) don't support this 
if ( ! defined('E_STRICT'))
{
	define('E_STRICT', 2048);
}

/*
|------------------------------------------------
| Set Error Handler
|------------------------------------------------
|
| This lets us log error messages.
|
*/
set_error_handler('exception_handler');

/*
|------------------------------------------------
| Disable magic quotes
|------------------------------------------------
|
| This lets us run queries without having to
| escape the data.
|
*/
set_magic_quotes_runtime(0);

/*
|------------------------------------------------
| Start the Timer... tick tock tick tock...
|------------------------------------------------
*/
require_once(BASEPATH.'libraries/Benchmark'.EXT);	
$BM = new _Benchmark('code_igniter_start');

/*
|------------------------------------------------
| Instantiate the Config File class
|------------------------------------------------
|
*/
require_once(APPPATH.'config/config'.EXT);

if ( ! isset($config) OR ! is_array($config))
{
	show_error('Your config file does not appear to be formatted correctly.');
}

require_once(BASEPATH.'libraries/Config'.EXT);	
$CFG = new _Config();

/*
|------------------------------------------------
| Determine the routing
|------------------------------------------------
|
| The Router class will parse the URI and the
| routing matrix to determine which app controller
| should be loaded.
|
*/
require_once(BASEPATH.'libraries/Router'.EXT);	
$RTR = new _Router($CFG->item('uri_protocol'));

/*
|------------------------------------------------
| Load the Output Class
|------------------------------------------------
|
| We do this now only in case there is a cached
| web page to show.  Normally the output class
| isn't needed until the end of system execution,
| but if there is a cache we'll need it next
|
*/
require_once(BASEPATH.'libraries/Output'.EXT);
$OUT = new _Output();

/*
|------------------------------------------------
| Is there a cached file?
|------------------------------------------------
|
| If the current URI request matches a cached 
| file we'll retrieve it send it to the output
| class... and then bail out. 
|
*/

$cache_file = md5($config['base_url'].$config['index_page'].$RTR->uri_string);
$cache_path = ($config['cache_path'] == '') ? BASEPATH.'cache/' : $config['cache_path'];

if (@file_exists($cache_path.$cache_file))
{
	/*
	|------------------------------------------------
	| Looks like we have a cache file
	|------------------------------------------------
	|
	| We'll enable output buffering and grab it.
	|
	*/
	ob_start();
	include($cache_path.$cache_file);
	$cache = ob_get_contents();					
	ob_end_clean(); 

	/*
	|------------------------------------------------
	| Has the file expired?
	|------------------------------------------------
	|
	| We'll strip out the embedded timestamp and see.  
	| If it has expired we'll delete it and continue 
	| on with our normal system execution. If it hasn't 
	| expired  we'll display it and exit.
	|
	*/
	if (preg_match("/(\d+TS--->)/", $cache, $match))
	{		
		if (time() >= str_replace('TS--->', '', $match['1']))
		{
			@unlink($cache_path.$cache_file);
			log_message('debug', "Cache file has expired. File deleted");
		}
		else
		{
			$OUT->final_output = str_replace($match['0'], '', $cache);
			$OUT->display();
			log_message('debug', "Cache file is current. Sending it to browser and exiting.");
			exit;
		}
	}
}

/*
|------------------------------------------------
| Does the requested controller exist?
|------------------------------------------------
|
| If not, we'll show the 404 page
|
*/
if ( ! file_exists(APPPATH.'controllers/'.$RTR->fetch_class().EXT))
{
	show_404('controllers/'.$RTR->fetch_class().EXT);
}

/*
|------------------------------------------------
| Instantiate the Input class and filter globals
|------------------------------------------------
*/
require_once(BASEPATH.'libraries/Input'.EXT);	
$IN = new _Input($CFG->item('global_xss_filtering'));	

/*
|------------------------------------------------
| Instantiate the URI class
|------------------------------------------------
*/
require_once(BASEPATH.'libraries/URI'.EXT);
$URI = new _URI();

/*
|------------------------------------------------
| Load the controllers
|------------------------------------------------
|
| The parent/child controller classes do all
| the heavy lifting, so load em' up...
|
*/
require_once(BASEPATH.'libraries/Loader'.EXT);
require_once(BASEPATH.'libraries/Controller'.EXT);
require_once(APPPATH.'controllers/'.$RTR->fetch_class().EXT);

/*
|------------------------------------------------
| Instantiate the requested controller
|------------------------------------------------
|
| The Controller class will take it from here,
| executing the application routines.
|
*/

$class  = $RTR->fetch_class();
$method = $RTR->fetch_method();

if ( ! class_exists($class))
{
	show_404($class);
}

$CI = new $class();


/*
|------------------------------------------------
| Call the requested Method and display output
|------------------------------------------------
|
| If the method doesn't exist admonish them harshly.
|
*/
if ( ! method_exists($CI, $method))
{
	show_404($class.'/'.$method);
}
$CI->$method();

// Send it to the browser
$OUT->display();

// Close the DB connection if there is one
if ($CI->is_loaded('db'))
{
	$CI->db->close();
}


/*
| END OF SYSTEM EXECUTION ------------------------------->>
|
| Below are a few helper functions 
|
*/

/*
|==========================================================
| Get the CI object instance
|==========================================================
|
*/
function &get_instance()
{
	global $OBJ, $CI;
	
	if (is_object($CI))
	{
		return $CI;
	}
	else
	{
		if (isset($OBJ))
		{
			return $OBJ->load;
		}
	}
}

/*
|==========================================================
| Exception Handler
|==========================================================
|
| This is the custom exception handler we defined at the
| top of this file using set_error_handler();
| The only reason we use this is permit PHP errors to be
| logged in our own log files.  Without this, the only
| way to access logs is to have root server privileges
| which is rarely the case on standard hosting accounts.
|
*/

function exception_handler($severity, $message, $filename, $line)
{
	global $config;
	
	if ($config['log_errors'] === FALSE)
	{
		return;
	}

	/*
	| We don't log "strict" notices since this will fill up
	| the log file with information that isn't normally very
	| helpful.  For example, if you are running PHP 5 and you
	| use version 4 style class functions (without prefixes
	| like "public", "private", etc.) you'll get notices telling
	| you that these have been deprecated.
	*/
	
	if ($severity == E_STRICT)
	{
		return;
	}

	if ( ! class_exists('_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}

	$error = new _Exceptions();
	$error->log_exception($severity, $message, $filename, $line);
}

/*
|==========================================================
| Error Handler
|==========================================================
|
| This function lets us trigger the exception class manually
| so we can show our own errors using the standard error
| template.
|
*/
function show_error($message)
{
	if ( ! class_exists('_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}
	
	$error = new _Exceptions();
	echo $error->error_page('An Error Was Encountered', $message);
	exit;
}

/*
|==========================================================
| 404 Page Handler
|==========================================================
|
| This function lets us trigger the 404 template
|
*/
function show_404($page = '')
{
	if ( ! class_exists('_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}
	
	$error = new _Exceptions();
	$error->show_404_page($page);
	exit;
}

/*
|==========================================================
| Error Log Interface
|==========================================================
|
| We use this as a simple means to globally access 
| the logging class throughout the application.
|
*/
function log_message($level = 2, $message, $php_error = FALSE)
{
	global $config;
	
	if ($config['log_errors'] === FALSE)
	{
		return;
	}

	if ( ! class_exists('_Log'))
	{
		include_once(BASEPATH.'libraries/Log.php');		
	}
	
	if ( ! isset($LOG))
	{
		$LOG = new _Log(
					$config['log_path'], 
					$config['log_threshold'], 
					$config['log_date_format']
					);
	}
	
	$LOG->write_log($level, $message, $php_error);
}
?>