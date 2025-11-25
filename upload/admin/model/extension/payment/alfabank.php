<?php
class ModelExtensionPaymentAlfabank extends Model {
public function install() {
$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alfabank_order` (
`gateway_order_id` int(11) NOT NULL AUTO_INCREMENT,
`gateway_order_reference` varchar(64),
`order_id` int(11) NOT NULL,
`currency` varchar(3),
`order_amount` decimal(15,4) NOT NULL COMMENT 'Order amount',
`order_amount_deposited` decimal(15,4) NOT NULL COMMENT 'Order deposited amount',
`order_amount_refunded` decimal(15,4) NOT NULL COMMENT 'Order refunded amount',
`status_deposited` tinyint(1) NOT NULL DEFAULT 0,
`status_reversed` tinyint(1) NOT NULL DEFAULT 0,
`status_refunded` tinyint(1) NOT NULL DEFAULT 0,
`status` tinyint(1) NOT NULL DEFAULT 0,
`date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`date_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
PRIMARY KEY (`gateway_order_id`),
KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
}
public function deleteTables() {
$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "alfabank_order`");
}
public function getGatewayOrder($order_id) {
$result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "alfabank_order` WHERE order_id = " . (int)$order_id . " LIMIT 1");
return $result->row;
}
public function updateGatewayOrder($order_id, $data) {
$sql = "UPDATE `" . DB_PREFIX . "alfabank_order` SET ";
$sql_data = array();
if (isset($data['order_amount_deposited'])) {
$sql_data[] = "`order_amount_deposited` = '" . (float)$data['order_amount_deposited'] . "'";
}
if (isset($data['order_amount_refunded'])) {
$sql_data[] = "`order_amount_refunded` = '" . (float)$data['order_amount_refunded'] . "'";
}
if (isset($data['status_deposited'])) {
$sql_data[] = "`status_deposited` = " . (int)$data['status_deposited'];
}
if (isset($data['status_reversed'])) {
$sql_data[] = "`status_reversed` = " . (int)$data['status_reversed'];
}
if (isset($data['status_refunded'])) {
$sql_data[] = "`status_refunded` = " . (int)$data['status_refunded'];
}
if (isset($data['status'])) {
$sql_data[] = "`status` = " . (int)$data['status'];
}
$sql_data[] = "`date_updated` = NOW()";
$sql .= implode(', ', $sql_data);
$sql .= " WHERE `order_id` = " . (int)$order_id;
$this->db->query($sql);
}
}