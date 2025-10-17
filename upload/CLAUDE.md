# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an OpenCart 3.0.3.6 e-commerce platform with extensive customizations including Journal3 theme, CDEK shipping integration, and various payment modules. The site appears to be for "uniqsport.ru", a sports equipment retailer.

## Development Environment Setup

**Local Development Server:**
- Uses XAMPP/MAMP local server setup
- Base URL: `http://localhost/~max/oc3.uniqsport.ru/`
- Admin URL: `http://localhost/~max/oc3.uniqsport.ru/admin/`

**Database Configuration:**
- MySQL database: `a1627-unqs-oc3`
- Host: 127.0.0.1:3306
- Username: root (no password in local environment)
- Table prefix: `ocus_`

## Architecture

**MVC Structure:**
OpenCart follows a standard MVC pattern with separate admin and catalog applications:

- `/admin/` - Administrative interface
- `/catalog/` - Frontend customer interface  
- `/system/` - Core framework files
- `/system/engine/` - Core MVC components (Controller, Model, Router, etc.)

**Key Framework Files:**
- `system/startup.php` - Bootstrap and autoloader
- `system/framework.php` - Registry setup and error handling
- `config.php` / `admin/config.php` - Environment configuration

**Directory Structure:**
- Controllers: `*/controller/`
- Models: `*/model/` 
- Views: `*/view/template/`
- Languages: `*/language/`

## Major Extensions & Customizations

**Journal3 Theme:**
- Premium OpenCart theme with extensive customization options
- Theme files in `/catalog/view/theme/journal3/`
- Admin configuration in Journal3 controllers/models
- Heavy use of Twig templating engine

**CDEK Integration:**
- Russian shipping provider integration
- Files: `*cdek*`, `*CDEK*` pattern
- Cron job: `admin/cdek_integrator_cron.php`

**CSV Price Pro:**
- Bulk product import/export functionality
- CLI tool: `cli/csvprice_pro_cli.php`
- Admin interface in csvprice_pro controllers

**Payment Modules:**
- Yandex Payment systems (yandexplusplus, yandexplusplus_card)
- RBS payment gateway
- Liqpay, PayPal integrations
- Cash on delivery (COD) with CDEK integration

## Common Tasks

**Product Management:**
- Products managed through admin/controller/catalog/product.php
- Categories: admin/controller/catalog/category.php
- Reviews: admin/controller/catalog/review.php

**Order Processing:**
- Orders: admin/controller/sale/order.php
- Returns: admin/controller/sale/return.php

**Theme Customization:**
- Journal3 settings: admin/controller/journal3/
- Module management: admin/controller/extension/module/

**Extension Management:**
- Module installation: admin/controller/extension/
- Marketplace integration available

## Important File Locations

**Configuration:**
- Main config: `/config.php`
- Admin config: `/admin/config.php`
- Storage directory: `/Users/max/Sites/storage/` (outside web root)

**Logs & Cache:**
- Error logs: Storage directory + `/logs/`
- Cache: Storage directory + `/cache/`
- Sessions: Storage directory + `/session/`

**Modifications:**
- OCMOD system for extensions
- Modification files: Storage directory + `/modification/`
- System modifications applied through admin interface

## Development Notes

**PHP Requirements:**
- Minimum PHP 7.3+ (checked in startup.php)
- Uses mysqli database driver
- Error reporting enabled in development

**Vendor Dependencies:**
- Composer autoloader in `/system/storage/vendor/`
- Multiple payment/shipping vendor libraries included

**SEO & Multilingual:**
- Russian (ru-ru) and English (en-gb) language support
- SEO URL system with friendly URLs
- Yandex Market integration for product feeds

**Security:**
- Admin area password protected
- API system for external integrations
- GDPR compliance features included
- total_amount is formed like this