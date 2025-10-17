<?php

	class SEOMatic_Api_Notes extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/note' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/note' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/note' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/note' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/note' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/subscriptions' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Notes
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		(Object) An Object of ID and Type to associate the Note to anything 			* Optional *
		*				- id: 				(INT) The Link ID  														* Optional *
		*				- type: 			(String) The Link Type 													* Optional *
		*			- reference: 	(String) LIKE% Reference Search to link page specific items (Max: 100 Chars) 	* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- noteid: 		(INT) The Note ID 																* Optional *
		*			- accountid: 	(INT) The Account ID 															* Optional *
		*			- after: 		(INT) The Last ID to show Results After 										* Optional *
		*			- limit: 		(INT) The Maximum Items to Return (Default: 10) (Max: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add a Note
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID
		*			- link: 		(Object) An Object of ID and Type to associate the Note to anything
		*				- id: 			(INT) The Link ID to Associate the Note To
		*				- type: 		(String) The Link Type to Associate the Note To
		*			- reference: 	(String) LIKE% Reference Search to link page specific items (Max: 100 Chars) 	* Optional *
		* 			- date: 		(UTC Time) The Date of the Note (If not Passed, Defaults to Current Time) 		* Optional *
		*			- note: 		(String) The Note to Add
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);
		}








		/**
		*
		*	update
		*		- Update a Note
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- noteid: 		(INT) The Note ID
		*			- link: 		(Object) An Object of ID and Type to associate the Note to anything 			* Optional *
		*				- id: 			(INT) The Link ID 															* Optional *
		*				- type: 		(String) The Link Type 														* Optional *
		*			- reference: 	(String) LIKE% Reference Search to link page specific items (Max: 100 Chars) 	* Optional *
		* 			- date: 		(UTC Time) The Date of the Note 												* Optional *
		*			- note: 		(String) The Note 																* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}








		/**
		*
		* 	delete
		*		- Delete a Note
		* 	
		* 	Params:
		* 		- $data: 		(Object)
		*			- noteid: 		(INT) The Note ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ),$data);
		}







		/**
		*
		* 	get
		*		- Get Note/s
		* 	
		* 	Params:
		* 		- $data: 		(Object)
		*			- link: 		(Object) An Object of ID and Type to associate the Note to anything 			* Optional *
		*				- id: 				(INT) The Link ID  														* Optional *
		*				- type: 			(String) The Link Type 													* Optional *
		*			- reference: 	(String) LIKE% Reference Search to link page specific items (Max: 100 Chars) 	* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- noteid: 		(INT) The Note ID 																* Optional *
		*			- accountid: 	(INT) The Account ID 															* Optional *
		*			- after: 		(INT) The Last ID to show Results After 										* Optional *
		*			- limit: 		(INT) The Maximum Items to Return (Default: 10) (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}




		/**
		*
		* 	all
		*		- Get All User Note/s
		* 		
		* 	Params:
		* 		- $data: 		(Object)
		*			- link: 		(Object) An Object of ID and Type to associate the Note to anything 			* Optional *
		*				- id: 				(INT) The Link ID  														* Optional *
		*				- type: 			(String) The Link Type 													* Optional *
		*			- reference: 	(String) LIKE% Reference Search to link page specific items (Max: 100 Chars) 	* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- noteid: 		(INT) The Note ID 																* Optional *
		*			- accountid: 	(INT) The Account ID 															* Optional *
		*			- after: 		(INT) The Last ID to show Results After 										* Optional *
		*			- limit: 		(INT) The Maximum Items to Return (Default: 10) (Max: 100)
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );

		}








	}
 
?>