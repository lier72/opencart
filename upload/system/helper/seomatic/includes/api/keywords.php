<?php

	class SEOMatic_Api_Keywords extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/keyword' ),
			'add' 			=> array( 'method' => 'put' 	,	'url' => '/account/%s/keyword' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/keyword' ),
			'delete' 		=> array( 'method' => 'delete'	,	'url' => '/account/%s/keyword' ),
			'available' 	=> array( 'method' => 'get' 	,	'url' => '/account/%s/keywords/available' ),
			'get' 			=> array( 'method' => 'get' 	,	'url' => '/account/%s/keyword' ),
			'all' 			=> array( 'method' => 'get' 	,	'url' => '/user/keyword' )
		);







		/**
		*
		* 	__invoke
		*		- Get A List of Keywords
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		(Array)
		*				- id: 			(INT) The Link ID to Associate the Keyword to 							** Optional **
		*				- type: 		(String) The Link Type to Associate the Keyword to 						** Optional **
		*			- keywordid: 	(INT) The Keyword ID 														** Optional **
		*			- accountid: 	(INT) The Account ID 														** Optional **
		*			- countryid: 	(INT) The Country ID 														** Optional **
		*			- keyword: 		(String) The Keyword 														** Optional **
		*			- search: 		(String) Search for a Keyword starting with 								** Optional **
		*			- added: 		(Object) The Date the Keyword was Added										** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- updated: 		(Object) The Last time the Keyword was Scraped 								** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- status: 		(INT) Add the Keyword to the Scraper? (1: Yes, 2: No) 						** Optional **
		*			- after: 		(INT) Get Users Keywords After the passed Keyword ID 						** Optional **
		*			- before: 		(INT) Get Users Keywords Before the passed Keyword ID 						** Optional **
		*			- limit: 		(INT) The Maximum amount of keywords to show (Default: 10) (Maximum: 100)
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf( $this->requests['__invoke']['url'] , $this->accountid ),$data);
		}





		/**
		*
		* 	add
		*		- Add Keyword/s
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID
		*			- link: 		(Object) An Object of ID and Type to associate the Keyword to anything
		*				- id: 			(INT) The Link ID of the Keyword
		*				- type: 		(String) The Link Type of the Keyword
		*			- domainid: 	(INT) The Domain ID to associate the Keyword to
		*			- countryid: 	(INT) The Country ID to check the Keyword with
		*			- keyword: 		(String) The Keyword
		*			- active: 		(Bool) Is the Keyword Active or Inactive? 								* Optional *
		*
		**/
		public function add($data=array()){
			return $this->requestor->put(sprintf( $this->requests['add']['url'] , $this->accountid ),$data);		
		}








		/**
		*
		*	update
		*		- Update Keyword/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		*			- accountid: 	(INT) The Account ID
		*			- link: 		(Object) An Object of ID and Type to associate the Keyword to anything 	* Optional *
		*				- id: 			(INT) The Link ID of the Keyword 									* Optional *
		*				- type: 		(String) The Link Type of the Keyword 								* Optional *
		*			- domainid: 	(INT) The Domain ID to associate the Keyword to 						* Optional *
		*			- countryid: 	(INT) The Country ID to check the Keyword with 							* Optional *
		*			- keyword: 		(String) The Keyword 													* Optional *
		*			- active: 		(Bool) Is the Keyword Active or Inactive? 								* Optional *
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf( $this->requests['update']['url'] , $this->accountid ),$data);
		}








		/**
		*
		* 	delete
		*		- Delete Domain/s
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		* 		 	- keywordid: 	(INT) The Domain ID to Update
		*
		**/
		public function delete($data=array()){
			return $this->requestor->delete(sprintf( $this->requests['delete']['url'] , $this->accountid ),$data);
		}









		/**
		*
		* 	available
		*		- Total Keywords Available
		* 		
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		*
		**/
		public function available($data=array()){
			return $this->requestor->get(sprintf( $this->requests['available']['url'] , $this->accountid ),$data);
		}







		/**
		*
		* 	get
		*		- Get Domain/s
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		 (Array)
		*				- id: 			(INT) The Link ID to Associate the Keyword to 							** Optional **
		*				- type: 		(String) The Link Type to Associate the Keyword to 						** Optional **
		*			- keywordid: 	(INT) The Keyword ID 														** Optional **
		*			- accountid: 	(INT) The Account ID 														** Optional **
		*			- countryid: 	(INT) The Country ID 														** Optional **
		*			- keyword: 		(String) The Keyword 														** Optional **
		*			- search: 		(String) Search for a Keyword starting with 								** Optional **
		*			- added: 		(Object) The Date the Keyword was Added										** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- updated: 		(Object) The Last time the Keyword was Scraped 								** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- status: 		(INT) Add the Keyword to the Scraper? (1: Yes, 2: No) 						** Optional **
		*			- after: 		(INT) Get Users Keywords After the passed Keyword ID 						** Optional **
		*			- before: 		(INT) Get Users Keywords Before the passed Keyword ID 						** Optional **
		*			- limit: 		(INT) The Maximum amount of keywords to show (Default: 10) (Maximum: 100)
		*
		**/
		public function get($data=array()){	
			return $this->requestor->get(sprintf( $this->requests['get']['url'] , $this->accountid ),$data);
		}




		/**
		*
		* 	get
		*		- Get All User Domains
		*
		* 	Params:
		* 		- $data: 		(Array)
		*			- link: 		 (Array)
		*				- id: 			(INT) The Link ID to Associate the Keyword to 							** Optional **
		*				- type: 		(String) The Link Type to Associate the Keyword to 						** Optional **
		*			- keywordid: 	(INT) The Keyword ID 														** Optional **
		*			- accountid: 	(INT) The Account ID 														** Optional **
		*			- countryid: 	(INT) The Country ID 														** Optional **
		*			- keyword: 		(String) The Keyword 														** Optional **
		*			- search: 		(String) Search for a Keyword starting with 								** Optional **
		*			- added: 		(Object) The Date the Keyword was Added										** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- updated: 		(Object) The Last time the Keyword was Scraped 								** Optional **
		*				- start: 		(UTC Time) The Start Time 												** Optional **
		*				- end: 			(UTC Time) The End Time  												** Optional **
		*			- status: 		(INT) Add the Keyword to the Scraper? (1: Yes, 2: No) 						** Optional **
		*			- after: 		(INT) Get Users Keywords After the passed Keyword ID 						** Optional **
		*			- before: 		(INT) Get Users Keywords Before the passed Keyword ID 						** Optional **
		*			- limit: 		(INT) The Maximum amount of keywords to show (Default: 10) (Maximum: 100)
		*
		**/
		public function all($data=array()){
			return $this->requestor->get( $this->requests['all']['url'] , $data );
		}








	}
 
?>