<?php

	class SEOMatic_Api_Changelog extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/changelog' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/changelog' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/changelog' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/changelog' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/changelog' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/changelog' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Changes
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- Link: 		(Object) An Object of ID and Type to associate the Change to anything 		* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- type: 		(String) A Short string of the type of change made (Max: 10 Chars) 			* Optional *
		*			- date: 		(Object) An Object of the Start and End time to search in 					* Optional *
		*				- start: 		(UTC Timestamp) The Date to Start with 									* Optional *
		*				- end: 			(UTC Timestamp) The Date to End with 									* Optional *
		*			- after: 		(INT) The Change ID to start searching from 								* Optional *
		*			- limit: 		(INT) The Total number of changelog to return (Max: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add Change/s
		* 		
		* 	Params:
		* 		- $data:		(Array)
		*			- link: 		(Object) An Object of ID and Type to associate the Change to anything
		*				- id: 			(INT) The Link ID
		*				- type: 		(String) The Link Type
		*			- type: 		(String) A Short string of the type of change made (Max: 10 Chars)
		*			- change: 		(String) A String of the Changes Made (Generally JSON) 
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);
		}








		/**
		*
		* 	update
		*		- Update Change/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- changeid: 	(INT) The Change ID
		*			- link: 		(Object) An Object of ID and Type to associate the Change to anything 	* Optional *
		*				- id: 			(INT) The Link ID 													* Optional *
		*				- type: 		(String) The Link Type 												* Optional *
		*			- type: 		(String) A Short string of the type of change made (Max: 10 Chars) 		* Optional *
		*			- change: 		(String) A String of the Changes Made (Probably JSON?) 					* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}








		/**
		*
		* 	delete
		*		- Delete Change/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- changeid: 	(INT) The Change ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ),$data);
		}







		/**
		*
		* 	get
		*		- Get A List of Changes
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- Link: 		(Object) An Object of ID and Type to associate the Change to anything 		* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- type: 		(String) A Short string of the type of change made (Max: 10 Chars) 			* Optional *
		*			- date: 		(Object) An Object of the Start and End time to search in 					* Optional *
		*				- start: 		(UTC Timestamp) The Date to Start with 									* Optional *
		*				- end: 			(UTC Timestamp) The Date to End with 									* Optional *
		*			- after: 		(INT) The Change ID to start searching from 								* Optional *
		*			- limit: 		(INT) The Total number of changelog to return (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}




		/**
		*
		* 	all
		*		- Get All of the Users Changes
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- Link: 		(Object) An Object of ID and Type to associate the Change to anything 		* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- type: 		(String) A Short string of the type of change made (Max: 10 Chars) 			* Optional *
		*			- date: 		(Object) An Object of the Start and End time to search in 					* Optional *
		*				- start: 		(UTC Timestamp) The Date to Start with 									* Optional *
		*				- end: 			(UTC Timestamp) The Date to End with 									* Optional *
		*			- after: 		(INT) The Change ID to start searching from 								* Optional *
		*			- limit: 		(INT) The Total number of changelog to return (Max: 100)
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );

		}








	}
 
?>