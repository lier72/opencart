<?php

use Journal3\Utils\Arr;

/**
 * Size Selector Module
 * Frontend controller for size selection functionality
 */
class ControllerExtensionModuleSizeSelector extends Controller {

	/**
	 * Main entry point - Get size selector data for a product
	 *
	 * @return void (outputs JSON)
	 */
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('extension/module/size_mapping');
		$this->load->model('extension/module/size_converter');

		$product_id = (int)Arr::get($this->request->get, 'product_id');

		if (!$product_id) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('status' => 'error', 'message' => 'Product ID required')));
			return;
		}

		// Check if product has size-mapped options
		if (!$this->model_extension_module_size_mapping->productHasSizeOptions($product_id)) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('status' => 'error', 'message' => 'No size options configured')));
			return;
		}

		// Get product mappings
		$mappings = $this->model_extension_module_size_mapping->getProductMappings($product_id);

		// Try v2 category-based gender detection first
		$category_gender = null;
		if ($this->model_extension_module_size_mapping->isV2Installed()) {
			$category_gender = $this->model_extension_module_size_mapping->getProductGenderFromCategory($product_id);
		}

		// Get product options with values
		$product_options = $this->model_catalog_product->getProductOptions($product_id);

		$size_data = array();

		foreach ($mappings as $option_id => $mapping) {
			// Find the corresponding product option by option_id (not product_option_id)
			$product_option = null;
			foreach ($product_options as $po) {
				if ($po['option_id'] == $option_id) {
					$product_option = $po;
					break;
				}
			}

			if (!$product_option) {
				continue;
			}

			// Process each option value
			$sizes = array();

			if (isset($product_option['product_option_value'])) {
				foreach ($product_option['product_option_value'] as $pov) {
					// Parse size from description
					$size_value = $this->model_extension_module_size_converter->parseSize(
						$pov['name'],
						$mapping['source_system']
					);

					if ($size_value) {
						$sizes[] = array(
							'option_value_id' => $pov['product_option_value_id'],
							'size' => $size_value,
							'original_name' => $pov['name'],
							'price' => $pov['price'],
							'price_prefix' => $pov['price_prefix'],
							'quantity' => $pov['quantity'],
							'subtract' => $pov['subtract']
						);
					}
				}
			}

			// Determine the effective gender for this mapping
			// For SHOES: always use the option mapping gender (admin configured the correct conversion table)
			// For APPAREL: can use category-based gender if available (simpler sizes like XS, S, M, L)
			$effective_gender = $mapping['gender'];

			if ($mapping['size_type'] == 'apparel' && $category_gender && $category_gender['gender_type'] != 'unisex') {
				$effective_gender = $category_gender['gender_type'];
			}

			$size_data[$effective_gender] = array(
				'option_id' => $option_id,
				'product_option_id' => $product_option['product_option_id'],
				'option_name' => $product_option['name'],
				'gender' => $effective_gender,
				'size_type' => $mapping['size_type'],
				'source_system' => $mapping['source_system'],
				'sizes' => $sizes,
				'required' => $product_option['required']
			);
		}

		// Build genders array from actual size_data keys (ensures consistency)
		$genders = array_keys($size_data);

		// Get conversion tables from database if v2 is installed
		$conversion_tables = array();
		if ($this->model_extension_module_size_mapping->isV2Installed()) {
			// Load tables for all genders used in this product
			$all_genders = array_unique(array_merge($genders, array_keys($size_data)));

			foreach ($all_genders as $gender) {
				foreach (array('shoes', 'apparel') as $size_type) {
					$table = $this->model_extension_module_size_mapping->getConversionTable($gender, $size_type);
					if ($table && isset($table['table_data']) && is_array($table['table_data'])) {
						$conversion_tables[$gender . '_' . $size_type] = $table['table_data'];
					}
				}
			}

			// Also load unisex as fallback
			if (!isset($conversion_tables['unisex_shoes'])) {
				$table = $this->model_extension_module_size_mapping->getConversionTable('unisex', 'shoes');
				if ($table && isset($table['table_data']) && is_array($table['table_data'])) {
					$conversion_tables['unisex_shoes'] = $table['table_data'];
				}
			}
		}

		// Load language file
		$this->load->language('extension/module/size_selector');

		$data = array(
			'product_id' => $product_id,
			'genders' => $genders,
			'size_data' => $size_data,
			'default_system' => $this->model_extension_module_size_mapping->getSetting('default_size_system', 'EU'),
			'show_stock' => $this->model_extension_module_size_mapping->getSetting('show_stock_status', '1') == '1',
			'conversion_tables' => $conversion_tables,
			'category_gender' => $category_gender,
			'lang' => array(
				'size_guide' => $this->language->get('text_size_guide'),
				'gender_women' => $this->language->get('text_women'),
				'gender_men' => $this->language->get('text_men') ?: 'Мужские',
				'gender_kids' => $this->language->get('text_kids') ?: 'Детские',
				'gender_universal' => $this->language->get('text_universal'),
				'gender_unisex' => $this->language->get('text_unisex'),
				'size_chart_shoes' => $this->language->get('text_size_chart_shoes'),
				'measurements' => $this->language->get('text_measurements'),
				'text_size' => $this->language->get('text_size') ?: 'Размер',
				'chest_waist' => $this->language->get('text_chest_waist'),
				'chest_waist_lower' => $this->language->get('text_chest_waist_lower'),
				'recommendation' => $this->language->get('text_recommendation'),
				'millimeters' => $this->language->get('text_millimeters'),
				'loading' => $this->language->get('text_loading')
			)
		);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('status' => 'success', 'data' => $data)));
	}

	/**
	 * Get size guide content
	 *
	 * @return void (outputs JSON with HTML)
	 */
	public function getSizeGuide() {
		$this->load->model('extension/module/size_mapping');
		$this->load->model('extension/module/size_converter');

		$gender = Arr::get($this->request->get, 'gender');
		$size_type = Arr::get($this->request->get, 'size_type');
		$category_id = (int)Arr::get($this->request->get, 'category_id', 0);

		if (!$gender || !$size_type) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode(array('status' => 'error', 'message' => 'Missing parameters')));
			return;
		}

		// If apparel, get measurements
		$measurements = null;
		if ($size_type === 'apparel') {
			$measurements = $this->model_extension_module_size_converter->getMeasurements($gender);
		}

		// Get size conversion tables
		$size_tables = null;

		// Try to get from database first (v2)
		if ($this->model_extension_module_size_mapping->isV2Installed()) {
			$table = $this->model_extension_module_size_mapping->getConversionTable($gender, $size_type);
			if ($table && isset($table['table_data'])) {
				$size_tables = $table['table_data'];
			}
		}

		// Fallback to hardcoded tables
		if (!$size_tables) {
			$systems = $this->model_extension_module_size_converter->getAvailableSystems($gender, $size_type);
			$size_tables = array();

			foreach ($systems as $system) {
				$size_tables[$system] = $this->model_extension_module_size_converter->getAllSizes($system, $gender, $size_type);
			}
		}

		$data = array(
			'measurements' => $measurements,
			'size_tables' => $size_tables,
			'gender' => $gender,
			'size_type' => $size_type
		);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(array('status' => 'success', 'data' => $data)));
	}
}

class_alias('ControllerExtensionModuleSizeSelector', '\Opencart\Catalog\Controller\Extension\Module\SizeSelector');
