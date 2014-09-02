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
class ForumAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Date",
			"description" => "This API exposes the forums and categories present in the board.",
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
			$forums = cache_forums();
			switch (strtolower($api->paths[1])) {
				case "list" :
					if(isset($api->paths[2]) && is_string($api->paths[2]) && isset($forums[$api->paths[2]])) {
						return (object) $forums[$api->paths[2]];
					}
					else {
						return (object) $forums;
					}
				break;
				case "threads" :
					if(isset($api->paths[2]) && is_string($api->paths[2]) && isset($forums[$api->paths[2]])) {
						$threads = array();
						$fid = $db->escape_string($api->paths[2]);
						$query = $db->write_query("SELECT * FROM ".TABLE_PREFIX."threads t WHERE t.`fid` = '{$fid}'");
						while($thread = $db->fetch_array($query)) {
							$threads[$thread["tid"]] = $thread;
						}
						return (object) $threads;
					}
					else {
						// what forum?
					}
				break;
				case "permissions" :
					if(isset($api->paths[2]) && is_string($api->paths[2]) && isset($forums[$api->paths[2]]) && $this->is_authenticated()) {
						return (object) forum_permissions($api->paths[2], $this->get_user()->id, $this->get_user()->usergroup);
					}
					else {
						//what forum?
					}
				default:
				break;
			}
		}
		throw new BadRequestException("No valid option given in the URL.");
	}
}
