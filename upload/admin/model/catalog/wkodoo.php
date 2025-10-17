<?php
#################################################################################################
# Webservices xmlrpc Tab Opencart 3.0.x.x From Webkul  http://webkul.com                        #
#################################################################################################
class ModelCatalogWkodoo extends Model {

    public function viewDetails($id){
        $sql ="SELECT * FROM " . DB_PREFIX . "odoo_confg ";
        $result=$this->db->query($sql);
        return $result->row;
    }


    public function insertConfg($data){
        $this->db->query("DELETE FROM " . DB_PREFIX . "odoo_confg ");
        $sql = "INSERT INTO " . DB_PREFIX . "odoo_confg SET user_id = '" .$this->session->data['user_id']. "' ,user = '" .$data['wkuser']. "', url = '" .$data['wkurl']. "', wkproducttype='".$data['wkproducttype']."', port = '" .$data['wkport']. "', db_name = '" .$data['wkdb_name']. "', date_created = NOW() ,password = '" .$data['wkpassword']."'";
        $result=$this->db->query($sql);

    }

    public function getDefaultLanguageName(){
        $language_code = $this->config->get("config_language");
        $sql ="SELECT name FROM " . DB_PREFIX . "language WHERE code ='" .$language_code."' LIMIT 1";
        $result = $this->db->query($sql)->row;
        if ($result){
            return $result['name'];
        }
        return '';
    }

	public function updateConfg($data){
		// $sql ="UPDATE " . DB_PREFIX . "odoo_confg SET user='" .$data['wkuser']. "' , url='" .$data['wkurl']. "',port='" .$data['wkport']. "', db_name='" .$data['wkdb_name']."',password = '" .$data['wkpassword']. "' WHERE id='" .$data['wkid']."' AND user_id = '" .$this->session->data['user_id']. "'";
		$sql ="UPDATE " . DB_PREFIX . "odoo_confg SET status='" .$data['wkstatus']. "' , wkproducttype='".$data['wkproducttype']."' WHERE id='" .$data['wkid']."' AND user_id = '" .$this->session->data['user_id']. "'";
		$result=$this->db->query($sql);
	}

	public function getCategory($data){

		$sql ="SELECT *, cd.name FROM " . DB_PREFIX . "erp_category_merge erp LEFT JOIN ".DB_PREFIX."category_description cd ON (erp.opencart_category_id = cd.category_id) WHERE language_id = '".$this->config->get('config_language_id')."' AND 1";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_category_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_category_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (!empty($data['filter_categoryName'])) {
			$implode[] = "LCASE(cd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_categoryName'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_category_id',
			'opencart_category_id',
			'cd.name',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getCategoryTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_category_merge ";

		$sql ="SELECT *, cd.name FROM " . DB_PREFIX . "erp_category_merge erp LEFT JOIN ".DB_PREFIX."category_description cd ON (erp.opencart_category_id = cd.category_id) WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_category_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_category_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (!empty($data['filter_categoryName'])) {
			$implode[] = "LCASE(cd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_categoryName'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_category_id',
			'opencart_category_id',
			'cd.name',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteCategory($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_category_merge WHERE id='$id'");

	}

	public function getTax($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_tax_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_tax_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_tax_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_rate']) && !is_null($data['filter_rate'])) {
			$implode[] = "rate = '" . (int)$data['filter_rate'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_tax_id',
			'opencart_tax_id',
			'rate',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getTaxTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_tax_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_tax_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_tax_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_tax_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_rate']) && !is_null($data['filter_rate'])) {
			$implode[] = "rate = '" . (int)$data['filter_rate'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_tax_id',
			'opencart_tax_id',
			'rate',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function getOpTax(){
		$sql = $this->db->query("SELECT name,tax_rate_id as id FROM " . DB_PREFIX . "tax_rate ")->rows;
		return $sql;
	}

	public function deleteTax($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_tax_merge WHERE id='$id'");

	}

	public function getCurrency($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_currency_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_currency_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_currency_id',
			'opencart_currency_id',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getCurrencyTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_currency_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_currency_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_currency_id',
			'opencart_currency_id',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function getOpCurrency(){
		$sql = $this->db->query("SELECT CONCAT(title ,'  ',symbol_left ,symbol_right) as name,currency_id as id FROM " . DB_PREFIX . "currency ")->rows;
		return $sql;
	}

	public function deleteCurrency($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_currency_merge WHERE id='$id'");

	}

	public function getCarrier($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_carrier_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_carrier_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_carrier_cod = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_carrier_id',
			'opencart_carrier_cod',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getCarrierTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_carrier_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_carrier_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_carrier_code = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_carrier_id',
			'opencart_carrier_cod',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteCarrier($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_carrier_merge WHERE id='$id'");

	}

	public function getPayment($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_payment_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_payment_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_payment_cod = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_payment_id',
			'opencart_payment_cod',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getPaymentTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_payment_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_payment_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_payment_code = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_payment_id',
			'opencart_payment_cod',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function getOpPayment(){
		$sql = $this->db->query("SELECT code from `" . DB_PREFIX . "extension` WHERE type = 'payment' ")->rows;

		$paymet_array = array();
		if($sql)
			foreach ($sql as $value) {
				$extension = basename($value['code'], '.php');
	            $this->language->load('extension/payment/' . $extension);
	            $paymet_array [] = array('id' => $value['code'],
	            					  'name' => $this->language->get('heading_title'),
	            				     );
			}
		return $paymet_array;
	}

	public function deletePayment($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_payment_merge WHERE id='$id'");

	}

	public function getAddress($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_address_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_address_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_address_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_cid']) && !is_null($data['filter_cid'])) {
			$implode[] = "customer_id = '" . (int)$data['filter_cid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_address_id',
			'opencart_address_id',
			'customer_id',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getAddressTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_address_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_address_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_address_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_cid']) && !is_null($data['filter_cid'])) {
			$implode[] = "customer_id = '" . (int)$data['filter_cid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_address_id',
			'opencart_address_id',
			'customer_id',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteAddress($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_address_merge WHERE id='$id'");

	}

	public function getCustomer($data){

		$sql ="SELECT *, CONCAT(c.firstname,' ', c.lastname) AS name FROM " . DB_PREFIX . "erp_customer_merge erp LEFT JOIN ".DB_PREFIX."customer c ON(erp.opencart_customer_id = c.customer_id) WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_customer_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_customer_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
			$implode[] = "LCASE(CONCAT(c.firstname,' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_customer_id',
			'opencart_customer_id',
			'c.firstname',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getCustomerTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT *, CONCAT(c.firstname,' ', c.lastname) AS name FROM " . DB_PREFIX . "erp_customer_merge erp LEFT JOIN ".DB_PREFIX."customer c ON(erp.opencart_customer_id = c.customer_id) WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_customer_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_customer_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_name']) && !is_null($data['filter_name'])) {
			$implode[] = "LCASE(CONCAT(c.firstname,' ', c.lastname)) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_customer_id',
			'opencart_customer_id',
			'c.firstname',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteCustomer($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_customer_merge WHERE id='$id'");

	}

	public function getProduct($data){

		// $sql ="SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE 1 ";
		$sql ="SELECT erp.*, pd.name FROM " . DB_PREFIX . "erp_product_template_merge erp LEFT JOIN ".DB_PREFIX."product_description pd ON (erp.opencart_product_id = pd.product_id) WHERE language_id = '".$this->config->get('config_language_id')."' AND 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_template_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_product_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_productName']) && !is_null($data['filter_productName'])) {
			$implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_productName'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_template_id',
			'opencart_product_id',
			'pd.name',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getProductTotal($data){

		$sql ="SELECT erp.*, pd.name FROM " . DB_PREFIX . "erp_product_template_merge erp LEFT JOIN ".DB_PREFIX."product_description pd ON (erp.opencart_product_id = pd.product_id) WHERE 1 ";

		// $sql ="SELECT * FROM " . DB_PREFIX . "erp_product_template_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_template_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_product_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_productName']) && !is_null($data['filter_productName'])) {
			$implode[] = "LCASE(pd.name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_productName'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_template_id',
			'opencart_product_id',
			'pd.name',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteProduct($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_product_template_merge WHERE id='$id'");

	}

	public function getOrders($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_order_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_order_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_order_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_cid']) && !is_null($data['filter_cid'])) {
			$implode[] = "customer_id = '" . (int)$data['filter_cid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_order_id',
			'opencart_order_id',
			'customer_id',
			'created_on',
			'created_by',
			'is_synch',
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

	public function getOrdersTotal($data){

		//$sql ="SELECT * FROM " . DB_PREFIX . "erp_currency_merge ";

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_order_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_order_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_order_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_cid']) && !is_null($data['filter_cid'])) {
			$implode[] = "customer_id = '" . (int)$data['filter_cid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_order_id',
			'opencart_order_id',
			'customer_id',
			'created_on',
			'created_by',
			'is_synch',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteOrder($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_order_merge WHERE id='$id'");

	}

	public function getOrderStatus($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_order_status_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_order_status_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_order_status_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(opname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (!empty($data['filter_erpname'])) {
			$implode[] = "LCASE(erpname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpname'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_order_status_id',
			'opencart_order_status_id',
			'opname',
			'erpname',
			'created_on',
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

	public function getOrderStatusTotal($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_order_status_merge WHERE 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_order_status_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_order_status_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(opname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (!empty($data['filter_erpname'])) {
			$implode[] = "LCASE(erpname) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpname'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_order_status_id',
			'opencart_order_status_id',
			'opname',
			'erpname',
			'created_on',
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

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteOrderStatus($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_order_status_merge WHERE id='$id'");

	}

	public function getOpOrderStatus(){

		$sql = $this->db->query("SELECT order_status_id as id,name from `" . DB_PREFIX . "order_status` WHERE language_id = '".$this->config->get('config_language_id')."' ")->rows;

		return $sql;
	}

	public function addto_order_status_merge($opName,$opId,$erpName,$erpId,$user){

		$sql_oc = $this->db->query("SELECT id from `" . DB_PREFIX . "erp_order_status_merge` WHERE opencart_order_status_id = '$opId' ")->row;
		if ($sql_oc)
			return 2;

		$sql_oe = $this->db->query("SELECT id from `" . DB_PREFIX . "erp_order_status_merge` WHERE erp_order_status_id = '$erpId' ")->row;
		if ($sql_oe)
			return 3;
		if(!$sql_oc && !$sql_oe){
			$this->db->query("INSERT INTO " . DB_PREFIX . "erp_order_status_merge SET erp_order_status_id = '$erpId',opencart_order_status_id = '$opId',opname = '$opName',erpname = '$erpName',created_by = '$user'");
			return 1;
		}

		return false;

	}

	public function getOpCategories(){

		$sql = $this->db->query("SELECT category_id as id, name FROM `" . DB_PREFIX . "category_description` WHERE language_id = '".$this->config->get('config_language_id')."' ");

		return $sql->rows;

	}

	public function getOpCustomers(){

		$sql = $this->db->query("SELECT customer_id AS id, CONCAT(firstname,' ',lastname) AS name FROM `" . DB_PREFIX . "customer` WHERE status = '1' ");

		return $sql->rows;
	}

	public function getOpProducts(){

		$sql = $this->db->query("SELECT product_id as id, name FROM `" . DB_PREFIX . "product_description` WHERE language_id = '".$this->config->get('config_language_id')."' ");

		return $sql->rows;

	}

	public function getOpProductsOption(){

		$sql = $this->db->query("SELECT option_id as id, name FROM `" . DB_PREFIX . "option_description` WHERE language_id = '".$this->config->get('config_language_id')."' ");

		return $sql->rows;

	}

	public function getOpProductsOptionValue(){

		$sql = $this->db->query("SELECT option_value_id as id, name FROM `" . DB_PREFIX . "option_value_description` WHERE language_id = '".$this->config->get('config_language_id')."' ");

		return $sql->rows;

	}

	public function getOpCarriers(){

		$sql = $this->db->query("SELECT `code` as id from `" . DB_PREFIX . "extension` WHERE type = 'shipping' ");

		return $sql->rows;

	}

	public function deleteProductOption($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_product_option_merge WHERE id='$id'");

	}

	public function getProductOption($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_product_option_merge e LEFT JOIN ".DB_PREFIX."option_description o ON (e.opencart_option_id = o.option_id) WHERE language_id = '".$this->config->get('config_language_id')."'  ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_option_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_option_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_option_id',
			'opencart_option_id',
			'name',
			'is_synch',
			'created_on',
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

	public function getProductOptionTotal($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_product_option_merge e LEFT JOIN ".DB_PREFIX."option_description o ON (e.opencart_option_id = o.option_id) WHERE language_id = '".$this->config->get('config_language_id')."'  ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_option_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_option_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteProductOptionValue($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_product_option_value_merge WHERE id='$id'");

	}

	public function getProductOptionValue($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_product_option_value_merge e LEFT JOIN ".DB_PREFIX."option_value_description o ON (e.opencart_option_value_id = o.option_value_id) WHERE language_id = '".$this->config->get('config_language_id')."' ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_option_value_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_option_value_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (isset($data['filter_oid']) && !is_null($data['filter_oid'])) {
			$implode[] = "option_id = '" . (int)$data['filter_oid'] . "'";
		}
		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_option_value_id',
			'opencart_option_value_id',
			'name',
			'option_id',
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

	public function getProductOptionValueTotal($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_product_option_value_merge e LEFT JOIN ".DB_PREFIX."option_value_description o ON (e.opencart_option_value_id = o.option_value_id) WHERE language_id = '".$this->config->get('config_language_id')."' ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "LCASE(erp_option_value_id) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_erpid'])) . "%'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_option_value_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_opname']) && !is_null($data['filter_opname'])) {
			$implode[] = "LCASE(name) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_opname'])) . "%'";
		}

		if (isset($data['filter_oid']) && !is_null($data['filter_oid'])) {
			$implode[] = "option_id = '" . (int)$data['filter_oid'] . "'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return count($query->rows);

	}

	public function deleteProductVariant($id){

		$this->db->query("DELETE FROM " . DB_PREFIX . "erp_product_variant_merge WHERE id='$id'");

	}

	public function getProductVariant($data){

		$sql ="SELECT erp.*, pd.`name` as prdouct_name, od.`name` as value_name FROM " . DB_PREFIX . "erp_product_variant_merge erp LEFT JOIN ".DB_PREFIX."product_description pd ON (erp.opencart_product_id = pd.product_id )
		LEFT JOIN ".DB_PREFIX."product_option_value ov ON (erp.opencart_product_option_id = ov.product_option_value_id )
		LEFT JOIN ".DB_PREFIX."option_value_description od ON (ov.option_value_id = od.option_value_id) WHERE pd.language_id = '".$this->config->get('config_language_id')."' AND 1 ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_product_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_product_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_optvid']) && !is_null($data['filter_optvid'])) {
			$implode[] = "option_value_id = '" . (int)$data['filter_optvid'] . "'";
		}

		if (isset($data['filter_optid']) && !is_null($data['filter_optid'])) {
			$implode[] = "option_id = '" . (int)$data['filter_optid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$sort_data = array(
			'id',
			'erp_product_id',
			'opencart_product_id',
			'option_value_id',
			'prdouct_name',
			'value_name',
			'option_id',
			'is_synch',
			'created_on',
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

	public function getProductVariantTotal($data){

		$sql ="SELECT * FROM " . DB_PREFIX . "erp_product_variant_merge WHERE 1  ";

		$implode = array();

		if (isset($data['filter_id']) && !is_null($data['filter_id'])) {
			$implode[] = "id = '" . (int)$data['filter_id'] . "'";
		}

		if (isset($data['filter_erpid']) && !is_null($data['filter_erpid'])) {
			$implode[] = "erp_product_id = '" . (int)$data['filter_erpid'] . "'";
		}

		if (isset($data['filter_opid']) && !is_null($data['filter_opid'])) {
			$implode[] = "opencart_product_id = '" . (int)$data['filter_opid'] . "'";
		}

		if (isset($data['filter_optvid']) && !is_null($data['filter_optvid'])) {
			$implode[] = "option_value_id = '" . (int)$data['filter_optvid'] . "'";
		}

		if (isset($data['filter_optid']) && !is_null($data['filter_optid'])) {
			$implode[] = "option_id = '" . (int)$data['filter_optid'] . "'";
		}

		if (isset($data['filter_date']) && !is_null($data['filter_date'])) {
			$implode[] = "created_on LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_date'])) . "%'";
		}

		if (!empty($data['filter_by'])) {
			$implode[] = "LCASE(created_by) LIKE '%" . $this->db->escape(utf8_strtolower($data['filter_by'])) . "%'";
		}

		if (isset($data['filter_status']) && !is_null($data['filter_status'])) {
			$implode[] = "is_synch = '" . (int)$data['filter_status'] . "'";
		}

		if ($implode) {
			$sql .= " AND " . implode(" AND ", $implode);
		}

		$query = $this->db->query($sql);

		return count($query->rows);

	}

}
?>
