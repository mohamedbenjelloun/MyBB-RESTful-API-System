<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define("IN_RESTFULAPI", true);
define("RESTFULAPI_URL", "restfulapi");
define('RESTFULAPI_PATH', MYBB_ROOT.'inc/plugins/restfulapi/');

require_once MYBB_ROOT . "/inc/plugins/restfulapi/apisystem.class.php";

$plugins->add_hook("admin_config_menu", "restfulapi_config_menu");
$plugins->add_hook("admin_config_action_handler", "restfulapi_admin_config_action_handler");
$plugins->add_hook("admin_load", "restfulapi_admin_load");

function restfulapi_info() {
	return array(
		"name"			=> "RESTful API System",
		"description"	=> "A RESTful API system for MyBB that deploys web services for the purpose of integration with other systems.",
		"website"		=> "http://mybb.com",
		"author"		=> "TheGarfield",
		"authorsite"	=> "http://mybb.com",
		"version"		=> "0.2",
		"guid" 			=> "",
		"compatibility" => "16*,17*,18*"
	);
}

function restfulapi_install() {
	global $db;
	
	$tables = array();
	$collation = $db->build_create_table_collation();
	
	if(! $db->table_exists("apisettings")) {
		$tables["apisettings"] = "CREATE TABLE " . TABLE_PREFIX . "apisettings (
		  id int unsigned NOT NULL auto_increment,
		  apiaction varchar(20) NOT NULL default '',
		  apivalue varchar(250) NOT NULL default '',
		  isdefault smallint unsigned NOT NULL default 1,
		  PRIMARY KEY (id)
		) ENGINE=MyISAM{$collation};";
	}
	
	if(! $db->table_exists("apikeys")) {
		$tables["apikeys"] = "CREATE TABLE " . TABLE_PREFIX . "apikeys (
		  id int unsigned NOT NULL auto_increment,
		  apikey varchar(150) NOT NULL default '',
		  apicustomer varchar(150) NOT NULL default '',
		  apicomment mediumtext,
		  access int unsigned NOT NULL default 0,
		  maxreq int unsigned NOT NULL default 0,
		  maxreqrate char(1) NOT NULL default 'm',
		  maxreqcounter int unsigned NOT NULL default 0,
		  maxreqfirstaccess varchar(20),
		  PRIMARY KEY (id)
		) ENGINE=MyISAM{$collation};
		";
	}
	
	if(! $db->table_exists("apipermissions")) {
		$tables["apipermissions"] = "CREATE TABLE " . TABLE_PREFIX . "apipermissions (
		  id int unsigned NOT NULL auto_increment,
		  apikey int unsigned NOT NULL default 0,
		  apiname varchar(150) NOT NULL default '',
		  PRIMARY KEY (id)
		) ENGINE=MyISAM{$collation};
		";
	}
	
	foreach($tables as $key => $query) {
		$db->write_query($query);
	}
	
	$query = $db->simple_select("settinggroups", "COUNT(*) as rows");
	$rows = $db->fetch_field($query, "rows");
	
	$settinggroups = array(
		"name" => "restfulapi",
		"title" => "RESTful API System",
		"description" => "These settings allow you to control your RESTful API System",
		"disporder" => $rows+1,
		"isdefault" => 0
	);
	
	$gid = $db->insert_query("settinggroups", $settinggroups);
	
	$settings = array();
	
	$settings[0] = array(
		"name" => "enablerestfulapi",
		"title" => "Enable RESTful API System",
		"description" => "If you wish to disable the RESTful API System on your board, switch this setting to no.",
		"optionscode" => "yesno",
		"value" => "1",
		"disporder" => "1",
		"gid" => $gid
	);
	
	$settings[1] = array(
		"name" => "apikeylength",
		"title" => "API Key Length",
		"description" => "Choose the API key length. Please note that this length won't affect the keys already generated.",
		"optionscode" => "select
0=8
1=16
2=32
3=64",
		"value" => "2",
		"disporder" => "2",
		"gid" => $gid
	);
	
	$settings[2] = array(
		"name" => "apirequestmethod",
		"title" => "Default Method for Sending API Options",
		"description" => "Select the default method for sending <em>username</em> / <em>password</em> / <em>output</em> / <em>apikey</em> options to the API System. If you or your customers struggle with <em>HTTP Header</em>, choose <em>URL Parameter</em> or <em>Both</em>. Please note that this does not affect API parameters that should be passed as URL parameters.<br />If you choose <em>URL Parameter</em> for example, you would be then able to send requests as follows : <em>api.php/apiname/?output=json&username=user&password=pass&apikey=my_api_key</em>.",
		"optionscode" => "select
0=HTTP Header
1=URL Parameter
2=Both",
		"value" => "0",
		"disporder" => "3",
		"gid" => $gid
	);
	
	$settings[3] = array(
		"name" => "apihttpsonly",
		"title" => "HTTPS Only",
		"description" => "Do you want to enable the API System over HTTPS connections only? Beware that if you can't access your own boards over <b>https://</b>, then the API System will never be able to respond to your customer's requests.",
		"optionscode" => "onoff",
		"value" => "0",
		"disporder" => "4",
		"gid" => $gid
	);
	
	// &setting so we don't need to create a new array, and we can insert them at once using insert_query_multiple
	foreach($settings as &$setting) {
		$setting["name"] = $db->escape_string($setting["name"]);
		$setting["title"] = $db->escape_string($setting["title"]);
		$setting["description"] = $db->escape_string($setting["description"]);
		$setting["optionscode"] = $db->escape_string($setting["optionscode"]);
		$setting["value"] = $db->escape_string($setting["value"]);
		$setting["disporder"] = (int) $setting["disporder"];
		$setting["gid"] = (int) $setting["gid"];
	}
	
	$db->insert_query_multiple("settings", $settings);
	rebuild_settings();
}

function restfulapi_is_installed() {
	global $db;
	return $db->table_exists("apisettings") && $db->table_exists("apikeys") && $db->table_exists("apipermissions");
}

function restfulapi_uninstall() {
	global $db;
	// propagate deactivation to the APIs, we only deactivate them ALL at uninstall
	restfulapi_apilist_deactivate();
	
	$db->write_query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "apisettings");
	$db->write_query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "apikeys");
	$db->write_query("DROP TABLE IF EXISTS " . TABLE_PREFIX . "apipermissions");
	$db->delete_query("settings", "name IN ('enablerestfulapi', 'apikeylength', 'apirequestmethod', 'apihttpsonly')");
	$db->delete_query("settinggroups", "name='restfulapi'");
	rebuild_settings();
}

function restfulapi_activate() {
	global $cache, $db;
	restfulapi_cache_rebuild();
	
	// propagate the activation to the APIs
	restfulapi_apilist_activate();
}

function restfulapi_deactivate() {
	global $cache;
	restfulapi_cache_delete();
}

/**
Useful functions - begin
*/

function restfulapi_generate_key() {
	global $settings;
	$characters = "0123456789abcdef";
	$randomString = "";
	$apikeylengtharray = array(0 => 8, 1 => 16, 2 => 32, 3 => 64);
	$apikeylength = 32; //default
	if(in_array((int) $settings["apikeylength"], array_keys($apikeylengtharray))) {
		$apikeylength = $apikeylengtharray[(int) $settings["apikeylength"]];
	}
	
	for($i = 0; $i < $apikeylength; $i++) {
		$randomString .= $characters[rand(0, strlen($characters) - 1)];
	}
	return $randomString;
}

/**
Useful function - end
*/

function restfulapi_config_menu(&$sub_menu) {
	global $lang;
	$lang->load("config_restfulapi");
	
	
	// compliance with incremental array keys, add 10 to the last key in array
	$sub_menu[array_pop(array_keys($sub_menu)) + 10] = array(
		"id" => "restfulapi",
		"title" => "RESTful API System",
		"link" => "index.php?module=config-" . RESTFULAPI_URL
	);
}

function restfulapi_admin_config_action_handler(&$actions) {
	global $lang;
	$actions["restfulapi"] = array(
		"active" => RESTFULAPI_URL,
		"file" => ""
	);
}

function restfulapi_admin_load() {
	global $mybb, $db, $page, $lang, $cache;
	
	if($page->active_action == RESTFULAPI_URL) {
	
		$page->add_breadcrumb_item($lang->restfulapi_title);
		$page->output_header($lang->restfulapi_title);
		
		$result = $db->simple_select("apisettings");
		
		$action = "config";
		
		if(isset($mybb->input["action"]) && in_array($mybb->input["action"], array("manage-keys", "add-key"))) {
			$action = $mybb->input["action"];
		}
		
		$navs = array(
			"config" => array(
				"link" => "index.php?module=config-" . RESTFULAPI_URL,
				"title" => $lang->restfulapi_config,
				"description" => $lang->restfulapi_config_description
			),
			"manage-keys" => array(
				"link" => "index.php?module=config-" . RESTFULAPI_URL . "&amp;action=manage-keys",
				"title" => $lang->restfulapi_manage_api_keys,
				"description" => $lang->restfulapi_manage_api_keys_description
			),
			"add-key" => array(
				"link" => "index.php?module=config-" . RESTFULAPI_URL . "&amp;action=add-key",
				"title" => $lang->restfulapi_add_api_key,
				"description" => $lang->restfulapi_add_api_key_description
			)
		);
		
		$page->output_nav_tabs($navs, $action);
		
		switch($action) {
			case "manage-keys":
				if(isset($mybb->input["do"]) && in_array($mybb->input["do"], array("regenerate", "edit", "delete"))) {
				
					$do = $mybb->input["do"];
					
					if($do == "edit" && isset($mybb->input["key_id"]) && is_string($mybb->input["key_id"])) {
						
						$key_id = (int) $db->escape_string($mybb->input["key_id"]);
						$result = $db->simple_select("apikeys", "*", "id='{$key_id}'");
						
						if($result->num_rows != 1) {
							flash_message($lang->restfulapi_key_not_found, "error");
							admin_redirect("index.php?module=config-restfulapi&amp;action=manage-keys");
							exit;
						}
						
						if($mybb->request_method == "post" && isset($mybb->input["apicustomer"]) && is_string($mybb->input["apicustomer"]) && isset($mybb->input["apicomment"]) 
							&& is_string($mybb->input["apicomment"]) && isset($mybb->input["maxreq"]) && is_numeric($mybb->input["maxreq"]) && isset($mybb->input["maxreqrate"])
							&& in_array($mybb->input["maxreqrate"], array("m", "w", "d", "h"))) {
							$update = array(
								"apicustomer" => $db->escape_string(htmlspecialchars_uni($mybb->input["apicustomer"])),
								"apicomment" => $db->escape_string(htmlspecialchars_uni($mybb->input["apicomment"])),
								"maxreq" => (int) $mybb->input["maxreq"],
								"maxreqrate" => $db->escape_string(htmlspecialchars_uni($mybb->input["maxreqrate"]))
							);
							
							$db->update_query("apikeys", $update, "id='{$key_id}'");
							$db->delete_query("apipermissions", "apikey='{$key_id}'");
							
							if(isset($mybb->input["apinames"]) && is_array($mybb->input["apinames"])) {
								$insert_allowed = array();
								foreach($mybb->input["apinames"] as $apiname) {
									$insert_allowed[] = array(
										"apikey" => $key_id,
										"apiname" => $db->escape_string($apiname)
									);
								}
								$db->insert_query_multiple("apipermissions", $insert_allowed);
							}
							
							restfulapi_cache_rebuild();
							flash_message($lang->restfulapi_key_edited_successfully, "success");
							admin_redirect("index.php?module=config-restfulapi&amp;action=manage-keys");
						}
						
						else {
							$keyset = $result->fetch_array();
							
							$form = new Form("index.php?module=config-" . RESTFULAPI_URL . "&amp;action=manage-keys&amp;do=edit&amp;key_id={$key_id}", "post", "edit");
							$form_container = new FormContainer($lang->restfulapi_edit_api_key);
					
							$form_container->output_row($lang->restfulapi_customer_name." <em>*</em>", $lang->restfulapi_customer_name_description, $form->generate_text_box('apicustomer', htmlspecialchars_uni($keyset["apicustomer"]), array('id' => 'apicustomer')), 'apicustomer');
							$rate_types = array("h" => $lang->restfulapi_per_hour, "d" => $lang->restfulapi_per_day, "w" => $lang->restfulapi_per_week, "m" => $lang->restfulapi_per_month);
							$form_container->output_row($lang->restfulapi_max_requests." <em>*</em>", $lang->restfulapi_max_requests_description, $form->generate_text_box('maxreq', htmlspecialchars_uni($keyset["maxreq"]), array('id' => 'maxreq')) . " " . $form->generate_select_box('maxreqrate', $rate_types, htmlspecialchars_uni($keyset["maxreqrate"]), array('id' => 'maxreqrate')), 'maxreq');
							$form_container->output_row($lang->restfulapi_comment, $lang->restfulapi_comment_description, $form->generate_text_area('apicomment', htmlspecialchars_uni($keyset["apicomment"]), array('id' => 'apicomment')), 'apicomment');
							$apis = glob(RESTFULAPI_PATH . "api/*api.class.php");
					
							$presentable_apis = array();
							foreach($apis as $key => $value) {
								$value = htmlspecialchars_uni(str_replace(array(RESTFULAPI_PATH . "api/", "api.class.php"), "", $value));
								$presentable_apis[$value] = $value;
							}
							
							$selected = array();
							// reminder, $key_id has already been escaped!
							$result = $db->simple_select("apipermissions", "*", "apikey='{$key_id}'");
							
							while($apipermission = $db->fetch_array($result)) {
								$selected[] = $apipermission["apiname"];
							}
					
							$form_container->output_row($lang->restfulapi_select_allowed_apis, $lang->restfulapi_select_allowed_apis_description, $form->generate_select_box('apinames[]', $presentable_apis, $selected, array('id' => 'apinames', 'multiple' => true, 'size' => 10)), 'apinames');
							
							$form_container->end();
							$buttons[] = $form->generate_submit_button($lang->restfulapi_edit_api_key);
							$form->output_submit_wrapper($buttons);
							$form->end();
						}
						
					}
					elseif($do == "delete" && isset($mybb->input["key_id"]) && isset($mybb->input["my_post_key"]) && verify_post_check($mybb->input["my_post_key"])) {
					
						$key_id = $db->escape_string($mybb->input["key_id"]);
						if($db->simple_select("apikeys", "*", "id='{$key_id}'")->num_rows == 1) {
						
							$db->delete_query("apipermissions", "apikey='{$key_id}'");
							$db->delete_query("apikeys", "id='{$key_id}'");
							
							restfulapi_cache_rebuild();
							flash_message($lang->restfulapi_key_deleted_successfully, "success");
						}
						else {
							flash_message($lang->restfulapi_key_not_found, "error");
						}
						admin_redirect("index.php?module=config-restfulapi&amp;action=manage-keys");
						
					}
					elseif($do == "regenerate" && isset($mybb->input["key_id"]) && isset($mybb->input["my_post_key"]) && verify_post_check($mybb->input["my_post_key"])) {
						
						$key_id = $db->escape_string($mybb->input["key_id"]);
						if($db->simple_select("apikeys", "*", "id='{$key_id}'")->num_rows == 1) {
							$apikey = restfulapi_generate_key();
							/* can't figure out a better way to generate a random yet never-generated-before API key than this one */
							while($db->simple_select("apikeys", "*", "apikey='{$apikey}'")->num_rows != 0) {
								$apikey = restfulapi_generate_key();
							}
							$update = array(
								"apikey" => $db->escape_string(htmlspecialchars_uni($apikey)) // yeah, cuz we never know
							);
							$db->update_query("apikeys", $update, "id='{$key_id}'");
							restfulapi_cache_rebuild();
							flash_message($lang->restfulapi_key_regenerated_successfully, "success");
							
						}
						else {
							flash_message($lang->restfulapi_key_not_found, "error");
						}
						admin_redirect("index.php?module=config-restfulapi&amp;action=manage-keys");
						
					}
				}
				else {
					$restfulapi_cache = $cache->read("restfulapi");
					$apikeysets = $restfulapi_cache["keys"];
					
					$table = new Table;
					
					$table->construct_header($lang->restfulapi_customer, array("width" => "15%"));
					$table->construct_header($lang->restfulapi_api_key, array("class" => "align_center", "width" => "29%"));
					$table->construct_header($lang->restfulapi_comment, array("class" => "align_center", "width" => "30%"));
					$table->construct_header($lang->restfulapi_usage, array("class" => "align_center", "width" => "5%"));
					$table->construct_header($lang->restfulapi_controls, array("class" => "align_center", "width" => "21%", "colspan" => 3));
						
					
					if(count($apikeysets) == 0) {
						$table->construct_cell($lang->sprintf($lang->restfulapi_no_api_key, '<a href="index.php?module=config-restfulapi&action=add-key">', '</a>'), array("class" => "first", "colspan" => 5));
						$table->construct_row();
						
					}
					else {
						// TODO : pagination maybe ?
						foreach($apikeysets as $key => $keyset) {
							$table->construct_cell("<b>".htmlspecialchars_uni($keyset['apicustomer'])."</b>");
							$table->construct_cell(htmlspecialchars_uni($keyset['apikey']));
							$table->construct_cell(htmlspecialchars_uni($keyset['apicomment']));
							$table->construct_cell(htmlspecialchars_uni($keyset['access']), array("class" => "align_center"));
							$table->construct_cell("<a href=\"index.php?module=config-restfulapi&amp;action=manage-keys&amp;do=regenerate&amp;key_id={$keyset['id']}&my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->restfulapi_regenerate_api_key_confirmation}')\">{$lang->restfulapi_regenerate_api_key}</a>", array("class" => "align_center", "width" => "9%"));
							$table->construct_cell("<a href=\"index.php?module=config-restfulapi&amp;action=manage-keys&amp;do=edit&amp;key_id={$keyset['id']}\">{$lang->restfulapi_edit}</a>", array("class" => "align_center", "width" => "6%"));
							$table->construct_cell("<a href=\"index.php?module=config-restfulapi&amp;action=manage-keys&amp;do=delete&amp;key_id={$keyset['id']}&my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->restfulapi_delete_confirm}')\">{$lang->restfulapi_delete}</a>", array("class" => "align_center", "width" => "6%"));
							$table->construct_row();
						}
					}
					$table->output($lang->restfulapi_manage_api_keys);
				}
				
			break;
			case "add-key":
			
				if($mybb->request_method == "post" && isset($mybb->input["apicustomer"]) && is_string($mybb->input["apicustomer"]) && isset($mybb->input["apicomment"])
					&& is_string($mybb->input["apicomment"]) && isset($mybb->input["maxreq"]) && is_numeric($mybb->input["maxreq"]) && isset($mybb->input["maxreqrate"])
					&& in_array($mybb->input["maxreqrate"], array("m", "w", "d", "h"))) {
					$apikey = restfulapi_generate_key();
					/* can't figure out a better way to generate a random yet never-generated-before API key than this one */
					while($db->simple_select("apikeys", "*", "apikey='{$db->escape_string($apikey)}'")->num_rows != 0) {
						$apikey = restfulapi_generate_key();
					}
					$insert = array(
						"apicustomer" => $db->escape_string(htmlspecialchars_uni($mybb->input["apicustomer"])),
						"apicomment" => $db->escape_string(htmlspecialchars_uni($mybb->input["apicomment"])),
						"access" => 0,
						"maxreq" => (int) $mybb->input["maxreq"],
						"maxreqrate" => $db->escape_string(htmlspecialchars_uni($mybb->input["maxreqrate"])),
						"apikey" => $db->escape_string(htmlspecialchars_uni($apikey)) //we never know :D
					);
					
					$apikeyid = $db->insert_query("apikeys", $insert);
					
					if(isset($mybb->input["apinames"]) && is_array($mybb->input["apinames"])) {
						$insert_allowed = array();
						foreach($mybb->input["apinames"] as $apiname) {
							$insert_allowed[] = array(
								"apikey" => $db->escape_string($apikeyid),
								"apiname" => $db->escape_string($apiname)
							);
						}
						$db->insert_query_multiple("apipermissions", $insert_allowed);
					}
					
					restfulapi_cache_rebuild();
					
					flash_message($lang->sprintf($lang->restfulapi_generated_successfully, $apikey, $mybb->input["apicustomer"]), 'success');
					admin_redirect("index.php?module=config-restfulapi&amp;action=manage-keys");
				}
				else {
					$form = new Form("index.php?module=config-" . RESTFULAPI_URL . "&amp;action=add-key", "post", "add");
					$form_container = new FormContainer($lang->restfulapi_add_api_key);
					
					$form_container->output_row($lang->restfulapi_customer_name." <em>*</em>", $lang->restfulapi_customer_name_description, $form->generate_text_box('apicustomer', '', array('id' => 'apicustomer')), 'apicustomer');
					$rate_types = array("h" => $lang->restfulapi_per_hour, "d" => $lang->restfulapi_per_day, "w" => $lang->restfulapi_per_week, "m" => $lang->restfulapi_per_month);
					$form_container->output_row($lang->restfulapi_max_requests." <em>*</em>", $lang->restfulapi_max_requests_description, $form->generate_text_box('maxreq', '0', array('id' => 'maxreq')) . " " . $form->generate_select_box('maxreqrate', $rate_types, "m", array('id' => 'maxreqrate')), 'maxreq');
					$form_container->output_row($lang->restfulapi_comment, $lang->restfulapi_comment_description, $form->generate_text_area('apicomment', '', array('id' => 'apicomment')), 'apicomment');
					
					$apis = glob(RESTFULAPI_PATH . "api/*api.class.php");
					
					$presentable_apis = array();
					foreach($apis as $key => $value) {
						$value = htmlspecialchars_uni(str_replace(array(RESTFULAPI_PATH . "api/", "api.class.php"), "", $value));
						$presentable_apis[$value] = $value;
					}
					
					$form_container->output_row($lang->restfulapi_select_allowed_apis . " <em>*</em>", $lang->restfulapi_select_allowed_apis_description, $form->generate_select_box('apinames[]', $presentable_apis, array_keys($presentable_apis), array('id' => 'apinames', 'multiple' => true, 'size' => 10)), 'apinames');
					
					$form_container->end();
					$buttons[] = $form->generate_submit_button($lang->restfulapi_generate_api_key);
					$form->output_submit_wrapper($buttons);
					$form->end();
				}
				
			break;
			default:
				$apilist = $cache->read("restfulapilist");
				// routine to install newly detected APIs, and activate them if needed
				
				restfulapi_apilist_activate();
				
				if($mybb->request_method == "post") {
					// we delete all the previously-deactivated options
					$db->delete_query("apisettings", "apiaction='deactivate'");
					$inserts = array();
					foreach($mybb->input as $key => $input) {
						if(substr($key, 0, 7) == "option_" && $input == "1") {
							// replace first occurrence of 'option_' with '' in case the option name is 'option_', so that 'option_option_' won't be all replaced into an empty string
							// yeah I know, probably would never happen but we never know
							$option = preg_replace('/option\_/', '', $key, 1);
							restfulapi_api_activate($option);
						}
						elseif(substr($key, 0, 7) == "option_" && $input == "0") {
							$option = preg_replace('/option\_/', '', $key, 1);
							restfulapi_api_deactivate($option);
						}
					}
					
					flash_message($lang->restfulapi_saved_config, "success");
					admin_redirect("index.php?module=config-restfulapi");
				}
				else {
					$result = $db->simple_select("apisettings", "*", "apiaction='deactivate'");
					
					$deactivatedapis = array();
					
					while($apiarray = $db->fetch_array($result)) {
						$deactivatedapis[] = $apiarray["apivalue"];
					}
					
					if(count($apilist) == 0) {
						echo '<div class="notice">' . $lang->sprintf($lang->restfulapi_no_api, '<a href="index.php?module=config-restfulapi&action=add-key">', '</a>') . '</div>';
					}
					
					else {
						$form = new Form("index.php?module=config-" . RESTFULAPI_URL, "post", "config");
						$form_container = new FormContainer($lang->restfulapi_config);
						
						$table = new Table;
						
						foreach($apilist as $api => $info_array) {
						
							require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
							$api = htmlspecialchars_uni($api);
							
							$apiclass = $api . "api";
							$api_instance = new $apiclass;
							$info_array = $api_instance->info();
							$name = isset($info_array["name"]) && is_string($info_array["name"]) ? htmlspecialchars_uni($info_array["name"]) . " : " . $api : $api;
							$description = isset($info_array["description"]) && is_string($info_array["description"]) ? htmlspecialchars_uni($info_array["description"]) : $lang->restfulapi_config_on_off_description;
							
							$setting_code = $form->generate_on_off_radio("option_" . $api, in_array($api, $deactivatedapis) ? 0 : 1, true, array('id' => $api.'_yes'), array('id' => $api.'_no'));
							$form_container->output_row($name, $description, $setting_code, '', array(), array('id' => 'row_'.$api));
						
						}
						
						$form_container->end();
						$buttons[] = $form->generate_submit_button($lang->restfulapi_save_config);
						$form->output_submit_wrapper($buttons);
						$form->end();
					}
				}
			break;
		}
		
		$page->output_footer();
	}
}

function restfulapi_cache_rebuild() {
	global $db, $cache;
	
	$restfulapi_cache = array();
	
	$result = $db->simple_select("apipermissions", "*");
	$apipermissions = array();
	while($apipermission = $db->fetch_array($result)) {
		$apipermissions[$apipermission["apikey"]][] = $apipermission["apiname"];
	}
	
	$result = $db->simple_select("apikeys", "*");
	$apikeys = array();
	while($apikeyset = $db->fetch_array($result)) {
		if(isset($apipermissions[$apikeyset["id"]])) {
			$apikeyset["permissions"] = $apipermissions[$apikeyset["id"]];
		}
		else {
			$apikeyset["permissions"] = array();
		}
		$apikeys[$apikeyset["apikey"]] = $apikeyset;
	}
	
	$restfulapi_cache["keys"] = $apikeys;
	
	$result = $db->simple_select("apisettings", "*");
	$apisettings = array();
	
	while($apisetting = $db->fetch_array($result)) {
		// I can't assign $apisetting["apiaction"] to $apisetting["apivalue"] since an 'apiaction' is not a unique key.
		$apisettings[$apisetting["id"]] = $apisetting;
	}
	
	$restfulapi_cache["settings"] = $apisettings;
	$cache->update("restfulapi", $restfulapi_cache);
}

function restfulapi_cache_delete() {
	global $cache, $db;
	// update to null
	$cache->update("restfulapi", null);
	$cache->update("restfulapilist", null);
	// delete cache trace from DB - if any!
	$db->delete_query("datacache", "title='restfulapi'");
}

function restfulapi_apilist_activate() {
	global $cache, $db;
	
	$apilist = $cache->read("restfulapilist");
	$restfulapi_cache = $cache->read("restfulapi");
	$rebuild_cache = false;
	
	if(! is_array($apilist)) {
		$apilist = array();
	}
	
	$apis = glob(RESTFULAPI_PATH . "api/*api.class.php");
	
	foreach($apis as $api) {
		$first_detection = false;
		
		$api = str_replace(array(RESTFULAPI_PATH . "api/", "api.class.php"), "", $api);
		
		if(! isset($apilist[$api])) {
			// first time we detect this API
			$first_detection = true;
			require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
			$apiclass = $api . "api";
			$api_instance = new $apiclass;
			$apilist[$api] = $api_instance->info();
			$apilist[$api]["status"] = "deactivated";
		}
		
		if(isset($apilist[$api]["default"]) && $apilist[$api]["default"] == "deactivated") {
			// this API should not be activated, moreover, deactivate it in db
			if($first_detection) {
				$insert_array = array(
					"apiaction" => "deactivate",
					"apivalue" => $db->escape_string($api)
				);
				$db->insert_query("apisettings", $insert_array);
				$rebuild_cache = true;
			}
			continue;
		}
		
		if(isset($apilist[$api]["status"]) && $apilist[$api]["status"] == "activated") {
			// this API has already been activated
			continue;
		}
		
		// did the admin already de-activate this API? Then we shouldn't activate it
		if(isset($restfulapi_cache["settings"]) && is_array($restfulapi_cache["settings"])) {
			foreach($restfulapi_cache["settings"] as $setting) {
				if($setting["apiaction"] == "deactivate" && $setting["apivalue"] == $api) {
					continue 2;
				}
			}
		}
		
		if(! isset($api_instance)) {
			require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
			$apiclass = $api . "api";
			$api_instance = new $apiclass;
			
		}
		
		$api_instance->activate();
		$apilist[$api]["status"] = "activated";
	}
	if($rebuild_cache) {
		restfulapi_cache_rebuild();
	}
	$cache->update("restfulapilist", $apilist);
}

function restfulapi_apilist_deactivate() {
	global $cache, $db;
	$apilist = $cache->read("restfulapilist");
	
	if(! is_array($apilist)) {
		return;
	}
	
	foreach($apilist as $api => $api_info) {
		if(isset($api_info["status"]) && $api_info["status"] == "activated") {
			// this plugin has already been activated, deactivate it
			require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
			$apiclass = $api . "api";
			$api_instance = new $apiclass;
			$api_instance->deactivate();
			$apilist[$api]["status"] = "deactivated";
		}
		// else the plugin has never been activated, don't do anything
	}
	
	// delete cache trace
	$cache->update("restfulapilist", null);
	// propagate deletion to db
	$db->delete_query("datacache", "title='restfulapi'");
}

function restfulapi_api_activate($api) {
	global $cache, $db;
	$apilist = $cache->read("restfulapilist");
	// is the api valid ?
	if(! is_array($apilist) || !isset($apilist[$api])) {
		return;
	}
	// has the api been already activated
	if(isset($apilist[$api]["status"]) && $apilist[$api]["status"] == "activated") {
		return;
	}
	
	require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
	$apiclass = $api . "api";
	$api_instance = new $apiclass;
	$api_instance->activate();
	$apilist[$api]["status"] = "activated";
	
	// we delete the deactivation from db IF ANY
	$db->delete_query("apisettings", "'apiaction' = 'deactivate' AND 'apivalue' = '{$db->escape_string($api)}'");
	restfulapi_cache_rebuild();
	$cache->update("restfulapilist", $apilist);
}

function restfulapi_api_deactivate($api) {
	global $cache, $db;
	$apilist = $cache->read("restfulapilist");
	// is the api valid ?
	if(! is_array($apilist) || !isset($apilist[$api])) {
		return;
	}
	// has the api been already activated
	if(isset($apilist[$api]["status"]) && $apilist[$api]["status"] == "deactivated") {
		return;
	}
	
	$query = $db->simple_select("apisettings", "*", "'apiaction' = 'deactivate' AND 'apivalue' = '{$db->escape_string($api)}'");
	if($db->num_rows($query) == 0) {
		
		// tell the DB that the admin deactivated this API
		$insert_array = array(
			"apiaction" => "deactivate",
			"apivalue" => $db->escape_string($api)
		);
		$db->insert_query("apisettings", $insert_array);
	}
	
	restfulapi_cache_rebuild();
	
	require_once RESTFULAPI_PATH . "api/" . $api . "api.class.php";
	$apiclass = $api . "api";
	$api_instance = new $apiclass;
	$api_instance->deactivate();
	$apilist[$api]["status"] = "deactivated";
	
	$cache->update("restfulapilist", $apilist);
}