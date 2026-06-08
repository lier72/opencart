<?php
/**
 * Yandex Commerce Protocol (YCP) Model
 *
 * Handles all data operations for the YCP API controller:
 *
 *   validateBasket()       — product validation + enrichment for /checkout/basket/check
 *   createOrder()          — order creation from YCP checkout payload
 *   findOrderBySession()   — session_id → OpenCart order_id lookup
 *   findOrderByYandexId()  — Yandex order UUID → OpenCart order_id lookup
 *   attachYandexOrderId()  — store Yandex order_id in order comment after /checkout/placed
 *   addOrderHistory()      — thin wrapper for model_checkout_order->addOrderHistory()
 *   getOrderForYcp()       — build GET /order response (items + delivery_statuses)
 *
 * Session tracking comment format: "YCP|{session_id}|{yandex_order_id}"
 * yandex_order_id is empty until /checkout/placed is called.
 *
 * Price convention: all prices in whole RUB (integer, rounded).
 *
 * productId encoding: "{product_id}" or "{product_id}:{option_value_id}"
 * Must match the offer id format in the Yandex Market YML feed.
 */
class ModelApiYcp extends Model {

    // ─── Private helpers ────────────────────────────────────────────────────

    /**
     * Parses a YCP offer ID string into product_id and optional option_value_id.
     * Format: "{product_id}" or "{product_id}:{option_value_id}".
     *
     * Returns ['product_id' => int, 'option_value_id' => int|null].
     */
    private function parseOfferId($id_str) {
        $parts = explode(':', (string)$id_str, 2);
        return [
            'product_id'      => (int)$parts[0],
            'option_value_id' => isset($parts[1]) ? (int)$parts[1] : null
        ];
    }

    /**
     * Converts product weight to grams based on the weight_class unit.
     * Falls back to 500g if weight is 0 or unit is unrecognised.
     *
     * Called when building basket check response dimensions.
     */
    private function convertWeightToGrams($weight, $weight_class_id) {
        if ((float)$weight <= 0) return 500;

        $query = $this->db->query("
            SELECT unit FROM `" . DB_PREFIX . "weight_class_description`
            WHERE weight_class_id = '" . (int)$weight_class_id . "'
            AND language_id = '" . (int)$this->config->get('config_language_id') . "'
            LIMIT 1
        ");

        $unit = $query->num_rows ? strtolower(trim($query->row['unit'])) : 'g';
        $w    = (float)$weight;

        if ($unit === 'kg' || $unit === 'кг') return (int)round($w * 1000);
        if ($unit === 'lb' || $unit === 'lbs') return (int)round($w * 453.592);
        if ($unit === 'oz') return (int)round($w * 28.3495);
        return (int)round($w); // assume grams
    }

    /**
     * Converts a product dimension to millimetres based on the length_class unit.
     * Falls back to the provided $default_mm if value is 0.
     *
     * Called when building basket check response dimensions.
     */
    private function convertDimensionToMm($value, $length_class_id, $default_mm = 100) {
        if ((float)$value <= 0) return $default_mm;

        $query = $this->db->query("
            SELECT unit FROM `" . DB_PREFIX . "length_class_description`
            WHERE length_class_id = '" . (int)$length_class_id . "'
            AND language_id = '" . (int)$this->config->get('config_language_id') . "'
            LIMIT 1
        ");

        $unit = $query->num_rows ? strtolower(trim($query->row['unit'])) : 'mm';
        $v    = (float)$value;

        if ($unit === 'cm' || $unit === 'см') return (int)round($v * 10);
        if ($unit === 'in' || $unit === 'inch' || $unit === '"') return (int)round($v * 25.4);
        if ($unit === 'm')  return (int)round($v * 1000);
        return (int)round($v); // assume mm
    }

    /**
     * Extracts hex color code from attribute values like "Черный (#000000)" → "#000000".
     * Returns empty string if no hex code is present.
     */
    private function extractColorHex($color_text) {
        if (preg_match('/\(#([0-9A-Fa-f]{3,6})\)/u', $color_text, $m)) {
            return '#' . strtoupper($m[1]);
        }
        return '';
    }

    /**
     * Strips hex color code from attribute values like "Черный (#000000)" → "Черный".
     */
    private function stripColorHex($color_text) {
        return trim(preg_replace('/\s*\(#[0-9A-Fa-f]{3,6}\)/u', '', $color_text));
    }

    /**
     * Looks up full option details for a product + option_value_id combination.
     * Used to build option_data for addOrder() and for building size characteristics.
     *
     * Returns array with all IDs, names and type, or null if not found.
     */
    private function getOptionDetails($product_id, $option_value_id) {
        $query = $this->db->query("
            SELECT
                pov.product_option_value_id,
                pov.product_option_id,
                pov.quantity          AS option_quantity,
                pov.subtract          AS option_subtract,
                po.option_id,
                ov.option_value_id,
                ovd.name AS value_name,
                od.name  AS option_name,
                o.type   AS option_type
            FROM `" . DB_PREFIX . "product_option_value` pov
            LEFT JOIN `" . DB_PREFIX . "product_option` po
                ON (pov.product_option_id = po.product_option_id)
            LEFT JOIN `" . DB_PREFIX . "option_value` ov
                ON (pov.option_value_id = ov.option_value_id)
            LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd
                ON (ov.option_value_id = ovd.option_value_id
                    AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
            LEFT JOIN `" . DB_PREFIX . "option` o
                ON (po.option_id = o.option_id)
            LEFT JOIN `" . DB_PREFIX . "option_description` od
                ON (o.option_id = od.option_id
                    AND od.language_id = '" . (int)$this->config->get('config_language_id') . "')
            WHERE pov.option_value_id = '" . (int)$option_value_id . "'
            AND po.product_id = '" . (int)$product_id . "'
            LIMIT 1
        ");

        if (!$query->num_rows) return null;

        return [
            'product_option_id'       => (int)$query->row['product_option_id'],
            'product_option_value_id' => (int)$query->row['product_option_value_id'],
            'option_id'               => (int)$query->row['option_id'],
            'option_value_id'         => (int)$query->row['option_value_id'],
            'name'                    => $query->row['option_name'],
            'value'                   => $query->row['value_name'],
            'type'                    => $query->row['option_type'],
            'quantity'                => (int)$query->row['option_quantity'],
            'subtract'                => (bool)$query->row['option_subtract']
        ];
    }

    /**
     * Returns the available quantity for a specific option value.
     * When the option has subtract=1 (per-variant stock), returns its own quantity.
     * Otherwise falls back to the product-level quantity.
     */
    private function optionAvailableQty($product_id, $option_value_id, $product_quantity) {
        $opt = $this->getOptionDetails($product_id, $option_value_id);
        if ($opt && $opt['subtract']) {
            return max(0, $opt['quantity']);
        }
        return max(0, (int)$product_quantity);
    }

    /**
     * Builds the product fields that are identical across all size variants of an item:
     * name, prices, image URL, product URL, warehouses, dimensions.
     *
     * Does NOT include 'id' or 'characteristics' — those differ per variant.
     */
    private function buildProductBase($product) {
        $regular_price = (int)round((float)$product['price']);
        $final_price   = $product['special']
            ? (int)round((float)$product['special'])
            : $regular_price;

        $img_url = '';
        if (!empty($product['image'])) {
            $img_url = $this->model_tool_image->resize($product['image'], 500, 500);
        }

        return [
            'name'          => $product['name'],
            'regular_price' => $regular_price,
            'final_price'   => $final_price,
            'img'           => $img_url,
            'url'           => html_entity_decode($this->url->link('product/product', 'product_id=' . $product['product_id'])),
            'warehouses'    => [
                ['id' => 'main', 'available_quantity' => max(0, (int)$product['quantity'])]
            ],
            'dimensions'    => [
                'width'  => $this->convertDimensionToMm($product['width'],  $product['length_class_id'], 200),
                'height' => $this->convertDimensionToMm($product['height'], $product['length_class_id'], 100),
                'depth'  => $this->convertDimensionToMm($product['length'], $product['length_class_id'], 100),
                'weight' => $this->convertWeightToGrams($product['weight'], $product['weight_class_id'])
            ]
        ];
    }

    /**
     * Returns option_value_ids of all sibling values in the same option group as
     * $current_option_value_id, using the standard getProductOptions() data.
     *
     * Returns [int, ...] (in sort_order) or [] if the option value is not found.
     */
    private function getSiblingOptionValues($product_id, $current_option_value_id) {
        $options = $this->model_catalog_product->getProductOptions($product_id);

        foreach ($options as $option) {
            $found = false;
            foreach ($option['product_option_value'] as $ov) {
                if ((int)$ov['option_value_id'] === (int)$current_option_value_id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) continue;

            $siblings = [];
            foreach ($option['product_option_value'] as $ov) {
                $ovid = (int)$ov['option_value_id'];
                if ($ovid !== (int)$current_option_value_id && !in_array($ovid, $siblings)) {
                    $siblings[] = $ovid;
                }
            }
            return $siblings;
        }

        return [];
    }

    /**
     * Builds YCP characteristics array for a product (size + color).
     *
     * Size: taken from the option_value name when option_value_id is present.
     *   code resolved via model_extension_feed_yandex_market->getSizeCode() (shared with YML feed).
     *   The same code must be used across all variants so Yandex groups them correctly.
     * Color: taken from the "Цвет" product attribute.
     *   code = COLOR_REF (per Yandex YCP spec).
     *   display_type = "color" when a hex code is found in the attribute value.
     *   display_type = "text" otherwise.
     *
     * Returns array of Characteristic objects ready for the basket check response.
     */
    private function buildCharacteristics($product_id, $option_value_id, $attrs) {
        $characteristics = [];

        // Size characteristic (only when a specific variant was requested)
        if ($option_value_id) {
            $opt = $this->getOptionDetails($product_id, $option_value_id);
            if ($opt && !empty($opt['value'])) {
                $characteristics[] = [
                    'display_type' => 'text',
                    'code'         => $this->model_extension_feed_yandex_market->getSizeCode($opt['name']),
                    'name'         => 'Размер',
                    'properties'   => ['value' => $this->model_extension_feed_yandex_market->formatSize($opt['value'])]
                ];
            }
        }

        // Color characteristic
        if (!empty($attrs['Цвет'])) {
            $raw_color   = $attrs['Цвет'];
            $color_name  = $this->stripColorHex($raw_color);
            $color_hex   = $this->extractColorHex($raw_color);

            if ($color_hex) {
                $characteristics[] = [
                    'display_type' => 'color',
                    'code'         => 'COLOR_REF',
                    'name'         => 'Цвет',
                    'properties'   => ['value' => $color_name, 'hex' => $color_hex]
                ];
            } else {
                $characteristics[] = [
                    'display_type' => 'text',
                    'code'         => 'COLOR_REF',
                    'name'         => 'Цвет',
                    'properties'   => ['value' => $color_name]
                ];
            }
        }

        return $characteristics;
    }

    /**
     * Maps an OpenCart order_status_id to a YCP delivery_status string.
     *
     * Mapping:
     *   1  (Pending)    → new
     *   2  (Processing) → in_progress
     *   3  (Shipped)    → in_progress
     *   5  (Complete)   → delivered
     *   7  (Cancelled)  → cancelled
     *   others          → new (safe default)
     */
    private function mapStatusToYcp($oc_status_id) {
        $map = [
            1 => 'new',
            2 => 'in_progress',
            3 => 'in_progress',
            5 => 'delivered',
            7 => 'cancelled'
        ];
        return isset($map[(int)$oc_status_id]) ? $map[(int)$oc_status_id] : 'new';
    }

    // ─── Public methods ──────────────────────────────────────────────────────

    /**
     * Validates and enriches a list of YCP basket items.
     *
     * Scope: called from ControllerApiYcp::basketCheck().
     *
     * For each item:
     *   - Parses the offer ID into product_id + option_value_id
     *   - Loads product data from catalog (checks status, availability date, store association)
     *   - Returns current prices in whole RUB (regular = base price, final = with special if active)
     *   - Builds size and color characteristics
     *   - Reports warehouse availability (main warehouse only for MVP)
     *   - Returns dimensions converted to mm/g
     *
     * Missing or disabled products are omitted from the response (Yandex shows them as unavailable).
     *
     * $offers_from_mc: true → ids are offer IDs (pass through parseOfferId);
     *                  false → ids are bare product_ids (no option encoding)
     */
    public function validateBasket($items) {
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        $this->load->model('extension/feed/yandex_market');

        $result = [];

        foreach ($items as $item) {
            if (empty($item['id'])) continue;

            $parsed          = $this->parseOfferId($item['id']);
            $product_id      = $parsed['product_id'];
            $option_value_id = $parsed['option_value_id'];

            $product = $this->model_catalog_product->getProduct($product_id);
            if (!$product) continue; // disabled or not found — omit

            $attrs = $this->model_extension_feed_yandex_market->getProductAttributes($product_id);
            $base  = $this->buildProductBase($product);

            $item_data = array_merge($base, [
                'id'              => $item['id'],
                'characteristics' => $this->buildCharacteristics($product_id, $option_value_id, $attrs),
                'warehouses'      => [['id' => 'main', 'available_quantity' => $option_value_id
                    ? $this->optionAvailableQty($product_id, $option_value_id, $product['quantity'])
                    : max(0, (int)$product['quantity'])
                ]],
            ]);

            // Build variations: other option values within the same option group.
            // Color attribute stays the same (same product); only size differs.
            $variations = [];
            if ($option_value_id) {
                foreach ($this->getSiblingOptionValues($product_id, $option_value_id) as $sib_ovid) {
                    $sib_qty = $this->optionAvailableQty($product_id, $sib_ovid, $product['quantity']);
                    if ($sib_qty <= 0) continue;
                    $variations[] = array_merge($base, [
                        'id'              => $product_id . ':' . $sib_ovid,
                        'characteristics' => $this->buildCharacteristics($product_id, $sib_ovid, $attrs),
                        'warehouses'      => [['id' => 'main', 'available_quantity' => $sib_qty]],
                    ]);
                }
            }
            $item_data['variations'] = $variations;

            $result[] = $item_data;
        }

        return $result;
    }

    /**
     * Creates an OpenCart order from a YCP /checkout payload.
     *
     * Scope: called from ControllerApiYcp::checkout().
     *
     * Maps YCP customer/delivery fields to OpenCart order format.
     * Validates that Yandex-supplied final_prices match current prices (returns
     * ['price_mismatch' => 'description'] on any discrepancy).
     * Stores the session_id in the order comment as "YCP|{session_id}|".
     * Sets order status to Pending (config_order_status_id or 1).
     *
     * Returns ['order_id' => int] on success, false on infrastructure failure,
     * ['price_mismatch' => string] on price conflict.
     */
    public function createOrder($checkout_data) {
        $this->load->model('catalog/product');
        $this->load->model('checkout/order');

        $session_id = $checkout_data['session_id'];
        $customer   = $checkout_data['customer'];
        $delivery   = $checkout_data['delivery'];
        $items      = $checkout_data['items'];

        // Split customer full_name into first/last (best-effort)
        $name_parts = explode(' ', trim($customer['full_name']), 2);
        $first_name = $name_parts[0];
        $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

        $email      = isset($customer['email']) ? $customer['email'] : '';
        $phone      = isset($customer['phone']) ? $customer['phone'] : '';

        // Delivery address
        $addr    = isset($delivery['address']) ? $delivery['address'] : [];
        $city    = isset($addr['locality'])    ? $addr['locality']    : '';
        $street  = isset($addr['address'])     ? $addr['address']     : '';
        $apt     = isset($addr['apartment'])   ? $addr['apartment']   : '';
        $address_1 = $street . ($apt ? ', кв. ' . $apt : '');

        // Delivery method label for order
        $service_name = isset($delivery['service_display_name'])
            ? $delivery['service_display_name'] : 'Яндекс Доставка';
        $delivery_method = isset($delivery['delivery_method']) ? $delivery['delivery_method'] : 'courier';

        // Look up Russia country_id
        $country_q  = $this->db->query("SELECT country_id FROM `" . DB_PREFIX . "country` WHERE iso_code_2 = 'RU' LIMIT 1");
        $russia_id  = $country_q->num_rows ? (int)$country_q->row['country_id'] : 176;

        // Look up RUB currency from DB (session currency may not be set server-to-server)
        $cur_q      = $this->db->query("SELECT currency_id, value FROM `" . DB_PREFIX . "currency` WHERE code = 'RUB' LIMIT 1");
        $currency_id    = $cur_q->num_rows ? (int)$cur_q->row['currency_id']  : 0;
        $currency_value = $cur_q->num_rows ? (float)$cur_q->row['value']       : 1.0;

        // Build products and validate prices
        $order_products = [];
        $order_total    = 0.0;

        foreach ($items as $item) {
            if (empty($item['id'])) continue;

            $parsed          = $this->parseOfferId($item['id']);
            $product_id      = $parsed['product_id'];
            $option_value_id = $parsed['option_value_id'];
            $qty             = isset($item['quantity']) ? (int)$item['quantity'] : 1;

            $product = $this->model_catalog_product->getProduct($product_id);
            if (!$product) return false;
            $our_price    = $product['special'] ? (float)$product['special'] : (float)$product['price'];
            $our_rub      = (int)round($our_price);
            $their_rub    = isset($item['final_price']) ? (int)$item['final_price'] : null;

            // Reject if Yandex sends a price that doesn't match ours
            if ($their_rub !== null && $their_rub !== $our_rub) {
                return ['price_mismatch' => 'Product ' . $item['id'] . ': expected ' . $our_rub . ' RUB, got ' . $their_rub];
            }

            $tax   = $this->tax->getTax($our_price, $product['tax_class_id']);
            $total = $our_price * $qty;
            $order_total += $total;

            $option_data = [];
            if ($option_value_id) {
                $opt = $this->getOptionDetails($product_id, $option_value_id);
                if ($opt) $option_data[] = $opt;
            }

            $order_products[] = [
                'product_id' => $product_id,
                'name'       => $product['name'],
                'model'      => $product['model'],
                'option'     => $option_data,
                'download'   => [],
                'quantity'   => $qty,
                'subtract'   => $product['subtract'],
                'price'      => $our_price,
                'total'      => $total,
                'tax'        => $tax,
                'reward'     => 0
            ];
        }

        $order_data = [
            'invoice_prefix' => $this->config->get('config_invoice_prefix'),
            'store_id'       => $this->config->get('config_store_id'),
            'store_name'     => $this->config->get('config_name'),
            'store_url'      => $this->config->get('config_url'),

            'customer_id'       => 0,
            'customer_group_id' => $this->config->get('config_customer_group_id'),
            'firstname'         => $first_name,
            'lastname'          => $last_name,
            'email'             => $email,
            'telephone'         => $phone,
            'custom_field'      => [],

            'payment_firstname'      => $first_name,
            'payment_lastname'       => $last_name,
            'payment_company'        => '',
            'payment_address_1'      => '',
            'payment_address_2'      => '',
            'payment_city'           => $city,
            'payment_postcode'       => '',
            'payment_zone'           => '',
            'payment_zone_id'        => 0,
            'payment_country'        => 'Россия',
            'payment_country_id'     => $russia_id,
            'payment_address_format' => '',
            'payment_custom_field'   => [],
            'payment_method'         => 'Яндекс Пэй',
            'payment_code'           => 'ycp',

            'shipping_firstname'      => $first_name,
            'shipping_lastname'       => $last_name,
            'shipping_company'        => '',
            'shipping_address_1'      => $address_1,
            'shipping_address_2'      => '',
            'shipping_city'           => $city,
            'shipping_postcode'       => '',
            'shipping_zone'           => '',
            'shipping_zone_id'        => 0,
            'shipping_country'        => 'Россия',
            'shipping_country_id'     => $russia_id,
            'shipping_address_format' => '',
            'shipping_custom_field'   => [],
            'shipping_method'         => $service_name,
            'shipping_code'           => 'ycp.' . $delivery_method,

            'products' => $order_products,
            'vouchers' => [],
            'totals'   => [
                ['code' => 'sub_total', 'title' => 'Subtotal', 'value' => $order_total, 'sort_order' => 1],
                ['code' => 'total',     'title' => 'Total',    'value' => $order_total, 'sort_order' => 9]
            ],
            'total' => $order_total,

            // Comment stores session_id for later lookup; yandex_order_id added in placed()
            'comment'         => 'YCP|' . $session_id . '|',
            'affiliate_id'    => 0,
            'commission'      => 0,
            'marketing_id'    => 0,
            'tracking'        => '',
            'language_id'     => $this->config->get('config_language_id'),
            'currency_id'     => $currency_id,
            'currency_code'   => 'RUB',
            'currency_value'  => $currency_value,
            'ip'              => $this->request->server['REMOTE_ADDR'],
            'forwarded_ip'    => isset($this->request->server['HTTP_X_FORWARDED_FOR']) ? $this->request->server['HTTP_X_FORWARDED_FOR'] : '',
            'user_agent'      => isset($this->request->server['HTTP_USER_AGENT'])       ? $this->request->server['HTTP_USER_AGENT']       : '',
            'accept_language' => isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])  ? $this->request->server['HTTP_ACCEPT_LANGUAGE']  : ''
        ];

        $order_id = $this->model_checkout_order->addOrder($order_data);
        if (!$order_id) return false;

        $pending_status = (int)$this->config->get('config_order_status_id') ?: 1;
        $this->model_checkout_order->addOrderHistory($order_id, $pending_status);

        return ['order_id' => $order_id];
    }

    /**
     * Finds the OpenCart order_id for a given YCP session_id.
     *
     * Scope: called from checkoutCancel() and placed() (when order_number is absent).
     * Searches the order comment for the prefix "YCP|{session_id}|".
     *
     * Returns int order_id or 0 if not found.
     */
    public function findOrderBySession($session_id) {
        $q = $this->db->query("
            SELECT order_id FROM `" . DB_PREFIX . "order`
            WHERE comment LIKE 'YCP|" . $this->db->escape($session_id) . "|%'
            ORDER BY order_id DESC LIMIT 1
        ");
        return $q->num_rows ? (int)$q->row['order_id'] : 0;
    }

    /**
     * Finds the OpenCart order_id for a given Yandex order_id (UUID).
     *
     * Scope: called from orderCancel(), order(), and orderDelivered().
     * The Yandex order_id is appended to the comment in attachYandexOrderId()
     * after /checkout/placed is called.
     *
     * Returns int order_id or 0 if not found.
     */
    public function findOrderByYandexId($yandex_order_id) {
        $escaped = $this->db->escape($yandex_order_id);
        $q = $this->db->query("
            SELECT order_id FROM `" . DB_PREFIX . "order`
            WHERE comment LIKE 'YCP|" . $escaped . "|%'
               OR comment LIKE 'YCP|%|" . $escaped . "'
            ORDER BY order_id DESC LIMIT 1
        ");
        return $q->num_rows ? (int)$q->row['order_id'] : 0;
    }

    /**
     * Updates the order comment to include the Yandex order_id.
     *
     * Scope: called from ControllerApiYcp::placed() after Yandex confirms placement.
     * Comment changes from "YCP|{session_id}|" to "YCP|{session_id}|{yandex_order_id}".
     * Only updates if the comment already starts with "YCP|" to avoid corrupting non-YCP orders.
     */
    public function attachYandexOrderId($oc_order_id, $yandex_order_id) {
        // Fetch current comment
        $q = $this->db->query("SELECT comment FROM `" . DB_PREFIX . "order` WHERE order_id = '" . (int)$oc_order_id . "' LIMIT 1");
        if (!$q->num_rows) return;

        $comment = $q->row['comment'];
        if (strpos($comment, 'YCP|') !== 0) return;

        // Replace the trailing empty yandex_order_id slot
        $new_comment = preg_replace('/\|$/', '|' . $yandex_order_id, $comment);

        $this->db->query("
            UPDATE `" . DB_PREFIX . "order`
            SET comment = '" . $this->db->escape($new_comment) . "'
            WHERE order_id = '" . (int)$oc_order_id . "'
        ");
    }

    /**
     * Thin wrapper around model_checkout_order->addOrderHistory().
     *
     * Scope: used by controller methods to update order status.
     * Loads the checkout/order model if not already loaded.
     */
    public function addOrderHistory($order_id, $status_id, $comment = '') {
        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment);
    }

    /**
     * Builds the GET /api/v1/order response for a given OpenCart order.
     *
     * Scope: called from ControllerApiYcp::order().
     *
     * Returns:
     *   items[]:
     *     id             — YCP offer id (reconstructed from order product + option)
     *     quantity       — ordered quantity
     *     refused_count  — always 0 for MVP (partial cancellation not tracked)
     *   delivery_statuses[]:
     *     status         — YCP status mapped from OpenCart order_status_id
     *     timestamp      — UNIX timestamp of the status history entry
     *   tracking_url     — null (Yandex manages tracking for now)
     */
    public function getOrderForYcp($oc_order_id) {
        $this->load->model('checkout/order');

        // Build items list from order_product + order_option
        $products_q = $this->db->query("
            SELECT op.order_product_id, op.product_id, op.quantity,
                   oo.option_value_id
            FROM `" . DB_PREFIX . "order_product` op
            LEFT JOIN `" . DB_PREFIX . "order_option` oo
                ON (oo.order_id = op.order_id AND oo.order_product_id = op.order_product_id)
            WHERE op.order_id = '" . (int)$oc_order_id . "'
        ");

        $items = [];
        foreach ($products_q->rows as $row) {
            $offer_id = (string)$row['product_id'];
            if (!empty($row['option_value_id'])) {
                $offer_id .= ':' . $row['option_value_id'];
            }
            $items[] = [
                'id'            => $offer_id,
                'quantity'      => (int)$row['quantity'],
                'refused_count' => 0
            ];
        }

        // Build delivery_statuses from order_history
        $history_q = $this->db->query("
            SELECT order_status_id, date_added
            FROM `" . DB_PREFIX . "order_history`
            WHERE order_id = '" . (int)$oc_order_id . "'
            ORDER BY date_added ASC
        ");

        $statuses = [];
        foreach ($history_q->rows as $row) {
            $statuses[] = [
                'status'    => $this->mapStatusToYcp($row['order_status_id']),
                'timestamp' => strtotime($row['date_added'])
            ];
        }

        // Guarantee at least one "new" status (YCP requires this)
        if (empty($statuses)) {
            $statuses[] = ['status' => 'new', 'timestamp' => time()];
        }

        return [
            'items'             => $items,
            'delivery_statuses' => $statuses,
            'tracking_url'      => null
        ];
    }
}
