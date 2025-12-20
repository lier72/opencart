<?php
class ModelExtensionModuleCdekIntegrator extends Model {
	
	public function getOrders($data = array()) {
		
		$sql  = "SELECT o.order_id, ";
		$sql .= "CONCAT(o.firstname, ' ', o.lastname) AS customer, ";
		$sql .= "(SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS status, ";
		$sql .= "o.total, ";
		$sql .= "o.currency_code, ";
		$sql .= "o.currency_value, ";
		$sql .= "o.date_added, ";
		$sql .= "o.date_modified ";
		$sql .= "FROM `" . DB_PREFIX . "order` o";
		
		if ($conditions = $this->getOrderConditions($data)) {
			$sql .= " WHERE " . implode(" AND ", $conditions);
		}

		$sort_data = array(
			'o.order_id',
			'customer',
			'status',
			'o.date_added',
			'o.date_modified',
			'o.total',
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY o.order_id";
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
	
	public function getTotalOrders($data = array()) {
		
		$sql  = "SELECT COUNT(*) as total ";
		$sql .= "FROM `" . DB_PREFIX . "order` o";
		
		if ($conditions = $this->getOrderConditions($data)) {
			$sql .= " WHERE " . implode(" AND ", $conditions);
		}
		
		return $this->db->query($sql)->row['total'];
		
	}
	
	private function getOrderConditions($data) {
		
		$conditions = array();

		if (isset($data['filter_order_status_id']) && !is_null($data['filter_order_status_id'])) {
			
			if (is_array($data['filter_order_status_id'])) {
				$conditions[] = "o.order_status_id IN (" . implode(',', $data['filter_order_status_id']) . ")";
			} else {
				$conditions[] = "o.order_status_id = " . (int)$data['filter_order_status_id'] . "";
			}
			
		}
		
		if (isset($data['filter_dispatch'])) {
			$conditions[] = "(SELECT co.order_id FROM `" . DB_PREFIX . "cdek_order` co WHERE o.order_id = co.order_id LIMIT 1) IS NULL";
		}
		
		if (isset($data['filter_dispatch_number'])) {
			$conditions[] = "dispatch_number = '" . $this->db->escape($data['filter_dispatch_number']) . "'";
		}
		
		if (!empty($data['filter_order_id'])) {
			$conditions[] = "o.order_id = '" . (int)$data['filter_order_id'] . "'";
		}
		
		if (!empty($data['filter_new_order'])) {
			$conditions[] = "DATEDIFF(CURRENT_DATE(), o.date_added) <= '" . (int)$data['filter_new_order'] . "'";
		}
		
		if (!empty($data['filter_shipping'])) {
			
			$shipping_condition = array();
			
			if (is_array($data['filter_shipping'])) {
				$shipping_code = $data['filter_shipping'];
			} else {
				$shipping_code = array($data['filter_shipping']);
			}
			
			foreach ($shipping_code as $code) {
				$shipping_condition[] = "LCASE(o.shipping_code) LIKE '" . $this->db->escape($code) . "%'";
			}
			
			if (!empty($shipping_condition)) {
				$conditions[] = "(" . implode(" OR ", $shipping_condition) . ")";
			}
		}
		
		if (!empty($data['filter_payment'])) {
			
			$pyament_condition = array();
			
			if (is_array($data['filter_payment'])) {
				$payment_code = $data['filter_payment'];
			} else {
				$payment_code = array($data['filter_payment']);
			}
			
			$conditions[] = "LCASE(o.payment_code) IN ('" . implode("', '", $payment_code) . "')"/*"(" . implode(' OR ', $pyament_condition) . ")"*/;				
		}

		if (!empty($data['filter_customer'])) {
			$conditions[] = "LCASE(CONCAT(o.firstname, ' ', o.lastname)) LIKE '" . $this->db->escape(utf8_strtolower($data['filter_customer'])) . "%'";
		}

		if (!empty($data['filter_date_added'])) {
			$conditions[] = "DATE(o.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		if (!empty($data['filter_total'])) {
			$conditions[] = "ROUND(o.total,0) >= '" . round((float)$data['filter_total']) . "'";
		}
		
		if (!empty($data['filter_new_order']) && is_numeric($data['filter_new_order']) && $data['filter_new_order'] > 0) {
			$conditions[] = "DATEDIFF(CURDATE(), DATE(o.date_added)) < " . (int)$this->db->escape($data['filter_new_order']);
		}
		
		return $conditions;
	}
	
	public function getOrderProducts($order_id) {

		$sql  = "SELECT op.*, ";
		$sql .= "p.length, ";
		$sql .= "p.width, ";
		$sql .= "p.height, ";
		$sql .= "p.weight, ";
		$sql .= "p.length_class_id, ";
		$sql .= "p.weight_class_id ";
		$sql .= "FROM " . DB_PREFIX . "order_product op ";
		$sql .= "LEFT JOIN " . DB_PREFIX . "product p ON (op.product_id = p.product_id) ";
		$sql .= "WHERE op.order_id = '" . (int)$order_id . "'";

		return $this->db->query($sql)->rows;
	}
	
	public function getOrderProductOptions($order_product_id) {
		
		$sql  = "SELECT oo.*, ";
		$sql .= "pov.weight, ";
		$sql .= "pov.weight_prefix ";
		$sql .= "FROM `" . DB_PREFIX . "order_option` oo ";
		$sql .= "LEFT JOIN " . DB_PREFIX . "product_option_value pov ON (oo.product_option_value_id = pov.product_option_value_id) ";
		//$sql .= "product_option_value pov
		$sql .= "WHERE oo.order_product_id = '" . (int)$order_product_id . "'";
		
		return $this->db->query($sql)->rows;
	}
	
	public function getCity($name = '') {
		
		if (!$name) return FALSE;
		
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "cdek_city` WHERE LCASE(name) LIKE '" . $name . "%'")->rows;
	}
	
	public function addDispatch($data = array()) {
		
		if (!$data) return FALSE;
		
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cdek_dispatch` SET `dispatch_number` = '" . $this->db->escape($data['number']) . "', `date` = '" . $this->db->escape($data['date']) . "', `server_date` = '" . $this->db->escape(time()) . "'");
	
		$dispatch_id = $this->db->getLastId();
		
		if (!empty($data['orders'])) {

			$order_info = $data['orders'];

			$order_id = (int)$order_info['order_id'];
			
			$sql  = "INSERT INTO `" . DB_PREFIX . "cdek_order` SET ";
			$sql .= "`order_id` = " . $order_id . ", ";
			$sql .= "`dispatch_id` = " . (int)$dispatch_id . ", ";
			
			if (!empty($order_info['city_postcode'])) {
				$sql .= "`act_number` = '" . $order_info['act_number'] . "', ";
			}

			$sql .= "`dispatch_number` = '" . $order_info['dispatch_number'] . "', ";
			$sql .= "`city_id` = " . (int)$order_info['city_id'] . ", ";
			$sql .= "`city_name` = '" . $this->db->escape($order_info['city_name']) . "', ";
			
			if (!empty($order_info['city_postcode'])) {
				$sql .= "`city_postcode` = '" . $this->db->escape($order_info['city_postcode']) . "', ";
			}
			
			$sql .= "`recipient_city_id` = " . (int)$order_info['recipient_city_id'] . ", ";
			$sql .= "`recipient_city_name` = '" . $this->db->escape($order_info['recipient_city_name']) . "', ";
			
			if (!empty($order_info['recipient_city_postcode'])) {
				$sql .= "`recipient_city_postcode` = '" . $this->db->escape($order_info['recipient_city_postcode']) . "', ";
			}
			
			$sql .= "`recipient_name` = '" . $this->db->escape($order_info['recipient_name']) . "', ";
			$sql .= "`recipient_email` = '" . $this->db->escape($order_info['recipient_email']) . "', ";
			$sql .= "`phone` = '" . $this->db->escape($order_info['recipient_telephone']) . "', ";
			$sql .= "`tariff_id` = " . (int)$order_info['tariff_id'] . ", ";
			$sql .= "`mode_id` = " . (int)$order_info['mode_id'] . ", ";
			$sql .= "`reason_id` = 0, "; // Доп статус
			$sql .= "`status_id` = '" . $this->db->escape($order_info['status_id']). "', ";
			$sql .= "`delivery_recipient_cost` = " . (float)$order_info['delivery_recipient_cost'] . ", ";
			$sql .= "`currency` = '" . $this->db->escape($order_info['currency']) . "', ";
			$sql .= "`currency_cod` = '" . $this->db->escape($order_info['currency_cod']) . "', ";
			$sql .= "`comment` = '" . $this->db->escape($order_info['cdek_comment']) . "', ";
			$sql .= "`seller_name` = '" . $this->db->escape($order_info['seller_name']) . "', ";
			$sql .= "`address_street` = '" . $this->db->escape($order_info['address']['street']) . "', ";
			$sql .= "`address_house` = '" . $this->db->escape($order_info['address']['house']) . "', ";
			$sql .= "`address_flat` = '" . $this->db->escape($order_info['address']['flat']) . "', ";
			$sql .= "`address_pvz_code` = '" . $this->db->escape($order_info['address']['pvz_code']) . "',";
			$sql .= "`last_exchange` = '" . time() . "'";
			
			if (!empty($order_info['delivery_cost']) && (float)$order_info['delivery_cost']) {
				$sql .= ", `delivery_cost` = '" . (float)$order_info['delivery_cost'] . "'";
			}
			
			if (!empty($order_info['delivery_last_change'])) {
				$sql .= ", `delivery_last_change` = '" . (float)$order_info['delivery_last_change'] . "'";
			}
			
			$this->db->query($sql);
			
			// Change order status
			$this->changeOrderStatus($order_info['status_id'], $this->getDispatchInfo($order_id));

			// NOTE: History data (status_history, packages, courier, schedule, add_service)
			// is no longer stored locally - it's fetched from CDEK API on-demand
		
		}
	}
	
	public function editDispatch($order_id, $data) {

        if ($dispatch_info = $this->getDispatchInfo($order_id))
		{

			$sql  = "UPDATE `" . DB_PREFIX . "cdek_order` SET last_exchange = " . time();

			if (!empty($data['status_id'])) {
				$sql .= ", status_id = '" . $this->db->escape($data['status_id']) . "'";
			}
			
			if (!empty($data['reason_id'])) {
				$sql .= ", reason_id = " . (int)$data['reason_id'];
			}

            if (!empty($data['cdek_number'])) {
                $sql .= ", cdek_number = '" . $this->db->escape($data['cdek_number']) . "'";
            }
			
			if (!empty($data['act_number'])) {
				$sql .= ", act_number = '" . $this->db->escape($data['act_number']) . "'";
			}
			
			if (!empty($data['delay_id'])) {
				$sql .= ", delay_id = " . (int)$data['delay_id'];
			}
			
			if (!empty($data['delivery_cost'])) {
				$sql .= ", delivery_cost = " . (float)$data['delivery_cost'];
			}
			
			if (isset($data['cod'])) {
				$sql .= ", cod = " . (float)$data['cod'];
			}
			
			if (isset($data['cod_fact'])) {
				$sql .= ", cod_fact = " . (float)$data['cod_fact'];
			}
			
			if (!empty($data['city_postcode'])) {
				$sql .= ", city_postcode = '" . $this->db->escape($data['city_postcode']) . "'";
			}
			
			if (!empty($data['delivery_date'])) {
				$sql .= ", delivery_date = '" . $this->db->escape($data['delivery_date']) . "'";
			}
			
			if (!empty($data['delivery_recipient_name'])) {
				$sql .= ", delivery_recipient_name = '" . $this->db->escape($data['delivery_recipient_name']) . "'";
			}
			
			if (!empty($data['recipient_city_postcode'])) {
				$sql .= ", recipient_city_postcode = '" . $this->db->escape($data['recipient_city_postcode']) . "'";
			}
			
			$sql .= " WHERE order_id = " . (int)$order_id;

			$this->db->query($sql);

			// NOTE: History data operations removed - data is now fetched from CDEK API on-demand
			// The following data arrays are ignored: status_history, reason_history, delay_history,
			// attempt, call['good'], call['fail'], call['delay']

			// Change order status after dispatch update.
            // This should give us {status_city_name},
            // I don't think we should use $dispatch info here as it was before the changes
            // So may be it would be better to use $data instead
            // get updated $dispatch_info
            if (!empty($data['status_id'])) {
                $dispatch_info = $this->getDispatchInfo($order_id);
                $this->changeOrderStatus($data['status_id'], $dispatch_info);
            }
		} else {
			return FALSE;
		}
		
	}
	
	public function orderExists($order_id) {
		return $this->db->query("SELECT COUNT(*) total FROM `" . DB_PREFIX . "cdek_order` WHERE order_id = " . (int)$order_id)->row['total'];
	}
	
	public function getDispatchInfo($order_id, $enrich_with_api = true) {

		$sql  = "SELECT o.*, ";
		$sql .= "o.dispatch_number AS number, ";
		$sql .= "d.date, ";
		$sql .= "d.server_date, ";
		$sql .= "d.dispatch_id, ";
		$sql .= "d.dispatch_number ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "INNER JOIN `" . DB_PREFIX . "cdek_dispatch` d ON (o.dispatch_id = d.dispatch_id) ";
		$sql .= "WHERE o.order_id = " . (int)$order_id;

		$result = $this->db->query($sql)->row;

		// Optionally enrich with live API data for status/delay info
		// This is skipped when called from fetchCdekOrderData to prevent recursion
		if ($enrich_with_api && $result && !empty($result['status_id'])) {
			// Get the latest status from API
			$status_history = $this->getStatusHistory($order_id);
			if (!empty($status_history)) {
				// Find the current status in history
				foreach ($status_history as $status) {
					if ($status['status_id'] == $result['status_id']) {
						$result['status_date'] = $status['date'];
						$result['status_description'] = $status['description'];
						$result['status_city_name'] = $status['city_name'];
						break;
					}
				}
			}

			// Get delay info if there's a delay_id
			if (!empty($result['delay_id'])) {
				$delay_history = $this->getDelayHistory($order_id);
				if (!empty($delay_history)) {
					foreach ($delay_history as $delay) {
						if ($delay['delay_id'] == $result['delay_id']) {
							$result['delay_date'] = $delay['date'];
							$result['delay_description'] = $delay['description'];
							break;
						}
					}
				}
			}
		}

		return $result;
	}
	
	public function deleteDispatch($order_id) {

		$dispatch_info = $this->getDispatchInfo($order_id, false); // Don't enrich with API during delete

		if ($dispatch_info) {

			// Delete main order record
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cdek_order` WHERE order_id = '" . (int)$order_id . "'");

			// NOTE: History table deletes removed - those tables no longer exist
			// Data is now fetched from CDEK API on-demand

			// Delete dispatch if no more orders reference it
			$this->db->query("DELETE FROM `" . DB_PREFIX . "cdek_dispatch` WHERE dispatch_id = '" . (int)$dispatch_info['dispatch_id'] . "' AND (SELECT COUNT(*) FROM `" . DB_PREFIX . "cdek_order` WHERE dispatch_id = '" . (int)$dispatch_info['dispatch_id'] . "') = 0");

		}

	}
	
	public function getDispatchList($data = array()) {

		$sql  = "SELECT o.*, ";
		$sql .= "d.date ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "INNER JOIN `" . DB_PREFIX . "cdek_dispatch` d ON (o.dispatch_id = d.dispatch_id) ";
		
		$filter = array();
		
		if (!empty($data['filter_order_id'])) {
			$filter[] = "o.order_id = " . (int)$data['filter_order_id'];
		}
		
		if (!empty($data['filter_dispatch_number'])) {
			$filter[] = "o.dispatch_number = " . (int)$data['filter_dispatch_number'];
		}
		
		if (!empty($data['filter_recipient_name'])) {
			$filter[] = "o.recipient_name LIKE '%" . $this->db->escape($data['filter_recipient_name']) . "%'";
		}
		
		if (!empty($data['filter_date'])) {
			$filter[] = "DATE(FROM_UNIXTIME(d.date)) = DATE('" . $this->db->escape($data['filter_date']) . "')";
		}
		
		if (!empty($data['filter_city_from'])) {
			$filter[] = "o.city_name LIKE '" . $this->db->escape($data['filter_city_from']) . "%'";
		}
		
		if (!empty($data['filter_city_to'])) {
			$filter[] = "o.recipient_city_name LIKE '" . $this->db->escape($data['filter_city_to']) . "%'";
		}
		
		if (!empty($data['filter_status_id'])) {
			$filter[] = "o.status_id = '" . $this->db->escape($data['filter_status_id']) . "'";
		}
		
		if (!empty($data['filter_total'])) {
			$filter[] = "o.delivery_cost = " . (float)$data['filter_total'];
		}
		
		if (!empty($filter)) {
			$sql .= "WHERE " . implode(' AND ', $filter);
		}
		
		$sort_data = array(
			'o.order_id',
			'o.dispatch_number',
			'o.recipient_name',
			'd.date',
			'o.city_name',
			'o.recipient_city_name',
			'o.status_id',
			'o.delivery_cost'
		);
		
		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY d.date";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			
			if (empty($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}

			if (empty($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}
		
		return $this->db->query($sql)->rows;
	}
	
	public function getDispatchTotal($data = array()) {
		
		$sql  = "SELECT COUNT(o.order_id) as total ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "INNER JOIN `" . DB_PREFIX . "cdek_dispatch` d ON (o.dispatch_id = d.dispatch_id)";
		
		$filter = array();
		
		if (!empty($data['filter_order_id'])) {
			$filter[] = "o.order_id = " . (int)$data['filter_order_id'];
		}
		
		if (!empty($data['filter_dispatch_number'])) {
			$filter[] = "o.dispatch_number = " . (int)$data['filter_dispatch_number'];
		}
		
		if (!empty($data['filter_recipient_name'])) {
			$filter[] = "o.recipient_name LIKE '%" . $this->db->escape($data['filter_recipient_name']) . "%'";
		}
		
		if (!empty($data['filter_date'])) {
			$filter[] = "DATE(FROM_UNIXTIME(d.date)) = DATE('" . $this->db->escape($data['filter_date']) . "')";
		}
		
		if (!empty($data['filter_city_from'])) {
			$filter[] = "o.city_name LIKE '" . $this->db->escape($data['filter_city_from']) . "%'";
		}
		
		if (!empty($data['filter_city_to'])) {
			$filter[] = "o.recipient_city_name LIKE '" . $this->db->escape($data['filter_city_to']) . "%'";
		}
		
		if (!empty($data['filter_status_id'])) {
			$filter[] = "o.status_id = '" . $this->db->escape($data['filter_status_id']) . "'";
		}
		
		if (!empty($data['filter_total'])) {
			$filter[] = "o.delivery_cost = " . (float)$data['filter_total'];
		}
		
		if (!empty($filter)) {
			$sql .= "WHERE " . implode(' AND ', $filter);
		}
		
		return $this->db->query($sql)->row['total'];
	}
	
	/**
	 * Fetch CDEK order data from API
	 * Returns cached API response or makes fresh API call
	 */
	private function fetchCdekOrderData($order_id) {
		// Check if we already have the API data cached in this request
		static $cache = array();

		if (isset($cache[$order_id])) {
			return $cache[$order_id];
		}

		// Get dispatch info to find dispatch_number (UUID)
		// IMPORTANT: This will call getDispatchInfo which also calls fetchCdekOrderData
		// We need to prevent infinite recursion by checking dispatch_number directly
		$sql  = "SELECT o.dispatch_number ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "WHERE o.order_id = " . (int)$order_id;
		$result = $this->db->query($sql)->row;

		if (!$result || empty($result['dispatch_number'])) {
			return null;
		}

		$dispatch_number = $result['dispatch_number'];

		// Initialize CDEK API
		$setting = $this->getSetting();

		if (empty($setting['account']) || empty($setting['secure_password'])) {
			return null;
		}

		// Load CDEK library
		require_once(DIR_SYSTEM . 'library/cdek_integrator/class.cdek_integrator.php');

		$api = new cdek_integrator($setting['account'], $setting['secure_password']);

		$component = $api->loadComponent('order_info');

		if (!$component) {
			return null;
		}

		$component->setMetod($dispatch_number);

		$response = $api->sendData($component);

		if (!isset($response['entity'])) {
			return null;
		}

		// Cache the response for this request
		$cache[$order_id] = $response['entity'];

		return $response['entity'];
	}

	/**
	 * Get status history from CDEK API
	 * Parses 'statuses' array from API response
	 */
	public function getStatusHistory($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['statuses'])) {
			return array();
		}

		$history = array();
		foreach ($cdek_data['statuses'] as $status) {
			$history[] = array(
				'status_id' => $status['code'],
				'description' => isset($status['name']) ? $status['name'] : '',
				'date' => isset($status['date_time']) ? strtotime($status['date_time']) : 0,
				'city_name' => isset($status['city']) ? $status['city'] : ''
			);
		}

		return $history;
	}

	/**
	 * Get delay history from CDEK API
	 * Parses 'delay_reasons' array from API response
	 */
	public function getDelayHistory($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['delay_reasons'])) {
			return array();
		}

		$history = array();
		$delay_id = 1;
		foreach ($cdek_data['delay_reasons'] as $delay) {
			$history[] = array(
				'delay_id' => $delay_id++,
				'description' => isset($delay['description']) ? $delay['description'] : '',
				'date' => isset($delay['create_date']) ? strtotime($delay['create_date']) : 0
			);
		}

		return $history;
	}

	/**
	 * Get successful call history from CDEK API
	 * Note: API doesn't separate good/fail/delay calls, returns empty for backward compatibility
	 */
	public function getCallHistoryGood($order_id) {
		// CDEK API v2 doesn't provide detailed call history breakdown
		// Keeping method for backward compatibility but returning empty
		return array();
	}

	/**
	 * Get failed call history from CDEK API
	 * Parses 'calls.failed_calls' array from API response
	 */
	public function getCallHistoryFail($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['calls']['failed_calls'])) {
			return array();
		}

		$history = array();
		foreach ($cdek_data['calls']['failed_calls'] as $call) {
			$history[] = array(
				'fail_id' => isset($call['reason_code']) ? $call['reason_code'] : 0,
				'date' => isset($call['date_time']) ? strtotime($call['date_time']) : 0,
				'description' => '' // API doesn't provide description, would need separate lookup
			);
		}

		return $history;
	}

	/**
	 * Get rescheduled call history from CDEK API
	 * Parses 'calls.rescheduled_calls' array from API response
	 */
	public function getCallHistoryDelay($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['calls']['rescheduled_calls'])) {
			return array();
		}

		$history = array();
		foreach ($cdek_data['calls']['rescheduled_calls'] as $call) {
			$history[] = array(
				'date' => isset($call['date_time']) ? strtotime($call['date_time']) : 0,
				'date_next' => isset($call['date_next']) ? strtotime($call['date_next'] . ' ' . $call['time_next']) : 0
			);
		}

		return $history;
	}

	/**
	 * Get additional services from CDEK API
	 * Parses 'services' array from API response
	 */
	public function getAddService($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['services'])) {
			return array();
		}

		$services = array();
		foreach ($cdek_data['services'] as $service) {
			$services[] = array(
				'service_id' => isset($service['code']) ? $service['code'] : '',
				'description' => isset($service['parameter']) ? $service['parameter'] : '',
				'price' => isset($service['sum']) ? $service['sum'] : 0
			);
		}

		return $services;
	}

	/**
	 * Get courier call info from CDEK API
	 * Note: API v2 structure changed, returns empty for backward compatibility
	 */
	public function getCourierCall($order_id) {
		// CDEK API v2 doesn't provide courier call details in the same format
		// Keeping method for backward compatibility but returning empty
		return array();
	}
	
	public function getChedule($order_id) {
		
		$sql  = "SELECT sch.*, ";
		$sql .= "(SELECT sch_d.description FROM `" . DB_PREFIX . "cdek_order_schedule_delay` sch_d WHERE sch_d.attempt_id = sch.attempt_id AND sch_d.order_id = sch.order_id) as delay ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order_schedule` sch ";
		$sql .= "WHERE sch.order_id = " . (int)$order_id;
		
		return $this->db->query($sql)->rows;
	}
	
	/**
	 * Get packages from CDEK API
	 * Parses 'packages' array from API response
	 */
	public function getPackages($order_id) {
		$cdek_data = $this->fetchCdekOrderData($order_id);

		if (!$cdek_data || empty($cdek_data['packages'])) {
			return array();
		}

		$packages = array();
		foreach ($cdek_data['packages'] as $index => $package) {
			$packages[] = array(
				'number' => isset($package['number']) ? $package['number'] : ($index + 1),
				'brcode' => isset($package['barcode']) ? $package['barcode'] : '',
				'weight' => isset($package['weight']) ? $package['weight'] : 0,
				'size_a' => isset($package['length']) ? $package['length'] : 0,
				'size_b' => isset($package['width']) ? $package['width'] : 0,
				'size_c' => isset($package['height']) ? $package['height'] : 0,
				'package_id' => isset($package['package_id']) ? $package['package_id'] : '',
				'items' => isset($package['items']) ? $package['items'] : array()
			);
		}

		return $packages;
	}

	/**
	 * Get package items from CDEK API
	 * Note: Items are now included in getPackages() response
	 */
	public function getPackageItems($package_id, $order_id) {
		$packages = $this->getPackages($order_id);

		foreach ($packages as $package) {
			if (isset($package['package_id']) && $package['package_id'] == $package_id) {
				if (isset($package['items'])) {
					$items = array();
					foreach ($package['items'] as $item) {
						$items[] = array(
							'ware_key' => isset($item['ware_key']) ? $item['ware_key'] : '',
							'comment' => isset($item['name']) ? $item['name'] : '',
							'weight' => isset($item['weight']) ? $item['weight'] : 0,
							'amount' => isset($item['amount']) ? $item['amount'] : 0,
							'cost' => isset($item['cost']) ? $item['cost'] : 0,
							'payment' => isset($item['payment']['value']) ? $item['payment']['value'] : 0
						);
					}
					return $items;
				}
			}
		}

		return array();
	}
	
	public function changeOrderStatus($cdek_status_id, $dispatch_info) {
		
		$setting = $this->getSetting();

		if (empty($setting['order_status_rule'])) {
			return;
		}
			
		$this->load->model('sale/order');
		
		foreach ($setting['order_status_rule'] as $rule) {
			
			if ($cdek_status_id != $rule['cdek_status_id']) {
				continue;
			}
				
			$order_info = $this->model_sale_order->getOrder($dispatch_info['order_id']);
			
			if ($order_info) {
				
                $comment = strtr($rule['comment'], array('{dispatch_number}' => $dispatch_info['cdek_number'],
                    '{order_id}' => $dispatch_info['order_id'],
                    '{status_city_name}' => $dispatch_info['status_city_name']));

				$this->statusApi((int)$dispatch_info['order_id'], (int)$rule['order_status_id'], (int)$rule['notify'], $comment);
			}
			
		}
		
	}

	public function statusApi($order_id, $status_id, $notify, $comment) {
		$this->load->model('user/api');
		$api_info = $this->model_user_api->getApi($this->config->get('config_api_id'));

		require_once DIR_SYSTEM . 'library/cdek_integrator/ocapi.php';

		// Prefer HTTPS_CATALOG if defined (production sites should use HTTPS)
		// Fall back to HTTP_CATALOG for local/dev environments
		if (defined('HTTPS_CATALOG')) {
			$site_url = rtrim(HTTPS_CATALOG, '/');
		} else {
			$site_url = rtrim(HTTP_CATALOG, '/');
		}

		$oc = new OpenCart\OpenCart($site_url);
		$token = $oc->login($api_info['username'], $api_info['key']);

		if (empty($token)) {
			$last_error = $oc->getLastError();
			$this->log->write('Не удалось авторизоваться в OpenCart API (проверьте настройки OpenCart)');
			$this->log->write('Site_url: ' . $site_url . ' | Username: ' . $api_info['username'] . ' | order_id: ' . $order_id . ' | status_id: ' . $status_id);
			if ($last_error) {
				$this->log->write('API Error: ' . print_r($last_error, true));
			}
			$this->log->write('Проверьте: 1) IP сервера в ocus_api_ip, 2) HTTP_CATALOG в config.php, 3) API ключ');
			ob_start();
    		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    		$trace = ob_get_clean();
			$this->log->write('TRACE: ' . $trace);
		}

		$oc->order->setToken($token);
		$response = $oc->order->history((int)$order_id, (int)$status_id, $notify, false, $comment);
		return $response;
	}
	
	public function getOrderToSdek($order_id)
	{
		$sql = "SELECT * FROM `" . DB_PREFIX . "order_to_sdek` WHERE `order_id` = '".$order_id."' LIMIT 1";
		$query = $this->db->query($sql);
		if($query->num_rows)
		{
			return $query->row;
		}
		else
		{
			return false;
		}
	}

	public function getCityById($id) {		
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "cdek_city` WHERE `id` = '".$id."'")->row;
	}
	
	public function install() {

		$columnIsExist = $this->db->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' AND TABLE_NAME = '" . DB_PREFIX . "order' AND COLUMN_NAME = 'pvz_cdek'");

		if (empty($columnIsExist->rows)) {
			$this->db->query("ALTER TABLE `" . DB_PREFIX . "order` ADD `pvz_cdek` VARCHAR(128) NOT NULL AFTER `comment`");
		}

        /* Запрос на создание записи ip адреса сервера, для исключения ошибки с изменением статуса заказа в зависимости
от статуса СДЭК проставленный в настройках отгрузки */
        $host = gethostname();
        $ip = gethostbyname($host);

        $sql = $this->db->query("SELECT * FROM `" . DB_PREFIX . "api_ip` WHERE `ip` = '" . $ip ."'")->row;

        if (empty($sql)) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "api_ip` (`api_id`, `ip`) VALUES (1,'" . $ip ."')");
        }
		
		$sql  = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cdek_city` ( ";
		$sql .= "`id` varchar(11) NOT NULL, ";
		$sql .= "`name` varchar(64) NOT NULL, ";
		$sql .= "`cityName` varchar(64) NOT NULL, ";
		$sql .= "`regionName` varchar(64) NOT NULL, ";
		$sql .= "`center` tinyint(1) NOT NULL DEFAULT '0', ";
		$sql .= "`payment_limit` float(5,4) NOT NULL, ";
		$sql .= "PRIMARY KEY (`id`) ";
		$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8";
		
		$this->db->query($sql);
		$this->load->model('tool/cdektool');
		$this->model_tool_cdektool->importCdekCities();
		
		$sql  = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cdek_dispatch` ( ";
		$sql .= "`dispatch_id` int(11) NOT NULL AUTO_INCREMENT, ";
		$sql .= "`dispatch_number` varchar(64) NOT NULL, ";
        $sql .= "`cdek_number` varchar(45) NOT NULL, ";
		$sql .= "`date` varchar(32) NOT NULL, ";
		$sql .= "`server_date` varchar(32) NOT NULL, ";
		$sql .= "PRIMARY KEY (`dispatch_id`) ";
		$sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8";
		
		$this->db->query($sql);
		
		$sql  = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "cdek_order` ( ";
		$sql .= "`order_id` int(11) NOT NULL, ";
		$sql .= "`dispatch_id` int(11) NOT NULL, ";
		$sql .= "`act_number` varchar(20) DEFAULT NULL, ";
		$sql .= "`dispatch_number` varchar(64) NOT NULL, ";
        $sql .= "`cdek_number` varchar(45) NOT NULL, ";
		$sql .= "`return_dispatch_number` varchar(128) NOT NULL, ";
		$sql .= "`city_id` int(11) NOT NULL, ";
		$sql .= "`city_name` varchar(128) NOT NULL, ";
		$sql .= "`city_postcode` int(6) DEFAULT NULL, ";
		$sql .= "`recipient_city_id` int(11) NOT NULL, ";
		$sql .= "`recipient_city_name` varchar(128) NOT NULL, ";
		$sql .= "`recipient_city_postcode` int(6) DEFAULT NULL, ";
		$sql .= "`recipient_name` varchar(128) NOT NULL, ";
		$sql .= "`recipient_email` varchar(255) DEFAULT NULL, ";
		$sql .= "`phone` varchar(50) NOT NULL, ";
		$sql .= "`tariff_id` int(4) NOT NULL, ";
		$sql .= "`mode_id` int(1) NOT NULL, ";
		$sql .= "`status_id` varchar(50) NOT NULL, ";
		$sql .= "`reason_id` int(11) DEFAULT '0', ";
		$sql .= "`delay_id` int(4) DEFAULT NULL, ";
		$sql .= "`delivery_recipient_cost` float(15,4) DEFAULT '0.0000', ";
		$sql .= "`cod` float(8,4) DEFAULT '0.0000', ";
		$sql .= "`cod_fact` float(8,4) DEFAULT '0.0000', ";
		$sql .= "`comment` varchar(255) DEFAULT NULL, ";
		$sql .= "`seller_name` varchar(255) DEFAULT NULL, ";
		$sql .= "`address_street` varchar(50) DEFAULT NULL, ";
		$sql .= "`address_house` varchar(30) DEFAULT NULL, ";
		$sql .= "`address_flat` varchar(10) DEFAULT NULL, ";
		$sql .= "`address_pvz_code` varchar(10) DEFAULT NULL, ";
		$sql .= "`delivery_cost` float(8,4) DEFAULT '0.0000', ";
		$sql .= "`delivery_last_change` varchar(32) DEFAULT NULL, ";
		$sql .= "`delivery_date` varchar(32) NOT NULL, ";
		$sql .= "`delivery_recipient_name` varchar(50) DEFAULT NULL, ";
		$sql .= "`currency` varchar(3) DEFAULT 'RUB', ";
		$sql .= "`currency_cod` varchar(3) DEFAULT 'RUB', ";
		$sql .= "`last_exchange` varchar(32) NOT NULL, ";
		$sql .= "PRIMARY KEY (`order_id`) ";
		$sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		
		$this->db->query($sql);

		// NOTE: History tables removed - data is now fetched from CDEK API on-demand
		// Removed tables: cdek_order_add_service, cdek_order_call, cdek_order_call_history_*,
		// cdek_order_courier, cdek_order_delay_history, cdek_order_package,
		// cdek_order_package_item, cdek_order_reason, cdek_order_schedule,
		// cdek_order_schedule_delay, cdek_order_status_history

		$sql  = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_to_sdek` ( ";
		$sql .= "`order_to_sdek_id` int(11) NOT NULL AUTO_INCREMENT, ";
		$sql .= "`order_id` int(11) NOT NULL, ";
		$sql .= "`cityId` int(11) NOT NULL, ";
		$sql .= "`pvz_code` varchar(255) NOT NULL, ";
		$sql .= "PRIMARY KEY (`order_to_sdek_id`), ";
		$sql .= "UNIQUE KEY `order_id` (`order_id`) ";
		$sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

		$this->db->query($sql);

		$this->load->model('setting/event');

		$this->model_setting_event->addEvent('cdek_shipping_add_scripts', 'catalog/controller/common/header/before','event/cdekshipping/addScripts');

		$this->model_setting_event->addEvent('cdek_shipping_success_order', 'catalog/controller/checkout/success/before','event/cdekshipping/successOrder');

		$this->model_setting_event->addEvent('cdek_shipping_order_create', 'catalog/model/checkout/order/addOrder/after','event/cdekshipping/orderCreate');

		$this->model_setting_event->addEvent('cdek_shipping_order_history', 'catalog/model/checkout/order/addOrderHistory/before','event/cdekshipping/orderHistory');

		$this->model_setting_event->addEvent('cdek_shipping_check_tariff_pvz', 'catalog/controller/checkout/shipping_method/save/before','event/cdekshipping/checkTariffPvz');
	}

	public function deleteCities() {
		$this->db->query("DELETE FROM " . DB_PREFIX . "cdek_city");
	}

	public function addCities($data) {
		if (!empty($data) && count($data) >= 1) {
			$sql = "INSERT INTO " . DB_PREFIX . "cdek_city (id, name, cityName, regionName, center, payment_limit) VALUES ";
			foreach ($data as $value) {				
				if (!next($data)) {
					$sql .= "('" . (int)$value['id'] . "', '" . $this->db->escape($value['name']) . "', '" . $this->db->escape($value['cityName']) . "', '" . $this->db->escape($value['regionName']) . "', '0', '" . $value['payment_limit'] . "');";
				} else {
					$sql .= "('" . (int)$value['id'] . "', '" . $this->db->escape($value['name']) . "', '" . $this->db->escape($value['cityName']) . "', '" . $this->db->escape($value['regionName']) . "', '0', '" . $value['payment_limit'] . "'), ";
				}				
			}
			$this->db->query($sql);
		}
	}
	
	public function uninstall() {

        $this->db->query("ALTER TABLE `" . DB_PREFIX . "order` DROP `pvz_cdek`");

        $sql  = "DROP TABLE IF EXISTS `" . DB_PREFIX . "cdek_dispatch`, ";
		$sql .= "`" . DB_PREFIX . "cdek_city`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_add_service`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_call`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_call_history_delay`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_call_history_fail`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_call_history_good`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_courier`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_delay_history`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_package`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_package_item`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_reason`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_schedule`, ";
		$sql .= "`" . DB_PREFIX . "cdek_order_schedule_delay`, ";
//		$sql .= "`" . DB_PREFIX . "cdek_order_status_history`;";
		
		$this->db->query($sql);

		$this->load->model('setting/event');

		$this->model_setting_event->deleteEvent('cdek_shipping_add_scripts');
		$this->model_setting_event->deleteEvent('cdek_shipping_success_order');
		$this->model_setting_event->deleteEvent('cdek_shipping_order_create');
		$this->model_setting_event->deleteEvent('cdek_shipping_order_history');
		$this->model_setting_event->deleteEvent('cdek_shipping_check_tariff_pvz');
	}

	public function getDispatchToSync() {
		
		$exchange_interval = 21600; // 6 часов

		$filter_statuses = array(
			4,	// Вручен
			5,	// Не вручен, возврат
			16,	// Возвращен на склад отправителя
			2	// Удален
		);
		
		$sql  = "SELECT o.* ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "INNER JOIN `" . DB_PREFIX . "cdek_dispatch` d ON (o.dispatch_id = d.dispatch_id)";
		$sql .= "WHERE (UNIX_TIMESTAMP() - o.last_exchange) > " . $exchange_interval . " AND ";
		$sql .= "o.status_id NOT IN (" . implode(',', $filter_statuses) . ") ";
		$sql .= "ORDER BY o.last_exchange, d.date ";
		$sql .= "LIMIT 1";
		
		
		return $this->db->query($sql)->row;
	}

	public function getDispatchesToSync() {


		$filter_statuses = array(
			'DELIVERED',	// Вручен бывш 4
			'NOT_DELIVERED',	// Не вручен, возврат 5
			'DELETED'	// Удален 2
			);

		$sql  = "SELECT o.* ";
		$sql .= "FROM `" . DB_PREFIX . "cdek_order` o ";
		$sql .= "INNER JOIN `" . DB_PREFIX . "cdek_dispatch` d ON (o.dispatch_id = d.dispatch_id) ";
		$sql .= "WHERE 1 ";
		$sql .= "AND o.status_id NOT IN ('" . implode('\',\'', $filter_statuses) . "') ";
		$sql .= "ORDER BY o.last_exchange, d.date";


		return $this->db->query($sql)->rows;
	}
	
	private function getSetting() {
		return $this->config->get('cdek_integrator_setting');
	}
}
?>