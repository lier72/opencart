<?php

	class SEOMatic_Api_Domains extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/domain' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/domain' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/domain' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/domain' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/domain' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/domains' )
		);







		/**
		*	
		* 	__invoke
		*		- Get A List of Domains
		* 	
		* 	Params:
		* 		- $data:		(Array)
		* 			- domainid: 	(INT) The Domain ID to Lookup 								* Optional *
		* 			- limit: 		(INT) The Total Domains to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 		(INT) The Domain ID to Return Results After 				* Optional *
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add Domain/s
		*
		* 	Params:
		* 		- $data:		(Array)
		* 			- domain: 		(String) The Domain Name to Add
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);

		}








		/**
		*
		* 	update
		*		- Update Domain/s
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 		 	- domainid: 	(INT) The Domain ID to Update
		* 			- domain: 		(String) The Domain to Update
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);

		}








		/**
		*
		* 	delete
		*		- Delete Domain/s
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 		 	- domainid: 	(INT) The Domain ID to Update
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ),$data);
		
		}







		/**
		*	
		* 	get
		*		- Get A List of Domains
		* 	
		* 	Params:
		* 		- $data:		(Array)
		* 			- domainid: 	(INT) The Domain ID to Lookup 								* Optional *
		* 			- limit: 		(INT) The Total Domains to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 		(INT) The Domain ID to Return Results After 				* Optional *
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}





		/**
		*	
		* 	all
		*		- Get A List of all the User's Domains
		* 	
		* 	Params:
		* 		- $data:		(Array)
		* 			- domainid: 	(INT) The Domain ID to Lookup 								* Optional *
		* 			- limit: 		(INT) The Total Domains to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 		(INT) The Domain ID to Return Results After 				* Optional *
		*
		**/
		public function all($data=array()){	
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}








	}
 
?>