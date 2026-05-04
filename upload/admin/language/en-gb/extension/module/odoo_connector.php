<?php
// Heading
$_['heading_title']          = 'Odoo Connector Configuration';

// Text
$_['text_edit']             = 'Edit Odoo Configuration';
$_['text_success']          = 'Success: You have modified Odoo connector configuration!';
$_['text_environment_status'] = 'Environment Status';
$_['text_test_mode']        = 'Test Environment';
$_['text_production_mode']  = 'Production Environment';
$_['text_connection_success'] = 'Connection successfully tested!';
$_['text_order_total_mapping'] = 'Odoo Order Total Mapping';
$_['text_order_total_mapping_list'] = 'Order Total Mappings';
$_['text_order_total_mapping_help'] = 'Map OpenCart order total codes such as shipping, reward, and voucher to Odoo service products. Title include/exclude filters are matched as case-insensitive substrings.';
$_['text_no_results']         = 'No results found';
$_['text_confirm']            = 'Are you sure?';
$_['text_home']               = 'Home';

// Entry
$_['entry_url']             = 'Odoo URL';
$_['entry_database']        = 'Database Name';
$_['entry_username']        = 'Username';
$_['entry_password']        = 'Password';
$_['entry_port']            = 'Port';
$_['entry_debug']            = 'Debug (1 - yes; 0 - no)';
$_['entry_virtual_available_categories'] = 'Virtual Qty Categories';


// Sync batch size
$_['entry_sync_batch_size']      = 'Sync Batch Size';
$_['help_sync_batch_size']       = 'Number of products to sync in each batch (1-100). Higher values may cause timeout issues.';
$_['help_virtual_available_categories'] = 'Products in the selected categories will use Odoo virtual_available instead of qty_available - outgoing_qty during stock sync.';
$_['error_sync_batch_size']      = 'Sync batch size must be between 1 and 100!';

// Columns
$_['column_total_code']       = 'OpenCart Total Code';
$_['column_include_pattern']  = 'Title Include Pattern';
$_['column_exclude_pattern']  = 'Title Exclude Pattern';
$_['column_odoo_product_id']  = 'Odoo Product ID';
$_['column_odoo_product_name'] = 'Odoo Product Name';
$_['column_priority']         = 'Priority';
$_['column_active']           = 'Active';
$_['column_action']           = 'Action';

// Button
$_['button_save']           = 'Save';
$_['button_cancel']         = 'Cancel';
$_['button_test_connection'] = 'Test Connection';
$_['button_add_mapping']      = 'Add Mapping';
$_['button_delete']           = 'Delete';

// Error
$_['error_permission']      = 'Warning: You do not have permission to modify Odoo connector!';
$_['error_url_required']    = 'Odoo URL is required!';
$_['error_db_required']     = 'Database name is required!';
$_['error_user_required']   = 'Username is required!';
$_['error_password_required'] = 'Password is required!';
$_['error_connection_failed'] = 'Connection test failed!';
$_['error_invalid_request'] = 'Invalid request!';
