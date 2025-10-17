<?php

	class SEOMatic_Lib_Batch extends SEOMatic_Lib_Engine {


		private $commands;



		/**
		*
		* 	Create a new Batch Instance
		* 		Params:
		* 			- $registry: 	(Object) The Registry Class
		*
		*
		**/
		public function __invoke(){

			$this->commands = array();

			return $this;

		}



		/**
		*
		* 	Add a Command to the Batch
		* 		Params:
		* 			- $command: 		(Object) The Batch Command
		*
		*
		**/
		public function add( $command ){

			//Commands to Send with the Request
			$params = array(

				//Wait until the Command has completed before continuing
				'wait' 	=> false

			);

			//Add the Command
			$this->commands[] = array_merge( $this->getCommand( $command ) , $params );

			//Return the Batch Object
			return $this;

		}





		/**
		*
		* 	Wait for a Command to finish before Continuing
		* 		Params:
		* 			$toggle: 		(Bool) Toggle the Wait (True: On, False: Off)
		*
		*
		**/
		public function wait( $toggle = true ){

			//Add the Pause
			$this->commands[ count( $this->commands ) - 1 ]['wait'] = $toggle;

			//Return the Batch Object
			return $this;

		}




		/**
		*
		* 	Add a Command to the Batch
		* 		Params:
		* 			- $obj: 		(Object) The Object to Use
		* 			- $data: 		(Array) The Data to send in the Batch Command
		*
		*
		**/
		public function send(){

			//Post all Batch Requests
			return $this->requestor->post( '/batch' , array( 'commands' => $this->commands ) );

		}





		/**
		*
		* 	Outputs the Batch Command
		* 		Params:
		* 			n/a
		*
		**/
		public function output(){

			//Output the Commands
			echo '<pre>';
				print_r( $this->commands );
			echo '</pre>';

			//Return
			return $this;

		}





		/**
		*
		* 	Gets the Command Information
		* 		Params:
		* 			- $obj: 		(Object) The Object to Use
		* 			- $data: 		(Array) The Data to send in the Batch Command
		*
		*
		**/
		private function getCommand( $command ){

			//Ensure the Array is an Associative
			if( (bool)count( array_filter( array_keys( $command ) , 'is_string' ) ) == false ){

				//Check if the Command passed is an Object
				if( is_object( $command[0] ) ){

					//Get the Class Name
					$class 		= get_class( $command[0] );

					//Get the Command
					$command[0] = strtolower( substr( $class , strrpos( $class , '_' ) + 1 ) ) . ':__invoke';
				
				}

				//Recreate the Array if it is not associative
				$command = array( $command[0] => array() );

			}

			//Get the Class
			$class 		= explode( ':' , key( $command ) );

			//Get the Request
			$request 	= $this->{ $class[0] }->requests[ $class[1] ];

			//Get the Account ID if Required
			if( strpos( $request['url'] , '%s' ) !== false ){

				//Add the Account ID
				$request['url'] = sprintf( $request['url'] , $this->accountid ) ;

			}

			//Get the Data
			$data = $command[ key( $command ) ];

			//Set the Command as a Sub-Batch if Required			
			if(array_keys($data) === range(0,count($data)-1) && is_array($data[0])){

				//Set the Data
				$data = array( 'batch' => $data );

			}

			//Specifically for Login, if we Batch the Login set up the cURL request
			if( $class[1] == 'login' ){

				//Setup the cURL cookies
				$this->libutils->cookies( $data['email'] );

			}

			//Return the Request
			return array_merge( $request , array( 'data' => $data ) );

		}






	}
 
?>