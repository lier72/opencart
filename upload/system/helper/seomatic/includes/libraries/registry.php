<?php

	final class SEOMatic_Lib_Registry {

		//The Registry Data
		private $data = array();


		/**
		*
		*	Register Get Variable
		* 		Params
		*			- $key: 	(String) The Variable KEY to find
		*
		*/
		public function get($key){
			return (isset($this->data[$key]) ? $this->data[$key] : NULL);
		}



		/**
		*
		*	Register Set Variable
		* 		Params
		*			- $key: 	(String) The Variable KEY to set
		* 			- $val: 	(Object) The Variable to SET
		*
		*/
		public function set($key, $val){
			$this->data[$key] = $val;
		}



	}

?>