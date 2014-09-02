<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)

require_once MYBB_ROOT . 'inc/functions_user.php';
require_once 'restfulexception.class.php';
require_once 'restfuloutput.class.php';
require_once 'restfulapi.class.php';

/*
require exceptions
*/
foreach(glob(MYBB_ROOT . "inc/plugins/restfulapi/exception/*.class.php") as $class) {
	require_once $class;
}

class APISystem {
	
	const HTTP_HEADER = 0;
	const URL_PARAMETER = 1;
	const BOTH = 2;
	const DATE_FORMAT = 'Y-m-d H:i:s';
	
	private $outputer;
	public $paths;
	
	private $settings = array();
	private $settings_performed = false;
	private $settings_changed = false;
	
	private $api_instance;
	
	private $user_authenticated;
	private $auth_user_object;
	private $authentication_performed = false;
	
	private static $instance = null;
	
	/**
	 * Set or update a setting with a value
	 * The setting value might be anything (int, string, array, object or anything) as it would be serialized before saving.
	 * @return mixed This method would return the old value that was assigned to $setting_name if any, null will be returned otherwise.
	 */
	public function set_setting($setting_name, $setting_value) {
		$this->_perform_settings();
		$old_val = isset($this->settings[$setting_name]) ? $this->settings[$setting_name] : null;
		$this->settings[$setting_name] = $setting_value;
		$this->settings_changed = true;
		return $old_val;
	}
	
	/**
	 * Get a setting, if the setting doesn't exist, this function will return $default_return, change that to something else if you want to have a different result.
	 * If you want to check if a setting exists, use exists_setting($setting_name).
	 */
	public function get_setting($setting_name, $default_return = null) {
		$this->_perform_settings();
		return isset($this->settings[$setting_name]) ? $this->settings[$setting_name] : $default_return;
	}
	
	/**
	 * Delete a setting, if the setting doesn't exist this function won't perform any action
	 * @return mixed This method would return the value that was assigned to $setting_name if nay, null will be returned otherwise.
	 */
	public function delete_setting($setting_name) {
		$this->_perform_settings();
		if(isset($this->settings[$setting_name])) {
			$old_val = $this->settings[$setting_name];
			unset($this->settings[$setting_name]);
			return $old_val;
		}
		else {
			return null;
		}
	}
	
	/**
	 * Check if a setting exists already
	 */
	public function exists_setting($setting_name) {
		$this->_perform_settings();
		return isset($this->settings[$setting_name]);
	}
	
	/**
	 * This method saves the changed settings, if nothing has changed this method won't do anything. Please do not call this yourself, rather let the APISystem call it, unless you know
	 * what you're doing.
	 */
	public function save_settings() {
		global $cache, $db;
		if($this->settings_changed) {
			$restfulapi_cache = $cache->read("restfulapi");
			$db->delete_query("apisettings", "'default' = '0'"); // delete all non-default settings.
			$inserts = array();
			foreach($this->settings as $name => $value) {
				$serialized_value = serialize($value);
				$inserts[] = array(
					"apiaction" => $db->escape_string($name),
					"apivalue" => $db->escape_string(serialize($value)),
					"isdefault" => 0
				);
				$restfulapi_cache["settings"][$name] = $serialized_value;
			}
			if(count($inserts) >= 1) {
				$db->insert_query_multiple("apisettings", $inserts);
			}
			$cache->update("restfulapi", $restfulapi_cache);
			// performed settings change, now reset that to false.
			$this->settings_changed = false;
		}
	}
	
	/**
	 * Redirect to the index page, outputs the message $message before.
	 * @string $message
	 */
	public function redirect_index($message) {
		global $settings;
		redirect($settings["bburl"] . "/" . INDEX_URL, $message);
		exit;
	}
	
	/**
	 * Returns true if the API System is installed, activated and enabled, false otherwise.
	 */
	public function is_active() {
		global $settings;
		return defined("IN_RESTFULAPI") && isset($settings["enablerestfulapi"]) && $settings["enablerestfulapi"] == "1";
	}
	
	public function check_api_key_access_limit() {
		global $db, $cache;
		$restfulapi_cache = $cache->read("restfulapi");
		$api_key = $this->declared_api_key();
		$keyset = $restfulapi_cache["keys"][$api_key];
		// if counter's limit = 0 (unlimited), return true ASAP
		if((int) $keyset["maxreq"] == 0) {
			return true;
		}
		$date_now = my_date(self::DATE_FORMAT);
		// if we have never used this API key, set first access to NOW
		// and return true, we should increment this later
		if(empty($keyset["maxreqfirstaccess"])) {
			$db->update_query("apikeys", array(
				"maxreqcounter" => 0, // do we need to escape constants? be totally paranoid? :D
				"maxreqfirstaccess" => $db->escape_string($date_now) // as always, we never know
			), "id='{$db->escape_string($keyset['id'])}'");
			// we don't need to run the time-expensive restfulapi_cache_rebuild() function, just update it manually
			$restfulapi_cache["keys"][$api_key]["maxreqcounter"] = 0;
			$restfulapi_cache["keys"][$api_key]["maxreqfirstaccess"] = $date_now;
			$cache->update("restfulapi", $restfulapi_cache);
			return true;
		}
		// check if we've passed the limit date, then reset it to now and return true
		$date = $keyset["maxreqfirstaccess"];
		$type = $keyset["maxreqrate"];
		$count = TIME_NOW - strtotime($date);
		$passed = false;
		switch ($type) {
			case "m":
			// 30 days, 24 hours a day, 60 minutes an hour, 60 seconds a minute
			$passed = $count >= 30*24*60*60;
			break;
			case "w":
			$passed = $count >= 7*24*60*60;
			break;
			case "d":
			$passed = $count >= 24*60*60;
			break;
			case "h":
			$passed = $count >= 60*30;
			break;
			default:
			// we should't arrive here anyway, so if we by any null chance we're here, leave $passed = false.
			break;
		}
		
		if($passed) {
			// ok passed, update
			$db->update_query("apikeys", array(
				"maxreqcounter" => 0,
				"maxreqfirstaccess" => $db->escape_string($date_now) // as always, we never know
			), "id='{$db->escape_string($keyset['id'])}'");
			$restfulapi_cache["keys"][$api_key]["maxreqcounter"] = 0;
			$restfulapi_cache["keys"][$api_key]["maxreqfirstaccess"] = $date_now;
			$cache->update("restfulapi", $restfulapi_cache);
			return true;
		}
		else {
			// didn't pass, have we reached the limit?
			return $keyset["maxreqcounter"] < $keyset["maxreq"];
		}
	}
	
	public function api_key_increment_counters() {
		global $db, $cache;
		$restfulapi_cache = $cache->read("restfulapi");
		$api_key = $this->declared_api_key();
		$restfulapi_cache["keys"][$api_key]["access"]++;
		// only incremented if we choosed to set an access limit
		if(! empty($restfulapi_cache["keys"][$api_key]["maxreqfirstaccess"])) {
			$restfulapi_cache["keys"][$api_key]["maxreqcounter"]++;
		}
		$db->update_query("apikeys", array(
			"access" => $db->escape_string($restfulapi_cache["keys"][$api_key]["access"]),
			"maxreqcounter" => $db->escape_string($restfulapi_cache["keys"][$api_key]["maxreqcounter"])
		), "id='{$db->escape_string($restfulapi_cache['keys'][$api_key]['id'])}'");
		$cache->update("restfulapi", $restfulapi_cache);
	}
	
	/**
	 * Is the active request made over a HTTPS connection?
	 */
	public function is_https() {
		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
	}
	
	/**
	 * Did the admin choose to activate the API System over HTTPS only?
	 */
	public function requires_https() {
		global $settings;
		return isset($settings["apihttpsonly"]) ? $settings["apihttpsonly"] == "1" : false;
	}
	
	/**
	 * Returns standard method (one of : APISystem::HTTP_HEADER, APISystem::URL_PARAMETER or APISystem::BOTH) based on the admin settings
	 * @return int
	 */
	public function standard_method() {
		global $settings;
		return intval($settings["apirequestmethod"]);
	}
	
	/**
	 * Returns the key length based on the admin settings
	 * @return int
	 */
	public function key_length() {
		global $settings;
		$keylengths = array(0 => 8, 1 => 16, 2 => 32, 3 => 64);
		return $keylengths[$settings["apikeylength"]];
	}
	
	/**
	 * Returns the declared API key from the active request, based on the admin settings, for example if the admin choosed HTTP Header and the user sent the api key 
	 * via a URL Parameter, this method wouldn't take that parameter into consideration.
	 * @return string the API key declared in the active request, null if nothing found or not in compliance with the admin settings.
	 */
	public function declared_api_key() {
		switch($this->standard_method()) {
			case self::HTTP_HEADER:
			return $this->_declared_api_key_from_http_header();
			break;
			case self::URL_PARAMETER:
			return $this->_declared_api_key_from_url_parameter();
			break;
			default:
			return $this->_declared_api_key_from_both();
			break;
		}
	}
	
	/**
	 * Returns the declared username in the active request, based on the admin settings. Null if nothing found.
	 * @return string the declared username.
	 */
	public function declared_user() {
		switch($this->standard_method()) {
			case self::HTTP_HEADER:
			return $this->_declared_user_from_http_header();
			break;
			case self::URL_PARAMETER:
			return $this->_declared_user_from_url_parameter();
			break;
			default:
			return $this->_declared_user_from_both();
			break;
		}
	}
	
	/**
	 * Returns the declared action in the active request, null if nothing was found.
	 */
	public function declared_action() {
		return isset($this->paths[0]) ? $this->paths[0] : null;
	}
	
	/**
	 * Returns the declared password in the active request, null if nothing was found.
	 */
	public function declared_pwd() {
		switch($this->standard_method()) {
			case self::HTTP_HEADER:
			return $this->_declared_pwd_from_http_header();
			break;
			case self::URL_PARAMETER:
			return $this->_declared_pwd_from_url_parameter();
			break;
			default:
			return $this->_declared_pwd_from_both();
			break;
		}
	}
	
	/**
	 * Returns the declared output in the active request, null if nothing was found.
	 */
	public function declared_output() {
		switch($this->standard_method()) {
			case self::HTTP_HEADER:
			return $this->_declared_output_from_http_header();
			break;
			case self::URL_PARAMETER:
			return $this->_declared_output_from_url_parameter();
			break;
			default:
			return $this->_declared_output_from_both();
			break;
		}
	}
	
	/**
	 * Returns true if there is a API key declared in the active request, and it's valid. False otherwise.
	 */
	public function is_valid_api_key() {
		global $cache;
		$api_key = $this->declared_api_key();
		if(is_null($api_key) || !is_string($api_key)) {
			return false;
		}
		else {
			$restfulapi_cache = $cache->read("restfulapi");
			$api_keys = $restfulapi_cache["keys"];
			return isset($api_keys[$api_key]);
		}
	}
	
	/**
	 * Performs a RESTfulException instance, based on the current output class.
	 */
	public function perform_exception(RESTfulException $ex) {
		header('X-PHP-Response-Code: ' . $ex->getErrorCode(), true, $ex->getErrorCode());
		$outputer = $this->build_outputer();
		$outputer->action($ex->getExceptionObject());
		exit;
	}
	
	/**
	 * Builds the outputer (RESTfulOutput instance), or create a JSONOutput if nothing was found. If already built, that same instance is returned.
	 */
	public function build_outputer() {
		if(null === $this->outputer) {
			$declared_output = $this->declared_output();
			if(is_string($declared_output)) {
				if(file_exists(MYBB_ROOT . "inc/plugins/restfulapi/output/" . strtolower($declared_output) . "output.class.php")) {
					require_once "output/" . strtolower($declared_output) . "output.class.php";
					$outputclass = strtolower($declared_output) . "output";
					$this->outputer = new $outputclass;
					return $this->outputer;
				}
			}
			require_once "output/jsonoutput.class.php";
			$outputclass = "jsonoutput";
			$this->outputer = new $outputclass;
		}
		return $this->outputer;
	}
	
	/**
	 * Builds the API instance, based on the action parameter in the request (if any), returns the instance or NULL if any error happens. If already built, the same instance is returned.
	 */
	public function build_api_instance() {
		return $this->_build_api_instance_from_class();
	}
	
	/**
	 * Check if the user specified in the request is authenticated.
	 */
	public function is_user_authenticated() {
		$this->_authenticate_user();
		return $this->user_authenticated;
	}
	
	/**
	 * Get the authenticated user object, if the user is authenticated, null otherwise.
	 */
	public function get_auth_user_object() {
		$this->_authenticate_user();
		return $this->auth_user_object;
	}
	
	/**
	 * Call an API if you're using this inside your application
	 * @string $api The API name, or path for example : "thread/path2/path3"
	 * @array $options ["username" => username, "password" => password], "output", "username" and "password" are optional
	 * @array $parameters optional, parameters to pass to the API
	 * @return stdClass object the result of the API
	 * @throws RESTfulException is the API throws an error
	 */
	public function call($api,$parameters=array(),$options=array()) {
		global $mybb;
		$apiclone = new self($api);
		$apiclass = strpos($api, "/") !== false ? substr($api, 0, strpos($api, "/")) : $api;
		$api_instance = $apiclone->_build_api_instance_from_class($apiclass);
		if(empty($api_instance)) {
			throw new UnauthorizedException($lang->restfulapi_no_permission);
		}
		else {
			if(isset($options["username"]) && is_string($options["username"]) && isset($options["password"]) && is_string($options["password"])) {
				$apiclone->_authenticate_user($options["username"], $options["password"]);
			}
			$inputclone = $mybb->input;
			$mybb->input = array_merge($mybb->input, $parameters);
			$result = $api_instance->action();
			$mybb->input = $inputclone;
			return $result;
		}
	}
	
	private function __construct($pathinfo=null) {
		$pathinfo = is_null($pathinfo) && isset($_SERVER["PATH_INFO"]) && is_string($_SERVER["PATH_INFO"]) ? $_SERVER["PATH_INFO"] : $pathinfo;
		if(! is_null($pathinfo)) {
			$paths = explode("/", $pathinfo);
			$this->paths = array();
			// can't use array_walk for compatibility issue
			foreach($paths as $path) {
				if($path != "") {
					$this->paths[] = $path;
				}
			}
		}
		else {
			$this->paths = array();
		}
	}
	
	private function __clone() {
	
	}
	
	/**
	 * Get single instance of the APISystem class
	 */
	public static function get_instance() {
		if(null === self::$instance) {
			self::$instance = new APISystem();
		}
		return self::$instance;
	}
	
	private function _declared_api_key_from_http_header() {
		return isset($_SERVER["HTTP_APIKEY"]) ? $_SERVER["HTTP_APIKEY"] : null;
	}
	
	private function _declared_api_key_from_url_parameter() {
		global $mybb;
		return isset($mybb->input["apikey"]) ? $mybb->input["apikey"] : null;
	}
	
	private function _declared_api_key_from_both() {
		$from_http_header = $this->_declared_api_key_from_http_header();
		return is_null($from_http_header) ? $this->_declared_api_key_from_url_parameter() : $from_http_header;
	}
	
	private function _declared_user_from_http_header() {
		$auth_user = isset($_SERVER["PHP_AUTH_USER"]) && is_string($_SERVER["PHP_AUTH_USER"]) ? $_SERVER["PHP_AUTH_USER"] : null;
		return is_null($auth_user) && isset($_SERVER["HTTP_USERNAME"]) && is_string($_SERVER["HTTP_USERNAME"]) ? $_SERVER["HTTP_USERNAME"] : $auth_user;
	}
	
	private function _declared_user_from_url_parameter() {
		global $mybb;
		return isset($mybb->input["username"]) ? $mybb->input["username"] : null;
	}
	
	private function _declared_user_from_both() {
		$from_http_header = $this->_declared_user_from_http_header();
		return is_null($from_http_header) ? $this->_declared_user_from_url_parameter() : $from_http_header;
	}
	
	private function _declared_pwd_from_http_header() {
		$auth_pwd = isset($_SERVER["PHP_AUTH_PW"]) && is_string($_SERVER["PHP_AUTH_PW"]) ? $_SERVER["PHP_AUTH_PW"] : null;
		return is_null($auth_pwd) && isset($_SERVER["HTTP_PASSWORD"]) && is_string($_SERVER["HTTP_PASSWORD"]) ? $_SERVER["HTTP_PASSWORD"] : $auth_pwd;
	}
	
	private function _declared_pwd_from_url_parameter() {
		global $mybb;
		return isset($mybb->input["password"]) ? $mybb->input["password"] : null;
	}
	
	private function _declared_pwd_from_both() {
		$from_http_header = $this->_declared_pwd_from_http_header();
		return is_null($from_http_header) ? $this->_declared_pwd_from_url_parameter() : $from_http_header;
	}
	
	private function _declared_output_from_http_header() {
		return isset($_SERVER["HTTP_OUTPUT"]) ? $_SERVER["HTTP_OUTPUT"] : null;
	}
	
	private function _declared_output_from_url_parameter() {
		global $mybb;
		return isset($mybb->input["output"]) ? $mybb->input["output"] : null;
	}
	
	private function _declared_output_from_both() {
		$from_http_header = $this->_declared_output_from_http_header();
		return is_null($from_http_header) ? $this->_declared_output_from_url_parameter() : $from_http_header;
	}
	
	private function _authenticate_user($username=null,$password=null) {
		if($this->authentication_performed) {
			return;
		}
		$username = empty($username) ? $this->declared_user() : $username;
		$password = empty($password) ? $this->declared_pwd() : $password;
		
		if(!is_string($username) || !is_string($password)) {
			$this->user_authenticated = false;
			$this->auth_user_object = null;
		}
		$result = validate_password_from_username($username, $password);
		if(!is_array($result)) {
			$this->user_authenticated = false;
			$this->auth_user_object = null;
		}
		else {
			$this->user_authenticated = true;
			$this->auth_user_object = (object) $result;
		}
		$this->authentication_performed = true;
	}
	
	private function _build_api_instance_from_class($class=null) {
		global $cache;
		if(null == $this->api_instance) {
			$declared_action = is_null($class) ? $this->declared_action() : $class;
			if(! is_null($declared_action) && file_exists(MYBB_ROOT . "inc/plugins/restfulapi/api/" . $declared_action . "api.class.php")) {
				$deactivated = false;
				$restfulapi_cache = $cache->read("restfulapi");
				$deactivatedapis = $restfulapi_cache["settings"];
				foreach($deactivatedapis as $deactivatedapi) {
					if($deactivatedapi["apiaction"] == "deactivate" && $deactivatedapi["apivalue"] == $declared_action) {
						$deactivated = true;
						break;
					}
				}
				if(! $deactivated) {
					$declared_api_key = is_null($class) ? $this->declared_api_key() : null;
					if(!is_null($class) ||
						(isset($restfulapi_cache["keys"][$declared_api_key]["permissions"]) && is_array($restfulapi_cache["keys"][$declared_api_key]["permissions"])
						&& in_array($declared_action, $restfulapi_cache["keys"][$declared_api_key]["permissions"]))) {
						require_once "api/" . $declared_action . "api.class.php";
						$apiclass = $declared_action . "api";
						$this->api_instance = new $apiclass;
					}
				}
			}
		}
		return $this->api_instance;
	}
	
	
	private function _perform_settings() {
		global $cache;
		if($this->settings_performed) {
			return;
		}
		$restfulapi_cache = $cache->read("restfulapi");
		foreach($restfulapi_cache["settings"] as $setting_id => $setting) {
			if($setting["default"] == 0) {
				$this->settings[$setting["apiaction"]] = unserialize($setting["apivalue"]);
			}
		}
		$this->settings_performed = true;
	}
	
	
	
	public function __destruct() {
		$this->save_settings();
	}
}
