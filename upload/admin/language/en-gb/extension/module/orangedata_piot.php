<?php
// Heading
$_['heading_title']                 = 'OrangeData ТС ПИоТ (Permissive mode)';

// Text
$_['text_extension']                = 'Extensions';
$_['text_success']                  = 'Settings saved!';
$_['text_edit']                     = 'OrangeData ТС ПИоТ settings';
$_['text_enabled']                  = 'Enabled';
$_['text_disabled']                 = 'Disabled';
$_['text_yes']                      = 'Yes';
$_['text_no']                       = 'No';
$_['text_none']                     = '--- None ---';

$_['text_group_general']            = 'General';
$_['text_group_certs']              = 'Certificates & signing key';
$_['text_group_pmsr']               = 'ПМСР (cash software identity)';
$_['text_group_receipt']            = 'Receipt parameters';
$_['text_group_automation']         = 'Automation';

$_['text_tax_osn']                  = 'OSN — general';
$_['text_tax_usn_income']           = 'USN income (incl. AUSN income)';
$_['text_tax_usn_income_outcome']   = 'USN income-outcome (incl. AUSN income-outcome)';
$_['text_tax_patent']               = 'Patent';

$_['text_subject_tm']               = '33 — TM (marked goods)';
$_['text_subject_atm']              = '31 — ATM (excisable marked)';

$_['text_test_ok']                  = 'Connection OK (HTTP %s). Signature and mTLS accepted.';
$_['text_test_fail']                = 'Error: %s';
$_['text_test_http']                = 'HTTP %s response: %s';

// Entry
$_['entry_status']                  = 'Status';
$_['entry_base_url']                = 'API URL';
$_['entry_inn']                     = 'INN';
$_['entry_key']                     = 'Key';
$_['entry_group']                   = 'Device group';
$_['entry_verify_peer']             = 'Verify server certificate';
$_['entry_ssl_cert_path']           = 'SSL certificate path (client.crt)';
$_['entry_ssl_key_path']            = 'SSL key path (client.key)';
$_['entry_ssl_key_pass']            = 'SSL key password';
$_['entry_sign_key_path']           = 'Signing key path (PEM or XML)';
$_['entry_client_name']             = 'ПМСР name';
$_['entry_client_version']          = 'ПМСР version';
$_['entry_client_id']               = 'ПМСР id';
$_['entry_client_token']            = 'ПМСР checksum/token';
$_['entry_taxation_system']         = 'Taxation system';
$_['entry_tax']                     = 'VAT rate (tag)';
$_['entry_marked_subject_type']     = 'Payment subject type (marked)';
$_['entry_marked_categories']       = 'Marked categories';
$_['help_marked_categories']        = 'Start typing a category name, pick it, then choose the товарная группа. All products in the selected categories and their subcategories are treated as marked. A product can override this (Yes/No/Auto) on its form.';
$_['text_add_category']             = 'Add a category…';
$_['column_category']               = 'Category';
$_['column_group']                  = 'Product group';
$_['entry_payment_type']            = 'Payment type for prepaid (card)';
$_['text_pay_prepaid']              = '14 — Prepayment offset (bank already issued the advance receipt)';
$_['text_pay_cashless']             = '2 — Cashless';
$_['text_pay_cash']                 = '1 — Cash';
$_['help_payment_type']             = 'For card orders paid via the bank (Alfa/RBS issued a "Prepayment 100%" receipt) our shipment receipt is "Prepayment offset" (14), otherwise revenue is double-counted.';
$_['entry_payment_type_map']        = 'Payment method → payment type map';
$_['help_payment_type_map']         = 'One line per method as payment_code:type. Type: 14 — prepayment offset (card already fiscalized by bank), 2 — cashless, 1 — cash. E.g. rbs:14, bank_transfer:2, cod:1. Unlisted methods use the prepaid default.';
$_['text_no_gs']                    = 'No GS separator in the code — the FN may reject the receipt';
$_['entry_trigger_status']          = 'Order status to check/fiscalize';
$_['entry_auto_fiscalize']          = 'Auto-fiscalize on status';
$_['entry_ignore_item_code']        = 'Skip FN code check (test)';

// Help
$_['help_base_url']                 = 'Test: https://apip.orangedata.ru:12001/api/v2/ · Prod: https://api.orangedata.ru:12003/api/v2/';
$_['help_key']                      = 'Usually the INN; for new clients INN_ID.';
$_['help_verify_peer']             = 'Usually "No" on the test contour.';
$_['help_sign_key_path']            = 'Test key private_key_test.xml (.NET XML) is auto-converted to PEM. Production key from ЛК is PEM.';
$_['help_client_info']              = 'All 4 ПМСР fields are required for the ТС ПИоТ method. If the token is empty, an MD5 checksum of the module code is used.';
$_['help_taxation_ausn']            = 'No dedicated AUSN value: income → "USN income", income-outcome → "USN income-outcome".';
$_['help_tax']                      = '6 — VAT exempt (USN/AUSN). 1 — 20/22%, 2 — 10%, etc.';
$_['help_auto_fiscalize']           = 'If enabled, the check + fiscalization run automatically when the order reaches the selected status.';
$_['help_ignore_item_code']         = 'Test only: the FN will not validate the marking code authenticity. Use "No" in production.';

// Buttons
$_['button_test']                   = 'Test connection';

// Errors
$_['error_permission']              = 'You do not have permission to modify this module!';
$_['error_empty_code']              = 'Empty marking code.';
$_['text_check_summary']            = 'Checked: %s, allowed: %s, denied: %s.';
$_['text_check_backend_used']       = 'Check backend: %s.';

// Marking-code check backend
$_['text_group_check']              = 'Marking-code check backend';
$_['entry_check_backend']           = 'Check backend';
$_['text_backend_trueapi']          = 'Честный знак True API (X-API-KEY)';
$_['text_backend_orangedata']       = 'OrangeData ТС ПИоТ';
$_['help_check_backend']            = 'One backend is used, no automatic switching. Receipts always go through OrangeData. ЦРПТ switches X-API-KEY checks off in stages from 01.10.2026 — on a 401/410 switch the backend.';
$_['entry_trueapi_env']             = 'True API contour';
$_['text_trueapi_prod']             = 'Production (cdn.crpt.ru)';
$_['text_trueapi_sandbox']          = 'Sandbox (markirovka.sandbox.crptech.ru)';
$_['entry_trueapi_key']             = 'X-API-KEY';
$_['help_trueapi_key']              = 'Permissive-mode key from the ГИС МТ cabinet (UUID format, the "token for KKT"). The "API key for INN" does not work — 401.';
$_['entry_trueapi_fn']              = 'Fiscal drive number (optional)';
$_['help_trueapi_fn']               = 'Sent as fiscalDriveNumber when set.';
$_['entry_trueapi_proxy']           = 'Proxy (optional)';
$_['help_trueapi_proxy']            = 'Only if the shop server cannot reach ЦРПТ hosts directly, e.g. socks5h://127.0.0.1:1080.';
$_['button_test_trueapi']           = 'Test True API';
$_['text_trueapi_ok']               = 'Key accepted, check host: %s';
$_['text_trueapi_key_fail']         = 'True API rejected the X-API-KEY (401/403). Check the key.';
$_['text_trueapi_gone']             = 'True API: X-API-KEY checks are switched off (410). Switch the check backend.';
$_['text_trueapi_no_host']          = 'True API: no CDN host available (cdn/info HTTP %s): %s';
$_['text_via_trueapi']              = 'via True API';
$_['text_offline']                  = 'offline';

// Order panel
$_['text_panel_title']              = 'ТС ПИоТ / Permissive mode';
$_['text_no_lines']                 = 'No products in this order.';
$_['text_marked']                   = 'Marked';
$_['text_not_marked']               = 'Not marked';
$_['text_scan_placeholder']         = 'Scan DataMatrix…';
$_['text_allowed']                  = 'Allowed';
$_['text_not_checked']              = 'Not checked';
$_['text_denied']                   = 'Denied';
$_['text_scanned']                  = 'Scanned';
$_['text_fiscalized']               = 'Fiscalized';
$_['column_product']                = 'Product';
$_['column_unit']                   = 'Unit';
$_['column_code']                   = 'Marking code';
$_['column_status']                 = 'Status';
$_['button_check']                  = 'Check via ТС ПИоТ';
$_['button_fiscalize']              = 'Fiscalize receipt';

// EAN-first scan routing
$_['entry_scan_ean']                = 'Scan product (EAN)';
$_['text_scan_ean_placeholder']     = 'Scan the product barcode (EAN)…';
$_['help_scan_ean']                 = 'Scan the product barcode (EAN) first — the form highlights the matching line, then scan its DataMatrix. For several identical units, scan the EAN once, then all DataMatrix codes in a row.';
$_['text_ean_not_found']            = 'This EAN is not in the order.';
$_['text_all_units_scanned']        = 'All units of this product are already scanned.';
$_['text_scan_ean_first']           = 'That looks like a DataMatrix. Scan the product barcode (EAN) first.';
$_['text_gtin_mismatch']            = 'Warning: the DataMatrix GTIN does not match the EAN of the selected line — you may have scanned the wrong product. The code was stored.';
$_['text_no_ean']                   = 'No EAN';
$_['help_no_ean']                   = 'No barcode (EAN) loaded from Odoo for this line. Run cli/odoo_variant_barcode_sync.php.';

// Scanner test
$_['text_group_scanner']            = 'Scanner test';
$_['entry_scan_test']               = 'Scan a DataMatrix';
$_['help_scan_test']                = 'Scan a code from a shoe/apparel box with the scanner the packer will use. Nothing is stored. If the scanner does not send the GS separator, configure it to send Ctrl+] or F8 instead of GS — the module inserts the separator.';
$_['text_scan_ok']                  = 'GS separator received from the scanner — the scanner is set up correctly.';
$_['text_scan_restored']            = 'The scanner did not send GS; separators were rebuilt from the code layout (01…21…91…92…). Usable, but better configure the scanner to send Ctrl+] or F8 for GS.';
$_['text_scan_no_gs']               = 'No GS separator and it could not be rebuilt — the fiscal drive will reject this code. Configure the scanner (GS → Ctrl+] or F8).';
$_['text_scan_length']              = 'Length, bytes';

// Receipt verification
$_['button_verify_receipt']         = 'Verify receipt';
$_['text_verify_doc']               = 'Receipt';
$_['text_verify_payments']          = 'payment (type=amount)';
$_['text_verify_ok']                = 'Receipt registered; codes and industry attribute 1260/1265 match the check.';
$_['text_verify_mismatch']          = 'Mismatch: tag 1265 in the receipt differs from the check result (or is missing).';
$_['text_verify_expected']          = 'expected';
