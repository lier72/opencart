<?php
################################################################################################
# Product xmlrpc Tab Opencart 1.5.1.x From Webkul  http://webkul.com    #
################################################################################################

class ModelCatalogErpProductDiscount extends Model {

    public function check_all_products($userId, $client, $sock,$context){
        $db = $context['db'];
        $pwd = $context['pwd'];
        $cart_user = $context['cart_user'];
        $id_lang = $context['id_lang'];
        $is_error      = 0;
        $error_message = '';
        $ids           = '';        
        $id_currency = $this->config->get('wk_webservices_discount_currency');
        $price_list_name = $this->config->get('wk_webservices_discount_lable');
        $status = $this->config->get('wk_webservices_discount_status');
        
        // Check for existing Pricelist
        $pricelist = $this->db->query("SELECT `erp_id` ,`erp_pricelist_version_id` FROM ". DB_PREFIX . "erp_product_discount`")->rows;           

        if (count($pricelist) > 1) {            
            //Nothing to export
            return array(
                'is_error' => 1,
                'error_message' => 'Duplicate Pricelists found in Mapping Table !!!',
                'value' => 0,
                '$ids' => $ids
            );
        }
       

        elseif (count($pricelist) ==0){             
            if ($id_currency == '')
                $id_currency  = $this->config->get('config_currency');   
            $erp_currency_id = $this->get_erp_currency_id($id_currency, $userId, $client, $sock, $db, $pwd);
            
            $arrayVal = array(
            'name' => new xmlrpcval($price_list_name, "string"),
            'erp_currency_id' => new xmlrpcval($erp_currency_id, "int")           
            );

            $msg_server  = new xmlrpcmsg('execute');
            $msg_server->addParam(new xmlrpcval($context['db'], "string"));
            $msg_server->addParam(new xmlrpcval($userId, "int"));
            $msg_server->addParam(new xmlrpcval($context['pwd'], "string"));
            $msg_server->addParam(new xmlrpcval("extra.function", "string"));
            $msg_server->addParam(new xmlrpcval("create_pricelist", "string"));
            $msg_server->addParam(new xmlrpcval($arrayVal, "struct"));
            $resp = $client->send($msg_server);           
            if (!$resp->faultCode()){
                $erp_discount_pricelist_id    = $resp->value()->me['struct']['pricelist_id']->me['int'];
                $erp_pricelist_version   = $resp->value()->me['struct']['pricelist_version']->me['int'];

                $entry_in_merge = $this->db->query("INSERT INTO ".DB_PREFIX."erp_product_discount SET  `erp_id`='$erp_discount_pricelist_id', `pricelist_name`='$price_list_name'  ,`erp_pricelist_version_id`='$erp_pricelist_version',created_by='$cart_user'");
             }                          
        }

        else{
            $erp_discount_pricelist_id=$pricelist[0]['erp_id'];
            $erp_pricelist_version=$pricelist[0]['erp_pricelist_version_id'];
        }
    
        $data = $this->db->query("SELECT a.`product_special_id`, a.`product_id`, a.`price`, e.`erp_product_id`, e.`opencart_product_option_id`, p.`price` as original_price FROM `". DB_PREFIX ."product_special` a 
            LEFT JOIN ".DB_PREFIX."erp_product_variant_merge e ON (a.`product_id` = e.`opencart_product_id`) 
            LEFT JOIN ".DB_PREFIX."product p ON (e.`opencart_product_id` = p.`product_id`) ")->rows;
       
        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => 1,
                'error_message' => 'No Mapped products found !!!',
                'value' => 0,
                '$ids' => $ids
            );
        }

        $mapped_specific_rule = array();
        $erp_price_type_id = $this->get_erp_product_price_type($userId, $client, $sock, $db, $pwd);

        foreach ($data as $map_data) { 
            array_push($mapped_specific_rule, $map_data['product_special_id']);           
            $check = $this->check_discount_merge($map_data['product_special_id'],$map_data['product_id'],$map_data['opencart_product_option_id']);                       
            if ($check['erp_product_id']<0){                
                //Create item discount
                
                $export_to_erp = $this->create_pricelist_line_for_product($userId, $client, $sock, $db, $pwd, $map_data, $erp_pricelist_version, $erp_price_type_id);                                       
                    
                }                       
            
        }
        //Deleting old specific price rule
        $extra_ids = join(", ",$mapped_specific_rule );
            
          $q =     "SELECT `erp_discount_item_id` FROM ". DB_PREFIX . "erp_product_special_price_merge WHERE 
            `product_special_id` NOT IN (".$extra_ids.") " ; 

        $deleted_rule = $this->db->query($q)->rows;              
        if ($deleted_rule){
            $to_delete_erp_id = array(); 
            foreach ($deleted_rule as $erp_id) { 
                array_push($to_delete_erp_id, new xmlrpcval($erp_id['erp_discount_item_id'], 'int'));
            }        
            $delete = $this->delete_pricelist_item($to_delete_erp_id, $userId, $client, $sock, $db, $pwd);
            if ($delete['delete']){
                $deleted_mappiings = $this->db->query("DELETE FROM ". DB_PREFIX . "erp_product_special_price_merge WHERE `product_special_id` NOT IN (".$extra_ids.")")->rows; 
            }
       
        }
    }

    public function delete_pricelist_item($erp_ids, $userId, $client, $sock, $db, $pwd){
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("force.done", "string"));
        $msg_ser->addParam(new xmlrpcval("delete_product_pricelist_item", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_ids, "struct"));
        $resp = $client->send($msg_ser);
        if (!$resp->faultCode()){
           return array(               
                'delete' => True
            );
        }
    }

    private function check_discount_merge($specific_rule, $oc_product_id, $oc_product_option_id){
        $merge_data = $this->db->query("SELECT `erp_product_id`,`oc_product_id`,`oc_product_option_id` from `" . DB_PREFIX . "erp_product_special_price_merge` where `product_special_id`=".$specific_rule." and `oc_product_id`=".$oc_product_id." and `oc_product_option_id`=".$oc_product_option_id." ")->rows;         
        if ($merge_data){
            return array(
            'erp_product_id' => $merge_data[0]['erp_product_id']          
            );
        } else
           return array(
            'erp_product_id' => -1            
        );
    }

    public function create_pricelist_line_for_product($userId, $client, $sock, $db, $pwd, $data, $erp_pricelist_version, $erp_price_type_id){

        $discount_amt = $data['price'] - $data['original_price'];        
        $key     = array(
            'name' => new xmlrpcval('Default Public Pricelist Rule', "string"),
            'price_version_id' => new xmlrpcval($erp_pricelist_version, "int"),
            'product_id' => new xmlrpcval($data['erp_product_id'], "int"),
            'min_quantity' => new xmlrpcval(0, "int"),
            'price_surcharge' => new xmlrpcval($discount_amt, "double"),
            'price_discount' => new xmlrpcval(0.0000, "double"),
            'base' => new xmlrpcval($erp_price_type_id, "int"),
        );  

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.pricelist.item", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "struct"));
        $resp = $client->send($msg_ser);
              
        if ($resp->faultCode()){
            $error_message = $resp->faultString();              
            return array(
                'error_message' => $error_message,
                'is_error' => True
            );
        }
        else{
            $val    = $resp->value()->me;
            $erp_id = $val['int'];
            $this->db->query("INSERT INTO " . DB_PREFIX ."erp_product_special_price_merge SET `erp_product_id`='".$data['erp_product_id']."', `oc_product_id`='".$data['product_id']."' , `oc_product_option_id`='".$data['opencart_product_option_id']."', `product_special_id`='".$data['product_special_id']."', `erp_discount_item_id`='".$erp_id."'")->rows;
        } 
    }

    public function get_erp_product_price_type($userId, $client, $sock, $db, $pwd){
        $key = array(new xmlrpcval(
                    array(  new xmlrpcval('field' , "string"),
                            new xmlrpcval('=',"string"),
                            new xmlrpcval('list_price',"string")),"array"),
            );
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.price.type", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));        
        $resp = $client->send($msg_ser);       
        if (!$resp->faultCode()) {
            $val = $resp->value()->me['array'];
            if (count($val)>0){
                $erp_price_type = $val[0]->me['int'];
                return $erp_price_type;
            }else{
                $key     = array(
                    'name' => new xmlrpcval('Public Price', "string"),
                    'field' => new xmlrpcval('list_price', "string")                
                );
                $msg_ser1 = new xmlrpcmsg('execute');
                $msg_ser1->addParam(new xmlrpcval($db, "string"));
                $msg_ser1->addParam(new xmlrpcval($userId, "int"));
                $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
                $msg_ser1->addParam(new xmlrpcval("product.price.type", "string"));
                $msg_ser1->addParam(new xmlrpcval("create", "string"));
                $msg_ser1->addParam(new xmlrpcval($key, "struct"));
                $response = $client->send($msg_ser1);
                if (!$response->faultCode()){
                    $val_1   = $response->value()->me;
                    $erp_price_type = $val_1['int'];
                    return $erp_price_type;                     
                }               
            }
        }        
    }


    public function get_erp_currency_id($id_currency, $userId, $client, $sock, $db, $pwd){         
        
        $this->load->model('catalog/erp_currency') ;
        $currency         = $this->model_catalog_erp_currency;
        $erp_pricelist_id      = $currency->check_specific_currency($id_currency, $userId, $sock, $client, $db, $pwd);  

        $field[0] = new xmlrpcval("currency_id","string");
        $erp_id[0] = new xmlrpcval($erp_pricelist_id,"int");

        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.pricelist", "string"));
        $msg_ser->addParam(new xmlrpcval("read", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_id, "array"));
        $msg_ser->addParam(new xmlrpcval($field, "array"));
        $resp = $client->send($msg_ser);
        if (!$resp->faultCode()) {           
            $val = $resp->value()->me['array'][0]->me['struct']['currency_id']->me['array'][0]->me;
            return $val['int'];            
        }
    }



    public function getProductTotal($data){

        // return false;

        $sql = "SELECT * FROM " . DB_PREFIX . "erp_product_discount WHERE 1 ";

        $query = $this->db->query($sql);
        
        return count($query->rows);  

    }
    
    public function getProduct($data){

        // return false;        

        $sql = "SELECT * FROM " . DB_PREFIX . "erp_product_discount WHERE 1 ";

        $sort_data = array(
            'id',
            'erp_id',
            'pricelist_name',
            'created_date',
            'created_by',
        );

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];   
        } else {
            $sql .= " ORDER BY id ";    
        }

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }           

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }   
            
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        } 

        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    public function deleteProductDiscount($id){
        $this->db->query("DELETE FROM ".DB_PREFIX."erp_product_discount WHERE id = '".(int)$id."'");        
    }


}

?>