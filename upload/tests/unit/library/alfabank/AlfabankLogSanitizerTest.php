<?php
namespace Tests\Unit\Library\Alfabank;
use PHPUnit\Framework\TestCase;
class AlfabankLogSanitizerTest extends TestCase
{
    public function testLogsOnlyPaymentDiagnostics(): void
    {
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankLogSanitizer.php';
        $payload = array('orderId' => 'reference', 'errorCode' => '7', 'errorMessage' => 'Incorrect state',
            'userName' => 'secret-login', 'password' => 'secret-password', 'token' => 'secret-token',
            'payerData' => array('email' => 'private@example.test'), 'cardAuthInfo' => array('pan' => 'private-card'),
            'paymentAmountInfo' => array('depositedAmount' => 45400, 'unexpected' => 'private-value'));
        $expected = array('orderId' => 'reference', 'errorCode' => '7', 'errorMessage' => 'Incorrect state',
            'paymentAmountInfo' => array('depositedAmount' => 45400));
        $this->assertSame($expected, \AlfabankLogSanitizer::payload($payload));
        $this->assertSame($expected, \AlfabankLogSanitizer::payload(json_encode($payload)));
        $this->assertSame('[unstructured payload omitted]', \AlfabankLogSanitizer::payload('HTTP/1.1 200 OK Set-Cookie: secret'));
        $this->assertSame('[unstructured payload omitted]', \AlfabankLogSanitizer::payload(serialize($payload)));
        $this->assertSame('https://example.test/rest/reverse.do',
            \AlfabankLogSanitizer::endpoint('https://user:password@example.test/rest/reverse.do?token=secret#secret'));
    }
}
