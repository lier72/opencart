-- Size Selector Option Mappings for uniqsport.ru
-- Based on actual option values analysis

-- Analysis of current options:
-- Option 11: Размер одежды -> Format: "Euro XXS [Asia (XS)]" -> Use Asian in brackets as source
-- Option 22: Размер женской обуви (US) -> Format: "34 1/3 us(4,5)" -> Use US in parentheses as source
-- Option 23: Размер обуви мужские (US) -> Format: "35 2/3 us(4)" -> Use US in parentheses as source
-- Option 26: Размер детской обуви (US) -> Format: "31 us(0,5)" -> Use US in parentheses as source
-- Option 28: Размер детских ракеток -> Not size-related (racket sizes in cm)
-- Option 29: Размер обуви baby -> Format: "130mm us(7C)" -> Use US in parentheses as source

-- Clear any existing mappings first
DELETE FROM `ocus_j3_size_option_mapping`;

-- 1. Apparel Sizes (Option 11)
-- Format: "Euro XXS [Asia (XS)]" - Asian size is in brackets
-- We'll use Asian as source since that's what's in brackets
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(11, 'unisex', 'apparel', 'Asian', 1);

-- 2. Women's Shoes (Option 22)
-- Format: "34 1/3 us(4,5)" - EU size first, US in parentheses
-- We'll use US as source since that's in parentheses
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(22, 'women', 'shoes', 'US', 1);

-- 3. Men's/Universal Shoes (Option 23)
-- Format: "35 2/3 us(4)" - EU size first, US in parentheses
-- We'll use US as source since that's in parentheses
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(23, 'universal', 'shoes', 'US', 1);

-- 4. Kids Shoes (Option 26)
-- Format: "31 us(0,5)" - EU size first, US in parentheses
-- We'll use US as source since that's in parentheses
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(26, 'unisex', 'shoes', 'US', 1);

-- 5. Baby Shoes (Option 29)
-- Format: "130mm us(7C)" - mm first, US in parentheses
-- We'll use US as source since that's in parentheses
INSERT INTO `ocus_j3_size_option_mapping`
(`option_id`, `gender`, `size_type`, `source_system`, `enabled`)
VALUES
(29, 'unisex', 'shoes', 'US', 1);

-- Note: Options 24, 25, 27, 28 are not size-related and don't need mappings:
-- 24: Цветовая гамма (colors)
-- 25: Натяжка (string tension)
-- 27: Тип ракетки (racket type)
-- 28: Размер детских ракеток (racket sizes in cm, not clothing/shoe sizes)

-- Verify mappings
SELECT
    m.option_id,
    od.name as option_name,
    m.gender,
    m.size_type,
    m.source_system,
    m.enabled,
    COUNT(ov.option_value_id) as num_values
FROM `ocus_j3_size_option_mapping` m
LEFT JOIN `ocus_option_description` od ON m.option_id = od.option_id AND od.language_id = 1
LEFT JOIN `ocus_option_value` ov ON m.option_id = ov.option_id
GROUP BY m.option_id
ORDER BY m.option_id;
