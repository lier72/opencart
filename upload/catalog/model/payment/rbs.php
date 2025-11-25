<?php
class ModelPaymentRbs extends Model {
    public function getMethod($address, $total) {
        $this->load->language('payment/rbs');

        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
        } else {
            $customer_group_id = $this->config->get('config_customer_group_id');
        }

        // Check if customer group restriction is enabled
        $allowed_customer_groups = $this->config->get('payment_rbs_customer_group');

        // If customer groups are configured, check if current customer group is allowed
        if (!empty($allowed_customer_groups) && is_array($allowed_customer_groups)) {
            // Payment method is restricted to selected groups
            $status = in_array($customer_group_id, $allowed_customer_groups);
        } else {
            // No restriction - available for all groups
            $status = true;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code'       => 'rbs',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('custom_sort_order')
            );
        }

        return $method_data;
    }
}