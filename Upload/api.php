<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'api.php');

require_once './global.php';
require_once MYBB_ROOT . 'inc/plugins/restfulapi/apisystem.class.php';

$api = APISystem::get_instance();

$lang->load("restfulapi");

if(! $api->is_active()) {
	// restful api system is either not enabled, not installed or not activated
	$api->redirect_index($lang->restfulapi_no_permission);
}

/*
building our output class
*/
$outputer = $api->build_outputer();

// does the API system require HTTPS and the request was made over HTTP ?
if($api->requires_https() && !$api->is_https()) {
	$api->perform_exception(new BadRequestException($lang->restfulapi_not_https));
}

/*
Reject invalid API keys, but provide an error answer instead of a redirection, so they can parse the error answer and know
they have been rejected.
*/
if(! $api->is_valid_api_key()) {
	$api->perform_exception(new UnauthorizedException($lang->restfulapi_invalid_api_key));
}

$api_instance = $api->build_api_instance();

if(empty($api_instance)) {
	$api->perform_exception(new UnauthorizedException($lang->restfulapi_no_permission));
}
else {
	try {
		// has the limit amount of requests been reached ?
		if(! $api->check_api_key_access_limit()) {
			throw new ForbiddenException($lang->restfulapi_limit_reached);
		}
		// does the API require authentication and no authentication has been made ?
		if($api_instance->requires_auth() && ! $api->is_user_authenticated()) {
			throw new UnauthorizedException($lang->restfulapi_no_user_authenticated);
		}
		else {
			// anyway increment API key number of uses
			$api->api_key_increment_counters();
			$outputer->action($api_instance->action());
		}
	}
	catch (RESTfulException $ex) {
		$api->perform_exception($ex);
	}
}
