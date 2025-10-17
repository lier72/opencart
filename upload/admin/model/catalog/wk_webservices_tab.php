<?php
################################################################################################
# Webservices Tab Opencart 1.5.1.x From Webkul  http://webkul.com   #
################################################################################################
class ModelCatalogWkWebservicesTab extends Model {

    public function userValidation($data){

        $sql ="SELECT api_id as id FROM `" . DB_PREFIX . "api` WHERE `key` = '" . $this->db->escape($data['api_key']) . "' AND status = '1'";

        $result=$this->db->query($sql);
        if(isset($result->row['id'])){
            $Authkey = $this->CreateSessionKey($result->row['id']);
            return $Authkey;
        }else{
            return false;
        }
    }

    public function CreateSessionKey($id){
        $date = date("Y-m-d H:i:s");

        $seed = str_split('abcdefghijklmnopqrstuvwxyz'
                             .'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
                             .'0123456789');
        shuffle($seed);
        $rand = '';
        foreach (array_rand($seed, 32) as $k) $rand .= $seed[$k];

        $sql =$this->db->query("SELECT id FROM " . DB_PREFIX . "api_keys  WHERE user_id='".$id."'");

        if(isset($sql->row['id'])){
            $this->db->query("UPDATE " . DB_PREFIX . "api_keys SET date_created='$date' ,Auth_key='$rand' WHERE id='".$sql->row['id']."' ");
        }else{
            $this->db->query("INSERT INTO " . DB_PREFIX . "api_keys SET user_id='" .$id. "', date_created='$date' ,Auth_key='$rand' ");
        }

        return $rand;

    }

    public function keyValidation($key)
    {
        $sql ="SELECT id FROM " . DB_PREFIX . "api_keys  WHERE Auth_key='".$key."'";
        $result=$this->db->query($sql);
        if (isset($result->row['id'])) {
            return true;
        } else {
            return false;
        }
    }

    public function addProduct($data)
    {
        $data['created_by'] = 'From Odoo';
        $merge_data = array();
        $this->db->query("INSERT INTO " . DB_PREFIX . "product SET model = '" . $this->db->escape($data['model']) . "', sku = '" . $this->db->escape($data['sku']) . "', location = '" . $this->db->escape($data['location']) . "', quantity = '" . (int)$data['quantity'] . "', minimum = '" . (int)$data['minimum'] . "', subtract = '" . (int)$data['subtract'] . "', stock_status_id = '" . (int)$data['stock_status_id'] . "', date_available = '" . $this->db->escape($data['date_available']) . "', manufacturer_id = '" . (int)$data['manufacturer_id'] . "', shipping = '" . (int)$data['shipping'] . "', price = '" . (float)$data['price'] . "', points = '" . (int)$data['points'] . "', weight = '" . (float)$data['weight'] . "', weight_class_id = '" . (int)$data['weight_class_id'] . "', length = '" . (float)$data['length'] . "', width = '" . (float)$data['width'] . "', height = '" . (float)$data['height'] . "', length_class_id = '" . (int)$data['length_class_id'] . "', status = '" . (int)$data['status'] . "', tax_class_id = '" . $this->db->escape($data['tax_class_id']) . "', sort_order = '" . (int)$data['sort_order'] . "', date_added = NOW()");

        $product_id = $this->db->getLastId();

        if (isset($data['image'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE product_id = '" . (int)$product_id . "'");
        }
        $productName = "";
        foreach ($data['product_description'] as $language_id => $value) {
            $languages = $this->db->query("SELECT language_id FROM " . DB_PREFIX . "language where status = 1")->rows;
            foreach ($languages as $lang) {
                $productName = $value['name'];
                $language_id = $lang['language_id'];
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', description = '" . $this->db->escape($value['description']) ."'");
            }
        }
        if (isset($data['product_image'])) {
            $this->uploadProductImage($product_id, $productName, $data['product_image']);
        }

        if (isset($data['product_store'])) {
            foreach ($data['product_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
                if ($product_attribute['attribute_id']) {
                    $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");

                    foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $this->db->escape($product_attribute_description['text']) . "'");
                    }
                }
            }
        }

        if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
                if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

                    $product_option_id = $this->db->getLastId();

                    if (isset($product_option['product_option_value']) && count($product_option['product_option_value']) > 0 ) {

                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");

                            // To Add to Variant Merge Table
                            $check_option_value_data = $this->db->query("SELECT `product_option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$this->db->escape($product_id)."'")->rows;
                            if ($check_option_value_data) {
                                foreach ($check_option_value_data as $option_values) {
                                    $check_merge = $this->db->query("SELECT * FROM `". DB_PREFIX ."erp_product_variant_merge` where opencart_product_option_id='".$option_values['product_option_value_id']."'")->row;
                                    if (!$check_merge) {
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "erp_product_variant_merge SET erp_product_id = '" . (int)$product_option_value['erp_product_id'] . "', opencart_product_option_id = '" . $option_values['product_option_value_id']. "', opencart_product_id = '" . $this->db->escape($product_id) . "',created_by = '".$this->db->escape($data['created_by'])."'");


                                    $merge_data[$product_option_value['erp_product_id']] = $option_values['product_option_value_id'];
                                    }
                                }
                            }
                        }
                    } else {
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '".$product_option_id."'");
                    }
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value = '" . $this->db->escape($product_option['option_value']) . "', required = '" . (int)$product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
            }
        }

        if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
            }
        }

        if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$product_reward['points'] . "'");
            }
        }

        if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout) {
                if ($layout['layout_id']) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
                }
            }
        }

        // SEO URL
    		if (isset($data['product_seo_url'])) {
    			foreach ($data['product_seo_url'] as $store_id => $language) {
    				foreach ($language as $language_id => $keyword) {
    					if (!empty($keyword)) {
    						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
    					}
    				}
    			}
    		}

        if (isset($data['erp_product_id'])) {
            if (!isset($data['created_by'])) {
                $data['created_by'] = 'From Odoo';
            }

            if (isset($data['erp_template_id'])) {
                $erp_template_id = $data['erp_template_id'];

                $this->db->query("INSERT INTO " . DB_PREFIX . "erp_product_template_merge SET erp_template_id = '" . (int)$erp_template_id . "', opencart_product_id = '" . $this->db->escape($product_id) . "',created_by = '".$this->db->escape($data['created_by'])."'");
            }
            if (isset($data['variant_id'])) {
                $erp_product_id = $data['variant_id'];

                $this->db->query("INSERT INTO " . DB_PREFIX . "erp_product_variant_merge SET erp_product_id = '" . (int)$erp_product_id . "', opencart_product_option_id =0  , opencart_product_id = '" . $this->db->escape($product_id) . "',created_by = '".$this->db->escape($data['created_by'])."'");
                $merge_data[$erp_product_id] = 0;

            }

        }

        return array(
            'product_id'=>$product_id,
            'merge_data'=>$merge_data,
        );
    }

    public function uploadProductImage($product_id, $productName, $image_path = null)
    {
        if ((isset($product_id) && $product_id) && isset($image_path)) {
            $imageData = base64_decode($image_path);
            $productName = strtolower($productName);
            $productName = str_replace(' ', '_', $productName);
            $imageName = $productName.'_'.$product_id.'.jpg';
            if ($imageName) {
                file_put_contents(DIR_IMAGE.'catalog/'.$imageName, $imageData);
                $image_details = array('product_id' => $product_id,'img_path' => 'catalog/'.$imageName);

                $this->saveImageToProduct($image_details);
            }
        }
    }

    /**
     * [saveImageToProduct function for save image]
     * @param  array  $data [description]
     * @return [type]       [description]
     */
    public function saveImageToProduct($data = array())
    {
        if (isset($data['img_path']) && $data['img_path']) {
            $getEntry = $this->db->query("SELECT product_id FROM ".DB_PREFIX."product WHERE product_id = '".(int)$data['product_id']."'")->row;

            if (isset($getEntry['product_id']) && $getEntry['product_id']) {
              $this->db->query("UPDATE ".DB_PREFIX."product SET `image` = '".$this->db->escape($data['img_path'])."' WHERE product_id = '".(int)$data['product_id']."' ");
            }
        }
    }

    public function updateProductStock($params)
    {
        $status = 5;
        $product_id = $params['product_id'];
        $quantity = $params['stock'];
        if ($quantity > 0) {
            $status = 7;
        }
        if (isset($params['option_id'])) {
            $option_id = $params['option_id'];
            $option_qty = $params['option_qty'];
            $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET  quantity = '" . (int)$option_qty . "' WHERE product_id = '".$product_id."' AND product_option_value_id = '".(int)$option_id."'");
        }
        $this->db->query("UPDATE " . DB_PREFIX . "product SET  quantity = '" . (int)$quantity . "', stock_status_id = '".$status."', date_modified = NOW() WHERE product_id = '".$product_id."'");
    }

    public function addCategory($data)
    {

        $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '" . (int)$data['parent_id'] . "', `top` = '" . (isset($data['top']) ? (int)$data['top'] : 0) . "', `column` = '" . (int)$data['column'] . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', date_modified = NOW(), date_added = NOW()");

        $category_id = $this->db->getLastId();

        if (isset($data['image'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "category SET image = '" . $this->db->escape(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE category_id = '" . (int)$category_id . "'");
        }

        foreach ($data['category_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "', meta_keyword = '" . $this->db->escape($value['meta_keyword']) . "', meta_description = '" . $this->db->escape($value['meta_description']) . "', description = '" . $this->db->escape($value['description']) . "'");
        }

        // MySQL Hierarchical Data Closure Table Pattern
        $level = 0;

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY `level` ASC");

        foreach ($query->rows as $result) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

            $level++;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");

        if (isset($data['category_filter'])) {
            foreach ($data['category_filter'] as $filter_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_filter SET category_id = '" . (int)$category_id . "', filter_id = '" . (int)$filter_id . "'");
            }
        }

        if (isset($data['category_store'])) {
            foreach ($data['category_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        // Set which layout to use with this category
        if (isset($data['category_layout'])) {
            foreach ($data['category_layout'] as $store_id => $layout) {
                if ($layout['layout_id']) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
                }
            }
        }

        if (isset($data['category_seo_url'])) {
    			foreach ($data['category_seo_url'] as $store_id => $language) {
    				foreach ($language as $language_id => $keyword) {
    					if (!empty($keyword)) {
    						$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape($keyword) . "'");
    					}
    				}
    			}
    		}

        if(isset($data['erp_category_id'])){
            if(!isset($data['created_by']))
                $data['created_by'] = 'From Odoo';

            $this->db->query("INSERT INTO " . DB_PREFIX . "erp_category_merge SET erp_category_id = '" . (int)$data['erp_category_id'] . "', opencart_category_id = '" . $this->db->escape($category_id) . "',created_by = '".$this->db->escape($data['created_by'])."'");
        }

        return $category_id;
    }

    function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

    public function editProduct($product_id, $data)
    {
        $merge_data  = array();
        $data['created_by'] = 'From Odoo';

        $sql = "UPDATE " . DB_PREFIX . "product SET ";

        $implode = array();

        if (isset($data['model'])) {
            $implode[] = "model = '" . $this->db->escape($data['model']) . "'";
        }

        if (isset($data['sku'])) {
            $implode[] = "sku = '" . $this->db->escape($data['sku']) . "'";
        }

        if (isset($data['location'])) {
            $implode[] = "location = '" . $this->db->escape($data['location']) . "'";
        }

        $status = 5;
        $quantity = $data['quantity'];
        if ($quantity > 0) {
            $status = 7;
        }
        if (isset($data['quantity'])) {
            $implode[] = "quantity = '" . $this->db->escape($quantity) . "'";
        }

        if (isset($data['minimum'])) {
            $implode[] = "minimum = '" . $this->db->escape($data['minimum']) . "'";
        }

        if (isset($data['subtract'])) {
            $implode[] = "subtract = '" . $this->db->escape($data['subtract']) . "'";
        }

        if (isset($status)) {
            $implode[] = "stock_status_id = '" . $this->db->escape($status) . "'";
        }

        if (isset($data['date_available'])) {
            $implode[] = "date_available = '" . $this->db->escape($data['date_available']) . "'";
        }

        if (isset($data['manufacturer_id'])) {
            $implode[] = "manufacturer_id = '" . $this->db->escape($data['manufacturer_id']) . "'";
        }

        if (isset($data['shipping'])) {
            $implode[] = "shipping = '" . $this->db->escape($data['shipping']) . "'";
        }

        if (isset($data['price'])) {
            $implode[] = "price = '" . $this->db->escape($data['price']) . "'";
        }

        if (isset($data['points'])) {
            $implode[] = "points = '" . $this->db->escape($data['points']) . "'";
        }

        if (isset($data['weight'])) {
            $implode[] = "weight = '" . $this->db->escape($data['weight']) . "'";
        }

        if (isset($data['weight_class_id'])) {
            $implode[] = "weight_class_id = '" . $this->db->escape($data['weight_class_id']) . "'";
        }

        if (isset($data['length'])) {
            $implode[] = "length = '" . $this->db->escape($data['length']) . "'";
        }

        if (isset($data['width'])) {
            $implode[] = "width = '" . $this->db->escape($data['width']) . "'";
        }

        if (isset($data['height'])) {
            $implode[] = "height = '" . $this->db->escape($data['height']) . "'";
        }

        if (isset($data['length_class_id'])) {
            $implode[] = "length_class_id = '" . $this->db->escape($data['length_class_id']) . "'";
        }

        if (isset($data['status'])) {
            $implode[] = "status = '" . $this->db->escape($data['status']) . "'";
        }

        if (isset($data['tax_class_id'])) {
            $implode[] = "tax_class_id = '" . $this->db->escape($data['tax_class_id']) . "'";
        }

        if (isset($data['sort_order'])) {
            $implode[] = "sort_order = '" . $this->db->escape($data['sort_order']) . "'";
        }

        if ($implode) {
            $sql .=  implode(" , ", $implode)." , " ;
        }

        $sql .= " date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'";

        $this->db->query($sql);

        if (isset($data['image'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "product SET image = '" . $this->db->escape(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE product_id = '" . (int)$product_id . "'");
        }
        $productName = '';
        if (isset($data['product_description'])) {
            foreach ($data['product_description'] as $language_id => $value) {
                $productName = $value['name'];
                $q = "UPDATE " . DB_PREFIX . "product_description SET name = '" . $this->db->escape($value['name']) . "', description = '" . $this->db->escape($value['description']) . "' WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "'";
                $this->db->query($q);
            }
        }
        if (isset($data['product_image'])) {
            $this->uploadProductImage($product_id, $productName, $data['product_image']);
        }

        if (isset($data['product_store'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        if (isset($data['product_attribute'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

            if (!empty($data['product_attribute'])) {
                foreach ($data['product_attribute'] as $product_attribute) {
                    if ($product_attribute['attribute_id']) {
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");

                        foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $this->db->escape($product_attribute_description['text']) . "'");
                        }
                    }
                }
            }
        }


        if (isset($data['product_option'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_option'] as $product_option) {
                if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");

                    $product_option_id = $this->db->getLastId();

                    if (isset($product_option['product_option_value'])  && count($product_option['product_option_value']) > 0 ) {
                        foreach ($product_option['product_option_value'] as $product_option_value) {
                            $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '" . (int)$product_option_value['product_option_value_id'] . "', product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$product_option_value['price'] . "', price_prefix = '" . $this->db->escape($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $this->db->escape($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $this->db->escape($product_option_value['weight_prefix']) . "'");

                            // To Add to Variant Merge Table
                            $check_option_value_data = $this->db->query("SELECT `product_option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$this->db->escape($product_id)."'")->rows;
                            if ($check_option_value_data){

                                foreach ($check_option_value_data as $option_values) {

                                    $check_merge = $this->db->query("SELECT * FROM `". DB_PREFIX ."erp_product_variant_merge` where opencart_product_option_id='".$option_values['product_option_value_id']."'")->row;
                                    if (!$check_merge){
                                        $this->db->query("INSERT INTO " . DB_PREFIX . "erp_product_variant_merge SET erp_product_id = '" . (int)$product_option_value['erp_product_id'] . "', opencart_product_option_id = '" . $option_values['product_option_value_id']. "', opencart_product_id = '" . $this->db->escape($product_id) . "',created_by = '".$this->db->escape($data['created_by'])."'");


                                    $merge_data[$product_option_value['erp_product_id']] = $option_values['product_option_value_id'];
                                    }
                                }
                            }
                        }
                    }else{
                        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '".$product_option_id."'");
                    }
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value = '" . $this->db->escape($product_option['option_value']) . "', required = '" . (int)$product_option['required'] . "'");
                }
            }
        }

        if (isset($data['product_discount'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_discount'] as $product_discount) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$product_discount['price'] . "', date_start = '" . $this->db->escape($product_discount['date_start']) . "', date_end = '" . $this->db->escape($product_discount['date_end']) . "'");
            }
        }

        if (isset($data['product_special'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_special'] as $product_special) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$product_special['price'] . "', date_start = '" . $this->db->escape($product_special['date_start']) . "', date_end = '" . $this->db->escape($product_special['date_end']) . "'");
            }
        }

        if (isset($data['product_download'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_download'] as $download_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
            }
        }

        if (isset($data['product_category'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_category'] as $category_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
            }
        }

        if (isset($data['product_filter'])) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_filter'] as $filter_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
            }
        }

        if (isset($data['product_related'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");

            foreach ($data['product_related'] as $related_id) {
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
                $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
            }
        }

        if (isset($data['product_reward'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_reward'] as $customer_group_id => $value) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$value['points'] . "'");
            }
        }

        if (isset($data['product_layout'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");

            foreach ($data['product_layout'] as $store_id => $layout) {
                if ($layout['layout_id']) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
                }
            }
        }

        if (isset($data['product_seo_url'])) {
            foreach ($data['product_seo_url'] as $store_id => $language) {
                foreach ($language as $language_id => $keyword) {
                    if (!empty($keyword)) {
                        $this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language_id . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
                    }
                }
            }
        }

        // if (isset($data['keyword']) AND $data['keyword']) {
        //
        //     $this->db->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id. "'");
        //
        //     $this->db->query("INSERT INTO " . DB_PREFIX . "url_alias SET query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($data['keyword']) . "'");
        // }

        $delete = $this->deleteExtraMapping($product_id);

        return array(
                    'product_id'=>$product_id,
                    'merge_data'=>$merge_data,
                );
    }

    public function deleteExtraMapping($product_id)
    {
        $existing_options = array();

        $query = $this->db->query("SELECT DISTINCT `product_option_value_id` FROM `" . DB_PREFIX . "product_option_value` WHERE product_id = '" . (int)$product_id . "'")->rows;
        foreach ($query as $option_data) {
            array_push($existing_options, $option_data['product_option_value_id']);
        }
        if ($existing_options) {
            $existing_options = join(", ", $existing_options);
            $delete_map = $this->db->query("DELETE  FROM `". DB_PREFIX ."erp_product_variant_merge` where opencart_product_id ='".(int)$product_id."' and opencart_product_option_id NOT IN (".$existing_options.")");
        }

    }

    public function editCategory($category_id, $data)
    {

        $sql = "UPDATE " . DB_PREFIX . "category SET ";

        $implode = array();

        if (isset($data['parent_id'])) {
            $implode[] = "parent_id = '" . $this->db->escape($data['parent_id']) . "'";

            // for set parent path for category
            // MySQL Hierarchical Data Closure Table Pattern
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE path_id = '" . (int)$category_id . "' ORDER BY level ASC");

            if ($query->rows) {
                foreach ($query->rows as $category_path) {
                    // Delete the path below the current one
                    $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_path['category_id'] . "' AND level < '" . (int)$category_path['level'] . "'");

                    $path = array();

                    // Get the nodes new parents
                    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

                    foreach ($query->rows as $result) {
                        $path[] = $result['path_id'];
                    }

                    // Get whats left of the nodes current path
                    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_path['category_id'] . "' ORDER BY level ASC");

                    foreach ($query->rows as $result) {
                        $path[] = $result['path_id'];
                    }

                    // Combine the paths with a new level
                    $level = 0;

                    foreach ($path as $path_id) {
                        $this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_path['category_id'] . "', `path_id` = '" . (int)$path_id . "', level = '" . (int)$level . "'");

                        $level++;
                    }
                }
            } else {
                // Delete the path below the current one
                $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_id . "'");

                // Fix for records with no paths
                $level = 0;

                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$data['parent_id'] . "' ORDER BY level ASC");

                foreach ($query->rows as $result) {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', level = '" . (int)$level . "'");

                    $level++;
                }

                $this->db->query("REPLACE INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', level = '" . (int)$level . "'");
            }
        }

            if (isset($data['top'])) {
                $implode[] = "top = '". (isset($data['top']) ? (int)$data['top'] : 0) . "'";
            }

        if (isset($data['column'])) {
            $implode[] = "column = '" . $this->db->escape($data['column']) . "'";
        }

        if (isset($data['status'])) {
            $implode[] = "status = '" . $this->db->escape($data['status']) . "'";
        }

        if (isset($data['sort_order'])) {
            $implode[] = "sort_order = '" . $this->db->escape($data['sort_order']) . "'";
        }

        if ($implode) {
            $sql .=  implode(" , ", $implode)." , " ;
        }

        $sql .= " date_modified = NOW() WHERE category_id = '" . (int)$category_id . "'";

        $this->db->query($sql);

        if (isset($data['image'])) {
            $this->db->query("UPDATE " . DB_PREFIX . "category SET image = '" . $this->db->escape(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE category_id = '" . (int)$category_id . "'");
        }

        if(isset($data['category_description'])){

            foreach ($data['category_description'] as $language_id => $value) {
                $query = "UPDATE " . DB_PREFIX . "category_description SET name = '" . $this->db->escape($value['name']) . "' WHERE category_id = '" . (int)$category_id . "' and  language_id = '" . (int)$language_id . "'";
                $this->db->query($query);
            }
        }

        if (isset($data['category_filter'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "category_filter WHERE category_id = '" . (int)$category_id . "'");

            foreach ($data['category_filter'] as $filter_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_filter SET category_id = '" . (int)$category_id . "', filter_id = '" . (int)$filter_id . "'");
            }
        }

        if (isset($data['category_store'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category_id . "'");

            foreach ($data['category_store'] as $store_id) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "'");
            }
        }

        if (isset($data['category_layout'])) {

            $this->db->query("DELETE FROM " . DB_PREFIX . "category_to_layout WHERE category_id = '" . (int)$category_id . "'");

            foreach ($data['category_layout'] as $store_id => $layout) {
                if ($layout['layout_id']) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_layout SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
                }
            }
        }

        // if (isset($data['keyword']) AND $data['keyword']) {
        //
        //     $this->db->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query = 'category_id=" . (int)$category_id. "'");
        //
        //     $this->db->query("INSERT INTO " . DB_PREFIX . "url_alias SET query = 'category_id=" . (int)$category_id . "', keyword = '" . $this->db->escape($data['keyword']) . "'");
        // }

        $this->cache->delete('category');
    }

    public function addOrderHistory($order_id, $data) {
        if(!isset($data['order_status_id']))
            return;
        // To send message to Customer
        $data['notify'] = 1;

        $check = $this->db->query("SELECT `opencart_order_status_id` from `" . DB_PREFIX . "erp_order_status_merge` where `erp_order_status_id`='".$data['order_status_id']."'")->row;
        if($check && $order_id){
            $data['order_status_id'] = $check['opencart_order_status_id'];

            $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$data['order_status_id'] . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");

            $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$data['order_status_id'] . "', notify = '" . (isset($data['notify']) ? (int)$data['notify'] : 0) . "', date_added = NOW()");
            return true;
        }else{
            return false;
        }
    }

    public function getProductOptionId($product_id) {
        $check = $this->db->query("SELECT `product_option_id` FROM `". DB_PREFIX ."product_option` where product_id='".$product_id."'")->row;
        if ($check)
            return $check['product_option_id'];
        else
            return '';
    }

    public function getProductOptionValueId($product_id, $option_value_id) {
        $check = $this->db->query("SELECT `product_option_value_id` FROM `". DB_PREFIX ."product_option_value` where product_id='".$product_id."' and option_value_id='".$option_value_id."' ")->row;
        if ($check)
            return $check['product_option_value_id'];
        else
            return '';
    }



    public function getOptionValues($option_id) {
        $option_value_data = array();

        $option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "option_value ov LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id = '" . (int)$option_id . "' AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ov.sort_order ASC");

        foreach ($option_value_query->rows as $option_value) {
            $option_value_data[] = array(
                'option_value_id' => $option_value['option_value_id'],
                'name'            => $option_value['name'],
                'image'           => $option_value['image'],
                'sort_order'      => $option_value['sort_order']
            );
        }

        return $option_value_data;
    }

    public function getProductOptions($product_id) {
        $product_option_data = array();

        $product_option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_option` po LEFT JOIN `" . DB_PREFIX . "option` o ON (po.option_id = o.option_id) LEFT JOIN `" . DB_PREFIX . "option_description` od ON (o.option_id = od.option_id) WHERE po.product_id = '" . (int)$product_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");

        foreach ($product_option_query->rows as $product_option) {
            $product_option_value_data = array();

            $product_option_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_option_value WHERE product_option_id = '" . (int)$product_option['product_option_id'] . "'");

            foreach ($product_option_value_query->rows as $product_option_value) {
                $product_option_value_data[] = array(
                    'product_option_value_id' => $product_option_value['product_option_value_id'],
                    'option_value_id'         => $product_option_value['option_value_id'],
                    'quantity'                => $product_option_value['quantity'],
                    'subtract'                => $product_option_value['subtract'],
                    'price'                   => $product_option_value['price'],
                    'price_prefix'            => $product_option_value['price_prefix'],
                    'points'                  => $product_option_value['points'],
                    'points_prefix'           => $product_option_value['points_prefix'],
                    'weight'                  => $product_option_value['weight'],
                    'weight_prefix'           => $product_option_value['weight_prefix']
                );
            }

            $product_option_data[] = array(
                'product_option_id'    => $product_option['product_option_id'],
                'product_option_value' => $product_option_value_data,
                'option_id'            => $product_option['option_id'],
                'name'                 => $product_option['name'],
                'type'                 => $product_option['type'],
                'value'                => $product_option['value'],
                'required'             => $product_option['required']
            );
        }

        return $product_option_data;
    }

    public function addOption($data) {
        // return $data;

        $this->db->query("INSERT INTO `" . DB_PREFIX . "option` SET type = '" . $this->db->escape($data['type']) . "', sort_order = '" . (int)$data['sort_order'] . "'");

        $option_id = $this->db->getLastId();

        foreach ($data['option_description'] as $language_id => $value) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "option_description SET option_id = '" . (int)$option_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
        }

        if (isset($data['option_value'])) {
            foreach ($data['option_value'] as $option_value) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "option_value SET option_id = '" . (int)$option_id . "', image = '" . $this->db->escape(html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8')) . "', sort_order = '" . (int)$option_value['sort_order'] . "'");

                $option_value_id = $this->db->getLastId();

                foreach ($option_value['option_value_description'] as $language_id => $option_value_description) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description SET option_value_id = '" . (int)$option_value_id . "', language_id = '" . (int)$language_id . "', option_id = '" . (int)$option_id . "', name = '" . $this->db->escape($option_value_description['name']) . "'");
                }
            }
        }
        return $option_id;
    }
}
?>