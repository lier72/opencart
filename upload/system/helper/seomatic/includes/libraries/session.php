<?php

	final class SEOMatic_Lib_Session {





		//The SEOMatic Data
		private $data = array();






		public function __construct(){

			//Check if PHP Sessions have been initialized
			if( ( PHP_VERSION < '5.4' && session_id() == '' ) || ( PHP_VERSION >= '5.4' && session_status() == PHP_SESSION_NONE ) ) session_start();

			//Save the Session Data
			$this->data =& $_SESSION;

			//Create the Subdata
			if(!isset( $this->data['seomatic'] )) $this->data['seomatic'] = array();

		}






		/**
		*
		*	__get
		* 		- Gets a Variable from the SEOMatic Session
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Lookup
		*
		*
		**/
		public function __get( $key = null ){
			return isset( $key ) ? $this->data['seomatic'][ $key ] : null ;
		}






		/**
		*
		*	__set
		* 		- Sets a Variable to the SEOMatic Session
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
		* 		- Delets the SEOMatic Session Variable
		*
		* 	Params:
		* 		- $key: 		(String) The Key to Check
		*
		*
		**/
		public function __unset( $key ){
			unset( $this->data['seomatic'][ $key ] );
		}






		/**
		*
		*	purge
		* 		- Deletes all of the SEOMatic Session Variables
		*
		* 	Params:
		* 		n/a
		*
		*
		**/
		public function purge(){
			foreach( $this->data['seomatic'] as $key => $val ){

				unset( $this->data['seomatic'][ $key ] );
		
			}
		}



	}
 
?>