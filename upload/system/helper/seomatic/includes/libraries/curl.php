<?php

	final class SEOMatic_Lib_cURL {


		//The cURL Handler
		private $ch;


		//The HTML returned from the Request
		private $html;


		//The Request cURL Info
		public $info;


		//cURL Preset Options
		protected $presets = array(
			'returntransfer' 		=> true,
			'connecttimeout' 		=> 30,
			//'followlocation' 		=> true, 	* This was breaking on some sites, not really needed for SEOMatic
			'header' 				=> false,
			'ssl_verifypeer' 		=> 1,
			'ssl_verifyhost' 		=> 2,
			'encoding' 				=> 'UTF-8'
		);





		/**
		*
		*	Set the cURL Options
		* 		Params:
		* 			- $key: 		(String) The cURL Option
		* 			- $val: 		(String) The CURLOPT_XXX option to be set
		*
		*
		**/
		public function __set($key,$val){
			curl_setopt($this->ch, constant( (stripos($key,'curl') === false? 'CURLOPT_' : '' ) . strtoupper($key) ), $val);
		}





		/**
		*
		*	Get the cURL Request Info
		* 		Params:
		* 			- $key: 		(String) The cURL getinfo Option
		*
		*
		**/
		public function __get($key){
			return curl_getinfo($this->ch, constant( (stripos($key,'curl') === false? 'CURLINFO_' : '' ) . strtoupper($key) ) );
		}





		/**
		*
		*	Destroy the cURL Resource when destructing the Class
		*
		**/
		public function __destruct(){
			if(gettype($this->ch) == 'resource') curl_close($this->ch);
		}





		/**
		*
		*	Initialize cURL and Setup the Presets
		*
		**/
		public function __construct(){
			$this->ch = curl_init();

			//Set Preset Options
			foreach($this->presets as $key=>$val){
				$this->$key = $val;
			}

		}





		/**
		*
		*	Set the cURL Cookies
		* 		Params:
		* 			- $file: 	(String) The Full path to the File to create the cookies in
		*
		**/
		public function cookies($file){

			//Create Folder if it doesn't exist
			$path = pathinfo($file,PATHINFO_DIRNAME);
			if(!is_dir($path)) mkdir($path,0755,true);

			//Set Settings
			$this->cookiesession 	= false;
			$this->cookiefile 		= $file;
			$this->cookiejar		= $file;
		
		}





		/**
		*
		*	An Alternative Way to __set cURL Options
		* 		Params:
		* 			- $key: 		(String) The cURL Option
		* 			- $val: 		(String) The CURLOPT_XXX option to be set
		*
		*
		**/
		public function set($key,$val){
			$this->$key = $val;
		}





		/**
		*
		*	GET Request
		* 		Params:
		* 			- $url: 	(String) The URL to Request
		* 			- $data: 	(Array) The Data to send with the Request
		*
		**/
		public function get($url,$data=array()){
			return $this->request($url,$data,'GET'); 
		}






		/**
		*
		*	POST Request
		* 		Params:
		* 			- $url: 	(String) The URL to Request
		* 			- $data: 	(Array) The Data to send with the Request
		*
		**/
		public function post($url,$data=array()){ 
			return $this->request($url,$data,'POST'); 
		}
		




		
		/**
		*
		*	PUT Request
		* 		Params:
		* 			- $url: 	(String) The URL to Request
		* 			- $data: 	(Array) The Data to send with the Request
		*
		**/
		public function put($url,$data=array()){ 
			return $this->request($url,$data,'PUT'); 
		}
		




		/**
		*
		*	DELETE Request
		* 		Params:
		* 			- $url: 	(String) The URL to Request
		* 			- $data: 	(Array) The Data to send with the Request
		*
		**/
		public function delete($url,$data=array()){ 
			return $this->request($url,$data,'DELETE'); 
		}






		/**
		*
		*	Close the cURL Connection
		*
		**/
		public function close(){
			curl_close( $this->ch );
		}





		/**
		*
		*	Show the last Request Error
		* 		Params:
		* 			- $type: 	(String) Return a specific error type (error,errno or info)
		*
		**/
		public function error($type=false){

			$error = array(
				'error' 	=> curl_error($this->ch),
				'errno' 	=> curl_errno($this->ch),
				'info' 		=> curl_getinfo($this->ch)
			);

			return !$type? $error : $error[$type] ;
		}






		/**
		*
		*	Submit a Request
		* 		Params:
		* 			- $url: 		(String) The URL to Request
		* 			- $postData: 	(Array) The Data to send with the Request
		* 			- $type: 		(String) The Type of Request
		*
		**/
		private function request($url,$postData=array(),$type='GET'){
			$this->httpget 			= 1;
			$this->customrequest 	= $type;
			$this->url 				= $url;
			$this->postfields		= is_array($postData)? http_build_query( $postData , NULL , '&') : $postData ;
			$this->html 			= curl_exec($this->ch);
			$this->info 			= curl_getinfo($this->ch);
			
			//Return
			return $this->html;
		}











	}
 
?>