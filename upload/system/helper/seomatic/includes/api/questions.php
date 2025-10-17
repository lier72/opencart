<?php

	class SEOMatic_Api_Questions extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/qna/question' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/qna/question' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/qna/question' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/qna/question' ),
			'vote' 			=> array( 'method' => 'put' 	,	'url' => '/qna/vote/question' ),
			'revote' 		=> array( 'method' => 'post' 	,	'url' => '/qna/vote/question' ),
			'unvote' 		=> array( 'method' => 'delete'	,	'url' => '/qna/vote/question' ),
			'votes' 		=> array( 'method' => 'get' 	,	'url' => '/qna/vote/question' ),
			'comment' 		=> array( 'method' => 'put' 	,	'url' => '/qna/comment/question' ),
			'recomment'		=> array( 'method' => 'post' 	,	'url' => '/qna/comment/question' ),
			'uncomment'		=> array( 'method' => 'delete'	,	'url' => '/qna/comment/question' ),
			'comments' 		=> array( 'method' => 'get' 	,	'url' => '/qna/comment/question' )
		);






		/**
		*
		* 	add
		*		- Add Question/s
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		(Object) An Object of ID and Type to associate the Question to any page 		* Optional *
		*				- id: 			(INT) The Link ID 															* Optional *
		*				- type: 		(String) The Link Type 														* Optional *
		*			- title: 		(String) The Title of the Question (Max: 255 Chars)
		*			- question: 	(String) The Content of the Question
		*			- tags: 		(Array) An Array of Tags to categorize to the question 							* Optional *
		*
		**/
		public function add($data=array()){
			return $this->requestor->put( $this->requests['add']['url'] , $data );
		}








		/**
		*
		* 	update
		*		- Update Question/s
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The ID of the Question
		*			- link: 		(Object) An Object of ID and Type to associate the Question to any page 		* Optional *
		*				- id: 			(INT) The Link ID 															* Optional *
		*				- type: 		(String) The Link Type 														* Optional *
		*			- title: 		(String) The Title of the Question (Max: 255 Chars) 							* Optional *
		*			- question: 	(String) The Content of the Question 											* Optional *
		*			- tags: 		(Array) An Array of Tags to categorize to the question 							* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post( $this->requests['update']['url'] , $data );
		}








		/**
		*
		* 	delete
		*		- Delete Question/s
		* 		
		*	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete( $this->requests['delete']['url'] , $data );
		}







		/**
		*
		*	get
		* 		-Get Question/s
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		(Object) An Object of ID and Type to associate the Question to anything 		* Optional *
		*				- id: 				(INT) The Link ID 														* Optional *
		*				- type: 			(String) The Link Type													* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- modified: 		(Object) The Date it was Modified 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- userid: 			(INT) The User ID 															* Optional *
		*			- tags: 			(Array) An Array of Tags to categorize to the questions						* Optional *
		*			- after: 			(INT) The Last ID to show Results After 									* Optional *
		*			- limit: 			(INT) The Maximum Items to Return (Default: 10) (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get( $this->requests['get']['url'] , $data );
		}









		/**
		*
		* 	vote
		*		- Add Vote
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function vote($data=array()){
			return $this->requestor->put( $this->requests['vote']['url'] , $data );
		}









		/**
		*
		* 	revote
		*		- Update Vote
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function revote($data=array()){
			return $this->requestor->post( $this->requests['revote']['url'] , $data );
		}









		/**
		*
		* 	unvote
		*		- Remove Vote
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*
		**/
		public function unvote($data=array()){
			return $this->requestor->delete( $this->requests['unvote']['url'] , $data );
		}









		/**
		*
		*	List Votes
		* 		- Get a List of Votes
		*
		* 	Params:
		* 		- $data: 			(Array)
		*			- questionid: 		(INT) The Question ID 													* Optional *
		*			- userid: 			(INT) The User ID 														* Optional *
		*			- after: 			(INT) The ID to get the Votes After 									* Optional *
		*			- limit: 			(INT) The Limit to Return (Default: 10) (Max: 100)
		*
		**/
		public function votes($data=array()){
			return $this->requestor->get( $this->requests['votes']['url'] , $data );
		}









		/**
		*
		*	Add Comment
		*		- Add a Comment
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*			- parentid: 	(INT) The Parent Comment ID 		* Optional *
		*			- comment: 		(String) The Comment to Add
		*
		**/
		public function comment($data=array()){
			return $this->requestor->put( $this->requests['comment']['url'] , $data );
		}









		/**
		*
		*	Update Comment
		*		- Update a Comment
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- commentid: 		(INT) The Comment ID
		*			- parentid:			(INT) The Parent Comment ID 	* Optional *
		*			- comment: 			(String) The Comment 			* Optional *
		*
		**/
		public function recomment($data=array()){
			return $this->requestor->post( $this->requests['recomment']['url'] , $data );
		}









		/**
		*	
		* 	uncomment
		*		- Remove Comment
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 	(INT) The Question ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function uncomment($data=array()){
			return $this->requestor->delete( $this->requests['uncomment']['url'] , $data );
		}









		/**
		*
		* 	comments
		*		- Get a List of Comments
		* 	
		* 	Params:
		* 		- $data: 		(Array)
		*			- questionid: 		(INT) The Question ID														* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- modified: 		(Object) The Date it was Modified 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- commentid: 		(INT) The Comment ID 														* Optional * 	
		*			- parentid: 		(INT) The Parent Comment ID 												* Optional *
		*			- userid: 			(INT) The User ID 															* Optional *
		*			- after: 			(INT) The Last ID to show Results After 									* Optional *
		*			- limit: 			(INT) The Maximum Items to Return (Default: 10) (Max: 100) 				
		*
		**/
		public function comments($data=array()){
			return $this->requestor->get( $this->requests['comments']['url'] , $data );
		}










	}
 
?>