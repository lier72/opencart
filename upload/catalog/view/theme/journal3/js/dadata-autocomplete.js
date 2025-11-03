/**
 * DaData.ru Address Autocomplete for OpenCart Journal3 Checkout
 * Provides automatic address completion using DaData.ru API
 *
 * Usage: Include this file in checkout pages and it will automatically
 * attach to address input fields.
 */

(function($) {
    'use strict';

    // DaData configuration
    var DaDataAutocomplete = {
        debug: false, // Set to true to enable logging
        // API configuration - replace with your actual API key
        apiKey: '8a5d1d5804c065c9e1c11b5975abd334d29b713f',
        apiUrl: 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address',

        // Selector configuration for different checkout forms
        selectors: {
            // Payment address fields (logged-in users and guest)
            payment: {
                address: '#input-payment-address-1',
                city: '#input-payment-city',
                postcode: '#input-payment-postcode',
                zone: '#input-payment-zone',
                country: '#input-payment-country'
            },
            // Shipping address fields
            shipping: {
                address: '#input-shipping-address-1',
                city: '#input-shipping-city',
                postcode: '#input-shipping-postcode',
                zone: '#input-shipping-zone',
                country: '#input-shipping-country'
            }
        },

        // Initialize autocomplete
        init: function() {
            // Only log critical configuration issues
            if (this.apiKey === 'YOUR_DADATA_API_KEY_HERE') {
                console.error('DaData API key not configured');
                return;
            }

            // Wait for Journal3 Vue QuickCheckout to be available
            this.waitForVueCheckout();
        },

        // Wait for Vue QuickCheckout to mount
        waitForVueCheckout: function() {
            var self = this;
            var attempts = 0;
            var maxAttempts = 50; // 5 seconds max wait

            var checkInterval = setInterval(function() {
                attempts++;

                // Check if Vue instance is ready
                if (window.QuickCheckout || attempts >= maxAttempts) {
                    clearInterval(checkInterval);

                    if (window.QuickCheckout) {
                        if (self.debug) console.log('DaData: Journal3 Vue QuickCheckout detected');
                    } else {
                        if (self.debug) console.log('DaData: Vue not detected, using standard mode');
                    }

                    // Attach to payment address fields
                    self.attachAutocomplete('payment');

                    // Attach to shipping address fields
                    self.attachAutocomplete('shipping');

                    // Listen for dynamic form loading
                    self.listenForDynamicForms();
                }
            }, 100);
        },

        // Attach autocomplete to specific form type
        attachAutocomplete: function(type) {
            var self = this;
            var selectors = this.selectors[type];

            // Check if already attached globally
            if (this['_attached_' + type]) {
                console.log('DaData: Already attached to ' + type + ', skipping');
                return;
            }

            console.log('DaData: Searching for ' + type + ' address input with selector:', selectors.address);

            // Wait for the input to exist (it might be loaded via AJAX or hidden by Vue v-if)
            var attempts = 0;
            var maxAttempts = 20; // 10 seconds total
            var checkInterval = setInterval(function() {
                attempts++;
                var $addressInput = $(selectors.address);

                if ($addressInput.length && !$addressInput.data('dadata-initialized')) {
                    // Check if visible
                    var isVisible = $addressInput.is(':visible');

                    if (isVisible) {
                        console.log('DaData: ✓ Attaching autocomplete to ' + type + ' address field (attempt ' + attempts + ')');
                        self.setupAddressAutocomplete($addressInput, selectors);
                        $addressInput.data('dadata-initialized', true);
                        self['_attached_' + type] = true; // Mark as globally attached
                        clearInterval(checkInterval);
                    }
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.log('DaData: Stopped checking for ' + type + ' address field after ' + attempts + ' attempts');
                }
            }, 500);
        },

        // Setup autocomplete on address input field
        setupAddressAutocomplete: function($input, selectors) {
            var self = this;
            var typingTimer;
            var doneTypingInterval = 300; // ms
            var $suggestionsContainer = null;
            var isSelecting = false; // Flag to prevent suggestions after selection
            var isPrepending = false; // Flag to prevent infinite loops when auto-prepending city
            var lastSelectedSuggestion = null; // Store last selected suggestion to detect continuation

            // Create suggestions dropdown container
            function createSuggestionsContainer() {
                if ($suggestionsContainer && $suggestionsContainer.length) {
                    // Update position in case input moved
                    updateContainerPosition();
                    return $suggestionsContainer;
                }

                $suggestionsContainer = $('<div>')
                    .addClass('dadata-suggestions')
                    .css({
                        'position': 'absolute',
                        'z-index': '9999',
                        'background': '#fff',
                        'border': '1px solid #ddd',
                        'border-radius': '4px',
                        'box-shadow': '0 2px 8px rgba(0,0,0,0.15)',
                        'max-height': '300px',
                        'overflow-y': 'auto',
                        'display': 'none',
                        'width': $input.outerWidth() + 'px'
                    });

                // Insert after input's parent to avoid layout issues
                $input.parent().css('position', 'relative');
                $input.parent().append($suggestionsContainer);

                updateContainerPosition();

                return $suggestionsContainer;
            }

            // Update container position
            function updateContainerPosition() {
                if (!$suggestionsContainer) return;

                var inputHeight = $input.outerHeight();
                var inputPos = $input.position(); // position relative to parent

                $suggestionsContainer.css({
                    'top': (inputPos.top + inputHeight) + 'px',
                    'left': inputPos.left + 'px',
                    'width': $input.outerWidth() + 'px'
                });

                if (self.debug) console.log('DaData: Container positioned at top:', inputPos.top + inputHeight, 'left:', inputPos.left);
            }

            // Hide suggestions
            function hideSuggestions() {
                if ($suggestionsContainer) {
                    $suggestionsContainer.hide();
                }
            }

            // Show suggestions
            function showSuggestions(suggestions) {
                var $container = createSuggestionsContainer();
                $container.empty();

                if (!suggestions || suggestions.length === 0) {
                    hideSuggestions();
                    return;
                }

                suggestions.forEach(function(suggestion, index) {
                    var $item = $('<div>')
                        .addClass('dadata-suggestion-item')
                        .html(self.highlightMatch(suggestion.value, $input.val()))
                        .css({
                            'padding': '10px 15px',
                            'cursor': 'pointer',
                            'border-bottom': '1px solid #f0f0f0'
                        })
                        .hover(
                                function() { $(this).addClass('active'); },
                                function() { $(this).removeClass('active'); }
                        )
                        .on('mousedown', function(e) {
                            e.preventDefault(); // Prevent blur event
                            if (self.debug) console.log('DaData: Suggestion clicked:', suggestion.value);

                            // Set flag to prevent suggestions from showing after filling
                            isSelecting = true;

                            // Store selected suggestion to detect if user continues typing for block/flat
                            lastSelectedSuggestion = suggestion;
                            if (self.debug) console.log('DaData: Stored suggestion for continuation detection');

                            self.fillAddress(suggestion, selectors);
                            hideSuggestions();
                        })
                        .data('suggestion', suggestion);

                    $container.append($item);
                });

                if (self.debug) {
                    console.log('DaData: Showing container...');
                    console.log('DaData: Container is visible:', $container.is(':visible'));
                    console.log('DaData: Container position:', $container.css('top'), $container.css('left'));
                }
                $container.show();
            }

            // Temporarily disable Vue auto-save during typing
            var vueInstance = window['_QuickCheckout'];
            var originalSave = null;
            var saveDisabled = false;

            function disableVueSave() {
                if (vueInstance && vueInstance.save && !saveDisabled) {
                    originalSave = vueInstance.save;
                    vueInstance.save = function() {
                        console.log('DaData: Vue save() blocked during typing');
                    };
                    saveDisabled = true;
                    console.log('DaData: Disabled Vue auto-save');
                }
            }

            function enableVueSave() {
                if (vueInstance && originalSave && saveDisabled) {
                    vueInstance.save = originalSave;
                    saveDisabled = false;
                    if (self.debug) console.log('DaData: Re-enabled Vue auto-save');
                }
            }

            // Input event handler
            $input.on('input', function() {
                clearTimeout(typingTimer);
                var query = $(this).val();

                if (self.debug) console.log('DaData: Input event fired, query:', query, 'length:', query.length, 'isSelecting:', isSelecting, 'isPrepending:', isPrepending);

                // Don't show suggestions if we're in the process of selecting
                if (isSelecting) {
                    if (self.debug) console.log('DaData: Skipping suggestions (selection in progress)');
                    isSelecting = false; // Reset flag
                    return;
                }

                // Skip if we're in the middle of auto-prepending
                if (isPrepending) {
                    isPrepending = false;
                    return;
                }

                // Disable Vue auto-save while user is typing
                disableVueSave();

                // Get city field value
                var $cityField = $(selectors.city);
                var cityValue = $cityField.val();

                // Simple logic:
                // 1. If city field has value and user typing -> auto-prepend
                // 2. If user deleting city from address -> clear city field
                // 3. Don't sync city field during typing (only on selection)

                if (cityValue && cityValue.length > 0) {
                    var cityPrefix = 'г ' + cityValue + ', ';

                    // Check if query has the expected city prefix
                    if (query.length > 0 && query.startsWith(cityPrefix)) {
                        // Good - address has city, nothing to do
                        if (self.debug) console.log('DaData: Address has city prefix');
                    } else if (query.length > 0 && query.startsWith('г ')) {
                        // User is deleting/editing city - clear city field and don't update it
                        if (self.debug) console.log('DaData: User editing city, clearing city field');
                        $cityField.val('');
                        var evt = new Event('input', { bubbles: true });
                        $cityField[0].dispatchEvent(evt);
                        cityValue = '';
                    } else if (query.length > 0 && !query.startsWith('г ')) {
                        // User typing street without city - auto-prepend
                        isPrepending = true;
                        var newQuery = cityPrefix + query;
                        if (self.debug) console.log('DaData: Auto-prepending city:', cityPrefix);
                        $input.val(newQuery);

                        // Move cursor to end
                        if ($input[0].setSelectionRange) {
                            var len = newQuery.length;
                            $input[0].setSelectionRange(len, len);
                        }

                        query = newQuery;
                    } else if (query.length === 0) {
                        // User cleared address field - clear city field too
                        if (self.debug) console.log('DaData: Address cleared, clearing city field');
                        $cityField.val('');
                        var evt = new Event('input', { bubbles: true });
                        $cityField[0].dispatchEvent(evt);
                        cityValue = '';
                    }
                }

                if (query.length >= 3) {
                        if (self.debug) console.log('DaData: Query length >= 3, will fetch suggestions in ' + doneTypingInterval + 'ms');
                        typingTimer = setTimeout(function() {
                            if (self.debug) console.log('DaData: Fetching suggestions for:', query);
                            if (self.debug) console.log('DaData: City constraint:', cityValue || '(none - search all cities)');

                            // Pass full query and city constraint
                            self.fetchSuggestions(query, showSuggestions, cityValue);                        // Re-enable Vue save after user stops typing
                        enableVueSave();
                    }, doneTypingInterval);
                } else {
                    hideSuggestions();
                    // Re-enable Vue save when query is too short
                    enableVueSave();
                }
            });

            // Hide suggestions on blur (with delay to allow click)
            $input.on('blur', function() {
                // Re-enable Vue save when user leaves the field
                setTimeout(function() {
                    enableVueSave();
                }, 300);
                setTimeout(function() {
                    if ($suggestionsContainer && !$suggestionsContainer.is(':hover')) {
                        hideSuggestions();
                    }
                }, 200);
            });

            // Keyboard navigation
            $input.on('keydown', function(e) {
                if (!$suggestionsContainer || !$suggestionsContainer.is(':visible')) {
                    return;
                }

                var $items = $suggestionsContainer.find('.dadata-suggestion-item');
                var $current = $items.filter('.active');
                var $next;

                switch(e.keyCode) {
                    case 40: // Down arrow
                        e.preventDefault();
                            $items.removeClass('active');
                            $next = $current.length ? $current.next() : $items.first();
                            if ($next.length) {
                                $next.addClass('active');
                                $suggestionsContainer.scrollTop($next.position().top + $suggestionsContainer.scrollTop() - 50);
                            }
                        break;
                    case 38: // Up arrow
                        e.preventDefault();
                            $items.removeClass('active');
                            $next = $current.length ? $current.prev() : $items.last();
                            if ($next.length) {
                                $next.addClass('active');
                                $suggestionsContainer.scrollTop($next.position().top + $suggestionsContainer.scrollTop() - 50);
                            }
                        break;
                    case 13: // Enter
                        e.preventDefault();
                        if ($current.length) {
                            var suggestion = $current.data('suggestion');

                            // Set flag to prevent suggestions from showing after filling
                            isSelecting = true;

                            // Store selected suggestion to detect if user continues typing for block/flat
                            lastSelectedSuggestion = suggestion;
                            if (self.debug) console.log('DaData: Stored suggestion (Enter key):', suggestion.value);

                            self.fillAddress(suggestion, selectors);
                            hideSuggestions();
                        }
                        break;
                    case 27: // Escape
                        e.preventDefault();
                        hideSuggestions();
                        break;
                }
            });
        },

        // Fetch suggestions from DaData API
        fetchSuggestions: function(query, callback, cityConstraint) {
            var self = this;
            // Build request data
            var requestData = {
                query: query,
                count: 10,
                // Always search up to flat level for complete addresses
                from_bound: { value: "street" },
                to_bound: { value: "flat" }
            };

            // Add city constraint if provided
            if (cityConstraint && cityConstraint.length > 0) {
                requestData.locations = [
                    { city: cityConstraint }
                ];
                if (this.debug) console.log('DaData: Added locations filter:', requestData.locations);
            }

            $.ajax({
                url: this.apiUrl,
                type: 'POST',
                headers: {
                    'Authorization': 'Token ' + this.apiKey,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                data: JSON.stringify(requestData),
                dataType: 'json',
                success: function(response) {
                    if (self.debug) {
                        console.log('DaData: API response received:', response);
                        if (response && response.suggestions) {
                            console.log('DaData: Found ' + response.suggestions.length + ' suggestions');
                        } else {
                            console.log('DaData: No suggestions in response');
                        }
                    }
                    if (response && response.suggestions) {
                        callback(response.suggestions);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('DaData API Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            });
        },

        // Fill address fields with selected suggestion
        fillAddress: function(suggestion, selectors) {
            var data = suggestion.data;

            console.log('DaData: Filling address fields', data);

            // Determine address type from selector
            var addressType = selectors.address.indexOf('payment') !== -1 ? 'payment' : 'shipping';

            // Try to find Vue instance - Journal3 checkout uses window['_QuickCheckout']
            var vueInstance = window['_QuickCheckout'];

            if (this.debug) {
                console.log('DaData: window._QuickCheckout exists:', !!vueInstance);

                if (vueInstance) {
                    console.log('DaData: Vue instance properties:');
                    console.log('  - has order_data:', !!vueInstance.order_data);
                    console.log('  - has save():', typeof vueInstance.save === 'function');
                    console.log('  - has payment_zones:', !!vueInstance.payment_zones);
                    console.log('  - has shipping_zones:', !!vueInstance.shipping_zones);
                } else {
                    console.log('DaData: Vue instance not found - will use fallback mode');
                }
            }

            // Try to update Vue data directly if available
            if (vueInstance && vueInstance.order_data) {
                console.log('DaData: Using Vue instance to fill fields');
                // Fill address line 1 - WITHOUT city (city goes to separate field)
                var addressValue = '';
                if (data.street_with_type || data.house) {
                    var addressParts = [];
                    // Don't include city in address field
                    if (data.street_with_type) addressParts.push(data.street_with_type);
                    if (data.house_type && data.house) addressParts.push(data.house_type + ' ' + data.house);
                    if (data.block_type && data.block) addressParts.push(data.block_type + ' ' + data.block);
                    if (data.flat_type && data.flat) addressParts.push(data.flat_type + ' ' + data.flat);
                    addressValue = addressParts.join(', ');
                } else {
                    // Fallback to suggestion value
                    addressValue = suggestion.value;
                }

                vueInstance.order_data[addressType + '_address_1'] = addressValue;

                // Fill city
                if (data.city) {
                    vueInstance.order_data[addressType + '_city'] = data.city;
                } else if (data.settlement) {
                    vueInstance.order_data[addressType + '_city'] = data.settlement;
                }

                // Fill postal code
                if (data.postal_code) {
                    vueInstance.order_data[addressType + '_postcode'] = data.postal_code;
                }

                // Fill region/zone and trigger save after zone is set
                if (data.region) {
                    this.setZoneVue(data.region, addressType, vueInstance);
                    // Save will be triggered after zone is set (in setZoneVue with 200ms delay)
                    setTimeout(function() {
                        if (vueInstance && vueInstance.save) {
                            console.log('DaData: Triggering Vue save() after zone set');
                            vueInstance.save();
                        }
                    }, 300); // Wait for setZoneVue to complete
                } else {
                    // No region to set, save immediately
                    setTimeout(function() {
                        if (vueInstance && vueInstance.save) {
                            console.log('DaData: Triggering Vue save()');
                            vueInstance.save();
                        }
                    }, 100);
                }
            } else {
                // Fallback to direct input manipulation for non-Vue checkout
                if (this.debug) {
                    console.log('DaData: Using fallback mode (direct input manipulation)');
                    console.log('DaData: City to fill:', data.city || data.settlement);
                }
                
                // Fill address line 1 - WITHOUT city (city goes to separate field)
                var addressValue;
                if (data.street_with_type || data.house) {
                    var addressParts = [];
                    // Don't include city in address field
                    if (data.street_with_type) addressParts.push(data.street_with_type);
                    if (data.house_type && data.house) addressParts.push(data.house_type + ' ' + data.house);
                    if (data.block_type && data.block) addressParts.push(data.block_type + ' ' + data.block);
                    if (data.flat_type && data.flat) addressParts.push(data.flat_type + ' ' + data.flat);
                    addressValue = addressParts.join(', ');
                } else {
                    // Fallback to suggestion value
                    addressValue = suggestion.value;
                }

                var $addressField = $(selectors.address);
                $addressField.val(addressValue);

                // Trigger native input event for Vue
                var inputEvent = new Event('input', { bubbles: true });
                $addressField[0].dispatchEvent(inputEvent);

                $addressField.trigger('change');

                // Fill city
                var cityValue = data.city || data.settlement;
                if (cityValue) {
                    console.log('DaData: Setting city field to:', cityValue);
                    var $cityField = $(selectors.city);
                    $cityField.val(cityValue);

                    // Trigger native input event for Vue to detect the change
                    var inputEvent = new Event('input', { bubbles: true });
                    $cityField[0].dispatchEvent(inputEvent);

                    // Also trigger change
                    $cityField.trigger('change');

                    console.log('DaData: City field after setting:', $cityField.val());
                }

                // Fill postal code
                if (data.postal_code) {
                    var $postcodeField = $(selectors.postcode);
                    $postcodeField.val(data.postal_code);

                    // Trigger native input event for Vue
                    var inputEvent = new Event('input', { bubbles: true });
                    $postcodeField[0].dispatchEvent(inputEvent);

                    $postcodeField.trigger('change');
                }

                // Fill region/zone
                if (data.region) {
                    this.setZone(data.region, data.region_fias_id, selectors);
                }

                // Trigger validation
                $(selectors.address).trigger('blur');
                $(selectors.city).trigger('blur');
                $(selectors.postcode).trigger('blur');
            }
        },

        // Set zone/region for Vue-based checkout
        setZoneVue: function(regionName, addressType, vueInstance) {
            if (this.debug) console.log('DaData: Setting zone for Vue checkout:', regionName);

            if (!vueInstance) return;

            // First, ensure Russia is selected
            var russiaCountryId = null;
            if (vueInstance.countries) {
                vueInstance.countries.forEach(function(country) {
                    if (country.name.indexOf('Russia') !== -1 || country.name.indexOf('Росс') !== -1) {
                        russiaCountryId = country.country_id;
                    }
                });

                if (russiaCountryId) {
                    vueInstance.order_data[addressType + '_country_id'] = russiaCountryId;

                    // Wait for zones to load
                    setTimeout(function() {
                        var zones = addressType === 'payment' ? vueInstance.payment_zones : vueInstance.shipping_zones;

                        if (zones && zones.length) {
                            var matched = false;
                            zones.forEach(function(zone) {
                                // Try to match zone name with region name
                                if (zone.name.indexOf(regionName) !== -1 || regionName.indexOf(zone.name) !== -1) {
                                    vueInstance.order_data[addressType + '_zone_id'] = zone.zone_id;
                                    matched = true;
                                    console.log('DaData: Matched zone:', zone.name);
                                }
                            });

                            if (!matched) {
                                console.log('DaData: Could not auto-match region:', regionName);
                            }
                        }
                    }, 200);
                }
            }
        },

        // Set zone/region field
        setZone: function(regionName, regionFiasId, selectors) {
            var $zone = $(selectors.zone);
            var $country = $(selectors.country);

            if (this.debug) {
                console.log('DaData: Setting zone/region:', regionName);
                console.log('DaData: Country selector:', selectors.country, 'found:', $country.length);
                console.log('DaData: Zone selector:', selectors.zone, 'found:', $zone.length);
            }

            // First ensure Russia is selected (country_id 176 is typically Russia)
            if ($country.length) {
                var russiaOption = $country.find('option:contains("Russia"), option:contains("Россия"), option:contains("Российская")').first();
                if (this.debug) console.log('DaData: Russia option found:', russiaOption.length, 'value:', russiaOption.val());

                if (russiaOption.length) {
                    var currentCountry = $country.val();
                    var russiaId = russiaOption.val();

                    if (this.debug) console.log('DaData: Current country:', currentCountry, 'Russia ID:', russiaId);

                    // Set Russia as country
                    $country.val(russiaId).trigger('change').trigger('input');

                    // Wait for zones to load, then try to match
                    var attempts = 0;
                    var self = this;
                    var checkZones = setInterval(function() {
                        attempts++;
                        var zoneOptions = $zone.find('option');
                        if (self.debug) console.log('DaData: Zone load attempt', attempts, '- options count:', zoneOptions.length);

                        if (zoneOptions.length > 1 || attempts >= 10) {
                            clearInterval(checkZones);

                            var matched = false;
                            zoneOptions.each(function() {
                                var optionText = $(this).text();
                                var optionValue = $(this).val();

                                // Try various matching patterns
                                if (optionText && regionName &&
                                    (optionText.indexOf(regionName) !== -1 ||
                                     regionName.indexOf(optionText) !== -1)) {
                                    if (self.debug) console.log('DaData: ✓ Matched zone:', optionText, 'value:', optionValue);
                                    $zone.val(optionValue).trigger('change').trigger('input');
                                    matched = true;
                                    return false; // break
                                }
                            });

                            if (!matched) {
                                if (self.debug) console.log('DaData: ✗ Could not auto-match region:', regionName);
                                if (self.debug) console.log('DaData: Available zones:', zoneOptions.map(function() {
                                    return $(this).text();
                                }).get());
                            }
                        }
                    }, 300);
                }
            } else {
                console.log('DaData: Country selector not found');
            }
        },


        // Highlight matching text in suggestions
        highlightMatch: function(text, query) {
            if (!query) return text;

            var regex = new RegExp('(' + this.escapeRegExp(query) + ')', 'gi');
            return text.replace(regex, '<strong>$1</strong>');
        },

        // Escape special characters for regex
        escapeRegExp: function(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        // Listen for dynamically loaded forms (Journal3 AJAX)
        listenForDynamicForms: function() {
            // Journal3 uses Vue.js with persistent DOM elements
            // The inputs are always present, just hidden/shown with v-if
            // Once attached, we don't need to re-attach
            // This function is kept for potential future compatibility
            if (this.debug) console.log('DaData: Form listener initialized (Journal3 Vue uses persistent elements)');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DaDataAutocomplete.init();

            // Listen for 'same address' checkbox changes to re-attach shipping autocomplete
            $('.checkout-same-address input[type="checkbox"]').on('change', function() {
                if (!this.checked) {
                    // Shipping address fields are now visible, reset flag and re-attach
                    DaDataAutocomplete._attached_shipping = false;
                    DaDataAutocomplete.attachAutocomplete('shipping');
                }
            });
    });

    // Also initialize on window load (fallback)
    $(window).on('load', function() {
        setTimeout(function() {
            DaDataAutocomplete.init();
        }, 500);
    });

    // Make it globally accessible for manual initialization if needed
    window.DaDataAutocomplete = DaDataAutocomplete;

})(jQuery);
