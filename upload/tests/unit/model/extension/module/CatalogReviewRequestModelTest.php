<?php

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\InMemoryReviewRequestDb;
use Tests\Unit\Support\NoOpLoader;

require_once dirname(__DIR__, 3) . '/Support/ReviewRequestTestDoubles.php';
class CatalogReviewRequestModelTest extends TestCase {
	private $config;
	private $db;
	private $model;

	protected function setUp(): void {
		parent::setUp();

		if (!class_exists(__NAMESPACE__ . '\\CatalogReviewRequestModelUnderTest', false)) {
			$source = file_get_contents(dirname(__DIR__, 5) . '/catalog/model/extension/module/review_request.php');
			$source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
			$source = str_replace('class ModelExtensionModuleReviewRequest extends Model', 'class CatalogReviewRequestModelUnderTest extends \\Model', $source);
			$source = 'namespace ' . __NAMESPACE__ . ' { ' . $source . ' }';

			eval($source);
		}

		$registry = new \Registry();
		$this->config = new \Config();
		$this->db = new InMemoryReviewRequestDb('2026-04-20 10:30:00');

		$registry->set('config', $this->config);
		$registry->set('db', $this->db);
		$registry->set('load', new NoOpLoader());

		$class_name = __NAMESPACE__ . '\\CatalogReviewRequestModelUnderTest';
		$this->model = new $class_name($registry);
	}

	public function testGetChannelsKeepsWidgetOnlyGoogleAndNormalizesYandexReference(): void {
		$this->config->set('module_review_request_google_status', 1);
		$this->config->set('module_review_request_google_widget_code', '<script>google-widget</script>');
		$this->config->set('module_review_request_google_review_url', '');
		$this->config->set('module_review_request_google_reference', '');
		$this->config->set('module_review_request_yandex_status', 1);
		$this->config->set('module_review_request_yandex_reference', '987654321');
		$this->config->set('module_review_request_yandex_review_url', '');
		$this->config->set('module_review_request_yandex_widget_code', '');

		$channels = $this->model->getChannels();

		$this->assertCount(2, $channels);
		$this->assertSame('google', $channels[0]['code']);
		$this->assertSame('', $channels[0]['url']);
		$this->assertSame('<script>google-widget</script>', $channels[0]['widget_code']);
		$this->assertSame('yandex', $channels[1]['code']);
		$this->assertSame('https://yandex.ru/maps/org/987654321/reviews/', $channels[1]['url']);
		$this->assertSame('', $channels[1]['widget_code']);
	}

	public function testQueueOrderCreatesPendingRowWithConfiguredDelayAndSkipsDuplicate(): void {
		$this->config->set('module_review_request_order_status_ids', array(5));
		$this->config->set('module_review_request_delay_days', 3);
		$this->config->set('module_review_request_google_status', 1);
		$this->config->set('module_review_request_google_review_url', 'https://google.example/review');
		$this->config->set('module_review_request_google_widget_code', '');
		$this->config->set('module_review_request_include_product_reviews', 0);

		$order_info = array(
			'order_id' => 101,
			'customer_id' => 11,
			'store_id' => 2,
			'language_code' => 'en-gb',
			'email' => 'buyer@example.com'
		);

		$this->assertTrue($this->model->queueOrder($order_info, 5));
		$this->assertFalse($this->model->queueOrder($order_info, 5));

		$queue_row = $this->db->findQueueRowByOrderId(101);

		$this->assertNotNull($queue_row);
		$this->assertSame('pending', $queue_row['status']);
		$this->assertSame(0, $queue_row['send_attempts']);
		$this->assertSame('buyer@example.com', $queue_row['email']);
		$this->assertSame('2026-04-23 10:30:00', $queue_row['date_send_after']);
		$this->assertCount(1, $this->db->getQueueRows());
	}

	public function testQueueOrderRejectsIneligibleStatusAndMissingTargets(): void {
		$this->config->set('module_review_request_order_status_ids', array(5));
		$this->config->set('module_review_request_include_product_reviews', 0);
		$this->config->set('module_review_request_google_status', 0);
		$this->config->set('module_review_request_yandex_status', 0);

		$order_info = array(
			'order_id' => 202,
			'customer_id' => 0,
			'store_id' => 0,
			'language_code' => 'ru-ru',
			'email' => 'guest@example.com'
		);

		$this->assertFalse($this->model->queueOrder($order_info, 5));

		$this->config->set('module_review_request_google_status', 1);
		$this->config->set('module_review_request_google_review_url', 'https://google.example/review');

		$this->assertFalse($this->model->queueOrder($order_info, 7));
		$this->assertCount(0, $this->db->getQueueRows());
	}

	public function testQueueOrderAllowsGuestProductReviewFlowWhenGuestReviewsAreEnabled(): void {
		$this->config->set('module_review_request_order_status_ids', array(5));
		$this->config->set('module_review_request_delay_days', -4);
		$this->config->set('module_review_request_include_product_reviews', 1);
		$this->config->set('config_review_status', 1);
		$this->config->set('config_review_guest', 1);
		$this->config->set('module_review_request_google_status', 0);
		$this->config->set('module_review_request_yandex_status', 0);

		$order_info = array(
			'order_id' => 303,
			'customer_id' => 0,
			'store_id' => 1,
			'language_code' => 'en-gb',
			'email' => 'guest@example.com'
		);

		$this->assertTrue($this->model->queueOrder($order_info, 5));

		$queue_row = $this->db->findQueueRowByOrderId(303);

		$this->assertNotNull($queue_row);
		$this->assertSame('2026-04-20 10:30:00', $queue_row['date_send_after']);
	}

	public function testCanAskOrganizationReviewUsesSuppressedUntilForEmailCooldown(): void {
		$this->config->set('module_review_request_org_review_cooldown_days', 180);
		$this->db->seedCustomerState(array(
			'email' => 'buyer@example.com',
			'org_review_suppressed_until' => '2026-05-01 00:00:00'
		));

		$this->assertFalse($this->model->canAskOrganizationReview('buyer@example.com'));
		$this->assertTrue($this->model->canAskOrganizationReview('fresh@example.com'));
	}

	public function testTrackOrganizationReviewClickUpdatesCustomerSuppressionState(): void {
		$this->config->set('module_review_request_org_review_cooldown_days', 90);
		$this->config->set('module_review_request_google_status', 1);
		$this->config->set('module_review_request_google_review_url', 'https://google.example/review');
		$this->db->seedQueueRow(array(
			'review_request_id' => 88,
			'order_id' => 5088,
			'customer_id' => 41,
			'email' => 'buyer@example.com'
		));

		$this->assertTrue($this->model->trackOrganizationReviewClick(88, 'google'));

		$state = $this->db->getCustomerState('buyer@example.com');

		$this->assertSame(41, $state['customer_id']);
		$this->assertSame(5088, $state['last_order_id']);
		$this->assertSame('google', $state['last_org_click_channel']);
		$this->assertSame('2026-04-20 10:30:00', $state['last_org_click_at']);
		$this->assertSame('2026-07-19 10:30:00', $state['org_review_suppressed_until']);
	}
}
