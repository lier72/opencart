<?php
/* 
Version: 1.0
Author: Artur Sułkowski
Website: http://artursulkowski.pl
*/

$config->set('product_tabs', unserialize(file_get_contents('admin/controller/module/product_tabs.json')));

class ThemeOptions {
	private $data = array();
    
    private $bestseller_products = null;
    private $latest_products = null;
    private $bestseller_limit = 10;
    private $latest_limit = 10;
	
	public function __construct($template, $store, $skin) {
		if(file_exists(DIR_TEMPLATE.$template.'/skins/store_'.$store.'/'.$skin.'/settings.json')) {
			$template = json_decode(file_get_contents(DIR_TEMPLATE.$template.'/skins/store_'.$store.'/'.$skin.'/settings.json'), true);
			foreach ($template as $option => $value) { 
				$this->data[$option] = $value;
			}
		}
		$this->data['store'] = $store;
		$this->data['skin']  = $skin;
	}
	
  	public function get($key, $array1 = '', $array2 = '', $array3 = '') {
  		if($key == 'payment') {
  			if(isset($this->data[$key])) {
				usort($this->data[$key], "cmp_by_optionNumber");
	  			return $this->data[$key];
  			}
  			return null;
  		} else {
	  		if($array1 != '' && $array2 != '' && $array3 != '') {
	  			return (isset($this->data[$key][$array1][$array2][$array3]) ? $this->data[$key][$array1][$array2][$array3] : null);
	  		} elseif($array1 != '' && $array2 != '') {
	    		return (isset($this->data[$key][$array1][$array2]) ? $this->data[$key][$array1][$array2] : null);
	    	} elseif($array1 != '') {
	    		return (isset($this->data[$key][$array1]) ? $this->data[$key][$array1] : null);
	    	} else {
	    		return (isset($this->data[$key]) ? $this->data[$key] : null);
	    	}
    	}
  	}
    
    public function getModel($name)
    {
  		global $registry;
  		return $registry->get($name);
    }
  	
  	public function compressorCodeCss($template, $files, $compressor_status, $http_server) {
  		if($compressor_status == 1 && is_writable('catalog/view/theme/' . $template . '/css')) {
	  		$file_cache = 'catalog/view/theme/' . $template . '/css/cache_css.css';
	  		$cache_life = 3600;
	  		
	  		if(!file_exists($file_cache) or (time() - filemtime($file_cache) >= $cache_life)){
	  			$buffer = "";
	  			foreach($files as $file) {
	  				$buffer .= file_get_contents($http_server . $file);
	  			}
	  			
	  			$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
	  			$buffer = str_replace(': ', ':', $buffer);
	  			$buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);
	  			
	  			file_put_contents($file_cache, $buffer);  
	  		}
	  		  		
	  		return '<link rel="stylesheet" type="text/css" href="catalog/view/theme/' . $template . '/css/cache_css.css" media="screen" />';
  		} else {
  			$output = '';
  			foreach($files as $file) {
  				$output .= '<link rel="stylesheet" type="text/css" href="' . $file . '" />';
  				$output .= "\n";
  			}
  			
  			return $output;
  		}
  	}
  	
  	public function compressorCodeJs($template, $files, $compressor_status, $http_server) {
  		if($compressor_status == 1 && is_writable('catalog/view/theme/' . $template . '/js')) {
	  		$file_cache = 'catalog/view/theme/' . $template . '/js/cache_js.js';
	  		$cache_life = 3600;
	  		
	  		if(!file_exists($file_cache) or (time() - filemtime($file_cache) >= $cache_life)){
	  			$buffer = "";
	  			foreach($files as $file) {
	  				$buffer .= file_get_contents($http_server . $file);
	  			}
	  			
				$buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
				$buffer = str_replace(': ', ':', $buffer);
	  			
	  			file_put_contents($file_cache, $buffer);  
	  		}
	  		  		
	  		return '<script type="text/javascript" src="catalog/view/theme/' . $template . '/js/cache_js.js"></script>';
  		} else {
  			$output = '';
  			foreach($files as $file) {
  				$output .= '<script type="text/javascript" src="' . $file . '"></script>';
  				$output .= "\n";
  			}
  			return $output;
  		}
  	}
  	
  	public function getDataProduct($product_id) {
  		global $registry;
  		
  		$output = array();
  		
  		// Registry
  		$product = $registry->get('model_catalog_product');
        $category_model = $registry->get('model_catalog_category');
        $registry->get('load')->language('product/product');
        $language = $registry->get('language');
        $link = $registry->get('url');
        $config = $registry->get('config');
  		
  		// Pobranie danych produktu
  		$result = $product->getProduct($product_id);
        $product_cat = $product->getCategories($product_id);
  		if($result) {
  		     $date_end = false;
  		     $db = $registry->get('db');
  		     $query = $db->query("SELECT date_end FROM " . DB_PREFIX . "product_special WHERE product_id='" . $product_id . "'");
  		     if ($query->num_rows) {
  		          $date_end = $query->row['date_end'];
  		     }
             $cats = array();
             if(!empty($product_cat) && $category_model){
                 foreach($product_cat as $cat){
                    $item = $category_model->getCategory($cat['category_id']);
                    $item['href'] = $link->link('product/category', 'path=' . $this->getCategoryPath($cat['category_id']) );
                    $cats[] = $item;
                 }
             }
             
            if ($result['quantity'] <= 0) {
				$result['stock'] = $result['stock_status'];
			} elseif ($config->get('config_stock_display')) {
				$result['stock'] = $result['quantity'];
			} else {
				$result['stock'] = $language->get('text_instock');
			}
            $result['text_stock'] = $language->get('text_stock');
             
             //var_dump($result);
             $output = array(
  				'description' => strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')),
  				'price' => $result['price'],
  				'stock' => $result['stock'],
  				'text_stock' => $result['text_stock'],
  				'special' => $result['special'],
                'is_available' => ($result['quantity'] > 0 ? true : false),
  				'date_end' => $date_end,
                'is_bestseller' => $this->isBestSellerProduct($product_id),
                'is_latest' => $this->isLatestProduct($product_id),
                'categories' => $cats,
                'manufacturer' => $result['manufacturer']
  			);
  		}	
  		
  		return $output;
  	}	
 
    public function getProductImages($product_id, $image_width_thumb = false, $image_height_thumb = false, $image_width_large = false, $image_height_large = false)
    {
        global $registry;
  		
  		// Registry
  		$product = $registry->get('model_catalog_product');
        $config = $registry->get('config');
  		$model_image = $registry->get('model_tool_image');
        
        if(!$image_width_thumb){
            $image_width_thumb = $config->get('config_image_additional_width');
        }
        if(!$image_height_thumb){
            $image_height_thumb = $config->get('config_image_additional_height');
        }
        
        if(!$image_width_thumb){
            $image_width_thumb = $config->get('config_image_additional_width');
        }
        if(!$image_height_thumb){
            $image_height_thumb = $config->get('config_image_additional_height');
        }
        
        if(!$image_width_large){
            $image_width_large = $config->get('config_image_popup_width');
        }
        if(!$image_height_large){
            $image_height_large = $config->get('config_image_popup_height');
        }
        
        $data['images'] = array();

        $results = $product->getProductImages($product_id);

        foreach ($results as $result) {
            $data['images'][] = array(
                'large' => $model_image->resize($result['image'], $image_width_large, $image_height_large),
                'thumb' => $model_image->resize($result['image'], $image_width_thumb, $image_height_thumb)
            );
        }
        return $data['images'];
    }   

  	
  	public function productImageSwap($product_id, $image_width, $image_height) {
  		global $registry;
  		
  		// Registry
  		$product = $registry->get('model_catalog_product');
  		$model_image = $registry->get('model_tool_image');
  		
  		// Pobranie danych produktu
  		$result = $product->getProductImages($product_id);
  		if($result && $image_width > 0 && $image_height > 0) {
  			foreach($result as $image) return $model_image->resize($image['image'], $image_width, $image_height);
  		}
  		
  		return false;
  	}
  	
  	public function productImageThumb($product_id, $image_width, $image_height) {
  		global $registry;
  		
  		// Registry
  		$product = $registry->get('model_catalog_product');
  		$model_image = $registry->get('model_tool_image');
  		
  		// Pobranie danych produktu
  		$result = $product->getProduct($product_id);
  		if($result && $image_width > 0 && $image_height > 0) return $model_image->resize($result['image'], $image_width, $image_height);
  		
  		return false;
  	}
  	
  	
  	public function refineSearch() {
  		global $loader, $registry;
  		
  		$output = array();
  		
  		// Load model
  		$loader->model('catalog/category');
  		
  		// Registry
  		$model = $registry->get('model_catalog_category');
  		$product = $registry->get('model_catalog_product');
  		$get = $registry->get('request');
  		$link = $registry->get('url');
  		$config = $registry->get('config');
  		
  		// Pobranie id kategorii
  		$parts = explode('_', (string)$get->get['path']);
  		$category_id = (int)array_pop($parts);
  		
  		$url = '';
  		if (isset($get->get['filter'])) {
  			$url .= '&filter=' . $get->get['filter'];
  		}	
  								
  		if (isset($get->get['sort'])) {
  			$url .= '&sort=' . $get->get['sort'];
  		}	
  		
  		if (isset($get->get['order'])) {
  			$url .= '&order=' . $get->get['order'];
  		}	
  					
  		if (isset($get->get['limit'])) {
  			$url .= '&limit=' . $get->get['limit'];
  		}
  		
  		// Pobranie Refine Search  		
  		$results = $model->getCategories($category_id);
  		foreach ($results as $result) {
  			$data = array(
  				'filter_category_id'  => $result['category_id'],
  				'filter_sub_category' => true
  			);
  			
  			$product_total = $product->getTotalProducts($data);		
  			
  			$output[] = array(
  				'thumb' => $result['image'],
  				'name'  => $result['name'] . ($config->get('config_product_count') ? ' (' . $product_total . ')' : ''),
  				'href'  => $link->link('product/category', 'path=' . $get->get['path'] . '_' . $result['category_id'] . $url)
  			);
  		}
  		
  		return $output;
  	}
  	
  	public function getCategories($category_id) {
  		global $registry;
  		
  		$config = $registry->get('config');
  		$link = $registry->get('url');
  		$get = $registry->get('request');
  		$model_products = $registry->get('model_catalog_product');
  		$model_categories = $registry->get('model_catalog_category');
  		$get_categories = $model_categories->getCategories($category_id);
  		$categories = array();
  		
  		foreach ($get_categories as $category) {
			$filter_data = array('filter_category_id' => $category['category_id'], 'filter_sub_category' => true);

			$categories[] = array(
				'category_id' => $category['category_id'], 
				'name' => $category['name'] . ($config->get('config_product_count') ? ' (' . $model_products->getTotalProducts($filter_data) . ')' : ''), 
				'label' => $category['name'],
				'href' => $link->link('product/category', 'path=' . $category_id . '_' . $category['category_id'])
			);
  		}
  		
  		return $categories;
  	}
    
    public function getCategoryPath($category_id){
        global $registry;
        $db = $registry->get('db');
        
        $category = $db->query("SELECT * FROM " . DB_PREFIX . "category c WHERE c.category_id = " .(int)($category_id));
        if($category->row['parent_id'] != 0){
               return $this->getCategoryPath($category->row['parent_id']) . '_' . $category_id;
        }
        return $category_id;
   }
    
    public function getCurrentCategory() {
        global $registry;
        
        $get = $registry->get('request');
 		if (isset($get->get['category_id'])) {
			$category_id = $get->get['category_id'];
		} else {
			$category_id = 0;
		} 
        return $category_id;
    }
  	
  	public function getAllCategories() {
  		global $registry;
  		
  		$model_categories = $registry->get('model_catalog_category');
        
        
		$data['categories'] = array();

		$categories_1 = $model_categories->getCategories(0);

		foreach ($categories_1 as $category_1) {
			$level_2_data = array();

			$categories_2 = $model_categories->getCategories($category_1['category_id']);

			foreach ($categories_2 as $category_2) {
				$level_3_data = array();

				$categories_3 = $model_categories->getCategories($category_2['category_id']);

				foreach ($categories_3 as $category_3) {
					$level_3_data[] = array(
						'category_id' => $category_3['category_id'],
						'name'        => $category_3['name'],
					);
				}

				$level_2_data[] = array(
					'category_id' => $category_2['category_id'],
					'name'        => $category_2['name'],
					'children'    => $level_3_data
				);
			}

			$data['categories'][] = array(
				'category_id' => $category_1['category_id'],
				'name'        => $category_1['name'],
				'children'    => $level_2_data
			);
		}
  		
  		return $data['categories'];
  	}
    
    
  	
  	public function getCart() {
  		global $loader, $registry;
  		
  		$output = array();
  		  		
  		// Registry
  		$cart = $registry->get('cart');
  		$session = $registry->get('session');
  		$currency = $registry->get('currency');
  		$config = $registry->get('config');
  		$customer = $registry->get('customer');
  		$model_setting = $registry->get('model_extension_extension');
  		
  		$total_data = array();					
  		$total = 0;
  		$taxes = $cart->getTaxes();
  		
		// Display prices
		if (($config->get('config_customer_price') && $customer->isLogged()) || !$config->get('config_customer_price')) {
			$sort_order = array(); 
			
			$results = $model_setting->getExtensions('total');
			foreach ($results as $result) {
				if ($config->get($result['code'] . '_status')) {
					$model_total = $registry->get('model_total_' . $result['code']);
					$model_total->getTotal($total_data, $total, $taxes);
				}	
			}
		}		
  		  		 
  		$output['total_item'] = $cart->countProducts() + (isset($session->data['vouchers']) ? count($session->data['vouchers']) : 0);
  		$output['total_price'] = $currency->format($total);
  		
  		return $output;
  	}
    
    public function getCompareCount()
    {  		
        global $registry;
  		// Registry
  		$session = $registry->get('session');
        return (isset($session->data['compare']) ? count($session->data['compare']) : 0);
    }
  	
  	public function productIsEnquiry($product_id) {
  	     global $registry, $loader;
  	     $output = false;
  	     
  	     $config = $registry->get('config');
          $modules = $config->get('product_blocks_module');
          
          foreach($modules as $module) {
               if(($module['position'] == 'product_enquiry' && $module['status'] == 1) && ($module['layout_id'] == 99999 || $module['layout_id'] == '99999' . $config->get( 'config_store_id' ))) {
                    $status = false;
                    
                    if($module['show_on_products_from'] == 'all') $status = true;
                    		
                    if($module['show_on_products_from'] == 'products') {
                         $products = explode(',', $module['products']);
                         foreach ($products as $product) {
                              if($product == $product_id) $status = true;
                         }
                    }
                    
                    if($module['show_on_products_from'] == 'categories') {

                         $loader->model('catalog/products');
                         $model_catalog_products = $registry->get('model_catalog_products');
                         $category_id = $model_catalog_products->getCategoryId($product_id);
                         
                         $categories = explode(',', $module['categories']);
                         foreach ($categories as $category) {
                              if($category == $category_id['category_id']) $status = true;
                         }
                    }
                    
                    if($status) {
                         if(isset($module['block_name'][$config->get('config_language_id')])) {
                         	$block_name = html_entity_decode($module['block_name'][$config->get('config_language_id')], ENT_QUOTES, 'UTF-8');
                         } else {
                         	$block_name = 'Request a qoute!';
                         }
                         
                         if(!isset($module['popup_module'])) $module['popup_module'] = 0;
                         
                         $output = array(
                              'block_name' => $block_name,
                              'icon' => $module['icon'],
                              'icon_position' => $module['icon_position'],
                              'popup_module' => $module['popup_module']
                         );
                    }
               }
          }
  		
  		return $output;
  	}
    
    /**
     * Sprawdza czy produkt o podanym ID jest bestsellerem
     * @param int $product_id ID produktu
     * @return boolean Prawda jeśli jest, falsz w przeciwnym razie
     */
    public function isBestSellerProduct($product_id)
    {
        global $registry;
        // Registry
  		$product = $registry->get('model_catalog_product');
        $category_model = $registry->get('model_catalog_category');
        
        if(array_key_exists($product_id, $this->getBestsellerProducts($product_id))){
            return true;
        }
        return false;
    }
    
    /**
     * Sprawdza czy produkt o podanym ID jest bestsellerem
     * @param int $product_id ID produktu
     * @return boolean Prawda jeśli jest, falsz w przeciwnym razie
     */
    public function isLatestProduct($product_id)
    {
        if(array_key_exists($product_id, $this->getLatestProducts($product_id))){
            return true;
        }
        return false;
    }
    
    /**
     * 
     * @param int $limit Limit produktów, określa zakres bestsellerów
     * @return void 
     */
    public function getBestsellerProducts()
    {
        global $registry;
        // Registry
  		$product = $registry->get('model_catalog_product');
        
        if(null === $this->bestseller_products){
            $this->bestseller_products = $product->getBestSellerProducts($this->bestseller_limit);
        }
        
        return $this->bestseller_products;
    }
    /**
     * 
     * @param int $limit Limit produktów, określa zakres bestsellerów
     * @return void 
     */
    public function getLatestProducts()
    {
        global $registry;
        // Registry
  		$product = $registry->get('model_catalog_product');
        
        if(null === $this->latest_products){
            $this->latest_products = $product->getLatestProducts($this->latest_limit);
        }
        
        return $this->latest_products;
    }
}

function cmp_by_optionNumber($a, $b) {
	return $a["sort"] - $b["sort"];
}

?>