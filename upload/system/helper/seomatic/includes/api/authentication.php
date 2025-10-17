<?php

	class SEOMatic_Api_Authentication extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'login' 		=> array( 'method' => 'post' 	, 	'url' => '/login' ),
			'logout'		=> array( 'method' => 'get' 	,	'url' => '/logout' )
		);





		/**
		*
		* 	login
		*		- Login to your Account
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- email: 		(String) Your Account Email
		* 			- password: 	(String) Your Account Password
		*
		**/
		public function login($email,$password){

			//Setup the cURL cookies
			$this->libutils->cookies( $email );

			//Send the Request
			return $this->requestor->post( $this->requests['login']['url'] , array(
				'email' 	=> $email,
				'password' 	=> $password
			));			

		}





		/**
		*
		* 	logout
		*		- Logout of your Account
		* 	
		* 	Params:
		*		n/a
		*
		**/
		public function logout(){

			//Purge the Cookies & Session
			$this->session->purge();

			//Send the Request
			return $this->requestor->get( $this->requests['logout']['url'] );

		}





	}
 
?>