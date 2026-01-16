# Product Family Module for OpenCart 3 + Journal3

## Overview

The Product Family module displays related products (variants) on the product page. It groups products that share the same model attribute but have different variant attributes (like color, size, weight, etc.).

### Features

- **Automatic grouping** by model attribute
- **Configurable variant attributes** (Color, Size, Weight, etc.)
- **Visual product cards** with images and prices
- **Stock status indication** (out of stock products are shown but disabled)
- **Responsive design** that works on mobile and desktop
- **Journal3 theme integration** - displays before the options section
- **Admin configuration** panel for easy setup

## Installation

### Step 1: Install the Module

All files are already in place in the correct directories:

- **Admin Controller**: `admin/controller/extension/module/product_family.php`
- **Admin View**: `admin/view/template/extension/module/product_family.twig`
- **Admin Languages**: `admin/language/*/extension/module/product_family.php`
- **Catalog Controller**: `catalog/controller/extension/module/product_family.php`
- **Catalog Model**: `catalog/model/extension/module/product_family.php`
- **Catalog View**: `catalog/view/theme/journal3/template/extension/module/product_family.twig`
- **Catalog Languages**: `catalog/language/*/extension/module/product_family.php`

### Step 2: Install Extension in Admin

1. Go to **Admin Panel** → **Extensions** → **Extensions**
2. Filter by **Modules**
3. Find **Product Family** in the list
4. Click **Install** (green + button)

### Step 3: Configure the Module

1. After installation, click **Edit** (blue pencil icon)
2. Configure the following settings:

   **Status**: Enable the module

   **Model Attribute**: Select the attribute that represents the product model
   - Example: "Модель" from "Общий" attribute group
   - This attribute should have the same value for all products in the same family

   **Variant Attributes**: Check the attributes that differentiate variants
   - Example: Цвет (Color), Размер (Size), Вес (Weight), Размер ручки (Grip Size)
   - These will be displayed as the distinguishing features of each variant

   **Show Image**: Enable to show product images in the variant list

   **Show Price**: Enable to show prices for each variant

   **Image Width/Height**: Set dimensions for variant images (default: 60x60px)

   **Strict Mode**: Enable to use product.model field instead of attributes for grouping
   - When enabled, products are grouped by their model base (removes -1, -2, etc.)
   - Example: AWSU076-1 and AWSU076-2 both have model base "AWSU076"

   **Strict Mode Categories**: Select categories where strict mode should be used
   - Only products in these categories will use model-based grouping
   - Other categories will use attribute-based grouping

3. Click **Save**

## Two Grouping Modes

The module supports two different modes for grouping products into families:

### Mode 1: Attribute-Based Grouping (Default)

Products are grouped by a shared attribute value (e.g., "Модель" attribute).

**When to use:**
- When you have well-structured product attributes
- When the model identifier is stored as an attribute
- For complex product variants with multiple distinguishing factors

**Example:**
- Product 1: Attribute "Модель" = "Astrox 99 Pro"
- Product 2: Attribute "Модель" = "Astrox 99 Pro"
- Product 3: Attribute "Модель" = "Astrox 99 Pro"

### Mode 2: Model Field-Based Grouping (Strict Mode)

Products are grouped by their `product.model` field, with variant suffixes removed.

**When to use:**
- When your products use a naming pattern like: BASE-VARIANT (e.g., AWSU076-1, AWSU076-2)
- For simple product families where the model field already contains grouping info
- When you don't want to maintain separate attributes

**How it works:**
1. Takes the current product's model field (e.g., "AWSU076-1")
2. Removes the variant suffix using pattern: `preg_replace('/-\d{1,2}$/', '', $model)`
3. Finds all products matching the base model (AWSU076-1, AWSU076-2, etc.)

**Example:**
- Product 1: model = "AWSU076-1"  → base = "AWSU076"
- Product 2: model = "AWSU076-2"  → base = "AWSU076"
- Product 3: model = "AWSU076-12" → base = "AWSU076"
- All three products will be grouped together

**Configuration:**
1. Enable "Strict Mode" in module settings
2. Select specific categories where this mode should apply
3. Products in selected categories use model-based grouping
4. Products in other categories use attribute-based grouping

## Usage

### Setting Up Product Attributes

For the module to work, products must have the correct attributes set:

#### 1. Create or Use Attribute Groups

Go to **Catalog** → **Attributes** → **Attribute Groups**

Create or use an existing group, for example:
- Name: **Общий** (General)

#### 2. Create Attributes

Go to **Catalog** → **Attributes** → **Attributes**

Create the following attributes in the "Общий" group:
- **Модель** (model_name) - the common model identifier
- **Цвет** (Color) - for color variants
- **Размер** (Size) - for size variants
- **Вес** (Weight) - for weight variants
- **Размер ручки** (Grip Size) - for racket grip sizes
- etc.

#### 3. Assign Attributes to Products

For each product in a family:

1. Go to **Catalog** → **Products** → Edit Product
2. Go to the **Attribute** tab
3. Add the model attribute with the SAME value for all family members
   - Example: For socks AWSU076-1 and AWSU076-2, both should have:
     - Модель: **AWSU076**
4. Add variant attributes with DIFFERENT values
   - Example: AWSU076-1 has Цвет: **Черный**
   - Example: AWSU076-2 has Цвет: **Белый**

### Example: Socks Family

**Product 1**: AWSU076-1 (Black Socks)
- Модель: AWSU076
- Цвет: Черный
- Размер: M

**Product 2**: AWSU076-2 (White Socks)
- Модель: AWSU076
- Цвет: Белый
- Размер: M

**Product 3**: AWSU076-3 (Red Socks)
- Модель: AWSU076
- Цвет: Красный
- Размер: L

When viewing AWSU076-1, the module will show AWSU076-2 and AWSU076-3 as "Other Variants".

### Example: Badminton Racket Family

**Product 1**: Astrox 99 Pro (3U G5)
- Модель: Astrox 99 Pro
- Вес: 3U (85-89g)
- Размер ручки: G5
- Цвет: Cherry Sunburst

**Product 2**: Astrox 99 Pro (4U G5)
- Модель: Astrox 99 Pro
- Вес: 4U (80-84g)
- Размер ручки: G5
- Цвет: Cherry Sunburst

**Product 3**: Astrox 99 Pro (3U G6)
- Модель: Astrox 99 Pro
- Вес: 3U (85-89g)
- Размер ручки: G6
- Цвет: Cherry Sunburst

When viewing any Astrox 99 Pro variant, all other variants will be shown with their weight and grip size.

## How It Works

1. When a customer views a product page, the module:
   - Finds the product's "Модель" attribute value
   - Searches for all other products with the same "Модель" value
   - Displays them as clickable variant cards

2. Each variant card shows:
   - Product image (if enabled)
   - Variant attributes (Цвет, Размер, etc.)
   - Product model/SKU
   - Price (if enabled)
   - Stock status

3. Out-of-stock variants:
   - Are still shown for visibility
   - Have reduced opacity
   - Are not clickable
   - Display "Нет в наличии" (Out of Stock) message

## Display Location

The module appears on the product page:
- **Location**: Right before the product options section
- **Position**: Between the price section and options
- **Template Integration**: Automatically inserted in `product/product.twig`

## CLI Tool: Adding Model Names to Products

A CLI tool is included to automatically parse and add the "Модель" attribute to existing products:

### Usage

```bash
# Process a single product
php cli/add_model_name_attribute.php --product_id=123

# Process all products in Yandex Market configured categories
php cli/add_model_name_attribute.php --all

# Test without saving (dry run)
php cli/add_model_name_attribute.php --all --dry-run

# Get help
php cli/add_model_name_attribute.php --help
```

### How the CLI Tool Works

The tool uses the same parsing logic as the Yandex Market feed:
1. Parses product names to extract the model
2. Detects vendor names (Yonex, Li-NING, RSL, etc.)
3. Extracts capitalized model names (Astrox, Falcon, AXForce, etc.)
4. Automatically creates the "Модель" attribute if it doesn't exist
5. Adds the parsed model value to each product

### Before Running the CLI Tool

1. Make sure you have configured categories in **Extensions** → **Feeds** → **Yandex Market**
2. The tool will only process products in those configured categories
3. Backup your database before running on all products

## Troubleshooting

### Module Doesn't Show

- Check if the module is **Enabled** in settings
- Verify that products have the "Модель" attribute with matching values
- Ensure at least 2 products share the same "Модель" value
- Check that variant attributes are configured in module settings

### No Variants Appearing

**For Attribute Mode:**
- Verify that both products have the same value for the "Модель" attribute (exact match)
- Check that the products have Status = Enabled
- Ensure products are not identical (they should have different variant attributes)

**For Strict Mode:**
- Check that the product is in a category selected for strict mode
- Verify that product model fields follow the pattern: BASE-NUMBER (e.g., AWSU076-1)
- Ensure the base model (after removing -1, -2) is the same for all variants
- Example: AWSU076-1 and AWSU076-2 will group, but AWSU076-1 and AWSU077-1 won't

### Styling Issues

- The module uses inline CSS in the template
- You can customize styling in `catalog/view/theme/journal3/template/extension/module/product_family.twig`
- Styles are responsive and work on mobile devices

## Customization

### Changing the Display

Edit `catalog/view/theme/journal3/template/extension/module/product_family.twig`:

- Modify the HTML structure
- Change CSS styles (in the `<style>` block)
- Add or remove displayed information
- Adjust responsive breakpoints

### Changing the Logic

Edit `catalog/model/extension/module/product_family.php`:

- Modify the SQL query for finding family members
- Change how variant attributes are fetched
- Add additional filtering or sorting logic

### Moving the Display Position

Edit `catalog/view/theme/journal3/template/product/product.twig`:

- Find the `{# === PRODUCT FAMILY MODULE === #}` section
- Move it to a different location in the template
- Current position: line ~302 (before options section)

## Technical Notes

- **OpenCart Version**: 3.0.3.6
- **Theme**: Journal3
- **Database Tables Used**:
  - `ocus_product_attribute`
  - `ocus_attribute`
  - `ocus_attribute_description`
  - `ocus_product`
- **Language Support**: Russian (ru-ru) and English (en-gb)

## Support

For issues or questions:
1. Check that all files are in the correct directories
2. Verify module settings in admin panel
3. Check product attributes are set correctly
4. Review browser console for JavaScript errors
5. Check OpenCart error logs in `storage/logs/`

## File Checklist

Admin Files:
- ✓ `admin/controller/extension/module/product_family.php`
- ✓ `admin/view/template/extension/module/product_family.twig`
- ✓ `admin/language/ru-ru/extension/module/product_family.php`
- ✓ `admin/language/en-gb/extension/module/product_family.php`

Catalog Files:
- ✓ `catalog/controller/extension/module/product_family.php`
- ✓ `catalog/model/extension/module/product_family.php`
- ✓ `catalog/view/theme/journal3/template/extension/module/product_family.twig`
- ✓ `catalog/language/ru-ru/extension/module/product_family.php`
- ✓ `catalog/language/en-gb/extension/module/product_family.php`

Modified Files:
- ✓ `catalog/controller/product/product.php` (line 456-457: loads module)
- ✓ `catalog/view/theme/journal3/template/product/product.twig` (line 302-306: displays module)

CLI Tool:
- ✓ `cli/add_model_name_attribute.php`

---

**Version**: 1.0
**Author**: Max Surdu
**Date**: 2025-12-15
