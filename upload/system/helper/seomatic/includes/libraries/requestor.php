<?php

	final class SEOMatic_Lib_Requestor extends SEOMatic_Lib_Engine {


		//The SEOMatic URL
		private $URL = 'https://seomatic.co/api/';

		//The Last Request Made
		private $request = array(); 

		//Data to Pass in Every Request
		private $data = array();




		/**
		*
		*	Set a Request item through __set
		* 		- If the $key value is "request" the entire object is set
		* 		Params:
		* 			$key: 	(String) The Key Value to Set
		* 			$val: 	(Object) The Value to Set
		*
		**/
		public function __set($key,$val){
			if( $key == 'request' ){
				$this->request = $val;
			}else{
				$this->request[$key] = $val;
			}
		}




		/**
		*
		*	Get the Last Request Error
		*		Params:
		*
		**/
		public function error(){
			if( isset($this->request['error']) ){
				return $this->request['error'];
			}else{
				return $this->libutils->error('SEOMatic: No Request Errors Available: (Library->Utilities->error)');
			}
		}





		/**
		*
		*	Get the Last Request Error Code
		*		Params:
		*
		**/
		public function code(){
			if( isset($this->request['code']) ){
				return $this->request['code'];
			}else{
				return $this->libutils->error('SEOMatic: No Request Errors Available: (Library->Utilities->code)');
			}
		}





		/**
		*
		*	Get the Last Request Result
		*		Params:
		*
		**/
		public function result(){ 
			if( isset($this->request['result']) ){
				return $this->request['result'];
			}else{
				return $this->libutils->error('SEOMatic: No Requests Available: (Library->Utilities->result)');
			}
		}





		/**
		*
		*	Get the Last Request Path
		*		Params:
		*
		**/
		public function path(){ 
			if( isset($this->request['path']) ){
				return $this->request['path'];
			}else{
				return $this->libutils->error('SEOMatic: No Requests Available: (Library->Utilities->path)');
			}
		}



		/**
		*
		*	Get the Last Request Method
		*		Params:
		*
		**/
		public function method(){ 
			if( isset($this->request['method']) ){
				return $this->request['method'];
			}else{
				return $this->libutils->error('SEOMatic: No Requests Available: (Library->Utilities->method)');
			}
		}



		/**
		*
		*	GET, PUT, POST & DELETE Requests
		*		Params:
		* 			- $path: 	(String) The Path to send the request to
		*			- $data: 	(Array) An Array of the Request Fields
		*
		**/
		public function get($path,$data=array()){ return $this->send( $path, $data, 'GET' ); }
		public function put($path,$data=array()){ return $this->send( $path, $data, 'PUT' ); }
		public function post($path,$data=array()){ return $this->send( $path, $data, 'POST' ); }
		public function delete($path,$data=array()){ return $this->send( $path, $data, 'DELETE' ); }



		/**
		*
		*	Adds Data in Each Request
		*		Params:
		* 			- $data: 		(Array) The Data to use on Each Request
		*
		**/
		public function append($data){
			$this->data = $data;
		}





		/**
		*
		* 	Overrides the URL to Request
		* 		Params:
		* 			- $url: 		(String) the URL to use
		*
		**/
		public function URL($url){
			$this->URL = $url;
		}






		/**
		*
		*	Send a Standard Requests
		*		Params:
		* 			- $path: 	(String) The Path to send the request to
		*			- $data: 	(Array) An Array of the Request Fields
		* 			- $type: 	(String) The Request Type
		*
		**/
		private function send($path,$data,$method){

			//Batch the Request
			if(array_keys($data) === range(0,count($data)-1) && is_array($data[0])){
				$data = array('batch'=>$data);
			}

			//Add the Cookies
			if( isset( $this->session->cookies ) ){
				$this->cURL->cookie = urldecode( http_build_query( $this->session->cookies , null , '; ' ) );
			}

			//Combine the Extra Data
			$data = array_merge( $this->data, $data );

			//Start Benchmark
			$this->libutils->benchmark($method.' '.$path,true);

			//Send the Request
			$request = $this->cURL->{$method}( rtrim( $this->URL , '/' ) . $path , $data );

			if( $request && ($json = json_decode($request,true)) && ( !function_exists('json_last_error') || (json_last_error() == JSON_ERROR_NONE ) ) ){

				//Request Success!		
				$this->request = $request = $json;
		
			}else
			if( $request && $this->cURL->info['http_code'] == 503 ){

				//Server Down.
				$this->request = $request = array(
					'result' 	=> 0,
					'error' 	=> 'Service Currently Unavailable',
					'code' 		=> 'service-unavailable'
				);

			}else{

				//Request Failed.
				$this->request = $request = array(
					'result' 	=> 0,
					'error' 	=> !$request? $this->cURL->error('error') : $request,
					'code' 		=> 'request-error'
				);

			}

			//Add Path to Request
			$this->request['path'] 		= $path;
			$this->request['method'] 	= $method;

			//End Benchmark
			$this->libutils->benchmark($method.' '.$path,false);

			//Output Request
			$this->libutils->output($method.' '.$path,$request);

			//Reset Account ID
			$this->accountid = false;

			//Return Request
			return $request;

		}









	}
 
?>