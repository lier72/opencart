<?php
class ControllerSeomaticAccount extends Controller {


	private $error = array();














	/**
	*
	*	Index
	* 		- The Account Index Controller
	*
	* 		- Params:
	* 			- $override 	- (String) Override the Template
	*
	**/
	public function index() {

		//Load the Language
		$data = $this->load->language('seomatic/account');

		// Includes
		$this->load->model('setting/setting');

		//Set the Title
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


		//General 
		$template = 'login';

		//Build the Links
		$data['action_profile'] 			= $this->url->link('seomatic/account/profile', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_account'] 			= $this->url->link('seomatic/account/create',	 		'token=' . $this->session->data['token'], 'SSL');
		$data['action_email'] 				= $this->url->link('seomatic/account/email', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_username'] 	 		= $this->url->link('seomatic/account/username', 		'token=' . $this->session->data['token'], 'SSL');
		$data['action_password'] 			= $this->url->link('seomatic/account/password', 		'token=' . $this->session->data['token'], 'SSL');
		$data['action_login'] 				= $this->url->link('seomatic/account/login', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_logout'] 				= $this->url->link('seomatic/account/logout', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_forgot_password'] 	= $this->url->link('seomatic/account/forgotpassword', 	'token=' . $this->session->data['token'], 'SSL');
		$data['action_account_create'] 		= $this->url->link('seomatic/account/create', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_account_select'] 		= $this->url->link('seomatic/account/select', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_account_name'] 		= $this->url->link('seomatic/account/name', 			'token=' . $this->session->data['token'], 'SSL');
		$data['action_resend_verification'] = $this->url->link('seomatic/account/resend', 			'token=' . $this->session->data['token'], 'SSL');


		// Values
		$data['value_email'] 						= $this->SEOMatic->email;
		$data['value_password'] 					= $this->SEOMatic->password;


		// If SEOMatic is Connected
		if( $this->SEOMatic->connected ){

			//Set Template
			$template 							= 'settings';

			//Create the Batch Command
			$Batch = $this->SEOMatic->batch()->add(array(

				//Get the User Data
				$this->SEOMatic->user->info => array()

			))->add(array(

				//Get all of the Accounts
				$this->SEOMatic->accounts->all => array('limit'=>25)

			))->add(array(

				//Get the Accepted Credit Cards
				$this->SEOMatic->creditcards->accepted => array()

			))->add(array(

				//Get the Users Credit Cards
				$this->SEOMatic->creditcards->get => array()

			))->add(array(

				//Get the Secured By Logo
				$this->SEOMatic->creditcards->security => array()

			))->add(array(

				//Get the Invoices
				$this->SEOMatic->invoices->get => array()

			));


			//Load the Account
			if( $this->SEOMatic->accountid ){

				//Save the Account ID
				$accountid 	= $this->SEOMatic->accountid;

				//Continue the Batch (Starting from #6 )
				$Batch = $Batch->add(array(

					//Load the Current Account
					$this->SEOMatic->accounts->get => array( 'accountid' => $accountid )

				))->add(array(

					//Load the Countries
					$this->SEOMatic->countries->get => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->domains->get => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->competitors->get => array()

				))->add(array(

					$this->SEOMatic->plans->all => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->keywords->all => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->keywords->available => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->subscriptions->get => array()

				))->add(array(

					$this->SEOMatic->account( $accountid )->notifications->all => array()

				))->send();


				$data['account'] 			= $Batch[6]['account'];
				$data['countries'] 			= $Batch[7]['countries'];
				$data['domains'] 			= $Batch[8]['domains'];
				$data['competitors'] 		= $Batch[9]['competitors'];
				$data['plans'] 				= $Batch[10]['plans'];
				$data['keywords'] 			= $Batch[11]['keywords'];
				$data['available'] 			= $Batch[12]['remaining'];
				$data['used'] 				= $Batch[12]['used'];
				$data['subscription'] 		= $Batch[13]['subscription'];
				$data['notifications'] 		= $Batch[14]['notifications'];

			}else{

				$Batch = $Batch->send();

			}


			$data['user'] 				= $Batch[0]['user'];
			$data['accounts'] 			= $Batch[1]['accounts'];
			$data['cards']				= $Batch[2]['creditcards'];
			$data['creditcards'] 		= $Batch[3]['creditcards'];
			$data['security'] 			= $Batch[4]['security'];
			$data['invoices'] 			= $Batch[5]['invoices'];

		}


		// Set Children
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');


		// Send Output
		$this->response->setOutput($this->load->view('seomatic/account/'.$template.'.tpl' , $data));

	}




























	/**
	*
	*	Create an Account
	* 		- Create an Account
	*
	* 		- Params:
	*			- Name: 	(String) The Account Name
	*
	**/
	public function create(){

		//Load the Language
		$data = $this->load->language('seomatic/account');

		//Load
		$this->load->model('setting/setting');
		$this->load->model('localisation/country');
		$this->load->model('setting/store');


		//Set the Title
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


		//Create Account
		if( !empty($this->request->post) && $this->validate() ){
		

			//Create the Account
			$account = $this->SEOMatic->account->create(array(
				'name' => $this->request->post['name']
			));


			//Ensure Account Created Successfully
			if( !$this->SEOMatic->getResult() ){


				//Error Occured
				$data['error_warning'] = $account['error'];


			}else{

				//Save Updated SEOMatic Configuration
				$this->model_setting_setting->editSettingValue( 'seomatic' , 'seomatic_account', $account['accountid'] );
				$this->model_setting_setting->editSettingValue( 'seomatic' , 'seomatic_country', $this->request->post['country'] );


				//Prepare Domains
				$domains = array();
				foreach($this->request->post['domains'] as $domain){
					$domains[] = array('domain' => $domain);
				}


				//Prepare the Request
				$Batch = $this->SEOMatic->batch()->add(array(

					//Add Domains to SEOMatic
					$this->SEOMatic->account( $account['accountid'] )->domains->add => $domains 

				));

				//Add Competitors if passed
				if(!empty($this->request->post['competitors'])){

					//Prepare Competitors
					$competitors = array();
					foreach($this->request->post['competitors'] as $competitor){
						$competitors[] = array(
							'name' 		=> str_replace('www.','',parse_url($competitor,PHP_URL_HOST)),
							'domain' 	=> $competitor
						);
					}

					//Add to the Batch
					$Batch->add(array(

						//Add Competitors to SEOMatic
						$this->SEOMatic->account( $account['accountid'] )->competitors->add => $competitors

					));

				}

				//Send the Data
				$Batch->send();

				//Set Success!
				$this->session->data['success'] = $this->language->get('success_account_create');

				//Redirect
				$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-account', 'SSL'));
			



			//End if( !$this->SEOMatic->getResult() )
			}


		//End if( !empty($this->request->post['name']) && $this->validate() ){
		}


		//Build the Links
		$data['action_cancel'] = $this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL');
		$data['action_create'] = $this->url->link('seomatic/account/create', 'token=' . $this->session->data['token'], 'SSL');


		//Get the Store Country
		$data['store_country'] 	= $this->model_localisation_country->getCountry( $this->config->get('config_country_id') );
		
		// Load Countries
		$data['countries'] 		= $this->SEOMatic->countries();
		$data['countries'] 		= $data['countries']['countries'];


		//If POST request made, build from Request
		if( !empty($this->request->post) ){



			//Preset Fields
			$this->request->post['competitors'] = !empty($this->request->post['competitors'])? $this->request->post['competitors'] : array() ;
			$this->request->post['countries'] 	= !empty($this->request->post['countries'])? $this->request->post['countries'] : array() ;
			$this->request->post['domains'] 	= !empty($this->request->post['domains'])? $this->request->post['domains'] : array() ;




			//Set the Account Name
			$data['name'] 		= $this->request->post['name'];


			//Set Domains
			$data['domains'] 	= $this->request->post['domains'];




			//Set Competitors
			$competitors = array();
			foreach($this->request->post['competitors'] as $competitor){
				$competitors[] = array(
					'name' 		=> str_replace('www.','',parse_url($competitor,PHP_URL_HOST)),
					'domain' 	=> $competitor
				);
			}

			$data['competitors'] = $competitors;



		}else{






			//Set the Account Name to Empty
			$data['name'] 	= '';


			//Get the Stores
			$stores 				= $this->model_setting_store->getStores();
			$data['domains'] 		= array(HTTP_CATALOG);




			//Add Each Domain
			foreach($stores as $store){
				$data['domains'][] = $store['url'];
			}


			//Set Competitors to Empty
			$data['competitors'] = array();








		//End if( !empty($this->request->post) ){
		}


		// Set Children
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');


		// Send Output
		$this->response->setOutput($this->load->view('seomatic/account/campaign.tpl', $data));

	}


















	




















	/**
	*
	*	Email
	* 		- Update the Users SEOMatic Email
	*
	*		- Params:
	*			- Email: 	(String) The Email to Update 
	*
	**/
	public function email(){

		//Load
		$this->load->model('setting/setting');

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		if( !empty($this->request->post['email']) ){

			//Set the Email
			$email = $this->request->post['email'];

			if( filter_var($email,FILTER_VALIDATE_EMAIL) ){

				//Update the Email
				$task = $this->SEOMatic->user->update(array(
					'email' => $email
				));

				//If it was Successful
				if( $task['result'] ){

					//Save the Email Locally
					$this->model_setting_setting->editSettingValue('seomatic', 'seomatic_email', $email );

				}

			}else{

				$task = array('result' => 0, 'error'=>'Invalid Email', 'code'=>'invalid-email');

			}

		}

		//Return Result
		$this->response->setOutput(json_encode($task));

	}





















	/**
	*
	*	Username
	* 		- Update the Users SEOMatic Username
	*
	*		- POST Params:
	*			- Username: 	(String) The Username to Update 
	*
	**/
	public function username(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		if( !empty($this->request->post['username']) ){

			//Update Username
			$task = $this->SEOMatic->user->update(array(
				'username' => $this->request->post['username']
			));

		}

		//Return Result
		$this->response->setOutput(json_encode($task));

	}





















	/**
	*
	*	Password
	* 		- Update the Users SEOMatic Password
	*
	*	- POST Params:
	*		- Password: 	(Array) Password Array (Password,Password Retyped)
	*
	**/
	public function password(){

		//Load
		$this->load->model('setting/setting');

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		if( !empty($this->request->post['password']) && count($this->request->post['password']) > 0 ){

			$Batch = $this->SEOMatic->batch()->add(array(

				$this->SEOMatic->user->update => array(
					'password' => $this->request->post['password'][0]
				)

			))->wait()->add(array(

				$this->SEOMatic->user->get => array()

			))->send();

			//Update Password
			$task = $Batch[0];
			$user = $Batch[1];

			if( $task['result'] ){

				//Success: Save the Password Locally
				$this->model_setting_setting->editSettingValue('seomatic', 'seomatic_password', $user['user']['password'] );

			}

		}

		//Return Result
		$this->response->setOutput(json_encode($task));

	}























	/**
	*
	*	Login
	* 		- Login to an SEOMatic Account
	*
	* 	POST Params:
	*		- Email: 		(String) The Email to Login as
	* 		- Password: 	(String) The Password
	*
	**/
	public function login(){

		if( !empty($this->request->post) ){

			//Load Data
			$this->load->model('setting/setting');
			$this->load->model('setting/store');
			$this->load->model('localisation/country');

			//Prepare Data
			$data = array(
				'email' 	=> $this->request->post['seomatic_email'],
				'password'	=> $this->request->post['seomatic_password']
			);

			
			if( $data['email'] && $data['password']){
				
				//Failed: Return Error
				if( ($authentication = $this->SEOMatic->login($data['email'],$data['password'])) && !$this->SEOMatic->getResult() ){
				
					$this->session->data['error'] = $authentication['error'];
				
				}else{		

					//Fallback incase the user had already been logged in
					if( isset( $authentication['code'] ) && $authentication['code'] == 'already-logged' ){
						
						//Get the User
						$user = $this->SEOMatic->user->info();

						//Get the Password
						$authentication['password'] = $user['user']['password'];

					}

					//Save Configuration
					$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_email' , $data['email'] );
					$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_password' , $authentication['password'] );

					//Success!
					$this->session->data['success'] = $this->language->get('success_login');

				}

			}else{

				//Invalid Authentication
				$this->session->data['error'] = $this->language->get('validate_auth_missing');

			}

		}

		//Redirect
		$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL'));

	}






















	/**
	*
	*	forgotpassword
	* 		- Forgot SEOMatic PAssword
	*
	* 	POST Params:
	*		- email: 		(String) The Email to Login as
	*
	**/
	public function forgotpassword(){

		if( !empty($this->request->post) ){

			//Get the Data
			$data = array(
				'email' 	=> $this->request->post['seomatic_email']
			);

			if( $data['email'] && filter_var($data['email'],FILTER_VALIDATE_EMAIL) ){

				//Failed: Return Error
				if( ($authentication = $this->SEOMatic->user->resetPassword( $data )) && !$this->SEOMatic->getResult() ){

					//Error
					$this->session->data['error'] = $authentication['error'];
				
				}else{		

					//Success!
					$this->session->data['success'] = sprintf( $this->language->get('success_forgot_password') , $data['email'] );

				}

			}else{

				//Invalid Authentication
				$this->session->data['error'] = $this->language->get('validate_password_missing');

			}

		}

		//Redirect
		$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-password', 'SSL'));

	}






















	/**
	*
	*	Logout
	* 		- Logout of the current SEOMatic Account
	*
	*
	* 	Params:
	*		n/a
	*
	**/
	public function logout(){

		// Load Setting Module
		$this->load->model('setting/setting');

		// Ensure SEOMatic is Logged In
		$this->SEOMatic->login( $this->config->get('seomatic_email') , $this->config->get('seomatic_password') );

		// Logout
		$this->SEOMatic->logout();

		// Delete SEOMatic Settings
		$this->model_setting_setting->editSettingValue( 'seomatic' , 'seomatic_email' , '' );
		$this->model_setting_setting->editSettingValue( 'seomatic' , 'seomatic_password' , '' );
		$this->model_setting_setting->editSettingValue( 'seomatic' , 'seomatic_account' , '' );

		//Redirect
		$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL'));

	}




















	/**
	*
	*	Select an Account
	* 		- Select an Account
	*
	* 	Params:
	*		n/a
	*
	**/
	public function select(){


		//Load
		$this->load->model('setting/setting');
		$this->load->model('setting/store');
		$this->load->model('localisation/country');


		
		//Get the Account
		$account = $this->SEOMatic->accounts->find(array(
			'accountid' => $this->request->post['seomatic_account']
		));


		//Check the Result
		if( $this->SEOMatic->getResult() && !empty($account['account']) ){

			//Get the Stores
			$stores 			= $this->model_setting_store->getStores();

			//Save Updated SEOMatic Configuration
			$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_account' , $account['account']['accountid'] );

			//Success!
			$this->session->data['success'] = $this->language->get('success_account_select');

		}else{

			//An Error Occured
			$this->session->data['error'] = $account['error'];

		}





		//Redirect
		$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'] . '#tab-account', 'SSL'));

	}


























	/**
	*
	*	Domain
	*		- Attempt to load a Domain Page, Obtain the Title
	* 		
	* 	POST Params:
	*		- domain: 	(String) The Fully Qualified Domain to Search
	*
	**/
	public function domain(){


		if( isset($this->request->post['domain']) && filter_var($this->request->post['domain'],FILTER_VALIDATE_URL) ){

			//Get the Page
			$page = $this->SEOMatic->cURL->get( $this->request->post['domain'] );
			$code = $this->SEOMatic->cURL->HTTP_CODE;

			//Load the Domain, Check the Result
			if( $code > 0 && $code[0] !== 4 ){

				//Return JSON
				$json = array(
					'result' 	=> 1,
					'name' 		=> str_replace('www.','',parse_url($this->request->post['domain'],PHP_URL_HOST)),
					'code' 		=> $code
				);

			}else{


				//Show the Response
				$json = array(
					'result' 	=> 0,
					'name' 		=> '',
					'code' 		=> $code,
					'error' 	=> $this->language->get('validate_domain_lookup')
				);

			}

		}else{

			//Invalid Domain
			$json = array(
				'result' 	=> 0,
				'name' 		=> '',
				'code' 		=> 0,
				'error' 	=> $this->language->get('validate_domain')
			);

		}

		//Return the Response
		$this->response->setOutput(json_encode($json));

	}


























	/**
	*
	*	Name
	*		- Save the Account Name
	* 		
	* 	POST Params:
	*		- name: 		(String) The SEOMatic Username
	*
	**/
	public function name(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		if( !empty($this->request->post['name']) ){

			//Update Username
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->account->update(array(
				'name' => $this->request->post['name']
			));

		}

		//Return Result
		$this->response->setOutput(json_encode($task));

	}


























	/**
	*
	*	country
	*		- Save the Default Country
	* 		
	* 	POST Params:
	*		- name: 		(String) The SEOMatic Country ID
	*
	**/
	public function country(){

		//load the Settings
		$this->load->model('setting/setting');

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		if( !empty($this->request->post['countryid']) ){		

			//Save Configuration
			$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_country' , $this->request->post['countryid'] );

			//Set Success
			$task = array('result' => 1);

		}

		//Return Result
		$this->response->setOutput(json_encode($task));

	}


























	/**
	*
	*	Save Domain
	*		- Save a single domain to SEOMatic
	* 		
	* 	POST Params:
	*		- domain: 		(String) The Domain to Save
	*
	**/
	public function addDomain(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		//Ensure the Domain was Passed
		if( !empty($this->request->post['domain']) ){

			//Add the Domain
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->domains->add(array(
				'domain' => $this->request->post['domain']
			));

		}

		//Set Response
		$this->response->setOutput(json_encode( $task ));

	}


























	/**
	*
	*	Delete Domain
	*		- Delete a single domain from SEOMatic
	* 		
	* 	POST Params:
	*		- domainid: 		(INT) The Domain ID to Delete
	*
	**/
	public function deleteDomain(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		//Ensure the Domain ID was Passed
		if( !empty($this->request->post['domainid']) ){

			//Add the Domain
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->domains->delete(array(
				'domainid' => $this->request->post['domainid']
			));

		}

		//Set Response
		$this->response->setOutput(json_encode( $task ));

	}


























	/**
	*
	*	Save Competitor
	*		- Save a single competitor to SEOMatic
	* 		
	* 	POST Params:
	*		- competitor: 		(INT) The Competitor to Save
	*
	**/
	public function addCompetitor(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		//Ensure the Competitor was Passed
		if( !empty($this->request->post['competitor']) ){

			//Get the Competitor
			$competitor = $this->request->post['competitor'];

			//Add the Competitor
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->competitors->add(array(
				'name' 		=> str_replace('www.','',parse_url( $competitor ,PHP_URL_HOST)),
				'domain' 	=> $competitor
			));

		}

		//Set Response
		$this->response->setOutput(json_encode( $task ));

	}


























	/**
	*
	*	Delete Competitor
	*		- Delete a single competitor from SEOMatic
	* 		
	* 	POST Params:
	*		- competitorid: 		(INT) The Competitor ID to Delete
	*
	**/
	public function deleteCompetitor(){

		//Preset Task
		$task = array('result' => 0, 'error'=>'Bad Request', 'code'=>'bad-request');

		//Ensure the Competitor was Passed
		if( !empty($this->request->post['competitorid']) ){

			//Add the Domain
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->competitors->delete(array(
				'competitorid' => (int)$this->request->post['competitorid']
			));

		}

		//Set Response
		$this->response->setOutput(json_encode( $task ));

	}


























	/**
	*
	*	report
	*		- Updates a Users Report Frequency
	* 		
	* 	POST Params:
	*		- notificationid: 		(INT) The Notification ID
	* 		- frequency: 			(INT) The Frequency to use
	*
	**/
	public function report(){

		//Send the Update
		$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->notifications->update(array(
			'notificationid' 	=> (int)$this->request->post['notificationid'],
			'frequency' 		=> (int)$this->request->post['frequency']
		));

		//Set Response
		$this->response->setOutput(json_encode($task));

	}


























	/**
	*
	*	Add a Credit Card
	*		- Add a Credit Card to the Account
	* 		
	* 	POST Params:
	*		- creditcard: 		(INT) The Credit Card Number to Add
	* 		- exp: 				(INT) The Expiry Month & Date Separated by " / "
	* 		- cvc 				(INT) The CVC Number
	*
	**/
	public function addCard(){

		//Preset Task
		$task = array( 'result' => 0, 'error' => 'Bad Request', 'code'=>'bad-request' );

		//Ensure the Data was Passed
		if( !empty($this->request->post['creditcard']) && !empty($this->request->post['exp']) && !empty($this->request->post['cvc']) ){

			//Preset Data
			$creditcard = preg_replace('/[^0-9]/','',$this->request->post['creditcard']);
			$expiry 	= $this->request->post['exp'];
			$expiry 	= strpos($expiry,' / ') > -1 ? explode(' / ',$expiry) : array(false,false) ;
			$cvc 		= preg_replace('/[^0-9]/','',$this->request->post['cvc']);

			//Add the Credit Card
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->creditcards->add(array(
				'number'	=> $creditcard,
				'expiry' 	=> array(
					'month' 	=> $expiry[0],
					'year' 		=> $expiry[1]
				),
				'cvc' 		=> $cvc,
				'name' 		=> $this->request->post['name']
			));


		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}


























	/**
	*
	*	Default Card
	*		- Set the Default Card to the Account
	* 		
	* 	POST Params:
	*		- cardid: 			(String) The Credit Card ID
	*
	**/
	public function defaultCard(){

		//Preset Task
		$task = array( 'result' => 0, 'error' => 'Bad Request', 'code'=>'bad-request' );

		//Ensure the Data was Passed
		if( !empty($this->request->post['cardid']) ){

			//Add the Credit Card
			$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->creditcards->update(array(
				'cardid' 	=> $this->request->post['cardid'],
				'default' 	=> true
			));

		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}


























	/**
	*
	*	Delete Card
	*		- Delete the Credit Card from the Account
	* 		
	* 	POST Params:
	*		- cardid: 			(String) The Credit Card ID
	*
	**/
	public function deleteCard(){

		//Preset Task
		$task = array( 'result' => 0, 'error' => 'Bad Request', 'code'=>'bad-request' );

		//Ensure the Data was Passed
		if( !empty($this->request->post['cardid']) ){

			//Add the Credit Card
			$task = $this->SEOMatic->account( $this->config->get('seomatic_account') )->creditcards->delete(array(
				'cardid' 	=> $this->request->post['cardid']
			));

		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}


























	/**
	*
	*	Subscribe
	*		- Subscribe to a Plan
	* 		
	* 	POST Params:
	*		- planid: 			(String) The Plan ID
	* 		- coupon: 			(String) The Coupon to Use
	*
	**/
	public function subscribe(){

		//Preset Task
		$task = array( 'result' => 0, 'error' => 'Bad Request', 'code'=>'bad-request' );

		//Ensure the Data was Passed
		if( !empty($this->request->post['planid']) ){

			//Load Current Account
			$account = $this->SEOMatic->accounts->get(array( 'accountid' => $this->SEOMatic->accountid ));

			if( !empty($account['account']['subscriptionid']) ){

				//Update the Subscription
				$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->subscriptions->update(array(
					'planid' 	=> $this->request->post['planid'],
					'coupon' 	=> $this->request->post['coupon']
				));

			}else{

				//Add the Subscription
				$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->subscriptions->add(array(
					'planid' 	=> $this->request->post['planid'],
					'coupon' 	=> $this->request->post['coupon']
				));

			}

			//Get the Subscription
			if( $task['result'] ){

				//Get the Subscription
				$task 										= $this->SEOMatic->account( $this->SEOMatic->accountid )->subscriptions->get();
				$task['subscription']['current_period_end'] = date('M d, Y', $task['subscription']['current_period_end']);

			}

		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}


























	/**
	*
	*	Unsubscribe
	*		- Unsubscribe from a Plan
	* 		
	* 	POST Params:
	*		- subscriptionid: 			(String) The Subscription ID
	*
	**/
	public function unsubscribe(){

		//Delete the Subscription
		$task = $this->SEOMatic->account( $this->SEOMatic->accountid )->subscriptions->delete();

		//Get the Subscription
		if( $task['result'] ){

			//Get the Subscription
			$task 										= $this->SEOMatic->account( $this->SEOMatic->accountid )->subscriptions->get();
			$task['subscription']['current_period_end'] = date('M d, Y', $task['subscription']['current_period_end']);

		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}


























	/**
	*
	*	Coupon
	*		- Get a Coupon
	* 		
	* 	POST Params:
	* 		- coupon: 			(String) The Coupon to Use
	*
	**/
	public function coupon(){

		//Preset Task
		$task = array( 'result' => 0, 'error' => 'Bad Request', 'code'=>'bad-request' );

		//Ensure the Data was Passed
		if( !empty($this->request->post['coupon']) ){

			//Add the Credit Card
			$task = $this->SEOMatic->account( $this->config->get('seomatic_account') )->coupons(array(
				'coupon' 	=> $this->request->post['coupon']
			));

		}


		//Set Response
		$this->response->setOutput(json_encode( $task ));


	}

















	/**
	*
	*	Validate
	* 		- Validate the Account Create
	*
	*	POST Params:
	* 		- name: 		(String) The Account Name 					* Optional *
	* 		- domains: 		(Array) An Array of Domains to Add
	*
	**/
	private function validate(){


		//Validate Domains
		if( empty($this->request->post['domains']) ){

			$data['error_warning'] = $this->language->get('validate_domains_required');
			return false;

		}


		//Validate Name
		if( isset($this->request->post['name']) ){

			//Ensure Account Name Exists
			if( empty($this->request->post['name']) ){

				$data['error_warning'] = $this->language->get('validate_account_name');
				return false;

			}else{


				//Find the Account
				$account = $this->SEOMatic->accounts->find(array(
					'name' => $this->request->post['name']
				));

				//Ensure Account Name Doesn't Exist
				if( $account['total'] > 0 ){

					$data['error_warning'] = $this->language->get('validate_account_exists');
					return false;

				}

			}

		}


		return true;

	}







}
?>