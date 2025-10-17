<?php
	
	abstract class SEOMatic_Lib_Engine {

		//The Registry Variable
		protected $registry;
		

		/**
		*
		* 	A Standard Construct Function
		* 		Params:
		* 			- $registry: 	(Object) The Registry Object
		*
		**/
		public function __construct($registry){
			$this->registry = $registry;
		}
		

		/**
		*
		*	Redirect all Get Requests to the Registry
		* 		Params:
		* 			- $key:  	(String) The Key to find
		*
		**/
		public function __get($key){
			if( method_exists( $this , $key ) ){

				$class = get_class( $this );

				return strtolower( substr( $class , strrpos( $class , '_' ) + 1 ) ) . ':' . $key;

			}else{

				return $this->registry->get($key);
			}
		}



		/**
		*
		*	Redirect all Set Requests to the Registry
		* 		Params:
		* 			- $key:  	(String) The Key to find
		* 			- $val: 	(Object) The Variable to Set
		*
		**/
		public function __set($key,$val){
			$this->registry->set($key, $val);
		}
		


		/**
		*
		*	Redirect all pluralized Calls to the Unpluralized Equivalent - I.E. addDomains( (Batch) ) -> addDomain( (Batch) )
		* 		Params:
		* 			- $method: 	(String) The Method Called
		* 			- $args: 	(Object) An Array of Arguments Passed
		*
		*
		**/
		public function __call($method,$args){
			if( method_exists($this,rtrim($method,'s')) ){

				//The Call was Pluralized, Check if an unpluralized Request Exists
				return call_user_func_array(array($this,rtrim($method,'s')),$args);
			
			}else{

				//Invalid API Call
				return $this->utilities->error('Invalid API Call: '.$method);
			
			}			
		}


	}
?>