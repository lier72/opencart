<?php
/**
 * Catalog-side controller for the OrangeData ТС ПИоТ module — the order-status event handler.
 *
 * fiscalizeEvent() fires on `catalog/model/checkout/order/addOrderHistory/after`. When auto-fiscalize is
 * enabled and the order reaches the configured trigger status (shipment/packing), it runs the ТС ПИоТ
 * check for the order's scanned codes and then submits the receipt — reusing OrangeDataService so the
 * behaviour is identical to the manual admin panel.
 *
 * Scope: automation only. It never throws (a failure must not break the order-history write); problems are
 * written to orangedata.log. Codes are scanned in admin beforehand; if none/unchecked, fiscalization is
 * skipped with a logged reason. NB: this runs synchronously — for high volume, move to a cron/queue.
 */
class ControllerExtensionModuleOrangedataPiot extends Controller {

    public function fiscalizeEvent(&$route, &$args, &$output) {
        if (!$this->config->get('module_orangedata_piot_status') || !$this->config->get('module_orangedata_piot_auto_fiscalize')) {
            return;
        }

        $order_id        = isset($args[0]) ? (int) $args[0] : 0;
        $order_status_id = isset($args[1]) ? (int) $args[1] : 0;
        $trigger         = (int) $this->config->get('module_orangedata_piot_trigger_status_id');

        if (!$order_id || !$trigger || $order_status_id !== $trigger) {
            return;
        }

        $log = new Log('orangedata.log');
        try {
            require_once DIR_SYSTEM . 'library/orangedata/OrangeDataService.php';
            $service = new OrangeDataService($this->registry);

            $check = $service->checkOrder($order_id);
            $log->write('[event] order ' . $order_id . ' checked: ' . json_encode($check ? array('checked' => $check['checked'], 'allowed' => $check['allowed'], 'denied' => $check['denied']) : null));

            $result = $service->fiscalizeOrder($order_id);
            if ($result['success']) {
                $log->write('[event] order ' . $order_id . ' fiscalize: OK ' . $result['document_id'] . (!empty($result['pending']) ? ' (pending)' : ''));
            } elseif (!empty($result['blocked'])) {
                // Marked order not yet scanned/approved — expected; operator will finish it manually.
                $log->write('[event] order ' . $order_id . ' fiscalize: SKIP (' . $result['message'] . ')');
            } else {
                $log->write('[event] order ' . $order_id . ' fiscalize: FAIL ' . $result['message']);
            }
        } catch (Exception $e) {
            $log->write('[event] order ' . $order_id . ' exception: ' . $e->getMessage());
        }
    }
}
