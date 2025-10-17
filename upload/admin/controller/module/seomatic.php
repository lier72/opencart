<?php
class ControllerModuleSeomatic extends Controller {
	private $error = array();






	/**
	*
	*	index
	* 		- The Extension Settings Page
	*
	* 	Params:
	* 		n/a
	*
	**/
	public function index(){

		/**
		*
		*	Begin Standard Module
		*
		**/

		$this->language->load('module/seomatic');

		$this->document->setTitle($this->language->get('heading_title_inside'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_module'),
			'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('module/seomatic', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['cancel'] 			= $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');
		$data['error_warning'] 		= isset( $this->session->data['error_warning'] ) ? $this->session->data['error_warning'] : '' ;
		$data['heading_title'] 		= $this->language->get('heading_title_inside');
		$data['button_cancel'] 		= $this->language->get('button_cancel');
		$data['button_save'] 		= $this->language->get('button_save');
		$data['text_installed'] 	= $this->language->get('text_installed');
		$data['text_edit'] 			= $this->language->get('text_edit');
		$data['can_validate'] 		= $this->validate();

		$data['header'] 			= $this->load->controller('common/header');
		$data['column_left'] 		= $this->load->controller('common/column_left');
		$data['footer'] 			= $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('module/seomatic.tpl', $data));

	}






	/**
	*
	*	validate
	* 		- Validation when Updating the Plugin
	*
	* 	Params:
	* 		n/a
	*
	**/
	protected function validate(){
		return $this->user->hasPermission('modify', 'module/seomatic');
	}





	/**
	*
	*	install
	* 		- Runs when the Extension is Installed
	*
	* 	Params:
	* 		n/a
	*
	**/
	public function install(){

		//Load SEOMatic if it hasn't already been loaded
		if( !$this->SEOMatic ){

			// Load Required
			$this->registry->get('load')->helper('seomatic/index');

			// Setup SEOMatic
			$this->registry->set('SEOMatic', new SEOMatic);

		}

		//Ensure the Cache Folder is Writeable
		$writeable = $this->SEOMatic->ABS_PATH . '/includes/cache/' ;

		//Validate
		if( is_writeable( $writeable ) && class_exists('VQMod') && isset(VQMod::$_vqversion) && VQMod::$_vqversion >= '2.4.0' ){

			$this->load->model('setting/setting');
			$this->load->model('user/user_group');

			//Merge any Existing Settings to allow Upgrades to run smoothly
			$settings = $this->model_setting_setting->getSetting('seomatic');

			$this->model_setting_setting->editSetting('seomatic',array_merge(array(
				'seomatic_installed' 	=> 1,
				'seomatic_installing' 	=> 0,
				'seomatic_dashboard' 	=> 1,
				'seomatic_email' 		=> '',
				'seomatic_password' 	=> '',
				'seomatic_account' 		=> '',
				'seomatic_country' 		=> '',
				'seomatic_autoupdate' 	=> 1,
				'seomatic_referral' 	=> 1
			),$settings));

			//Prepare the Pages
			$pages = array(
				'module/seomatic',
				'seomatic/account',
				'seomatic/abstract',
				'seomatic/compatability',
				'seomatic/front',
				'seomatic/functions',
				'seomatic/keywords',
				'seomatic/register'
			);

			//Add Permissions
			foreach( $pages as $page ){

				$this->model_user_user_group->addPermission($this->user->getId(), 'access', $page);
			    $this->model_user_user_group->addPermission($this->user->getId(), 'modify', $page);

			}

		}else{

			//Show the Error
			if( !class_exists('VQMod') ){

				//VQMod Not Installed - This won't work on 2.x
				//$this->session->data['error'] = '<strong>VQMod Not Found</strong> - SEOMatic Requires VQMod: <a href="https://github.com/vqmod/vqmod">Download VQMod</a>';

			}else
			if( !is_writeable( $writeable ) ){

				//Cache is Not Writeable
				$this->session->data['error'] = '<strong>SEOMatic Cache is not Writeable:</strong> ' . $writeable ;

			}else{

				//Invalid VQMod Version
				$this->session->data['error'] = '<strong>Incompatible VQMod Version</strong> - SEOMatic Requires VQMod Version: 2.4.0 or greater: <a href="https://github.com/vqmod/vqmod">Download the Latest VQMod</a>';

			}

			//Uninstall the Module
			$this->response->redirect($this->url->link('extension/module/uninstall', 'token=' . $this->session->data['token'] . '&extension=seomatic', 'SSL'));

		}
	}








	/**
	*
	*	uninstall
	* 		- Runs when the Extension is Uninstalled
	*
	* 	Params:
	* 		n/a
	*
	**/
	public function uninstall(){

		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('seomatic');

	}

















	/**
	*
	*	autoupdate
	* 		- Toggles the AutoUpdate Function
	*
	* 	Params:
	* 		$_POST['data']: 		(Bool) The AutoUpdate Toggled Status (1: Enabled, 0: Disabled)
	*
	**/
	public function autoupdate(){

		if( $this->validate() ){

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSettingValue('seomatic','seomatic_autoupdate', !(int)$this->request->post['data'] );

			echo (int)!$this->request->post['data'];

		}

	}

















	/**
	*
	*	dashboard
	* 		- Toggles the Dashboard Function
	*
	* 	Params:
	* 		$_POST['data']: 		(Bool) The Dashboard Toggled Status (1: Enabled, 0: Disabled)
	*
	**/
	public function dashboard(){

		if( $this->validate() ){

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSettingValue('seomatic','seomatic_dashboard', !(int)$this->request->post['data'] );

			echo (int)!$this->request->post['data'];

		}

	}




}
?>