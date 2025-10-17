<?php
class ControllerSeomaticFunctions extends Controller {


	


	




















	/**
	*
	*	analyze
	* 		- Analyze the Page Content
	*
	*		- Params:
	*			- $_POST: 			(Object)
	* 				- keyword: 			(String) The Keyword to Analyze
	* 				- title: 			(String) The Page Title
	* 				- description: 		(String) The Meta Description
	* 				- content: 			(String) The Page Content
	* 				- url: 				(String) The Page URL
	*
	**/
	public function analyze(){

		//Load the Model
		$this->load->model('seomatic/analysis');


		//Ensure the Keyword was Passed
		if( !isset($this->request->post['keyword']) || $this->request->post['keyword'] === '' ){
		
			//Missing Required Data
			$analysis = array(
				'result' 	=> 0,
				'error' 	=> 'Keyword is a Required Field',
				'code' 		=> 'missing-data'
			);
		
		}else{

			//Undecode any HTML
			if(!empty( $this->request->post['content'] )){
				$this->request->post['content'] = htmlspecialchars_decode( $this->request->post['content'] );
			}

			//Run the Analysis
			$analysis = $this->model_seomatic_analysis->analyze(array_merge(array(
				'keyword' 		=> '',
				'title' 		=> '',
				'description' 	=> '',
				'content' 		=> '',
				'url' 			=> ''
			),$this->request->post));

		}


		//Return the Report
		return $this->response->setOutput(json_encode( $analysis ));

	}


	


	







	/**
	*
	*	api
	* 		- Call an SEOMatic API Function
	*
	*		- Params:
	*			- $_POST: 			(Object)
	* 				- class: 			(String) The SEOMatic API Class to Call
	* 				- function: 		(String) The SEOMatic Function Class to Call
	* 				- object: 			(Array) The Array to send through the API
	*
	**/
	public function api(){

		if( empty($this->request->post['class']) || empty($this->request->post['function']) ){

			//Return Error
			return $this->response->setOutput(json_encode(array(
				'result' 	=> 0,
				'error' 	=> 'Class (The SEOMatic SDK Class) and Function (The SEOMatic SDK Function) are Required',
				'code' 		=> 'missing-data'
			)));

		}else{

			//Prepare the Command
			$command 	= isset($this->request->post['object']) ? $this->request->post['object'] : array() ;

			//Run the Command
			$result 	= $this->SEOMatic->account( $this->SEOMatic->accountid )->{ $this->request->post['class'] }->{ $this->request->post['function'] }( $command );

			//Return the Output
			return $this->response->setOutput(json_encode( $result ));

		}
	}






















}
?>