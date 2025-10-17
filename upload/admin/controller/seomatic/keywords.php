<?php
class ControllerSeomaticKeywords extends Controller {


	private $error = array();














	/**
	*
	*	Index
	* 		- The Keyword Index Controller
	*
	* 		- Params:
	* 			n/a
	*
	**/
	public function index() {

		//Load the Language
		$data = $this->load->language('seomatic/account');

		// Includes
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('catalog/information');

		// Title
		$this->document->setTitle($this->language->get('heading_title'));

		//Set the Token
		$data['token'] = $this->session->data['token'];

		//Error Messages
		if( isset($this->session->data['error']) ){
		
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		
		}else{
		
			$data['error_warning'] = '';
		
		}

		//Success Messages
		if( isset($this->session->data['success']) ){
		
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		
		}else{
			
			$data['success'] = '';
		
		}


		//Set the Breadcrumbs
		$routes = explode('/', $this->request->get['route']);
		foreach( $routes as $i => $type ){
			if($i == 0){

				//Add the Breadcrumb
				$data['breadcrumbs'][] = array(
					'text'      => 'SEOMatic',
					'href'      => $this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL'),
					'separator' => false					
				);

			}else{

				//Add the Breadcrumb
				$data['breadcrumbs'][] = array(
					'text' 		=> ucwords($type),
					'href' 		=> $this->url->link( implode('/', array_splice($routes,0,$i+1) ) , 'token='. $this->session->data['token'], 'SSL'),
					'separator' => ' :: '
				);

			}
		}

		// Title
		$this->document->setTitle($this->language->get('heading_title'));

		//General 
		$accountid 							= $this->SEOMatic->accountid;
		$data['token'] 						= $this->session->data['token'];
		$data['keywords'] 					= array();
		$data['requestvars'] 				= '';

		//Headings
		$data['heading_title'] 				= $this->language->get('heading_title');
		$data['heading_account_create'] 	= $this->language->get('heading_account_create');


		//Links
		$data['create_keyword'] 	= $this->url->link('seomatic/keywords/create', 'token=' . $this->session->data['token'], 'SSL');

		// If SEOMatic is Connected
		if( $this->SEOMatic->connected ){

			$Batch = $this->SEOMatic->batch()->add(array(

				//Get the Countries
				$this->SEOMatic->countries->get => array()

			))->add(array(

				//Get the Domains
				$this->SEOMatic->domains->get => array()

			))->add(array(

				//Get the Keywords
				$this->SEOMatic->keywords->get => $this->model_seomatic_pagination->query(array(
					'limit' => $this->model_seomatic_pagination->getLimit()
				))

			))->send();

			$data['countries'] 	= $Batch[0]['countries'];
			$data['domains'] 		= $Batch[1]['domains'];
			$data['keywords'] 	= $Batch[2]['keywords'];
			$total 						= $Batch[2]['total'];
			$keywords 					= $data['keywords'];

			//Loop through the Keywords
			foreach($data['keywords'] as $id=>$keyword){


				//Get the Linked ID Name
				switch($keyword['linktype']){

					case 'categoryid':						
						$category 								= $this->model_catalog_category->getCategoryDescriptions( $keyword['linkid'] );
						$data['keywords'][ $id ]['page'] 		= $category? $category[ key($category) ]['name'] : '' ;
						break;

					case 'productid':
						$product 								= $this->model_catalog_product->getProduct( $keyword['linkid'] );
						$data['keywords'][ $id ]['page'] 		= $product? $product['name'] : '' ;
						break;

					case 'informationid':
						$information 							= $this->model_catalog_information->getInformationDescriptions( $keyword['linkid'] );
						$data['keywords'][ $id ]['page'] 		= $information? $information[ key($information) ]['title'] : '' ;
						break;


				}

			}


			if( $total > $this->config->get('config_admin_limit') ){

				$this->model_seomatic_pagination->total( $total );
				$this->model_seomatic_pagination->previous( $keywords ,'keywordid' );
				$this->model_seomatic_pagination->next( $keywords , 'keywordid' );

			}

		}

		//Setup Pagination
		$data['pagination'] 	= $this->model_seomatic_pagination->render();


		// Set Children
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');


		// Send Output
		$this->response->setOutput($this->load->view( 'seomatic/keywords.tpl' , $data ));
		
	}

























	


































	/**
	*
	*	create
	* 		- Create a Single Keyword
	*
	*		- Params:
	*			- linkid: 			(INT) The Associated Link ID
	* 			- linktype: 		(String) The Associated Link Type (productid,categoryid,informationid)
	* 			- countryid: 		(INT) The Country ID to Link the Keyword to
	* 			- keyword: 			(String) The Keyword
	* 			- status: 			(INT) The Keyword Status
	*
	**/
	public function create(){

		//Load
		$this->load->model('setting/setting');

		//Preset Data
		$query 		= array();
		$accountid 	= $this->SEOMatic->accountid;

		//Send the Query
		$task = $this->SEOMatic->account( $accountid )->keywords->add(array(
			'link' 		=> array(
				'id' 		=> $this->request->post['linkid'],
				'type' 		=> $this->request->post['linktype']
			),
			'countryid'	=> $this->request->post['countryid'],
			'keyword' 	=> $this->request->post['keyword'],
			'status' 	=> $this->request->post['status']
		));

		//Return Result
		$this->response->setOutput(json_encode($task));

	}





















	/**
	*
	*	status
	* 		- Update the Status of the Keyword
	*
	*		- Params:
	*			- Status: 			(INT) The Status to Set it to (1: Active, 0: Inactive)
	* 			- Keywordids: 		(Array) An Array of Keyword IDs to Update
	*
	**/
	public function status(){

		//Load
		$this->load->model('setting/setting');

		//Preset Data
		$query 		= array();
		$accountid 	= $this->SEOMatic->accountid;

		//Build the Query
		foreach($this->request->post['keywordids'] as $keywordid){
			$query[] = array(
				'keywordid' => $keywordid,
				'status' 	=> $this->request->post['status']
			);
		}

		//Send the Query
		$task = $this->SEOMatic->account( $accountid )->keywords->update($query);

		//Return Result
		$this->response->setOutput(json_encode($task));

	}








	




















	/**
	*
	*	remove
	* 		- Remove a List of Keywords
	*
	*		- Params:
	* 			- Keywordids: 		(Array) An Array of Keyword IDs to Update
	*
	**/
	public function remove(){

		//Load
		$this->load->model('setting/setting');

		//Preset Data
		$query 		= array();
		$accountid 	= $this->SEOMatic->accountid;

		//Build the Query
		foreach($this->request->post['keywordids'] as $keywordid){
			$query[] = array(
				'keywordid' => $keywordid
			);
		}

		//Send the Query
		$task = $this->SEOMatic->account( $accountid )->keywords->delete( ( count($query) > 0 ? $query : $query[0] ) );

		//Return Result
		$this->response->setOutput(json_encode($task));

	}








	




















	/**
	*
	*	update
	* 		- Update a Single Keyword
	*
	*		- Params:
	*			- keyword: 			(String) The Status to Set it to (1: Active, 0: Inactive)
	* 			- Keywordid: 		(INT) An Array of Keyword IDs to Update
	*
	**/
	public function update(){

		//Load
		$this->load->model('setting/setting');

		//Preset Data
		$query 		= array();
		$accountid 	= $this->SEOMatic->accountid;

		//Send the Query
		$task = $this->SEOMatic->account( $accountid )->keywords->update(array(
			'keywordid' => $this->request->post['keywordid'],
			'link' 		=> array(
				'id' 		=> $this->request->post['linkid'],
				'type' 		=> $this->request->post['linktype']
			),
			'countryid' => $this->request->post['countryid'],
			'keyword' 	=> $this->request->post['keyword'],
			'status' 	=> $this->request->post['status']
		));

		//Return Result
		$this->response->setOutput(json_encode($task));

	}








	




















	/**
	*
	*	autocomplete
	* 		- Autocomplete function for the Create Keyword Pages
	*
	*		- Params:
	*			- filter_name: 			(String) The Filter to Search For
	*
	**/
	public function autocomplete(){

		//Load
		$this->load->model('catalog/information');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');

		//Get the Type
		switch($this->request->get['type']){
			case 'information':
				$data 	= array(
					'class' 	=> 'information',
					'function' 	=> 'Informations',
					'name' 		=> 'title'
				);
				break;

			case 'category':
				$data 	= array(
					'class' 	=> 'category',
					'function' 	=> 'Categories',
					'name' 		=> 'name'
				);
				break;

			case 'product':
				$data 	= array(
					'class' 	=> 'product',
					'function' 	=> 'Products',
					'name' 		=> 'name'
				);
				break;

			default:
				return false;

		}


		//Load the Information Pages
		$infos 	= $this->{'model_catalog_'.$data['class']}->{'get'.$data['function']}(array(
			'filter_name' => $this->request->get['filter_name']
		));

		//Prepare the Result
		$result = array();

		//Get the Results
		foreach($infos as $info){
			$result[] = array(
				'name' 	=> strip_tags(html_entity_decode( $info[ $data['name'] ], ENT_QUOTES, 'UTF-8')),
				'value' => $info[ $data['class'].'_id' ]
			);
		}

		//Return Result
		$this->response->setOutput(json_encode($result));

	}







	




















	/**
	*
	*	validate
	* 		- Validate Keyword Create Form
	*
	*		- Params:
	*			- keyword: 			(String) The Keyword
	* 			- status: 			(INT) Is the Keyword Active or Not
	*
	**/
	private function validate(){

		//Validate the Keyword
		if( !isset($this->request->post['keyword']) || $this->request->post['keyword'] === '' ){
			return false;
		}

		//Validate the Status
		if( !isset($this->request->post['status']) ){
			return false;
		}


		//Return
		return array_merge(array(
			'linkid' 	=> null,
			'linktype' 	=> null,
			'keyword' 	=> '',
			'status' 	=> false
		),$this->request->post);

	}























}
?>