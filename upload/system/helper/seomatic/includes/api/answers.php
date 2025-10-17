<?php

	class SEOMatic_Api_Answers extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/qna/answer' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/qna/answer' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/qna/answer' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/qna/answer' ),
			'vote' 			=> array( 'method' => 'put' 	,	'url' => '/qna/vote/answer' ),
			'revote' 		=> array( 'method' => 'post' 	,	'url' => '/qna/vote/answer' ),
			'unvote' 		=> array( 'method' => 'delete'	,	'url' => '/qna/vote/answer' ),
			'votes' 		=> array( 'method' => 'get' 	,	'url' => '/qna/vote/answer' ),
			'comment' 		=> array( 'method' => 'put' 	,	'url' => '/qna/comment/answer' ),
			'recomment'		=> array( 'method' => 'post' 	,	'url' => '/qna/comment/answer' ),
			'uncomment'		=> array( 'method' => 'delete'	,	'url' => '/qna/comment/answer' ),
			'comments' 		=> array( 'method' => 'get' 	,	'url' => '/qna/comment/answer' )
		);





		/**
		*
		* 	add
		*		- Add Answer/s
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- answer: 		(String) The Answer
		*
		**/
		public function add($data=array()){
			return $this->requestor->put('/qna/answer',$data);
		}








		/**
		*
		* 	update
		*		Update Answer/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- answer: 		(String) The Answer
		*
		**/
		public function update($data=array()){
			return $this->requestor->post('/qna/answer',$data);
		}








		/**
		*
		* 	delete
		*		- Delete Answer/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete('/qna/answer',$data);
		}







		/**
		*
		* 	get
		*		- Get Answer/s
		* 		
		* 	Params:
		* 		- $data: 			(Array)
		*			- answerid: 		(INT) The Answer ID															* Optional *
		*			- date: 			(Object) The Date it was Posted 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- modified: 		(Object) The Date it was Modified 											* Optional *
		*				- start: 			(UTC Time) The Start Time 												* Optional *
		*				- end: 				(UTC Time) The End Time 												* Optional *
		*			- userid: 			(INT) The User ID 															* Optional *
		*			- tags: 			(Array) An Array of Tags to categorize to the answers						* Optional *
		*			- after: 			(INT) The Last ID to show Results After 									* Optional *
		*			- limit: 			(INT) The Maximum Items to Return (Default: 10) (Max: 100)
		*
		**/
		public function get($data=array()){		
			return $this->requestor->get('/qna/answer',$data);
		}









		/**
		*
		* 	add
		*		- Add Vote
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function vote($data=array()){
			return $this->requestor->put('/qna/vote/answer',$data);
		}









		/**
		*
		* 	revote
		*		- Update a Vote
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function revote($data=array()){
			return $this->requestor->post('/qna/vote/answer',$data);
		}









		/**
		*
		* 	unvote
		*		- Remove Vote
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*
		**/
		public function unvote($data=array()){
			return $this->requestor->delete('/qna/vote/answer',$data);
		}









		/**
		*
		* 	votes
		*		- List Votes
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID 													* Optional *
		*			- userid: 		(INT) The User ID 														* Optional *
		*			- after: 		(INT) The ID to get the Votes After 									* Optional *
		*			- limit: 		(INT) The Limit to Return (Default: 10) (Max: 100)
		*
		**/
		public function votes($data=array()){
			return $this->requestor->get('/qna/vote/answer',$data);
		}









		/**
		*
		* 	comment
		*		- Add Comment
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- parent: 		(INT) The Parent Comment ID 		* Optional *
		*			- comment: 		(String) The Comment to Add
		*
		**/
		public function comment($data=array()){
			return $this->requestor->put('/qna/comment/answer',$data);
		}









		/**
		*
		* 	update
		*		- Update Comment
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function recomment($data=array()){
			return $this->requestor->post('/qna/comment/answer',$data);
		}









		/**
		*
		* 	remove
		*		- Remove Comment
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- answerid: 	(INT) The Answer ID
		*			- vote: 		(Bool) True for UP, False for DOWN, (Default: FALSE)
		*
		**/
		public function uncomment($data=array()){
			return $this->requestor->delete('/qna/comment/answer',$data);
		}









		/**
		*
		*	comments
		* 		- List Comments
		*
		* 	Params:
		* 		- $data:			(Array)
		*			- answerid: 		(INT) The Answer ID															* Optional *
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
			return $this->requestor->get('/qna/comment/answer',$data);
		}








	}
 
?>