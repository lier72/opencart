<?php

	class SEOMatic_Api_Conversions extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/conversion' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/conversion' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/conversion' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/conversion' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/conversion' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/conversion' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Conversions
		* 		
		* 	Params:
		* 		- $data:		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- orderid: 		(INT) The Order ID 															* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Conversion to anything 	* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- date: 		(Object) 																	* Optional *
		*				- start: 		(UTC Timezone) The Start Date 											* Optional *
		*				- end: 			(UTC Timezone) The End Date 											* Optional *
		*			- name: 		(String) The Product Name to Match											* Optional *
		*			- search: 		(String) The Product Name to Search 										* Optional *
		*			- quantity: 	(Object) 																	* Optional *
		*				- min: 			(INT) The Min quantity 													* Optional *
		*				- max: 			(INT) The Max Quantity	 												* Optional *
		*			- total: 		(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Total 												* Optional *
		*				- max: 			(Decimal) The Max Total 												* Optional *
		*			- shipping: 	(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Shipping total 										* Optional *
		*				- max: 			(Decimal) The Max Shipping Total 										* Optional *
		*			- tax: 			(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Tax 													* Optional *
		*				- max: 			(Decimal) The Max Tax 													* Optional *
		*			- userid: 		(INT) The User ID 															* Optional *
		*			- after: 		(String) The Conversion ID to get Results After. 						 	* Optional *
		*			- limit: 		(INT) The Maximum amount of results to return (Max: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add Conversion/s
		* 	
		* 	Params:
		*		- $data:		(Array)
		*			- accountid: 	(INT) The Account ID
		*			- orderid: 		(Object) The Internal Order ID
		*			- link: 		(Object) An Object of ID and Type to associate the Conversion to anything
		*				id: 			(INT) The Link ID
		*				type: 			(String) The Link Type
		*			- name: 		(String) The Product Name
		*			- quantity: 	(INT) The Quantity Sold
		*			- total: 		(Float) The Total Price
		*			- shipping: 	(Float) The Total Shipping
		*			- tax: 			(Float) The Total Tax
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf('/account/%s/conversion',$this->accountid),$data);
		}








		/**
		*
		* 	updated
		*		- Update Conversion/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID
		*			- conversionid: (INT) The Conversion ID
		*			- orderid: 		(Object) The Internal Order ID 												* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Conversion to anything 	* Optional *
		*				id: 			(INT) The Link ID 														* Optional *
		*				type: 			(String) The Link Type 													* Optional *
		*			- name: 		(String) The Product Name 													* Optional *
		*			- date: 		(UTC Timestamp) The Date of the Conversion 									* Optional *
		*			- quantity: 	(INT) The Quantity Sold 													* Optional *
		*			- total: 		(Float) The Total Price 													* Optional *
		*			- shipping: 	(Float) The Total Shipping Price											* Optional *
		*			- tax: 			(Float) The Total Tax 														* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf('/account/%s/conversion',$this->accountid),$data);
		}










		/**
		*
		* 	delete
		*		- Delete Conversion/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- acccountid: 		(INT) The Account ID
		*			- conversionid: 	(INT) The Conversion ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf('/account/%s/conversion',$this->accountid),$data);
		}










		/**
		*
		* 	get
		*		- Get A List of Conversions
		* 		
		* 	Params:
		* 		- $data:		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- orderid: 		(INT) The Order ID 															* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Conversion to anything 	* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- date: 		(Object) 																	* Optional *
		*				- start: 		(UTC Timezone) The Start Date 											* Optional *
		*				- end: 			(UTC Timezone) The End Date 											* Optional *
		*			- name: 		(String) The Product Name to Match											* Optional *
		*			- search: 		(String) The Product Name to Search 										* Optional *
		*			- quantity: 	(Object) 																	* Optional *
		*				- min: 			(INT) The Min quantity 													* Optional *
		*				- max: 			(INT) The Max Quantity	 												* Optional *
		*			- total: 		(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Total 												* Optional *
		*				- max: 			(Decimal) The Max Total 												* Optional *
		*			- shipping: 	(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Shipping total 										* Optional *
		*				- max: 			(Decimal) The Max Shipping Total 										* Optional *
		*			- tax: 			(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Tax 													* Optional *
		*				- max: 			(Decimal) The Max Tax 													* Optional *
		*			- userid: 		(INT) The User ID 															* Optional *
		*			- after: 		(String) The Conversion ID to get Results After. 						 	* Optional *
		*			- limit: 		(INT) The Maximum amount of results to return (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get(sprintf('/account/%s/conversion',$this->accountid),$data);
		}









		/**
		*
		* 	all
		*		- Get A List of all of the User's Conversions
		* 		
		* 	Params:
		* 		- $data:		(Array)
		*			- accountid: 	(INT) The Account ID 														* Optional *
		*			- orderid: 		(INT) The Order ID 															* Optional *
		*			- link: 		(Object) An Object of ID and Type to associate the Conversion to anything 	* Optional *
		*				- id: 			(INT) The Link ID 														* Optional *
		*				- type: 		(String) The Link Type 													* Optional *
		*			- date: 		(Object) 																	* Optional *
		*				- start: 		(UTC Timezone) The Start Date 											* Optional *
		*				- end: 			(UTC Timezone) The End Date 											* Optional *
		*			- name: 		(String) The Product Name to Match											* Optional *
		*			- search: 		(String) The Product Name to Search 										* Optional *
		*			- quantity: 	(Object) 																	* Optional *
		*				- min: 			(INT) The Min quantity 													* Optional *
		*				- max: 			(INT) The Max Quantity	 												* Optional *
		*			- total: 		(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Total 												* Optional *
		*				- max: 			(Decimal) The Max Total 												* Optional *
		*			- shipping: 	(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Shipping total 										* Optional *
		*				- max: 			(Decimal) The Max Shipping Total 										* Optional *
		*			- tax: 			(Object) 																	* Optional *
		*				- min: 			(Decimal) The Min Tax 													* Optional *
		*				- max: 			(Decimal) The Max Tax 													* Optional *
		*			- userid: 		(INT) The User ID 															* Optional *
		*			- after: 		(String) The Conversion ID to get Results After. 						 	* Optional *
		*			- limit: 		(INT) The Maximum amount of results to return (Max: 100)
		*
		**/
		public function all($data=array()){
			return $this->requestor->get('/user/conversion',$data);

		}








	}
 
?>