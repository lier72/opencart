<?php
	class SEOMatic {

		/************************************************************************
		**
		** 			SEOMatic SDK PHP Kit
		** 				
		** 			Available API Classes:
		** 				- User: 			(Class) User Functions
		** 					- Login: 			(Function) Login to SEOMatic
		** 					- Logout: 			(Function) Logout of SEOMatic
		** 					- Register: 		(Function) Register an Account
		** 					- Info: 			(Function) Get Your Account Information
		** 					- getInfo: 			(Function) Get Another Users Account Information
		** 				- Account: 			(Class) Account Functions
		** 					
		** 					
		** 			Available Library Classes:
		** 				- getError: 		(Function) Get the Last Request Error 		(Readable Text)
		** 				- getCode: 			(Function) Get the Last Request Error Code 	(single-line-text)
		** 				- getResult: 		(Function) Get the Last Request Result 		(Boolean)
		**
		**
		**
		**
		**
		**
		**
		**
		**
		************************************************************************/


		//Registry
		private $registry;


		/**
		* 	Begin SEOMatic SDK
		* 		Params:
		* 			
		**/
		public function __construct(){

			//Include Libraries
			require 'includes/libraries/registry.php';
			require 'includes/libraries/engine.php';
			require 'includes/libraries/curl.php';
			require 'includes/libraries/cache.php';
			require 'includes/libraries/batch.php';
			require 'includes/libraries/session.php';
			require 'includes/libraries/requestor.php';
			require 'includes/libraries/utilities.php';

			//Include API
			require 'includes/api/authentication.php';
			require 'includes/api/user.php';
			require 'includes/api/account.php';
			require 'includes/api/utilities.php';
			require 'includes/api/invoices.php';
			require 'includes/api/creditcards.php';
			require 'includes/api/plans.php';
			require 'includes/api/coupons.php';
			require 'includes/api/countries.php';
			require 'includes/api/domains.php';
			require 'includes/api/subscriptions.php';
			require 'includes/api/keywords.php';
			require 'includes/api/serp.php';
			require 'includes/api/competitors.php';
			require 'includes/api/changelog.php';
			require 'includes/api/conversions.php';
			require 'includes/api/comments.php';
			require 'includes/api/notes.php';
			require 'includes/api/notifications.php';
			require 'includes/api/questions.php';
			require 'includes/api/answers.php';

			//Setup Registry
			$this->registry 	= new SEOMatic_Lib_Registry;

			//Setup Variables
			$this->ABS_PATH 	= dirname(__FILE__);

			//Setup Classes
			$this->cURL			= new SEOMatic_Lib_cURL;
			$this->cache 		= new SEOMatic_Lib_Cache($this->registry);
			$this->session 		= new SEOMatic_Lib_Session($this->registry);
			$this->requestor 	= new SEOMatic_Lib_Requestor($this->registry);
			$this->libutils 	= new SEOMatic_Lib_Utilities($this->registry);
			$this->batch 		= new SEOMatic_Lib_Batch($this->registry);

			//Initialize API Classes
			$this->user 			= new SEOMatic_Api_User($this->registry);
			$this->account 			= new SEOMatic_Api_Account($this->registry);
			$this->authentication 	= new SEOMatic_Api_Authentication($this->registry);
			$this->plans 			= new SEOMatic_Api_Plans($this->registry);
			$this->countries		= new SEOMatic_Api_Countries($this->registry);

			//Add User Functions
			$this->creditcards		= new SEOMatic_Api_CreditCards($this->registry);
			$this->coupons			= new SEOMatic_Api_Coupons($this->registry);
			$this->invoices			= new SEOMatic_Api_Invoices($this->registry);
			$this->utilities 		= new SEOMatic_Api_Utilities($this->registry);
			$this->comments 		= new SEOMatic_Api_Comments($this->registry);

			//Add Account Functions
			$this->domains 			= new SEOMatic_Api_Domains($this->registry);
			$this->subscriptions 	= new SEOMatic_Api_Subscriptions($this->registry);
			$this->keywords 		= new SEOMatic_Api_Keywords($this->registry);
			$this->serp 			= new SEOMatic_Api_Serp($this->registry);
			$this->competitors 		= new SEOMatic_Api_Competitors($this->registry);
			$this->changelog 		= new SEOMatic_Api_Changelog($this->registry);
			$this->conversions		= new SEOMatic_Api_Conversions($this->registry);
			$this->notes			= new SEOMatic_Api_Notes($this->registry);
			$this->notifications 	= new SEOMatic_Api_Notifications($this->registry);

			//Add QnA Functions
			$this->qna 				= new StdClass();
			$this->qna->questions	= new SEOMatic_Api_Questions($this->registry);
			$this->qna->answers 	= new SEOMatic_Api_Answers($this->registry);

			//Duplications			
			$this->accounts 		= $this->account;
			$this->users 			= $this->user;

			//Setup cURL
			$this->cURL->useragent 	= 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36';
			$this->cURL->cainfo 	= dirname(__FILE__) . '/includes/certificates/ca-certificates.crt' ;
			
			//Get the Server Time
			//$this->SERVER_TIME 		= $this->utilities->time();

		}



		





		/**
		* 	Login to SEOMatic
		* 		Params:
		* 			- $email: 		(String) The Users Email
		* 			- $password: 	(String) The Users Password
		* 			
		**/
		public function login($email,$password){
				
			//Setup cURL Cookies
			$this->cURL->cookies( dirname(__FILE__) . '/includes/cookies/' . $this->libutils->hash($email) . '.jar' );

			//Return The Result
			return $this->authentication->login($email,$password);

		}






		


		/**
		* 	Logout of SEOMatic
		* 		Params:
		* 			
		**/
		public function logout(){
				
			//Login
			return $this->authentication->logout();

		}





		/**
		*
		*	Set the Account to Use
		* 		Params:
		* 			- $accountid: 		(INT) The Account ID
		*
		**/
		public function account($accountid){

			//Set the Account ID	
			$this->accountid = $accountid;

			//Return the Class
			return $this;

		}






		/**
		*
		*	Get the Last Request Error, Error Code, Request Result, Request Path or Request Method
		* 		Params:
		*
		**/
		public function getError(){ return $this->requestor->error(); }
		public function getCode(){ return $this->requestor->code(); }
		public function getResult(){ return $this->requestor->result(); }
		public function getPath(){ return $this->requestor->path(); }
		public function getMethod(){ return $this->requestor->method(); }







		/**
		*
		*	Enable Debugging
		*	 	Params:
		* 			- $fields: 		(Array) An Array of Fields to Enable in Debugging
		*
		**/
		public function debug($fields){
			$this->debug = array_merge(array(
				'benchmark' 	=> false,
				'output' 		=> false
			),$fields);
		}





		/**
		*
		*	SEOMatic __CALL Magic Method
		* 		- Call Registry as Key
		*
		**/
		public function __call($method,$args){
			if( ($class = $this->registry->get($method)) ){
				if( PHP_VERSION < '5.3' ){

					return $class->__invoke( ( is_array($args) && count($args) > 0? $args[0] : $args ) );

				}else{

					return $class( ( is_array($args) && count($args) > 0? $args[0] : $args ) );

				}
			}else{
				$this->libutils->error('SEOMatic: Invalid Function: '.$method);
			}
		}





		/**
		*
		*	SEOMatic __GET Magic Method
		* 		- Call Registry as Key
		*
		**/
		public function __get($key){
			return $this->registry->get($key);
		}










		/**
		*
		*	SEOMatic __SET Magic Method
		* 		- Call Registry as Key
		*
		**/
		public function __set($key,$val){
			return $this->registry->set($key,$val);
		}










		/**
		*
		*	SEOMatic __ISSET Magic Method
		* 		- Call Registry as Key
		*
		**/
		public function __isset($key){
			return !is_null( $this->registry->get($key) );
		}




		



	}
?>