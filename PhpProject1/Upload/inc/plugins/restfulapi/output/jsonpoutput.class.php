<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)
# 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
This interface should be implemented by Output options, see JSONOutput for a simple example.
*/
class JSONPOutput extends RESTfulOutput {
	
	/**
	This is where you output the object you receive, the parameter given is an instance of stdClass.
	*/
	public function action($stdClassObject) {
		$api = APISystem::get_instance();
		if($api->standard_method() == APISystem::HTTP_HEADER) {
			$jsonpcallback = $this->_jsonpcallback_from_http_header();
		}
		elseif($api->standard_method() == APISystem::URL_PARAMETER) {
			$jsonpcallback = $this->_jsonpcallback_from_url_parameter();
		}
		else {
			$jsonpcallback = $this->_jsonpcallback_from_both();
		}
		// if no callback function has been defined OR the one provided is invalid, return "callback"
		$jsonpcallback = is_null($jsonpcallback) || !self::_is_valid_jsonpcallback_function($jsonpcallback) ? "callback" : $jsonpcallback;
		header("Content-type: application/javascript");
		echo $jsonpcallback . "(";
		echo json_encode($stdClassObject);
		echo ")";
	}
	
	private function _jsonpcallback_from_http_header() {
		return isset($_SERVER["HTTP_CALLBACK"]) && is_string($_SERVER["HTTP_CALLBACK"]) ? $_SERVER["HTTP_CALLBACK"] : null;
	}
	
	private function _jsonpcallback_from_url_parameter() {
		global $mybb;
		return isset($mybb->input["callback"]) && is_string($mybb->input["callback"]) ? $mybb->input["callback"] : null;
	}
	
	private function _jsonpcallback_from_both() {
		$from_http_header = $this->_jsoncallback_from_http_header();
		return $from_http_header == null ? $this->_jsoncallback_from_url_parameter() : $from_http_header;
	}
	
	/**
	Sanitize jsonpcallback function
	Public in case another class wants to use that
	http://stackoverflow.com/questions/3128062/is-this-safe-for-providing-jsonp/3128948
	*/
	public static function _is_valid_jsonpcallback_function($function_name) {
		$identifier_syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';
		$reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
		'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
		'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
		'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
		'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
		'private', 'public', 'yield', 'interface', 'package', 'protected', 
		'static', 'null', 'true', 'false');
		return preg_match($identifier_syntax, $function_name)
         && ! in_array(strtolower($function_name), $reserved_words);
	}
}

