<?php

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'engine/controller.php';
require_once DIR_SYSTEM . 'library/url.php';
require_once dirname(DIR_SYSTEM) . '/admin/controller/extension/module/cdek_integrator.php';

class CdekPdfLinkTest extends TestCase
{
    public function testJsonLinkPreservesTheCurrentSessionToken()
    {
        $registry = new Registry();
        $registry->set('url', new Url('http://example.test/admin/', 'https://example.test/admin/'));
        $session = new stdClass();
        $session->data = array('user_token' => 'current-session-token');
        $registry->set('session', $session);
        $reflection = new ReflectionClass('ControllerExtensionModuleCdekIntegrator');
        $controller = $reflection->newInstanceWithoutConstructor();
        $property = new ReflectionProperty('Controller', 'registry');
        $property->setAccessible(true);
        $property->setValue($controller, $registry);
        $method = $reflection->getMethod('getCdekPdfUrl');
        $method->setAccessible(true);
        $url = $method->invoke($controller, 116278);
        $json = json_decode(json_encode(array('file' => $url)), true);
        parse_str(parse_url($json['file'], PHP_URL_QUERY), $query);
        $this->assertSame('https', parse_url($url, PHP_URL_SCHEME));
        $this->assertSame('current-session-token', $query['user_token']);
        $this->assertSame('116278', $query['order_id']);
        $this->assertSame('extension/module/cdek_integrator/showPdf', $query['route']);
        $this->assertStringNotContainsString('&amp;', $url);
    }
}
