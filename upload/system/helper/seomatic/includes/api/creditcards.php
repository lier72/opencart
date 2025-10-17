<?php

	class SEOMatic_Api_CreditCards extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/user/creditcard' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/user/creditcard' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/user/creditcard' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/user/creditcard' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/user/creditcard' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/creditcard' ),
			'accepted' 		=> array( 'method' => 'get' 	, 	'url' => '/creditcards' ),
			'security' 		=> array( 'method' => 'get' 	, 	'url' => '/security' ),
		);
		





		/**
		*
		* 	__invoke
		*		- Get A List of Credit Cards
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 			- cardid: 		(String) The Card ID
		*			- limit: 		(INT) The Total Cards to Show (Maximum: 100) (Default: 10)
		*			- after: 		(String) Get all Cards after the passed Card ID.
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}






		/**
		*
		* 	add
		*		- Add Credit Card
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- number: 		(INT) The Card Number
		* 			- expiry: 		(Object) The Card Expiry
		* 				- month: 		(INT) The Card Expiry Month (MM)
		* 				- year: 		(INT) The Card Expiry Year 	(YYYY)
		* 			- cvc: 			(INT) The Card Security Code
		* 			- name: 		(String) The Name on the Card 	
		* 			- default: 		(Bool) Make this the defaul Card?
		*
		**/
		public function add($data=array()){
			return $this->requestor->put( $this->requests['add']['url'] , $data );		}







		/**
		*
		* 	update
		*		- Update Credit Card
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- cardid: 		(String) The Card ID
		* 			- number: 		(INT) The Card Number
		* 			- expiry: 		(Object) The Card Expiry
		* 				- month: 		(INT) The Card Expiry Month (MM)
		* 				- year: 		(INT) The Card Expiry Year 	(YYYY)
		* 			- cvc: 			(INT) The Card Security Code
		* 			- name: 		(String) The Name on the Card 				* Optional *
		* 			- default: 		(Bool) Make this the defaul Card? 			* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post( $this->requests['update']['url'] , $data );
		}






		/**
		*
		* 	delete
		*		- Delete Credit Card
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- cardid: 		(String) The Card ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete( $this->requests['delete']['url'] , $data );
		}





		/**
		*
		* 	get
		*		- Get A List of Credit Cards
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 			- cardid: 		(String) The Card ID
		*			- limit: 		(INT) The Total Cards to Show (Maximum: 100) (Default: 10)
		*			- after: 		(String) Get all Cards after the passed Card ID.
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}





		/**
		*
		* 	all
		*		- Get A List of Credit Cards
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 			- cardid: 		(String) The Card ID
		*			- limit: 		(INT) The Total Cards to Show (Maximum: 100) (Default: 10)
		*			- after: 		(String) Get all Cards after the passed Card ID.
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}





		/**
		*
		* 	accepted
		*		- Get A List of Accepted Credit Cards
		* 	
		* 	Params:
		* 		n/a
		*
		**/
		public function accepted($data=array()){
			return $this->requestor->get( $this->requests['accepted']['url'] , $data );
		}





		/**
		*
		* 	security
		*		- Get the Secured By Image
		* 	
		* 	Params:
		* 		n/a
		*
		**/
		public function security($data=array()){
			return $this->requestor->get( $this->requests['security']['url'] , $data );
		}





	}
 
?>