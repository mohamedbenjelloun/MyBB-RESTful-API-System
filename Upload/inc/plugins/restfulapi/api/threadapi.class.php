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
class ThreadAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Thread",
			"description" => "This API exposes threads and posts.",
			"default" => "activated"
		);
	}
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb, $db;
		
		$api = APISystem::get_instance();
		if(isset($api->paths[1]) && is_string($api->paths[1])) {
			switch (strtolower($api->paths[1])) {
				case "list" :
					if(isset($api->paths[2]) && is_string($api->paths[2]) && isset($forums[$api->paths[2]])) {
						return (object) $forums[$api->paths[2]];
					}
					else {
						return (object) $forums;
					}
				break;
				case "posts" :
					if(isset($api->paths[2]) && is_string($api->paths[2])) {
						$posts = array();
						$tid = $db->escape_string($api->paths[2]);
						$query = $db->write_query("SELECT * FROM ".TABLE_PREFIX."posts p WHERE p.`tid` = '{$tid}'");
						while($post = $db->fetch_array($query)) {
							$posts[$post["pid"]] = $post;
						}
						return (object) $posts;
					}
					else {
						// what forum?
					}
				break;
				case "permissions" :
					$forumpermissions = forum_permissions();
					return (object) $forumpermissions;
				default:
				break;
			}
		}
		throw new BadRequestException("No valid option given in the URL.");
	}
}
