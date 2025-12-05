<?php

use Journal3\Utils\Arr;

/**
 * Journal3 Size Selector Module
 * Frontend controller for size selection functionality
 */
class ControllerJournal3SizeSelector extends Controller {

	/**
	 * Main entry point - Get size selector data for a product
	 *
	 * @return void (outputs JSON)
	 */
	public function index() {
		$this->load->model('catalog/product');
		$this->load->model('journal3/size_mapping');
		$this->load->model('journal3/size_converter');

		$product_id = (int)Arr::get($this->request->get, 'product_id');

		if (!$product_id) {
			$this->journal3_response->json('error', array('message' => 'Product ID required'));
			return;
		}

		// Check if product has size-mapped options
		if (!$this->model_journal3_size_mapping->productHasSizeOptions($product_id)) {
			$this->journal3_response->json('error', array('message' => 'No size options configured'));
			return;
		}

		// Get product mappings
		$mappings = $this->model_journal3_size_mapping->getProductMappings($product_id);
		$genders = $this->model_journal3_size_mapping->getProductGenders($product_id);

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
					$size_value = $this->model_journal3_size_converter->parseSize(
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

				$size_data[$mapping['gender']] = array(
				'option_id' => $option_id,
				'product_option_id' => $product_option['product_option_id'],
				'option_name' => $product_option['name'],
				'gender' => $mapping['gender'],
				'size_type' => $mapping['size_type'],
				'source_system' => $mapping['source_system'],
				'sizes' => $sizes,
				'required' => $product_option['required']
			);
		}

		$data = array(
			'product_id' => $product_id,
			'genders' => $genders,
			'size_data' => $size_data,
			'default_system' => $this->model_journal3_size_mapping->getSetting('default_size_system', 'EU'),
			'show_stock' => $this->model_journal3_size_mapping->getSetting('show_stock_status', '1') == '1'
		);

		$this->journal3_response->json('success', $data);
	}

	/**
	 * Convert sizes to a different system
	 * AJAX endpoint for dynamic size system switching
	 *
	 * @return void (outputs JSON)
	 */
	public function convertSizes() {
		$this->load->model('journal3/size_converter');
		$this->load->model('journal3/size_mapping');

		$sizes = Arr::get($this->request->post, 'sizes', array());
		$from_system = Arr::get($this->request->post, 'from_system');
		$to_system = Arr::get($this->request->post, 'to_system');
		$gender = Arr::get($this->request->post, 'gender');
		$size_type = Arr::get($this->request->post, 'size_type');

		if (!$sizes || !$from_system || !$to_system || !$gender || !$size_type) {
			$this->journal3_response->json('error', array('message' => 'Missing parameters'));
			return;
		}

		$converted = array();

		foreach ($sizes as $size_item) {
			$size_value = $size_item['size'];

			$converted_value = $this->model_journal3_size_converter->convert(
				$size_value,
				$from_system,
				$to_system,
				$gender,
				$size_type
			);

			if ($converted_value !== false) {
				$converted[] = array(
					'option_value_id' => $size_item['option_value_id'],
					'size' => $converted_value,
					'original_size' => $size_value,
					'quantity' => $size_item['quantity'],
					'subtract' => $size_item['subtract']
				);
			}
		}

		$this->journal3_response->json('success', array('sizes' => $converted));
	}

	/**
	 * Convert gender (Women <-> Universal) for shoes
	 * AJAX endpoint for gender switching
	 *
	 * @return void (outputs JSON)
	 */
	public function convertGender() {
		$this->load->model('journal3/size_converter');

		$size_value = Arr::get($this->request->post, 'size');
		$current_system = Arr::get($this->request->post, 'system');
		$from_gender = Arr::get($this->request->post, 'from_gender');
		$to_gender = Arr::get($this->request->post, 'to_gender');

		if (!$size_value || !$current_system || !$from_gender || !$to_gender) {
			$this->journal3_response->json('error', array('message' => 'Missing parameters'));
			return;
		}

		$converted = $this->model_journal3_size_converter->convertGender(
			$size_value,
			$current_system,
			$from_gender,
			$to_gender
		);

		if ($converted !== false) {
			$this->journal3_response->json('success', array('size' => $converted));
		} else {
			$this->journal3_response->json('error', array('message' => 'Conversion failed'));
		}
	}

	/**
	 * Get size guide content
	 *
	 * @return void (outputs JSON with HTML)
	 */
	public function getSizeGuide() {
		$this->load->model('journal3/size_mapping');
		$this->load->model('journal3/size_converter');

		$gender = Arr::get($this->request->get, 'gender');
		$size_type = Arr::get($this->request->get, 'size_type');
		$category_id = (int)Arr::get($this->request->get, 'category_id', 0);

		if (!$gender || !$size_type) {
			$this->journal3_response->json('error', array('message' => 'Missing parameters'));
			return;
		}

		// Get size guide from database
		$guide = $this->model_journal3_size_mapping->getSizeGuide($category_id, $gender, $size_type);

		// If apparel, also get measurements
		$measurements = null;
		if ($size_type === 'apparel') {
			$measurements = $this->model_journal3_size_converter->getMeasurements($gender);
		}

		// Get shoe size conversion tables if shoes
		$size_tables = null;
		if ($size_type === 'shoes') {
			$systems = $this->model_journal3_size_converter->getAvailableSystems($gender, $size_type);
			$size_tables = array();

			foreach ($systems as $system) {
				$size_tables[$system] = $this->model_journal3_size_converter->getAllSizes($system, $gender, $size_type);
			}
		}

		$data = array(
			'guide_content' => $guide ? $guide['guide_content'] : '',
			'measurements' => $measurements,
			'size_tables' => $size_tables,
			'gender' => $gender,
			'size_type' => $size_type
		);

		$this->journal3_response->json('success', $data);
	}

	/**
	 * Render size selector widget for product page
	 * This method can be called from product.twig or via AJAX
	 *
	 * @return string HTML output
	 */
	public function render() {
		$this->load->model('journal3/size_mapping');
		$this->load->model('journal3/size_converter');

		$product_id = (int)Arr::get($this->request->get, 'product_id');

		if (!$product_id || !$this->model_journal3_size_mapping->productHasSizeOptions($product_id)) {
			return '';
		}

		$this->load->language('journal3/size_selector');

		$data = array();
		$data['product_id'] = $product_id;
		$data['text_select_size'] = $this->language->get('text_select_size');
		$data['text_size_guide'] = $this->language->get('text_size_guide');
		$data['ajax_url'] = $this->url->link('journal3/size_selector');

		return $this->load->view('journal3/module/size_selector', $data);
	}
}

class_alias('ControllerJournal3SizeSelector', '\Opencart\Catalog\Controller\Journal3\SizeSelector');
