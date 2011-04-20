<?php

/**
 * Hermosa Framework
 *
 * Created as an exercise in doing things "the PHP way" 
 * (whatever the merits of that way might be.)
 * 
 * TODO: global error handler for live, routing with <variable> segments, caching helper
 * 
 * @author Daniel Leavitt
 * @version $Id$
 * @package hermosa
 */

/**
 * Initializes the environment
 * 
 * - Tries to determine environment based on SERVER_NAME and passed environment settings.
 * - Uses this env to set up the appropriate config, error visibility, etc.
 * - Routes request (calls appropriate "action_" based on "__action" param)
 * 
 * @param array an array of settings
 * @return void
 */
function run(array $settings)
{	
	// set env to live by default
	env('live');
	
	// loop through possible environments
	foreach (arr($settings, 'environment', array()) as $env_name => $env_matches)
	{
		if ( ! is_array($env_matches))
		{
			$env_matches = array($env_matches);
		}
		
		foreach ($env_matches as $env_match)
		{
			// does it match the "match string"?
			if (stripos($_SERVER['SERVER_NAME'], $env_match) !== FALSE)
			{
				// stop looking. return the first match we find
				env($env_name);
				break 2;
			}
		}
	}
	
	// turn error reporting on if we're not live
	error_reporting(E_ALL);
	ini_set('display_errors', env() == 'local');
	
	// set config items corresponding to current env
	foreach (arr($settings, 'config', array()) as $conf_name => $conf_item)
	{
		conf($conf_name, $conf_item);
	}
	
	// replace '/' with '_' to determine the name of the function to call
	$params = explode('/', trim(arr($_GET, '__action', 'index'), '/'));
	$action = 'action_'.array_shift($params);
	
	// get that shit out of the $_GET array
	unset($_GET['__action']);
	if (function_exists($action))
	{
		echo count($params) ? call_user_func_array($action, $params) : $action();
	}
	else
	{
		// if we can't find the function, do a 404 error
		header(arr($_SERVER, 'SERVER_PROTOCOL', 'HTTP/1.1').' 404 Not Found');
		echo 'Action: '.$action.' could not be found.';
	}	
}

/**
 * Get or set the name of the current environment
 * 
 * @param string environment name (optional)
 * @return string current environment name
 */
function env($new_env = NULL)
{
	static $env;
	if ($new_env !== NULL) $env = $new_env;
	return ($env === NULL) ? 'default' : $env;
}

/**
 * Get or set a config variable. Supplying an array allows
 * multilevel queries of the config object.
 * 
 * @param string name of the config variable to set or get
 * @param value if set, sets the config variable to this value
 * @return value value of the config variable
 */
function conf($key, $value = NULL)
{
	static $conf;
	if ($value === NULL)
	{
		if (is_array($key))
		{
			$arr = conf(array_shift($key));
			while (count($key))
			{
				$arr = $arr[array_shift($key)];
			}
			return $arr;
		}
		else
		{
			if (is_array($conf[$key]))
			{
				return arr($conf[$key], env(), arr($conf[$key], 'default'));
			}
			else
			{
				return $conf[$key];
			}
			
		}
		
	}
	else
	{
		$conf[$key] = $value;
	}
}

//---------------------------------------
//  SERVICES
//---------------------------------------

/**
 * Helper to generate a new PDO connection based on 'database' in settings array
 * 
 * @return PDO pdo connection
 */
function pdo_connect()
{
	static $pdo;
	if ($pdo) return $pdo;
	
	$conf = conf('db');
	$pdo = new PDO("mysql:host={$conf['host']};dbname={$conf['db']}", $conf['user'], $conf['pass']);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
	return $pdo;
}

function cache_get($slug)
{
	$conf = conf('cache');
	$table = arr($conf, 'table', 'cache');
	$pdo = pdo_connect();
	
	$query = $pdo->prepare("SELECT * FROM $table WHERE slug = ?");
	$query->execute(array($slug));
	
	$response = $query->fetch(PDO::FETCH_ASSOC);
	
	if (arr($response, 'expires', 0) > time())
	{
		return unserialize(arr($response, 'data', FALSE));
	}
	else
	{
		cache_delete($slug);
		return FALSE;
	}
}

function cache_set($slug, $data, $lifetime = NULL)
{
	// could use some explicit error handling
	
	$conf = conf('cache');
	$table = arr($conf, 'table', 'cache');
	
	if ($lifetime === NULL)
	{
		$lifetime = arr($conf, 'lifetime', 3600);
	}
	
	$pdo = pdo_connect();
	$query = $pdo->prepare("REPLACE INTO $table (slug, data, expires) 
							VALUES (:slug, :data, :expires)");
	$count = $query->execute(array(
		':slug' => $slug,
		':data' => serialize($data),
		':expires' => time() + $lifetime,
	));
	return $count;
}

function cache_delete($slug)
{
	$conf = conf('cache');
	$table = arr($conf, 'table', 'cache');
	$pdo = pdo_connect();
	
	$query = $pdo->prepare("DELETE FROM $table WHERE slug = ?");
	$count = $query->execute(array($slug));
	
	return $count;
}

// TODO: Mail helper

//---------------------------------------
//  VIEW
//---------------------------------------

/**
 * Render a template with the variables passed!
 * 
 * @param string path to template. '.php' will be appended
 * @param array vars to pass to template
 * @return string content of rendered template
 */
function render($template, $vars = array(), $buffer = TRUE)
{
	extract($vars);
	if ($buffer) ob_start();
	include($template.'.php');
	if ($buffer) 
	{
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}

/**
 * Really simple json response wrapper.
 * 
 * @param value a boolean, or a string if you want to get fancy
 * @param value data to be encoded 
 * @return string json-encoded response
 */
function send_response($status, $data = array())
{
	$json = str_replace('\\/', '/', json_encode(array('status' => $status, 'data' => $data)));
	if ($callback = arr($_REQUEST, 'callback'))
	{
		$json = $callback.'('.$json.')';
	}
	return $json;
}

//---------------------------------------
//  MISC HELPERS
//---------------------------------------

function arr($array, $key, $default = NULL)
{
	return isset($array[$key]) ? $array[$key] : $default;
}

function debug($var)
{
	echo '<pre>';
	print_r($var);
	echo '</pre>';
}
