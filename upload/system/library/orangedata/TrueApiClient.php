<?php
/**
 * TrueApiClient
 *
 * Direct ГИС МТ «Честный знак» True API v4 разрешительный-режим check, WITHOUT a ТС ПИоТ in between
 * (solution 1 of the test plan). Flow per ЦРПТ «Методические рекомендации» (разрешительный режим):
 *
 *   GET  {base}/api/v4/true-api/cdn/info               X-API-KEY → hosts[] (prod base: https://cdn.crpt.ru)
 *   GET  {host}/api/v4/true-api/cdn/health/check       X-API-KEY → avgTimeMs (pick the fastest host, code 0)
 *   POST {host}/api/v4/true-api/codes/check            X-API-KEY, {"codes":[...]} → codes[], reqId, reqTimestamp
 *
 * The answer is the shared ГИС МТ check shape, so MarkingCheckResult::normalize() + MarkingVerdict
 * derive the sale decision and tag1265 ("UUID=<reqId>&Time=<reqTimestamp>").
 *
 * Hosts (probed 2026-10-01 from a non-RU IP — all reachable, only the ЧЗ web portal is geo-blocked):
 *   prod    https://cdn.crpt.ru (cdn/info only; checks go to the cdnNN.crpt.ru hosts it returns)
 *   sandbox https://markirovka.sandbox.crptech.ru (cdn/info and codes/check, 401 without a key)
 *   legacy  https://markirovka.crpt.ru/api/v4/true-api/codes/check → 410 «Устаревшее API» (do not use)
 *
 * X-API-KEY: issued in the ЛК ГИС МТ (Профиль → API-ключ разрешительного режима) or via
 * /api/v3/true-api/auth/permissive-access, which needs a УКЭП (GOST) signature — not automated here.
 *
 * Scope: check backend `trueapi` of OrangeDataService (owner's decision 2026-10-07: X-API-KEY first, then ЕСМ
 * ТС ПИоТ), the settings «Проверить True API» action and cli/orangedata_trueapi_test.php.
 * NB: ЦРПТ switches X-API-KEY checks off in stages from 01.10.2026 — a 401/410 here means this key was cut off.
 */
require_once __DIR__ . '/MarkingCheckResult.php';

class TrueApiClient {

    const LABEL = 'True API';

    /** @var array */
    private $config;

    /** @var callable|null */
    private $logger;

    /** @var string|null Host chosen by selectHost(). */
    private $host;

    /** @var string[] Hosts listed by the last cdn/info. */
    private $hosts = array();

    /**
     * @param array $config ['env' => 'sandbox'|'prod', 'base_url' (overrides env), 'api_key', 'proxy'
     *                      (e.g. socks5h://127.0.0.1:1080), 'connect_timeout' (s), 'timeout' (s)]
     * @param callable|null $logger function(string $level, string $message, array $context)
     */
    public function __construct(array $config, $logger = null) {
        $this->config = array_merge(array(
            'env'             => 'sandbox',
            'base_url'        => '',
            'api_key'         => '',
            'proxy'           => '',
            'connect_timeout' => 3,
            'timeout'         => 8,
        ), $config);
        if ($this->config['base_url'] === '') {
            $this->config['base_url'] = $this->config['env'] === 'prod' ? 'https://cdn.crpt.ru' : 'https://markirovka.sandbox.crptech.ru';
        }
        $this->config['base_url'] = rtrim($this->config['base_url'], '/');
        $this->logger = is_callable($logger) ? $logger : null;
    }

    /** cdn/info on the base host. @return array ['http_code','body','raw','error'] */
    public function cdnInfo() {
        return $this->request('GET', $this->config['base_url'] . '/api/v4/true-api/cdn/info');
    }

    /** health/check on one CDN host. @return array ['http_code','body','raw','error'] */
    public function healthCheck($host) {
        return $this->request('GET', rtrim($host, '/') . '/api/v4/true-api/cdn/health/check');
    }

    /**
     * selectHost
     *
     * Picks the healthy host with the lowest avgTimeMs. When cdn/info answers 200 without hosts (sandbox), the
     * base host itself is used. When hosts are listed but none is healthy, host stays null.
     *
     * @return array ['host' => string|null, 'candidates' => array of [host, code, avgTimeMs, error]]
     */
    public function selectHost() {
        $info  = $this->cdnInfo();
        $hosts = array();
        if (is_array($info['body']) && !empty($info['body']['hosts'])) {
            foreach ($info['body']['hosts'] as $h) {
                if (!empty($h['host'])) $hosts[] = $h['host'];
            }
        }
        $this->hosts = $hosts;
        if (!$hosts) {
            $this->host = (int) $info['http_code'] === 200 ? $this->config['base_url'] : null;
            return array('host' => $this->host, 'candidates' => array(), 'info' => $info);
        }

        $best = null; $bestMs = PHP_INT_MAX; $candidates = array();
        foreach ($hosts as $h) {
            $hc   = $this->healthCheck($h);
            $code = is_array($hc['body']) && isset($hc['body']['code']) ? (int) $hc['body']['code'] : null;
            $ms   = is_array($hc['body']) && isset($hc['body']['avgTimeMs']) ? (int) $hc['body']['avgTimeMs'] : null;
            $candidates[] = array('host' => $h, 'code' => $code, 'avgTimeMs' => $ms, 'error' => $hc['error']);
            if ($code === 0 && $ms !== null && $ms < $bestMs) { $best = $h; $bestMs = $ms; }
        }
        $this->host = $best;
        return array('host' => $best, 'candidates' => $candidates, 'info' => $info);
    }

    /**
     * checkCodes
     *
     * @param string[]    $codes  Marking codes as scanned (raw, GS \x1d kept) — or Base64 when $base64 is true.
     * @param string|null $host   CDN host; null = the one chosen by selectHost() (called on demand), else the
     *                            first listed host. Never the prod base: cdn.crpt.ru answers 404 on codes/check.
     *                            No host at all → error result (http_code 0), so the caller fails over.
     * @param string      $fiscalDriveNumber Optional ФН serial (sent as fiscalDriveNumber when not empty).
     * @return array ['http_code','body','raw','error']
     */
    public function checkCodes(array $codes, $host = null, $fiscalDriveNumber = '') {
        if ($host === null) {
            if ($this->host === null) $this->selectHost();
            $host = $this->host ?: (isset($this->hosts[0]) ? $this->hosts[0] : null);
            if ($host === null) {
                return array('http_code' => 0, 'body' => null, 'raw' => '', 'error' => 'True API: no CDN host available (cdn/info)');
            }
        }
        $payload = array('codes' => array_values($codes));
        if ($fiscalDriveNumber !== '') $payload['fiscalDriveNumber'] = (string) $fiscalDriveNumber;
        return $this->request('POST', rtrim($host, '/') . '/api/v4/true-api/codes/check', $payload);
    }

    /** One code → flat normalized shape (see MarkingCheckResult::normalize()). */
    public static function normalizeResponse(array $resp) {
        return MarkingCheckResult::normalize($resp, self::LABEL);
    }

    private function request($method, $url, $payload = null) {
        $headers = array('Accept: application/json');
        if ($this->config['api_key'] !== '') $headers[] = 'X-API-KEY: ' . $this->config['api_key'];

        $ch = curl_init($url);
        $opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) $this->config['connect_timeout'],
            CURLOPT_TIMEOUT        => (int) $this->config['timeout'],
        );
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        if ($this->config['proxy'] !== '') $opts[CURLOPT_PROXY] = $this->config['proxy'];
        curl_setopt_array($ch, $opts);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($this->logger) {
            call_user_func($this->logger, $err ? 'error' : 'debug', 'TRUE API ' . $method . ' ' . parse_url($url, PHP_URL_PATH), array('http_code' => $httpCode, 'error' => $err));
        }

        $body = null;
        if (is_string($raw) && $raw !== '') {
            $body = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) $body = null;
        }
        return array('http_code' => $httpCode, 'body' => $body, 'raw' => is_string($raw) ? $raw : '', 'error' => $err);
    }
}
