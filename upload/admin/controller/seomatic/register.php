<?php
class ControllerSeomaticRegister extends Controller {


	private $error 			= array();
	private $password 		= null;






	/**
	*
	*	Index
	* 		- The Account Index Controller
	* 		- Params:
	* 			- n/a
	*
	**/
	public function index() {

		// Includes
		$data = $this->language->load('seomatic/register'); 

		// Includes
		$this->load->model('seomatic/account');
		$this->load->model('setting/setting');

		// Validate
		if( !empty($this->request->post) && $this->validate() ){

			// Save Data
			$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_email' , $this->request->post['seomatic_email'] );
			$this->model_setting_setting->editSettingValue( 'seomatic', 'seomatic_password' , $this->password );

			// Show Success
			$this->session->data['success'] = 'Success!';

			// Redirect
			$this->response->redirect($this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL'));
		
		}

		// Title
		$this->document->setTitle($this->language->get('heading_title'));

		// Heading
		$data['heading_title'] 			= $this->language->get('heading_title');

		// Form
		$data['action'] 	 			= $this->url->link('seomatic/register', 'token=' . $this->session->data['token'], 'SSL');

		// Buttons
		$data['button_register'] 		= $this->language->get('button_register');

		// Form
		$data['entry_email'] 			= $this->language->get('entry_email');
		$data['entry_password'] 		= array( $this->language->get('entry_password1'), $this->language->get('entry_password2') );
		$data['entry_username'] 		= $this->language->get('entry_username');

		//Values
		$data['value_email'] 			= !empty($this->request->post['seomatic_email'])? $this->request->post['seomatic_email'] : '' ;
		$data['value_username'] 		= !empty($this->request->post['seomatic_username'])? $this->request->post['seomatic_username'] : '' ;



		// Error
		if( $this->error ){
			$data['error_warning'] = $this->error['warning'];
		}else{
			$data['error_warning'] = '';
		}



		// Success
		if( isset($this->session->data['success']) ){
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		}else{
			$data['success'] = '';
		}



		// Breadcrumbs
		$data['breadcrumbs'] = array(
			array(
				'text'      => $this->language->get('text_home'),
				'href'      => $this->url->link('seomatic/account', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => false
			),
			array(
				'text'      => $this->language->get('text_current'),
				'href'      => $this->url->link('seomatic/register', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => ' :: '
			)
		);


		// Set Children
		$data['header'] 		= $this->load->controller('common/header');
		$data['column_left'] 	= $this->load->controller('common/column_left');
		$data['footer'] 		= $this->load->controller('common/footer');


		// Send Output
		$this->response->setOutput($this->load->view('seomatic/register.tpl' , $data));

	}











	/**
	*
	*	Validate
	* 		- Validate the Submitted Configuration
	*		- Params:
	*			- Null
	*
	**/
	private function validate(){

		//Prepare Data
		$data = array(
			'email' 	=> $this->request->post['seomatic_email'],
			'password'	=> array(
				$this->request->post['seomatic_password'][0],
				$this->request->post['seomatic_password'][1]
			),
			'username' 	=> $this->request->post['seomatic_username']
		);


		// Validate Password
		if( empty($data['password'][0]) || empty($data['password'][1]) ){

			//Password Error
			$this->error['warning'] = $this->language->get('validate_password');

			//Return Error
			return false;

		}else
		if( $data['password'][0] != $data['password'][1] ){

			//Passwords Didnt Match
			$this->error['warning'] = $this->language->get('validate_password_match');

			//Return
			return false;

		}


		// Validate Email
		if( !$data['email'] || !filter_var($data['email'],FILTER_VALIDATE_EMAIL) ){

			//Invalid Email
			$this->error['warning'] = $this->language->get('validate_email');

			//Return Error
			return false;

		}


		//Validate Credentials
		if( $data['email'] && $data['password']){

			//Create the Account
			$Register = $this->SEOMatic->user->register(array(
				'email' 	=> $data['email'],
				'password' 	=> $data['password'][0],
				'username' 	=> $data['username']
			));


			//Ensure no Errors were returned
			if( !$this->SEOMatic->getResult() ){

				//An Error Occured
				$this->error['warning'] = $Register['error'];

				//Return Error
				return false;

			}else{

				//Get the Encrypted Password returned from SEOMatic
				$this->password = $Register['password'];

			}

		}

		//Return Success!
		return true;

	}









}
?>