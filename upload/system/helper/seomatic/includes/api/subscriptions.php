<?php

	class SEOMatic_Api_Subscriptions extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	,	'url' => '/user/subscriptions' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/subscription' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/subscription' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/subscription' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/subscription' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/subscriptions' )
		);








		/**
		*
		* 	all
		*		- Get All User's Subscription/s
		*
		* 	Params:
		* 		- $data: 			(Array)
		* 			- subscriptionid: 	(INT) The Subscription ID to Lookup 								* Optional *
		* 			- limit: 			(INT) The Total Subscriptionss to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 			(INT) The Subscription ID to Return Results After 					* Optional *
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}









		/**
		*
		* 	add
		*		- Add Subscription
		*
		* 	Params:
		* 		- $data: 		(Array)
		*	 		- planid: 		(String) The Stripe Plan ID to Subscribe To
		*	 		- coupon: 		(String) The Coupon to Use 							* Optional *
		*
		**/
		public function add($data=array()){			
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);
		}







		/**
		*
		* 	update
		*		- Update Subscription
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		* 			- planid: 			(String) The Stripe Plan ID to Subscribe To
		* 			- coupon: 			(String) The Coupon to Use 						* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}







		/**
		*
		*	delete
		* 		- Delete Subscription
		*
		* 	Params:
		* 		n/a
		*
		**/
		public function delete(){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ));
		}







		/**
		*
		* 	get
		*		- Get Subscription
		* 	
		* 	Params:
		* 		- n/a
		*
		**/
		public function get(){
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ));
		}








		/**
		*
		* 	all
		*		- Get All User's Subscription/s
		*
		* 	Params:
		* 		- $data: 			(Array)
		* 			- subscriptionid: 	(INT) The Subscription ID to Lookup 								* Optional *
		* 			- limit: 			(INT) The Total Subscriptionss to Return (Max: 100, Default: 10) 	* Optional *
		* 			- after: 			(INT) The Subscription ID to Return Results After 					* Optional *
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}









	}
 
?>