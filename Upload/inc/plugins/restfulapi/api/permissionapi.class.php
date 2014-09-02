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
class PermissionAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Permission",
			"description" => "This API exposes permission interface.",
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
			switch(strtolower($api->paths[1])) {
				case "moderation" :
				if(isset($mybb->input["forumid"]) && is_string($mybb->input["forumid"])) {
					$fid = $db->escape_string($mybb->input["forumid"]);
					return (object) forum_permissions($fid, $this->get_user()->uid);
				}
				else {
					return (object) forum_permissions(0, $this->get_user()->uid);
				}
				break;
			}
		}
		else {
			return (object) user_permissions($this->get_user()->uid);
		}
	}
	
	public function requires_auth() {
		return true;
	}
	
	private function usergroup_add_tags(&$array) {
		global $cache;
		$usergroups = $cache->read("usergroups");
		foreach($array as $group_id => $forum_permission) {
			$forum_permission["usergroup"] = $usergroups[$group_id];
			$array[$group_id] = $forum_permission;
		}
		return $array;
	}
}
