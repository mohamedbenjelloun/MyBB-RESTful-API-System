<?php

# This file is a part of MyBB RESTful API System plugin - version 0.2
# Released under the MIT Licence by medbenji (TheGarfield)
# 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

abstract class RESTfulException extends Exception {

	protected $errorMessage;
	protected $errorCode;
	
	public function __construct($errorMessage, Exception $previous = null) {
		$this->errorMessage = $errorMessage;
		parent::__construct(is_string($errorMessage) ? $errorMessage : "", 0, $previous);
	}
	
	public function getExceptionObject() {
		$stdClass = new stdClass();
		$stdClass->error = $this->errorMessage;
		return $stdClass;
	}
	
	public function getErrorCode() {
		return $this->errorCode;
	}
}
