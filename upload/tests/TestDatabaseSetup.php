<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 28/01/25
 * Time: 14:03
 */

class TestDatabaseSetup {
    private $db;

    public function __construct() {
        $this->db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    }

    public function setupDatabase() {
        // Create test tables
        $this->createProductTable();
        $this->createProductSpecialTable();
        $this->createProductDiscountTable();
        $this->createOdooConfigTable();
        $this->createTestData();
    }

    private function createProductTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product`");
        $this->db->query("
            CREATE TABLE `" . DB_PREFIX . "product` (
                `product_id` int(11) NOT NULL AUTO_INCREMENT,
                `model` varchar(64) NOT NULL,
                `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
                PRIMARY KEY (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    private function createProductSpecialTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_special`");
        $this->db->query("
            CREATE TABLE `" . DB_PREFIX . "product_special` (
                `product_special_id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(11) NOT NULL,
                `customer_group_id` int(11) NOT NULL,
                `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
                `date_start` date NOT NULL DEFAULT '0000-00-00',
                `date_end` date NOT NULL DEFAULT '0000-00-00',
                PRIMARY KEY (`product_special_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    private function createProductDiscountTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_discount`");
        $this->db->query("
            CREATE TABLE `" . DB_PREFIX . "product_discount` (
                `product_discount_id` int(11) NOT NULL AUTO_INCREMENT,
                `product_id` int(11) NOT NULL,
                `customer_group_id` int(11) NOT NULL,
                `quantity` int(4) NOT NULL DEFAULT '0',
                `price` decimal(15,4) NOT NULL DEFAULT '0.0000',
                `date_start` date NOT NULL DEFAULT '0000-00-00',
                `date_end` date NOT NULL DEFAULT '0000-00-00',
                PRIMARY KEY (`product_discount_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    private function createOdooConfigTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "odoo_config`");
        $this->db->query("
            CREATE TABLE `" . DB_PREFIX . "odoo_config` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `key` varchar(255) NOT NULL,
                `value` text,
                PRIMARY KEY (`id`),
                UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");

        // Insert default debug value
        $this->db->query("INSERT INTO `" . DB_PREFIX . "odoo_config` SET
            `key` = 'debug',
            `value` = '0'
        ");
    }

    private function createTestData() {
        // Insert test product
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET 
            `product_id` = 780,
            `model` = 'TEST001',
            `price` = '12000.0000'
        ");

    }

    public function clearDatabase() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_special`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "product_discount`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "odoo_config`");
    }
}