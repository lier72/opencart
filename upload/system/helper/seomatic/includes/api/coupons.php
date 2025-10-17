<?php

	class SEOMatic_Api_Coupons extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/coupons' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/coupons' ),
		);



		/**
		*
		* 	__invoke
		*		- Get All Coupon(s)
		*
		* 	Params:
		*		- $data:		(Array)	
		* 			- coupon: 		(String) The Coupon to Look Up 										* Optional *
		*			- limit: 		(INT) The Total Coupons to Return (Maximum: 100) (Default: 10) 		* Optional *
		*			- after: 		(String) Get all Coupons After the passed Coupon ID 				* Optional *
		*
		**/
		public function __invoke($data){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}





		/**
		*
		* 	get
		*		- Get All Coupon(s)
		*
		* 	Params:
		*		- $data:		(Array)	
		* 			- coupon: 		(String) The Coupon to Look Up 										* Optional *
		*			- limit: 		(INT) The Total Coupons to Return (Maximum: 100) (Default: 10) 		* Optional *
		*			- after: 		(String) Get all Coupons After the passed Coupon ID 				* Optional *
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}




	}
 
?>