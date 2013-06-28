<?php
/*/================================================\*\
|*|                                                |*|
|*|      Xavux Dedicated Store - Version 2.0.0     |*|
|*|                                                |*|
|*| This software is provided to you by Xavux LLC. |*|
|*| Please, refer to the license file that came    |*|
|*| along with your distribution of XDS for more   |*|
|*| information about the software's usage terms.  |*|
\*\================================================/*/

##[[ SITE CONFIGURATION BLOCK ]]##

$_CONFIG['site']['title']			= 'Xavux Dedicated Store';
$_CONFIG['site']['currency']		= '$';
$_CONFIG['site']['admins']			= array();

##[[ PAYPAL CONFIGURATION BLOCK ]]##

$_CONFIG['paypal']['sandbox']		= true;
$_CONFIG['paypal']['business']		= 'some@email.com';
$_CONFIG['paypal']['currency_code']	= 'USD';
$_CONFIG['paypal']['lc']			= 'en';

##[[ DATABASE CONFIGURATION BLOCK ]]##

$_CONFIG['database']['engine']		= 'sqlite';
//MySQL config
$_CONFIG['database']['host']		= 'localhost';
$_CONFIG['database']['port']		= '3306';
$_CONFIG['database']['username']	= 'root';
$_CONFIG['database']['password']	= '';
$_CONFIG['database']['database']	= 'store';
$_CONFIG['database']['prefix']		= 'store_';
$_CONFIG['database']['charset']		= 'utf8_general_ci';
//SQLite config
$_CONFIG['database']['file']		= null;