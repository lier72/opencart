<?php
################################################################################################
#  Web Services Opencart 3.x.x.x From webkul http://webkul.com 		  	       #
################################################################################################
class ControllerExtensionModulewkwebservices extends Controller {

	private $error = array();

	public function install() {
		$this->load->model('extension/module/wk_webservices');
		$this->model_extension_module_wk_webservices->createTableJobWebServices();

		$this->load->model('user/user_group');
		$controllers = array(
			'catalog/wk_webservices_tab',
			'catalog/wk_odoo_address',
			'catalog/wk_odoo_carrier',
			'catalog/wk_odoo_category',
			'catalog/wk_odoo_config',
			'catalog/wk_odoo_currency',
			'catalog/wk_odoo_customer',
			'catalog/wk_odoo_order',
			'catalog/wk_odoo_order_status',
			'catalog/wk_odoo_payment',
			'catalog/wk_odoo_product',
			'catalog/wk_odoo_product_option',
			'catalog/wk_odoo_product_option_value',
			'catalog/wk_odoo_product_variant',
			'catalog/wk_odoo_tax',
			'catalog/wk_webservices_tab',
		);

		foreach ($controllers as $key => $controller) {
			$this->model_user_user_group->addPermission($this->user->getId(),'access',$controller);
			$this->model_user_user_group->addPermission($this->user->getId(),'modify',$controller);
		}
	}

	public function index() {
		//LOAD LANGUAGE
		$this->load->language('extension/module/wk_webservices');

		//SET TITLE
		$this->document->setTitle($this->language->get('heading_title'));


		//LOAD SETTINGS
		$this->load->model('setting/setting');

		//SAVE SETTINGS (on submission of form)
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_wk_webservices', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		//CONFIG
		$config_data = array(
			'module_wk_webservices_status',
		);

		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$data[$conf] = $this->request->post[$conf];
			} else {
				$data[$conf] = $this->config->get($conf);
			}
		}

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['user_token'] = $this->session->data['user_token'];

		$data['breadcrumbs'] = array();

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_home'),
				'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
    		'separator' => false
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('text_module'),
				'href'      => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
    		'separator' => ' :: '
 		);

 		$data['breadcrumbs'][] = array(
     		'text'      => $this->language->get('heading_title'),
				'href'      => $this->url->link('extension/module/wk_webservices', 'user_token=' . $this->session->data['user_token'], true),
    		'separator' => ' :: '
 		);

		$data['action'] = $this->url->link('extension/module/wk_webservices', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/wk_webservices', $data));

	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/wk_webservices')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->error) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * [image_Sync function to save the product image to store and also in database]
	 * @param  [integer]  $prdouct_id [product id of product]
	 * @param  boolean $image_path [product image path in base64 format]
	 * @return [boolean]              [true]
	 */
	public function image_Sync($prdouct_id, $image_path = null){
		if((isset($prdouct_id) && $prdouct_id) && isset($image_path)){
			$get_fileDetails = base64_decode($image_path);
			$getImageName = explode('/', $get_fileDetails);
			$checkExtention = explode('.', end($getImageName));
			// Allowed file extension types
			$allowed = array();
			$extension_allowed = preg_replace('~\r?\n~', "\n", $this->config->get('config_file_ext_allowed'));

			$filetypes = explode("\n", $extension_allowed);
			foreach ($filetypes as $filetype) {
				$allowed[] = trim($filetype);
			}

			if(is_array($checkExtention) && in_array(end($checkExtention), $allowed) && end($checkExtention) !== 'php'){
				file_put_contents(DIR_IMAGE.'catalog/'.end($getImageName), file_get_contents($get_fileDetails));
				$image_details = array('product_id' => $prdouct_id,'img_path' => 'catalog/'.end($getImageName));
				$this->load->model('extension/module/wk_webservices');
				$this->model_extension_module_wk_webservices->saveImageToProduct($image_details);
			}
		}
	}


}
?>
