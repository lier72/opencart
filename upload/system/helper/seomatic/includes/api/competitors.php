<?php

	class SEOMatic_Api_Competitors extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/competitor' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/competitor' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/competitor' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/competitor' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/competitor' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/competitor' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Competitors
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor 									* Optional *
		*			- competitorid: 	(INT) The Competitors ID 													* Optional *
		*			- link: 			(Object) An Object of ID and Type to associate the Competitor to anything 	* Optional *
		*				- id: 				(INT) The Link ID to associate the Competitor to 						* Optional *
		*				- type: 			(String) The Link Type to associate the Competitor to 					* Optional *
		*			- name: 			(String) The Name of the Competitor 										* Optional *
		*			- domain: 			(String) The Domain of the Competitor 										* Optional *
		*			- after: 			(INT) The Competitor ID to return results After 							* Optional *
		*			- limit: 			(INT) The Total Competitors to Return (Default: 10) (Maximum: 100) 
		*
		**/
		public function __invoke($data=array()){
			$this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add Keyword/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor
		*			- link: 			(Object) An Object of ID and Type to associate the Competitor to anything 	* Optional *
		*				- id: 				(INT) The Link ID to associate the Competitor to 						* Optional *
		*				- type: 			(String) The Link Type to associate the Competitor to 					* Optional *
		*			- name: 			(String) The Name of the Competitor
		*			- domain: 			(String) The Domain of the Competitor
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);
		}








		/**
		*	
		* 	update
		*		- Update Keyword/s
		*
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor
		*			- competitorid: 	(INT) The Competitors ID
		*			- link: 		(Object) An Object of ID and Type to associate the Competitor to anything 	* Optional *
		*				- id: 				(INT) The Link ID to associate the Competitor to 					* Optional *
		*				- type: 			(String) The Link Type to associate the Competitor to 				* Optional *
		*			- name: 			(String) The Name of the Competitor 									* Optional *
		*			- domain: 			(String) The Domain of the Competitor 									* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}








		/**
		*
		* 	delete
		*		- Delete Competitor/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor
		*			- competitorid: 	(INT) The Competitors ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ),$data);
		}







		/**
		*
		* 	get
		*		- Get A List of Competitors
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor 									* Optional *
		*			- competitorid: 	(INT) The Competitors ID 													* Optional *
		*			- link: 			(Object) An Object of ID and Type to associate the Competitor to anything 	* Optional *
		*				- id: 				(INT) The Link ID to associate the Competitor to 						* Optional *
		*				- type: 			(String) The Link Type to associate the Competitor to 					* Optional *
		*			- name: 			(String) The Name of the Competitor 										* Optional *
		*			- domain: 			(String) The Domain of the Competitor 										* Optional *
		*			- after: 			(INT) The Competitor ID to return results After 							* Optional *
		*			- limit: 			(INT) The Total Competitors to Return (Default: 10) (Maximum: 100) 
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}




		/**
		*
		* 	all
		*		- Get A List of Competitors
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- accountid: 		(INT) The Account ID for the Competitor 									* Optional *
		*			- competitorid: 	(INT) The Competitors ID 													* Optional *
		*			- link: 			(Object) An Object of ID and Type to associate the Competitor to anything 	* Optional *
		*				- id: 				(INT) The Link ID to associate the Competitor to 						* Optional *
		*				- type: 			(String) The Link Type to associate the Competitor to 					* Optional *
		*			- name: 			(String) The Name of the Competitor 										* Optional *
		*			- domain: 			(String) The Domain of the Competitor 										* Optional *
		*			- after: 			(INT) The Competitor ID to return results After 							* Optional *
		*			- limit: 			(INT) The Total Competitors to Return (Default: 10) (Maximum: 100) 
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}








	}
 
?>