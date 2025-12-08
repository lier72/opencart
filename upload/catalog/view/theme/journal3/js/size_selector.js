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
		this.conversionTables = null;
		this.lang = {};
		this.init();
	};

	SizeSelector.DEFAULTS = {
		productId: null,
		defaultSystem: 'EU',
		showStock: true,
		ajaxUrl: 'index.php?route=extension/module/size_selector'
	};

	SizeSelector.prototype = {
		init: function() {
			var self = this;
			// console.log('SizeSelector: Initializing for product ID:', this.productId);

			this.loadSizeData(function() {
				// console.log('SizeSelector: Data loaded successfully, proceeding to render.');
				self.render();
				self.bindEvents();
				self.hideStandardOptions();
			});
		},

		loadSizeData: function(callback) {
			var self = this;

			// console.log('SizeSelector: Loading size data from URL:', this.options.ajaxUrl, 'with data:', { product_id: this.productId });
			$.ajax({
				url: this.options.ajaxUrl,
				type: 'GET',
				data: {
					product_id: this.productId
				},
				dataType: 'json',
				success: function(response) {
					// console.log('SizeSelector: AJAX success. Server response:', response);

					if (response.status === 'success') {
						// console.log('SizeSelector: Response status is "success".');
						var data = response.data;
						self.sizeData = data;
						self.lang = data.lang || {};
						self.conversionTables = data.conversion_tables || null;
						self.currentSystem = data.default_system || self.options.defaultSystem;

						if (data.genders && data.genders.length > 0) {
							self.currentGender = data.genders[0];
							// console.log('SizeSelector: Default gender set to:', self.currentGender);
						} else {
							console.warn('SizeSelector: No genders found in the response data.');
						}

						if (self.currentGender && data.size_data[self.currentGender]) {
							self.currentSizeType = data.size_data[self.currentGender].size_type;
							// console.log('SizeSelector: Default size type set to:', self.currentSizeType);
						} else {
							console.warn('SizeSelector: No size data found for the current gender:', self.currentGender);
						}

						if (callback) callback();
					} else {
						// console.error('SizeSelector: Response status was not "success". Hiding element.');
						self.$element.hide();
					}
				},
				error: function(xhr, status, error) {
					console.error('SizeSelector: AJAX request failed.', { status: status, error: error });
					// console.log('SizeSelector: Full server response for debugging:', xhr.responseText);
					self.$element.hide();
				}
			});
		},

		render: function() {
			if (!this.sizeData || !this.currentGender) {
				// console.warn('SizeSelector: Render aborted. Missing sizeData or currentGender.', { hasSizeData: !!this.sizeData, hasCurrentGender: !!this.currentGender });
				return;
			}

			var html = '';
			var genderData = this.sizeData.size_data[this.currentGender];

			if (!genderData) {
				// console.warn('SizeSelector: Render aborted. No size data available for the current gender:', this.currentGender);
				return;
			}

			// console.log('SizeSelector: Rendering UI for gender:', this.currentGender, 'and system:', this.currentSystem);
			var requiredClass = genderData.required ? ' required' : '';
			html += '<div class="form-group' + requiredClass + ' product-option-radio push-option">';

			var optionLabel = genderData.option_name.replace(/\s*\([A-Z]+\)\s*$/, '');
			html += '<label class="control-label">' + optionLabel + ' (' + this.currentSystem + ')</label>';

			// Gender selector (if multiple genders)
			if (this.sizeData.genders.length > 1) {
				html += '<div class="size-gender-selector">';
				this.sizeData.genders.forEach(function(gender) {
					var activeClass = gender === this.currentGender ? ' active' : '';
					var genderLabel = this.getGenderLabel(gender);
					html += '<button type="button" class="btn btn-default btn-gender' + activeClass + '" data-gender="' + gender + '">';
					html += genderLabel;
					html += '</button>';
				}.bind(this));
				html += '</div>';
			}

			// Row 1: System selector + Size guide button
			var availableSystems = this.getAvailableSystems(genderData.size_type, this.currentGender);
			var sizeGuideText = this.lang.size_guide || 'Таблица размеров';

			html += '<div class="size-toolbar">';

			if (availableSystems.length > 1) {
				html += '<div class="size-system-selector">';
				availableSystems.forEach(function(system) {
					var activeClass = system === this.currentSystem ? ' active' : '';
					html += '<button type="button" class="btn btn-default btn-sm btn-system' + activeClass + '" data-system="' + system + '">';
					html += system;
					html += '</button>';
				}.bind(this));
				html += '</div>';
			}

			html += '<a href="#" class="btn-size-guide" data-gender="' + this.currentGender + '" data-type="' + this.currentSizeType + '">';
			html += '<i class="fa fa-ruler"></i> ' + sizeGuideText;
			html += '</a>';

			html += '</div>';

			// Row 2: Size buttons
			html += '<div id="custom-input-option' + genderData.product_option_id + '" class="size-options-row">';
			html += this.renderSizeButtons(genderData);
			html += '</div>';

			html += '</div>';

			this.$element.html(html);
		},

		renderSizeButtons: function(genderData) {
			var html = '';
			var sourceSystem = genderData.source_system;
			var sizes = genderData.sizes;

			sizes.forEach(function(sizeItem) {
				if (sizeItem.subtract && sizeItem.quantity <= 0) {
					return;
				}

				var displaySize = this.convertSize(
					sizeItem.size,
					sourceSystem,
					this.currentSystem,
					this.currentGender,
					this.currentSizeType
				);

				if (!displaySize) {
					displaySize = sizeItem.size;
				}

				html += '<div class="radio">';
				html += '<label>';
				html += '<input type="radio" name="option[' + genderData.product_option_id + ']" ';
				html += 'value="' + sizeItem.option_value_id + '" ';
				html += 'title="' + displaySize + '" aria-label="' + displaySize + '" ';
				html += 'data-size="' + displaySize + '" data-original-size="' + sizeItem.size + '" />';
				html += '<span class="option-wrapper">';
				html += '<span class="option-value">' + displaySize + '</span>';
				html += '</span>';
				html += '</label>';
				html += '</div>';
			}.bind(this));

			return html;
		},

		bindEvents: function() {
			var self = this;

			this.$element.on('click', '.btn-gender', function(e) {
				e.preventDefault();
				var gender = $(this).data('gender');
				self.switchGender(gender);
			});

			this.$element.on('click', '.btn-system', function(e) {
				e.preventDefault();
				var system = $(this).data('system');
				self.switchSystem(system);
			});

			this.$element.on('change', 'input[type="radio"]', function() {
				var $radio = $(this);
				var optionValueId = $radio.val();
				var optionName = $radio.attr('name');

				// Remove 'selected' class from all options and add to current
				self.$element.find('.radio').removeClass('selected');
				$radio.closest('.radio').addClass('selected');

				// Find the original hidden radio (not inside size-selector-container)
				var $originalRadio = $('.product-options input[type="radio"][name="' + optionName + '"][value="' + optionValueId + '"]')
					.not('#size-selector-container input');

				if ($originalRadio.length > 0) {
					// Use triggerHandler to avoid bubbling and infinite loops
					$originalRadio.prop('checked', true);
					// Trigger native change event without jQuery to avoid recursion
					$originalRadio[0].dispatchEvent(new Event('change', { bubbles: true }));
				}
			});

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

			if (this.sizeData.size_data[gender]) {
				this.currentSizeType = this.sizeData.size_data[gender].size_type;
			}

			this.render();
		},

		switchSystem: function(system) {
			if (system === this.currentSystem) {
				return;
			}

			this.currentSystem = system;
			this.render();
		},

		convertSize: function(value, fromSystem, toSystem, gender, sizeType) {
			if (fromSystem === toSystem) {
				return value;
			}

			var tables = this.getConversionTables(gender, sizeType);
			if (!tables) {
				// console.warn('SizeSelector: No conversion tables found for', gender, sizeType);
				return value;
			}
			if (!tables[fromSystem]) {
				// console.warn('SizeSelector: No source system', fromSystem, 'in tables:', Object.keys(tables));
				return value;
			}
			if (!tables[toSystem]) {
				// console.warn('SizeSelector: No target system', toSystem, 'in tables:', Object.keys(tables));
				return value;
			}

			var index = tables[fromSystem].indexOf(value);
			if (index === -1) {
				index = tables[fromSystem].indexOf(value.toString());
			}
			if (index === -1) {
				// Try fuzzy match for fractional sizes like "35 2/3"
				for (var i = 0; i < tables[fromSystem].length; i++) {
					if (tables[fromSystem][i].toString().indexOf(value) === 0 ||
						value.toString().indexOf(tables[fromSystem][i]) === 0) {
						index = i;
						break;
					}
				}
			}
			if (index === -1) {
				// console.warn('SizeSelector: Size', value, 'not found in', fromSystem, 'table');
				return value;
			}

			return tables[toSystem][index] || value;
		},

		getConversionTables: function(gender, sizeType) {
			// First try to get from database tables (v2)
			if (this.conversionTables) {
				var tableKey = gender + '_' + sizeType;
				if (this.conversionTables[tableKey]) {
					return this.conversionTables[tableKey];
				}

				// Try unisex fallback
				var unisexKey = 'unisex_' + sizeType;
				if (this.conversionTables[unisexKey]) {
					return this.conversionTables[unisexKey];
				}
			}

			// Fallback to hardcoded tables
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
				'women': this.lang.gender_women || 'Женские',
				'men': this.lang.gender_men || 'Мужские',
				'kids': this.lang.gender_kids || 'Детские',
				'universal': this.lang.gender_universal || 'Универсальные',
				'unisex': this.lang.gender_unisex || 'Унисекс'
			};
			return labels[gender] || gender;
		},

		hideStandardOptions: function() {
			if (!this.sizeData) {
				return;
			}

			var self = this;

			Object.keys(this.sizeData.size_data).forEach(function(gender) {
				var productOptionId = this.sizeData.size_data[gender].product_option_id;
				// console.log('SizeSelector: Hiding original option group for product_option_id:', productOptionId);
				var $originalFormGroup = $('#input-option' + productOptionId).closest('.form-group');
				$originalFormGroup.hide();

				// Store for validation observer
				self.productOptionId = productOptionId;

				// Set up MutationObserver to detect error messages
				self.observeErrors($originalFormGroup[0], productOptionId);
			}.bind(this));
		},

		observeErrors: function(targetNode, productOptionId) {
			if (!targetNode) return;

			var self = this;

			// Create observer to watch for .text-danger being added
			var observer = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					mutation.addedNodes.forEach(function(node) {
						if (node.nodeType === 1 && $(node).hasClass('text-danger')) {
							// Error message was added to hidden original option
							var errorText = $(node).text();
							self.showError(errorText);
						}
					});

					// Also check for has-error class being added
					if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
						var $target = $(mutation.target);
						if ($target.hasClass('has-error')) {
							self.$element.find('.form-group').addClass('has-error');
						} else {
							self.$element.find('.form-group').removeClass('has-error');
							self.clearError();
						}
					}
				});
			});

			// Observe for child additions and attribute changes
			observer.observe(targetNode, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class']
			});

			// Store observer reference for cleanup
			this.errorObserver = observer;
		},

		showError: function(message) {
			// Remove existing error
			this.$element.find('.size-error').remove();

			// Add error message
			this.$element.find('.form-group').addClass('has-error');
			this.$element.find('.size-options-row').after('<div class="size-error text-danger">' + message + '</div>');

			// Scroll to error
			try {
				$('html, body').animate({
					scrollTop: this.$element.offset().top - $('header').height() - 20
				}, 'slow');
			} catch (e) {}
		},

		clearError: function() {
			this.$element.find('.form-group').removeClass('has-error');
			this.$element.find('.size-error').remove();
		},

		showSizeGuide: function(gender, sizeType) {
			var self = this;

			$.ajax({
				url: 'index.php?route=extension/module/size_selector/getSizeGuide',
				type: 'GET',
				data: {
					gender: gender,
					size_type: sizeType,
					category_id: 0
				},
				dataType: 'json',
				success: function(response) {
					if (response.status === 'success') {
						self.renderSizeGuideModal(response.data);
					}
				}
			});
		},

		renderSizeGuideModal: function(data) {
			var genderLabel = this.getGenderLabel(data.gender);
			var sizeTypeLabel = data.size_type === 'shoes' ? 'обуви' : 'одежды';
			var modalTitle = 'Таблица размеров ' + sizeTypeLabel + ' (' + genderLabel + ')';

			var modalHtml = '<div class="modal fade size-guide-modal" tabindex="-1" role="dialog">';
			modalHtml += '<div class="modal-dialog modal-lg" role="document">';
			modalHtml += '<div class="modal-content">';
			modalHtml += '<div class="modal-header">';
			modalHtml += '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>';
			modalHtml += '<h4 class="modal-title">' + modalTitle + '</h4>';
			modalHtml += '</div>';
			modalHtml += '<div class="modal-body">';

			if (data.size_type === 'apparel' && data.measurements) {
				modalHtml += this.renderMeasurementsTable(data.measurements, data.gender, data.size_tables);
			}

			if (data.size_type === 'shoes' && data.size_tables) {
				modalHtml += this.renderSizeTable(data.size_tables, data.gender);
			}

			modalHtml += '</div>';
			modalHtml += '</div></div></div>';

			$('.size-guide-modal').remove();
			$('body').append(modalHtml);
			$('.size-guide-modal').modal('show');
		},

		renderSizeTable: function(tables, gender) {
			var lang = this.lang;
			var title = lang.size_chart_shoes || 'Соответствие размеров обуви';
			var mmLabel = lang.millimeters || 'Миллиметры';
			var hint = lang.recommendation || 'Если вы сомневаетесь в своем размере, рекомендуем измерить стельку обуви, которая подходит вам идеально и сравнить его с размером обуви в миллиметрах.';

			var html = '<div class="size-conversion-table">';
			html += '<h5>' + title + '</h5>';
			html += '<table class="table table-bordered table-sm">';
			html += '<thead><tr>';

			Object.keys(tables).forEach(function(system) {
				var label = system === 'mm' ? mmLabel : system;
				html += '<th>' + label + '</th>';
			});

			html += '</tr></thead><tbody>';

			var maxLength = 0;
			Object.keys(tables).forEach(function(system) {
				if (tables[system].length > maxLength) {
					maxLength = tables[system].length;
				}
			});

			for (var i = 0; i < maxLength; i++) {
				html += '<tr>';
				Object.keys(tables).forEach(function(system) {
					var value = tables[system][i] || '';
					html += '<td>' + value + '</td>';
				});
				html += '</tr>';
			}

			html += '</tbody></table>';
			html += '<p class="text-muted"><small>' + hint + '</small></p>';
			html += '</div>';

			return html;
		},

		renderMeasurementsTable: function(measurements, gender, sizeTables) {
			var lang = this.lang;
			var title = lang.measurements || 'Таблица размеров одежды';
			var chestLabel = lang.chest_waist || 'Рост / Обхват груди (см)';
			var waistLabel = lang.chest_waist_lower || 'Рост / Обхват талии (см)';

			// Determine which size systems to show
			var systems = [];
			if (sizeTables) {
				// Show Asian, EU, US in that order if available
				['Asian', 'EU', 'US'].forEach(function(sys) {
					if (sizeTables[sys]) {
						systems.push(sys);
					}
				});
			}

			var html = '<div class="measurements-table">';
			html += '<h5>' + title + '</h5>';
			html += '<div class="table-responsive">';
			html += '<table class="table table-bordered table-sm">';
			html += '<thead><tr>';

			// Size system headers
			systems.forEach(function(sys) {
				html += '<th>' + sys + '</th>';
			});

			// Measurement headers
			html += '<th>' + chestLabel + '</th>';
			html += '<th>' + waistLabel + '</th>';
			html += '</tr></thead><tbody>';

			// Get measurement keys (Asian sizes like XS, S, M, L, etc.)
			var measurementKeys = Object.keys(measurements);

			measurementKeys.forEach(function(asianSize, index) {
				var measure = measurements[asianSize];
				html += '<tr>';

				// Size conversions for each system
				systems.forEach(function(sys) {
					var sizeValue = '';
					if (sizeTables && sizeTables[sys] && sizeTables[sys][index] !== undefined) {
						sizeValue = sizeTables[sys][index];
					} else if (sys === 'Asian') {
						sizeValue = asianSize;
					}
					html += '<td><strong>' + sizeValue + '</strong></td>';
				});

				// Measurements
				html += '<td>' + (measure.chest || '') + '</td>';
				html += '<td>' + (measure.waist || '') + '</td>';
				html += '</tr>';
			});

			html += '</tbody></table>';
			html += '</div>';
			html += '</div>';

			return html;
		}
	};

	$.fn.sizeSelector = function(options) {
		return this.each(function() {
			var $this = $(this);
			var data = $this.data('sizeSelector');

			if (!data) {
				$this.data('sizeSelector', (data = new SizeSelector(this, options)));
			}
		});
	};

})(jQuery);
