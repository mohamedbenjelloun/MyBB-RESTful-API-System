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
This interface should be implemented by APIs, see VersionAPI for a simple example.
*/
class VersionAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Version",
			"description" => "This API exposes the version of the current forum.",
			"default" => "activated"
		);
	}
	
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb;
		$response = new stdClass();
		$response->version = $mybb->version;
		$response->version_code = $mybb->version_code;
		return $response;
	}

}
