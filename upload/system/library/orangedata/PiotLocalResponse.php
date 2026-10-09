<?php
/**
 * PiotLocalResponse
 *
 * Normalizes an answer of a ТС ПИоТ *local* API — the ЕСМ (АО ЕСП) `/api/v{1,2,3}/codes/check` interface a
 * certified ТС ПИоТ exposes to the cash software (ПМСР) — into the flat per-unit shape used by
 * OrangeDataService (see MarkingCheckResult::normalize()).
 *
 * The store fiscalizes through the OrangeData cloud kassa, whose ТС ПИоТ is on OrangeData's side, so this is a
 * TEST ORACLE: it lets the АО ЕСП emulator (https://esm-emu.ao-esp.ru) and its ПМСР certification scenarios drive
 * MarkingVerdict with realistic payloads. Not a production check backend.
 *
 * Answer shapes:
 *   200 {"codesResponse":[{code, description, codes[], reqId, reqTimestamp, isCheckedOffline, [inst, version]}]}  (v2/v3)
 *   200 {"codesResponse":{"codesResponse":[...]}}                                                              (v1)
 *   203 {"code":203,"message":"Аварийный режим"}  → sale allowed, no tag 1265 (tag 1260 omitted on the receipt)
 *   514 {"code":514,...}                          → ГИС МТ and ЛМ ЧЗ both silent → backend unavailable (failover)
 *   inner code 204                                → post-emergency answer, a regular check result
 *
 * Scope: cli/orangedata_esm_scenarios.php and tests/unit/library/orangedata/EsmScenarioTest.php.
 */
require_once __DIR__ . '/MarkingCheckResult.php';

class PiotLocalResponse {

    const LABEL = 'ТС ПИоТ (ЕСМ)';

    /**
     * normalize
     *
     * @param array $resp ['http_code' => int, 'body' => array|null, 'raw' => string, 'error' => string|null]
     * @return array Same keys as MarkingCheckResult::normalize().
     */
    public static function normalize(array $resp) {
        $body = isset($resp['body']) ? $resp['body'] : null;
        $code = is_array($body) && isset($body['code']) ? (int) $body['code'] : (int) $resp['http_code'];

        if (empty($resp['error']) && $code === 203) {
            return self::flat($resp, 203, 1, 'Аварийный режим ТС ПИоТ: продажа разрешена без тега 1265');
        }
        if (empty($resp['error']) && $code === 514) {
            return self::flat($resp, 514, 0, self::LABEL . ': нет ответа от ГИС МТ и ЛМ ЧЗ');
        }

        $inner = self::unwrap($body);
        if ($inner === null) {
            // Transport error, non-JSON or unknown shape → MarkingCheckResult maps it to -1 (unavailable).
            return MarkingCheckResult::normalize(array('http_code' => (int) $resp['http_code'] ?: 0, 'body' => null,
                'raw' => isset($resp['raw']) ? $resp['raw'] : '', 'error' => isset($resp['error']) ? $resp['error'] : null), self::LABEL);
        }

        return MarkingCheckResult::normalize(array(
            'http_code' => (int) $resp['http_code'] === 200 ? 200 : (int) $resp['http_code'],
            'body'      => $inner,
            'raw'       => isset($resp['raw']) ? $resp['raw'] : '',
            'error'     => isset($resp['error']) ? $resp['error'] : null,
        ), self::LABEL);
    }

    /**
     * unwrap
     *
     * Returns the first `codesResponse` element (the ЛМ ЧЗ-shaped object), or null for any other shape.
     *
     * @param mixed $body Decoded JSON body.
     * @return array|null
     */
    public static function unwrap($body) {
        if (!is_array($body) || !isset($body['codesResponse'])) {
            return null;
        }
        $list = $body['codesResponse'];
        if (isset($list['codesResponse'])) { // v1 double wrap
            $list = $list['codesResponse'];
        }
        return isset($list[0]) && is_array($list[0]) ? $list[0] : null;
    }

    private static function flat(array $resp, $status, $allowed, $text) {
        return array(
            'is_sale_allowed' => $allowed, 'status_code' => $status, 'reason_code' => null, 'reason_text' => $text,
            'req_id' => '', 'req_timestamp' => null, 'tag1265' => '', 'is_checked_offline' => 0,
            'gtin' => '', 'pg' => null, 'response_json' => isset($resp['raw']) ? $resp['raw'] : '',
        );
    }
}
