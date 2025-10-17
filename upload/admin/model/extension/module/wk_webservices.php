<?php
################################################################################################
#  for Opencart 2.3.x.x. From webkul http://webkul.com   	       #
################################################################################################
class ModelExtensionModulewkwebservices extends Model {

	public function createTableJobWebServices(){
         $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "api_user (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user` varchar(500) NOT NULL,
          `firstname` varchar(500) NOT NULL,
          `lastname` varchar(500) NOT NULL,
          `email` varchar(500) NOT NULL,
          `user_key` varchar(250) NOT NULL ,
          `key_description` varchar(5000) NOT NULL ,
          `date_created` varchar(500) NOT NULL ,
          `date_updated` varchar(500) NOT NULL ,
          `status` varchar (50) NOT NULL,
          PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8 ;");

          $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "api_keys (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` varchar(500) NOT NULL,
          `date_created` varchar(500) NOT NULL,
          `Auth_key` varchar(500) NOT NULL,
          PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8 ;");

          $this->db->query("CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "odoo_confg (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` int(100) NOT NULL,
          `date_created` varchar(500) NOT NULL,
          `url` varchar(500) NOT NULL,
          `port` int(100) NOT NULL,
          `db_name` varchar(500) NOT NULL,
          `wkproducttype` varchar(500) NOT NULL,
          `user` varchar(500) NOT NULL,
          `password` varchar(500) NOT NULL,
          `warehouse` varchar(500) NOT NULL,
          `status` text NOT NULL,
           PRIMARY KEY (`id`) ) DEFAULT CHARSET=utf8 ;");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_category_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_category_id` int(11) NOT NULL,
            `opencart_category_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
           `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_tax_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_tax_id` int(11) NOT NULL,
            `opencart_tax_id` int(11) NOT NULL,
            `rate` int(100) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_currency_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_currency_id` int(11) NOT NULL,
            `opencart_currency_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");


          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_carrier_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_carrier_id` int(11) NOT NULL,
            `opencart_carrier_cod` varchar(500) NOT NULL,
            `name` varchar(500) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");


          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_payment_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_payment_id` int(11) NOT NULL,
            `opencart_payment_cod` varchar(500) NOT NULL,
            `name` varchar(500) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_customer_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_customer_id` int(11) NOT NULL,
            `opencart_customer_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_address_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_address_id` int(11) NOT NULL,
            `opencart_address_id` int(11) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_product_template_merge (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `opencart_product_id` int(11) NOT NULL,
          `erp_template_id` int(11)NOT NULL,
          `created_by` varchar(255) NOT NULL,
          `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `is_synch` tinyint(1) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`)
          ) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8");


          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_order_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_order_id` int(11) NOT NULL,
            `customer_id` int(11) NOT NULL,
            `opencart_order_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
             PRIMARY KEY (`id`)
          )  ENGINE=MyISAM DEFAULT CHARSET=utf8");


          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_order_status_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_order_status_id` varchar(11) NOT NULL,
            `opencart_order_status_id` int(11) NOT NULL,
            `opname` varchar(500) NOT NULL,
            `erpname` varchar(500) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");


          $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_product_variant_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_product_id` int(11) NOT NULL,
            `opencart_product_id` int(11) NOT NULL,
            `opencart_product_option_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");


           $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_product_option_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_option_id` int(11) NOT NULL,
            `opencart_option_id` int(11) NOT NULL,
            `created_by` varchar(255) NOT NULL,
            `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

           $this->db->query("CREATE TABLE IF NOT EXISTS ".DB_PREFIX."erp_product_option_value_merge (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `erp_option_value_id` int(11) NOT NULL,
            `opencart_option_value_id` int(11) NOT NULL,
            `option_id` int(11) NOT NULL,
            `is_synch` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
        }

        /**
         * [saveImageToProduct function for save image]
         * @param  array  $data [description]
         * @return [type]       [description]
         */
        public function saveImageToProduct($data = array()){
          if(isset($data['img_path']) && $data['img_path']){
            $getEntry = $this->db->query("SELECT product_id FROM ".DB_PREFIX."product WHERE product_id = '".(int)$data['product_id']."'")->row;

            if(isset($getEntry['product_id']) && $getEntry['product_id']){
              $this->db->query("UPDATE ".DB_PREFIX."product SET `image` = '".$this->db->escape($data['img_path'])."' WHERE product_id = '".(int)$data['product_id']."' ");
            }
          }
        }
}
?>