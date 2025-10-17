<?php
class ModelSeomaticAccount extends Model {
	

	//The Users Account ID (INT)
	private $accountid;


	//The Users Domains (Object)
	private $domains;


	//The Users Keywords (Object)
	private $keywords;



	//The Countries (Object)
	private $countries;










	/**
	*
	*	construct
	* 		- Sets the Current Users Account ID
	*
	*		Params:
	* 			n/a
	*
	**/
	public function __construct($registry){
		$this->registry 	= $registry;
		$this->accountid 	= $this->account();
	}












	/**
	*
	*	account
	* 		- Get the Users Domains, Store Them for the Session
	* 		
	* 		Params:
	* 			n/a
	*
	**/
	public function account(){
		return $this->config->get('seomatic_account');
	}















	/**
	*
	*	addKeyword
	* 		- Add A Keyword to All Countries
	* 		
	* 		Params:
	* 			-
	*
	**/
	public function addKeyword($params){
		
		//Get the Countries
		$countries 		= explode(',',$this->config->get('seomatic_countries'));

		//Prepare Data
		$insert 		= array();

		//Loop through the Countries
		foreach($countries as $country){

			//Add the Fields
			$insert[] = array_merge(array(
				'link' 	=> array(
					'id' 		=> null,
					'type' 		=> null
				),
				'countryid' 	=> $country,
				'keyword' 		=> null,
				'active' 		=> null
			),$params);

		}

		//Add the Keywords
		$this->SEOMatic->account( $this->account() )->keywords->add( $insert );

	}















	/**
	*
	*	editKeyword
	* 		- Add A Keyword to All Countries
	* 		
	* 		Params:
	* 			- $find 	(Object) The Keywords to Search For
	* 			- $replace 	(Object) The Replacements to Make
	*
	**/
	public function editKeyword($find,$replace){

		//Get the Keywords
		$keywords 		= $this->SEOMatic->account( $this->account() )->keywords->get( $find );

		//Prepare the Data
		$update 		= array();

		//Create the Replacements
		foreach($keywords as $keyword){

			$update[] = array_merge($replace,array(
				'keywordid' 	=> $keyword['keywordid']
			));

		}

		//Add the Keywords
		$this->SEOMatic->account( $this->account() )->keywords->update( $update );

	}















	/**
	*
	*	deleteKeyword
	* 		- Remove Keywords
	* 		
	* 		Params:
	* 			- $find 	(Object) The Keywords to Search For
	*
	**/
	public function deleteKeyword($find){

		//Get the Keywords
		$keywords 		= $this->SEOMatic->account( $this->account() )->keywords->get( $find );

		//Prepare the Data
		$delete 		= array();

		//Create the Replacements
		foreach($keywords as $keyword){

			$delete[] = array(
				'keywordid' => $keyword['keywordid']
			);

		}

		//Add the Keywords
		$this->SEOMatic->account( $this->account() )->keywords->delete( $delete );

	}





}
?>