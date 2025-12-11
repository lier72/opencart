# Color Detector Module for OpenCart 3.x

## Overview

The Color Detector module automatically detects and adds color attributes to products in selected categories. It uses two detection methods:

1. **Product Name Analysis** - Searches for color keywords in product names
2. **Image Color Detection** - Analyzes product images to detect dominant colors

## Features

- ✅ Automatic color detection from product names and images
- ✅ Customizable color mapping table with keyword matching
- ✅ Support for multiple languages (Russian and English)
- ✅ HEX color code storage alongside color names
- ✅ Category-based filtering
- ✅ Force update or skip existing colors option
- ✅ Visual color picker in admin interface
- ✅ Pre-populated with common colors in Russian and English

## Installation

1. Upload all files to your OpenCart installation directory
2. Go to **Extensions → Extensions → Modules**
3. Find "Color Detector" in the list
4. Click the **Install** button (green plus icon)
5. Click the **Edit** button (blue pencil icon) to configure

## Configuration

### Step 1: Create Color Attribute

Before using the module, you need to create a color attribute:

1. Go to **Catalog → Attributes → Attributes**
2. Click **Add New**
3. Create an attribute named "Color" or "Цвет"
4. Save the attribute

### Step 2: Module Settings

1. **Status**: Enable/Disable the module
2. **Color Attribute**: Select the attribute you created for color
3. **Categories**: Select one or more categories to process
4. **Force Update**:
   - **Yes**: Update color even if product already has color attribute
   - **No**: Skip products that already have color attribute

### Step 3: Color Mapping

Click the **Manage Color Mapping** button to:

- View existing color mappings
- Add new color mappings
- Edit existing mappings
- Delete unwanted mappings

## Color Mapping Table

Each color mapping consists of:

- **Keyword**: Text to search for in product names (e.g., "черн", "black", "красн")
- **Color Name (Russian)**: Display name in Russian (e.g., "Черный")
- **Color Name (English)**: Display name in English (e.g., "Black")
- **HEX Code**: Color code in hexadecimal format (e.g., #000000)
- **Sort Order**: Display order in the list

### Default Colors Included

The module comes pre-configured with 18 common colors:

| Keyword | Russian | English | HEX Code |
|---------|---------|---------|----------|
| черн | Черный | Black | #000000 |
| бел | Белый | White | #FFFFFF |
| красн | Красный | Red | #FF0000 |
| син | Синий | Blue | #0000FF |
| зелен | Зеленый | Green | #00FF00 |
| желт | Желтый | Yellow | #FFFF00 |
| оранж | Оранжевый | Orange | #FFA500 |
| роз | Розовый | Pink | #FFC0CB |
| фиолет | Фиолетовый | Purple | #800080 |
| коричнев | Коричневый | Brown | #A52A2A |
| сер | Серый | Gray | #808080 |
| бежев | Бежевый | Beige | #F5F5DC |
| бордов | Бордовый | Burgundy | #800020 |
| голуб | Голубой | Light Blue | #87CEEB |
| салатов | Салатовый | Lime | #00FF00 |
| хаки | Хаки | Khaki | #F0E68C |
| navy | Темно-синий | Navy | #000080 |
| maroon | Темно-красный | Maroon | #800000 |

## How It Works

### Detection Process

1. **Name Detection** (Priority 1):
   - The module searches product names for color keywords
   - Keywords are matched using case-insensitive substring search
   - First matching keyword wins

2. **Image Detection** (Priority 2):
   - If no color found in name, analyzes product's main image
   - Extracts dominant color using GD library
   - Matches extracted color to closest predefined color in mapping table
   - Uses Euclidean distance algorithm in RGB color space

3. **Attribute Storage**:
   - Stores color as: `Color Name (HEX_CODE)`
   - Example: `Черный (#000000)` or `Red (#FF0000)`
   - Created for all installed languages

### Processing Products

1. Click the **Process Products** button
2. The module will:
   - Loop through all products in selected categories
   - Check if product already has color (if force=no)
   - Detect color from name or image
   - Add/update color attribute
3. Shows summary: Total processed, Updated, Skipped

## Technical Details

### Database Table

Table: `ocus_color_mapping`

```sql
CREATE TABLE `ocus_color_mapping` (
  `color_id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `color_name_ru` varchar(100) NOT NULL,
  `color_name_en` varchar(100) NOT NULL,
  `hex_code` varchar(7) NOT NULL,
  `sort_order` int(3) NOT NULL DEFAULT '0',
  PRIMARY KEY (`color_id`),
  KEY `keyword` (`keyword`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```

### Files Structure

```
admin/
  controller/extension/module/color_detector.php
  model/extension/module/color_detector.php
  view/template/extension/module/color_detector.twig
  view/template/extension/module/color_detector_mapping.twig
  language/en-gb/extension/module/color_detector.php
  language/ru-ru/extension/module/color_detector.php
```

### Requirements

- OpenCart 3.0+
- PHP GD extension (for image color detection)
- MySQL/MariaDB

## Usage Examples

### Example 1: Product Name Detection

Product name: "Кроссовки Nike Air Max черные размер 42"

- Module finds keyword "черн" in the name
- Adds attribute: `Черный (#000000)`

### Example 2: Image Detection

Product name: "Running Shoes Pro"
Product has image with dominant blue color

- Module analyzes image
- Detects dominant blue color
- Finds closest match in color mapping
- Adds attribute: `Blue (#0000FF)` or `Синий (#0000FF)`

### Example 3: Force Update

Scenario: Products already have incorrect colors

1. Set **Force Update** to **Yes**
2. Click **Process Products**
3. All products will be re-evaluated and colors updated

## Troubleshooting

### Colors Not Being Detected

1. **Check Attribute Selection**: Ensure you've selected the correct color attribute
2. **Check Categories**: Verify products are in selected categories
3. **Check Keywords**: Add more keyword variations in color mapping
4. **Check GD Extension**: For image detection, ensure PHP GD is installed

### Image Detection Not Working

- Verify PHP GD extension is installed: `php -m | grep -i gd`
- Check image file exists and is readable
- Supported formats: JPEG, PNG, GIF

### Permission Errors

- Ensure your admin user has permission to modify modules
- Check file/folder permissions on server

## Best Practices

1. **Test First**: Start with one category to test the module
2. **Review Mappings**: Add keywords specific to your product names
3. **Use Force Carefully**: Force update overwrites existing colors
4. **Backup**: Always backup database before bulk operations
5. **Keywords**: Use word stems (e.g., "черн" matches "черный", "черная", "черное")

## Support

For issues or questions:
- Check OpenCart logs: `system/storage/logs/error.log`
- Verify all files are uploaded correctly
- Test with a small number of products first

## Uninstallation

1. Go to **Extensions → Extensions → Modules**
2. Find "Color Detector"
3. Click **Uninstall** (red minus icon)
4. This will remove the `ocus_color_mapping` table and all color mappings

**Note**: Color attributes already added to products will NOT be removed during uninstallation.

## Version

Version: 1.0.0
Compatible with: OpenCart 3.0.x
