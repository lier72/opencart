<?php

namespace Tests\Unit\Library\Orangedata;

use PHPUnit\Framework\TestCase;

require_once DIR_SYSTEM . 'library/orangedata/MarkingCode.php';

/**
 * MarkingCode::normalize() must return the byte-exact marking code that goes to ТС ПИоТ and into the receipt.
 */
class MarkingCodeTest extends TestCase
{
    const GS = "\x1d";
    const SHOE = "0104600000000001215SerialABC12";

    public function testRealGsIsKeptAsIs()
    {
        $km = self::SHOE . self::GS . '91EE10' . self::GS . '92abcd==';
        $n = \MarkingCode::normalize($km);
        $this->assertSame($km, $n['code']);
        $this->assertFalse($n['restored']);
    }

    /**
     * @dataProvider gsTokens
     */
    public function testTextualGsTokensBecomeRealGs($token)
    {
        $n = \MarkingCode::normalize(self::SHOE . $token . '91EE10' . $token . '92abcd==');
        $this->assertSame(self::SHOE . self::GS . '91EE10' . self::GS . '92abcd==', $n['code']);
    }

    public function gsTokens()
    {
        return array(array('<GS>'), array('{GS}'), array('\\u001d'), array('\\x1d'), array('^]'));
    }

    public function testMissingGsIsRestoredPositionally()
    {
        $n = \MarkingCode::normalize(self::SHOE . '91EE10' . '92abcd==');
        $this->assertSame(self::SHOE . self::GS . '91EE10' . self::GS . '92abcd==', $n['code']);
        $this->assertTrue($n['restored']);
    }

    public function testNonMatchingCodeIsLeftUntouched()
    {
        $n = \MarkingCode::normalize('4600000000001'); // plain EAN, not a DataMatrix КМ
        $this->assertSame('4600000000001', $n['code']);
        $this->assertFalse($n['restored']);
        $this->assertFalse(\MarkingCode::hasGs($n['code']));
    }

    public function testRequestEscapingIsUndoneBeforeAnythingElse()
    {
        // OpenCart Request::clean() = htmlspecialchars(ENT_COMPAT); GS1 serials may contain & " < >.
        $original = '0104600000000001215a&b"c<d>' . self::GS . '91EE10' . self::GS . '92abcd==';
        $fromRequest = htmlspecialchars('0104600000000001215a&b"c<d><GS>91EE10<GS>92abcd==', ENT_COMPAT, 'UTF-8');
        $this->assertSame($original, \MarkingCode::normalize($fromRequest, true)['code']);
    }

    public function testSurroundingWhitespaceTrimmedButGsKept()
    {
        $km = self::SHOE . self::GS . '91EE10' . self::GS . '92abcd==';
        $this->assertSame($km, \MarkingCode::normalize("  " . $km . "\r\n")['code']);
    }

    public function testVisualize()
    {
        $this->assertSame('a⟨GS⟩b', \MarkingCode::visualize('a' . self::GS . 'b'));
    }
}
