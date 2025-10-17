<?php

	class SEOMatic_Api_User extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 					=> array( 'method' => 'get' 	,	'url' => '/user' ),
			'register' 					=> array( 'method' => 'put' 	,	'url' => '/user' ),
			'update' 					=> array( 'method' => 'post' 	,	'url' => '/user' ),
			'info' 						=> array( 'method' => 'get' 	,	'url' => '/user' ),
			'get' 						=> array( 'method' => 'get' 	,	'url' => '/user' ),
			'resetPassword' 			=> array( 'method' => 'post' 	,	'url' => '/user/reset/password' ),
			'resendVerification' 		=> array( 'method' => 'post' 	,	'url' => '/user/verify' )
		);








		/**
		*
		* 	__invoke
		*		- Get Current Users Account Info
		* 	
		* 	Note: 
		* 		- Passing the "Data" Variable will return a different User
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}






		/**
		*
		* 	register
		*		- Register an Account
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- email: 		(String) The Users Email
		* 			- password: 	(String) The Users Password
		* 			- username: 	(String) The Users Username 	** Optional **
		* 			- meta: 		(String) The Users Metadata 	** Optional **
		*
		**/
		public function register($data){
			return $this->requestor->put( $this->requests['register']['url'] , $data );
		}







		/**
		*
		*	update
		*		- Update an Account
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- email: 		(String) The Users Email 		** Optional **
		* 			- password: 	(String) The Users Password 	** Optional **
		* 			- username: 	(String) The Users Username 	** Optional **
		* 			- meta: 		(String) The Users Metadata 	** Optional **
		*
		**/
		public function update($data){
			return $this->requestor->post( $this->requests['update']['url'] , $data );
		}








		/**
		*
		* 	info
		*		- Get Current Users Account Info
		* 	
		* 	Note: 
		* 		- Passing the "Data" Variable will return a different User
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function info($data=array()){
			return $this->requestor->get( $this->requests['info']['url'] , $data );
		}









		/**
		*
		* 	get
		*		- Get Users Info
		* 	
		* 	Note: 
		* 		- Passing the "Data" Variable will return a different User
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- userid: 		(INT) The User ID 		** Optional **
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}









		/**
		*
		*	resetPassword
		*		- Reset a Users Password
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- email: 		(String) The Email of the Password to Reset
		*
		**/
		public function resetPassword($data=array()){
			return $this->requestor->post( $this->requests['resetPassword']['url'] , $data );
		}









		/**
		*
		*	resendVerification
		*		- Resend the Users Email Verification
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function resendVerification(){
			return $this->requestor->post( $this->requests['resendVerification']['url'] );
		}





	}
 
?>