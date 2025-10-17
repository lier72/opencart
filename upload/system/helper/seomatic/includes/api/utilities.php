<?php

	class SEOMatic_Api_Utilities extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'batch' 			=> array( 'method' => 'post' , 	'url' => '/batch' ),
			'screenshot' 		=> array( 'method' => 'get'	,	'url' => '/utilities/screenshot' ),
			'speedtest' 		=> array( 'method' => 'get'	,	'url' => '/utilities/speedtest' ),
			'pagerank' 			=> array( 'method' => 'get'	,	'url' => '/utilities/pagerank' ),
			'insight'	 		=> array( 'method' => 'get'	,	'url' => '/utilities/insight' ),
			'ping' 				=> array( 'method' => 'get'	,	'url' => '/utilities/ping' ),
			'time' 				=> array( 'method' => 'get'	,	'url' => '/time' ),
			'update' 			=> array( 'method' => 'get'	,	'url' => '/update' )
		);





		/**
		*
		* 	batch
		*		- An Alternative to the Batch class
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- batch: 			(Object) The Batch Request
		*
		**/
		public function batch($data=array()){
			return $this->requestor->post( $this->requests['batch']['url'] , $data );
		}





		/**
		*
		* 	screenshot
		*		- Take a Screenshot
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		* 			- domain: 		(String) The Full Domain & Path to render
		*
		**/
		public function screenshot($data=array()){
			return $this->requestor->get( $this->requests['screenshot']['url'] , $data );
		}





		/**
		*
		* 	speedtest
		*		- Get the Page Load Information & Load Time
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		* 			- domain: 		(String) The Full Domain & Path to check
		*
		**/
		public function speedtest($data=array()){
			return $this->requestor->get( $this->requests['speedtest']['url'] , $data );
		}





		/**
		*
		* 	pagerank
		*		- Test the Page Rank of the Current Page
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- accountid: 	(INT) The Account ID
		* 			- domain: 		(String) The Full Domain & Path to check
		*
		**/
		public function pagerank($data=array()){
			return $this->requestor->get( $this->requests['pagerank']['url'] , $data );
		}





		/**
		*
		* 	insight
		*		- Get the Page Speed Insights of the Current Page
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- domain: 		(String) The Full Domain & Path to check
		* 			- strategy: 	(String) The Strategy to lookup (Options: mobile, desktop) (Default: desktop)
		*
		**/
		public function insight($data=array()){
			return $this->requestor->get( $this->requests['insight']['url'] , $data );
		}





		/**
		*
		* 	ping
		*		- Test if the User's Session is Active
		*
		* 	Params:
		* 		- n/a
		*
		**/
		public function ping($data=array()){
			return $this->requestor->get( $this->requests['ping']['url'] , $data );
		}





		/**
		*
		* 	time
		*		- Gets the Current Server Time
		*
		* 	Params:
		* 		- n/a
		*
		**/
		public function time($data=array()){
			$time = $this->requestor->get( $this->requests['time']['url'] , $data );
			return $time['time'];
		}





		/**
		*
		* 	@note 	NOT IMPLEMENTED
		*
		* 	update
		*		- Installs the Latest version
		*
		* 	Params:
		* 		- $data: 		(Array)
		* 			- platform: 	(String) The Application Platform / CMS
		* 			- version: 		(String) The Current Application Version
		*
		**/
		public function update($data=array()){
			return $this->requestor->get( $this->requests['update']['url'] , $data );
		}





	}
 
?>