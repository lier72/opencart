<?php

	class SEOMatic_Api_Plans extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	,	'url' => '/plans' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/plans' ),
		);







		/**
		*
		* 	__invoke
		*		- Get All of the Available Plan/s
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function __invoke(){	
			return $this->requestor->get( $this->requests['__invoke']['url'] );
		}







		/**
		*
		* 	all
		*		- Get All of the Available Plan/s
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function all(){	
			return $this->requestor->get( $this->requests['all']['url'] );
		}









	}
 
?>