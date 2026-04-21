<?php

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\InMemoryReviewRequestDb;
use Tests\Unit\Support\NoOpLoader;

require_once dirname(__DIR__, 3) . '/Support/ReviewRequestTestDoubles.php';
class AdminReviewRequestModelTest extends TestCase {
	private $config;
	private $db;
	private $model;

	protected function setUp(): void {
		parent::setUp();

		if (!class_exists(__NAMESPACE__ . '\\AdminReviewRequestModelUnderTest', false)) {
			$source = file_get_contents(dirname(__DIR__, 5) . '/admin/model/extension/module/review_request.php');
			$source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
			$source = str_replace('class ModelExtensionModuleReviewRequest extends Model', 'class AdminReviewRequestModelUnderTest extends \\Model', $source);
			$source = 'namespace ' . __NAMESPACE__ . ' { ' . $source . ' }';

			eval($source);
		}

		$registry = new \Registry();
		$this->config = new \Config();
		$this->db = new InMemoryReviewRequestDb('2026-04-20 10:30:00');

		$registry->set('config', $this->config);
		$registry->set('db', $this->db);
		$registry->set('load', new NoOpLoader());

		$class_name = __NAMESPACE__ . '\\AdminReviewRequestModelUnderTest';
		$this->model = new $class_name($registry);
	}

	public function testGetDueRequestsReturnsOnlyPendingRowsWhoseSendTimeHasArrived(): void {
		$this->db->seedQueueRow(array(
			'review_request_id' => 1,
			'order_id' => 1001,
			'status' => 'pending',
			'date_send_after' => '2026-04-20 09:00:00'
		));
		$this->db->seedQueueRow(array(
			'review_request_id' => 2,
			'order_id' => 1002,
			'status' => 'pending',
			'date_send_after' => '2026-04-20 12:00:00'
		));
		$this->db->seedQueueRow(array(
			'review_request_id' => 3,
			'order_id' => 1003,
			'status' => 'sent',
			'date_send_after' => '2026-04-20 08:00:00'
		));

		$requests = $this->model->getDueRequests(10);

		$this->assertCount(1, $requests);
		$this->assertSame(1, $requests[0]['review_request_id']);
		$this->assertSame(1001, $requests[0]['order_id']);
	}

	public function testMarkSentMovesQueueRowToSentStateAndClearsPreviousError(): void {
		$this->db->seedQueueRow(array(
			'review_request_id' => 11,
			'order_id' => 2011,
			'status' => 'pending',
			'send_attempts' => 0,
			'last_error' => 'temporary failure'
		));

		$this->model->markSent(11);

		$queue_row = $this->db->getQueueRow(11);

		$this->assertSame('sent', $queue_row['status']);
		$this->assertSame(1, $queue_row['send_attempts']);
		$this->assertSame('', $queue_row['last_error']);
		$this->assertSame('2026-04-20 10:30:00', $queue_row['date_sent']);
	}

	public function testMarkRetryReschedulesFirstFailuresAndFailsOnThirdAttempt(): void {
		$this->db->seedQueueRow(array(
			'review_request_id' => 21,
			'order_id' => 3021,
			'status' => 'pending',
			'send_attempts' => 0,
			'date_send_after' => '2026-04-20 09:00:00'
		));
		$this->db->seedQueueRow(array(
			'review_request_id' => 22,
			'order_id' => 3022,
			'status' => 'pending',
			'send_attempts' => 2,
			'date_send_after' => '2026-04-20 09:00:00'
		));

		$this->model->markRetry(21, 'SMTP timeout');
		$this->model->markRetry(22, 'SMTP timeout');

		$retry_row = $this->db->getQueueRow(21);
		$failed_row = $this->db->getQueueRow(22);

		$this->assertSame('pending', $retry_row['status']);
		$this->assertSame(1, $retry_row['send_attempts']);
		$this->assertSame('SMTP timeout', $retry_row['last_error']);
		$this->assertSame('2026-04-21 10:30:00', $retry_row['date_send_after']);
		$this->assertSame('failed', $failed_row['status']);
		$this->assertSame(3, $failed_row['send_attempts']);
		$this->assertSame('SMTP timeout', $failed_row['last_error']);
	}

	public function testMarkSkippedClosesQueueRowWithoutIncrementingAttempts(): void {
		$this->db->seedQueueRow(array(
			'review_request_id' => 31,
			'order_id' => 4031,
			'status' => 'pending',
			'send_attempts' => 0
		));

		$this->model->markSkipped(31, 'organization review is in cooldown and no product reviews remain');

		$queue_row = $this->db->getQueueRow(31);

		$this->assertSame('sent', $queue_row['status']);
		$this->assertSame(0, $queue_row['send_attempts']);
		$this->assertStringContainsString('Skipped:', $queue_row['last_error']);
		$this->assertSame('2026-04-20 10:30:00', $queue_row['date_sent']);
	}

	public function testMarkOrganizationReviewSentCreatesCooldownState(): void {
		$this->config->set('module_review_request_org_review_cooldown_days', 180);

		$this->assertTrue($this->model->canAskOrganizationReview('buyer@example.com'));

		$this->model->markOrganizationReviewSent('buyer@example.com', 15, 7001);

		$state = $this->db->getCustomerState('buyer@example.com');

		$this->assertSame(15, $state['customer_id']);
		$this->assertSame(7001, $state['last_order_id']);
		$this->assertSame('2026-04-20 10:30:00', $state['last_org_request_sent_at']);
		$this->assertSame('2026-10-17 10:30:00', $state['org_review_suppressed_until']);
		$this->assertFalse($this->model->canAskOrganizationReview('buyer@example.com'));
	}
}
