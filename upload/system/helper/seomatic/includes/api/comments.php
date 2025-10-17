<?php

	class SEOMatic_Api_Comments extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/user/comment' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/user/comment' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/user/comment' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/user/comment' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/user/comment' )
		);







		/**
		*
		*	__invoke
		* 		- Get A List of Comment/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- link: 			(Object) An Object of ID and Type to associate the Comment to anything 	* Optional *
		*				- id: 				(INT) The Link ID 													* Optional * 
		*				- type: 			(String) The Link Type 												* Optional *
		*			- date: 			(Object) The Date it was Posted 										* Optional *
		*				- start: 			(UTC Time) The Start Time 											* Optional *
		*				- end: 				(UTC Time) The End Time 											* Optional *
		*			- modified: 		(Object) The Date it was Modified 										* Optional *
		*				- start: 			(UTC Time) The Start Time 											* Optional *
		*				- end: 				(UTC Time) The End Time 											* Optional *
		*			- commentid: 		(INT) The Comment ID 													* Optional *
		* 			- parent: 			(INT) The Parent Comment ID 											* Optional *
		*			- userid: 			(INT) The User ID 														* Optional *
		*			- after: 			(INT) The Last ID to show Results After 								* Optional *
		*			- limit: 			(INT) The Maximum Items to Return (Max: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get( $this->requests['__invoke']['url'] , $data );
		}





		/**
		*
		* 	add
		*		- Add Comment/s
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		* 			- parent: 		(INT) The Parent Comment ID
		*			- link: 		(Object) An Object of ID and Type to associate the Comment to anything
		*				- id: 			(INT) The Link ID to Associate the Comment To
		*				- type: 		(String) The Link Type to Associate the Comment To
		*			- comment: 		(String) The Comment to Add
		*
		**/
		public function add($data=array()){
			return $this->requestor->put( $this->requests['add']['url'] , $data );
		}








		/**
		*
		* 	update
		*		- Update Comment/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- commentid: 		(INT) The Comment ID
		* 			- parent: 			(INT) The Parent Comment ID
		*			- link: 			(Object) An Object of ID and Type to associate the Comment to anything
		*				- id: 				(INT) The Link ID
		*				- type: 			(String) The Link Type
		*			- comment: 			(String) The Comment
		*
		**/
		public function update($data=array()){
			return $this->requestor->post( $this->requests['update']['url'] , $data );
		}








		/**
		*
		* 	delete
		*		- Delete Comment/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- commentid: 		(INT) The Comment ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete( $this->requests['delete']['url'] , $data );
		}







		/**
		*
		*	get
		* 		- Get A List of Comment/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- link: 			(Object) An Object of ID and Type to associate the Comment to anything 	* Optional *
		*				- id: 				(INT) The Link ID 													* Optional * 
		*				- type: 			(String) The Link Type 												* Optional *
		*			- date: 			(Object) The Date it was Posted 										* Optional *
		*				- start: 			(UTC Time) The Start Time 											* Optional *
		*				- end: 				(UTC Time) The End Time 											* Optional *
		*			- modified: 		(Object) The Date it was Modified 										* Optional *
		*				- start: 			(UTC Time) The Start Time 											* Optional *
		*				- end: 				(UTC Time) The End Time 											* Optional *
		*			- commentid: 		(INT) The Comment ID 													* Optional *
		* 			- parent: 			(INT) The Parent Comment ID 											* Optional *
		*			- userid: 			(INT) The User ID 														* Optional *
		*			- after: 			(INT) The Last ID to show Results After 								* Optional *
		*			- limit: 			(INT) The Maximum Items to Return (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}








	}
 
?>