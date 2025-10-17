<?php

	class SEOMatic_Api_Notifications extends SEOMatic_Lib_Engine {


		//The Requests
		public $requests = array(
			'__invoke' 		=> array( 'method' => 'get' 	, 	'url' => '/account/%s/notifications' ),
			'get' 			=> array( 'method' => 'get' 	, 	'url' => '/account/%s/notifications' ),
			'update' 		=> array( 'method' => 'post' 	,	'url' => '/account/%s/notifications' ),
			'all' 			=> array( 'method' => 'get' 	, 	'url' => '/account/%s/notifications' ),
		);







		/**
		*
		* 	__invoke
		*		- Get A list of Notification(s)
		* 		
		* 	Params:
		*		- $data:		(Array)
		*			- accountid: 			(INT) The Account ID
		*			- notificationid: 		(INT) The Notification ID
		*
		**/
		public function __invoke($data=array()){
			return $this->requestor->get(sprintf($this->requests['__invoke']['url'],$this->accountid),$data);
		}





		/**
		*
		* 	get
		*		- Get A list of Notification(s)
		* 	
		* 	Params:
		*		- $data:		(Array)
		*			- accountid: 			(INT) The Account ID
		*			- notificationid: 		(INT) The Notification ID
		*
		**/
		public function get($data=array()){
			return $this->requestor->get(sprintf($this->requests['get']['url'],$this->accountid),$data);
		}








		/**
		*
		* 	update
		*		- Update The Notification Frequency
		* 		
		* 	Params:
		*		- $data:		(Array)
		*			- accountid: 			(INT) The Account ID
		*			- notificationid: 		(INT) The Notification ID
		* 			- frequency: 			(INT) The Frequency to Set (0: Never, 1: Daily, 2: Weekly)
		*
		**/
		public function update($data=array()){
			return $this->requestor->post(sprintf($this->requests['update']['url'],$this->accountid),$data);
		}





		/**
		*
		* 	get
		*		- Get A list of Notification(s)
		* 	
		* 	Params:
		*		- $data:		(Array)
		*			- accountid: 			(INT) The Account ID
		*			- notificationid: 		(INT) The Notification ID
		*
		**/
		public function all($data=array()){
			return $this->requestor->get(sprintf($this->requests['all']['url'],$this->accountid),$data);
		}








	}
 
?>