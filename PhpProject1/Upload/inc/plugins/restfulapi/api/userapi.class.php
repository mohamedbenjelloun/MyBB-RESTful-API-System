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
class UserAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "User",
			"description" => "This API exposes user interface.",
			"default" => "activated"
		);
	}
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb, $db, $cache;
		
		$api = APISystem::get_instance();
		
		if(isset($api->paths[1]) && is_string($api->paths[1])) {
			switch(strtolower($api->paths[1])) {
				case "list" :
				
				// Incoming sort field?
				if($mybb->input['sort'])
				{
					$mybb->input['sort'] = strtolower($mybb->input['sort']);
				}
				else
				{
					$mybb->input['sort'] = $mybb->settings['default_memberlist_sortby'];
				}

				switch($mybb->input['sort'])
				{
					case "regdate":
						$sort_field = "u.regdate";
						break;
					case "lastvisit":
						$sort_field = "u.lastactive";
						break;
					case "reputation":
						$sort_field = "u.reputation";
						break;
					case "postnum":
						$sort_field = "u.postnum";
						break;
					case "referrals":
						$sort_field = "u.referrals";
						break;
					default:
						$sort_field = "u.username";
						$mybb->input['sort'] = 'username';
						break;
				}

				// Incoming sort order?
				if($mybb->input['order'])
				{
					$mybb->input['order'] = strtolower($mybb->input['order']);
				}
				else
				{
					$mybb->input['order'] = strtolower($mybb->settings['default_memberlist_order']);
				}

				if($mybb->input['order'] == "ascending" || (!$mybb->input['order'] && $mybb->input['sort'] == 'username'))
				{
					$sort_order = "ASC";
					$mybb->input['order'] = "ascending";
				}
				else
				{
					$sort_order = "DESC";
					$mybb->input['order'] = "descending";
				}

				// Incoming results per page?
				$mybb->input['perpage'] = intval($mybb->input['perpage']);
				if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
				{
					$per_page = $mybb->input['perpage'];
				}
				else if($mybb->settings['membersperpage'])
				{
					$per_page = $mybb->input['perpage'] = intval($mybb->settings['membersperpage']);
				}
				else
				{
					$per_page = $mybb->input['perpage'] = 20;
				}

				$search_query = '1=1';

				// Limiting results to a certain letter
				if($mybb->input['letter'])
				{
					$letter = chr(ord($mybb->input['letter']));
					if($mybb->input['letter'] == -1)
					{
						$search_query .= " AND u.username NOT REGEXP('[a-zA-Z]')";
					}
					else if(strlen($letter) == 1)
					{
						$search_query .= " AND u.username LIKE '".$db->escape_string_like($letter)."%'";
					}
				}

				// Searching for a matching username
				$search_username = htmlspecialchars_uni(trim($mybb->input['username']));
				if($search_username != '')
				{
					$username_like_query = $db->escape_string_like($search_username);

					// Name begins with
					if($mybb->input['username_match'] == "begins")
					{
						$search_query .= " AND u.username LIKE '".$username_like_query."%'";
					}
					// Just contains
					else
					{
						$search_query .= " AND u.username LIKE '%".$username_like_query."%'";
					}
				}

				// Website contains
				$search_website = htmlspecialchars_uni($mybb->input['website']);
				if(trim($mybb->input['website']))
				{
					$search_query .= " AND u.website LIKE '%".$db->escape_string_like($mybb->input['website'])."%'";
				}

				// AIM Identity
				if(trim($mybb->input['aim']))
				{
					$search_query .= " AND u.aim LIKE '%".$db->escape_string_like($mybb->input['aim'])."%'";
				}

				// ICQ Number
				if(trim($mybb->input['icq']))
				{
					$search_query .= " AND u.icq LIKE '%".$db->escape_string_like($mybb->input['icq'])."%'";
				}

				// MSN/Windows Live Messenger address
				if(trim($mybb->input['msn']))
				{
					$search_query .= " AND u.msn LIKE '%".$db->escape_string_like($mybb->input['msn'])."%'";
				}

				// Yahoo! Messenger address
				if(trim($mybb->input['yahoo']))
				{
					$search_query .= " AND u.yahoo LIKE '%".$db->escape_string_like($mybb->input['yahoo'])."%'";
				}

				$query = $db->simple_select("users u", "COUNT(*) AS users", "{$search_query}");
				$num_users = $db->fetch_field($query, "users");

				$page = intval($mybb->input['page']);
				if($page && $page > 0)
				{
					$start = ($page - 1) * $per_page;
				}
				else
				{
					$start = 0;
					$page = 1;
				}

				$query = $db->query("
					SELECT u.*, f.*
					FROM ".TABLE_PREFIX."users u
					LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
					WHERE {$search_query}
					ORDER BY {$sort_field} {$sort_order}
					LIMIT {$start}, {$per_page}
				");
				
				$return_array = new stdClass();
				$return_array->list = array();
				while($user = $db->fetch_array($query)) {
					$return_array->list[] = $user;
				}
				
				$return_array->count = $num_users;
				return $return_array;
				
				break;
				case "group" :
				$usergroups = $cache->read("usergroups");
				return array_values($usergroups);
				break;
				default :
				
				break;
			}
		}
	}
	
	private function action_list() {
		
	}
}
