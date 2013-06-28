<?php
/*--------------------------------
 XDS Version Alpha
 Xavux Software LLC.
 Do not redistribute without the
 author's permission.
 ---------------------------------
 Main file
 --------------------------------*/

define('STORE_ROOT_PATH',dirname(__FILE__));
define('STORE_INCLUDES_PATH',STORE_ROOT_PATH . '/includes');

require STORE_INCLUDES_PATH . '/class.core.php';
require STORE_INCLUDES_PATH . '/config.inc.php';

try{$_STORE = new Store();}catch(PDOException $e){die("Database setup invalid, failed with error: ".$e->getMessage());}

try{
	$query = $_STORE->db->query("SELECT 1 FROM {$_STORE->prefix}{$_STORE->table_users};");
	$_STORE->createTables(false);
}catch(PDOException $e){
	if(strpos($e->getMessage(),'no such table')){
		$_STORE->createTables(true);
	}else die('MySQL error which has no known solution! '.$e->getMessage());
}
die('Delete this installation file');