/**
 * Journal3 Size Selector Module
 * Frontend JavaScript for interactive size selection
 */

(function($) {
	'use strict';

	var SizeSelector = function(element, options) {
		this.$element = $(element);
		this.options = $.extend({}, SizeSelector.DEFAULTS, options);
		this.productId = this.options.productId;
		this.currentGender = null;
		this.currentSystem = null;
		this.currentSizeType = null;
		this.selectedSize = null;
		this.sizeData = null;
		this.init();
	};

	SizeSelector.DEFAULTS = {
		productId: null,
		defaultSystem: 'EU',
		showStock: true,
		ajaxUrl: 'index.php?route=journal3/size_selector'
	};

	SizeSelector.prototype = {
		init: function() {
			var self = this;

			// Load size data from server
			this.loadSizeData(function() {
				self.render();
				self.bindEvents();
				self.hideStandardOptions();
			});
		},

		loadSizeData: function(callback) {
			var self = this;

			$.ajax({
				url: this.options.ajaxUrl,
				type: 'GET',
				data: {
					product_id: this.productId
				},
				dataType: 'json',
				success: function(response) {
				console.log("Size selector AJAX response:", response);
					if (response.status === 'success') {
					console.log("Success! Size data:", response.response);
						self.sizeData = response.response;
						self.currentSystem = response.response.default_system || self.options.defaultSystem;

						// Set default gender (first available)
						if (response.response.genders && response.response.genders.length > 0) {
							self.currentGender = response.response.genders[0];
						}

						// Set size type from first gender's data
						if (self.currentGender && response.response.size_data[self.currentGender]) {
							self.currentSizeType = response.response.size_data[self.currentGender].size_type;
						}

						if (callback) callback();
					} else {
						console.error('Size selector: Failed to load size data');
						self.$element.hide();
					}
				},
				error: function() {
					console.error('Size selector: AJAX error loading size data');
				}
			});
		},

	render: function() {
		console.log('render() called, sizeData:', this.sizeData, 'currentGender:', this.currentGender);
		
		if (!this.sizeData || !this.currentGender) {
			console.log('Render aborted: missing data');
			return;
		}

		var html = '';
		var genderData = this.sizeData.size_data[this.currentGender];

		if (!genderData) {
			console.log('Render aborted: no genderData for', this.currentGender);
			return;
		}

		console.log('Rendering size selector for gender:', this.currentGender, 'with data:', genderData);

		// Use Journal3's standard form-group structure with push-option class
		var requiredClass = genderData.required ? ' required' : '';
		html += '<div class="form-group' + requiredClass + ' product-option-radio push-option">';
		
		// Option label - remove source system from name and show current system
		var optionLabel = genderData.option_name.replace(/\s*\([A-Z]+\)\s*$/, ''); // Remove (US), (EU), etc from end
		html += '<label class="control-label">' + optionLabel + ' (' + this.currentSystem + ')</label>';
		
		// Gender tabs (if multiple genders available)
		if (this.sizeData.genders.length > 1) {
			html += '<div class="size-gender-selector" style="margin-bottom: 10px;">';
			this.sizeData.genders.forEach(function(gender) {
				var activeClass = gender === this.currentGender ? ' active' : '';
				var genderLabel = this.getGenderLabel(gender);
				html += '<button type="button" class="btn btn-default' + activeClass + '" data-gender="' + gender + '" style="margin-right: 5px;">';
				html += genderLabel;
				html += '</button>';
			}.bind(this));
			html += '</div>';
		}

		// Size system tabs
		var availableSystems = this.getAvailableSystems(genderData.size_type, this.currentGender);
		if (availableSystems.length > 1) {
			html += '<div class="size-system-selector" style="margin-bottom: 10px;">';
			availableSystems.forEach(function(system) {
				var activeClass = system === this.currentSystem ? ' active' : '';
				html += '<button type="button" class="btn btn-default btn-sm btn-system' + activeClass + '" data-system="' + system + '" style="margin-right: 3px;">';
				html += system;
				html += '</button>';
			}.bind(this));
			html += '</div>';
		}

		// Container for size options using Journal3's structure
		// Use custom-input-option prefix to avoid conflict with original Journal3 options
		html += '<div id="custom-input-option' + genderData.product_option_id + '">';
		html += this.renderSizeButtons(genderData);
		html += '</div>';

		// Size guide link
		html += '<div style="margin-top: 10px; text-align: center;">';
		html += '<a href="#" class="btn-size-guide" data-gender="' + this.currentGender + '" data-type="' + this.currentSizeType + '" style="font-size: 12px;">';
		html += '<i class="fa fa-ruler"></i> Таблица размеров';
		html += '</a>';
		html += '</div>';

		html += '</div>'; // Close form-group

		console.log("Setting HTML, length:", html.length);
		this.$element.html(html);
		console.log("After setting HTML, first 200 chars:", this.$element.html().substring(0, 200));
	},

	renderSizeButtons: function(genderData) {
		console.log('renderSizeButtons called with:', genderData);
		var html = '';
		var sourceSystem = genderData.source_system;
		var sizes = genderData.sizes;

		console.log('Source system:', sourceSystem, 'Current system:', this.currentSystem);
		console.log('Total sizes:', sizes.length);

		var renderedCount = 0;
		sizes.forEach(function(sizeItem) {
			// Skip items with zero or negative quantity if subtract is enabled
			if (sizeItem.subtract && sizeItem.quantity <= 0) {
				console.log('Skipping out of stock size:', sizeItem.size);
				return; // Skip this size
			}

			// Convert size to current system if needed
			var displaySize = this.convertSize(
				sizeItem.size,
				sourceSystem,
				this.currentSystem,
				this.currentGender,
				this.currentSizeType
			);

			if (!displaySize) {
				displaySize = sizeItem.size; // Fallback to original
			}

			console.log('Rendering size:', sizeItem.size, '->', displaySize);

			// Stock status indicator (optional - only show if low stock)
			var stockText = '';
			if (this.sizeData.show_stock && sizeItem.subtract) {
				if (sizeItem.quantity <= 3) {
					stockText = ' <small style="color: #ff9800;">(' + sizeItem.quantity + ')</small>';
				}
			}

			// Create Journal3-style radio option
			html += '<div class="radio">';
			html += '<label>';
			html += '<input type="radio" name="option[' + genderData.product_option_id + ']" ';
			html += 'value="' + sizeItem.option_value_id + '" ';
			html += 'title="' + displaySize + '" aria-label="' + displaySize + '" ';
			html += 'data-size="' + displaySize + '" data-original-size="' + sizeItem.size + '" />';
			html += '<span class="option-wrapper">';
			html += '<span class="option-value">' + displaySize + stockText + '</span>';
			html += '</span>';
			html += '</label>';
			html += '</div>';
			
			renderedCount++;
		}.bind(this));

		console.log('Rendered', renderedCount, 'sizes, HTML length:', html.length);
		return html;
	},
	bindEvents: function() {
		var self = this;

		// Gender selection
		this.$element.on('click', '.btn-gender', function(e) {
			e.preventDefault();
			var gender = $(this).data('gender');
			self.switchGender(gender);
		});

		// System selection
		this.$element.on('click', '.btn-system', function(e) {
			e.preventDefault();
			console.log("btn-system clicked! Event:", e);
			var system = $(this).data('system');
			self.switchSystem(system);
			console.log("System button clicked:", system);
		});

		// Radio button change - sync to original hidden option and trigger Journal3's price update
		this.$element.on('change', 'input[type="radio"]', function() {
			var $radio = $(this);
			var optionValueId = $radio.val();
			var optionName = $radio.attr('name'); // e.g., "option[991]"

			console.log('Size selected:', optionValueId, $radio.data('size'), 'syncing to original option:', optionName);

			// Find and check the corresponding radio button in the hidden original Journal3 options
			// Use attribute selector with exact match
			var $originalRadio = $('.product-options input[type="radio"][name="' + optionName + '"][value="' + optionValueId + '"]');
			console.log('Found original radio:', $originalRadio.length, $originalRadio);

			if ($originalRadio.length > 0) {
				$originalRadio.prop('checked', true).trigger('change');
				console.log('Successfully synced to original option and triggered change');
			} else {
				console.error('Could not find original radio button for', optionName, 'value', optionValueId);
				// Try searching in entire document, not just .product-options
				var $fallback = $('input[type="radio"][name="' + optionName + '"][value="' + optionValueId + '"]').not('#size-selector-container input');
				console.log('Fallback search found:', $fallback.length, $fallback);
				if ($fallback.length > 0) {
					$fallback.prop('checked', true).trigger('change');
					console.log('Successfully synced via fallback');
				}
			}
		});

		// Size guide
		this.$element.on('click', '.btn-size-guide', function(e) {
			e.preventDefault();
			var gender = $(this).data('gender');
			var sizeType = $(this).data('type');
			self.showSizeGuide(gender, sizeType);
		});
	},
		switchGender: function(gender) {
			if (gender === this.currentGender) {
				return;
			}

			this.currentGender = gender;
			this.selectedSize = null;

			// Update size type
			if (this.sizeData.size_data[gender]) {
				this.currentSizeType = this.sizeData.size_data[gender].size_type;
			}

			this.render();
		},

		switchSystem: function(system) {
		console.log("switchSystem called:", system, "current:", this.currentSystem);
			if (system === this.currentSystem) {
			console.log("Already on this system, ignoring");
				return;
			}

			this.currentSystem = system;
		console.log("Switched to system:", system, "now rendering...");
			this.render();
		},

	convertSize: function(value, fromSystem, toSystem, gender, sizeType) {
		if (fromSystem === toSystem) {
			return value;
		}

		var tables = this.getConversionTables(gender, sizeType);
		if (!tables || !tables[fromSystem] || !tables[toSystem]) {
			return value;
		}

		var index = tables[fromSystem].indexOf(value);
		if (index === -1) {
			index = tables[fromSystem].indexOf(value.toString());
		}
		if (index === -1) {
			return value;
		}

		return tables[toSystem][index] || value;
	},

	getConversionTables: function(gender, sizeType) {
		if (typeof SizeConversionTables === 'undefined') {
			return null;
		}
		if (sizeType === 'shoes') {
			return (gender === 'women') ? SizeConversionTables.women_shoes : SizeConversionTables.universal_shoes;
		} else if (sizeType === 'apparel') {
			return SizeConversionTables.apparel;
		}
		return null;
	},

	getAvailableSystems: function(sizeType, gender) {
			if (sizeType === 'shoes') {
				return ['EU', 'US', 'UK', 'mm'];
			} else if (sizeType === 'apparel') {
				return ['Asian', 'EU', 'US'];
			}
			return ['EU'];
		},

		getGenderLabel: function(gender) {
			var labels = {
				'women': '👗 Женские',
				'universal': '👔 Универсальные',
				'unisex': '⚡ Унисекс'
			};
			return labels[gender] || gender;
		},

	hideStandardOptions: function() {
		console.log('hideStandardOptions called');
		// Hide the standard OpenCart option display for size options
		if (!this.sizeData) {
			console.log('No sizeData, aborting hide');
			return;
		}

		Object.keys(this.sizeData.size_data).forEach(function(gender) {
			var productOptionId = this.sizeData.size_data[gender].product_option_id;
			console.log('Trying to hide option ID:', productOptionId);

			// Hide the standard option container
			var $target = $('#input-option' + productOptionId).closest('.form-group');
			console.log('Found elements to hide:', $target.length, $target);
			$target.hide();
		}.bind(this));
	},
		showSizeGuide: function(gender, sizeType) {
			var self = this;

			$.ajax({
				url: 'index.php?route=journal3/size_selector/getSizeGuide',
				type: 'GET',
				data: {
					gender: gender,
					size_type: sizeType,
					category_id: 0 // TODO: Get from product
				},
				dataType: 'json',
				success: function(response) {
					if (response.status === 'success') {
						self.renderSizeGuideModal(response.response);
					}
				}
			});
		},

		renderSizeGuideModal: function(data) {
			var modalHtml = '<div class="modal fade size-guide-modal" tabindex="-1" role="dialog">';
			modalHtml += '<div class="modal-dialog modal-lg" role="document">';
			modalHtml += '<div class="modal-content">';
			modalHtml += '<div class="modal-header">';
			modalHtml += '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>';
			modalHtml += '<h4 class="modal-title">Таблица размеров</h4>';
			modalHtml += '</div>';
			modalHtml += '<div class="modal-body">';

			// Size conversion tables for shoes
			if (data.size_tables) {
				modalHtml += this.renderSizeTable(data.size_tables, data.gender);
			}

			// Measurements for apparel
			if (data.measurements) {
				modalHtml += this.renderMeasurementsTable(data.measurements, data.gender);
			}

			// Custom guide content
			if (data.guide_content) {
				modalHtml += '<div class="guide-content">' + data.guide_content + '</div>';
			}

			modalHtml += '</div>';
			modalHtml += '</div></div></div>';

			// Remove existing modal if any
			$('.size-guide-modal').remove();

			// Append and show
			$('body').append(modalHtml);
			$('.size-guide-modal').modal('show');
		},

		renderSizeTable: function(tables, gender) {
			var html = '<div class="size-conversion-table">';
			html += '<h5>Соответствие размеров обуви</h5>';
			html += '<table class="table table-bordered table-sm">';
			html += '<thead><tr>';

			// Headers
			Object.keys(tables).forEach(function(system) {
				var label = system === 'mm' ? 'Миллиметры' : system;
				html += '<th>' + label + '</th>';
			});

			html += '</tr></thead><tbody>';

			// Get max length
			var maxLength = 0;
			Object.keys(tables).forEach(function(system) {
				if (tables[system].length > maxLength) {
					maxLength = tables[system].length;
				}
			});

			// Rows
			for (var i = 0; i < maxLength; i++) {
				html += '<tr>';
				Object.keys(tables).forEach(function(system) {
					var value = tables[system][i] || '';
					html += '<td>' + value + '</td>';
				});
				html += '</tr>';
			}

			html += '</tbody></table>';
			html += '<p class="text-muted"><small>Если вы сомневаетесь в своем размере, рекомендуем измерить стельку обуви, которая подходит вам идеально и сравнить его с размером обуви в миллиметрах.</small></p>';
			html += '</div>';

			return html;
		},

		renderMeasurementsTable: function(measurements, gender) {
			var html = '<div class="measurements-table">';
			html += '<h5>Размерные измерения</h5>';
			html += '<table class="table table-bordered table-sm">';
			html += '<thead><tr>';
			html += '<th>Размер</th>';
			html += '<th>Рост / Обхват груди (см)</th>';
			html += '<th>Рост / Обхват талии (см)</th>';
			html += '</tr></thead><tbody>';

			Object.keys(measurements).forEach(function(size) {
				var measure = measurements[size];
				html += '<tr>';
				html += '<td><strong>' + size + '</strong></td>';
				html += '<td>' + (measure.chest || '') + '</td>';
				html += '<td>' + (measure.waist || '') + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			html += '</div>';

			return html;
		}
	};

	// jQuery plugin
	$.fn.sizeSelector = function(options) {
		return this.each(function() {
			var $this = $(this);
			var data = $this.data('sizeSelector');

			if (!data) {
				$this.data('sizeSelector', (data = new SizeSelector(this, options)));
			}
		});
	};

	// Auto-initialize on product pages
	$(document).ready(function() {
		if ($('.product-info').length > 0) {
			var productId = $('input[name="product_id"]').val();

			if (productId && $('#size-selector-container').length > 0) {
				$('#size-selector-container').sizeSelector({
					productId: productId
				});
			}
		}
	});

})(jQuery);
