<?php
// Heading
$_['heading_title']    = 'Bonus Manager';

// Text
$_['text_extension']   = 'Extensions';
$_['text_success']     = 'Success: You have modified bonus manager module!';
$_['text_edit']        = 'Edit Bonus Manager Module';
$_['text_dashboard']   = 'Dashboard';
$_['text_settings']    = 'Settings';
$_['text_enabled']     = 'Enabled';
$_['text_disabled']    = 'Disabled';
$_['text_bonus_setting_added']   = 'Bonus setting added!';
$_['text_bonus_setting_deleted'] = 'Bonus setting deleted!';

// Entry
$_['entry_status']              = 'Module Status';
$_['entry_discount_threshold']  = 'Discount Threshold (%)';
$_['entry_max_usage_percent']   = 'Max Bonus Usage (%)';
$_['entry_expiration_days']     = 'Bonus Expiration (Days)';
$_['entry_excluded_categories'] = 'Excluded Categories';
$_['entry_accrual_status']      = 'Accrual Order Status';
$_['entry_return_deduction_status'] = 'Return Deduction Status';
$_['entry_customer_group']      = 'Customer Group';
$_['entry_category']            = 'Category';
$_['entry_bonus_percent']       = 'Bonus Percent';
$_['entry_order_id']             = 'Order ID';
$_['entry_customer']             = 'Customer';
$_['entry_reward_kind']          = 'Reward Kind';
$_['entry_bonus_type']           = 'Bonus Type';
$_['entry_points_sign']          = 'Points';
$_['entry_date_from']            = 'Date From';
$_['entry_date_to']              = 'Date To';
$_['entry_min_remaining']         = 'Min Remaining';
$_['entry_notification_email']  = 'Email Notifications';
$_['entry_email_awarded_status'] = 'Bonus Awarded Notification';
$_['entry_email_awarded_subject'] = 'Email Subject (Awarded)';
$_['entry_email_awarded_body']   = 'Email Template (Awarded)';
$_['entry_email_spent_status']   = 'Bonus Spent Notification';
$_['entry_email_spent_subject']  = 'Email Subject (Spent)';
$_['entry_email_spent_body']     = 'Email Template (Spent)';
$_['entry_email_expiring_status'] = 'Bonus Expiring Warning';
$_['entry_email_expiring_subject'] = 'Email Subject (Expiring)';
$_['entry_email_expiring_body']  = 'Email Template (Expiring)';
$_['entry_expiration_warning_days'] = 'Warning Days (comma-separated)';
$_['entry_register_widget_heading'] = 'Registration Widget';
$_['entry_register_widget_title'] = 'Widget Heading';
$_['entry_register_widget_description'] = 'Widget Description';
$_['entry_register_widget_button_text'] = 'Button Text';
$_['entry_register_widget_icon'] = 'Icon (Font Awesome)';
$_['entry_register_widget_show_details'] = 'Show Benefit Details';

// Tab
$_['tab_general']               = 'General Settings';
$_['tab_bonus_settings']        = 'Bonus Settings';
$_['tab_notifications']         = 'Notifications';
$_['tab_statistics']            = 'Statistics';

// Column
$_['column_customer_group']     = 'Customer Group';
$_['column_category']           = 'Category';
$_['column_bonus_percent']      = 'Bonus %';
$_['column_action']             = 'Action';
$_['column_order_id']           = 'Order ID';
$_['column_customer']           = 'Customer';
$_['column_points']             = 'Bonuses';
$_['column_remaining']           = 'Remaining';
$_['column_reward_kind']         = 'Kind';
$_['column_bonus_type']          = 'Type';
$_['column_date']               = 'Date';
$_['column_date_expires']         = 'Expires';
$_['column_total_awarded']        = 'Total Awarded';
$_['column_total_remaining']      = 'Total Remaining';
$_['column_last_award_date']      = 'Last Award';
$_['column_loyalty_level']        = 'Loyalty Level';
$_['column_current_loyalty_level'] = 'Current Level';
$_['column_recommended_loyalty_level'] = 'Recommended Level';
$_['column_total_spent']          = 'Spent This Period';
$_['column_required_total_spent'] = 'Required for Current Level';
$_['column_period']               = 'Program Period';

// Help
$_['help_status']               = 'Enable or disable the bonus system';
$_['help_discount_threshold']   = 'Products with discount greater than this percentage will not earn bonuses (default 15%)';
$_['help_max_usage_percent']    = 'Maximum percentage of cart subtotal that can be paid with bonuses (default 30%)';
$_['help_expiration_days']      = 'Number of days after which bonuses expire (0 = never expire, default 365)';
$_['help_excluded_categories']  = 'Products from these categories will not earn bonuses';
$_['help_accrual_status']       = 'Bonuses are awarded when order reaches this status';
$_['help_return_deduction_status'] = 'Bonuses are automatically deducted when return reaches this status (default: Complete)';
$_['help_notification_email']   = 'Configure email notifications for various bonus system events';
$_['help_email_awarded_status'] = 'Send email notification to customer when bonuses are awarded';
$_['help_email_awarded_subject'] = 'Available placeholders: {customer_firstname}, {customer_lastname}, {order_id}, {bonus_amount}, {current_balance}, {store_name}';
$_['help_email_awarded_body']   = 'Available placeholders: {customer_firstname}, {customer_lastname}, {order_id}, {bonus_amount}, {current_balance}, {max_usage_percent}, {store_name}, {date_awarded}, {account_url}, {order_url}, {store_url}. HTML is supported.';
$_['help_email_spent_status']   = 'Send email notification to customer when bonuses are used';
$_['help_email_spent_subject']  = 'Available placeholders: {customer_firstname}, {customer_lastname}, {order_id}, {points_spent}, {current_balance}, {store_name}';
$_['help_email_spent_body']     = 'Available placeholders: {customer_firstname}, {customer_lastname}, {order_id}, {points_spent}, {current_balance}, {store_name}, {date_spent}, {account_url}, {order_url}, {store_url}. HTML is supported.';
$_['help_email_expiring_status'] = 'Send email notification when bonuses are about to expire';
$_['help_email_expiring_subject'] = 'Available placeholders: {customer_firstname}, {customer_lastname}, {expiring_points}, {days_left}, {expiration_date}, {current_balance}, {store_name}';
$_['help_email_expiring_body']   = 'Available placeholders: {customer_firstname}, {customer_lastname}, {expiring_points}, {days_left}, {expiration_date}, {current_balance}, {store_name}, {account_url}, {store_url}. Supports Twig syntax for logic ({% if %}, {% for %}, etc). HTML is supported.';
$_['help_expiration_warning_days'] = 'Send warning emails X days before expiration (e.g., "90,30,7" for warnings at 90, 30, and 7 days before expiration)';
$_['help_register_widget'] = 'Configure the registration widget shown to guests in the cart. Encourages visitors to register and earn bonus points.';
$_['help_register_widget_icon'] = 'Font Awesome icon class (e.g., fa-gift, fa-star, fa-trophy). See <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank">Font Awesome Icons</a>';
$_['help_register_widget_show_details'] = 'Show detailed benefits list (earn %, use %, validity period, etc.)';

// Button
$_['button_add_setting']        = 'Add Setting';
$_['button_save']               = 'Save';
$_['button_cancel']             = 'Cancel';
$_['button_filter']              = 'Filter';
$_['button_apply_downgrade']     = 'Apply';
$_['button_dismiss_downgrade']   = 'Dismiss';

// Statistics
$_['text_total_issued']         = 'Total Issued';
$_['text_total_redeemed']       = 'Total Redeemed';
$_['text_active_bonuses']       = 'Active Bonuses';
$_['text_customers_count']      = 'Customers with Bonuses';

// Registration Widget Defaults
$_['text_register_widget_heading_default'] = 'Join Our Loyalty Program!';
$_['text_register_widget_description_default'] = 'Register now and start earning bonus points with every purchase!';
$_['text_register_button_default'] = 'Register Now';
$_['text_yes'] = 'Yes';
$_['text_no'] = 'No';
$_['text_orders_with_bonuses']  = 'Orders with Bonuses';
$_['text_recent_transactions']  = 'Recent Transactions';
$_['text_filter']               = 'Filter';
$_['text_operations']            = 'Operations';
$_['text_operations_help']       = 'Awards add points, spends/deductions remove points. Remaining shows the unspent balance for each award.';
$_['text_view_all_operations']    = 'View all operations';
$_['text_spent_bonuses']           = 'Spent Bonuses';
$_['text_active_bonus_awards']     = 'Active Bonuses';
$_['text_awarded_clients']         = 'Clients with Awards';
$_['text_awarded_clients_help']    = 'Totals are based on awarded bonus entries. Remaining shows the current unspent balance.';
$_['text_view_all_awarded_clients'] = 'View all awarded clients';
$_['text_pending_loyalty_downgrades'] = 'Pending Loyalty Downgrades';
$_['text_pending_loyalty_downgrades_help'] = 'Customers keep their current loyalty level until a manager applies or dismisses the downgrade review.';
$_['text_view_all_loyalty_reviews'] = 'Review all downgrade requests';
$_['text_no_pending_loyalty_downgrades'] = 'No pending loyalty downgrade reviews.';
$_['text_loyalty_downgrade_applied'] = 'Loyalty downgrade applied.';
$_['text_loyalty_downgrade_dismissed'] = 'Loyalty downgrade dismissed for this period.';
$_['text_confirm_apply_downgrade'] = 'Apply this loyalty downgrade? No customer congratulation email will be sent.';
$_['text_confirm_dismiss_downgrade'] = 'Dismiss this loyalty downgrade for the current program period?';
$_['text_all_categories']       = 'All Categories (Default)';

// Descriptions (for database records)
$_['text_return_deduction']     = 'Return deduction for return #%s';

// Error
$_['error_permission']          = 'Warning: You do not have permission to modify bonus manager module!';
$_['error_customer_group']      = 'Customer group is required!';
$_['error_loyalty_review_not_found'] = 'Pending loyalty downgrade review was not found.';
