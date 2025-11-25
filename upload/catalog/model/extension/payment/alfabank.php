<?php
class ModelExtensionPaymentAlfabank extends Model {
public function getMethod($address, $total) {
$this->load->language('extension/payment/alfabank');
$this->load->model('catalog/product');
$this->load->model('catalog/category');

// Check customer group restriction
if ($this->customer->isLogged()) {
$customer_group_id = $this->customer->getGroupId();
} else {
$customer_group_id = $this->config->get('config_customer_group_id');
}

$allowed_customer_groups = $this->config->get('payment_alfabank_customer_group_id');

if ($allowed_customer_groups && is_array($allowed_customer_groups) && !in_array($customer_group_id, $allowed_customer_groups)) {
return [];
}

$min_amount = (float)$this->config->get('payment_alfabank_min_amount');
$max_amount = (float)$this->config->get('payment_alfabank_max_amount');
if ($total < $min_amount) {
return [];
}
if ($max_amount > 0 && $total > $max_amount) {
return [];
}
$allowed_categories_json = $this->config->get('payment_alfabank_allowed_categories');
$allowed_categories = json_decode($allowed_categories_json, true);
$allowed_categories = is_array($allowed_categories) ? array_map('intval', $allowed_categories) : [];
$denied_categories_json = $this->config->get('payment_alfabank_denied_categories');
$denied_categories = json_decode($denied_categories_json, true);
$denied_categories = is_array($denied_categories) ? array_map('intval', $denied_categories) : [];
$getAllChildCategories = function($parent_id) use (&$getAllChildCategories) {
$children = $this->model_catalog_category->getCategories($parent_id);
$result = [];
foreach ($children as $child) {
$result[] = (int)$child['category_id'];
$result = array_merge($result, $getAllChildCategories($child['category_id']));
}
return $result;
};
$all_allowed_categories = [];
foreach ($allowed_categories as $cat_id) {
$all_allowed_categories[] = $cat_id;
$all_allowed_categories = array_merge($all_allowed_categories, $getAllChildCategories($cat_id));
}
$all_allowed_categories = array_unique($all_allowed_categories);
$all_denied_categories = [];
foreach ($denied_categories as $cat_id) {
$all_denied_categories[] = $cat_id;
$all_denied_categories = array_merge($all_denied_categories, $getAllChildCategories($cat_id));
}
$all_denied_categories = array_unique($all_denied_categories);
$cart_products = $this->cart->getProducts();
foreach ($cart_products as $product) {
$product_categories = $this->model_catalog_product->getCategories($product['product_id']);
$category_ids = array_map(function($c) {
return (int)$c['category_id'];
}, $product_categories);
foreach ($category_ids as $cid) {
if (in_array($cid, $all_denied_categories)) {
return [];
}
}
if (!empty($all_allowed_categories)) {
$found_allowed = false;
foreach ($category_ids as $cid) {
if (in_array($cid, $all_allowed_categories)) {
$found_allowed = true;
break;
}
}
if (!$found_allowed) {
return [];
}
}
}
return [
'code'       => 'alfabank',
'title'      => $this->language->get('entry_alfabank_text_title'),
'terms'      => '',
'sort_order' => $this->config->get('payment_alfabank_sort_order')
];
}
public function storeGatewayOrder($data) {
$this->db->query("INSERT INTO `" . DB_PREFIX . "alfabank_order` SET `order_id` = '" . (int)$data['order_id'] . "', `gateway_order_reference` = '" . $this->db->escape($data['gateway_order_reference']) . "', `currency` = '" . $this->db->escape($data['currency']) . "', `order_amount` = '" . (float)$data['order_amount'] . "', `order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "', `status_deposited` = '" . (int)$data['status_deposited'] . "', `date_added` = NOW(), `date_updated` = NOW()");
}
}