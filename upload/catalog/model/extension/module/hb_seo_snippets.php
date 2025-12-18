<?php
class ModelExtensionModuleHbSeoSnippets extends Model {	
	public function get_stock_status_id($product_id) {
		$query = $this->db->query("SELECT stock_status_id FROM ".DB_PREFIX."product WHERE product_id = '".(int)$product_id."'");
		if ($query->row) {
			return $query->row['stock_status_id'];
		}else {
			return '0';
		}
	}
	
	public function product_sd($product_info, $data) {		
		if ($this->config->get('hb_snippets_prod_enable')) {
			$review_count 				= $product_info['reviews'];
			$currencycode 				= $this->config->get('config_currency');
			$hb_snippets_prod_enable 	= $this->config->get('hb_snippets_prod_enable');
			$hb_snippets_bc_enable 		= $this->config->get('hb_snippets_bc_enable');
			
			if ($this->config->get('hb_snippets_description') == 'description') {
				$description = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", htmlentities(strip_tags($data['description']))); 
			}else{
				$description = $product_info['meta_description'];
			}
			
			$description = preg_replace('/\s{2,}/', ' ', trim($description));
			
			$product_id = $product_info['product_id'];
			$name  		= $product_info['name'];
			$brand 		= $product_info['manufacturer'];
			$model 		= $product_info['model'];
			$url		= $this->url->link('product/product','product_id='.$product_id);
			
			if ((float)$product_info['special']) {
				$price = (float)$product_info['special'];
			}else{
				$price = (float)$product_info['price'];
			}
			
			$actual_price = (float)$product_info['price'];
			
			if ($this->config->get('hb_snippets_incl_tax')) {
				$price = $this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax'));
				$actual_price = $this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax'));
			}
			
			$formatted_price =  $this->currency->format($price, $currencycode);
			
			if ($product_info['quantity'] > 0){
				$availability = 'http://schema.org/InStock';
			}else{
				$stock_status_id = $this->get_stock_status_id($product_id);
				if ($this->config->get('hb_snippets_stock')) {
					$availability = $this->config->get('hb_snippets_stock');
					$availability = 'http://schema.org/'.$availability[$stock_status_id];
				}else{
					$availability = 'http://schema.org/OutOfStock';
				}
			}
			
			$code = '';
			$code .= '<script type="application/ld+json">';
			$code .= '{';
			$code .= '"@context": "http://schema.org/",';
			$code .= '"@type": "Product",';
			$code .= '"name": "'.str_replace('"','',$data['heading_title']).'",';
			
			$images = array();
			if ($product_info['image']) {
				$images[] = '"'.$data['popup'].'"';
			}
			
			if (!empty($data['images'])) {
				foreach ($data['images'] as $image) {
					$images[] = '"'.$image['popup'].'"';
				}
			}
			
			if (!empty($images)) {
				$images = implode(',',$images);
				$code .= '"image": [';
				$code .= $images;
				$code .= ' ],';
			}
			
			$code .= '"description": "'.$description.'",';
			
			$code .= '"productID": "'.$product_id.'",';
			
			if ($product_info['sku']) {
				$code .= '"sku": "'.$product_info['sku'].'",';
			}else{
				$code .= '"sku": "'.$product_id.'",';
			}
			
			if ($product_info['mpn']) {
				$code .= '"mpn": "'.$product_info['mpn'].'",';
			}else{
				$code .= '"mpn": "'.$product_id.'",';
			}
			
			if ($data['manufacturer']) {
				$code .= '"brand": { "@type": "Brand", "name": "'.$brand.'" },';
			}else {
				$code .= '"brand": { "@type": "Brand", "name": "'.$this->config->get('hb_snippets_brand').'" },';
			}
			
			if ($data['rating'] and $review_count > 0) {
				$code .= '"aggregateRating": { "@type": "AggregateRating", "ratingValue": "'.$data['rating'].'", "reviewCount": "'.$review_count.'" },';
			}
			/******* PROPERTY OFFERS : START ********/
			$code .= '"offers": {"@type": "Offer", "priceCurrency": "'.$currencycode.'", "price": "'.$price.'",';
			if ($this->config->get('hb_snippets_pricevalid')) {
				$pricedate_query = $this->db->query("SELECT date_end FROM `".DB_PREFIX."product_special` WHERE product_id = '".(int)$product_id."' AND customer_group_id = '".(int)$this->config->get('config_customer_group_id')."' AND date_end > now() ORDER BY priority ASC LIMIT 1");
				
				if ($pricedate_query->row) {
					$price_date = date('Y-m-d',strtotime($pricedate_query->row['date_end']));
				}else{
					$price_date = $this->config->get('hb_snippets_pricevaliddate');
				}
				
				$code .= '"priceValidUntil": "'.$price_date.'",';
			}
			$code .= '"availability": "'.$availability.'", "url":"'.$url.'" }';
			/*******PROPERTY OFFERS : END ********/
			
			/******* PROPERTY REVIEW : START ********/
			$review_query = $this->db->query("SELECT * FROM `".DB_PREFIX."review` WHERE product_id = '".(int)$product_id."' AND status = 1");
			if ($review_query->rows) {
				$reviews = $review_query->rows;
				
				foreach ($reviews as $rev) {
					$review_data[] = '{"@type": "Review", "author": {"@type": "Person", "name": "'.$rev['author'].'"}, "datePublished": "'.date('Y-m-d', strtotime($rev['date_added'])).'", "description": "'.htmlentities($rev['text']).'", "reviewRating": {"@type": "Rating","bestRating": "5","ratingValue": "'.$rev['rating'].'","worstRating": "1"}}';
				}
				$review_data = implode(',',$review_data);
				
				$code .= ',"review": [';
				$code .= $review_data;
				$code .= ']';
			}
			/******* PROPERTY REVIEW : END********/
			$code .= '}';
			$code .= "</script>";
		} else {
			$code = '';
		}
		//OPEN GRAPH
		if ($this->config->get('hb_snippets_og_enable')){
			$hb_snippets_ogp = $this->config->get('hb_snippets_ogp');
			if (strlen($hb_snippets_ogp) > 4){				
				$hb_snippets_ogp = str_replace('{name}',$name,$hb_snippets_ogp);
				$hb_snippets_ogp = str_replace('{model}',$model,$hb_snippets_ogp);
				$hb_snippets_ogp = str_replace('{brand}',$brand,$hb_snippets_ogp);
				$hb_snippets_ogp = str_replace('{price}',$price,$hb_snippets_ogp);
			}else{
				$hb_snippets_ogp = $name;
			}
			
			if (strlen($this->config->get('hb_snippets_og_id')) > 5 ){
			    $this->document->setOpengraph('fb:app_id', $this->config->get('hb_snippets_og_id'));
			}
			$this->document->setOpengraph('og:title', $hb_snippets_ogp);
            $this->document->setOpengraph('og:type', 'product');
			$this->document->setOpengraph('og:site_name', $this->config->get('config_name'));
			
			$this->load->model('tool/image');
			if ($product_info['image']) {
				$snippet_thumb = $this->model_tool_image->resize($product_info['image'], $this->config->get('hb_snippets_og_piw'), $this->config->get('hb_snippets_og_pih'));
				$this->document->setOpengraph('og:image', $snippet_thumb);
			    $this->document->setOpengraph('og:image:width', $this->config->get('hb_snippets_og_piw'));
			    $this->document->setOpengraph('og:image:height', $this->config->get('hb_snippets_og_pih'));
			} 
			
			$this->document->setOpengraph('og:url', $this->url->link('product/product', 'product_id=' . $product_id));
            $this->document->setOpengraph('og:description', $description);
			
			if (!empty($data['images'])) {
				foreach ($data['images'] as $additional_image){
					$this->document->setOpengraph('og:image', $additional_image['popup']);	
					$this->document->setOpengraph('og:image:width', $this->config->get('hb_snippets_og_piw'));
					$this->document->setOpengraph('og:image:height', $this->config->get('hb_snippets_og_pih'));
				}
			}
			
			if ((float)$product_info['special']) {
				$this->document->setOpengraph('product:sale_price:amount', $price);
				$this->document->setOpengraph('product:sale_price:currency', $currencycode);
				$this->document->setOpengraph('product:original_price:amount', $actual_price);
				$this->document->setOpengraph('product:original_price:currency', $currencycode);
			} else {
				$this->document->setOpengraph('product:original_price:amount', $price);
				$this->document->setOpengraph('product:original_price:currency', $currencycode);
			}

			if ($product_info['quantity'] > 0){
				$this->document->setOpengraph('og:availability', 'instock');
			} else {
				 $this->document->setOpengraph('og:availability', 'oos');
			}
			
			if (!empty($data['products'])) {
				foreach ($data['products'] as $product){
					$this->document->setOpengraph('og:see_also', $product['href']);
				}
			}
		}
		//TWITTER CARDS
		if ($this->config->get('hb_snippets_tc_enable')){
			$hb_snippets_tcp = $this->config->get('hb_snippets_tcp');
			if (strlen($hb_snippets_tcp) > 4){				
				$hb_snippets_tcp = str_replace('{name}',$name,$hb_snippets_tcp);
				$hb_snippets_tcp = str_replace('{model}',$model,$hb_snippets_tcp);
				$hb_snippets_tcp = str_replace('{brand}',$brand,$hb_snippets_tcp);
				$hb_snippets_tcp = str_replace('{price}',$price,$hb_snippets_tcp);
			}else{
				$hb_snippets_tcp = $name;
			}
			
			$this->document->setTwittercard('twitter:card', 'summary_large_image');
			$this->document->setTwittercard('twitter:site', $this->config->get('hb_snippets_tc_username'));
			$this->document->setTwittercard('twitter:title', $hb_snippets_tcp);
			$this->document->setTwittercard('twitter:description', $description);
			if ($product_info['image']) {
			    $this->document->setTwittercard('twitter:image', $data['popup']);
			}
		}
		
		$this->document->setStructureddata($code);
	}
	
	public function category_social($category_info){
		$this->load->model('tool/image');
		if ($this->config->get('hb_snippets_og_enable')){
			$hb_snippets_ogc = $this->config->get('hb_snippets_ogc');
			if (strlen($hb_snippets_ogc) > 4){
				$ogc_name = $category_info['name'];
				$hb_snippets_ogc = str_replace('{name}',$ogc_name,$hb_snippets_ogc);
			}else{
				$hb_snippets_ogc = $category_info['name'];
			}
			
			if (strlen($this->config->get('hb_snippets_og_id')) > 5 ){
			    $this->document->setOpengraph('fb:app_id', $this->config->get('hb_snippets_og_id'));
			}
			$this->document->setOpengraph('og:title', $hb_snippets_ogc);
            $this->document->setOpengraph('og:type', 'product.group');
			$this->document->setOpengraph('og:site_name', $this->config->get('config_name'));
			$this->document->setOpengraph('og:url', $this->url->link('product/category', 'path=' . $category_info['category_id']));
			if ($category_info['image']) {
				$image = $this->model_tool_image->resize($category_info['image'], $this->config->get('hb_snippets_og_ciw'), $this->config->get('hb_snippets_og_cih'));
				$this->document->setOpengraph('og:image', $image);
				$this->document->setOpengraph('og:image:width', $this->config->get('hb_snippets_og_ciw'));
				$this->document->setOpengraph('og:image:height', $this->config->get('hb_snippets_og_cih'));
			}
			$this->document->setOpengraph('og:description', $category_info['meta_description']);
		}
		
		//TWITTER CARDS
		if ($this->config->get('hb_snippets_tc_enable')){
			$hb_snippets_tcc = $this->config->get('hb_snippets_tcc');
			if (strlen($hb_snippets_tcc) > 4){
				$tcc_name = $category_info['name'];
				$hb_snippets_tcc = str_replace('{name}',$tcc_name,$hb_snippets_tcc);
			}else{
				$hb_snippets_tcc = $category_info['name'];
			}
			
			$this->document->setTwittercard('twitter:card', 'summary_large_image');
			$this->document->setTwittercard('twitter:site', $this->config->get('hb_snippets_tc_username'));
			$this->document->setTwittercard('twitter:title', $hb_snippets_tcc);
			$this->document->setTwittercard('twitter:description', $category_info['meta_description']);
			if ($category_info['image']) {
				$image = $this->model_tool_image->resize($category_info['image'], $this->config->get('hb_snippets_og_ciw'), $this->config->get('hb_snippets_og_cih'));
			    $this->document->setTwittercard('twitter:image', $image);
			}
		}
	}
	
	public function information_social($information_info){
		if ($this->config->get('hb_snippets_og_enable')){
			if (strlen($this->config->get('hb_snippets_og_id')) > 5 ){
				$this->document->setOpengraph('fb:app_id', $this->config->get('hb_snippets_og_id'));
			}
			$this->document->setOpengraph('og:title', $information_info['title']);
			$this->document->setOpengraph('og:type', 'website');
			$this->document->setOpengraph('og:site_name', $this->config->get('config_name'));
			if ($this->config->get('hb_snippets_og_img')) {
				$this->document->setOpengraph('og:image', $this->config->get('config_url') . 'image/' . $this->config->get('hb_snippets_og_img'));
				$this->document->setOpengraph('og:image:width', $this->config->get('hb_snippets_og_diw'));
				$this->document->setOpengraph('og:image:height', $this->config->get('hb_snippets_og_dih'));
			}
			$this->document->setOpengraph('og:url', $this->url->link('information/information', 'information_id=' .  $information_info['information_id']));
			$this->document->setOpengraph('og:description', $information_info['meta_description']);
		}
		
		//TWITTER CARDS
		if ($this->config->get('hb_snippets_tc_enable')){
			$this->document->setTwittercard('twitter:card', 'summary_large_image');
			$this->document->setTwittercard('twitter:site', $this->config->get('hb_snippets_tc_username'));
			$this->document->setTwittercard('twitter:title', $information_info['title']);
			$this->document->setTwittercard('twitter:description', $information_info['meta_description']);
			if ($this->config->get('hb_snippets_og_img')) {
				$this->document->setTwittercard('twitter:image', $this->config->get('config_url') . 'image/' . $this->config->get('hb_snippets_og_img'));
			}
			
		}
	}
	
	public function home_social(){
		$this->load->model('tool/image');
		if ($this->config->get('hb_snippets_og_enable')){
			if (strlen($this->config->get('hb_snippets_og_id')) > 5 ){
			        $this->document->setOpengraph('fb:app_id', $this->config->get('hb_snippets_og_id'));
			    }
				$this->document->setOpengraph('og:title', $this->config->get('config_meta_title'));
				$this->document->setOpengraph('og:type', 'website');
				$this->document->setOpengraph('og:site_name', $this->config->get('config_name'));
				if ($this->config->get('hb_snippets_og_img')) {
					$this->document->setOpengraph('og:image', $this->config->get('config_url') . 'image/' . $this->config->get('hb_snippets_og_img'));
					$this->document->setOpengraph('og:image:width', $this->config->get('hb_snippets_og_diw'));
					$this->document->setOpengraph('og:image:height', $this->config->get('hb_snippets_og_dih'));
				}
				$this->document->setOpengraph('og:url', $this->config->get('config_url'));
				$this->document->setOpengraph('og:description', $this->config->get('config_meta_description'));
		}
		
		//TWITTER CARDS
		if ($this->config->get('hb_snippets_tc_enable')){
			$this->document->setTwittercard('twitter:card', 'summary_large_image');
			$this->document->setTwittercard('twitter:site', $this->config->get('hb_snippets_tc_username'));
			$this->document->setTwittercard('twitter:title', $this->config->get('config_meta_title'));
			$this->document->setTwittercard('twitter:description', $this->config->get('config_meta_description'));
			if ($this->config->get('hb_snippets_og_img')) {
				$this->document->setTwittercard('twitter:image', $this->config->get('config_url') . 'image/' . $this->config->get('hb_snippets_og_img'));
			}
		}
	}
	
	public function breadcrumbs_sd($breadcrumbs) {		
		if ($this->config->get('hb_snippets_bc_enable')) {
			$code = '';
			$code .= '<script type="application/ld+json">';
			$code .= '{';
			$code .= '"@context": "http://schema.org/",';
			$code .= '"@type": "BreadcrumbList",';
			
			$bclist = array();
			if (!empty($breadcrumbs)) {
				$i = 1;
				foreach ($breadcrumbs as $bc) {
					if ($i == 1){
						$text = 'Home';
					}else{
						$text = $bc['text'];
					}
					$bclist[] = '{"@type": "ListItem", "position": '.$i.', "name": "'.$text.'", "item": "'.$bc['href'].'" }';
					$i++;
				}
			}
			
			if (!empty($bclist)) {
				$bclist = implode(',',$bclist);
				$code .= '"itemListElement": [';
				$code .= $bclist;
				$code .= ' ]';
			}
			
			$code .= '}';
			$code .= "</script>";
		} else {
			$code = '';
		}
		
		$this->document->setStructureddata($code);
	}
	
	public function local_business() {		
		if ($this->config->get('hb_snippets_local_enable')) {
			$code = html_entity_decode($this->config->get('hb_snippets_local_snippet'), ENT_QUOTES, 'UTF-8');
		} else {
			$code = '';
		}
		
		$this->document->setStructureddata($code);
	}
	
	public function knowledge_graph() {		
		if ($this->config->get('hb_snippets_kg_enable')) {
			$code = html_entity_decode($this->config->get('hb_snippets_local_snippet'), ENT_QUOTES, 'UTF-8');
			$store_id = (int)$this->config->get('config_store_id');
			
			if ($this->config->get('config_url')){
				$store_url = $this->config->get('config_url');
			}else{
				$store_url = HTTPS_SERVER;
			}
			
			$code = '';
			$code .= '<script type="application/ld+json">';
			$code .= '{';
			$code .= '"@context": "http://schema.org/",';
			$code .= '"@type": "Organization",';
			$code .= '"name": "'.$this->config->get('config_name').'",';
			$code .= '"url": "'.$store_url.'"';
			
			if ($this->config->get('hb_snippets_logo')) {
				if ($this->config->get('hb_snippets_logo') <> '') {
					$store_logo = $store_url . 'image/' . $this->config->get('hb_snippets_logo');
					$code .= ',"logo": "'.$store_logo.'"';
				}
			}
			
			if ($this->config->get('hb_snippets_contact')) {
				$contacts = $this->config->get('hb_snippets_contact');
				foreach ($contacts as $contact) {
					$c[] = '{"@type": "ContactPoint","telephone": "'.$contact['n'].'","contactType": "'.$contact['t'].'"}';
				}
				$contact_info = implode(',', $c);
				$code .= ',"contactPoint": [ '.$contact_info.' ]';
			}
			
			if ($this->config->get('hb_snippets_socials')) {
				$socials = $this->config->get('hb_snippets_socials');
				foreach ($socials as $social) {
					$s[] = '"'.$social.'"';
				}
				$social_links = implode(',', $s);
				$code .= ',"sameAs": [ '.$social_links.' ]';
			}
			
			$code .= '}';
			$code .= "</script>";
			
			
		} else {
			$code = '';
		}
		
		$this->document->setStructureddata($code);
	}
	
	public function site_search() {		
		if ($this->config->get('hb_snippets_search_enable')) {
			$store_id = (int)$this->config->get('config_store_id');
			
			if ($this->config->get('config_url')){
				$store_url = $this->config->get('config_url');
			}else{
				$store_url = HTTPS_SERVER;
			}
			
			$code = '';
			$code .= '<script type="application/ld+json">';
			$code .= '{';
			$code .= '"@context": "http://schema.org/",';
			$code .= '"@type": "WebSite",';
			$code .= '"url": "'.$store_url.'",';
		
			$search_link = $this->url->link('product/search', 'search=');
			$code .= '"potentialAction": { "@type": "SearchAction", "target": "'.$search_link.'{search_term_string}", "query-input": "required name=search_term_string"}';
			
			$code .= '}';
			$code .= "</script>";
			
		} else {
			$code = '';
		}
		
		$this->document->setStructureddata($code);
	}
	
	public function itemlist($products) {		
		if ($this->config->get('hb_snippets_list_enable') && $products) {
			$store_id = (int)$this->config->get('config_store_id');
			
			if ($this->config->get('config_url')){
				$store_url = $this->config->get('config_url');
			}else{
				$store_url = HTTPS_SERVER;
			}
			
			$code = '';
			$code .= '<script type="application/ld+json">';
			$code .= '{';
			$code .= '"@context": "http://schema.org/",';
			$code .= '"@type": "ItemList",';
			$code .= '"itemListElement":';
			$i = 1;
			
			$list = array();
			
			foreach ($products as $product) {
				$list[] = '{"@type":"ListItem", "position":'.$i.', "url":"'.$product['href'].'","name": "'.$product['name'].'", "image": "'.$product['thumb'].'"}';
				$i++;
			}
			
			$itemlist = implode(',', $list);
			$code .= '[ '.$itemlist.' ]';
			
			$code .= '}';
			$code .= "</script>";
			
		} else {
			$code = '';
		}
		
		$this->document->setStructureddata($code);
	}
	
}