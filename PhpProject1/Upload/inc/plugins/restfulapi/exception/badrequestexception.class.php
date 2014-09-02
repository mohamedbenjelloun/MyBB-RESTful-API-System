<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)
# 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BadRequestException extends RESTfulException {
	
	public function __construct($errorMessage, Exception $previous = null) {
		$this->errorCode = 400;
		parent::__construct($errorMessage, $previous);
	}
}
