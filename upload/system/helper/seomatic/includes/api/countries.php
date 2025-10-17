<?php

	class SEOMatic_Api_Countries extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/country' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/country' ),
		);



		/**
		*
		* 	__invoke
		*		- Get Countries
		* 		
		* 	Params:
		*		- $data:	 		(Array)
		*			- countryid: 		(INT) The Country ID 					* Optional *
		*			- name: 			(String) The Exact Country Name 		* Optional *
		*			- search: 			(String) The Country Name to Search 	* Optional *
		*			- codes: 			(Object) The Country Codes 				* Optional *
		*				- google: 			(String) The Google Country Code 	* Optional *
		*				- yahoo: 			(String) The Yahoo Country Code 	* Optional *
		*				- bing: 			(String) The Bing Country Code 		* Optional *
		*
		**/
		public function __invoke($data){
			return $this->requestor->get( $this->requests['__invoke']['url'] ,$data);
		}





		/**
		*
		* 	get
		*		- Get All Countries
		* 		
		* 	Params:
		*		- $data:	 		(Array)
		*			- countryid: 		(INT) The Country ID 					* Optional *
		*			- name: 			(String) The Exact Country Name 		* Optional *
		*			- search: 			(String) The Country Name to Search 	* Optional *
		*			- codes: 			(Object) The Country Codes 				* Optional *
		*				- google: 			(String) The Google Country Code 	* Optional *
		*				- yahoo: 			(String) The Yahoo Country Code 	* Optional *
		*				- bing: 			(String) The Bing Country Code 		* Optional *
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] ,$data);
		}




	}
 
?>