<?php
/**
 * OrangeDataReceiptBuilder
 *
 * Assembles an FFD 1.2 receipt document (the payload for OrangeDataClient::createDocument) from plain
 * order data, applying the marked-goods rules that were empirically validated against the OrangeData
 * contour (see cli/orangedata_receipt_test.php):
 *   - A position that carries a marking code MUST use paymentSubjectType 33 (ТМ — маркированный товар)
 *     [or 31 for АТМ]; the API rejects 1 (Товар) for such positions.
 *   - A marked position carries itemCode (tag 1163, RAW code with GS \x1d), plannedStatus and
 *     quantityMeasurementUnit, and — under разрешительный режим — industryAttribute (tag 1260 with
 *     tag 1265) built from the ТС ПИоТ check result.
 *   - In аварийный/пост-аварийный режим (check statusCode 203) the sale is allowed but industryAttribute
 *     (tag 1260) MUST NOT be attached (CRPT recommendation §3.1 / OrangeData Nebula).
 *
 * Scope: called by the admin order fiscalization flow / order-status event to turn an order + its stored
 * marking codes (ocus_order_marking_code) into the document passed to OrangeDataClient::createDocument().
 * It is pure (no I/O, no OpenCart registry) so it can be unit-tested. Business gating (only fiscalize when
 * every code isSaleAllowed) stays in the caller; this class only shapes the payload.
 */
class OrangeDataReceiptBuilder {

    /** @var array Defaults applied to every document/position unless overridden per call. */
    private $defaults;

    /**
     * @param array $defaults {
     *   @type int $taxation_system   tag 1055 (0 ОСН, 1 УСН доход, 2 УСН д-р, 5 патент; АУСН per client config).
     *   @type int $ffd_version       tag 1209 (4 = ФФД 1.2). Default 4.
     *   @type int $tax               Default НДС rate tag (6 = НДС не облагается). Default 6.
     *   @type int $payment_method_type Default признак способа расчёта (4 = полный расчёт). Default 4.
     *   @type int $marked_subject_type Payment subject for marked positions (33 ТМ / 31 АТМ). Default 33.
     *   @type int $planned_status    tag 2003 for marked positions (1 = штучный реализован). Default 1.
     *   @type int $quantity_measurement_unit tag 2108 (0 = штука). Default 0.
     * }
     */
    public function __construct(array $defaults = array()) {
        $this->defaults = array_merge(array(
            'taxation_system'          => 1,
            'ffd_version'              => 4,
            'tax'                      => 6,
            'payment_method_type'      => 4,   // полный расчёт
            'payment_type'             => 2,   // checkClose payment: 2 безнал, 14 зачёт аванса (prepaid)
            'marked_subject_type'      => 33,
            'planned_status'           => 1,
            'quantity_measurement_unit' => 0,
        ), $defaults);
    }

    /**
     * position
     *
     * Builds a single предмет расчёта (receipt position), applying marked-goods rules.
     *
     * Scope: one call per receipt line. For a marked line pass 'item_code' (raw KM) and, when a ТС ПИоТ
     * check produced one, 'tag1265'. Pass 'emergency' => true when the check returned statusCode 203 so
     * the industry attribute is deliberately omitted.
     *
     * @param array $line {
     *   @type string $text        Position name (required).
     *   @type float  $price       Unit price (required).
     *   @type float  $quantity    Quantity (default 1).
     *   @type int    $tax         НДС rate tag (default from defaults).
     *   @type bool   $marked      Whether this is a marked good (default: inferred from item_code).
     *   @type string $item_code   Raw marking code with GS \x1d (required if marked).
     *   @type string $tag1265     tag 1265 value from the check (attach industryAttribute if present).
     *   @type bool   $emergency   True when check statusCode == 203 (omit industryAttribute).
     *   @type int    $payment_method_type  Override признак способа расчёта.
     *   @type int    $payment_subject_type Override признак предмета расчёта.
     * }
     * @return array The position structure.
     */
    public function position(array $line) {
        if (empty($line['text']) || !isset($line['price'])) {
            throw new InvalidArgumentException('OrangeDataReceiptBuilder::position requires text and price.');
        }

        $marked = isset($line['marked']) ? (bool) $line['marked'] : !empty($line['item_code']);

        $text = trim((string) $line['text']);
        if (function_exists('mb_substr')) { $text = mb_substr($text, 0, 128, 'UTF-8'); } else { $text = substr($text, 0, 128); }

        $position = array(
            'quantity'          => isset($line['quantity']) ? (float) $line['quantity'] : 1.0,
            'price'             => round((float) $line['price'], 2),
            'tax'               => isset($line['tax']) ? (int) $line['tax'] : (int) $this->defaults['tax'],
            'text'              => $text,
            'paymentMethodType' => isset($line['payment_method_type']) ? (int) $line['payment_method_type'] : (int) $this->defaults['payment_method_type'],
        );

        if ($marked) {
            if (empty($line['item_code'])) {
                throw new InvalidArgumentException('OrangeDataReceiptBuilder::position marked line requires item_code.');
            }
            $position['paymentSubjectType']      = isset($line['payment_subject_type']) ? (int) $line['payment_subject_type'] : (int) $this->defaults['marked_subject_type'];
            $position['itemCode']                = (string) $line['item_code'];
            $position['plannedStatus']           = (int) $this->defaults['planned_status'];
            $position['quantityMeasurementUnit'] = (int) $this->defaults['quantity_measurement_unit'];

            // Attach отраслевой реквизит 1260 only when a check token exists and we are NOT in аварийный режим.
            if (!empty($line['tag1265']) && empty($line['emergency'])) {
                $position['industryAttribute'] = OrangeDataClient::buildIndustryAttribute($line['tag1265']);
            }
        } else {
            $position['paymentSubjectType'] = isset($line['payment_subject_type']) ? (int) $line['payment_subject_type'] : 1; // Товар
        }

        return $position;
    }

    /**
     * document
     *
     * Assembles the full document payload for OrangeDataClient::createDocument().
     *
     * Scope: called once per receipt after all positions are prepared. The caller supplies a STABLE,
     * idempotent id (e.g. order id + leg) so retries collapse to 409 instead of issuing duplicate receipts.
     *
     * @param array $args {
     *   @type string $id                Stable document id (required).
     *   @type array  $positions         Array of line specs for position(), or ready position arrays.
     *   @type array  $payments          Array of ['type'=>int,'amount'=>float] (default: single безнал for the total).
     *   @type string $customer_contact  Email/phone/"none" (default "none").
     *   @type int    $type              Признак расчёта tag 1054 (default 1 = приход).
     *   @type int    $taxation_system   Override tag 1055.
     *   @type bool   $ignore_item_code_check  Skip ФН code check (test only; default false).
     * }
     * @return array Document ready for createDocument().
     */
    public function document(array $args) {
        if (empty($args['id']) || empty($args['positions'])) {
            throw new InvalidArgumentException('OrangeDataReceiptBuilder::document requires id and positions.');
        }

        $positions = array();
        $total = 0.0;
        foreach ($args['positions'] as $p) {
            // Accept either a ready position (has paymentSubjectType) or a line spec to build.
            $pos = isset($p['paymentSubjectType']) ? $p : $this->position($p);
            $positions[] = $pos;
            $total += $pos['price'] * $pos['quantity'];
        }

        $payType = isset($args['payment_type']) ? (int) $args['payment_type'] : (int) $this->defaults['payment_type'];
        $payments = isset($args['payments']) && $args['payments']
            ? $args['payments']
            : array(array('type' => $payType, 'amount' => round($total, 2)));

        $document = array(
            'id'      => (string) $args['id'],
            'content' => array(
                'type'            => isset($args['type']) ? (int) $args['type'] : 1,
                'positions'       => $positions,
                'checkClose'      => array(
                    'payments'       => $payments,
                    'taxationSystem' => isset($args['taxation_system']) ? (int) $args['taxation_system'] : (int) $this->defaults['taxation_system'],
                ),
                'customerContact' => isset($args['customer_contact']) && $args['customer_contact'] !== '' ? (string) $args['customer_contact'] : 'none',
                'ffdVersion'      => (int) $this->defaults['ffd_version'],
            ),
            'ignoreItemCodeCheck' => !empty($args['ignore_item_code_check']),
        );

        return $document;
    }
}
