<?php

	class SEOMatic_Lib_Utilities extends SEOMatic_Lib_Engine {

		//A Log to Output when Debugging
		private $log = false;

		//The PHP Version
		private $version;

		//Benchmarks
		private $benchmark;



		/**
		*
		* 	Setup the Utilities Values
		* 		Params:
		* 			- $registry: 	(Object) The Registry Class
		*
		*
		**/
		public function __construct($registry){
			$this->registry = $registry;
			$this->version 	= phpversion();
			$this->benchmark('start',true);
		}

		

		/**
		*
		* 	Shutdown Function
		* 		Params:
		*
		**/
		public function __destruct(){
			$this->benchmark('start',false);
		}







		/**
		*
		*	Add Cookies to cURL
		* 		Params:
		* 			- $email: 	(String) The Unique Email to Hash for the Cookie
		*
		**/
		public function cookies($email){

			/**
			*	@note 	cURL Cookies do not support maintaining cookies across browser / page refreshes.
			* 			Replaced the cURL Cookie Jar with Cookies Managed by PHP Sessions
			*
			**/

			//Send the Headers to the Parsing Function
			$this->cURL->headerfunction = array($this,'headers');

		}






		/**
		*
		*	Gets the cURL header data
		*		Params:
		*			- $data: 	(Array) An Array of the Request Fields
		*
		**/
		public function headers($ch, $data){

			//Get the Cookies
			preg_match_all('/(?<=Set-Cookie: ).[a-zA-Z0-9_=:.\-]+/', urldecode( $data ), $cookies );

			//If we found a Cookie
			if( count( $cookies[0] ) > 0 ){

				//Get the Cookie
				$position 	= strpos( urldecode( $cookies[0][0] ) , '=' );
				$key  		= substr( urldecode( $cookies[0][0] ) , 0 , $position );
				$value 		= substr( urldecode( $cookies[0][0] ) , $position + 1 );
				$cookies 	= array_merge( ( isset( $this->session->cookies ) ? $this->session->cookies : array() ) , array( $key => trim( $value ) ) );

				//Set the Cookie
				$this->session->cookies = $cookies;

			}

			return strlen( $data );

		}



		
		/**
		*
		*	SEOMatic Error	
		*		Params:
		* 			- $error: 	(String) The Error to Show
		* 			- $type: 	(Constant) The E_USER Constants
		*
		*
		**/
		public function error($error,$type=E_USER_NOTICE){

			//Trigger Error
			trigger_error($error,$type);

			//Standard Return False Request
			return false;

		}







		/**
		*
		*	Get the PHP Version
		* 		Params:
		*
		**/
		public function version(){ 
			return $this->version; 
		}








		/**
		*
		*	Hash a String
		* 		Params:
		* 			- $str: 	(String) The String to Hash
		*
		**/
		public function hash($str){
			return preg_replace('/^[a-z0-9]/i','',md5('~!@#',sha1($str)));
		}






		/**
		*
		*	Benchmarking
		* 		Params:
		* 			- $key: 	(String) The Benchmark Key to Lookup with
		* 			- $status: 	(Bool) True to Start the Benchmark, False to Stop it
		*
		**/
		public function benchmark($key,$status=true){
			if($status){
				
				//Start the Benchmark	
				$this->benchmark[$key] = microtime(TRUE);
			
			}else
			if($this->debug && $this->debug['benchmark']){
				
				//End and Output the Benchmark
				echo '<!-- '.$key.': '.(microtime(TRUE) - $this->benchmark[$key]).' -->';
			
			}
		}










		/**
		*
		*	Output a Field in PRE tags for Viewing
		*	 	Params:
		* 			- $ref: 		(String) The Output Reference
		* 			- $obj: 		(Object) The Object to Output
		*
		**/
		public function output($ref,$obj){
			if($this->debug && $this->debug['output']){
				echo '<pre>';
					echo '<strong>'.$ref.'</strong>'.($this->log?' <small><em>- '.$this->log.'</em></small>':'').':<br />';
					echo '<small>';
						print_r($obj);
					echo '</small>';
				echo '</pre>';
			}

			//Clear the log
			$this->log = false;

		}







		/**
		*
		*	Log a Task (For Debugging)
		*	 	Params:
		* 			- $task: 		(Array) The Task Name
		*
		**/
		public function log($string,$severity=0){
			$this->log = $string;
		}




	}
 
?>