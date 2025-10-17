<?php
// Check if the current PHP version is 8.2 or higher
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    // Suppress deprecation warnings only for this version and above
	echo 'Inside the IF Current PHP version: ' . PHP_VERSION . "\n";
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Version
define('VERSION', '3.0.3.6');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: install/index.php');
	exit;
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('catalog');