<?php
/**
 * MarkingCode
 *
 * Normalizes a DataMatrix marking code (КМ) as it arrives from a barcode scanner through a browser field, so the
 * stored `km_raw` is byte-exact: it is sent Base64-encoded to ТС ПИоТ (`cis`) and raw to the receipt (`itemCode`,
 * tag 1163) — any altered byte means a different code.
 *
 * Problems handled:
 *  1. OpenCart's Request::clean() HTML-escapes every POST value. GS1 serials (AI 21) may legitimately contain
 *     & " < >, so the value must be un-escaped first (exact inverse of htmlspecialchars ENT_COMPAT).
 *  2. Keyboard-wedge scanners cannot type the GS byte (0x1D) into a browser field. They either send a substitute
 *     (configured on the scanner, or mapped by the order-panel JS to the token `<GS>`) or drop it entirely.
 *     Textual substitutes are converted to 0x1D; if GS is missing altogether, it is restored positionally for the
 *     standard обувь/одежда layout 01<14>21<serial> GS 91<4> GS 92<signature>. Codes that do not match that
 *     layout are left untouched (the order panel then flags them as "no GS").
 *
 * Scope: the admin scan() action and the settings «Проверка сканера» test; pure and unit-tested.
 */
class MarkingCode {

    const GS = "\x1d";

    /** Textual representations of GS that scanners / the panel JS / people may produce. */
    private static $gsTokens = array('<GS>', '{GS}', '\\u001d', '\\x1d', '\\035', '&#29;', '^]');

    /**
     * normalize
     *
     * @param string $value     Raw field value.
     * @param bool   $fromRequest True when the value came from OpenCart's Request (HTML-escaped by Request::clean).
     * @return array ['code' => string, 'restored' => bool] — `restored` is true when GS was rebuilt positionally.
     */
    public static function normalize($value, $fromRequest = false) {
        $km = (string) $value;
        if ($fromRequest) {
            $km = htmlspecialchars_decode($km, ENT_COMPAT);
        }
        $km = trim($km, " \t\r\n\0\x0B");
        $km = str_replace(self::$gsTokens, self::GS, $km);

        $restored = false;
        if ($km !== '' && strpos($km, self::GS) === false && preg_match('/^(01\d{14}21.+)(91.{4})(92.+)$/U', $km, $m)) {
            $km = $m[1] . self::GS . $m[2] . self::GS . $m[3];
            $restored = true;
        }

        return array('code' => $km, 'restored' => $restored);
    }

    /** True when the code contains at least one GS separator. */
    public static function hasGs($km) {
        return strpos((string) $km, self::GS) !== false;
    }

    /** Human-readable form with GS shown as ⟨GS⟩ (for the scanner test and logs). */
    public static function visualize($km) {
        return str_replace(self::GS, '⟨GS⟩', (string) $km);
    }
}
