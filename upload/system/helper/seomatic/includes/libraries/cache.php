<?php

	final class SEOMatic_Lib_Cache extends SEOMatic_Lib_Engine {
		
		public $writable = false;


		/**
		*
		* 	A Standard Construct Function
		* 		Params:
		* 			- $registry: 	(Object) The Registry Object
		*
		**/
		public function __construct($registry){
			
			//Setup the Registry
			$this->registry = $registry;
			
			//Check if its writable
			$this->writable = is_writable( $this->ABS_PATH . '/includes/cache/' );

		}




		/**
		*
		*	get
		* 		- Stores Local Cache for 
		*
		* 	Params:
		* 		- $group: 		(String) The Key Group to Lookup
		* 		- $key: 		(String) The Key to Lookup
		*
		*
		**/
		public function get( $group , $key = null ){

			if( file_exists( $this->ABS_PATH . '/includes/cache/' . $group . $this->stringify( $key ) ) ){

				$data = json_decode( file_get_contents( $this->ABS_PATH . '/includes/cache/' . $group . $this->stringify( $key ) ) , true );

				if( empty($data['expires']) || time() < (int)$data['expires'] ){

					return $data['cache'] ;

				}

			}

			return false;

		}








		/**
		*
		*	store
		* 		- Stores Local Cache for 
		*
		*	Params:
		* 		- $group: 		(String) The Key Group to Store
		* 		- $key: 		(String) The Key to Store
		* 		- $cache: 		(Object) The Object | Array to Store
		* 		- $ttl: 		(String) The Time to Live (24 hours, 15 minutes, 10 seconds, etc.)
		*
		**/
		public function store( $group , $key = null , $cache , $ttl = null ){

			file_put_contents( $this->ABS_PATH . '/includes/cache/' . $group . $this->stringify( $key ) , json_encode( array(

				'expires' 	=> ( $ttl ? strtotime( $ttl , time() ) : '' ) ,

				'cache' 	=> $cache

			) ) );

			return $cache;

		}






		/**
		*
		*	purge
		* 		- Purge all Local Cache based on the Wildcard
		*
		*	Params:
		* 		- $wildcard: 		(String) The Key Wildcard to Search
		*
		**/
		public function purge( $wildcard = '*' ){

			$files = glob( $this->ABS_PATH . '/includes/cache/' . $wildcard );

			foreach( $files as $file ){

				unlink( $file );

			}


		}






		/**
		*
		*	stringify
		* 		- Modifies the Cache Filename into a parsable Format
		*
		*	Params:
		* 		- $object: 		(Object) The Object | Array to Modify
		*
		**/
		public function stringify( $object ){
			if( is_array($object) ){

				$object = is_array($object) && isset( $object[0] ) ? $object[0] : $object ;

				return trim( preg_replace( '/_+/' , '_' , preg_replace( '/[^a-z0-9]/' , '_' , json_encode( $object ) ) ) , '_' ) ;

			}else{

				return $object;

			}
		}



	}
 
?>