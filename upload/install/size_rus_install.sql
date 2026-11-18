-- RUS-размеры обуви: установка
-- Префикс таблиц: ocus_ (как на uniqsport.ru). Запуск:
--   mysql -u USER -p a1627-unqs-oc3 < install/size_rus_install.sql

-- 1. События (повторный запуск безопасен)
DELETE FROM `ocus_event` WHERE `code` LIKE 'size_rus_%';

INSERT INTO `ocus_event` (`code`, `trigger`, `action`, `status`, `sort_order`) VALUES
('size_rus_order_label',  'catalog/model/checkout/order/addOrderHistory/before', 'event/size_rus/orderLabel',  1, -10),
('size_rus_order_filter', 'catalog/model/checkout/order/addOrderHistory/after',  'event/size_rus/orderFilter', 1, 0),
('size_rus_product_edit', 'admin/model/catalog/product/editProduct/after',       'event/size_rus/productEdit', 1, 0),
('size_rus_product_add',  'admin/model/catalog/product/addProduct/after',        'event/size_rus/productAdd',  1, 0);

-- 2. RUS — система по умолчанию в виджете размеров
INSERT INTO `ocus_j3_size_selector_settings` (`setting_key`, `setting_value`) VALUES ('default_size_system', 'RUS')
ON DUPLICATE KEY UPDATE `setting_value` = 'RUS';

-- 3. Группа фильтров «Размер (RUS)» и её значения создаются автоматически
--    при первом запуске: php cli/sync_size_filter.php
