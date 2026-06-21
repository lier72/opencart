<?php
/**
 * Yandex Commerce Protocol (YCP) API Controller
 *
 * Implements the merchant-side endpoints that Yandex calls during the checkout flow.
 * All routes are mapped via .htaccess rewrites from /api/v1/... to route=api/ycp/...
 *
 * Endpoints (minimum viable — delivery managed by Yandex):
 *   basketCheck()     POST /api/v1/checkout/basket/check   — validate cart, return product data
 *   warehouses()      GET  /api/v1/warehouses               — return store as shipping origin
 *   checkout()        POST /api/v1/checkout                 — create OpenCart order
 *   placed()          POST /api/v1/checkout/placed          — payment confirmed (deprecated by Yandex)
 *   checkoutCancel()  POST /api/v1/checkout/cancel          — cancel incomplete session
 *   orderCancel()     POST /api/v1/order/cancel             — cancel placed order
 *   order()           GET  /api/v1/order                    — return delivery status history
 *   orderDelivered()  POST /api/v1/order/delivered          — mark order as delivered
 *
 * Authentication: Bearer token compared via hash_equals() against config value ycp_api_token.
 *
 * Session tracking: the OpenCart order comment stores session and Yandex order IDs as:
 *   "YCP|{session_id}|{yandex_order_id}"
 * yandex_order_id is empty until /checkout/placed is called.
 *
 * Price convention: all prices received/sent are in kopecks (integer). 1 RUB = 100 kopecks.
 */
class ControllerApiYcp extends Controller {

    // ─── Private helpers ────────────────────────────────────────────────────

    private $rawBody     = null;
    private $ycpEndpoint = '';

    private function getRawBody() {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }

        if (isset($this->request->server['YCP_RAW_BODY_CACHE'])) {
            $this->rawBody = (string)$this->request->server['YCP_RAW_BODY_CACHE'];
            return $this->rawBody;
        }

        if (isset($_SERVER['YCP_RAW_BODY_CACHE'])) {
            $this->rawBody = (string)$_SERVER['YCP_RAW_BODY_CACHE'];
            return $this->rawBody;
        }

        $this->rawBody = (string)file_get_contents('php://input');

        return $this->rawBody;
    }

    private function decodeJsonBody($raw_body, &$normalized_body = null) {
        $normalized_body = $raw_body;

        $data = json_decode($raw_body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        if (strpos($raw_body, '&') === false) {
            return null;
        }

        $decoded_body = html_entity_decode($raw_body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decoded_body === $raw_body) {
            return null;
        }

        $data = json_decode($decoded_body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $normalized_body = $decoded_body;
            return $data;
        }

        return null;
    }

    /**
     * Appends a timestamped line to DIR_LOGS/ycp.log, keeping only the last 100 entries.
     */
    private function log($message) {
        $file  = DIR_LOGS . 'ycp.log';
        $lines = file_exists($file)
            ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : [];
        $lines[] = date('Y-m-d H:i:s') . ' ' . $message;
        if (count($lines) > 100) {
            $lines = array_slice($lines, -100);
        }
        file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    }

    /**
     * Logs the incoming request (endpoint, HTTP method, URI, payload).
     * For POST bodies the raw JSON is logged; for GET-only endpoints the query params.
     * Call once at the top of each public endpoint after setting $this->ycpEndpoint.
     */
    private function logIncoming() {
        $method = isset($this->request->server['REQUEST_METHOD']) ? $this->request->server['REQUEST_METHOD'] : '';
        $uri    = isset($this->request->server['REQUEST_URI'])    ? $this->request->server['REQUEST_URI']    : '';
        $this->rawBody = $this->getRawBody();
        $payload = $this->rawBody !== ''
            ? $this->rawBody
            : json_encode($this->request->get, JSON_UNESCAPED_UNICODE);
        $this->log("→ [{$this->ycpEndpoint}] {$method} {$uri} {$payload}");
    }

    /**
     * Verifies the Authorization: Bearer header against the stored ycp_api_token config.
     * Sends a JSON 401 and returns false on failure.
     */
    private function authenticate() {
        $token = $this->config->get('ycp_api_token');

        if (empty($token)) {
            $this->sendError(500, 'YCP API token is not configured');
            return false;
        }

        $header = isset($this->request->server['HTTP_AUTHORIZATION'])
            ? $this->request->server['HTTP_AUTHORIZATION'] : '';

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            $this->sendError(401, 'Authorization header missing or malformed');
            return false;
        }

        if (!hash_equals($token, trim($matches[1]))) {
            $this->sendError(401, 'Invalid API token');
            return false;
        }

        return true;
    }

    /**
     * Reads and JSON-decodes the raw request body (uses cached rawBody if already read).
     * Returns the decoded array or sends a 400 error and returns false.
     */
    private function readJson() {
        $this->rawBody = $this->getRawBody();
        if (empty($this->rawBody)) {
            $this->sendError(400, 'Request body is empty');
            return false;
        }
        $normalized_body = $this->rawBody;
        $data = $this->decodeJsonBody($this->rawBody, $normalized_body);
        $this->rawBody = $normalized_body;
        $_SERVER['YCP_RAW_BODY_CACHE'] = $this->rawBody;
        if ($data === null) {
            $this->sendError(400, 'Request body is not valid JSON');
            return false;
        }
        return $data;
    }

    /**
     * Sends a JSON response with the given HTTP status code. Also logs the response.
     */
    private function sendJson($http_code, $data) {
        $texts = [200 => 'OK', 201 => 'Created', 400 => 'Bad Request',
                  401 => 'Unauthorized', 404 => 'Not Found',
                  409 => 'Conflict', 500 => 'Internal Server Error'];
        $text = isset($texts[$http_code]) ? $texts[$http_code] : '';
        $this->log("← [{$this->ycpEndpoint}] {$http_code} " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('HTTP/1.1 ' . $http_code . ' ' . $text);
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sends a YCP-format error: {"error": "message"}.
     */
    private function sendError($http_code, $message) {
        $this->sendJson($http_code, ['error' => $message]);
    }

    /**
     * Sends an empty 200 OK body (used by cancel/delivered endpoints). Also logs.
     */
    private function sendEmpty() {
        $this->log("← [{$this->ycpEndpoint}] 200 (empty)");
        $this->response->addHeader('HTTP/1.1 200 OK');
        $this->response->setOutput('');
    }

    // ─── Endpoints ──────────────────────────────────────────────────────────

    /**
     * POST /api/v1/checkout/basket/check
     *
     * Called by Yandex to validate the cart and retrieve current product data.
     * Returns enriched product info: prices (kopecks), images, URLs, dimensions,
     * size and color characteristics, warehouse availability.
     *
     * When is_health_check = true, Yandex only checks endpoint availability —
     * respond quickly with the same structure but skip heavy DB work.
     *
     * Request fields:
     *   items[].id                        — offer ID from YML feed ("{product_id}" or "{product_id}:{option_value_id}")
     *   items[].quantity                  — requested quantity
     *   offers_id_from_merchant_center    — true: IDs are YML offer IDs; false: product IDs
     *   locality                          — region string (used for warehouse filtering — ignored for MVP)
     *   is_health_check                   — if true, return immediately without DB validation
     */
    public function basketCheck() {
        $this->ycpEndpoint = 'basketCheck';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $input = $this->readJson();
        if ($input === false) return;

        if (empty($input['items']) || !is_array($input['items'])) {
            $this->sendError(400, 'items array is required');
            return;
        }

        // Health check: respond immediately to confirm the endpoint is alive
        if (!empty($input['is_health_check'])) {
            $this->sendJson(200, ['items' => []]);
            return;
        }

        $this->load->model('api/ycp');

        $items = $this->model_api_ycp->validateBasket($input['items']);

        if ($items === false) {
            $this->sendError(500, 'Failed to validate basket');
            return;
        }

        $this->sendJson(200, ['items' => $items]);
    }

    /**
     * GET /api/v1/warehouses
     *
     * Returns the store's physical location as the shipping-origin warehouse.
     * Self-pickup is disabled — Yandex manages all delivery in this version.
     * Store address, title, and phone are pulled from OpenCart config.
     *
     * Supports limit/offset pagination (from query params) but only one warehouse exists.
     */
    public function warehouses() {
        $this->ycpEndpoint = 'warehouses';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $warehouse = [
            'id'          => 'main',
            'title'       => $this->config->get('config_name'),
            'address'     => $this->config->get('config_address'),
            'phone'       => $this->config->get('config_telephone'),
            'description' => '',
            'self_pickup_options' => ['enabled' => false]
        ];

        $this->sendJson(200, [
            'total_count' => 1,
            'warehouses'  => [$warehouse]
        ]);
    }

    /**
     * POST /api/v1/checkout
     *
     * Creates a new OpenCart order from the YCP checkout payload.
     * Stores the session_id in the order comment ("YCP|{session_id}|") for later lookup.
     * Returns order_number = the OpenCart order_id as a string.
     *
     * Mandatory fields: session_id, warehouse_id, items[], customer, delivery.
     * Price validation: if Yandex-sent final_price differs from our price, reject with 409.
     */
    public function checkout() {
        $this->ycpEndpoint = 'checkout';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $input = $this->readJson();
        if ($input === false) return;

        $required = ['session_id', 'items', 'customer', 'delivery'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->sendError(400, 'Missing required field: ' . $field);
                return;
            }
        }

        $this->load->model('api/ycp');

        $result = $this->model_api_ycp->createOrder($input);

        if ($result === false) {
            $this->sendError(500, 'Could not create order');
            return;
        }

        if (isset($result['price_mismatch'])) {
            $this->sendError(409, 'Price mismatch: ' . $result['price_mismatch']);
            return;
        }

        $this->sendJson(200, ['order_number' => (string)$result['order_id']]);
    }

    /**
     * POST /api/v1/checkout/placed (deprecated by Yandex, still called)
     *
     * Called after payment is completed. Finds the OpenCart order by order_number
     * (the value we returned from /checkout), stores the Yandex order_id in the comment,
     * and updates order status based on payment method (online → Processing, on_delivery → Pending).
     *
     * Request: session_id, order_id (Yandex UUID), order_number (our OpenCart order_id),
     *          payment_method ("online" | "on_delivery"), online_payment_method (optional).
     */
    public function placed() {
        $this->ycpEndpoint = 'placed';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $input = $this->readJson();
        if ($input === false) return;

        $order_number    = isset($input['order_number'])    ? $input['order_number']    : '';
        $session_id      = isset($input['session_id'])      ? $input['session_id']      : '';
        $yandex_order_id = isset($input['order_id'])        ? $input['order_id']        : '';
        $payment_method  = isset($input['payment_method'])  ? $input['payment_method']  : '';

        if (empty($order_number) && empty($session_id)) {
            $this->sendError(400, 'order_number or session_id is required');
            return;
        }

        $this->load->model('api/ycp');

        // Find order by order_number (our OpenCart order_id) if provided
        if (!empty($order_number)) {
            $oc_order_id = (int)$order_number;
        } else {
            $oc_order_id = $this->model_api_ycp->findOrderBySession($session_id);
        }

        if (!$oc_order_id) {
            $this->sendError(404, 'Order not found');
            return;
        }

        // Attach Yandex order_id to the comment for future webhook/status lookups
        if (!empty($yandex_order_id)) {
            $this->model_api_ycp->attachYandexOrderId($oc_order_id, $yandex_order_id);
        }

        // Advance order status: online payment → Processing, cash on delivery → stays Pending
        if ($payment_method === 'online') {
            $status_id = (int)($this->config->get('ycp_order_status_confirmed') ?: 2);
            $this->model_api_ycp->addOrderHistory($oc_order_id, $status_id, 'YCP: оплата online подтверждена');
        }

        $this->sendEmpty();
    }

    /**
     * POST /api/v1/checkout/cancel
     *
     * Called by Yandex when a checkout session is abandoned (>1 hour with no placement,
     * or customer returned to cart). Finds the order by session_id and cancels it.
     *
     * Request: query param session_id (UUID).
     */
    public function checkoutCancel() {
        $this->ycpEndpoint = 'checkoutCancel';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $session_id = isset($this->request->get['session_id']) ? $this->request->get['session_id'] : '';

        if (empty($session_id)) {
            $this->sendError(400, 'session_id query parameter is required');
            return;
        }

        $this->load->model('api/ycp');

        $oc_order_id = $this->model_api_ycp->findOrderBySession($session_id);

        if (!$oc_order_id) {
            $this->sendError(404, 'Session not found');
            return;
        }

        $cancel_status = (int)($this->config->get('ycp_order_status_cancelled') ?: 7);
        $this->model_api_ycp->addOrderHistory($oc_order_id, $cancel_status, 'YCP: сессия отменена');

        $this->sendEmpty();
    }

    /**
     * POST /api/v1/order/cancel
     *
     * Called by Yandex to cancel a placed order. Finds the order by Yandex order_id
     * (stored in comment) and updates its status to Cancelled.
     *
     * Request: query param order_id (Yandex UUID).
     */
    public function orderCancel() {
        $this->ycpEndpoint = 'orderCancel';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $yandex_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';

        if (empty($yandex_order_id)) {
            $this->sendError(400, 'order_id query parameter is required');
            return;
        }

        $this->load->model('api/ycp');

        $oc_order_id = $this->model_api_ycp->findOrderByYandexId($yandex_order_id);

        if (!$oc_order_id) {
            $this->sendError(404, 'Order not found');
            return;
        }

        $cancel_status = (int)($this->config->get('ycp_order_status_cancelled') ?: 9);
        $this->model_api_ycp->addOrderHistory($oc_order_id, $cancel_status, 'YCP: заказ отменён Яндексом');

        $this->sendEmpty();
    }

    /**
     * GET /api/v1/order
     *
     * Returns delivery status history and item list for a placed order.
     * Yandex calls this to display the order state to the customer.
     *
     * Request: query param order_id (Yandex UUID).
     * Response: items[], delivery_statuses[], tracking_url (null for now).
     *
     * OpenCart order status → YCP delivery_status mapping:
     *   Pending (1)    → new
     *   Processing (2) → in_progress
     *   Shipped (3)    → in_progress
     *   Complete (5)   → delivered
     *   Cancelled (7)  → cancelled
     */
    public function order() {
        $this->ycpEndpoint = 'order';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $yandex_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';

        if (empty($yandex_order_id)) {
            $this->sendError(400, 'order_id query parameter is required');
            return;
        }

        $this->load->model('api/ycp');

        $oc_order_id = $this->model_api_ycp->findOrderByYandexId($yandex_order_id);

        if (!$oc_order_id) {
            $this->sendError(404, 'Order not found');
            return;
        }

        $data = $this->model_api_ycp->getOrderForYcp($oc_order_id);

        $this->sendJson(200, $data);
    }

    /**
     * POST /api/v1/order/delivered
     *
     * Called by Yandex when delivery is completed. Updates OpenCart order status to Complete.
     * Supports partial fulfillment via purchased_items (we record but don't split the order for MVP).
     *
     * Request: query param order_id (Yandex UUID).
     * Body: {"purchased_items": [{"id": "42:15", "quantity": 2}]}.
     */
    public function orderDelivered() {
        $this->ycpEndpoint = 'orderDelivered';
        $this->logIncoming();
        if (!$this->authenticate()) return;

        $yandex_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : '';

        if (empty($yandex_order_id)) {
            $this->sendError(400, 'order_id query parameter is required');
            return;
        }

        $this->load->model('api/ycp');

        $oc_order_id = $this->model_api_ycp->findOrderByYandexId($yandex_order_id);

        if (!$oc_order_id) {
            $this->sendError(404, 'Order not found');
            return;
        }

        $complete_status = (int)($this->config->get('ycp_order_status_delivered') ?: 5);
        $this->model_api_ycp->addOrderHistory($oc_order_id, $complete_status, 'YCP: доставлен');

        $this->sendEmpty();
    }
}
