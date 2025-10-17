<?php
$ADMIN_PATH = DIR_SYSTEM . '../admin/';
require_once ($ADMIN_PATH . 'model/catalog/wk_webservices_tab.php');

require_once ($ADMIN_PATH . 'model/customer/customer.php');

require_once ($ADMIN_PATH . 'model/catalog/category.php');

require_once ($ADMIN_PATH . 'model/catalog/product.php');

require_once ($ADMIN_PATH . 'model/sale/order.php');

class ControllerApiOob extends Controller {
  public function login(){
    $message = '';

    $status = False;

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }

    if(isset($params) && isset($params['api_key']) && trim($params['api_key'])){
      $obj = new ModelCatalogWkWebservicesTab($this->registry);

      $total = $obj->userValidation($params);

      if($total){
          $message = $total;
          $status = True;
      }else{
          $message = 'Details - api_user and api_key are not correct !!!';
      }
     }else{
      $message ='Method must have a parameter(array type). Array must contains api_user and api_key!!!';
    }

    $this->response->addHeader('Content-Type: application/json');

    $this->response->setOutput(json_encode(array($message, $status)));
  }

  public function Validate_session_key($session){
    $connection = false;

    $message =  '';
    if(!is_string($session)){
        $message = 'First prameter is reserverd for session key, and it should be string type!!!';
    } elseif(isset($session) && trim($session)){
        $sql ="SELECT id FROM " . DB_PREFIX . "api_keys  WHERE Auth_key='".$session."'";

        $result=$this->db->query($sql);

        if (isset($result->row['id'])) {
            $connection = true;
        } else {
            $message = 'Session Key is not validated!!!';
        }
    }else{
        $message = 'Method must have a prameter(string type)!!!';
    }

    return array($connection, $message);
  }

  public function UpdateOrderStatus() {
    $connection = false;

    $message = '';

    $status = False;

    $session = '';

    $params = $this->request->post;

		//Accepting data in json format / raw data

		$raw_data = json_decode(file_get_contents("php://input"),true);

		if ($raw_data) {
			foreach ($raw_data as $key => $value) {
			    $params[$key] = $value;
			}
		}

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      if(!is_array($params)){
        $message = 'Second prameter is reserverd for post data, and it should be array type with valid indexes!!!';
      }

      if(!isset($params['order_id']) || !$params['order_id'])
          $message = 'Parameter array must have order id to Update Status!!!';

      if(!isset($params['order_status_id']) || !$params['order_status_id'])
          $message = 'Parameter array must have order status id !!!';

      if ($message == '') {
        $params['notify'] = 1;

        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        $obj->addOrderHistory($params['order_id'],$params);

        $message = 'Order Status Updated';
        $status = True;
      }
    }
    else{
        $message = $connection[1];
    }

    $this->response->addHeader('Content-Type: application/json');

    $this->response->setOutput(json_encode(array($message, $status)));
  }

  public function category() {
    $connection = false;

    $message = '';

    $status = False;

    $last_id = 0;

    $session = '';

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];

      unset($params['session']);
    }

    if (isset($this->request->get['session']) && $this->request->get['session']) {
      $session = $this->request->get['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      //Edit category
      if (isset($params['category_id']) && $params['category_id']) {
        $message = '';

        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        if(!isset($params['name'])){
          $message = 'Parameter array must have category name !!!';
        }

        $data = $params;

        foreach ($params as $key=>$value) {
            if($key == 'name' || $key == 'meta_keyword' || $key == 'meta_description' || $key == 'description'){
                $data['category_description'][1][$key] = $params[$key];
            }else{
                $data[$key] = $params[$key];
            }
        }

        if ($message == '') {
          $obj->editCategory($params['category_id'], $data);
          $status = True;
          $message = 'Category Successfully Updated!';
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode(array($message, $status)));
      //Add category
      } elseif (!empty($params)) {
        $message = '';

        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        if(!isset($params['erp_category_id'])){
          $message = 'Parameter array must have erp category id for merge data !!!';
        }

        if(!isset($params['name'])){
          $message = 'Parameter array must have category name !!!';
        }

        if (isset($params['parent_id'])){
          $parent_id = $params['parent_id'];
        } else{
          $parent_id = 0;
        }

        $data = array(
          'parent_id' => $parent_id,
          'erp_category_id' => '',
          'top' => 0,
          'column' => '',
          'sort_order' => '',
          'status' => 1,
          'keyword' => 1,
          'category_description' => array(
            1 => array(
              'name' => '',
              'meta_keyword' => '',
              'meta_description' => '',
              'description' => '',
            )
          ),
        );

        foreach ($params as $key=>$value) {
          if($key == 'name' || $key == 'meta_keyword' || $key == 'meta_description' || $key == 'description'){
              $data['category_description'][1][$key] = $params[$key];
          }else{
              $data[$key] = $params[$key];
          }
        }

        $data['category_store'] = array(0);

        if ($message == '') {
          $last_id = $obj->addCategory($data);
          $status = True;
          $message = 'Category successfully added. Category Id: ' . $last_id;
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode(array($message, $last_id, $status)));
      } else {
        $obj = new ModelCatalogCategory($this->registry);

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode($obj->getCategories(array())));
      }
    }
    //Get category
    else{
      $this->response->addHeader('Content-Type: application/json');

      $this->response->setOutput(json_encode(array($connection[1], $status)));
    }
  }

  public function UpdateProductStock() {
    $connection = false;

    $message = '';

    $status = False;

    $session = '';

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      if(!is_array($params)){
        $message = 'Second prameter is reserverd for post data, and it should be array type with valid indexes!!!';
      }

      if(!isset($params['product_id']) || !$params['product_id'])
          $message = 'Parameter array must have product id to Update Stock!!!';

      if(!isset($params['stock']) || !$params['stock'])
          $message = 'Parameter array must have product stock !!!';

      if ($message == '') {
        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        $obj->updateProductStock($params);
        $status = True;
        $message = 'Product Stock Updated';
      }
    }
    else{
        $message = $connection[1];
    }

    $this->response->addHeader('Content-Type: application/json');

    $this->response->setOutput(json_encode(array($message, $status)));
  }

  public function getAllOrders() {
    $connection = false;

    $message = '';

    $session = '';

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      $obj = new ModelSaleOrder($this->registry);

      $message = $obj->getOrders($data);
      $status = True;
    }
    else{
        $message = $connection[1];
    }

    $this->response->addHeader('Content-Type: application/json');

    $this->response->setOutput(json_encode(array($message, $status)));
  }

  public function product() {
    $connection = false;

    $message = '';

    $last_id = 0;

    $status = False;

    $session = '';

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }
    // $this->response->addHeader('Content-Type: application/json');
    //
    // // $this->response->setOutput(json_encode(array($params)));
    // $this->response->setOutput(json_encode(array($raw_data)));
    // return $raw_data;

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];

      unset($params['session']);
    }

    if (isset($this->request->get['session']) && $this->request->get['session']) {
      $session = $this->request->get['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      //Edit product
      if (isset($params['product_id']) && $params['product_id']) {
        $message = '';

        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        if(!isset($params['name'])){
          $message = 'Parameter array must have product name !!!';
        }

        $data = $params;

        foreach ($params as $key=>$value) {
            if($key == 'name' || $key == 'meta_keyword' || $key == 'meta_description' || $key == 'description' || $key == 'tag' ){
                $data['product_description'][1][$key] = $params[$key];
            }else{
                $data[$key] = $params[$key];
            }
        }

        if(isset($params['oc_option_name'])){
          $option_data = $obj->getProductOptions($params['product_id']);

          if ($option_data && $option_data[0]['option_id'] == $params['oc_option_id']){
            $product_option_id = $obj->getProductOptionId($params['product_id']);

            $data['product_option'] = array(
              0 => array(
                'product_option_id'=> $product_option_id,
                'name'=>$params['oc_option_name'],
                'type'=>'select',
                'option_id'=>$params['oc_option_id'],
                'required'=>'1',
                'product_option_value'=>array()
              )
            );
          }else{
            $data['product_option'] = array(
              0 => array(
                'product_option_id'=> '',
                'name'=>$params['oc_option_name'],
                'type'=>'select',
                'option_id'=>$params['oc_option_id'],
                'required'=>'1',
                'product_option_value'=>array()
              )
            );
          }

          $data['product_option'][0]['product_option_value'] =array();

          for ($i=0; $i<sizeof($params['oc_option_value_ids']); $i++) {
            $product_option_value_id = $obj->getProductOptionValueId($params['product_id'], $params['oc_option_value_ids'][$i]['option_value_id']);

            array_push(
              $data['product_option'][0]['product_option_value'],
              array(
                'option_value_id'=>$params['oc_option_value_ids'][$i]['option_value_id'],
                'product_option_value_id'=>$product_option_value_id,
                'quantity'=>$params['oc_option_value_ids'][$i]['quantity'],
                'subtract'=>'1',
                'price_prefix'=>$params['oc_option_value_ids'][$i]['price_prefix'],
                'price'=>$params['oc_option_value_ids'][$i]['price'],
                'points_prefix'=>'+',
                'points'=>'',
                'weight_prefix'=>'+',
                'weight'=>'',
                'erp_product_id'=>$params['oc_option_value_ids'][$i]['erp_product_id'],
              )
            );
          }
        }

        $data['product_store'] = array(0);

        if ($message == '') {
          $last_id = $obj->editProduct($params['product_id'],$data);
          $status = True;
          $message = 'Product Successfully Updated!';
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode(array($message, $last_id, $status)));
      //Add product
      } elseif (!empty($params)) {
        $message = '';

        $obj = new ModelCatalogWkWebservicesTab($this->registry);

        if(!isset($params['erp_product_id'])){
          $message = 'Parameter array must have erp product id for merge data !!!';
        }

        if(!isset($params['name'])){
          $message = 'Parameter array must have product name !!!';
        }

        $language_id = $this->config->get('config_language_id');

        if(!$language_id){
            $message = 'Opencart Config Language Not Found!!!';
        }

        $data = array (
          'model' => '',
          'sku' => '',
          'location' => '',
          'quantity' => 0,
          'minimum' => 0,
          'subtract' => '',
          'stock_status_id' => '',
          'date_available' => '',
          'manufacturer_id' => '',
          'shipping' => '',
          'price' => '',
          'points' => '',
          'weight' => '',
          'weight_class_id' => '',
          'length' => '',
          'width' => '',
          'height' => '',
          'length_class_id' => '',
          'status' => 1,
          'tax_class_id' => 1,
          'sort_order' => '',
          'keyword' => 1,
          'erp_product_id' => 0,
          'erp_template_id' => 0,
          'product_category' => array(),
          'product_description' => array(
            $language_id => array (
              'name' => '',
              'meta_keyword' => '',
              'meta_description' => '',
              'description' => '',
              'tag' => ''
            )
          ),
        );

        if(isset($params['oc_option_name'])){
          $data['product_option'] = array(
            0 => array(
              'product_option_id'=>'',
              'name'=>$params['oc_option_name'],
              'type'=>'select',
              'option_id'=>$params['oc_option_id'],
              'required'=>'1',
              'product_option_value'=>array()
            )
          );

          $data['product_option'][0]['product_option_value'] =array();

          for ($i=0; $i<sizeof($params['oc_option_value_ids']); $i++) {
            array_push(
              $data['product_option'][0]['product_option_value'],

              array(
                  'option_value_id'=>$params['oc_option_value_ids'][$i]['option_value_id'],
                  'product_option_value_id'=>'',
                  'quantity'=>$params['oc_option_value_ids'][$i]['quantity'],
                  'subtract'=>'1',
                  'price_prefix'=>$params['oc_option_value_ids'][$i]['price_prefix'],
                  'price'=>$params['oc_option_value_ids'][$i]['price'],
                  'points_prefix'=>'+',
                  'points'=>'',
                  'weight_prefix'=>'+',
                  'weight'=>'',
                  'erp_product_id'=>$params['oc_option_value_ids'][$i]['erp_product_id'],
              )
            );
          }
        }

        foreach ($params as $key=>$value) {
            if($key == 'name' || $key == 'meta_keyword' || $key == 'meta_description' || $key == 'description' || $key == 'tag' ){
                $data['product_description'][$language_id][$key] = $params[$key];
            }else{
                $data[$key] = $params[$key];
            }
        }

        $data['product_store'] = array(0);

        if ($message == '') {
          $last_id = $obj->addProduct($data);
          $status = True;
          $message = 'Product successfully added. product Id: ' . $last_id['product_id'];
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode(array($message, $last_id, $status)));
      } else {
        $obj = new ModelCatalogProduct($this->registry);

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode($obj->getProducts(array())));
      }
    }
    //Get product
    else{
      $this->response->addHeader('Content-Type: application/json');

      $this->response->setOutput(json_encode(array($connection[1], $last_id, $status)));
    }
  }

  public function customer() {
    $connection = false;

    $message = '';

    $session = '';

    $params = $this->request->post;

    //Accepting data in json format / raw data

    $raw_data = json_decode(file_get_contents("php://input"),true);

    if ($raw_data) {
      foreach ($raw_data as $key => $value) {
          $params[$key] = $value;
      }
    }

    if (isset($params['session']) && $params['session']) {
      $session = $params['session'];

      unset($params['session']);
    }

    if (isset($this->request->get['session']) && $this->request->get['session']) {
      $session = $this->request->get['session'];
    }

    $connection = $this->Validate_session_key($session);

    if($connection[0]){
      if (!empty($params)) {
        $message = '';

        $obj = new ModelCustomerCustomer($this->registry);

        if(!isset($params['firstname']) || !isset($params['lastname']) || !isset($params['email']) || !isset($params['password'])){
          $message = 'Parameter array must have customer firstname, lastname, email and password !!!';
        }

        $data = array(
          'firstname' => '',
          'lastname' => '',
          'email' => '',
          'telephone' => '',
          'fax' => '',
          'newsletter' => '',
          'customer_group_id' => 1,
          'salt' => '',
          'password' => '',
          'status' => 1,
          'address' => array(
            0 => array(
              'firstname' => '',
              'lastname' => '',
              'company' => '',
              'company_id' => '',
              'tax_id' => '',
              'address_1' => '',
              'address_2' => '',
              'city' => '',
              'postcode' => '',
              'country_id' => '',
              'zone_id' => '',
            )
          ),
        );

        foreach ($params as $key=>$value) {
            if($key == 'firstname' || $key == 'lastname' || $key == 'company' || $key == 'company_id' || $key == 'tax_id' || $key == 'address_1' || $key == 'address_2' || $key == 'city' || $key == 'postcode' || $key == 'country_id' || $key == 'zone_id' ){
                $data['address'][$key] = $params[$key];
            }
            $data[$key] = $params[$key];
        }

        if ($message == '') {
          $last_id = $obj->addCustomer($data);

          $message = 'Customer successfully added. customer Id: ' . $last_id;
        }

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode(array($message)));
      } else {
        $obj = new ModelCustomerCustomer($this->registry);

        $this->response->addHeader('Content-Type: application/json');

        $this->response->setOutput(json_encode($obj->getCustomers(array())));
      }
    }
    //Get customer
    else{
      $this->response->addHeader('Content-Type: application/json');

      $this->response->setOutput(json_encode(array($connection[1])));
    }
  }
}
