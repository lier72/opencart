<?php
$_['site_url']          = HTTP_SERVER;
$_['site_ssl']          = HTTPS_SERVER;

$_['db_autostart']      = true;
$_['db_engine']         = DB_DRIVER;
$_['db_hostname']       = DB_HOSTNAME;
$_['db_username']       = DB_USERNAME;
$_['db_password']       = DB_PASSWORD;
$_['db_database']       = DB_DATABASE;
$_['db_port']           = DB_PORT;

$_['session_autostart'] = false;
$_['url_autostart']     = true;

$_['action_pre_action'] = array(
	'startup/startup',
	'startup/error',
	'startup/event'
);

$_['action_default'] = 'extension/module/review_request/cron';
$_['action_router']  = 'startup/router';
$_['action_error']   = 'error/not_found';
