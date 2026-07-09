<?php
/*
CREATE TABLE IF NOT EXISTS `session` (
  `session_id` varchar(32) NOT NULL,
  `data` text NOT NULL,
  `expire` datetime NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
namespace Session;
final class DB {
	private $db;
	private $config;
	
	public function __construct($registry) {
		$this->db = $registry->get('db');
		$this->config = $registry->get('config');
	}
	
	public function read($session_id) {
		$query = $this->db->query("SELECT `data` FROM `" . DB_PREFIX . "session` WHERE session_id = '" . $this->db->escape($session_id) . "' AND expire > '" . $this->db->escape(date('Y-m-d H:i:s')) . "'");
		
		if ($query->num_rows) {
			return json_decode($query->row['data'], true);
		} else {
			return array();
		}
	}
	
	public function write($session_id, $data) {
		if ($session_id) {
			$this->db->query("REPLACE INTO `" . DB_PREFIX . "session` SET session_id = '" . $this->db->escape($session_id) . "', `data` = '" . $this->db->escape(json_encode($data)) . "', expire = '" . $this->db->escape(date('Y-m-d H:i:s', time() + $this->getSessionExpire())) . "'");
		}
		
		return true;
	}
	
	public function destroy($session_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "session` WHERE session_id = '" . $this->db->escape($session_id) . "'");
		
		return true;
	}
	
	public function gc() {
		$gc_divisor = $this->getGcDivisor();
		$gc_probability = $this->getGcProbability();

		if (mt_rand(1, $gc_divisor) <= min($gc_probability, $gc_divisor)) {
			$this->db->query("DELETE FROM `" . DB_PREFIX . "session` WHERE expire < '" . $this->db->escape(date('Y-m-d H:i:s')) . "'");
		}
		
		return true;
	}

	private function getSessionExpire() {
		$session_expire = $this->getConfigInt('config_session_expire');

		if ($session_expire > 0) {
			return $session_expire;
		}

		$session_expire = $this->getConfigInt('session_expire');

		if ($session_expire > 0) {
			return $session_expire;
		}

		return 86400;
	}

	private function getGcDivisor() {
		$gc_divisor = $this->getConfigInt('session_divisor');

		if ($gc_divisor > 0) {
			return $gc_divisor;
		}

		$gc_divisor = (int)ini_get('session.gc_divisor');

		if ($gc_divisor > 0) {
			return $gc_divisor;
		}

		return 100;
	}

	private function getGcProbability() {
		$gc_probability = $this->getConfigInt('session_probability');

		if ($gc_probability > 0) {
			return $gc_probability;
		}

		$gc_probability = (int)ini_get('session.gc_probability');

		if ($gc_probability > 0) {
			return $gc_probability;
		}

		return 1;
	}

	private function getConfigInt($key) {
		if ($this->config) {
			$value = (int)$this->config->get($key);

			if ($value > 0) {
				return $value;
			}
		}

		return 0;
	}
}
