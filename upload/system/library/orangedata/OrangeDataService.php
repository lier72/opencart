<?php
/**
 * OrangeDataService
 *
 * OpenCart-aware orchestration layer for the ТС ПИоТ / разрешительный режим flow. It wires the pure
 * OrangeDataClient (transport) and OrangeDataReceiptBuilder (payload) to the store's config, order data
 * and the ocus_order_marking_code table, so both the admin order panel and the catalog order-status
 * event can perform the same operations without duplicating logic.
 *
 * Responsibilities:
 *   - getClient()        build a configured client from module settings (+ log to orangedata.log).
 *   - checkOrder()       run the ТС ПИоТ check for every scanned code of an order (one call per code,
 *                        the per-code decision), persisting each verdict.
 *   - fiscalizeOrder()   assemble the receipt from the order's products + verified codes and submit it,
 *                        then poll and record the document id.
 *
 * Scope: instantiated with the OpenCart $registry (mirrors how cart/journal3 libraries take the registry).
 * Business gating lives here (fiscalize only when every marked unit is scanned and sale-allowed / emergency);
 * the caller just invokes and reports.
 */
require_once __DIR__ . '/OrangeDataClient.php';
require_once __DIR__ . '/OrangeDataReceiptBuilder.php';
require_once __DIR__ . '/OrangeDataRepository.php';
require_once __DIR__ . '/TrueApiClient.php';

class OrangeDataService {

    /** @var Registry */
    private $registry;

    public function __construct($registry) {
        $this->registry = $registry;
    }

    /** @var array Unsaved setting values (e.g. the settings form being tested) that take precedence over config. */
    private $overrides = array();

    /**
     * setOverrides
     *
     * Lets the settings "Test" buttons check the values currently in the form without saving them first.
     * Keys are full setting names (module_orangedata_piot_*). Scope: admin test actions only.
     *
     * @param array $settings
     */
    public function setOverrides(array $settings) {
        foreach ($settings as $k => $v) {
            if (strpos($k, 'module_orangedata_piot_') === 0) {
                $this->overrides[substr($k, strlen('module_orangedata_piot_'))] = is_string($v) ? trim($v) : $v;
            }
        }
    }

    private function config($key, $default = null) {
        $v = array_key_exists($key, $this->overrides)
            ? $this->overrides[$key]
            : $this->registry->get('config')->get('module_orangedata_piot_' . $key);
        return ($v === null || $v === '') ? $default : $v;
    }

    /**
     * requireReadableFile
     *
     * Validates a configured file path and returns it, or throws a message naming the setting and the exact
     * problem (empty / not found / not readable by the web server user). Scope: buildClientConfig().
     */
    private function requireReadableFile($key, $label) {
        $path = (string) $this->config($key, '');
        if ($path === '') {
            throw new RuntimeException('Не указан ' . $label . ' (настройки модуля).');
        }
        if (!is_file($path)) {
            throw new RuntimeException($label . ': файл не найден — ' . $path);
        }
        if (!is_readable($path)) {
            throw new RuntimeException($label . ': нет прав на чтение для пользователя веб-сервера — ' . $path);
        }
        return $path;
    }

    private function db() { return $this->registry->get('db'); }

    /** @var OrangeDataRepository */
    private $repo;

    /** Repository over the raw DB — works in admin AND catalog (event) contexts. */
    private function repo() {
        if (!$this->repo) {
            $this->repo = new OrangeDataRepository($this->db());
        }
        return $this->repo;
    }

    /* ------------------------------------------------------------------ *
     *  Client / builder construction
     * ------------------------------------------------------------------ */

    /**
     * buildClientConfig
     *
     * Maps module settings to the OrangeDataClient config array. Reads the signing key file and converts
     * it from .NET RSA XML to PEM when needed. Throws if a required piece is missing.
     * Scope: internal; also reused by the settings "Test connection" action.
     *
     * @return array
     */
    public function buildClientConfig() {
        $signPath = $this->requireReadableFile('sign_key_path', 'путь к ключу подписи (PEM или XML)');
        $certPath = $this->requireReadableFile('ssl_cert_path', 'путь к SSL-сертификату (client.crt)');
        $keyPath  = $this->requireReadableFile('ssl_key_path', 'путь к SSL-ключу (client.key)');
        // No silent default for the group: `main` on the test contour is an FFD 1.05 group and yields
        // «Группа не поддерживает ФФД 1.2»; the group must be the one registered for the FFD 1.2 kassa.
        foreach (array('inn' => 'ИНН', 'key' => 'ключ (key)', 'group' => 'группа устройств (group, напр. main_2)') as $k => $label) {
            if ($this->config($k, '') === '') {
                throw new RuntimeException('Не указан ' . $label . ' (настройки модуля).');
            }
        }

        $raw = file_get_contents($signPath);
        $signPem = (strpos($raw, '<RSAKeyValue') !== false || strpos($raw, '<?xml') !== false)
            ? OrangeDataClient::xmlRsaPrivateKeyToPem($raw)
            : $raw;

        return array(
            'base_url'      => $this->config('base_url', 'https://apip.orangedata.ru:12001/api/v2/'),
            'inn'           => $this->config('inn', ''),
            'key'           => $this->config('key', ''),
            'group'         => $this->config('group', 'main'),
            'ssl_cert_path' => $certPath,
            'ssl_key_path'  => $keyPath,
            'ssl_key_pass'  => $this->config('ssl_key_pass', ''),
            'sign_key_pem'  => $signPem,
            'client_info'   => array(
                'name'    => $this->config('client_name', 'UniqSport OpenCart'),
                'version' => $this->config('client_version', '1.0.0'),
                'id'      => $this->config('client_id', 'uniqsport-oc3'),
                'token'   => $this->config('client_token', self::moduleChecksum()),
            ),
            'verify_peer'   => (bool) $this->config('verify_peer', 0),
            'timeout'       => 30,
        );
    }

    /**
     * moduleChecksum
     *
     * Default ПМСР `clientInfo.token` («контрольная сумма исполняемого файла ПМСР»): MD5 over this module's
     * library sources, so it changes whenever the cash-software code changes. Used when the setting is left
     * empty — OrangeData rejects a check whose clientInfo.token is missing. Scope: buildClientConfig().
     *
     * @return string
     */
    public static function moduleChecksum() {
        $files = glob(__DIR__ . '/*.php');
        sort($files);
        $ctx = hash_init('md5');
        foreach ($files as $f) {
            hash_update_file($ctx, $f);
        }
        return hash_final($ctx);
    }

    /**
     * getClient
     *
     * Returns a configured OrangeDataClient whose logger writes to system storage orangedata.log.
     * Scope: internal + settings test action.
     *
     * @return OrangeDataClient
     */
    public function getClient() {
        $log = new Log('orangedata.log');
        $logger = function ($level, $message, $context) use ($log) {
            $log->write('[' . $level . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        };
        return new OrangeDataClient($this->buildClientConfig(), $logger);
    }

    /**
     * buildTrueApiClient
     *
     * Returns a TrueApiClient (ГИС МТ True API v4, X-API-KEY) configured from the module settings, logging to
     * orangedata.log. Throws when no key is configured so the caller reports a clear error.
     * Scope: checkOrder() with check_backend = trueapi, and the settings «Проверить True API» action.
     *
     * @return TrueApiClient
     */
    public function buildTrueApiClient() {
        $key = (string) $this->config('trueapi_key', '');
        if ($key === '') {
            throw new RuntimeException('Не указан X-API-KEY для True API (настройки модуля).');
        }
        $log = new Log('orangedata.log');
        $logger = function ($level, $message, $context) use ($log) {
            $log->write('[' . $level . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        };
        return new TrueApiClient(array(
            'env'      => $this->config('trueapi_env', 'prod') === 'sandbox' ? 'sandbox' : 'prod',
            'base_url' => (string) $this->config('trueapi_base_url', ''), // not in the UI: tests / custom hosts only
            'api_key'  => $key,
            'proxy'   => (string) $this->config('trueapi_proxy', ''),
        ), $logger);
    }

    /**
     * getCheckBackend
     *
     * The single active разрешительный-режим check backend (`check_backend` setting). One at a time, no
     * automatic failover — the owner switches backends in phases:
     *   trueapi    — ГИС МТ True API v4 codes/check with X-API-KEY (current phase)
     *   orangedata — OrangeData ТС ПИоТ /api/v2/piot/codes/check
     * (ЕСМ ТС ПИоТ of АО ЕСП is the next phase.) Scope: checkOrder().
     *
     * @return string
     */
    public function getCheckBackend() {
        $b = (string) $this->config('check_backend', 'orangedata');
        return in_array($b, array('trueapi', 'orangedata'), true) ? $b : 'orangedata';
    }

    private function getBuilder() {
        return new OrangeDataReceiptBuilder(array(
            'taxation_system'     => (int) $this->config('taxation_system', 1),
            'tax'                 => (int) $this->config('tax', 6),
            'marked_subject_type' => (int) $this->config('marked_subject_type', 33),
            'payment_type'        => (int) $this->config('payment_type', 14),
        ));
    }

    /* ------------------------------------------------------------------ *
     *  Response normalisation
     * ------------------------------------------------------------------ */

    /**
     * normalizeCheck
     *
     * Reduces a checkPiotCodes() response for a SINGLE submitted code to the flat fields stored per unit.
     * Handles both shapes: the 200 response (result.codesResponse[0].codes[0]) and the 203/514/-1 responses
     * (top-level statusCode + checkResult).
     *
     * Scope: internal, called once per code after a check.
     *
     * @param array $resp Client result: ['http_code','body','raw','error'].
     * @return array Flat fields for ModelExtensionModuleOrangedataPiot::saveCheckResult().
     */
    public function normalizeCheck(array $resp) {
        $out = array(
            'is_sale_allowed' => null, 'status_code' => null, 'reason_code' => null, 'reason_text' => '',
            'req_id' => '', 'req_timestamp' => null, 'tag1265' => '', 'is_checked_offline' => 0,
            'gtin' => '', 'pg' => null, 'response_json' => $resp['raw'],
        );

        if (!empty($resp['error']) || empty($resp['body'])) {
            $out['status_code'] = -1;
            $out['is_sale_allowed'] = 0;
            $out['reason_text'] = !empty($resp['error']) ? $resp['error'] : 'Пустой ответ ТС ПИоТ';
            return $out;
        }

        $body = $resp['body'];
        $out['status_code'] = isset($body['statusCode']) ? (int) $body['statusCode'] : $resp['http_code'];

        // 203 / 514 / -1 shape: top-level checkResult, no per-code data.
        if (!isset($body['result']['codesResponse'][0])) {
            if (isset($body['checkResult']['isSaleAllowed'])) {
                $out['is_sale_allowed'] = (int) (bool) $body['checkResult']['isSaleAllowed'];
            }
            if (isset($body['statusDescription'])) {
                $out['reason_text'] = $body['statusDescription'];
            }
            return $out; // Аварийный (203): allowed, no tag1265 => tag 1260 omitted downstream.
        }

        $cr = $body['result']['codesResponse'][0];
        $out['req_id']             = isset($cr['reqId']) ? $cr['reqId'] : '';
        $out['req_timestamp']      = isset($cr['reqTimestamp']) ? (int) $cr['reqTimestamp'] : null;
        $out['tag1265']            = isset($cr['tag1265']) ? $cr['tag1265'] : '';
        $out['is_checked_offline'] = !empty($cr['isCheckedOffline']) ? 1 : 0;

        $code = isset($cr['codes'][0]) ? $cr['codes'][0] : array();
        if (isset($code['gtin'])) $out['gtin'] = $code['gtin'];
        if (isset($code['groupIds'][0])) $out['pg'] = (int) $code['groupIds'][0];

        if (isset($code['checkResult']['isSaleAllowed'])) {
            $out['is_sale_allowed'] = (int) (bool) $code['checkResult']['isSaleAllowed'];
            if (empty($out['is_sale_allowed']) && isset($code['checkResult']['reason'])) {
                $out['reason_code'] = isset($code['checkResult']['reason']['code']) ? (int) $code['checkResult']['reason']['code'] : null;
                $out['reason_text'] = isset($code['checkResult']['reason']['description']) ? $code['checkResult']['reason']['description'] : '';
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ *
     *  High-level operations
     * ------------------------------------------------------------------ */

    /**
     * checkOrder
     *
     * Runs the разрешительный-режим check for every scanned code of an order (one request per code so each unit
     * gets its own reqId/tag1265) and stores the verdicts, using the single backend from getCheckBackend().
     * A backend that cannot answer stores status -1 («проверка недоступна»), which blocks fiscalization of that
     * unit; there is no automatic switch to another backend. The backend is recorded in `check_backend`.
     *
     * Scope: the "Проверить" action on the order panel, and the event before fiscalization.
     *
     * @param int $order_id
     * @return array ['checked','allowed','denied' => int, 'backend' => string, 'codes' => rows]
     */
    public function checkOrder($order_id) {
        $repo    = $this->repo();
        $backend = $this->getCheckBackend();
        $rows    = $repo->getOrderMarkingCodes($order_id);

        $client = null; $clientError = null; // built once per run; True API host is selected on the first call

        $allowed = 0; $denied = 0; $checked = 0;
        foreach ($rows as $row) {
            // Never re-check an already-fiscalized code: its stored tag1265 is the audit link to the issued receipt.
            if (!empty($row['fiscalized'])) {
                continue;
            }
            if ($row['km_base64'] === '' && $row['km_raw'] === '') {
                continue;
            }

            if ($client === null && $clientError === null) {
                try {
                    $client = $backend === 'trueapi' ? $this->buildTrueApiClient() : $this->getClient();
                } catch (Exception $e) {
                    $clientError = $e->getMessage(); // misconfiguration: every unit gets the same clear reason
                }
            }

            if ($clientError !== null) {
                $norm = array('status_code' => -1, 'is_sale_allowed' => 0, 'reason_text' => $clientError);
            } else {
                try {
                    if ($backend === 'trueapi') {
                        // True API takes the raw code (GS kept); one code per request → its own reqId/tag1265.
                        $resp = $client->checkCodes(array($row['km_raw']), null, (string) $this->config('trueapi_fn', ''));
                        $norm = TrueApiClient::normalizeResponse($resp);
                    } else {
                        $cis  = $row['km_base64'] !== '' ? $row['km_base64'] : OrangeDataClient::encodeCisForCheck($row['km_raw']);
                        $norm = $this->normalizeCheck($client->checkPiotCodes(array(array('cis' => $cis))));
                    }
                } catch (Exception $e) {
                    $norm = array('status_code' => -1, 'is_sale_allowed' => 0, 'reason_text' => $e->getMessage());
                }
            }

            $norm['check_backend'] = $backend;
            $repo->saveCheckResult($row['marking_code_id'], $norm);
            $checked++;
            if (!empty($norm['is_sale_allowed'])) { $allowed++; } else { $denied++; }
        }

        return array('checked' => $checked, 'allowed' => $allowed, 'denied' => $denied, 'backend' => $backend, 'codes' => $repo->getOrderMarkingCodes($order_id));
    }

    /**
     * fiscalizeOrder
     *
     * Builds and submits the fiscal receipt for an order: each marked unit becomes a position with its
     * itemCode + industryAttribute (tag 1260/1265); unmarked products and shipping become plain positions.
     * Refuses to fiscalize if any marked unit is unscanned or sale-denied (unless аварийный режим).
     *
     * Scope: the "Фискализировать" action on the order panel, and the auto-fiscalize event.
     *
     * @param int  $order_id
     * @param bool $ignoreItemCodeCheck Skip the ФН code check (test contour only).
     * @return array ['success' => bool, 'message' => string, 'document_id' => string, 'status' => array|null]
     */
    public function fiscalizeOrder($order_id, $ignoreItemCodeCheck = null) {
        $repo = $this->repo();
        $db = $this->db();

        // Query order tables directly so this works in BOTH admin and catalog (event) contexts.
        $order_id = (int) $order_id;
        $orderQ = $db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE order_id = '" . $order_id . "'");
        if (!$orderQ->num_rows) {
            return array('success' => false, 'message' => 'Заказ не найден', 'document_id' => '', 'status' => null);
        }
        $order = $orderQ->row;

        $products = $db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = '" . $order_id . "'")->rows;
        $codesByLine = array();
        foreach ($repo->getOrderMarkingCodes($order_id) as $c) {
            $codesByLine[$c['order_product_id']][] = $c;
        }

        $categoryMap = OrangeDataRepository::parseCategoryMap($this->config('marked_categories', array()));

        // Build the discount-input in the SAME gateway shape AlfabankDiscount expects (itemPrice/itemAmount
        // in kopecks, quantity.value = count) so the зачёт-аванса check distributes the whole-order discount
        // exactly as the Alfabank prepayment check did. $goods receive the discount; the service lines
        // (shipping, COD fee) stay at full price. Marking metadata rides along under '_op' and survives
        // normalizeItems() because that method preserves the original gate array (arrGate).
        $goods = array();
        foreach ($products as $p) {
            $opId      = (int) $p['order_product_id'];
            $marking   = $repo->getProductMarking($p['product_id'], $categoryMap);
            $lineCodes = isset($codesByLine[$opId]) ? $codesByLine[$opId] : array();
            $isMarked  = !empty($marking['is_marked']) || !empty($lineCodes);
            $qty       = (float) $p['quantity'];
            $unitRub   = round((float) $p['price'] + (float) $p['tax'], 2); // per-unit price incl. tax

            $units = null;
            if ($isMarked) {
                if (count($lineCodes) < (int) $p['quantity']) {
                    return array('success' => false, 'blocked' => true, 'message' => 'Не отсканированы все КМ для позиции: ' . $p['name'] . ' (' . count($lineCodes) . '/' . (int)$p['quantity'] . ')', 'document_id' => '', 'status' => null);
                }
                $units = array();
                foreach ($lineCodes as $c) {
                    $emergency = ((int) $c['status_code'] === 203);
                    if (empty($c['is_sale_allowed']) && !$emergency) {
                        $unchecked = $c['status_code'] === null || in_array((int) $c['status_code'], array(-1, 514), true);
                        $prefix = $unchecked ? 'Проверка КМ не выполнена (' : 'Продажа запрещена для КМ (';
                        return array('success' => false, 'blocked' => true, 'message' => $prefix . $p['name'] . '): ' . ($c['reason_text'] !== '' ? $c['reason_text'] : 'нажмите «Проверить»'), 'document_id' => '', 'status' => null);
                    }
                    $units[] = array('item_code' => $c['km_raw'], 'tag1265' => $c['tag1265'], 'emergency' => $emergency);
                }
            }

            $goods[] = array(
                'positionId' => $opId,
                'name'       => $p['name'],
                'quantity'   => array('value' => $qty, 'measure' => 0),
                'itemPrice'  => (int) round($unitRub * 100),
                'itemAmount' => (int) round($unitRub * $qty * 100),
                '_op'        => array('opId' => $opId, 'marked' => $isMarked, 'units' => $units),
            );
        }

        // Full-price service lines: shipping + the CDEK cash-on-delivery fee (positive surcharges that add
        // to order.total). Same code set as the Alfabank path so both checks agree.
        $service_codes = array('shipping', 'cod_cdek_total', 'cod_cdek', 'cod', 'cdek');
        $fullPrice = array();
        $totals = $db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = '" . $order_id . "' ORDER BY sort_order")->rows;
        foreach ($totals as $t) {
            if (in_array($t['code'], $service_codes, true) && (float) $t['value'] != 0) {
                $fullPrice[] = array(
                    'positionId' => $t['code'],
                    'name'       => $t['title'],
                    'quantity'   => array('value' => 1, 'measure' => 0),
                    'itemPrice'  => (int) round((float) $t['value'] * 100),
                    'itemAmount' => (int) round((float) $t['value'] * 100),
                    '_service'   => true,
                );
            }
        }

        if (!$goods && !$fullPrice) {
            return array('success' => false, 'message' => 'Нет позиций для чека', 'document_id' => '', 'status' => null);
        }

        // Distribute the whole-order discount across goods only (delivery/COD stay full price), mirroring the
        // Alfabank check position-for-position. $amountKop is what the customer actually paid.
        require_once DIR_SYSTEM . 'library/alfabank/AlfabankDiscount.php';
        $amountKop   = (int) round(round((float) $order['total'] * (float) $order['currency_value'], 2) * 100);
        $discounter  = new AlfabankDiscount();
        $distributed = $discounter->distribute($goods, $fullPrice, $amountKop);

        // Map the distributed gateway positions into OrangeData receipt positions (pure, testable).
        $expanded = self::expandDistributed($distributed);
        if ($expanded['error'] !== null) {
            return array('success' => false, 'message' => $expanded['error'] . '; фискализация отменена', 'document_id' => '', 'status' => null);
        }
        $positions = $expanded['positions'];

        if (!$positions) {
            return array('success' => false, 'message' => 'Нет позиций для чека', 'document_id' => '', 'status' => null);
        }

        // Reconciliation guard: the receipt must sum to the amount actually paid. This is what keeps the
        // "only Alfabank orders are fiscalized" assumption safe — any order whose totals do not reconcile
        // (e.g. an order-level order_total code not placed in a group → a positive/negative gap that
        // distribute() could not absorb) is refused rather than sent wrong.
        $sum = 0.0;
        foreach ($positions as $pos) {
            $sum += $pos['price'] * $pos['quantity'];
        }
        if (abs($sum - $amountKop / 100) > 0.01) {
            return array('success' => false, 'message' => 'Чек не сходится с суммой заказа (' . number_format($sum, 2, '.', '') . ' ≠ ' . number_format($amountKop / 100, 2, '.', '') . '); фискализация отменена', 'document_id' => '', 'status' => null);
        }

        $builder = $this->getBuilder();
        $client  = $this->getClient();

        // Receipt id. OrangeData keeps every id it has seen, so a REJECTED attempt (422) burns its id — a retry
        // with the same id would only get 409 and the old rejection. Look at the last attempt stored for this
        // order: registered → done; still processing → keep polling it; rejected → next id (…-ship-2, -3, …).
        $base  = 'oc-' . (int) $order_id . '-ship';
        $docId = $base;
        $prevId = '';
        foreach ($repo->getOrderMarkingCodes($order_id) as $c) {
            if ($c['document_id'] !== '') { $prevId = $c['document_id']; break; }
        }
        if ($prevId !== '') {
            $prev = $client->getDocumentStatus($prevId);
            if ((int) $prev['http_code'] === 200 && !empty($prev['body']['fp'])) {
                $repo->markFiscalized($order_id, $prevId);
                return array('success' => true, 'message' => 'Чек уже зарегистрирован (ФП ' . $prev['body']['fp'] . ')', 'document_id' => $prevId, 'status' => $prev['body']);
            }
            if ((int) $prev['http_code'] === 202) {
                return $this->confirmDocument($client, $repo, $order_id, $prevId);
            }
            if ((int) $prev['http_code'] === 422 || (int) $prev['http_code'] === 200) {
                $n = preg_match('/^-(\d+)$/', substr($prevId, strlen($base)), $m) ? (int) $m[1] + 1 : 2;
                $docId = $base . '-' . $n;
            } else {
                $docId = $prevId; // unknown to OrangeData (never accepted) → safe to reuse
            }
        }

        if ($ignoreItemCodeCheck === null) {
            $ignoreItemCodeCheck = (bool) $this->config('ignore_item_code_check', 0);
        }

        // Payment type per order payment method, from the configured code:type map. Card/online prepaid
        // via bank (Alfa/RBS/SBP/Yandex — bank already fiscalized the advance) -> 14 зачёт аванса;
        // bank_transfer -> 2 безнал; cod/cod_cdek -> 1 наличными. Unmapped codes fall back to payment_type.
        $paymentType = $this->paymentTypeForCode(isset($order['payment_code']) ? $order['payment_code'] : '');

        $document = $builder->document(array(
            'id'                    => $docId,
            'positions'             => $positions,
            'payment_type'          => $paymentType,
            'customer_contact'      => $this->orderContact($order),
            'ignore_item_code_check' => $ignoreItemCodeCheck,
        ));

        $create = $client->createDocument($document);

        // 201 = accepted into the queue, 409 = this id already accepted earlier. Neither means the ФН has
        // confirmed the receipt — only a status body carrying `fp` does. Record the id as pending first.
        if ($create['http_code'] === 201 || $create['http_code'] === 409) {
            $repo->setDocumentId($order_id, $docId);
            return $this->confirmDocument($client, $repo, $order_id, $docId);
        }

        $errs = isset($create['body']['errors']) ? implode('; ', $create['body']['errors']) : $create['raw'];
        return array('success' => false, 'message' => 'Ошибка createDocument (' . $create['http_code'] . '): ' . $errs, 'document_id' => '', 'status' => null);
    }

    /**
     * expandDistributed
     *
     * Maps the positions returned by AlfabankDiscount::distribute() into OrangeData receipt positions.
     * A marked line is expanded into one quantity-1 position per physical unit, each carrying its own КМ,
     * priced at the distributed per-unit price (itemPrice/100). A per-order_product-line cursor assigns
     * marking codes so that a line the rounding reconciliation split in two (N-1 units at price X, 1 unit
     * at X') still consumes each code exactly once. Full-price service lines ('_service') become service
     * positions (paymentSubjectType 4). Unmarked lines keep their aggregate quantity.
     *
     * Scope: internal to fiscalizeOrder(); public static only so it can be unit-tested without a DB (it is
     * the one place a wrong kopeck could attach to a specific marking code).
     *
     * @param array $distributed distribute() output: goods carry '_op' => ['opId','marked','units'],
     *                           service lines carry '_service' => true; all have itemPrice (kopecks) +
     *                           quantity.value.
     * @return array ['positions' => array, 'error' => string|null]  error != null means refuse fiscalization.
     */
    public static function expandDistributed(array $distributed) {
        $positions  = array();
        $codeCursor = array();
        foreach ($distributed as $pos) {
            if (!empty($pos['_service'])) {
                $positions[] = array('text' => $pos['name'], 'price' => $pos['itemPrice'] / 100, 'quantity' => 1, 'payment_subject_type' => 4);
                continue;
            }
            $unitPrice = $pos['itemPrice'] / 100;
            $count     = $pos['quantity']['value'];
            if (!empty($pos['_op']['marked'])) {
                if ((float) $count != (int) $count) {
                    return array('positions' => array(), 'error' => 'Дробное количество для маркированной позиции: ' . $pos['name']);
                }
                $opId  = $pos['_op']['opId'];
                $units = $pos['_op']['units'];
                if (!isset($codeCursor[$opId])) { $codeCursor[$opId] = 0; }
                for ($u = 0; $u < (int) $count; $u++) {
                    if (!isset($units[$codeCursor[$opId]])) {
                        return array('positions' => array(), 'error' => 'Не хватает КМ для позиции: ' . $pos['name']);
                    }
                    $unit = $units[$codeCursor[$opId]];
                    $codeCursor[$opId]++;
                    $positions[] = array(
                        'text'      => $pos['name'],
                        'price'     => $unitPrice,
                        'quantity'  => 1,
                        'item_code' => $unit['item_code'],
                        'tag1265'   => $unit['tag1265'],
                        'emergency' => $unit['emergency'],
                    );
                }
            } else {
                $positions[] = array(
                    'text'     => $pos['name'],
                    'price'    => $unitPrice,
                    'quantity' => (float) $count,
                );
            }
        }
        return array('positions' => $positions, 'error' => null);
    }

    /**
     * confirmDocument
     *
     * Polls a submitted document and marks the order's codes fiscalized ONLY when the ФН returns a fiscal
     * sign (`fp`). While still queued (202) or if the ФН rejected it, the codes stay unfiscalized so a
     * retry/status re-check is possible. Scope: internal, after createDocument returns 201/409.
     *
     * @return array ['success','pending'?,'message','document_id','status']
     */
    private function confirmDocument($client, $repo, $order_id, $docId) {
        $status = null; $http = 202;
        for ($i = 0; $i < 10; $i++) {
            usleep(1500000);
            $s = $client->getDocumentStatus($docId);
            $http = (int) $s['http_code'];
            if ($http !== 202) { $status = $s['body']; break; }
        }

        // 422 = the kassa's ФН/ОИСМ did not confirm the marking codes (tag 2106) — no receipt was formed
        // (API §2.2.2). The body lists every position with its checkResult.
        if ($http === 422) {
            $parts = array();
            foreach ((array) $status as $p) {
                if (!is_array($p)) continue;
                $cr = isset($p['checkResult']) ? $p['checkResult'] : array();
                $parts[] = 'позиция ' . ((int) (isset($p['position']) ? $p['position'] : 0) + 1)
                    . ': КМ не подтверждён ФН (checkResult ' . (isset($cr['checkResult']) ? $cr['checkResult'] : '?')
                    . ', fsCheckStatus ' . (isset($cr['fsCheckStatus']) ? $cr['fsCheckStatus'] : '?') . ')';
            }
            $msg = 'Чек не сформирован — ФН отклонил коды маркировки (422). ' . implode('; ', $parts) . '.';
            $base = (string) $this->config('base_url', '');
            if (strpos($base, 'apip.') !== false || strpos($base, ':12001') !== false) {
                $msg .= ' Тестовая касса не может подтвердить ваши реальные коды — на тестовом контуре включите «Пропускать проверку КМ в ФН».';
            }
            return array('success' => false, 'rejected' => true, 'message' => $msg, 'document_id' => $docId, 'status' => $status);
        }

        if (is_array($status) && !empty($status['fp'])) {
            $repo->markFiscalized($order_id, $docId);
            return array('success' => true, 'message' => 'Чек зарегистрирован (ФП ' . $status['fp'] . ')', 'document_id' => $docId, 'status' => $status);
        }

        // Processed but the ФН/ОФД reported an error (no fp).
        if (is_array($status) && (isset($status['errorCode']) || isset($status['errors']))) {
            $msg = isset($status['errors']) ? implode('; ', (array) $status['errors']) : ('errorCode ' . $status['errorCode']);
            return array('success' => false, 'message' => 'Чек отклонён: ' . $msg, 'document_id' => $docId, 'status' => $status);
        }

        // Still queued after the poll window — not an error, but not yet confirmed.
        return array('success' => true, 'pending' => true, 'message' => 'Чек отправлен, ожидает обработки (проверьте статус позже)', 'document_id' => $docId, 'status' => $status);
    }

    /**
     * verifyReceipt
     *
     * Reads the order's receipt back from OrangeData (document status = the fiscal document as registered) and
     * compares it with what we stored: per marked position the item code (tag 1163), the отраслевой реквизит
     * (tag 1260) and whether its value (tag 1265) equals the tag1265 of the check for that code. The public
     * receipt viewer does not display tag 1260/1265, so this is the way to confirm them.
     * Scope: the «Сверить чек» action on the order panel. Read-only.
     *
     * @param int $order_id
     * @return array ['ok' => bool, 'message' => string, 'document_id', 'http_code', 'fp', 'document_number',
     *                'processed_at', 'payments' => [], 'positions' => [ [n, text, subject, item_code, has_1260,
     *                tag1265, expected, match] ], 'all_match' => bool]
     */
    public function verifyReceipt($order_id) {
        $repo = $this->repo();
        $byCode = array(); $docId = '';
        foreach ($repo->getOrderMarkingCodes($order_id) as $c) {
            if ($c['document_id'] !== '' && $docId === '') $docId = $c['document_id'];
            $byCode[$c['km_raw']] = $c['tag1265'];
        }
        if ($docId === '') {
            return array('ok' => false, 'message' => 'По заказу ещё не отправлялся чек.');
        }

        $s = $this->getClient()->getDocumentStatus($docId);
        $out = array('ok' => (int) $s['http_code'] === 200, 'document_id' => $docId, 'http_code' => (int) $s['http_code'],
            'message' => '', 'fp' => '', 'document_number' => '', 'processed_at' => '', 'payments' => array(),
            'positions' => array(), 'all_match' => true, 'ignore_item_code_check' => null);
        if (!$out['ok']) {
            $out['message'] = (int) $s['http_code'] === 202 ? 'Чек ещё обрабатывается.' : 'Статус чека: HTTP ' . (int) $s['http_code'];
            return $out;
        }

        $b = $s['body'];
        $out['fp']              = isset($b['fp']) ? (string) $b['fp'] : '';
        $out['document_number'] = isset($b['documentNumber']) ? (string) $b['documentNumber'] : '';
        $out['processed_at']    = isset($b['processedAt']) ? (string) $b['processedAt'] : '';
        $out['ignore_item_code_check'] = isset($b['ignoreItemCodeCheck']) ? (bool) $b['ignoreItemCodeCheck'] : null;
        $out['payments']        = isset($b['content']['checkClose']['payments']) ? $b['content']['checkClose']['payments'] : array();

        $positions = isset($b['content']['positions']) ? $b['content']['positions'] : array();
        foreach ($positions as $i => $p) {
            $item = isset($p['itemCode']) ? (string) $p['itemCode'] : '';
            $value = isset($p['industryAttribute']['value']) ? (string) $p['industryAttribute']['value'] : '';
            $expected = ($item !== '' && isset($byCode[$item])) ? (string) $byCode[$item] : '';
            $match = $item === '' ? null : ($value !== '' && $value === $expected);
            if ($match === false) $out['all_match'] = false;
            $out['positions'][] = array(
                'n'         => $i + 1,
                'text'      => isset($p['text']) ? (string) $p['text'] : '',
                'subject'   => isset($p['paymentSubjectType']) ? (int) $p['paymentSubjectType'] : null,
                'item_code' => $item,
                'has_1260'  => isset($p['industryAttribute']),
                'attr'      => isset($p['industryAttribute']) ? $p['industryAttribute'] : null,
                'tag1265'   => $value,
                'expected'  => $expected,
                'match'     => $match,
            );
        }
        return $out;
    }

    /**
     * paymentTypeForCode
     *
     * Resolves the checkClose payment type (tag 1031/1081/1215) for an order payment method from the
     * configured `payment_type_map` ("code:type" per line), falling back to `payment_type` (default 14).
     * Scope: internal, used when building the receipt.
     *
     * @param string $code order.payment_code
     * @return int
     */
    private function paymentTypeForCode($code) {
        $map = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) $this->config('payment_type_map', '')) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) continue;
            list($c, $t) = explode(':', $line, 2);
            $map[trim($c)] = (int) trim($t);
        }
        $code = (string) $code;
        if ($code !== '' && isset($map[$code])) {
            return $map[$code];
        }
        return (int) $this->config('payment_type', 14);
    }

    /** Picks a valid customerContact (email or phone) from the order, else "none". */
    private function orderContact(array $order) {
        if (!empty($order['email']) && strpos($order['email'], '@') !== false) {
            return $order['email'];
        }
        if (!empty($order['telephone'])) {
            $tel = preg_replace('/[^0-9+]/', '', $order['telephone']);
            if ($tel !== '') return (strpos($tel, '+') === 0 ? $tel : '+' . ltrim($tel, '+'));
        }
        return 'none';
    }
}
