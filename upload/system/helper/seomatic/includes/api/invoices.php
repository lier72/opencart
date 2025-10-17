<?php

	class SEOMatic_Api_Invoices extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/user/invoices' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/user/invoices' )
		);








		/**
		*
		* 	__invoke
		*		- Get A List of Invoices
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- invoiceid: 	(INT) The Invoice ID 														* Optional *
		*			- after: 		(String) Return Invoices after The Invoice ID 								* Optional *
		*			- date: 		(Object) A Start and End Date to query for Invoices 						* Optional *
		*				- Start: 		(UTC Timestamp) The Start Date to Query for 							* Optional *
		*				- End: 			(UTC Timestamp) The End Date to Query for 								* Optional *
		*			- limit: 		(INT) The Max number of invoices to return (Default: 10) (Max: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] ,$data );
		}






		/**
		*
		* 	get
		*		- Get An Invoice
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- invoiceid: 	(INT) The Invoice ID 														* Optional *
		*			- after: 		(String) Return Invoices after The Invoice ID 								* Optional *
		*			- date: 		(Object) A Start and End Date to query for Invoices 						* Optional *
		*				- Start: 		(UTC Timestamp) The Start Date to Query for 							* Optional *
		*				- End: 			(UTC Timestamp) The End Date to Query for 								* Optional *
		*			- limit: 		(INT) The Max number of invoices to return (Default: 10) (Max: 100)
		*
		**/
		public function get($data=array()){
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}




	}
 
?>