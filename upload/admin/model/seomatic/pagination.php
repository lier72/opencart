<?php
class ModelSeomaticPagination extends Model {
	

	private $page;
	private $limit;
	private $size;
	private $prev;
	private $next;


	/**
	*
	*	__construct
	* 		- Sets up the Primary Variables
	* 		
	* 		Params:
	* 			n/a
	*
	**/
	public function __construct($registry){

		$this->registry 	= $registry;
		$this->page 		= isset($this->request->get['page'])? $this->request->get['page'] : 0 ;
		$this->limit 		= $this->getLimit();
		$this->size 		= 0;

	}




	/**
	*
	*	getLimit
	* 		- The Page Limit of the Current Version of OC
	* 		
	* 		Params:
	* 			- $limit: 		(INT) The Total Pagination Limit
	*
	**/
	public function getLimit(){
		$version = explode('.',VERSION);
		return $this->registry->get('config')->get( $version[0] == 2 ? 'config_limit_admin' : 'config_admin_limit' );
	}




	/**
	*
	*	total
	* 		- The Total Results in the Pagination
	* 		
	* 		Params:
	* 			- $total: 		(INT) The Total Results in the Pagination
	*
	**/
	public function total($total){
		$this->total = (int)$total;
	}




	/**
	*
	*	prev
	* 		- The Previous ID used in SEOMatic Pagination
	* 		
	* 		Params:
	* 			- $arr: 		(Object) The Object to Paginate
	* 			- $key: 		(String) The Object Key to Compare on
	* 			- $url: 		(String) Additional URL Parameters to add to the string
	*
	**/
	public function previous($arr, $key, $url=''){
		if( count($arr) > 0 ){			
			$id 		= $arr[0][ $key ];
			$this->prev	= $this->url->link( $this->request->get['route'] , 'token=' . $this->session->data['token'] . $url . '&page=' . ($this->page-1) . '&after=' . $id);
		}
	}




	/**
	*
	*	next
	* 		- The Next Page used in SEOMatic Pagination
	* 		
	* 		Params:
	* 			- $id: 		(String) The Element ID to return results After
	* 			- $url: 	(String) Additional URL Parameters to add to the string
	*
	**/
	public function next($arr, $key, $url=''){
		if( count($arr) > 0 && count($arr) >= $this->config->get('config_admin_limit') ){
			$id 		= $arr[ count($arr)-1 ][ $key ];
			$this->next	= $this->url->link( $this->request->get['route'] , 'token=' . $this->session->data['token'] . $url . '&page=' . ($this->page+1) . '&before=' . $id);
		}
	}



	/**
	*
	* 	render
	* 		- Render the Pagination
	*
	* 		Params:
	* 			- n/a
	*
	**/
	public function render(){

		ob_start();
		
			include(DIR_TEMPLATE . 'seomatic/pagination.tpl'); 
			
			$pagination = ob_get_contents();

		ob_end_clean();

		return $pagination;

	}




	/**
	*
	*	query
	* 		- Adds the Query Fields to the SEOMatic Query
	*
	*	Params:
	* 		- n/a
	*
	*
	**/
	public function query($obj=array()){

		//Add the After Page
		if( isset($this->request->get['after']) ) $obj['after'] = $this->request->get['after'];
		
		//Add the Before Page
		if( isset($this->request->get['before']) ) $obj['before'] = $this->request->get['before'];
		
		//Return the Object
		return $obj;

	}



}
?>