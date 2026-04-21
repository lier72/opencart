<?php
define('VERSION', '3.0.3.6');

if (is_file('config.php')) {
	require_once('config.php');
}

if (!defined('DIR_APPLICATION')) {
	exit('Error: Could not load configuration. Make sure config.php exists.');
}

$_SERVER['SERVER_PORT'] = 443;

require_once(DIR_SYSTEM . 'startup.php');

start('reviewrequestcron');
