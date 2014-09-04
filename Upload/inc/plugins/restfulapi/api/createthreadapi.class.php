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
class CreateThreadAPI extends RESTfulAPI {
	
	public function info() {
		return array(
			"name" => "Create Thread",
			"description" => "This API exposes an API capable of creating threads.",
			"default" => "deactivated" // only activate it if needed
		);
	}
	/**
	This is where you perform the action when the API is called, the parameter given is an instance of stdClass, this method should return an instance of stdClass.
	*/
	public function action() {
		global $mybb;
                
                require_once MYBB_ROOT.'inc/functions_post.php';
		require_once MYBB_ROOT.'/inc/datahandlers/post.php';
                
		if(isset($mybb->input["subject"]) && is_string($mybb->input["subject"])
                        && isset($mybb->input["forumid"]) && is_numeric($mybb->input["forumid"])
                        && isset($mybb->input["message"]) && is_string($mybb->input["message"])
                        && isset($mybb->input["ipaddress"]) && is_string($mybb->input["ipaddress"])
                        ) {
                    
                    $subject = $mybb->input["subject"];
                    $forumid = (int) $mybb->input["forumid"];
                    $message = $mybb->input["message"];
                    $ipaddress = $mybb->input["ipaddress"];
                    
                    $prefix = isset($mybb->input["prefix"]) && is_string($mybb->input["prefix"]) ? $mybb->input["prefix"] : null;
                    $icon = isset($mybb->input["icon"]) && is_string($mybb->input["icon"]) ? $mybb->input["icon"] : null;
                    $savedraft = isset($mybb->input["savedraft"]) && in_array($mybb->input["savedraft"], array("1", "0")) ? (int) $mybb->input["savedraft"] : 0;
                    $subscriptionmethod = isset($mybb->input["subscriptionmethod"]) && in_array($mybb->input["subscriptionmethod"], array("", "none", "instant")) ? $mybb->input["subscriptionmethod"] : "";
                    
                    $signature = isset($mybb->input["signature"]) && in_array($mybb->input["signature"], array("1", "0")) ? (int) $mybb->input["signature"] : 0;
                    $disablesmilies = isset($mybb->input["disablesmilies"]) && in_array($mybb->input["disablesmilies"], array("1", "0")) ? (int) $mybb->input["disablesmilies"] : 0;
                    
                    $modclosethread = isset($mybb->input["modclosethread"]) && in_array($mybb->input["modclosethread"], array("1", "0")) ? (int) $mybb->input["modclosethread"] : 0;
                    $modstickthread = isset($mybb->input["modstickthread"]) && in_array($mybb->input["modstickthread"], array("1", "0")) ? (int) $mybb->input["modstickthread"] : 0;
                    
                    // let's start
                    $posthandler = new PostDataHandler('insert');
                    $posthandler->action = 'thread';
                    
                    $data = array(
                        "uid" => $this->get_user()->uid,
                        "username" => $this->get_user()->username,
                        "subject" => $subject,
                        "fid" => $forumid,
                        "prefix" => $prefix,
                        "message" => $message,
                        "ipaddress" => $ipaddress,
                        "icon" => $icon,
                        "savedraft" => $savedraft,
                        "options" => array(
                            "subscriptionmethod" => $subscriptionmethod,
                            "signature" => $signature,
                            "disablesmilies" => $disablesmilies,
                        )
                    );
                    
                    if(isset($this->get_user()->is_moderator) && $this->get_user()->is_moderator) {
                        $data[] = array(
                            "closethread" => $modclosethread,
                            "stickthread" => $modstickthread
                        );
                    }
                    
                    $posthandler->set_data($data);
                    
                    if (!$posthandler->validate_thread()) {
                        throw new BadRequestException((object) $posthandler->get_friendly_errors());
                    }
                    
                    return (object) $posthandler->insert_thread();
                }
	}
        
        /**
         * We need the user to be authenticated
         * @return boolean
         */
        public function requires_auth() {
            return true;
        }
}
