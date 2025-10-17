<?php
class ModelSeomaticAccount extends Model {
	

	//The Users Account ID (INT)
	private $accountid;


	//The Users Domains (Object)
	private $domains;



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
	*	domains
	* 		- Get the Users Domains, Store Them for the Session
	* 		
	* 		Params:
	* 			n/a
	*
	**/
	public function domains(){
		
		//If the Domains don't exist - Get them
		if(empty($this->domains)){

			//Get && Save the Domains
			$this->domains = $this->SEOMatic->account( $this->accountid )->domains();

		}

		//Return the Domains
		return $this->domains['domains'];

	}












	/**
	*
	*	countries
	* 		- Get All of the Countries
	* 		
	* 		Params:
	* 			n/a
	*
	**/
	public function countries(){
		
		//If the Countries don't exist - Get them
		if(empty($this->countries)){

			//Get && Save the Domains
			$this->countries = $this->SEOMatic->countries();

		}

		//Return the Domains
		return $this->countries['countries'];

	}












	/**
	*
	*	domainid
	* 		- Get the Current Domain ID or Get the First Domain if NO Domain is Selected
	* 		
	* 		Params:
	* 			n/a
	*
	**/
	public function domainid(){

		//Check if the Domain ID is set
		if( isset($this->request->get['domainid']) ){

			//Return the Domain ID
			return $this->request->get['domainid'];

		}else{

			//Get the Domains
			$domains = $this->domains();

			//Return & Set the First Domain ID
			return ( $this->request->get['domainid'] = $domains[0]['domainid'] );

		}

	}





}
?>