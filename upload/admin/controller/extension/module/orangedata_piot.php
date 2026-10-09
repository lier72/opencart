<?php
/**
 * Admin controller for the OrangeData ТС ПИоТ / разрешительный режим module.
 *
 * Provides:
 *   - index()          the settings screen (credentials, cert paths, ПМСР clientInfo, taxation, trigger).
 *   - testConnection() AJAX: verify credentials against ТС ПИоТ with a sample code.
 *   - order()          AJAX: render the marking panel injected into the order info screen.
 *   - scan/check/fiscalize/removeCode() AJAX actions backing that panel.
 *   - install/uninstall() create tables + register the order-status event.
 *
 * Scope: the admin side of the integration. Heavy lifting is delegated to the OrangeDataService system
 * library so the same logic serves the catalog-side auto-fiscalize event.
 */
class ControllerExtensionModuleOrangedataPiot extends Controller {

    private $error = array();

    /** Fully-qualified setting keys persisted for this module. */
    private $fields = array(
        'module_orangedata_piot_status',
        'module_orangedata_piot_base_url',
        'module_orangedata_piot_inn',
        'module_orangedata_piot_key',
        'module_orangedata_piot_group',
        'module_orangedata_piot_ssl_cert_path',
        'module_orangedata_piot_ssl_key_path',
        'module_orangedata_piot_ssl_key_pass',
        'module_orangedata_piot_sign_key_path',
        'module_orangedata_piot_client_name',
        'module_orangedata_piot_client_version',
        'module_orangedata_piot_client_id',
        'module_orangedata_piot_client_token',
        'module_orangedata_piot_taxation_system',
        'module_orangedata_piot_tax',
        'module_orangedata_piot_marked_subject_type',
        'module_orangedata_piot_marked_categories',
        'module_orangedata_piot_payment_type',
        'module_orangedata_piot_payment_type_map',
        'module_orangedata_piot_trigger_status_id',
        'module_orangedata_piot_verify_peer',
        'module_orangedata_piot_ignore_item_code_check',
        'module_orangedata_piot_auto_fiscalize',
        'module_orangedata_piot_check_backend',
        'module_orangedata_piot_trueapi_env',
        'module_orangedata_piot_trueapi_key',
        'module_orangedata_piot_trueapi_fn',
        'module_orangedata_piot_trueapi_proxy',
    );

    public function index() {
        $data = array_merge(array(), (array) $this->load->language('extension/module/orangedata_piot'));
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('extension/module/orangedata_piot');
        $this->load->model('localisation/order_status');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // Ensure tables exist (idempotent) whenever settings are saved.
            $this->model_extension_module_orangedata_piot->install();

            $post = array();
            foreach ($this->fields as $field) {
                $post[$field] = isset($this->request->post[$field]) ? $this->request->post[$field] : '';
            }
            $this->model_setting_setting->editSetting('module_orangedata_piot', $post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        $data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/orangedata_piot', 'user_token=' . $this->session->data['user_token'], true));

        $data['action'] = $this->url->link('extension/module/orangedata_piot', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['test_connection'] = $this->jsUrl('extension/module/orangedata_piot/testConnection', 'user_token=' . $this->session->data['user_token']);
        $data['test_trueapi'] = $this->jsUrl('extension/module/orangedata_piot/testTrueApi', 'user_token=' . $this->session->data['user_token']);
        $data['scan_test'] = $this->jsUrl('extension/module/orangedata_piot/scanTest', 'user_token=' . $this->session->data['user_token']);

        // Populate every field: POST value (on validation error) falls back to stored setting.
        foreach ($this->fields as $field) {
            $data[$field] = isset($this->request->post[$field]) ? $this->request->post[$field] : $this->config->get($field);
        }

        // Sensible default payment-type map (real payment_code values of this store) on first load.
        if ($data['module_orangedata_piot_payment_type_map'] === null || $data['module_orangedata_piot_payment_type_map'] === '') {
            $data['module_orangedata_piot_payment_type_map'] = "rbs:14\nalfabank:14\nsbp:14\nyandexplusplus:14\nyandexplusplus_card:14\nycp:14\nbank_transfer:2\ncod:1\ncod_cdek:1";
        }

        // Marked-categories picker: resolve names for existing rows + provide the group list + autocomplete.
        $this->load->model('catalog/category');
        $data['user_token'] = $this->session->data['user_token'];
        $data['marking_groups'] = $this->getMarkingGroups();

        $saved = isset($this->request->post['module_orangedata_piot_marked_categories'])
            ? $this->request->post['module_orangedata_piot_marked_categories']
            : $this->config->get('module_orangedata_piot_marked_categories');
        $data['marked_categories'] = array();
        if (is_array($saved)) {
            foreach ($saved as $row) {
                if (empty($row['category_id'])) continue;
                $cat = $this->model_catalog_category->getCategory($row['category_id']);
                // getCategory()['path'] holds only the ANCESTORS (it excludes the category itself), so append the
                // category's own name — same "Parent > Child" display as OpenCart's category list.
                $name = $cat
                    ? (!empty($cat['path']) ? $cat['path'] . '&nbsp;&nbsp;&gt;&nbsp;&nbsp;' . $cat['name'] : $cat['name'])
                    : ('#' . (int) $row['category_id']);
                $data['marked_categories'][] = array(
                    'category_id' => (int) $row['category_id'],
                    'name'        => $name,
                    'group_id'    => (int) $row['group_id'],
                );
            }
        }

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Select option sources (labels come from language file where relevant).
        $data['taxation_systems'] = array(
            array('id' => 0, 'name' => $this->language->get('text_tax_osn')),
            array('id' => 1, 'name' => $this->language->get('text_tax_usn_income')),
            array('id' => 2, 'name' => $this->language->get('text_tax_usn_income_outcome')),
            array('id' => 5, 'name' => $this->language->get('text_tax_patent')),
        );
        $data['marked_subject_types'] = array(
            array('id' => 33, 'name' => $this->language->get('text_subject_tm')),
            array('id' => 31, 'name' => $this->language->get('text_subject_atm')),
        );
        $data['check_backends'] = array(
            array('id' => 'trueapi',    'name' => $this->language->get('text_backend_trueapi')),
            array('id' => 'orangedata', 'name' => $this->language->get('text_backend_orangedata')),
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/orangedata_piot', $data));
    }

    /**
     * jsUrl
     *
     * Admin link for use inside JavaScript. Url::link() emits `&amp;` separators (HTML-safe) and Twig autoescape
     * is off here, so a raw link in a JS string would send `amp;user_token` and hit the login redirect. Decoding
     * gives real `&` separators. Scope: every AJAX URL handed to the settings and order-panel templates.
     *
     * @param string $route
     * @param string $args
     * @return string
     */
    private function jsUrl($route, $args) {
        return html_entity_decode($this->url->link($route, $args, true), ENT_QUOTES, 'UTF-8');
    }

    /**
     * getMarkingGroups
     *
     * Labeled товарные группы (Приложение 1 «Список поддерживаемых товарных групп») for the category and
     * product-form dropdowns, so the user picks a name instead of typing a group number. Scope: settings UI.
     *
     * @return array [group_id => name]
     */
    private function getMarkingGroups() {
        return array(
            1  => 'Одежда, бельё',
            2  => 'Обувь',
            3  => 'Табачная продукция',
            4  => 'Духи и туалетная вода',
            5  => 'Шины и покрышки',
            6  => 'Фотокамеры и лампы-вспышки',
            8  => 'Молочная продукция',
            9  => 'Велосипеды',
            10 => 'Медицинские изделия',
            12 => 'Альтернативная табачная продукция',
            13 => 'Упакованная вода',
            14 => 'Товары из натурального меха',
            15 => 'Пиво и слабоалкогольные напитки',
            16 => 'Никотинсодержащая продукция',
            17 => 'БАД к пище',
            19 => 'Антисептики',
            20 => 'Корма для животных',
            21 => 'Морепродукты',
            22 => 'Безалкогольное пиво',
            23 => 'Соковая продукция и напитки',
            26 => 'Ветеринарные препараты',
            32 => 'Консервированная продукция',
            33 => 'Растительные масла',
        );
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    /**
     * testConnection
     *
     * AJAX: runs a sample ТС ПИоТ check against the configured contour using the saved credentials and
     * reports whether the mTLS + signature round-trip succeeds. Scope: the settings "Test connection" button.
     */
    public function testConnection() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
            $service = new OrangeDataService($this->registry);
            $service->setOverrides($this->request->post); // test what is in the form, saved or not
            $client = $service->getClient();
            // Documented sample code (Base64) — a real code is not needed to prove connectivity/signature.
            $resp = $client->checkPiotCodes(array(array('cis' => 'MDEwNDYyOTMwODg3NzA0NDIxRHprY1l0Mh04MDA1MDkwMDAwHTkzZEdWeg==')));
            if ($resp['http_code'] === 200) {
                $json['success'] = sprintf($this->language->get('text_test_ok'), $resp['http_code']);
            } elseif ($resp['error']) {
                $json['error'] = sprintf($this->language->get('text_test_fail'), $resp['error']);
            } else {
                $json['error'] = sprintf($this->language->get('text_test_http'), $resp['http_code'], substr($resp['raw'], 0, 300));
            }
        } catch (Exception $e) {
            $json['error'] = sprintf($this->language->get('text_test_fail'), $e->getMessage());
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * testTrueApi
     *
     * AJAX: with the X-API-KEY currently in the form, calls True API `cdn/info` (requires the key) and health-checks
     * the CDN hosts it lists, reporting the host checks would use. No marking code is sent.
     * Scope: the settings «Проверить True API» button.
     */
    public function testTrueApi() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
                $service = new OrangeDataService($this->registry);
                $service->setOverrides($this->request->post);
                $sel  = $service->buildTrueApiClient()->selectHost();
                $info = $sel['info'];
                if ($info['error']) {
                    $json['error'] = sprintf($this->language->get('text_test_fail'), $info['error']);
                } elseif (in_array((int) $info['http_code'], array(401, 403), true)) {
                    $json['error'] = $this->language->get('text_trueapi_key_fail');
                } elseif ((int) $info['http_code'] === 410) {
                    $json['error'] = $this->language->get('text_trueapi_gone');
                } elseif ($sel['host'] === null) {
                    $json['error'] = sprintf($this->language->get('text_trueapi_no_host'), (int) $info['http_code'], substr($info['raw'], 0, 300));
                } else {
                    $ms = '';
                    foreach ($sel['candidates'] as $c) {
                        if ($c['host'] === $sel['host'] && $c['avgTimeMs'] !== null) $ms = ', ' . $c['avgTimeMs'] . ' ms';
                    }
                    $json['success'] = sprintf($this->language->get('text_trueapi_ok'), $sel['host'] . $ms);
                }
            } catch (Exception $e) {
                $json['error'] = sprintf($this->language->get('text_test_fail'), $e->getMessage());
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /* ------------------------------------------------------------------ *
     *  Order panel (injected into sale/order info view via OCMOD)
     * ------------------------------------------------------------------ */

    /**
     * order
     *
     * AJAX: renders the ТС ПИоТ marking panel for one order — its lines, per-unit scanned codes and their
     * verdicts, and the action buttons. Scope: loaded by the OCMOD-injected block on the order info page.
     */
    public function order() {
        $data = array_merge(array(), (array) $this->load->language('extension/module/orangedata_piot'));
        $this->load->model('extension/module/orangedata_piot');
        $this->load->model('sale/order');

        $order_id = isset($this->request->get['order_id']) ? (int) $this->request->get['order_id'] : 0;

        require_once DIR_SYSTEM . 'library/orangedata/OrangeDataRepository.php';
        $categoryMap = OrangeDataRepository::parseCategoryMap($this->config->get('module_orangedata_piot_marked_categories'));

        $products = $this->model_sale_order->getOrderProducts($order_id);
        $codesByLine = array();
        foreach ($this->model_extension_module_orangedata_piot->getOrderMarkingCodes($order_id) as $c) {
            $codesByLine[$c['order_product_id']][(int) $c['unit_index']] = $c;
        }

        // Resolve each line's own EAN barcode(s) for EAN-first scan routing. A line is one specific variant
        // (size/colour), so we match by its order_option.product_option_value_id against the per-variant cache;
        // single-variant goods (no options) fall back to ocus_product.ean.
        $lineOptions = array();   // order_product_id => [ 'pov_ids' => [...], 'text' => '…' ]
        $allPovIds   = array();
        foreach ($products as $p) {
            $pov_ids = array();
            $parts   = array();
            foreach ($this->model_sale_order->getOrderOptions($order_id, $p['order_product_id']) as $opt) {
                if (!empty($opt['product_option_value_id'])) {
                    $pov_ids[] = (int) $opt['product_option_value_id'];
                    $allPovIds[] = (int) $opt['product_option_value_id'];
                }
                $parts[] = $opt['name'] . ': ' . $opt['value'];
            }
            $lineOptions[$p['order_product_id']] = array('pov_ids' => $pov_ids, 'text' => implode(', ', $parts));
        }
        $variantEans = $this->model_extension_module_orangedata_piot->getVariantEans($allPovIds);
        $productEans = $this->model_extension_module_orangedata_piot->getProductEans(array_map(function ($p) { return $p['product_id']; }, $products));

        $lines = array();
        foreach ($products as $p) {
            $marking = $this->model_extension_module_orangedata_piot->getProductMarking($p['product_id'], $categoryMap);
            $qty = (int) $p['quantity'];
            // Build an explicit, ordered unit list in PHP (Twig merge would renumber integer keys).
            $units = array();
            for ($u = 0; $u < $qty; $u++) {
                $code = isset($codesByLine[$p['order_product_id']][$u]) ? $codesByLine[$p['order_product_id']][$u] : null;
                $has_gs = $code && strpos($code['km_raw'], "\x1d") !== false;
                $units[] = array('unit_index' => $u, 'code' => $code, 'has_gs' => $has_gs);
            }
            // Each option line (size/colour) must use its OWN variant barcode — never the product-level EAN,
            // which is "last-synced-variant-wins" and would route e.g. size 41 to the size-38 row. Product.ean
            // is used ONLY for lines that have no option at all (genuine single-variant goods). An option line
            // with no cached variant barcode stays empty → "No EAN" badge (run the backfill CLI).
            $eans = array();
            foreach ($lineOptions[$p['order_product_id']]['pov_ids'] as $pov) {
                if (isset($variantEans[$pov])) $eans[] = $variantEans[$pov];
            }
            if (!$eans && !$lineOptions[$p['order_product_id']]['pov_ids'] && isset($productEans[$p['product_id']])) {
                $eans[] = $productEans[$p['product_id']];
            }
            $lines[] = array(
                'order_product_id' => $p['order_product_id'],
                'product_id'       => $p['product_id'],
                'name'             => $p['name'],
                'options'          => $lineOptions[$p['order_product_id']]['text'],
                'eans'             => array_values(array_unique($eans)),
                'quantity'         => $qty,
                'is_marked'        => !empty($marking['is_marked']),
                'group_id'         => (int) $marking['marking_group_id'],
                'units'            => $units,
            );
        }

        $token = $this->session->data['user_token'];
        $data['user_token']     = $token;
        $data['order_id']       = $order_id;
        $data['lines']          = $lines;
        $data['scan_url']       = $this->jsUrl('extension/module/orangedata_piot/scan', 'user_token=' . $token);
        $data['check_url']      = $this->jsUrl('extension/module/orangedata_piot/check', 'user_token=' . $token . '&order_id=' . $order_id);
        $data['fiscalize_url']  = $this->jsUrl('extension/module/orangedata_piot/fiscalize', 'user_token=' . $token . '&order_id=' . $order_id);
        $data['remove_url']     = $this->jsUrl('extension/module/orangedata_piot/removeCode', 'user_token=' . $token);
        $data['verify_url']     = $this->jsUrl('extension/module/orangedata_piot/verifyReceipt', 'user_token=' . $token . '&order_id=' . $order_id);
        $data['has_document']   = false;
        foreach ($lines as $l) { foreach ($l['units'] as $u) { if (!empty($u['code']['document_id'])) $data['has_document'] = true; } }

        $this->response->setOutput($this->load->view('extension/module/orangedata_piot_order', $data));
    }

    /** AJAX: store a scanned code for one physical unit. */
    public function scan() {
        $this->load->language('extension/module/orangedata_piot');
        $this->load->model('extension/module/orangedata_piot');
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            require_once DIR_SYSTEM . 'library/orangedata/OrangeDataClient.php';
            require_once DIR_SYSTEM . 'library/orangedata/MarkingCode.php';
            // Request::clean() HTML-escaped the value — normalize() un-escapes it and restores GS.
            $norm = MarkingCode::normalize(isset($this->request->post['km']) ? $this->request->post['km'] : '', true);
            $km = $norm['code'];
            if ($km === '') {
                $json['error'] = $this->language->get('error_empty_code');
            } else {
                $id = $this->model_extension_module_orangedata_piot->saveScannedCode(array(
                    'order_id'         => (int) $this->request->post['order_id'],
                    'order_product_id' => (int) $this->request->post['order_product_id'],
                    'product_id'       => (int) $this->request->post['product_id'],
                    'unit_index'       => (int) $this->request->post['unit_index'],
                    'km_raw'           => $km,
                    'km_base64'        => OrangeDataClient::encodeCisForCheck($km),
                ));
                $json['success'] = true;
                $json['marking_code_id'] = $id;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** AJAX: run the ТС ПИоТ check for all scanned codes of the order. */
    public function check() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();
        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
                $service = new OrangeDataService($this->registry);
                $result = $service->checkOrder((int) $this->request->get['order_id']);
                $json['success'] = true;
                $json['summary'] = sprintf($this->language->get('text_check_summary'), $result['checked'], $result['allowed'], $result['denied']);
                $json['summary'] .= ' ' . sprintf($this->language->get('text_check_backend_used'), $this->language->get('text_backend_' . $result['backend']));
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** AJAX: build + submit the fiscal receipt for the order. */
    public function fiscalize() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();
        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
                $service = new OrangeDataService($this->registry);
                $result = $service->fiscalizeOrder((int) $this->request->get['order_id']);
                if ($result['success']) {
                    $json['success'] = $result['message'];
                    $json['pending'] = !empty($result['pending']);
                    $json['document_id'] = $result['document_id'];
                    $json['cheque_url'] = $this->chequeUrl($result['document_id']);
                } else {
                    $json['error'] = $result['message'];
                }
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** Builds the receipt-viewer URL, choosing test vs prod host from the configured API base URL. */
    private function chequeUrl($document_id) {
        if (!$document_id) return '';
        $base = (string) $this->config->get('module_orangedata_piot_base_url');
        $host = (strpos($base, 'apip.') !== false || strpos($base, ':12001') !== false) ? 'cheques-test.orangedata.ru' : 'cheques.orangedata.ru';
        return 'https://' . $host . '/' . $this->config->get('module_orangedata_piot_inn') . '/' . rawurlencode($document_id);
    }

    /**
     * verifyReceipt
     *
     * AJAX: reads the order's registered receipt back from OrangeData and compares item codes and tag 1260/1265
     * with the stored check results (OrangeDataService::verifyReceipt). Read-only.
     * Scope: the «Сверить чек» button on the order panel.
     */
    public function verifyReceipt() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();
        if (!$this->user->hasPermission('access', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            try {
                require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
                require_once DIR_SYSTEM . 'library/orangedata/MarkingCode.php';
                $service = new OrangeDataService($this->registry);
                $json = $service->verifyReceipt((int) $this->request->get['order_id']);
                foreach ($json['positions'] ?? array() as $k => $p) {
                    $json['positions'][$k]['item_code'] = MarkingCode::visualize($p['item_code']); // show GS as ⟨GS⟩
                }
                if (!empty($json['document_id'])) $json['cheque_url'] = $this->chequeUrl($json['document_id']);
            } catch (Exception $e) {
                $json = array('error' => $e->getMessage());
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /** AJAX: remove a mis-scanned (not-yet-fiscalized) code. */
    public function removeCode() {
        $this->load->model('extension/module/orangedata_piot');
        $json = array();
        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $this->load->language('extension/module/orangedata_piot');
            $json['error'] = $this->language->get('error_permission');
        } else {
            $this->model_extension_module_orangedata_piot->deleteCode((int) $this->request->post['marking_code_id']);
            $json['success'] = true;
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * scanTest
     *
     * AJAX: normalizes a code scanned into the settings «Проверка сканера» field exactly as scan() would, and
     * reports how it arrived: GS present, GS rebuilt positionally, or missing. Lets the shop verify the real scanner
     * model (and its GS substitute setting) before go-live. Stores nothing. Scope: settings page.
     */
    public function scanTest() {
        $this->load->language('extension/module/orangedata_piot');
        $json = array();

        if (!$this->user->hasPermission('modify', 'extension/module/orangedata_piot')) {
            $json['error'] = $this->language->get('error_permission');
        } else {
            require_once DIR_SYSTEM . 'library/orangedata/MarkingCode.php';
            $norm = MarkingCode::normalize(isset($this->request->post['km']) ? $this->request->post['km'] : '', true);
            if ($norm['code'] === '') {
                $json['error'] = $this->language->get('error_empty_code');
            } else {
                $hasGs = MarkingCode::hasGs($norm['code']);
                $json['code']     = MarkingCode::visualize($norm['code']);
                $json['length']   = strlen($norm['code']);
                $json['has_gs']   = $hasGs;
                $json['restored'] = $norm['restored'];
                $json['verdict']  = !$hasGs ? $this->language->get('text_scan_no_gs')
                    : ($norm['restored'] ? $this->language->get('text_scan_restored') : $this->language->get('text_scan_ok'));
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json, JSON_UNESCAPED_UNICODE));
    }

    /**
     * productMarking
     *
     * AJAX (GET): returns the marked-goods flag + товарная группа for a product, so the injected product
     * form field can show the current value without a core controller edit. Scope: product edit form.
     */
    public function productMarking() {
        $this->load->model('extension/module/orangedata_piot');
        $product_id = isset($this->request->get['product_id']) ? (int) $this->request->get['product_id'] : 0;

        // Return the explicit override MODE for the form select: auto (no row) / yes / no.
        $override = $this->model_extension_module_orangedata_piot->getMarkingOverride($product_id);
        if ($override === null) {
            $out = array('mode' => 'auto', 'marking_group_id' => 0);
        } else {
            $out = array('mode' => $override['is_marked'] ? 'yes' : 'no', 'marking_group_id' => $override['marking_group_id']);
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($out));
    }

    /**
     * productMarkingSave
     *
     * Admin event handler for addProduct/editProduct: persists the marked-goods flag posted from the product
     * form. Scope: registered on admin/model/catalog/product add & edit "after" events.
     */
    public function productMarkingSave(&$route, &$args, &$output) {
        if (strpos($route, 'addProduct') !== false) {
            $product_id = (int) $output;                       // addProduct returns the new id
            $data = isset($args[0]) ? $args[0] : array();
        } else {
            $product_id = isset($args[0]) ? (int) $args[0] : 0; // editProduct($product_id, $data)
            $data = isset($args[1]) ? $args[1] : array();
        }
        if ($product_id) {
            $this->load->model('extension/module/orangedata_piot');
            $mode = isset($data['orangedata_marking_mode']) ? $data['orangedata_marking_mode'] : 'auto';
            $group = isset($data['orangedata_marking_group_id']) ? (int) $data['orangedata_marking_group_id'] : 0;
            if ($mode === 'yes') {
                $this->model_extension_module_orangedata_piot->setProductMarking($product_id, 1, $group);
            } elseif ($mode === 'no') {
                $this->model_extension_module_orangedata_piot->setProductMarking($product_id, 0, 0);
            } else {
                // 'auto' — remove the override so category-based marking applies.
                $this->model_extension_module_orangedata_piot->deleteProductMarking($product_id);
            }
        }
    }

    public function install() {
        $this->load->model('extension/module/orangedata_piot');
        $this->model_extension_module_orangedata_piot->install();

        $this->load->model('setting/event');
        foreach (array('orangedata_piot', 'orangedata_piot_product_add', 'orangedata_piot_product_edit') as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
        $this->model_setting_event->addEvent('orangedata_piot', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/orangedata_piot/fiscalizeEvent');
        $this->model_setting_event->addEvent('orangedata_piot_product_add', 'admin/model/catalog/product/addProduct/after', 'extension/module/orangedata_piot/productMarkingSave');
        $this->model_setting_event->addEvent('orangedata_piot_product_edit', 'admin/model/catalog/product/editProduct/after', 'extension/module/orangedata_piot/productMarkingSave');
    }

    public function uninstall() {
        $this->load->model('setting/event');
        foreach (array('orangedata_piot', 'orangedata_piot_product_add', 'orangedata_piot_product_edit') as $code) {
            $this->model_setting_event->deleteEventByCode($code);
        }
    }
}
