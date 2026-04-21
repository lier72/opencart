<?php

namespace Tests\Unit\Controller\Extension\Module;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\FakeLanguage;
use Tests\Unit\Support\FakeOrderModel;
use Tests\Unit\Support\FakeUser;
use Tests\Unit\Support\InvokesNonPublicMembers;
use Tests\Unit\Support\NoOpLoader;

require_once dirname(__DIR__, 3) . '/Support/ReviewRequestTestDoubles.php';
class ReviewRequestControllerTest extends TestCase {
	use InvokesNonPublicMembers;

	private $config;
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname(__DIR__, 5) . '/admin/controller/extension/module/review_request.php';

		$registry = new \Registry();
		$this->config = new \Config();

		$request = new \stdClass();
		$request->post = array();
		$request->server = array();

		$registry->set('config', $this->config);
		$registry->set('request', $request);
		$registry->set('user', new FakeUser(true));
		$registry->set('language', new FakeLanguage(array(
			'error_permission' => 'Permission denied',
			'error_delay_days' => 'Delay must be zero or more',
			'error_org_cooldown_days' => 'Cooldown must be zero or more'
		)));
		$registry->set('load', new NoOpLoader());

		$this->controller = new \ControllerExtensionModuleReviewRequest($registry);
	}

	public function testDefaultEmailTemplatesUseReviewFirstMessagingAndTealTheme(): void {
		$russian_subject = $this->invokeMethod($this->controller, 'getDefaultEmailSubject', array('ru-ru'));
		$russian_body = $this->invokeMethod($this->controller, 'getDefaultEmailBody', array('ru-ru'));
		$english_subject = $this->invokeMethod($this->controller, 'getDefaultEmailSubject', array('en-gb'));
		$english_body = $this->invokeMethod($this->controller, 'getDefaultEmailBody', array('en-gb'));

		$this->assertSame('{store_name} - поделитесь впечатлением о заказе #{order_id}', $russian_subject);
		$this->assertStringContainsString('{email_intro}', $russian_body);
		$this->assertStringContainsString('{organization_review_section}', $russian_body);
		$this->assertStringContainsString('linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)', $russian_body);
		$this->assertStringContainsString('{product_reviews_section}', $russian_body);
		$this->assertStringContainsString('{order_button}', $russian_body);
		$this->assertSame('{store_name} - tell us about order #{order_id}', $english_subject);
		$this->assertStringContainsString('{organization_review_section}', $english_body);
	}

	public function testGetOrganizationChannelsUsesDirectUrlsAndNormalizedYandexReference(): void {
		$this->config->set('module_review_request_google_status', 1);
		$this->config->set('module_review_request_google_reference', '');
		$this->config->set('module_review_request_google_review_url', 'https://google.example/write-review');
		$this->config->set('module_review_request_yandex_status', 1);
		$this->config->set('module_review_request_yandex_reference', '1234567');
		$this->config->set('module_review_request_yandex_review_url', '');
		$this->config->set('module_review_request_track_review_clicks', 1);

		$channels = $this->invokeMethod($this->controller, 'getOrganizationChannels', array(
			new FakeLanguage(array(
				'text_google' => 'Google',
				'text_yandex' => 'Yandex'
			)),
			55,
			'https://shop.example/'
		));

		$this->assertCount(2, $channels);
		$this->assertSame('google', $channels[0]['code']);
		$this->assertSame('https://shop.example/index.php?route=extension/module/review_request/redirect&review_request_id=55&channel=google', $channels[0]['url']);
		$this->assertSame('linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)', $channels[0]['color']);
		$this->assertSame('yandex', $channels[1]['code']);
		$this->assertSame('https://shop.example/index.php?route=extension/module/review_request/redirect&review_request_id=55&channel=yandex', $channels[1]['url']);
		$this->assertSame('linear-gradient(135deg, #0f766e 0%, #115e59 100%)', $channels[1]['color']);
	}

	public function testGetProductReviewLinksDeduplicatesProductsAndBuildsReviewAnchors(): void {
		$this->config->set('module_review_request_include_product_reviews', 1);
		$this->config->set('config_review_status', 1);
		$this->config->set('config_review_guest', 0);

		$this->controller->model_sale_order = new FakeOrderModel(array(
			array('product_id' => 5, 'name' => 'Boots &amp; More'),
			array('product_id' => 5, 'name' => 'Boots &amp; More'),
			array('product_id' => 7, 'name' => 'Gloves')
		));

		$links = $this->invokeMethod($this->controller, 'getProductReviewLinks', array(
			array(
				'order_id' => 77,
				'customer_id' => 15,
				'store_url' => 'https://shop.example/'
			)
		));

		$this->assertCount(2, $links);
		$this->assertSame('Boots & More', $links[0]['name']);
		$this->assertSame('https://shop.example/index.php?route=product/product&product_id=5#tab-review', $links[0]['url']);
		$this->assertSame('https://shop.example/index.php?route=product/product&product_id=7#tab-review', $links[1]['url']);
	}

	public function testRenderHelpersEscapeInputAndReplaceOnlyScalarPlaceholders(): void {
		$button = $this->invokeMethod($this->controller, 'renderEmailButton', array(
			'Review <Now>',
			'https://example.com/review?x=1&y=2',
			'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)'
		));
		$section = $this->invokeMethod($this->controller, 'renderProductReviewSection', array(
			array(
				array(
					'name' => 'Sneakers & Socks',
					'url' => 'https://shop.example/review?product=1&lang=en'
				)
			),
			'Product reviews'
		));
		$rendered = $this->invokeMethod($this->controller, 'replacePlaceholders', array(
			'Hello {customer_name}, {review_buttons} {ignored}',
			array(
				'customer_name' => 'Max',
				'review_buttons' => '<a>Review</a>',
				'ignored' => array('x')
			)
		));

		$this->assertStringContainsString('Review &lt;Now&gt;', $button);
		$this->assertStringContainsString('x=1&amp;y=2', $button);
		$this->assertStringContainsString('font-size:16px', $button);
		$this->assertStringContainsString('Sneakers &amp; Socks', $section);
		$this->assertStringContainsString('product=1&amp;lang=en', $section);
		$this->assertStringContainsString('color:#0f766e', $section);
		$this->assertSame('Hello Max, <a>Review</a> {ignored}', $rendered);
	}

	public function testValidateRejectsNegativeDelayAndStoresFieldError(): void {
		$this->controller->request->post['module_review_request_delay_days'] = -1;
		$this->controller->request->post['module_review_request_org_review_cooldown_days'] = -5;

		$result = $this->invokeMethod($this->controller, 'validate');
		$error = $this->readProperty($this->controller, 'error');

		$this->assertFalse($result);
		$this->assertSame('Delay must be zero or more', $error['delay_days']);
		$this->assertSame('Cooldown must be zero or more', $error['org_cooldown_days']);
	}
}
