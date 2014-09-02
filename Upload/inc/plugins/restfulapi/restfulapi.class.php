<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
This interface should be implemented by APIs, see VersionAPI for a simple example.
*/
abstract class RESTfulAPI {
	
	/**
	This object will be automatically populated if the user has been authenticated (whether it's mandatory to authenticate (requiresAuth() returns true), or not).
	This object contains the following keys :
	- uid @string
	- username @string
	- password @string
	- rawpassword @string !!WATCH!!NEVER STORE THIS INFORMATION
	- email @string
	- usergroup @string
	- additionalgroups @string (seperated by comma ,)
	- displaygroup @string
	- ismoderator @boolean
	*/
	protected $user;
	
	/*
	 This function should return an array containing the name and the description of the API
	*/
	public function info() {
		return array(
			"name" => null,
			"description" => null,
			"default" => "activated" // this should be either "activated" or "deactivated", it means that by default this API should be activated or deactivated, for APIs exposing sensitive data for example, this should be "deactivated".
		);
	}
	
	/*
	This function is called after when the API is first detected AND ONLY IF the "default" attribute returned by info() method is different than "activated".
	This function is called everytime the admin re-activates the API after deactivation.
	*/
	public function activate() {
	}
	
	/*
	This function is called before uninstall IF the API is already installed and activated.
	This function is called everytime the admin deactivates the API.
	*/
	public function deactivate() {
	}
	
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass or an array.
	*/
	public abstract function action();
	
	/**
	Return TRUE in case your API requires authentication, if set to TRUE the API System will populate the $user object that could be used internally to identify the 
	authenticated user.
	*/
	public function requires_auth() {
		return false;
	}
	
	/**
	Returns TRUE if a user has been successfully authenticated, you can then access $user to get user information.
	*/
	public function is_authenticated() {
		return APISystem::get_instance()->is_user_authenticated();
	}
	
	/**
	Getter not used by the API System
	*/
	public function get_user() {
		return APISystem::get_instance()->get_auth_user_object();
	}
}
