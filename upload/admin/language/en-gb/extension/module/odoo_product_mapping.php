<?php
/**
 * Created by PhpStorm.
 * User: max
 * Date: 09/12/24
 * Time: 17:53
 */

// Heading
$_['heading_title']         = 'Odoo Product Mapping';

// Text
$_['text_list']            = 'Product Mapping List';
$_['text_success']         = 'Success: You have modified Odoo product mapping!';
$_['text_sync_success']    = 'Success: Product has been synchronized with Odoo!';
$_['text_no_results']      = 'No results found for product mapping.';
$_['text_confirm']         = 'Are you sure?';
$_['text_not_synced']      = 'Not Synced';
$_['text_synced']          = 'Synced';
$_['text_pending']         = 'Pending';
$_['text_error']           = 'Error';
$_['text_unknown']         = 'Unknown';
$_['text_never']           = 'Never';
$_['text_not_mapped']      = 'Not Mapped';
$_['text_sync_progress']   = 'Sync progress';
$_['text_product_synced']  = 'Synced %s';
$_['text_product_failed']  = 'Synced failed %s';
$_['text_product_error']   = 'Error %s';
$_['text_sync_history']    = 'Product Sync History';
$_['text_direction_to_odoo'] = 'To Odoo';
$_['text_direction_from_odoo'] = 'From Odoo';
$_['text_status_success'] = 'Success';
$_['text_status_error'] = 'Error';
$_['text_status_warning'] = 'Warning';
$_['tab_products'] = 'Product Mapping';
$_['tab_vendors'] = 'Vendor / Brand Mapping';
$_['text_vendor_mapping_help'] = 'Fetch product brands from Odoo once, then map each brand to an OpenCart manufacturer. The downloaded list is cached by OpenCart and the button refreshes it from Odoo.';
$_['text_no_vendor_mappings'] = 'No vendor mappings found.';
$_['text_fetch_odoo_vendors_first'] = 'Fetch brands from Odoo to select a brand';
$_['text_select_odoo_vendor'] = 'Select an Odoo brand';
$_['text_fetching_odoo_vendors'] = 'Loading brands from Odoo...';
$_['text_odoo_vendors_loaded'] = 'Odoo brands available: ';
$_['text_no_unmapped_odoo_vendors'] = 'All Odoo brands are already mapped.';
$_['text_no_odoo_vendors'] = 'No product brands were found in Odoo.';


// Column
$_['column_product_id']    = 'OC product ID';
$_['column_product']       = 'Product';
$_['column_model']         = 'Model';
$_['column_variant']       = 'Variant';
$_['column_price']         = 'Price';
$_['column_odoo_ref']      = 'Odoo Reference';
$_['column_sync_status']   = 'Sync Status';
$_['column_last_sync']     = 'Last Sync';
$_['column_action']        = 'Action';
$_['column_date']          = 'Date';
$_['column_direction']     = 'Direction';
$_['column_message']       = 'Sync Message';
$_['column_odoo_vendor_id'] = 'Odoo Brand ID';
$_['column_odoo_vendor_name'] = 'Odoo Brand / Vendor';
$_['column_manufacturer'] = 'OpenCart Manufacturer';
$_['column_active'] = 'Active';

// Entry
$_['entry_product_id']        = 'Product ID';
$_['entry_product']        = 'Product Name';
$_['entry_model']          = 'Model';
$_['entry_odoo_ref']       = 'Odoo Reference';
$_['entry_sync_status']    = 'Sync Status';

// Button
$_['button_filter']        = 'Filter';
$_['button_sync']          = 'Sync';
$_['button_mass_sync']     = 'Sync Selected';
$_['button_history']       = 'Sync history';
$_['button_back']          =  'Back';
$_['button_stock']         = "Update Stock";
$_['button_save']           = 'Save';
$_['button_delete']         = 'Delete';
$_['button_add_mapping']    = 'Add Mapping';
$_['button_fetch_odoo_vendors'] = 'Fetch Brands from Odoo';
$_['button_refresh_odoo_vendors'] = 'Refresh Brands from Odoo';

// Error
$_['error_permission']     = 'Warning: You do not have permission to modify Odoo product mapping!';
$_['error_sync']           = 'Warning: Failed to synchronize with Odoo!';
$_['error_connection']     = 'Warning: Could not connect to Odoo server!';
$_['error_product']        = 'Warning: Product does not exist in Odoo!';
$_['error_invalid_request'] = 'Invalid request method.';

// Date Format
$_['date_format_short']    = 'd/m/Y';
