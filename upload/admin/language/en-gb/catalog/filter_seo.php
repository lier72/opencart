<?php
// Heading
$_['heading_title']                    = 'Filter SEO';

// Text
$_['text_success']                     = 'Success: You have modified filter SEO settings!';
$_['text_list']                        = 'Facets (attributes, options, filter groups)';
$_['text_list_help']                   = 'The prefix override word is combined with each value as "word-facetname-value", e.g. raketki-tsvet-belyy.';
$_['text_type_fa']                     = 'Attribute';
$_['text_type_fo']                     = 'Option';
$_['text_type_ff']                     = 'Filter';
$_['text_colliding']                   = 'This name matches another facet - without an override, slugs will be ambiguous';
$_['text_colliding_short']             = 'Collides';
$_['text_colliding_warning']           = 'This facet\'s name collides with another one - without a prefix override (below), generated slugs will get a numeric suffix (-2, -3, ...) instead of a meaningful word.';
$_['text_configured']                  = 'Configured';
$_['text_values']                      = 'Values';
$_['text_values_help']                 = 'Only values currently in use by a product are listed. Changes take effect immediately on save - old URLs for any changed value will stop working.';
$_['text_not_generated']               = '(not generated yet)';
$_['text_no_results']                  = 'No results!';
$_['text_confirm_regenerate']          = 'Regenerate every URL for this facet now? Old URLs will stop working.';
$_['text_regenerated']                 = 'Regenerated %s URL(s).';

// Column
$_['column_type']                      = 'Type';
$_['column_name']                      = 'Name';
$_['column_group']                     = 'Group';
$_['column_status']                    = 'Status';
$_['column_action']                    = 'Action';
$_['column_value']                     = 'Value';
$_['column_current_slug']              = 'Current URL';
$_['column_value_override']            = 'Override';

// Entry
$_['entry_prefix_override']            = 'Facet prefix override';
$_['entry_prefix_override_placeholder'] = 'e.g. raketki';
$_['entry_omit_facet_name']            = 'Drop the facet name from the URL entirely';
$_['entry_strip']                      = 'Simplify URL';
$_['entry_strip_parenthetical']        = 'Drop trailing "(...)" (e.g. a colour code like "(C066)")';
$_['entry_strip_brackets']             = 'Drop trailing "[...]" (e.g. "[Asia (S)]")';

// Help
$_['help_prefix_override']             = 'By default combined with the facet\'s own name, e.g. "raketki" + "Colour" = raketki-tsvet-*. Turn on "Drop the facet name" below if you want just "raketki-belyy", or a bare value, instead.';
$_['help_omit_facet_name']              = 'E.g. raketki-tsvet-belyy becomes raketki-belyy. Leave the prefix override above blank as well to get just the value, e.g. plain "belyy".';
$_['help_regenerate']                  = 'Regenerates every URL for this facet right now, instead of waiting for organic traffic to trigger it.';

// Button
$_['button_edit']                      = 'Edit';
$_['button_save']                      = 'Save';
$_['button_cancel']                    = 'Cancel';
$_['button_regenerate']                = 'Regenerate URLs now';

// Error
$_['error_permission']                 = 'Warning: You do not have permission to modify filter SEO settings!';
$_['error_facet_not_found']            = 'Warning: Facet not found!';
