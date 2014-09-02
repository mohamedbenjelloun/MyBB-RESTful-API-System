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
class DateAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Date",
			"description" => "This API exposes date utility, very useful for external systems.",
			"default" => "activated"
		);
	}
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb;
		$stdClass = new stdClass();
		$timestamp = "";
		if(isset($mybb->input["timestamp"])) {
			$timestamp = (string) $mybb->input["timestamp"];
		}
		$ty = 1;
		if(isset($mybb->input["ty"]) && in_array($mybb->input["ty"], array("0", "1"))) {
			$ty = (int) $mybb->input["ty"];
		}
		
		$stdClass->date = my_date($mybb->settings['dateformat'], $timestamp, "", $ty);
		$stdClass->time = my_date($mybb->settings['timeformat'], $timestamp, "", $ty);
		$stdClass->timestamp = $timestamp;
		
		return $stdClass;
	}

}
