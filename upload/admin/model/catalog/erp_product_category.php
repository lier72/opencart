<?php
################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com    #
################################################################################################
require_once 'oob_log.php';
class ModelCatalogErpproductcategory  extends Model{

    //To check for all categories.
    public function check_all_categories($context){

        $is_error       = 0;
        $error_message  = '';
        $ids            = '';

        $data = $this->db->query("SELECT c.category_id,cd.name,c.parent_id FROM ".DB_PREFIX."category c LEFT JOIN ".DB_PREFIX."category_description cd ON(c.category_id = cd.category_id) WHERE c.category_id NOT IN (SELECT opencart_category_id FROM " .DB_PREFIX."erp_category_merge WHERE is_synch = 0) AND c.status = 1 ")->rows;

        if (count($data) == 0) {
            //Nothing to export
            return array(
                'is_error' => $is_error,
                'error_message' => $error_message,
                'value' => 0,
                '$ids' => $ids
            );
        }

        foreach ($data as $cat_id) {
              $this->sync_categories($cat_id['category_id'],$context);
        }

        return array(
            'is_error' => $is_error,
            'error_message' => $error_message,
            'value' => 1,
            'ids' => $ids
        );
    }

    //To synch categories with its structure.
    public function sync_categories($opencart_id, $context){

        $check_obj = $this->check_category($opencart_id);

        if ($check_obj[0] == 0) {

            $db_object          = $this->get_cat_info($opencart_id);
            $name               = html_entity_decode($db_object['name']);
            $opencart_parent_id = $db_object['id_parent'];
            if ($opencart_parent_id > 0)
                $erp_parent_id = $this->sync_categories($opencart_parent_id, $context);
            else {
                $erp_id = $this->create_category($opencart_id, false , $name, $context);
                return $erp_id['erp_id'];
            }

            $erp_id = $this->create_category($opencart_id, $erp_parent_id, $name, $context);
            return $erp_id['erp_id'];

        } elseif ($check_obj[0] == -1) {

            $db_object           = $this->get_cat_info($opencart_id);
            $name                = html_entity_decode($db_object['name']);
            $opencart_parent_id = $db_object['id_parent'];

            if ($opencart_parent_id > 0)
                $erp_parent_id = $this->sync_categories($opencart_parent_id, $context);
            else
                $erp_parent_id = false;

            $this->update_category($opencart_id, $erp_parent_id, $name, $check_obj[1], $context);
            return $check_obj[1];

        } else
            return $check_obj[0];
    }


    //To check categories in Merge Table.
    public function check_category($opencart_id){
        $categ_obj = $this->db->query("SELECT `erp_category_id`,`is_synch` from `" . DB_PREFIX . "erp_category_merge` where `opencart_category_id` = '" . $opencart_id . "'")->row;
        if (isset($categ_obj['erp_category_id']) && $categ_obj['erp_category_id'] > 0) {
            if ($categ_obj['is_synch'] == 1)
                return array(
                    -1,
                    $categ_obj['erp_category_id']
                );
            else
                return array(
                    $categ_obj['erp_category_id']
                );
        } else
            return array(
                0
            );
    }


    // the parent id of category
    public function get_cat_info($opencart_id){

        $db_object  = $this->db->query("SELECT `parent_id`,`name` from `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON(c.category_id = cd.category_id) where c.category_id='$opencart_id'")->row;

        if($db_object)
            return array(
                'id_parent' => $db_object['parent_id'],
                'name' => $db_object['name']
            );
        else
            return array(
                'id_parent' => '0',
                'name' => 'No Category'
            );
    }

     //To create category at erp end.
    private function create_category($opencart_id, $id_parent = 0, $name, $context){

        $userId         = $context['userId'];
        $client         = $context['client'];
        $db             = $context['db'];
        $pwd            = $context['pwd'];
        $cart_user      = $context['cart_user'];

        $context = array(
            'opencart' => new xmlrpcval('opencart', "string")
        );

        $arrayVal = array(
            'name' => new xmlrpcval($name, "string"),
            'type' => new xmlrpcval("normal", "string"),
            'opencart_id' => new xmlrpcval($opencart_id, "int")
        );

        if($id_parent)
            $arrayVal['parent_id'] = new xmlrpcval($id_parent, "int");

        $msg_ser  = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.category", "string"));
        $msg_ser->addParam(new xmlrpcval("create", "string"));
        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
        $msg_ser->addParam(new xmlrpcval($context, "struct"));
        $resp = $client->send($msg_ser);
        if (!$resp->faultCode()) {
                $val    = $resp->value()->me;
                $erp_id = $val['int'];
                $this->addto_category_merge($erp_id, $opencart_id, $cart_user);
                return array(
                    'erp_id' => $erp_id
                );
        }else{
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
            $error_message = $resp->faultString();
            return array(
                'error_message' => $error_message,
                'value' => False
            );
        }
    }

     //To insert data in category merge table.
    public function addto_category_merge($erp_id, $opencart_id, $cart_user){
        $this->db->query("INSERT INTO ".DB_PREFIX."erp_category_merge SET erp_category_id='$erp_id',opencart_category_id='$opencart_id',created_by='$cart_user'");
    }

    //To check for specific category.
    public function check_specific_category($cartid, $userId, $client, $db, $pwd, $cart_user='Front End'){
        $context = array( 'db' => $db,
                         'pwd' => $pwd,
                         // 'sock' => $sock,
                         'client' => $client,
                         'userId' => $userId,
                         'cart_user' => $cart_user
                         );

        $erp_id      = $this->sync_categories($cartid, $context);
        return $erp_id;
    }

    //To update category at erp end.
    public function update_category($cart_id, $id_parent, $name, $erp_id, $context){

        $userId         = $context['userId'];
        $client         = $context['client'];
        // $sock           = $context['sock'];
        $db             = $context['db'];
        $pwd            = $context['pwd'];
        $cart_user      = $context['cart_user'];

        $context = array(
            'opencart' => new xmlrpcval('opencart', "string")
        );
        $erp_cat_id = array(
            new xmlrpcval($erp_id, 'int')
        );
        $arrayVal   = array(
            'name' => new xmlrpcval($name, "string"),
            'type' => new xmlrpcval("normal", "string"),
            'parent_id' => new xmlrpcval($id_parent, "int")
        );
        $msg_ser    = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.category", "string"));
        $msg_ser->addParam(new xmlrpcval("write", "string"));
        $msg_ser->addParam(new xmlrpcval($erp_cat_id, "array"));
        $msg_ser->addParam(new xmlrpcval($arrayVal, "struct"));
        $msg_ser->addParam(new xmlrpcval($context, "struct"));
        $resp = $client->send($msg_ser);
        if (!$resp->faultCode()) {
            $val = $resp->value()->me;
            $this->db->query("UPDATE  `" . DB_PREFIX . "erp_category_merge` set `is_synch`= 0 where `opencart_category_id`='" . $cart_id . "'");
        } else{
          $log = new oob_log();
          $log->logMessage(__FILE__,__LINE__,$resp->raw_data,'CRITICAL: ');
        }
    }

    public function chkErpOpencartCategories($erp_id,$cart_id){
        $chk  = $this->db->query("SELECT `id` from `" . DB_PREFIX . "erp_category_merge` WHERE opencart_category_id='$cart_id' or erp_category_id='$erp_id'")->row;
        if($chk)
            return false;
        return true;
    }

    public function getErpCategoryArray($userId,$client,$db,$pwd,$cart_user) {
        $Category = array();

        $key     = array();
        $msg_ser = new xmlrpcmsg('execute');
        $msg_ser->addParam(new xmlrpcval($db, "string"));
        $msg_ser->addParam(new xmlrpcval($userId, "int"));
        $msg_ser->addParam(new xmlrpcval($pwd, "string"));
        $msg_ser->addParam(new xmlrpcval("product.category", "string"));
        $msg_ser->addParam(new xmlrpcval("search", "string"));
        $msg_ser->addParam(new xmlrpcval($key, "array"));

        $resp0    = $client->send($msg_ser);
        if ($resp0->faultCode()) {
            $log = new oob_log();
            $log->logMessage(__FILE__,__LINE__,$resp0->raw_data,'CRITICAL: ');
            array_push($Category, array('name' => 'Not Available(Error in Fetching)', 'id' => ''));
            return $Category;
        }else{
            $val    = $resp0->value()->me['array'];
            $key1 = array(new xmlrpcval('id','int') , new xmlrpcval('name', 'string'));
            $msg_ser1 = new xmlrpcmsg('execute');
            $msg_ser1->addParam(new xmlrpcval($db, "string"));
            $msg_ser1->addParam(new xmlrpcval($userId, "int"));
            $msg_ser1->addParam(new xmlrpcval($pwd, "string"));
            $msg_ser1->addParam(new xmlrpcval("product.category", "string"));
            $msg_ser1->addParam(new xmlrpcval("read", "string"));
            $msg_ser1->addParam(new xmlrpcval($val, "array"));
            $msg_ser1->addParam(new xmlrpcval($key1, "array"));
            $resp1   = $client->send($msg_ser1);
            if ($resp1->faultCode()) {
                $log = new oob_log();
                $log->logMessage(__FILE__,__LINE__,$resp1->raw_data,'CRITICAL: ');
                $msg = 'Not Available- Error: '.$resp1->faultString();
                array_push($Category, array('label' => $msg, 'id' => ''));
                return $Category;
            }else {
                $value_array=$resp1->value()->scalarval();
                $count = count($value_array);

                for($x=0;$x<$count;$x++){
                   $id = $value_array[$x]->me['struct']['id']->me['int'];
                   $name = $value_array[$x]->me['struct']['name']->me['string'];
                   array_push($Category,
                    array(
                            'id' => $id,
                            'name'=>$name )
                    );
                }
            }
        }

        return $Category;
    }


}
?>