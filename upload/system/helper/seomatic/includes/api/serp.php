<?php

	class SEOMatic_Api_Serp extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	,	'url' => '/serp' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/serp' ),
			'status' 		=> array( 'method' => 'get' 	, 	'url' => '/serp/status' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Keyword SERP Positions
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- serpid: 		(INT) The SERP ID 														* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Keyword to anything 	* Optional *
		*				- id: 			(INT) The Link ID to Associate the Keyword to 						* Optional *
		*				- type: 		(String) The Link Type to Associate the Keyword to 					* Optional *
		*			- accountid: 	(INT) The Account ID 													* Optional *
		* 			- countryid: 	(INT) The Country ID 													* Optional *
		*			- date: 		(Object) The Date of the SERP Scrape									* Optional *
		* 				- start: 		(UTC Timestamp) The Oldest Date to Return							* Optional *
		* 				- end: 			(UTC Timestamp) The Earliest Date to Return							* Optional *
		*			- keyword: 		(String) The Keyword 													* Optional *
		* 			- google: 		(Object) The Google SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		* 			- bing: 		(Object) The Bing SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		* 			- yahoo: 		(Object) The Yahoo SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		*			- before: 		(INT) Get Serp listings Before the passed SERP ID 						* Optional *
		*			- after: 		(INT) Get Serp listings After the passed SERP ID 						* Optional *
		*			- limit: 		(INT) The Maximum amount of domains to show (Default: 10) (Maximum: 100) 
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}





		/**
		*
		* 	get
		*		- Get A List of Keyword SERP Positions
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 			- serpid: 		(INT) The SERP ID 														* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Keyword to anything 	* Optional *
		*				- id: 			(INT) The Link ID to Associate the Keyword to 						* Optional *
		*				- type: 		(String) The Link Type to Associate the Keyword to 					* Optional *
		*			- accountid: 	(INT) The Account ID 													* Optional *
		* 			- countryid: 	(INT) The Country ID 													* Optional *
		*			- date: 		(Object) The Date of the SERP Scrape									* Optional *
		* 				- start: 		(UTC Timestamp) The Oldest Date to Return							* Optional *
		* 				- end: 			(UTC Timestamp) The Earliest Date to Return							* Optional *
		*			- keyword: 		(String) The Keyword 													* Optional *
		* 			- google: 		(Object) The Google SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		* 			- bing: 		(Object) The Bing SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		* 			- yahoo: 		(Object) The Yahoo SERP Position 										* Optional *
		* 				- min: 			(INT) The Lowest Number to Return 									* Optional *
		* 				- max: 			(INT) The Largest Number to Return 									* Optional *
		*			- before: 		(INT) Get Serp listings Before the passed SERP ID 						* Optional *
		*			- after: 		(INT) Get Serp listings After the passed SERP ID 						* Optional *
		*			- limit: 		(INT) The Maximum amount of domains to show (Default: 10) (Maximum: 100) 
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}





		/**
		*
		* 	status
		*		- A More efficient way of getting the highest serp history for the last two days scraped
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		(Object) An Object of ID and Type to associate the Keyword to anything 	* Optional *
		*				- id: 			(INT) The Link ID to Associate the Keyword to 						* Optional *
		*				- type: 		(String) The Link Type to Associate the Keyword to 					* Optional *
		*			- accountid: 	(INT) The Account ID 													* Optional *
		* 			- countryid: 	(INT) The Country ID 													* Optional *
		*			- date: 		(Object) The Date of the SERP Scrape									* Optional *
		* 				- start: 		(UTC Timestamp) The Oldest Date to Return							* Optional *
		* 				- end: 			(UTC Timestamp) The Earliest Date to Return							* Optional *
		*			- limit: 		(INT) The Maximum amount of domains to show (Default: 10) (Maximum: 100) 
		*
		**/
		public function status($data=array()){
			return $this->requestor->get( $this->requests['status']['url'] , $data );
		}









	}
 
?>