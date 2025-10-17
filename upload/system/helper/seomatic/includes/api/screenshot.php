<?php

	class SEOMatic_Api_Utilities extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'create' 		=> array( 'method' => 'post',	'url' => '/utilities/screenshot' ),
			'get' 			=> array( 'method' => 'get'	,	'url' => '/utilities/screenshot' ),
		);





		/**
		*
		* 	create
		*		- Create a new Screenshot
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		* 			- domain: 		(String) The Full Domain & Path to render
		*
		* 	Returns:
		* 		- screenshotid: 	(String) The Screenshot ID to Lookup during the Rendering Process
		*
		**/
		public function create($data=array()){
			return $this->requestor->get( $this->requests['screenshot']['url'] , $data );
		}





		/**
		*
		* 	get
		*		- Get an Existing Screenshot
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- screenshotid: 	(String) The Screenshot ID to Lookup
		*
		*	Returns: 	Success, Rendering Failed, Rendering In Progress, Invalid Screenshot
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['screenshot']['url'] , $data );
		}





	}
 
?>