<?php

	final class SEOMatic_Lib_Cookies {





		//The SEOMatic Data
		private $data = array();






		public function __construct(){

			//Save the Cookie Session Data
			$this->data =& $_COOKIE;

			//Create the Subdata
			if(!isset( $this->data['seomatic'] )) $this->data['seomatic'] = array();

		}






		/**
		*
		*	__get
		* 		- Gets a Variable from the SEOMatic Cookie Session
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Lookup
		*
		*
		**/
		public function __get( $key = null ){
			return isset( $key ) ? $this->data['seomatic'][ $key ] : $this->data['seomatic'] ;
		}






		/**
		*
		*	__set
		* 		- Sets a Variable to the SEOMatic Cookie Session
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Set
		* 		- $val: 		(String) The Value to SEt
		*
		*
		**/
		public function __set( $key , $val ){
			$this->data['seomatic'][ $key ] = $val;
		}






		/**
		*
		*	__isset
		* 		- Checks if an SEOMatic Variable is set
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Check
		*
		*
		**/
		public function __isset( $key ){
			return isset( $this->data['seomatic'][ $key ] );
		}






		/**
		*
		*	__unset
		* 		- Delets the SEOMatic Cookie Session Variable
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Check
		*
		*
		**/
		public function __unset( $key ){
			unset( $this->data['seomatic'][ $key ] );
		}



	}
 
?>