<?php

	class SEOMatic_Api_Account extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'create' 		=> array( 'method' => 'put' 	,	'url' => '/account' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s' ),
			'find' 			=> array( 'method' => 'delete'	,	'url' => '/account' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/accounts' ),
		);



		/**
		*
		* 	create
		*		- Create a new Account
		* 		
		* 	Note:
		* 		- A User can have a Maximum of 25 Accounts
		* 	
		* 	Params:
		*		- $data: 	(Array)
		*			- name:		(String) The Account Name
		*
		**/
		public function create($data){
			return $this->requestor->put( $this->requests['create']['url'] , $data );
		}






		/**
		*
		* 	update
		*		- Update an Account
		* 		
		* 	Params:
		*		- $data: 	(Array)
		*			- name:		(String) The Account Name
		*
		**/
		public function update($data){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}







		/**
		*
		*	get
		* 	Get an Account
		*
		* 	Params:
		*		- $data: 	(Array)
		* 			- accountid: 	(INT) The Account ID to Lookup 								* Optional *
		* 			- limit: 		(INT) The Total Accounts to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 		(INT) The Account ID to Return Results After 				* Optional *
		*
		**/
		public function get($data=array()){
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}







		/**
		*
		*	find
		* 	find an Account
		*
		* 	Params:
		*		- $data: 	(Array)
		*			- accountid: 	(INT) The Account ID 												* Optional *
		*			- name: 		(String) The Account Name to Search for 							* Optional *
		*			- after: 		(String) Get all Accounts after the passed Account ID. 				* Optional *
		*			- before: 		(String) Get all Accounts before the passed Account ID. 			* Optional *
		*			- limit: 		(INT) The Total Accounts to Show (Maximum: 100) (Default: 10)
		*
		**/
		public function find($data=array()){
			return $this->requestor->get('/account',$data);
		}








		/**
		*
		* 	all
		*		- List All of a Users Account(s)
		* 		
		* 	Params:
		*		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID 												* Optional *
		*			- name: 		(String) The Account Name to Search for 							* Optional *
		*			- after: 		(String) Get all Accounts after the passed Account ID. 				* Optional *
		*			- before: 		(String) Get all Accounts before the passed Account ID. 			* Optional *
		*			- limit: 		(INT) The Total Accounts to Show (Maximum: 100) (Default: 10)
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}





	}
 
?>