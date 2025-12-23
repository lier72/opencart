<?php

class ModelExtensionShippingCdek extends Model
{

    private $length_class_id = 1; // cm
    private $weight_class_id = 1; // kg
    private $api;
    private $auth_data;

    function __construct($registry)
    {

        parent::__construct($registry);

        $this->length_class_id = $this->config->get('shipping_cdek_length_class_id');
        $this->weight_class_id = $this->config->get('shipping_cdek_weight_class_id');

        require_once DIR_SYSTEM . 'library/cdek_integrator/class.cdek_integrator.php';

        $api_account = $this->config->get('shipping_cdek_login');
        $api_secure = $this->config->get('shipping_cdek_password');

        $this->api = new cdek_integrator($api_account, $api_secure);
    }

    function getQuote($address)
    {
        if (!isset($address['zone_id'])) {
            $address['zone_id'] = '';
        }
        if (!isset($address['country_id'])) {
            $address['country_id'] = '';
        }
        if (!isset($address['city'])) {
            $address['city'] = '';
        }
        if (!isset($address['zone'])) {
            $address['zone'] = '';
        }
        if (!isset($address['postcode'])) {
            $address['postcode'] = '';
        }
        if (!isset($address['address_1'])) {
            $address['address_1'] = '';
        }


        $this->load->language('extension/shipping/cdek');

        $quote_data = array();

        $renderData = array();

        $status = TRUE;

        if (!is_array($this->config->get('shipping_cdek_store')) || !in_array($this->config->get('config_store_id'), $this->config->get('shipping_cdek_store'))) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: не выбран магазин!');
            }

            $status = FALSE;
        }

        if (!$this->currency->getId('RUB')) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: в системе не найдена валюта "RUB"!');
            }

            $status = FALSE;
        }

        if (!$city_from = $this->config->get('shipping_cdek_city_from_id')) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: не выбран город отправки!');
            }

            $status = FALSE;
        }

        if (empty($address['zone_id'])) {
            $address['zone_id'] = 0;
        }

        if (empty($address['country_id'])) {
            $address['country_id'] = 0;
        }

        $this->auth_data = $this->getInfo()->getAuthToken();

        $products = $this->cart->getProducts();

        $cdek_default_weight = $this->config->get('shipping_cdek_default_weight');

        $weight = $this->getWeight();

        $min_weight = (float)$this->config->get('shipping_cdek_min_weight');
        $max_weight = (float)$this->config->get('shipping_cdek_max_weight');

        if ($status && (($min_weight > 0 && $weight < $min_weight) || ($max_weight > 0 && $weight > $max_weight))) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: превышены ограничения по весу!');
            }

            $status = FALSE;
        }


        if ($this->config->get('shipping_cdek_log')) {
            $this->log->write('СДЭК: вес заказа ' . $weight);
        }

        $total = $this->cart->getTotal();

        $min_total = (float)$this->config->get('shipping_cdek_min_total');
        $max_total = (float)$this->config->get('shipping_cdek_max_total');

        if ($status && (($min_total > 0 && $total < $min_total) || ($max_total > 0 && $total > $max_total))) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: превышены ограничения по стоимости!');
            }

            $status = FALSE;
        }


        $countries = array();
        $empty_country = FALSE;

        if (empty($address['country_id'])) {
            $address['country_id'] = $this->config->get('config_country_id');
        }

        $to_data = $this->db->query("SELECT name FROM " . DB_PREFIX . "country WHERE country_id = '" . (int)$address['country_id'] . "' LIMIT 1")->row;

        if ($to_data) {
            $countries = $this->prepareCountry($to_data['name']);
        } else {
            $empty_country = TRUE;
        }

        $empty_zone = FALSE;

        if (empty($address['zone_id'])) {
            $empty_zone = TRUE;
        }

        if (!$empty_zone) {
            $to_data = $this->db->query("SELECT name FROM " . DB_PREFIX . "zone WHERE zone_id = '" . (int)$address['zone_id'] . "' LIMIT 1")->row;
        }

        if (!$address['city'] && $address['zone']) {
            $zoneNameParts = trim(str_replace(array("ская Республика", "Республика", "АО", "- Югра"), "", $address['zone']));
            $cdekZoneData = $this->db->query("SELECT cityName FROM `" . DB_PREFIX . "cdek_city` WHERE (`regionName` LIKE '%" . $this->db->escape($zoneNameParts) . "%' OR `cityName` LIKE '%" . $this->db->escape($zoneNameParts) . "%') AND `center` = '1' LIMIT 1")->row;

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write("CDEK Info: SELECT cityName FROM `" . DB_PREFIX . "cdek_city` WHERE (`regionName` LIKE '%" . $this->db->escape($zoneNameParts) . "%' OR `cityName` LIKE '%" . $this->db->escape($zoneNameParts) . "%') AND `center` = '1' LIMIT 1");
                $this->log->write('СДЭК INFO: zone (покупатель): ' . $zoneNameParts);
                $this->log->write('СДЭК INFO: город (покупатель): ' . $cdekZoneData['cityName']);
            }
            if ($cdekZoneData) {
                $address['city'] = $cdekZoneData['cityName'];
            }

        }


        if ($to_data && $address['city']) {

            $regions = array();

            if (!$empty_zone) {
                $regions = $this->prepareRegion($to_data['name']);
            }

            $cities = array(trim($address['city']));
            $cities[] = preg_replace('|[^a-zа-яё]|isu', ' ', $address['city']);
            $cities[] = preg_replace('|[^a-zа-яё]|isu', '-', $address['city']);

            foreach ($cities as $city) {
                $city = trim(preg_replace('/\s+/', ' ', $city)); // нормализуем пробелы
                $cdek_cities = $this->getURL($this->getInfo()->getAjaxUrl() . 'v2/location/cities?city=' . urlencode($city), new parser_json());

                if (is_array($cdek_cities) && !empty($cdek_cities)) {
                    $address['city'] = $city;
                    break;
                }

            }

            if (is_array($cdek_cities)) {

                $available = array();
                $address['city'] = $this->_clear($address['city']);

                $city_ignore = array();

                if ($this->config->get('shipping_cdek_city_ignore')) {

                    $city_ignore = preg_split('#\s*,\s*#', $this->config->get('shipping_cdek_city_ignore'));
                    $city_ignore = array_map('trim', $city_ignore);
                    $city_ignore = array_filter($city_ignore);
                    $city_ignore = array_map(array($this, '_clear'), $city_ignore);

                }

                if (in_array($address['city'], $city_ignore)) {
                    $status = FALSE;
                }

                if ($status) {

                    foreach ($cdek_cities as $city_info) {

                        list($city) = explode(',', $city_info['city']);

                        if (mb_strpos($this->_clear($city), $address['city']) === 0) {
                            $available[] = $city_info;
                        }

                    }

                }

                if ($this->config->get('shipping_cdek_use_region_russia')) {
                    $address['zone'] = $this->ocToCdek($address['zone']);
                    $sql = "SELECT regionName FROM  `" . DB_PREFIX . "cdek_city` WHERE `regionName` = '" . $this->db->escape($address['zone']) . "' OR cityName = '" . $this->db->escape($address['zone']) . "' LIMIT 1";
                    $zoneQuery = $this->db->query($sql);
                    if ($zoneQuery->num_rows) {
                        $newAvailable = array();

                        foreach ($available as $key => $available_value) {
                            if (!isset($available_value['region'])) {
                                continue;
                            }

                            if ($available_value['region'] == $zoneQuery->row['regionName']) {
                                $newAvailable[] = $available_value;
                            }
                        }

                        if (count($newAvailable)) {
                            $available = $newAvailable;
                        } else {
                            if ($this->config->get('shipping_cdek_log')) {
                                $this->log->write('СДЭК WARNING: не удалось отфильтровать города по региону!');
                                $this->log->write('СДЭК WARNING: город: ' . $address['city']);
                                $this->log->write('СДЭК WARNING: регион: ' . $address['zone']);
                            }
                        }
                    } else {
                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК WARNING: не найден регион ' . $address['zone']);
                        }
                    }
                }

                if ($this->config->get('shipping_cdek_use_postcode') && $address['postcode']) {
                    $newAvailable = array();

                    foreach ($available as $key => $available_value) {
                        if (!empty($available_value['postal_codes'])) {
                            $postCodeArray = $available_value['postal_codes'];

                            if ($postCodeArray && in_array($address['postcode'], $postCodeArray)) {
                                $newAvailable[] = $available_value;
                            }
                        }
                    }

                    if (count($newAvailable)) {
                        $available = $newAvailable;
                    } else {
                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК WARNING: не удалось отфильтровать города по почтовому коду!');
                            $this->log->write('СДЭК WARNING: город: ' . $address['city']);
                            $this->log->write('СДЭК WARNING: индекс: ' . $address['postcode']);
                            $this->log->write('СДЭК WARNING: регион: ' . $address['zone']);
                        }
                    }
                }

                if ($count = count($available)) {

                    if ($count > 1) {
                        $available = array($available[0]);
                    }


                    $available_city = reset($available);


                    $city_to = $available_city['code'];

                    if ($this->config->get('shipping_cdek_log')) {
                        $this->log->write('СДЭК INFO: город (покупатель): ' . $address['city']);
                        $this->log->write('СДЭК INFO: регион (покупатель): ' . $address['zone']);

                        $zoneQuerySql_debug = "SELECT * FROM  `" . DB_PREFIX . "cdek_city` WHERE `id` = '" . (int)$city_to . "'";
                        $zoneQuery_debug = $this->db->query($zoneQuerySql_debug);

                        if ($zoneQuery_debug->num_rows) {
                            $this->log->write('СДЭК INFO: город (определен): ' . $zoneQuery_debug->row['cityName'] . ', ' . $zoneQuery_debug->row['regionName']);
                        } else {
                            $this->log->write('СДЭК INFO: город (определен): ' . $city_to);
                        }
                    }

                    if (!file_exists(DIR_APPLICATION . 'model' . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'shipping' . DIRECTORY_SEPARATOR . 'CalculatePriceDeliveryCdek.php')) {

                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК: file CalculatePriceDeliveryCdek.php not found!');
                        }

                        return;
                    }

                    require_once DIR_APPLICATION . 'model' . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'shipping' . DIRECTORY_SEPARATOR . 'CalculatePriceDeliveryCdek.php';

                    if (!class_exists('CalculatePriceDeliveryCdek')) {

                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК: class CalculatePriceDeliveryCdek not found!');
                        }

                        return;
                    }

                    $calc = new CalculatePriceDeliveryCdek($this);

                    $calc->setSenderCityId($city_from);
                    $calc->setReceiverCityId($city_to);

                    $day = (is_numeric($this->config->get('shipping_cdek_append_day'))) ? trim($this->config->get('shipping_cdek_append_day')) : 0;
                    $date = date('Y-m-d', strtotime('+' . (float)$day . ' day'));
                    $calc->setDateExecute($date);

                    $cdek_default_param = $this->config->get('shipping_cdek_default_param');

                    if ($cdek_default_param['use']) {

                        switch ($cdek_default_param['work_mode']) {
                            case 'order':
                                $calc->addGoodsItemBySize($cdek_default_param['weight'] * 1000, $cdek_default_param['size_a'], $cdek_default_param['size_b'], $cdek_default_param['size_c']);
                                break;
                            case 'all':
                            case 'optional':

                                foreach ($products as $product) {

                                    if ($cdek_default_param['work_mode'] == 'all') {
                                        $calc->addGoodsItemBySize($cdek_default_param['weight'] * 1000, (int)$cdek_default_param['size_a'], (int)$cdek_default_param['size_b'], (int)$cdek_default_param['size_c']);
                                    } else {
                                        if (!empty((int)$product['weight'])) {
                                            $weight = $this->weight->convert($product['weight'], $product['weight_class_id'], $this->weight_class_id);
                                        } else {
                                            $weight = $cdek_default_param['weight'] * 1000;
                                        }
                                        if (!empty((int)$product['length'])) {
                                            $length = $this->length->convert($product['length'], $product['length_class_id'], $this->length_class_id);
                                        } else {
                                            $length = $cdek_default_param['size_a'];
                                        }
                                        if (!empty((int)$product['width'])) {
                                            $width = $this->length->convert($product['width'], $product['length_class_id'], $this->length_class_id);
                                        } else {
                                            $width = $cdek_default_param['size_b'];
                                        }
                                        if (!empty((int)$product['height'])) {
                                            $height = $this->length->convert($product['height'], $product['length_class_id'], $this->length_class_id);
                                        } else {
                                            $height = $cdek_default_param['size_c'];
                                        }

                                        $calc->addGoodsItemBySize((int)$weight, (int)$length, (int)$width, (int)$height);
                                    }
                                }
                                break;
                        }

                    } else {

                        foreach ($products as $product) {

                            $weight = $this->weight->convert($product['weight'], $product['weight_class_id'], $this->weight_class_id);
                            $length = $this->length->convert($product['length'], $product['length_class_id'], $this->length_class_id);
                            $width = $this->length->convert($product['width'], $product['length_class_id'], $this->length_class_id);
                            $height = $this->length->convert($product['height'], $product['length_class_id'], $this->length_class_id);

                            if ((int)$weight == 0) {
                                if ($this->config->get('shipping_cdek_log')) {
                                    $this->log->write('СДЭК: не заполнен вес у товара!');
                                }

                                $status = FALSE;
                            }

                            if ((int)$length == 0) {
                                if ($this->config->get('shipping_cdek_log')) {
                                    $this->log->write('СДЭК: не заполнена длина у товара!');
                                }

                                $status = FALSE;
                            }

                            if ((int)$width == 0) {
                                if ($this->config->get('shipping_cdek_log')) {
                                    $this->log->write('СДЭК: не заполнена ширина у товара!');
                                }

                                $status = FALSE;
                            }

                            if ((int)$height == 0) {
                                if ($this->config->get('shipping_cdek_log')) {
                                    $this->log->write('СДЭК: не заполнена высота у товара!');
                                }

                                $status = FALSE;
                            }

                            if (!$status) {
                                break;
                            }

                            $calc->addGoodsItemBySize((int)$weight, (int)$length, (int)$width, (int)$height);
                        }
                        
                    }
                    if (!$weight) {

                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК: не заполнен вес у товара!');
                        }

                        $status = FALSE;
                    }

                    if ($status) {

                        if (!$this->config->get('shipping_cdek_custmer_tariff_list')) {

                            if ($this->config->get('shipping_cdek_log')) {
                                $this->log->write('СДЭК: список тарифов пуст!');
                            }

                            $status = FALSE;
                        }

                        if ($status) {

                            $geo_zones = array();

                            $query = $this->db->query("SELECT DISTINCT geo_zone_id FROM " . DB_PREFIX . "zone_to_geo_zone WHERE country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

                            if ($query->num_rows) {
                                foreach ($query->rows as $row) {
                                    $geo_zones[$row['geo_zone_id']] = $row['geo_zone_id'];
                                }

                            }

                            if ($this->customer->isLogged()) {
                                $customer_group_id = $this->customer->getGroupId();
                            } else {
                                $customer_group_id = $this->config->get('config_customer_group_id');
                            }

                            $cdek_tariff_list = $this->config->get('shipping_cdek_tariff_list');

                            $results = $tariff_list = array();

                            foreach ($this->config->get('shipping_cdek_custmer_tariff_list') as $key => $tariff_info) {

                                if (empty($cdek_tariff_list[$tariff_info['tariff_id']])) continue;

                                $tariff_title = !empty($tariff_info['title'][$this->config->get('config_language_id')]) ? $tariff_info['title'][$this->config->get('config_language_id')] : $cdek_tariff_list[$tariff_info['tariff_id']]['title'];

                                if (!empty($tariff_info['customer_group_id']) && !in_array($customer_group_id, $tariff_info['customer_group_id'])) continue;

                                $min_weight = (float)$tariff_info['min_weight'];
                                $max_weight = (float)$tariff_info['max_weight'];

                                if (($min_weight > 0 && $weight < $min_weight) || ($max_weight > 0 && $weight > $max_weight)) {

                                    if ($this->config->get('shipping_cdek_log')) {
                                        $this->log->write('СДЭК: Тариф «' . $tariff_title . '» превышены ограничения по весу!');
                                    }

                                    continue;
                                }

                                $min_total = (float)$tariff_info['min_total'];
                                $max_total = (float)$tariff_info['max_total'];

                                // Check if free shipping should apply
                                $free_shipping_eligible = false;
                                if ($max_total > 0 && $total > $max_total) {
                                    // Check if this tariff has geo_zone restrictions
                                    if (!empty($tariff_info['geo_zone'])) {
                                        // Check if customer's geo zone matches
                                        $intersect = array_intersect($tariff_info['geo_zone'], $geo_zones);
                                        if ($intersect) {
                                            $free_shipping_eligible = true;
                                            if ($this->config->get('shipping_cdek_log')) {
                                                $this->log->write('СДЭК: Тариф «' . $tariff_title . '» - бесплатная доставка (сумма заказа ' . $total . ' превышает ' . $max_total . ')!');
                                            }
                                        }
                                    }
                                }

                                if (($min_total > 0 && $total < $min_total) || ($max_total > 0 && $total > $max_total && !$free_shipping_eligible)) {

                                    if ($this->config->get('shipping_cdek_log')) {
                                        $this->log->write('СДЭК: Тариф «' . $tariff_title . '» превышены ограничения по стоимости!');
                                    }

                                    continue;
                                }

                                if (!empty($tariff_info['geo_zone'])) {

                                    $intersect = array_intersect($tariff_info['geo_zone'], $geo_zones);

                                    if (!$intersect) {
                                        continue;
                                    }

                                } else {
                                    $key = 'all';
                                }

                                // Add free shipping flag to tariff info
                                $tariff_info['free_shipping_eligible'] = $free_shipping_eligible;

                                $tariff_list[$tariff_info['tariff_id']][$key] = $tariff_info;
                            }

                            if (!$tariff_list) {

                                if ($this->config->get('shipping_cdek_log')) {
                                    $this->log->write('СДЭК: Не сформирован список тарифов для текущей географической зоны!');
                                }

                                $status = FALSE;
                            }

                            if ($status) {

                                foreach ($tariff_list as $tariff_id => &$items) {

                                    if (count($items) > 1) {

                                        if (array_key_exists('all', $items)) unset($items['all']);

                                        $sort_order = array();

                                        foreach ($items as $key => $item) {
                                            $sort_order[$key] = $item['sort_order'];
                                        }

                                        array_multisort($sort_order, SORT_ASC, $items);

                                        $items = reset($items);

                                    } elseif (count($items) == 1) {
                                        $items = reset($items);
                                    } else {
                                        continue;
                                    }

                                    if ($this->config->get('shipping_cdek_work_mode') == 'single') {
                                        $calc->addTariffPriority($tariff_id, $items['sort_order']);
                                    }
                                }

                                if ($this->config->get('shipping_cdek_work_mode') == 'single') {

                                    $exTariffList = array();
                                    $exTariffList['dver'] = array();
                                    $exTariffList['sklad'] = array();
                                    $exTariffList['postamat'] = array();

                                    foreach ($tariff_list as $tariff_info) {

                                        $calc->setTariffId($tariff_info['tariff_id']);

                                        if ($result = $this->getResult($calc, $total)) {
                                            if ($tariff_info['mode_id'] == 1 || $tariff_info['mode_id'] == 3) {
                                                $exTariffList['dver'][] = $result;
                                            } elseif ($tariff_info['mode_id'] == 2 || $tariff_info['mode_id'] == 4) {
                                                $exTariffList['sklad'][] = $result;
                                            } else {
                                                $exTariffList['postamat'][] = $result;
                                            }
                                        }

                                    }

                                    $set_dver = false;
                                    foreach ($exTariffList['dver'] as $mtfkey => $mtfvalue) //changed (float)$mtfvalue['priceByCurrency'] for (float)isset($mtfvalue['priceByCurrency'])
                                    {

                                        if (!$set_dver || (float)isset($mtfvalue['priceByCurrency']) < (float)isset($set_dver['priceByCurrency']))
                                            $set_dver = $mtfvalue;
                                    }

                                    $set_sklad = false;
                                    foreach ($exTariffList['sklad'] as $mtfkey => $mtfvalue) {
                                        if (!$set_sklad || (float)isset($mtfvalue['priceByCurrency']) < (float)isset($set_sklad['priceByCurrency']))
                                            $set_sklad = $mtfvalue;
                                    }
                                    $set_postamat = false;
                                    foreach ($exTariffList['postamat'] as $mtfkey => $mtfvalue) {
                                        if (!$set_postamat || (float)isset($mtfvalue['priceByCurrency']) < (float)isset($set_postamat['priceByCurrency']))
                                            $set_postamat = $mtfvalue;
                                    }

                                    if ($set_dver) {
                                        $results[] = $set_dver;
                                    }
                                    if ($set_sklad) {
                                        $results[] = $set_sklad;
                                    }
                                    if ($set_postamat) {
                                        $results[] = $set_postamat;
                                    }

                                } else {

                                    foreach ($tariff_list as $tariff_info) {

                                        $calc->setTariffId($tariff_info['tariff_id']);

                                        if ($result = $this->getResult($calc, $total)) {
                                            $results[] = $result;
                                        }

                                    }

                                }

                                if (!empty($results)) {

                                    // Performance optimization: Skip loading PVZ list during getQuote()
                                    // PVZ data will be loaded via AJAX when customer selects CDEK shipping
                                    // Only store city information in session for later use
                                    $this->session->data['cdek']['city'] = $city_to;
                                    $this->session->data['cdek']['weight'] = $weight;

                                    // Set cache keys for later lazy loading
                                    $this->session->data['cdek']['pvz_cache_key'] = 'cdek_pvz_' . $city_to;
                                    $this->session->data['cdek']['postamat_cache_key'] = 'cdek_postamat_' . $city_to;

                                    if ($this->config->get('shipping_cdek_log')) {
                                        $this->log->write('СДЭК DEBUG: Skipped PVZ loading for performance. City=' . $city_to . ', will load via AJAX later');
                                    }

                                    // For backward compatibility, set flag to skip PVZ check
                                    // PVZ will be loaded later via AJAX
                                    $usePVZ = 0; // Disable PVZ requirement check during getQuote
                                    $pvz_list = array();
                                    $postamat_list = array();

                                    $sub_total = $this->cart->getSubTotal();

                                    foreach ($results as $shipping_info) {

                                        if (array_key_exists($shipping_info['tariffId'], $cdek_tariff_list)) {

                                            $tariff_info = $cdek_tariff_list[$shipping_info['tariffId']];

                                            if (!$this->config->get('shipping_cdek_empty_address') && trim($address['address_1']) == '' && in_array($tariff_info['mode_id'], array(1, 3))) {

                                                if ($this->config->get('shipping_cdek_log')) {
                                                    $this->log->write('СДЭК: пустой адрес доставки для тарифа ' . $shipping_info['tariffId']);
                                                }

                                                continue;
                                            }
                                            if (!empty($shipping_info['total_sum'])) {
                                                $price = $shipping_price = round(($this->config->get('config_currency') == 'RUB') ? $shipping_info['total_sum'] : $this->currency->convert($shipping_info['total_sum'], 'RUB', $this->config->get('config_currency')));
                                            } elseif (!empty($shipping_info['delivery_sum'])) {
                                                $price = $shipping_price = round(($this->config->get('config_currency') == 'RUB') ? $shipping_info['delivery_sum'] : $this->currency->convert($shipping_info['delivery_sum'], 'RUB', $this->config->get('config_currency')));
                                            }

                                            $customer_tariff_info = $tariff_list[$shipping_info['tariffId']];

                                            $discounts = $this->getDiscount($sub_total, $shipping_info['tariffId'], $geo_zones);

                                            foreach ($discounts as $discount_info) {

                                                $markup = (float)$discount_info['value'];

                                                switch ($discount_info['mode']) {
                                                    case 'fixed_weight':
                                                        $markup = $weight * $markup;
                                                        break;
                                                    case 'percent':
                                                        $markup = ($sub_total / 100) * $markup;
                                                        break;
                                                    case 'percent_shipping':
                                                        $markup = ($shipping_price / 100) * $markup;
                                                        break;
                                                    case 'percent_cod':
                                                        $markup = (($sub_total + $price) / 100) * $markup;
                                                        break;
                                                }

                                                $markup = $this->tax->calculate($markup, $discount_info['tax_class_id'], $this->config->get('config_tax'));

                                                if ($discount_info['prefix'] == '+') {
                                                    $price += (float)$markup;
                                                } elseif ($discount_info['prefix'] == '-') {
                                                    $price -= (float)min($markup, $price);
                                                } else {
                                                    $price = (float)$markup;
                                                }

                                            }

                                            // Round to whole rubles (50 kopecks rounds up to 1 ruble)
                                            $price = round($price);

                                            // Apply free shipping if eligible
                                            if (!empty($customer_tariff_info['free_shipping_eligible'])) {
                                                $price = 0;
                                                if ($this->config->get('shipping_cdek_log')) {
                                                    $this->log->write('СДЭК: Применена бесплатная доставка для тарифа ' . $shipping_info['tariffId']);
                                                }
                                            }

                                            if (!empty($customer_tariff_info['title'][$this->config->get('config_language_id')])) {
                                                $description = $customer_tariff_info['title'][$this->config->get('config_language_id')];
                                            } else {
                                                $description = $tariff_info['title'];
                                            }

                                            /*if ($this->config->get('shipping_cdek_period') || (!empty($pvz_list) && in_array($tariff_info['mode_id'], array(2, 4)))) {
                                                $description .= ':';
                                            }*/

                                            if (in_array((string)$shipping_info['tariffId'], $this->getInpostTariffs())) {
                                                $renderData['pvzType'] = 'POSTAMAT';
                                            } else {
                                                $renderData['pvzType'] = 'PVZ';
                                            }

                                            $renderData['period'] = '';

                                            if ($this->config->get('shipping_cdek_period')) {

                                                $period = array_unique(array($shipping_info['period_min'], $shipping_info['period_max']));

                                                if ((float)$this->config->get('shipping_cdek_more_days')) {
                                                    foreach ($period as &$period_item) $period_item += (float)$this->config->get('shipping_cdek_more_days');
                                                }

                                                $renderData['period'] = ' Срок доставки ' . implode('–', $period) . ' ' . $this->declination(max($period), array('день', 'дня', 'дней')) . '.';

                                                //$description .= ' Срок доставки ' . implode('–', $period) . ' ' . $this->declination(max($period), array('день', 'дня', 'дней')) . '.';

                                            }

                                            $renderData['delivery_data'] = '';

                                            if ($this->config->get('shipping_cdek_delivery_data')) {

                                                $period = array_unique(array($shipping_info['period_min'], $shipping_info['period_max']));

                                                if ((float)$this->config->get('shipping_cdek_more_days')) {
                                                    foreach ($period as &$period_item) $period_item += (float)$this->config->get('shipping_cdek_more_days');
                                                }

                                                if (count($period) == 1) {
                                                    $period[0] = $this->formatDate((int)$period[0]);
                                                    $renderData['delivery_data'] = ' Планируемая дата доставки ' . $period[0] . '.';
                                                    //$description .= ' Планируемая дата доставки ' . $period[0] . '.';
                                                } else {
                                                    $period[0] = $this->formatDate((int)$period[0]);
                                                    $period[1] = $this->formatDate((int)$period[1]);
                                                    $renderData['delivery_data'] = ' Планируемая дата доставки с ' . $period[0] . ' по ' . $period[1] . '.';
                                                    //$description .= ' Планируемая дата доставки с ' . $period[0] . ' по ' . $period[1] . '.';
                                                }
                                            }

                                            $names = array();

                                            $renderData['usePvz'] = false;

                                            if (in_array($tariff_info['mode_id'], array(2, 4))) {
                                                if ($usePVZ && !$pvz_list) continue;

                                                if ($usePVZ && $pvz_list) {
                                                    $renderData['usePvz'] = true;
                                                }

                                                if ($this->config->get('shipping_cdek_show_pvz')) {

                                                    if (!empty($pvz_list)) {
                                                        foreach ($pvz_list as $pvz_info) {
                                                            //$names[$pvz_info['code']/* . '_' . $key*/] = $description . ' Пункт выдачи заказов: ' . $pvz_info['address'];
                                                            $names[$pvz_info['code']/* . '_' . $key*/] = $description;
                                                        }
                                                    } else {
                                                        // Lazy loading mode: show option without PVZ details
                                                        $names[] = $description;
                                                        $renderData['usePvz'] = true; // Will load via AJAX
                                                    }

                                                } else {
                                                    $names[] = $description;
                                                }

                                            } else if (in_array($tariff_info['mode_id'], array(6, 7))) {
                                                if ($usePVZ && !$postamat_list) continue;

                                                if ($usePVZ && $postamat_list) {
                                                    $renderData['usePvz'] = true;
                                                }

                                                if ($this->config->get('shipping_cdek_show_pvz')) {

                                                    if (!empty($postamat_list)) {
                                                        foreach ($postamat_list as $pvz_info) {
                                                            //$names[$pvz_info['code']/* . '_' . $key*/] = $description . ' Пункт выдачи заказов: ' . $pvz_info['address'];
                                                            $names[$pvz_info['code']/* . '_' . $key*/] = $description;
                                                        }
                                                    } else {
                                                        // Lazy loading mode: show option without postamat details
                                                        $names[] = $description;
                                                        $renderData['usePvz'] = true; // Will load via AJAX
                                                    }

                                                } else {
                                                    $names[] = $description;
                                                }

                                            } else {
                                                $names[] = $description;
                                            }

                                            $cod = !isset($shipping_info['delivery_sum']) || ((float)$shipping_info['delivery_sum'] && $total >= (float)$shipping_info['delivery_sum']);

                                            if ($this->config->get('shipping_cdek_log')) {
                                                $this->log->write('СДЭК DEBUG: Processing ' . count($names) . ' shipping options for tariff ' . $shipping_info['tariffId']);
                                            }

                                            foreach ($names as $key => $description) {

                                                $code = 'tariff_' . $shipping_info['tariffId'] . '_' . $key;
                                                $renderData['code'] = $code;

                                                $currency = '';

                                                if ($this->session->data['currency']) {
                                                    $currency = $this->session->data['currency'];
                                                }

                                                $extendedDescription = $this->renderTpl($renderData);

                                                $quote_data[$code] = array(
                                                    'code' => 'cdek.' . $code,
                                                    'cod' => $cod,
                                                    'title' => $description,
                                                    'description' => $extendedDescription,
                                                    'cost' => $price,
                                                    'tax_class_id' => $this->config->get('shipping_cdek_tax_class_id'),
                                                    'text' => $this->currency->format($this->tax->calculate($price, $this->config->get('shipping_cdek_tax_class_id'), $this->config->get('config_tax')), $currency)
                                                );

                                            }

                                        }

                                    }

                                } else {

                                    if ($this->config->get('shipping_cdek_log')) {
                                        $this->log->write('СДЭК: нет результатов для вывода!');
                                    }

                                }
                            }
                        }
                    }

                } else {

                    if ($this->config->get('shipping_cdek_log')) {
                        $this->log->write('СДЭК: не определен подходящий город!');
                    }

                }

            } else {

                if ($this->config->get('shipping_cdek_log')) {
                    $this->log->write('СДЭК: город доставки не определен!');
                }

            }

        } else {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: город доставки не найден!');
            }

        }

        $method_data = array();

        $empty_info = $this->config->get('shipping_cdek_empty');

        if (!$quote_data && !empty($empty_info['use'])) {

            if (!empty($empty_info['title'][$this->config->get('config_language_id')])) {
                $title = $empty_info['title'][$this->config->get('config_language_id')];
            } else {
                $title = $this->language->get('text_title');
            }

            if (!empty($empty_info['cost'])) {
                $price = round((float)$empty_info['cost']);
            } else {
                $price = 0;
            }

            $currency = '';
            if ($this->session->data['currency']) {
                $currency = $this->session->data['currency'];
            }

            $quote_data['empty'] = array(
                'code' => 'cdek.empty',
                'title' => $title,
                'cost' => $price,
                'tax_class_id' => $this->config->get('shipping_cdek_tax_class_id'),
                'text' => $this->currency->format($this->tax->calculate($price, $this->config->get('shipping_cdek_tax_class_id'), $this->config->get('config_tax')), $currency)
            );
        }

        if ($quote_data) {

            $title_info = $this->config->get('shipping_cdek_title');

            if (!empty($title_info[$this->config->get('config_language_id')])) {
                $title = $title_info[$this->config->get('config_language_id')];
            } else {
                $title = $this->language->get('text_title');
            }

            $method_data = array(
                'code' => 'cdek',
                'title' => $title,
                'quote' => $quote_data,
                'sort_order' => (int)$this->config->get('shipping_cdek_sort_order'),
                'error' => false
            );
        }

        return $method_data;
    }

    private function getInpostTariffs()
    {
        $list = $this->getInfo()->getInpostTariffs();

        return $list;
    }

    private function renderTpl($data)
    {
        require_once(DIR_APPLICATION . "/controller/extension/shipping/cdek.php");
        $controller = new ControllerExtensionShippingCdek($this->registry);
        $tpl = $controller->renderTpl($data);
        return $tpl;
    }

    private function getResult(CalculatePriceDeliveryCdek &$calc, $total = 0)
    {

        $result = FALSE;

        if ($calc->calculate() === true) {

            $response = $calc->getResult();

            if (!$this->config->get('shipping_cdek_cache_on_delivery') || !array_key_exists('cashOnDelivery', $response) || ($this->config->get('shipping_cdek_cache_on_delivery') && (float)$response['cashOnDelivery'] && $total >= (float)$response['cashOnDelivery'])) {
                $result = $response;
            }

        } else {

            $error = $calc->getError();

            if (isset($error['error']) && !empty($error) && $this->config->get('shipping_cdek_log')) {
                foreach ($error['error'] as $error_info) {
                    $this->log->write('СДЭК: ' . $error_info['text']);
                }
            }

        }

        return $result;
    }

    private function getPVZList($city_id, $weight = 0, $type)
    {

        $pvz_list = array();

        $pvz_list_data = $this->getURL($this->getInfo()->getAjaxUrl() . 'v2/deliverypoints?city_code=' . $city_id . '&type=' . $type, new parser_json());

        if ($this->config->get('shipping_cdek_log')) {
            $this->log->write('СДЭК DEBUG: getPVZList received ' . count($pvz_list_data) . ' PVZ locations for type ' . $type);
        }

        if (isset($pvz_list_data)) {

            $use_limit = $this->config->get('shipping_cdek_weight_limit');
            $full_info = array();
            $processed_count = 0;

            foreach ($pvz_list_data as $pvz_info) {
                $processed_count++;
                if (empty($pvz_info['location']['city']) || empty($pvz_info['location']['address'])) {
                    continue;
                }

                $key = md5($pvz_info['location']['address']);

                if (array_key_exists($key, $pvz_list)) continue;

                if ($use_limit && isset($pvz_info->WeightLimit)) {

                    $min_weight = (float)$pvz_info['weight_min'];
                    $max_weight = (float)$pvz_info['weight_max'];

                    if (($min_weight > 0 && $weight < $min_weight) || ($max_weight > 0 && $weight > $max_weight)) {

                        if ($this->config->get('shipping_cdek_log')) {
                            $this->log->write('СДЭК: превышены ограничения по весу для ПВЗ ' . $pvz_info['name'] . ' по адресу: ' . $pvz_info['location']['address'] . '!');
                        }

                        continue;
                    }

                }

                $pvz_address = 'г. ' . $pvz_info['location']['city'] . ', ' . $this->mb_ucfirst($pvz_info['location']['address']) . '.';

                if (!empty($pvz_info['phones']['number']) && trim($pvz_info['phones']['number']) != '-') {
                    $pvz_address .= ' Телефон: ' . $pvz_info['phones'][0]['number'] . '.';
                }

                $addarray['Code'] = '' . $pvz_info['code'];
                $addarray['CityCode'] = '' . $pvz_info['location']['city_code'];
                $addarray['City'] = '' . $pvz_info['location']['city'];
                $addarray['WorkTime'] = '' . $pvz_info['work_time'];
                $addarray['Address'] = '' . $pvz_info['location']['address'];
                $addarray['Phone'] = '' . $pvz_info['phones'][0]['number'];
                $addarray['coordX'] = '' . $pvz_info['location']['longitude'];
                $addarray['coordY'] = '' . $pvz_info['location']['latitude'];
                $full_info[] = $addarray;

                $pvz_list[$key] = array(
                    'address' => $pvz_address,
                    'code' => (string)$pvz_info['code'],
                    'info' => $full_info
                );
            }

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК DEBUG: Processed ' . $processed_count . ' PVZ entries, creating merged list');
            }

            $pvz_list_tmp = array();

            foreach ($pvz_list as $pvz_info) $pvz_list_tmp[] = $pvz_info['address'];

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК DEBUG: Merging ' . count($pvz_list_tmp) . ' PVZ addresses');
            }

            $pvz_list = array(
                'MRG' => array(
                    'address' => implode('; ', $pvz_list_tmp),
                    'code' => 'MRG',
                    'info' => $full_info
                )
            );

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК DEBUG: getPVZList completed for type ' . $type);
            }

        } elseif (!empty($pvz_list_data['ErrorCode'])) {

            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: В выбранном городе ПВЗ отсутствуют!');
            }

        }

        return $pvz_list;
    }

    private function getDiscount($total, $tariff_Id = 0, $geo_zones = array())
    {

        $discounts = array();

        $cdek_discounts = $this->config->get('shipping_cdek_discounts');

        if (!empty($cdek_discounts)) {

            if ($this->customer->isLogged()) {
                $customer_group_id = $this->customer->getGroupId();
            } else {
                $customer_group_id = $this->config->get('config_customer_group_id');
            }

            foreach ($cdek_discounts as $key => $discount_info) {

                $item_status = TRUE;

                if ((!empty($discount_info['customer_group_id']) && !in_array($customer_group_id, $discount_info['customer_group_id']))) {
                    $item_status = FALSE;
                }

                if (!empty($discount_info['geo_zone'])) {

                    $intersect = array_intersect($discount_info['geo_zone'], $geo_zones);

                    if (!$intersect) {
                        $item_status = FALSE;
                    }

                }

                if (!isset($discount_info['tariff_id'])) $discount_info['tariff_id'] = array();

                if ($item_status && (float)$discount_info['total'] <= $total && (!$discount_info['tariff_id'] || in_array($tariff_Id, $discount_info['tariff_id']))) {
                    $discounts[$discount_info['prefix'] . '_' . $discount_info['mode']] = $discount_info;
                }

            }

        }

        return $discounts;
    }

    private function prepareRegion($name = '')
    {
        $regions = array();

        $parts = explode(' ', $name);
        $parts = array_map(array($this, '_clear'), $parts);

        if (in_array($parts[0], array('московская', 'москва'))) {
            $regions[] = 'москва';
            $regions[] = 'московская';
        } elseif (in_array($parts[0], array('ленинградская', 'санкт-петербург'))) {
            $regions[] = 'санкт-петербург';
            $regions[] = 'ленинградская';
        } elseif (mb_strpos($parts[0], 'респ') === 0) {
            $regions[] = $parts[1];
        } elseif (in_array($parts[0], array('киев', 'киевская'))) { // Украина
            $regions[] = 'киевская';
            $regions[] = 'киев';
        } elseif (in_array($parts[0], array('винница', 'винницкая'))) { // Украина
            $regions[] = 'винница';
            $regions[] = 'винницкая';
        } elseif (in_array($parts[0], array('днепропетровск', 'днепропетровская'))) { // Украина
            $regions[] = 'днепропетровск';
            $regions[] = 'днепропетровская';
        } elseif (in_array($parts[0], array('чувашская'))) {
            $regions[] = 'чувашия';
        } elseif (in_array($parts[0], array('удмуртская'))) {
            $regions[] = 'удмуртия';
        } else {
            $regions = $parts;
        }

        return $regions;
    }

    private function prepareCountry($name = '')
    {

        $countries = array();

        $name = $this->_clear($name);

        if (in_array($name, array('российская федерация', 'россия', 'russia', 'russian', 'russian federation'))) {
            $countries[] = 'россия';
        } elseif (in_array($name, array('украина', 'ukraine'))) {
            $countries[] = 'украина';
        } elseif (in_array($name, array('белоруссия', 'белоруссия (беларусь)', 'беларусь', '(беларусь)', 'belarus'))) {
            $countries[] = 'беларусь';
        } elseif (in_array($name, array('казахстан', 'kazakhstan'))) {
            $countries[] = 'казахстан';
        } elseif (in_array($name, array('сша', 'соединенные штаты америки', 'соединенные штаты', 'usa', 'united states'))) {
            $countries[] = 'сша';
        } elseif (in_array($name, array('aзербайджан', 'azerbaijan'))) {
            $countries[] = 'aзербайджан';
        } elseif (in_array($name, array('узбекистан', 'uzbekistan'))) {
            $countries[] = 'узбекистан';
        } elseif (in_array($name, array('китайская народная республика', 'сhina'))) {
            $countries[] = 'китай (кнр)';
        } else {
            $countries[] = $name;
        }

        return $countries;
    }

    private function ocToCdek($region_name = '')
    {
        
         if ($region_name) {
//            $region_name = str_replace('обл.', 'область', $region_name);
            $region_name = str_replace('АО', 'автономный округ', $region_name);
/*             if (strpos($region_name, 'Республика') !== FALSE) {
                $region_name = str_replace('Республика', '', $region_name);
                $region_name .= " респ.";
            }
 */        }
        
        return trim($region_name);
    }

    private function getWeight()
    {
        $products = $this->cart->getProducts();

        $cdek_default_param = $this->config->get('shipping_cdek_default_param');

//        if ($cdek_default_param['use']) {
//
//            switch ($cdek_default_param['work_mode']) {
//                case 'order':
//                    $weight = $cdek_default_param['weight'];
//                    break;
//                case 'all':
//                case 'optional':
//                    $weight = 0;
//                    foreach ($products as $product) {
//
//                        if ($cdek_default_param['work_mode'] == 'all') {
//                            $weight += $cdek_default_param['weight'];
//                        } else {
//                            if ((float)$product['weight'] > 0) {
//                                $weight += $this->weight->convert($product['weight'], $product['weight_class_id'], $this->weight_class_id);
//                            } else {
//                                $weight += $cdek_default_param['weight'];
//                            }
//                        }
//                    }
//                    break;
//            }
//        } else {
//            $weight = $this->cart->getWeight();
//        }

        $weight = $this->cart->getWeight();

        if ($this->config->get('config_weight_class_id') != $this->weight_class_id) {
            $weight = $this->weight->convert($weight, $this->config->get('config_weight_class_id'), $this->weight_class_id);
        }

        $packing_min_weight = $this->weight->convert((float)$this->config->get('shipping_cdek_packing_min_weight'), $this->config->get('shipping_cdek_packing_weight_class_id'), $this->weight_class_id);
        $packing_value = $this->weight->convert((float)$this->config->get('shipping_cdek_packing_value'), $this->config->get('shipping_cdek_packing_weight_class_id'), $this->weight_class_id);

        if ($packing_value) {

            $packing_weight = 0;

            switch ($this->config->get('shipping_cdek_packing_mode')) {
                case 'fixed':
                    $packing_weight = $packing_value;
                    break;
                case 'all_percent':
                    $packing_weight = ($weight / 100) * $packing_value;
                    break;
            }

            if ($packing_min_weight && $packing_min_weight > $packing_weight) {
                $packing_weight = $packing_min_weight;
            }

            if ($this->config->get('shipping_cdek_packing_prefix') == '+') {
                $weight += $packing_weight;
            } else {
                $weight -= (float)min($packing_weight, $weight);
            }

        } elseif ($packing_min_weight) {
            $weight += $packing_min_weight;
        }

        if ((float)$this->config->get('shipping_cdek_additional_weight')) {
            $weight += (float)$this->config->get('shipping_cdek_additional_weight');
        }
        return $weight;
    }

    private function _normalizeDate($value = '')
    {
        return str_replace('-', '.', $value);
    }

    private function _clear($value)
    {
        $value = mb_convert_case($value, MB_CASE_LOWER, "UTF-8");
        return trim($value);
    }

    private function declination($number, $titles)
    {
        $cases = array(2, 0, 1, 1, 1, 2);
        return $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
    }

    private function getURL($url, response_parser $parser, $data = array())
    {
        $ch = curl_init();
        if ($this->config->get('shipping_cdek_log') && !strpos($url,"city_code=44")) {
            $this->log->write('СДЭК url запроса: ' . $url);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $this->auth_data['access_token'],
                'Content-Type: application/json')
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        if (!empty($data)) {
            if ($this->config->get('shipping_cdek_log') && !strpos($url,"city_code=44")) {
                $this->log->write('СДЭК передаваемые данные: ' . $data);
            }
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $out = curl_exec($ch);
        if ($this->config->get('shipping_cdek_log') && !strpos($url,"city_code=44")) {
            $this->log->write('СДЭК ответ от api: ' . $out);
        }
        $parser->setData($out);

        return $parser->getData();
    }

    private function mb_ucfirst($str, $enc = 'utf-8')
    {
        return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc) . mb_substr($str, 1, mb_strlen($str, $enc), $enc);
    }

    public function getInfo()
    {

        static $instance;

        if (!$instance) {
            $instance = $this->api->loadComponent('info');
        }

        return $instance;
    }

    private function formatDate($period)
    {
        $current_date = date('Y-m-d', time());

        $new_date = new DateTime($current_date);
        $new_date->add(new DateInterval('P' . $period . 'D'));

        $period = $new_date->format('Y-m-d');

        return $period;
    }

    /**
     * Load PVZ data with cache expiry check (lazy loading)
     * This method should be called via AJAX when customer selects CDEK shipping
     *
     * @param string $type 'PVZ' or 'POSTAMAT'
     * @return array PVZ list data
     */
    public function loadPVZData($type = 'PVZ')
    {
        if (!isset($this->session->data['cdek']['city'])) {
            if ($this->config->get('shipping_cdek_log')) {
                $this->log->write('СДЭК: Cannot load PVZ data - city not set in session');
            }
            return array();
        }

        $city_to = $this->session->data['cdek']['city'];
        $weight = isset($this->session->data['cdek']['weight']) ? $this->session->data['cdek']['weight'] : 0;

        $cache_key = 'cdek_' . strtolower($type) . '_' . $city_to;
        $cache_file = DIR_CACHE . $cache_key . '.cache';
        $cache_expiry = 24 * 60 * 60; // 24 hours in seconds

        // Check if cache exists and is still valid
        if (file_exists($cache_file)) {
            $cache_age = time() - filemtime($cache_file);

            if ($cache_age < $cache_expiry) {
                // Cache is valid, load from file
                if ($this->config->get('shipping_cdek_log')) {
                    $this->log->write('СДЭК: Loading ' . $type . ' from cache (age: ' . round($cache_age / 3600, 1) . ' hours)');
                }

                $cached_data = unserialize(file_get_contents($cache_file));
                return $cached_data ? $cached_data : array();
            } else {
                if ($this->config->get('shipping_cdek_log')) {
                    $this->log->write('СДЭК: Cache expired for ' . $type . ' (age: ' . round($cache_age / 3600, 1) . ' hours), reloading...');
                }
            }
        }

        // Cache doesn't exist or expired, fetch from API
        $this->auth_data = $this->getInfo()->getAuthToken();
        $pvz_list = $this->getPVZList($city_to, $weight, $type);

        // Store in cache
        if (!empty($pvz_list)) {
            foreach ($pvz_list as $pvz_list_content) {
                file_put_contents($cache_file, serialize($pvz_list_content['info']));

                if ($this->config->get('shipping_cdek_log')) {
                    $size = strlen(serialize($pvz_list_content['info']));
                    $count = count($pvz_list_content['info']);
                    $this->log->write('СДЭК: Cached ' . $count . ' ' . $type . ' items (' . number_format($size) . ' bytes)');
                }

                return $pvz_list_content['info'];
            }
        }

        return array();
    }

}

abstract class response_parser
{

    protected $data;

    public function setData($data)
    {
        $this->data = $data;
    }

    abstract protected function getData();
}

class parser_json extends response_parser
{

    public function getData()
    {
        return json_decode($this->data, TRUE);
    }

}

class parser_xml extends response_parser
{

    public function getData()
    {
        return (strpos($this->data, '<?xml') === 0) ? new SimpleXMLElement($this->data) : '';
    }

}

?>