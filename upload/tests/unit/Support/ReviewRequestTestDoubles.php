<?php

namespace Tests\Unit\Support;

final class ArrayQueryResult {
	public $row;
	public $rows;
	public $num_rows;

	public function __construct(array $rows = array()) {
		$this->rows = array_values($rows);
		$this->num_rows = count($this->rows);
		$this->row = $this->num_rows ? $this->rows[0] : array();
	}
}

final class InMemoryReviewRequestDb {
	private $queue_rows = array();
	private $customer_rows = array();
	private $next_review_request_id = 1;
	private $next_customer_id = 1;
	private $now;

	public function __construct($now = '2026-04-20 10:30:00') {
		$this->now = new \DateTimeImmutable($now);
	}

	public function escape($value) {
		return addslashes((string)$value);
	}

	public function query($sql) {
		$sql = preg_replace('/\s+/', ' ', trim((string)$sql));

		if (strpos($sql, 'CREATE TABLE IF NOT EXISTS') === 0 || strpos($sql, 'DROP TABLE IF EXISTS') === 0) {
			return new ArrayQueryResult();
		}

		if (strpos($sql, "INSERT INTO `" . DB_PREFIX . "review_request_queue` SET") === 0) {
			return $this->handleInsertQueueRow($sql);
		}

		if (preg_match("/SELECT `review_request_id` FROM `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` WHERE `order_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleSelectQueuedOrder((int)$matches[1]);
		}

		if (preg_match("/SELECT \\* FROM `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` WHERE `status` = 'pending' AND `date_send_after` <= NOW\\(\\) ORDER BY `review_request_id` ASC LIMIT (\\d+)/", $sql, $matches)) {
			return $this->handleSelectDueRequests((int)$matches[1]);
		}

		if (preg_match("/SELECT \\* FROM `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` WHERE `review_request_id` = '(\\d+)' LIMIT 1/", $sql, $matches)) {
			return $this->handleSelectQueueById((int)$matches[1]);
		}

		if (preg_match("/SELECT `send_attempts` FROM `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` WHERE `review_request_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleSelectSendAttempts((int)$matches[1]);
		}

		if (preg_match("/SELECT \\* FROM `" . preg_quote(DB_PREFIX, '/') . "review_request_customer` WHERE `email` = '((?:\\\\.|[^'])*)' LIMIT 1/", $sql, $matches)) {
			return $this->handleSelectCustomerState(stripcslashes($matches[1]));
		}

		if (preg_match("/UPDATE `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` SET `status` = 'sent', `send_attempts` = `send_attempts` \\+ 1, `last_error` = '', `date_sent` = NOW\\(\\), `date_modified` = NOW\\(\\) WHERE `review_request_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleMarkSent((int)$matches[1]);
		}

		if (preg_match("/UPDATE `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` SET `status` = 'sent', `last_error` = '((?:\\\\.|[^'])*)', `date_sent` = NOW\\(\\), `date_modified` = NOW\\(\\) WHERE `review_request_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleMarkSkipped((int)$matches[2], stripcslashes($matches[1]));
		}

		if (preg_match("/UPDATE `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` SET `status` = 'failed', `send_attempts` = '(\\d+)', `last_error` = '((?:\\\\.|[^'])*)', `date_modified` = NOW\\(\\) WHERE `review_request_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleMarkFailed((int)$matches[3], (int)$matches[1], stripcslashes($matches[2]));
		}

		if (preg_match("/UPDATE `" . preg_quote(DB_PREFIX, '/') . "review_request_queue` SET `status` = 'pending', `send_attempts` = '(\\d+)', `last_error` = '((?:\\\\.|[^'])*)', `date_send_after` = DATE_ADD\\(NOW\\(\\), INTERVAL 1 DAY\\), `date_modified` = NOW\\(\\) WHERE `review_request_id` = '(\\d+)'/", $sql, $matches)) {
			return $this->handleMarkRetry((int)$matches[3], (int)$matches[1], stripcslashes($matches[2]));
		}

		if (strpos($sql, "INSERT INTO `" . DB_PREFIX . "review_request_customer` SET") === 0) {
			return $this->handleInsertCustomerRow($sql);
		}

		if (strpos($sql, "UPDATE `" . DB_PREFIX . "review_request_customer` SET") === 0) {
			return $this->handleUpdateCustomerRow($sql);
		}

		throw new \RuntimeException('Unhandled SQL in test double: ' . $sql);
	}

	public function seedQueueRow(array $row) {
		$review_request_id = isset($row['review_request_id']) ? (int)$row['review_request_id'] : $this->next_review_request_id;
		$defaults = array(
			'review_request_id' => $review_request_id,
			'order_id' => 0,
			'customer_id' => 0,
			'store_id' => 0,
			'language_code' => '',
			'email' => '',
			'order_status_id' => 0,
			'status' => 'pending',
			'send_attempts' => 0,
			'last_error' => '',
			'date_send_after' => $this->formatNow(),
			'date_sent' => null,
			'date_added' => $this->formatNow(),
			'date_modified' => $this->formatNow()
		);

		$this->queue_rows[$review_request_id] = array_merge($defaults, $row, array('review_request_id' => $review_request_id));
		$this->next_review_request_id = max($this->next_review_request_id, $review_request_id + 1);

		return $review_request_id;
	}

	public function getQueueRow($review_request_id) {
		if (!isset($this->queue_rows[$review_request_id])) {
			throw new \RuntimeException('Unknown queue row #' . (int)$review_request_id);
		}

		return $this->queue_rows[$review_request_id];
	}

	public function seedCustomerState(array $row) {
		$email = isset($row['email']) ? strtolower($row['email']) : '';

		if (!$email) {
			throw new \RuntimeException('Customer state email is required');
		}

		$review_request_customer_id = isset($row['review_request_customer_id']) ? (int)$row['review_request_customer_id'] : $this->next_customer_id;
		$defaults = array(
			'review_request_customer_id' => $review_request_customer_id,
			'email' => $email,
			'customer_id' => 0,
			'last_order_id' => 0,
			'last_org_request_sent_at' => null,
			'last_org_click_at' => null,
			'last_org_click_channel' => '',
			'org_review_suppressed_until' => null,
			'date_added' => $this->formatNow(),
			'date_modified' => $this->formatNow()
		);

		$this->customer_rows[$email] = array_merge($defaults, $row, array('review_request_customer_id' => $review_request_customer_id, 'email' => $email));
		$this->next_customer_id = max($this->next_customer_id, $review_request_customer_id + 1);

		return $review_request_customer_id;
	}

	public function getCustomerState($email) {
		$email = strtolower((string)$email);

		if (!isset($this->customer_rows[$email])) {
			throw new \RuntimeException('Unknown customer state for ' . $email);
		}

		return $this->customer_rows[$email];
	}

	public function findQueueRowByOrderId($order_id) {
		foreach ($this->queue_rows as $queue_row) {
			if ((int)$queue_row['order_id'] === (int)$order_id) {
				return $queue_row;
			}
		}

		return null;
	}

	public function getQueueRows() {
		$rows = array_values($this->queue_rows);

		usort($rows, function($left, $right) {
			return (int)$left['review_request_id'] <=> (int)$right['review_request_id'];
		});

		return $rows;
	}

	private function handleInsertQueueRow($sql) {
		$delay_days = $this->extractIntFromSql($sql, '/DATE_ADD\\(NOW\\(\\), INTERVAL (\\d+) DAY\\)/');

		$this->seedQueueRow(array(
			'order_id' => $this->extractFieldInt($sql, 'order_id'),
			'customer_id' => $this->extractFieldInt($sql, 'customer_id'),
			'store_id' => $this->extractFieldInt($sql, 'store_id'),
			'language_code' => $this->extractFieldString($sql, 'language_code'),
			'email' => $this->extractFieldString($sql, 'email'),
			'order_status_id' => $this->extractFieldInt($sql, 'order_status_id'),
			'status' => 'pending',
			'send_attempts' => 0,
			'date_send_after' => $this->formatDate($this->now->modify('+' . $delay_days . ' day')),
			'date_added' => $this->formatNow(),
			'date_modified' => $this->formatNow()
		));

		return new ArrayQueryResult();
	}

	private function handleSelectQueuedOrder($order_id) {
		$queue_row = $this->findQueueRowByOrderId($order_id);

		if (!$queue_row) {
			return new ArrayQueryResult();
		}

		return new ArrayQueryResult(array(
			array('review_request_id' => $queue_row['review_request_id'])
		));
	}

	private function handleSelectQueueById($review_request_id) {
		if (!isset($this->queue_rows[$review_request_id])) {
			return new ArrayQueryResult();
		}

		return new ArrayQueryResult(array($this->queue_rows[$review_request_id]));
	}

	private function handleSelectDueRequests($limit) {
		$due_rows = array_filter($this->getQueueRows(), function($queue_row) {
			return $queue_row['status'] === 'pending' && strtotime($queue_row['date_send_after']) <= $this->now->getTimestamp();
		});

		$due_rows = array_slice(array_values($due_rows), 0, $limit);

		return new ArrayQueryResult($due_rows);
	}

	private function handleSelectSendAttempts($review_request_id) {
		if (!isset($this->queue_rows[$review_request_id])) {
			return new ArrayQueryResult();
		}

		return new ArrayQueryResult(array(
			array('send_attempts' => $this->queue_rows[$review_request_id]['send_attempts'])
		));
	}

	private function handleSelectCustomerState($email) {
		$email = strtolower($email);

		if (!isset($this->customer_rows[$email])) {
			return new ArrayQueryResult();
		}

		return new ArrayQueryResult(array($this->customer_rows[$email]));
	}

	private function handleMarkSent($review_request_id) {
		if (isset($this->queue_rows[$review_request_id])) {
			$this->queue_rows[$review_request_id]['status'] = 'sent';
			$this->queue_rows[$review_request_id]['send_attempts'] = (int)$this->queue_rows[$review_request_id]['send_attempts'] + 1;
			$this->queue_rows[$review_request_id]['last_error'] = '';
			$this->queue_rows[$review_request_id]['date_sent'] = $this->formatNow();
			$this->queue_rows[$review_request_id]['date_modified'] = $this->formatNow();
		}

		return new ArrayQueryResult();
	}

	private function handleMarkSkipped($review_request_id, $reason) {
		if (isset($this->queue_rows[$review_request_id])) {
			$this->queue_rows[$review_request_id]['status'] = 'sent';
			$this->queue_rows[$review_request_id]['last_error'] = $reason;
			$this->queue_rows[$review_request_id]['date_sent'] = $this->formatNow();
			$this->queue_rows[$review_request_id]['date_modified'] = $this->formatNow();
		}

		return new ArrayQueryResult();
	}

	private function handleMarkFailed($review_request_id, $send_attempts, $error) {
		if (isset($this->queue_rows[$review_request_id])) {
			$this->queue_rows[$review_request_id]['status'] = 'failed';
			$this->queue_rows[$review_request_id]['send_attempts'] = $send_attempts;
			$this->queue_rows[$review_request_id]['last_error'] = $error;
			$this->queue_rows[$review_request_id]['date_modified'] = $this->formatNow();
		}

		return new ArrayQueryResult();
	}

	private function handleMarkRetry($review_request_id, $send_attempts, $error) {
		if (isset($this->queue_rows[$review_request_id])) {
			$this->queue_rows[$review_request_id]['status'] = 'pending';
			$this->queue_rows[$review_request_id]['send_attempts'] = $send_attempts;
			$this->queue_rows[$review_request_id]['last_error'] = $error;
			$this->queue_rows[$review_request_id]['date_send_after'] = $this->formatDate($this->now->modify('+1 day'));
			$this->queue_rows[$review_request_id]['date_modified'] = $this->formatNow();
		}

		return new ArrayQueryResult();
	}

	private function handleInsertCustomerRow($sql) {
		$email = strtolower($this->extractFieldString($sql, 'email'));
		$this->seedCustomerState(array(
			'email' => $email,
			'customer_id' => $this->extractFieldInt($sql, 'customer_id'),
			'last_order_id' => $this->extractFieldInt($sql, 'last_order_id'),
			'last_org_request_sent_at' => strpos($sql, '`last_org_request_sent_at` = NOW()') !== false ? $this->formatNow() : null,
			'last_org_click_at' => strpos($sql, '`last_org_click_at` = NOW()') !== false ? $this->formatNow() : null,
			'last_org_click_channel' => $this->extractOptionalFieldString($sql, 'last_org_click_channel', ''),
			'org_review_suppressed_until' => $this->extractSuppressedUntil($sql),
			'date_added' => $this->formatNow(),
			'date_modified' => $this->formatNow()
		));

		return new ArrayQueryResult();
	}

	private function handleUpdateCustomerRow($sql) {
		$email = strtolower($this->extractWhereEmail($sql));

		if (!isset($this->customer_rows[$email])) {
			$this->seedCustomerState(array('email' => $email));
		}

		$row = $this->customer_rows[$email];

		if (preg_match("/`customer_id` = '(\\d+)'/", $sql, $matches)) {
			$row['customer_id'] = (int)$matches[1];
		}

		if (preg_match("/`last_order_id` = '(\\d+)'/", $sql, $matches)) {
			$row['last_order_id'] = (int)$matches[1];
		}

		if (strpos($sql, '`last_org_request_sent_at` = NOW()') !== false) {
			$row['last_org_request_sent_at'] = $this->formatNow();
		}

		if (strpos($sql, '`last_org_click_at` = NOW()') !== false) {
			$row['last_org_click_at'] = $this->formatNow();
		}

		if (preg_match("/`last_org_click_channel` = '((?:\\\\.|[^'])*)'/", $sql, $matches)) {
			$row['last_org_click_channel'] = stripcslashes($matches[1]);
		}

		if (strpos($sql, '`org_review_suppressed_until` = ') !== false) {
			$row['org_review_suppressed_until'] = $this->extractSuppressedUntil($sql);
		}

		$row['date_modified'] = $this->formatNow();
		$this->customer_rows[$email] = $row;

		return new ArrayQueryResult();
	}

	private function extractFieldInt($sql, $field) {
		return (int)$this->extractFieldString($sql, $field);
	}

	private function extractFieldString($sql, $field) {
		$pattern = "/`" . preg_quote($field, '/') . "` = '((?:\\\\.|[^'])*)'/";

		if (!preg_match($pattern, $sql, $matches)) {
			throw new \RuntimeException('Unable to extract field "' . $field . '" from SQL: ' . $sql);
		}

		return stripcslashes($matches[1]);
	}

	private function extractOptionalFieldString($sql, $field, $default = '') {
		$pattern = "/`" . preg_quote($field, '/') . "` = '((?:\\\\.|[^'])*)'/";

		if (!preg_match($pattern, $sql, $matches)) {
			return $default;
		}

		return stripcslashes($matches[1]);
	}

	private function extractIntFromSql($sql, $pattern) {
		if (!preg_match($pattern, $sql, $matches)) {
			throw new \RuntimeException('Unable to extract integer from SQL: ' . $sql);
		}

		return (int)$matches[1];
	}

	private function extractWhereEmail($sql) {
		if (!preg_match("/WHERE `email` = '((?:\\\\.|[^'])*)'/", $sql, $matches)) {
			throw new \RuntimeException('Unable to extract WHERE email from SQL: ' . $sql);
		}

		return stripcslashes($matches[1]);
	}

	private function extractSuppressedUntil($sql) {
		if (preg_match('/`org_review_suppressed_until` = DATE_ADD\\(NOW\\(\\), INTERVAL (\\d+) DAY\\)/', $sql, $matches)) {
			return $this->formatDate($this->now->modify('+' . (int)$matches[1] . ' day'));
		}

		if (strpos($sql, '`org_review_suppressed_until` = NULL') !== false) {
			return null;
		}

		return null;
	}

	private function formatNow() {
		return $this->formatDate($this->now);
	}

	private function formatDate(\DateTimeImmutable $date_time) {
		return $date_time->format('Y-m-d H:i:s');
	}
}

final class NoOpLoader {
	public function model($route) {
		return null;
	}

	public function controller($route, $data = array()) {
		return '';
	}

	public function view($route, $data = array()) {
		return '';
	}
}

final class FakeLanguage {
	private $translations;

	public function __construct(array $translations = array()) {
		$this->translations = $translations;
	}

	public function get($key) {
		return isset($this->translations[$key]) ? $this->translations[$key] : $key;
	}

	public function load($route) {
		return $this->translations;
	}
}

final class FakeUser {
	private $can_modify;

	public function __construct($can_modify = true) {
		$this->can_modify = (bool)$can_modify;
	}

	public function hasPermission($action, $route) {
		return $this->can_modify;
	}
}

final class FakeOrderModel {
	private $order_products;

	public function __construct(array $order_products) {
		$this->order_products = $order_products;
	}

	public function getOrderProducts($order_id) {
		return $this->order_products;
	}
}

trait InvokesNonPublicMembers {
	private function invokeMethod($object, $method, array $arguments = array()) {
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs($object, $arguments);
	}

	private function readProperty($object, $property) {
		$reflection = new \ReflectionProperty($object, $property);
		$reflection->setAccessible(true);

		return $reflection->getValue($object);
	}
}
