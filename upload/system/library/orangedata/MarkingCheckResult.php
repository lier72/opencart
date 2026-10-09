<?php
/**
 * MarkingCheckResult
 *
 * Turns one raw разрешительный-режим check answer into the flat per-unit fields stored in
 * ocus_order_marking_code (is_sale_allowed, status_code, reason_*, req_id, req_timestamp, tag1265, offline, gtin, pg).
 *
 * Input is the ГИС МТ check-answer shape shared by the True API v4 `codes/check` (X-API-KEY) and the ТС ПИоТ
 * local API (ЕСМ, after PiotLocalResponse unwraps it):
 *   { code, description, reqId, reqTimestamp, [isCheckedOffline, inst, version], codes: [ {cis, gtin, groupIds,
 *     valid, found, utilised, verified, realizable, sold, isBlocked, ...} ] }
 * These backends return raw flags only, so the sale decision comes from MarkingVerdict (ЦРПТ §4 rules).
 * (OrangeData's own ТС ПИоТ pre-computes isSaleAllowed and goes through OrangeDataService::normalizeCheck().)
 *
 * Any transport failure, non-200, module error code or incomplete answer becomes status_code -1 («проверка
 * недоступна»), which blocks fiscalization of that unit.
 *
 * Scope: TrueApiClient::normalizeResponse(), PiotLocalResponse::normalize(), CLIs and unit tests. Pure.
 */
require_once __DIR__ . '/MarkingVerdict.php';

class MarkingCheckResult {

    /**
     * normalize
     *
     * @param array  $resp  ['http_code' => int, 'body' => array|null, 'raw' => string, 'error' => string|null]
     *                      for ONE checked code (codes[0] is evaluated).
     * @param string $label Backend name used in error texts, e.g. 'True API'.
     * @return array Flat fields for OrangeDataRepository::saveCheckResult().
     */
    public static function normalize(array $resp, $label) {
        $out = array(
            'is_sale_allowed' => null, 'status_code' => null, 'reason_code' => null, 'reason_text' => '',
            'req_id' => '', 'req_timestamp' => null, 'tag1265' => '', 'is_checked_offline' => 0,
            'gtin' => '', 'pg' => null, 'response_json' => isset($resp['raw']) ? $resp['raw'] : '',
        );

        $body = isset($resp['body']) ? $resp['body'] : null;
        if (!empty($resp['error']) || (int) $resp['http_code'] !== 200 || !is_array($body)) {
            $out['status_code'] = -1;
            $out['is_sale_allowed'] = 0;
            $out['reason_text'] = !empty($resp['error']) ? $label . ' недоступен: ' . $resp['error'] : $label . ': HTTP ' . (int) $resp['http_code'];
            return $out;
        }
        // 0 = ok; 204 = post-emergency answer, still a regular check result.
        if (isset($body['code']) && !in_array((int) $body['code'], array(0, 204), true)) {
            $out['status_code'] = -1;
            $out['is_sale_allowed'] = 0;
            $out['reason_text'] = $label . ': ' . (isset($body['description']) ? $body['description'] : 'code ' . $body['code']);
            return $out;
        }
        if (empty($body['reqId']) || !isset($body['reqTimestamp']) || empty($body['codes'][0])) {
            $out['status_code'] = -1;
            $out['is_sale_allowed'] = 0;
            $out['reason_text'] = $label . ': неполный ответ (нет reqId/reqTimestamp/codes)';
            return $out;
        }

        $out['status_code']   = 200;
        $out['req_id']        = (string) $body['reqId'];
        $out['req_timestamp'] = (int) $body['reqTimestamp'];
        $out['tag1265']       = 'UUID=' . $body['reqId'] . '&Time=' . $body['reqTimestamp'];
        $code = $body['codes'][0];

        // Offline = explicit isCheckedOffline, else the local-black-list shape (has `inst`). An answer with
        // found=true is never offline (the local black list doesn't know it), which guards the `inst` heuristic.
        $offline = isset($body['isCheckedOffline'])
            ? !empty($body['isCheckedOffline'])
            : (!empty($body['inst']) && !(isset($code['found']) && $code['found'] === true));
        $out['is_checked_offline'] = $offline ? 1 : 0;

        if (isset($code['gtin'])) $out['gtin'] = (string) $code['gtin'];
        if (isset($code['groupIds'][0])) $out['pg'] = (int) $code['groupIds'][0];

        $verdict = MarkingVerdict::evaluate($code, $offline);
        $out['is_sale_allowed'] = $verdict['allowed'] ? 1 : 0;
        $out['reason_code']     = $verdict['reason_code'];
        $out['reason_text']     = $verdict['reason_text'];

        return $out;
    }
}
