<?php

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/cdek_integrator/class.cdek_integrator.php';
require_once DIR_SYSTEM . 'library/cdek_integrator/components/interface.exchange.php';
foreach (array('order_print', 'order_print_info', 'order_download_pdf', 'order_delete', 'info') as $component) {
    require_once DIR_SYSTEM . 'library/cdek_integrator/components/class.' . $component . '.php';
}
require_once DIR_SYSTEM . 'library/cdek_integrator/class.response_parser.php';
require_once DIR_SYSTEM . 'library/cdek_integrator/class.parser_json.php';

class CdekPrintWithoutWait extends order_print
{
    public function __construct() {}
    protected function waitForPdf() {}
}

class CdekQueuedApi
{
    public $responses;
    public $calls = array();
    public function __construct($responses) { $this->responses = $responses; }
    public function loadComponent($name) { return (new ReflectionClass($name))->newInstanceWithoutConstructor(); }
    public function sendData($component) {
        $this->calls[] = $component->getMethod();
        if (!$this->responses) throw new LogicException('Unexpected API request');
        return array_shift($this->responses);
    }
}

class CdekRecordingTransport extends cdek_integrator
{
    public $request;
    public function __construct() {}
    protected function getURL($url, response_parser $parser, $data = array(), $http_method = null) {
        $this->request = array($url, $data, $http_method);
        return array('entity' => array('uuid' => 'test'));
    }
}

class CdekPickupFixture extends info
{
    public $url;
    public function __construct() {}
    protected function getURL($url, response_parser $parser, $data = array(), $http_method = null) {
        $this->url = $url;
        return array(array('code' => 'TEST', 'name' => 'Office', 'work_time' => '9-18',
            'location' => array('city' => 'City', 'city_code' => 44, 'address' => 'Street', 'longitude' => 1, 'latitude' => 2),
            'weight_max' => 30));
    }
}

class CdekApiCompatibilityTest extends TestCase
{
    private function status($code) {
        return array('entity' => array('uuid' => 'print-id', 'statuses' => array(
            array('code' => $code, 'date_time' => '2026-10-11T12:00:01+0000'),
            array('code' => 'ACCEPTED', 'date_time' => '2026-10-11T12:00:00+0000')
        )));
    }

    public function testPrintWaitsForReadyBeforeDownloading() {
        $api = new CdekQueuedApi(array(array('entity' => array('uuid' => 'print-id')),
            $this->status('PROCESSING'), $this->status('READY'), '%PDF-1.4 test'));
        $uuid = '';
        $this->assertSame('%PDF-1.4 test', (new CdekPrintWithoutWait())->fetchPdf($api, $uuid));
        $this->assertSame(array('v2/print/orders', 'v2/print/orders/print-id',
            'v2/print/orders/print-id', 'v2/print/orders/print-id.pdf'), $api->calls);
    }

    public function testSlowGenerationResumesWithoutCreatingAnotherJob() {
        $api = new CdekQueuedApi(array_fill(0, 3, $this->status('PROCESSING')));
        $uuid = 'print-id';
        $component = new CdekPrintWithoutWait();
        try {
            $component->fetchPdf($api, $uuid);
            $this->fail('Pending generation must be reported');
        } catch (RuntimeException $e) {
            $this->assertSame('print-id', $uuid);
            $this->assertCount(3, $api->calls);
        }
        $api->responses = array($this->status('READY'), '%PDF-1.4 test');
        $this->assertSame('%PDF-1.4 test', $component->fetchPdf($api, $uuid));
        $this->assertNotContains('v2/print/orders', $api->calls);
    }

    public function testReadyWinsWhenAllTransitionsHaveTheSameTimestamp() {
        $response = $this->status('READY');
        $response['entity']['statuses'] = array(
            array('code' => 'ACCEPTED', 'date_time' => '2026-10-11T12:00:00+0000'),
            array('code' => 'PROCESSING', 'date_time' => '2026-10-11T12:00:00+0000'),
            array('code' => 'READY', 'date_time' => '2026-10-11T12:00:00+0000')
        );
        $api = new CdekQueuedApi(array($response, '%PDF-1.4 test'));
        $uuid = 'print-id';
        $this->assertSame('%PDF-1.4 test', (new CdekPrintWithoutWait())->fetchPdf($api, $uuid));
    }

    public function testDownloadUrlSignalsReadinessWithoutStatuses() {
        $response = array('entity' => array('uuid' => 'print-id', 'url' => 'https://api.cdek.ru/v2/print/orders/print-id.pdf'));
        $api = new CdekQueuedApi(array($response, '%PDF-1.4 test'));
        $uuid = 'print-id';
        $this->assertSame('%PDF-1.4 test', (new CdekPrintWithoutWait())->fetchPdf($api, $uuid));
    }

    public function testMissingStatusIsNotReportedAsStillPreparing() {
        $api = new CdekQueuedApi(array(array('entity' => array('uuid' => 'print-id'))));
        $uuid = 'print-id';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('статус отсутствует');
        (new CdekPrintWithoutWait())->fetchPdf($api, $uuid);
    }

    public function testJsonErrorIsNeverReturnedAsPdf() {
        $api = new CdekQueuedApi(array($this->status('READY'),
            '{"errors":[{"code":"not_ready","message":"Try later"}]}'));
        $uuid = 'print-id';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not_ready: Try later');
        (new CdekPrintWithoutWait())->fetchPdf($api, $uuid);
    }

    public function testRejectedPrintRequestDoesNotDownload() {
        $api = new CdekQueuedApi(array(array('requests' => array(array('state' => 'INVALID',
            'errors' => array(array('code' => 'missing_order', 'message' => 'No order')))))));
        $uuid = '';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing_order: No order');
        (new CdekPrintWithoutWait())->fetchPdf($api, $uuid);
    }

    public function testExpiredJobIsClearedForRetry() {
        $api = new CdekQueuedApi(array($this->status('REMOVED')));
        $uuid = 'print-id';
        try {
            (new CdekPrintWithoutWait())->fetchPdf($api, $uuid);
            $this->fail('Expired document must be reported');
        } catch (RuntimeException $e) {
            $this->assertSame('', $uuid);
            $this->assertStringContainsString('REMOVED', $e->getMessage());
        }
    }

    public function testDeletionUsesDeleteWithoutLegacyBody() {
        $api = new CdekRecordingTransport();
        $component = (new ReflectionClass('order_delete'))->newInstanceWithoutConstructor();
        $component->setNumber('test');
        $component->setData(array(123));
        $api->sendData($component);
        $this->assertSame(array('https://api.cdek.ru/v2/orders/test', array(), 'DELETE'), $api->request);
    }

    public function testPickupImportUsesV2AndPreservesGroupedFormat() {
        $info = new CdekPickupFixture();
        $points = $info->getPVZData();
        $this->assertSame('https://api.cdek.ru/v2/deliverypoints?type=ALL', $info->url);
        $point = reset($points[44]['List']);
        $this->assertSame('TEST', $point['Code']);
        $this->assertSame('', $point['Phone']);
        $this->assertSame(30.0, $point['WeightLimit']['WeightMax']);
        $info->getPVZ(44);
        $this->assertStringContainsString('&city_code=44', $info->url);
    }
}
