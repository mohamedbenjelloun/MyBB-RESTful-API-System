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
class OnlineAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Online",
			"description" => "This API exposes the online users on the board.",
			"default" => "activated"
		);
	}
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb, $db, $cache;
			require_once MYBB_ROOT."inc/functions_online.php";
			
			$timesearch = TIME_NOW - $mybb->settings['wolcutoffmins']*60;
			
			switch($db->type)
			{
				case "sqlite":
					$sessions = array();
					$query = $db->simple_select("sessions", "sid", "time > {$timesearch}");
					while($sid = $db->fetch_field($query, "sid"))
					{
						$sessions[$sid] = 1;
					}
					$online_count = count($sessions);
					unset($sessions);
					break;
				case "pgsql":
				default:
					$query = $db->simple_select("sessions", "COUNT(sid) as online", "time > {$timesearch}");
					$online_count = $db->fetch_field($query, "online");
					break;
			}
			
			$query = $db->query("
			SELECT DISTINCT s.sid, s.ip, s.uid, s.time, s.location, u.username, s.nopermission, u.invisible, u.usergroup, u.displaygroup
			FROM ".TABLE_PREFIX."sessions s
			LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
			WHERE s.time>'$timesearch'
			");
			
		//ORDER BY $sql
		//	LIMIT {$start}, {$perpage}
			
			$users = array();
			$guests = array();
			$spiders = $cache->read("spiders");
			while($user = $db->fetch_array($query)) {

				// Fetch the WOL activity
				$user['activity'] = fetch_wol_activity($user['location'], $user['nopermission']);

				$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));

				// Have a registered user
				if($user['uid'] > 0)
				{
					if($users[$user['uid']]['time'] < $user['time'] || !$users[$user['uid']])
					{
						$users[$user['uid']] = $user;
					}
				}
				// Otherwise this session is a bot
				else if(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
				{
					$user['bot'] = $spiders[$botkey]['name'];
					$user['usergroup'] = $spiders[$botkey]['usergroup'];
					$guests[] = $user;
				}
				// Or a guest
				else
				{
					$guests[] = $user;
				}
			}
			
			foreach($users as &$user) {
				$user["display"] = format_name($user["username"], $user["usergroup"], $user["displaygroup"]);
			}
			
			$stdClass = new stdClass();
			// remove keys from this otherwise we will get an object of objects, sigh!
			$stdClass->users = array_values($users);
			$stdClass->guests = $guests;
			$stdClass->count = $online_count;
			$stdClass->wolcutoffmins = $mybb->settings["wolcutoffmins"];
			$stdClass->mostonline = $cache->read("mostonline");
			
			return $stdClass;
	}

}
