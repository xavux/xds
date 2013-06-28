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

##[[ INITIALIZATION BLOCK ]]##

//error_reporting(0);

if(!defined('STORE_ROOT_PATH')) die('Store root path is not defined');
if(!defined('STORE_INCLUDES_PATH')) define('STORE_INCLUDES_PATH',dirname(__FILE__));

##[[ CONFIGURATION BLOCK ]]##

$_CONFIG = isset($_CONFIG) ? $_CONFIG : array();
$_CONFIG['paypal'] = array(
	'business'		=> '--merchant email required--',
	'currency_code'	=> 'USD');
$_CONFIG['database'] = array(
	'engine'		=> 'sqlite',
	'prefix'		=> '');

##[[ CORE CLASS ]]##

class Store {
	public $db;
	public $prefix;
	public $autoincrement;
	public $conf;

	public $table_users		= 'users';
	public $table_packages	= 'packages';
	public $table_servers	= 'servers';
	public $table_purchases	= 'purchases';
	public $table_active	= 'active';
	public $table_queue		= 'queue';

	const STEAM_LOGIN		= 'https://steamcommunity.com/openid/login';

	function __construct() {
		global $_CONFIG;
		Typehint::initializeHandler();

		$this->conf = $_CONFIG;

		$dbconf = $this->conf['database'];
		$user=null; $pass=null;
		switch($dbconf['engine']){
			case 'mysql':
				$dsn = $this->generateDsn('mysql',array(
					'dbname' => $dbconf['database'],
					'host' => $dbconf['host'],
					'port' => $dbconf['port'],
					'charset' => $dbconf['charset']
					));
				$user = $dbconf['username']; $pass = $dbconf['password'];
				$this->autoincrement = "AUTO_INCREMENT";
				break;
			case 'pgsql':
				$dsn = $this->generateDsn('pgsql',array(
					'dbname' => $dbconf['database'],
					'host' => $dbconf['host'],
					'port' => $dbconf['port'],
					'user' => $dbconf['username'],
					'password' => $dbconf['password'],
					));
				$user = null; $pass = null;
				$this->autoincrement = "AUTO_INCREMENT";
				break;
			case 'sqlite':
				if(empty($dbconf['file'])){
					$dbconf['file'] = STORE_INCLUDES_PATH . '/store.db';
				}
				if(!file_exists($dbconf['file'])){
					$create = touch($dbconf['file']);
					if(!$create) throw new Exception("Could not create sqlite database file");
				}
				$dsn = $dbconf['engine'].':';
				$dsn.= $dbconf['file'];
				$user = null; $pass = null;
				$this->autoincrement = "AUTOINCREMENT";
				break;
			default:
				throw new Exception('Unsupported database engine');
				break;
		}
		$this->prefix = $dbconf['prefix'];
		$this->db = new PDO($dsn,$user,$pass,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

		$this->PayPal = new PayPal();
		foreach($_CONFIG['paypal'] as $field=>$value){$this->PayPal->addField($field,$value);}
	}

	//Utility functions
	function appendPackage($user, $package) {
		$query = $this->selectPackages(array('pid','duration','sid'),array('pid'=>$package));
		if($query===false or !count($query)) return false;
		$package = $query['0'];

		$user = (string) $user;

		$this->refreshQueue();

		$query = $this->selectActive(array('expire'),array('pid'=>$package['pid'],'sid'=>$package['sid']));
		if($query===false) return false;
		if(count($query)){
			$query = $this->updateActive(array('expire'=>date('Y-m-h',strtotime($query['0']['expire'].'+'.$package['duration']))));
		}else{
			$_expire = date('Y-m-h',strtotime(date('Y-m-h').'+'.$package['duration']));
			$query = $this->insertActive(array('pid'=>$package['pid'],'sid'=>$package['sid'],'uid'=>$user,'permanent'=>(($package['duration']<=0)?'1':'0'),'expire'=>$_expire));
			if($query===false) return false;
			$query = $this->insertQueue(array('action'=>'apply','pid'=>$package['pid'],'sid'=>$package['sid'],'uid'=>$user));
			if($query===false) return false;
		}
		return true;
	}
	function cleanGarbage(){
		//Round 1
		$query = $this->selectServers(array('sid'));
		if($query===false) return false;
		try{
			$transaction = $this->db->beginTransaction();
			if($transaction===false) throw new Exception('');
			$sids = array(); foreach($query as $server){$sids[] = $server['sid'];}
			$query = $this->deletePackages(array('!sid'=>$sids));
			if($query===false) throw new Exception('');
			$commit = $this->db->commit();
			if($commit===false) throw new Exception('');
		}catch(Exception $e){
			$this->db->rollBack();
			return false;
		}
		//Round 2
		$query = $this->selectPackages(array('pid'));
		if($query===false) return false;
		try{
			$transaction = $this->db->beginTransaction();
			if($transaction===false) throw new Exception('');
			$pids = array(); foreach($query as $package){$pids[] = $package['pid'];}
			$query = $this->updateActive(array('permanent'=>'0','expire'=>date('Y-m-h',strtotime('-1 month'))),array('!pid'=>$pids));
			if($query===false) throw new Exception('');
			$commit = $this->db->commit();
			if($commit===false) throw new Exception('');
		}catch(Exception $e){
			$this->db->rollBack();
			return false;
		}
		//Round 3
		$query = $this->db->query("SELECT uid,pid,sid FROM `{$this->prefix}{$this->table_active}` WHERE permanent = '0' AND expire <= CURRENT_DATE;");
		if($query===false) return false;
		$query = $query->fetchAll(PDO::FETCH_ASSOC);
		if(count($query)){
			try{
				$transaction = $this->db->beginTransaction();
				if($transaction===false) throw new Exception('');
				foreach($query as $active) {
					$query = $this->insertQueue(array('action'=>'remove','sid'=>$active['sid'],'pid'=>$active['pid'],'uid'=>$active['uid']));
					if($query===false) throw new Exception('');;
					$query = $this->deleteActive($active);
					if($query===false) throw new Exception('');;
				}
				$commit = $this->db->commit();
				if($commit===false) throw new Exception('');
			}catch(Exception $e){
				$this->db->rollBack();
				return false;
			}
		}
		return true;
	}
	function convertConflicts($conflicts) {
		if(is_array($conflicts)){
			return implode('|',array_values(array_unique(array_filter($conflicts)))).'|';
		}elseif(is_string($conflicts)){
			return array_values(array_unique(array_filter(explode('|',$conflicts))));
		}else{
			return false;
		}
	}
	function createTables($drop = false) {
		$queries = array('drop'=>array(),'create'=>array());
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_active}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_active}` (
			  `pid` INTEGER NOT NULL,
			  `uid` VARCHAR(32) NOT NULL,
			  `sid` INTEGER NOT NULL,
			  `permanent` TINYINT(1) NOT NULL,
			  `expire` DATE NOT NULL,
			  UNIQUE (`pid`,`uid`)
			);";
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_packages}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_packages}` (
			  `pid` INTEGER PRIMARY KEY {$this->autoincrement} NOT NULL,
			  `sid` INTEGER NOT NULL,
			  `name` VARCHAR(64) NOT NULL,
			  `price` FLOAT NOT NULL DEFAULT '1',
			  `conflicts` VARCHAR(256) NOT NULL DEFAULT '|',
			  `duration` VARCHAR(64) NOT NULL DEFAULT '1 month',
			  UNIQUE (`pid`)
			);";
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_purchases}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_purchases}` (
			  `mid` INTEGER PRIMARY KEY {$this->autoincrement} NOT NULL,
			  `pid` INTEGER NOT NULL,
			  `uid` VARCHAR(32) NOT NULL,
			  `date` DATE NOT NULL,
			  `amount` FLOAT NOT NULL,
			  `data` BLOB,
			  UNIQUE (`mid`)
			);";
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_queue}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_queue}` (
			  `qid` INTEGER PRIMARY KEY {$this->autoincrement} NOT NULL,
			  `pid` INTEGER NOT NULL,
			  `uid` VARCHAR(32) NOT NULL,
			  `sid` INTEGER NOT NULL,
			  `action` VARCHAR(16) NOT NULL,
			  UNIQUE (`qid`)
			) ;";
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_servers}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_servers}` (
			  `sid` INTEGER PRIMARY KEY {$this->autoincrement} NOT NULL,
			  `name` VARCHAR(64) NOT NULL,
			  `address` VARCHAR(64) NOT NULL,
			  `port` VARCHAR(5) NOT NULL,
			  `apikey` VARCHAR(32) DEFAULT NULL,
			  `earned` BIGINT(16) NOT NULL DEFAULT '0',
			  UNIQUE (`sid`)
			) ;";
		$queries['drop'][] = "DROP TABLE IF EXISTS `{$this->prefix}{$this->table_users}`;";
		$queries['create'][] = "
			CREATE TABLE IF NOT EXISTS `{$this->prefix}{$this->table_users}` (
			  `uid` VARCHAR(32) PRIMARY KEY NOT NULL,
			  `paid` BIGINT(16) NOT NULL DEFAULT '0',
			  UNIQUE (`uid`)
			);";
		
			if($drop==true){foreach($queries['drop'] as $sql) {$this->db->query($sql);}}
			foreach($queries['create'] as $sql) {$this->db->query($sql);}
			//$this->db->commit();
			return true;
	}
	function findConflicts($user, $package) {
		$query = $this->selectPackages(array('conflicts'),array('pid'=>$package));
		if($query===false or !count($query)) return false;
		$conflicts = $this->convertConflicts($query['0']['conflicts']);
		$query = $this->selectActive(array(),array('pid'=>$conflicts,'uid'=>$user));
		if($query===false) return false;
		$active_conflicts = array();
		foreach($query as $active){
			if(in_array($conflicts,$active['pid'])){
				$active_conflicts[] = $active;
			}
		}
		return count($active_conflicts) ? $active_conflicts : true;
	}
	function generateDsn(string $type, array $params) {
		return "{$type}:" . join(';', array_map(function($v, $k) {
			return "{$k}={$v}";
		}, $params, array_keys($params)));
	}
	function purchasePackage($user, $package, $amount = false, array $data = array()) {
		$user = (string) $user;
		
		$query = $this->terminateConflicts($user,$package);
		if($query!==true) return false;

		$query = $this->appendPackage($user, $package);
		if($query===false) return false;

		if(!$amount){
			$query = $this->selectPackages(array('price'),array('pid'=>$package));
			if($query===false) return false;
			$amount = $query['0']['price'];
		}
		if(!count($data) and isset($_POST)) $data = $_POST;

		$query = $this->insertPurchase(array(
			'uid'=>$user,
			'pid'=>$package,
			'amount'=>$amount,
			'data'=>json_encode($data)));
		if($query===false) return false;
		return true;
	}
	function refreshQueue() {
		$query = "SELECT uid,pid,sid FROM `{$this->prefix}{$this->table_active}` WHERE permanent = '0' AND expire <= CURRENT_DATE;";
		$query = $this->db->query($query);
		if($query===false) return false;
		$active = $query->fetchAll(PDO::FETCH_ASSOC);
		if(!count($active)) return true;
		foreach($active as $at) {
			$query = $this->insertQueue(array('action'=>'remove','sid'=>$at['sid'],'pid'=>$at['pid'],'uid'=>$at['uid']));
			if($query===false) return false;
			$query = $this->deleteActive($at);
			if($query===false) return false;
		}
		return true;
	}
	function setConflicts($package, array $conflicts = array()) {
		if(!is_array($conflicts)) return false;
		$query = $this->selectPackages(array('pid','conflicts'),array('pid'=>$package));
		if($query===false or !count($query)) return false;
		$main_package = $query['0'];

		$conflicts = array_diff($conflicts,array($package));

		$original_conflicts = $this->convertConflicts($main_package['conflicts']);
		$altered_conflicts = $conflicts;
		$added_conflicts = array_diff($altered_conflicts,$original_conflicts);
		$removed_conflicts =  array_diff($original_conflicts,$altered_conflicts);

		if(!count(array_merge($removed_conflicts,$added_conflicts))) return true;

		$package_conflicts = array();

		$packages = $this->selectPackages(array('pid','conflicts'),array('pid'=>array_merge($removed_conflicts,$added_conflicts)));
		if(!$packages) return 1;

		foreach($packages as $package){
			$conflicts = $this->convertConflicts($package['conflicts']);
			if(in_array($package['pid'],$added_conflicts)){
				$conflicts[] = $main_package['pid'];
				$package_conflicts[$package['pid']] = $this->convertConflicts($conflicts);
			}elseif(in_array($package['pid'],$removed_conflicts)){
				$conflicts = array_diff($conflicts,array($main_package['pid']));
				$package_conflicts[$package['pid']] = $this->convertConflicts($conflicts);
			}
		}
		$package_conflicts[$main_package['pid']] = $this->convertConflicts($altered_conflicts);

		foreach($package_conflicts as $pid=>$this_conflicts){
			$this->updatePackages(array('conflicts'=>$this_conflicts),array('pid'=>$pid));
		}
		return true;
	}
	function submitPurchase(integer $user, integer $package) {
		$query = $this->selectUsers(array('uid'),array('uid'=>$user));
		if($query===false) return false;
		$user = $query['0'];
		$query = $this->selectPackages(array(),array('pid'=>$package));
		if($query===false) return false;
		$package = $query['0'];
		$ppconf = $this->conf['paypal'];
		$this->PayPal->addField('currency_code',$ppconf['currency_code']);
		$this->PayPal->addField('business',$ppconf['business']);
		$this->PayPal->addField('no_note','1');
		$this->PayPal->addField('no_shipping','1');
		foreach($this->conf['paypal'] as $key=>$value){
			$this->PayPal->addField((string)$key,(string)$value);
		}
		$this->PayPal->addField('item_name', '#'.$package['pid'].': '.$package['name']);
		$this->PayPal->addField('item_number', $package['pid']);
		$this->PayPal->addField('notify_url', STORE_REQUEST_PATH . '/server.php');
		$this->PayPal->addField('amount', number_format($package['price'],2));
		$this->PayPal->addField('custom',$user['uid']);
		$this->PayPal->submitPayment();
	}
	function terminateConflicts($user, $package) {
		$conflicts = $this->findConflicts($user,$package);
		if(is_bool($conflicts)) return $conflicts;
		foreach($conflicts as $conflict) {if($conflict['permanent']) return $conflicts;}
		foreach($conflicts as $conflict) {
			$query = $this->updateActive(array('expire'=>date('Y-m-h',strtotime('-1 month'))),array('uid'=>$conflict['uid'],'pid'=>$conflict['pid']));
			if($query===false) return false;
		}
		return $this->refreshQueue();
	}

	//Steam login processing
	function checkLogin() {
		if(!isset($_GET['openid_assoc_handle'],$_GET['openid_signed'],$_GET['openid_sig'])) return false;
		$params = array(
			'openid.assoc_handle'	=> $_GET['openid_assoc_handle'],
			'openid.signed'			=> $_GET['openid_signed'],
			'openid.sig'			=> $_GET['openid_sig'],
			'openid.ns'				=> 'http://specs.openid.net/auth/2.0',
		);
		$signed = explode(',', $_GET['openid_signed']);
		foreach($signed as $item) {
			$index = 'openid_' . str_replace('.','_',$item);
			if(!isset($_GET[$index])) return false;
			$params['openid.' . $item] = get_magic_quotes_gpc() ? stripslashes($_GET[$index]) : $_GET[$index]; 
		}
		$params['openid.mode'] = 'check_authentication';
		$query =  http_build_query($params);
		$context = stream_context_create(array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 
					"Accept-language: en\r\n".
					"Content-type: application/x-www-form-urlencoded\r\n" .
					"Content-Length: " . strlen($query) . "\r\n",
				'content' => $query,
			),
		));
		$result = file_get_contents(self::STEAM_LOGIN, false, $context);

		preg_match("#^http://steamcommunity.com/openid/id/([0-9]{17,25})#", $_GET['openid_claimed_id'], $matches);
		$steamID64 = is_numeric($matches[1]) ? $matches[1] : 0;

		return preg_match("#is_valid\s*:\s*true#i", $result) == 1 ? $steamID64 : false;
	}
	function generateLoginUrl(string $returnTo, $useAmp = true) {
		$params = array(
			'openid.ns'			=> 'http://specs.openid.net/auth/2.0',
			'openid.mode'		=> 'checkid_setup',
			'openid.return_to'	=> $returnTo,
			'openid.realm'		=> (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
			'openid.identity'	=> 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.claimed_id'	=> 'http://specs.openid.net/auth/2.0/identifier_select',
		);
		
		$sep = ($useAmp) ? '&amp;' : '&';
		return self::STEAM_LOGIN . '?' . http_build_query($params, '', $sep);
	}

	//Database manipulation
	function selectArray(string $table, array $columns = array(), array $compare = array(), $limit = 0) {
		$_prepare 	= array();

		$_table 	= "`{$table}`";
		$_columns 	= ($columns=='*')?'*':(is_array($columns)?(count($columns)?"`".implode("`,`",array_values($columns))."`":'*'):"`".((string)$columns)."`");

		if(count($compare)){
			$_compare 	= array();
			array_walk($compare,function($v,$k) use(&$_compare,&$_prepare) {
				if(substr($k,0,1)=="!"){
					$k = substr($k,1);
					$prefix = "NOT ";
				}else $prefix = "";
				if(is_array($v)){
					$temp = array();
					foreach($v as $key=>$value){
						$temp[] = ":t{$k}_{$key}";
						$_prepare[":t{$k}_{$key}"] = $value;
					}
					$_compare[]="{$prefix}{$k} IN (".implode(",",$temp).")";
				}else{
					$_compare[]="{$prefix}{$k}=:c{$k}";
					$_prepare[":c{$k}"]=$v;
				}
			});
			$_table 	.= " WHERE ".implode(" AND ",$_compare);
		}

		if(is_numeric($limit) and $limit>0){
			$_table.=" LIMIT {$limit}";
		}

		$_query 	= "SELECT {$_columns} FROM {$_table};";
		$_query 	= $this->db->prepare($_query);
		if($_query===false) return false;
		$_query->execute($_prepare);
		return $_query;
	}
	function insertArray(string $table, array $values = array()) {
		$_prepare 	= array();

		$_table 	= "`{$table}`";
		$_columns 	= "`".implode("`,`",array_keys($values))."`";

		$_values 	= array();
		array_walk($values,function($v,$k) use(&$_values,&$_prepare) {$_values[]=":v{$k}";$_prepare[":v{$k}"]=$v;});
		$_values 	= implode(",",$_values);

		$_query 	= "INSERT INTO {$_table}({$_columns}) VALUES ({$_values});";
		$_query 	= $this->db->prepare($_query);
		if($_query===false) return false;
		$_query->execute($_prepare);
		return $_query;
	}
	function updateArray(string $table, array $values, $compare=array(), $limit = 0) {
		$_prepare 	= array();

		$_table		= "`{$table}`";

		$_values 	= array();
		array_walk($values,function($v,$k) use(&$_values,&$_prepare) {$_values[]="$k=:v$k";$_prepare[":v$k"]=$v;});
		$_values 	= implode(",",$_values);

		if(count($compare)){
			$_compare 	= array();
			array_walk($compare,function($v,$k) use(&$_compare,&$_prepare) {
				if(substr($k,0,1)=="!"){
					$k = substr($k,1);
					$prefix = "NOT ";
				}else $prefix = "";
				if(is_array($v)){
					$temp = array();
					foreach($v as $key=>$value){
						$temp[] = ":t{$k}_{$key}";
						$_prepare[":t{$k}_{$key}"] = $value;
					}
					$_compare[]="{$prefix}{$k} IN (".implode(",",$temp).")";
				}else{
					$_compare[]="{$prefix}{$k}=:c{$k}";
					$_prepare[":c{$k}"]=$v;
				}
			});
			$_values 	.= " WHERE ".implode(" AND ",$_compare);
		}

		if(is_numeric($limit) and $limit>0){
			$_values.=" LIMIT {$limit}";
		}

		$_query 	= "UPDATE {$_table} SET {$_values};";
		$_query 	= $this->db->prepare($_query);
		if($_query===false) return false;
		$_query->execute($_prepare);
		return $_query;
	}
	function deleteArray(string $table, array $compare = array(), $limit = 0){
		$_prepare 	= array();

		$_table 	= "`{$table}`";

		if(count($compare)){
			$_compare 	= array();
			array_walk($compare,function($v,$k) use(&$_compare,&$_prepare) {
				if(substr($k,0,1)=="!"){
					$k = substr($k,1);
					$prefix = "NOT ";
				}else $prefix = "";
				if(is_array($v)){
					$temp = array();
					foreach($v as $key=>$value){
						$temp[] = ":t{$k}_{$key}";
						$_prepare[":t{$k}_{$key}"] = $value;
					}
					$_compare[]="{$prefix}{$k} IN (".implode(",",$temp).")";
				}else{
					$_compare[]="{$prefix}{$k}=:c{$k}";
					$_prepare[":c{$k}"]=$v;
				}
			});
			$_table 	.= " WHERE ".implode(" AND ",$_compare);
		}

		if(is_numeric($limit) and $limit>0){
			$_table.=" LIMIT {$limit}";
		}

		$_query 	= "DELETE FROM {$_table};";
		$_query 	= $this->db->prepare($_query);
		if($_query===false) return false;
		$_query->execute($_prepare);
		return $_query;
	}
	function hasError($query){
		if($query===false) return true;
		return $query->errorCode() !== '00000';
	}

	//select* shortcuts
	function selectUsers(array $columns = array(), array $compare = array(),$limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_users,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}
	function selectPackages(array $columns = array(), array $compare = array(), $limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_packages,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}
	function selectServers(array $columns = array(), array $compare=array(), $limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_servers,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}
	function selectPurchases(array $columns = array(), array $compare=array(), $limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_purchases,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}
	function selectActive(array $columns = array(), array $compare=array(), $limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_active,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}
	function selectQueue(array $columns = array(), array $compare=array(), $limit = 0) {
		$query = $this->selectArray($this->prefix . $this->table_queue,$columns,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->fetchAll(PDO::FETCH_ASSOC);
	}

	//update* shortcuts
	function updateUsers(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_users,$values,$compare,$limit);
		return $this->hasError($query)===false;
	}
	function updatePackages(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_packages,$values,$compare,$limit);
		return $this->hasError($query)===false;
	}
	function updateServers(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_servers,$values,$compare,$limit);
		return $this->hasError($query,$compare=array(),$limit=0)===false;
	}
	function updatePurchases(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_purchases,$values,$compare,$limit);
		return $this->hasError($query)===false;
	}
	function updateActive(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_active,$values,$compare,$limit);
		return $this->hasError($query,$compare=array(),$limit=0)===false;
	}
	function updateQueue(array $values, array $compare = array(), $limit = 0) {
		$query = $this->updateArray($this->prefix . $this->table_queue,$values,$compare,$limit);
		return $this->hasError($query)===false;
	}

	//insert* shortcuts
	function insertUser(array $values) {
		$query = $this->insertArray($this->prefix . $this->table_users,$values);
		return $this->hasError($query)===false;
	}
	function insertPackage(array $values) {
		$query = $this->insertArray($this->prefix . $this->table_packages,$values);
		return $this->hasError($query)===false;
	}
	function insertServer(array $values) {
		$values['apikey'] = (empty($values['apikey'])) ? md5(md5(microtime().rand())) : $values['apikey'];
		$query = $this->insertArray($this->prefix . $this->table_servers,$values);
		return $this->hasError($query)===false;
	}
	function insertPurchase(array $values) {
		$query = $this->insertArray($this->prefix . $this->table_purchases,$values);
		return $this->hasError($query)===false;
	}
	function insertActive(array $values) {
		$query = $this->insertArray($this->prefix . $this->table_active,$values);
		return $this->hasError($query)===false;
	}
	function insertQueue(array $values) {
		$query = $this->insertArray($this->prefix . $this->table_queue,$values);
		return $this->hasError($query)===false;
	}

	//delete* shortcuts
	function deleteUsers(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_users,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
	function deletePackages(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_packages,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
	function deleteServers(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_servers,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
	function deletePurchases(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_purchases,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
	function deleteActive(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_active,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
	function deleteQueue(array $compare = array(), $limit = 0) {
		$query = $this->deleteArray($this->prefix . $this->table_queue,$compare,$limit);
		if($this->hasError($query)) return false;
		return $query->rowCount();
	}
}

##[[ PAYPAL CLASS ]]##

class PayPal {
	public $use_curl = false;
	public $force_ssl_v3 = true;
	public $follow_location = false;
	public $use_ssl = true;
	public $use_test = false;
	public $use_sandbox = true;
	public $timeout = 30;
	public $ipn_addresses = array('173.0.82.126'/*sandbox*/,'173.0.81.1','173.0.81.33','66.211.170.66');
	public $log_file = 'ipn_log.txt';

	private $submit_fields = array();
	private $post_data = array();
	private $post_uri = '';     
	private $response_status = '';
	private $response = '';

	const PAYPAL_IPN_HOST = 'www.paypal.com';
	const SANDBOX_IPN_HOST = 'www.sandbox.paypal.com';
	const PAYPAL_CART_URL = 'https://www.paypal.com/cgi-bin/webscr';
	const SANDBOX_CART_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

	public function __construct() {
		$this->addField('rm','2');
		$this->addField('cmd','_donations');
	}

	protected function curlPost($encoded_data) {
		if ($this->use_ssl) {
			$uri = 'https://'.$this->getPaypalHost().'/cgi-bin/webscr';
			$this->post_uri = $uri;
		} else {
			$uri = 'http://'.$this->getPaypalHost().'/cgi-bin/webscr';
			$this->post_uri = $uri;
		}
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CAINFO, 
					dirname(__FILE__)."/cert/api_cert_chain.crt");
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_data);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->follow_location);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		
		if ($this->force_ssl_v3) {
			curl_setopt($ch, CURLOPT_SSLVERSION, 3);
		}
		
		$this->response = curl_exec($ch);
		$this->response_status = strval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		
		if ($this->response === false || $this->response_status == '0') {
			$errno = curl_errno($ch);
			$errstr = curl_error($ch);
			throw new Exception("cURL error: [$errno] $errstr");
		}
	}

	protected function fsockPost($encoded_data) {
	
		if ($this->use_ssl) {
			$uri = 'ssl://'.$this->getPaypalHost();
			$port = '443';
			$this->post_uri = $uri.'/cgi-bin/webscr';
		} else {
			$uri = $this->getPaypalHost(); // no "http://" in call to fsockopen()
			$port = '80';
			$this->post_uri = 'http://'.$uri.'/cgi-bin/webscr';
		}

		$fp = fsockopen($uri, $port, $errno, $errstr, $this->timeout);
		
		if (!$fp) { 
			// fsockopen error
			throw new Exception("fsockopen error: [$errno] $errstr");
		} 

		$header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
		$header .= "Host: ".$this->getPaypalHost()."\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: ".strlen($encoded_data)."\r\n";
		$header .= "Connection: Close\r\n\r\n";
		
		fputs($fp, $header.$encoded_data."\r\n\r\n");
		
		while(!feof($fp)) { 
			if (empty($this->response)) {
				// extract HTTP status from first line
				$this->response .= $status = fgets($fp, 1024); 
				$this->response_status = trim(substr($status, 9, 4));
			} else {
				$this->response .= fgets($fp, 1024); 
			}
		} 
		
		fclose($fp);
	}
	
	private function getPaypalHost() {
		if ($this->use_sandbox) return self::SANDBOX_IPN_HOST;
		else return self::PAYPAL_IPN_HOST;
	}

	private function getPaypalUrl() {
		if ($this->use_sandbox) return self::SANDBOX_CART_URL;
		else return self::PAYPAL_CART_URL;
	}

	public function addField($field, $value) {
		$this->submit_fields[(string) $field] = (string) $value;
	}

	public function submitPayment($finish = true, $url = false) {
		$url = $this->getPaypalUrl();
		echo "<html>\n";
		echo "<head><title>Processing Payment...</title></head>\n";
		echo "<body onLoad=\"document.form.submit();\">\n";
		echo "<center><h3>Please wait, your order is being processed...</h3></center>\n";
		echo "<form method=\"post\" name=\"form\" action=\"{$url}\">\n";

		foreach ($this->submit_fields as $name => $value) {
			echo "<input type=\"hidden\" name=\"$name\" value=\"$value\">";
		}
 
		echo "</form>\n";
		echo "</body></html>\n";
		if($finish) exit;
	}

	public function getPostUri() {
		return $this->post_uri;
	}
	
	public function getResponse() {
		return $this->response;
	}
	
	public function getResponseStatus() {
		return $this->response_status;
	}
	
	public function getTextReport() {
		
		$r = '';
		
		// date and POST url
		for ($i=0; $i<80; $i++) { $r .= '-'; }
		$r .= "\n[".date('m/d/Y g:i A').'] - '.$this->getPostUri();
		if ($this->use_curl) $r .= " (curl)\n";
		else $r .= " (fsockopen)\n";
		
		// HTTP Response
		for ($i=0; $i<80; $i++) { $r .= '-'; }
		$r .= "\n{$this->getResponse()}\n";
		
		// POST vars
		for ($i=0; $i<80; $i++) { $r .= '-'; }
		$r .= "\n";
		
		foreach ($this->post_data as $key => $value) {
			$r .= str_pad($key, 25)."$value\n";
		}
		$r .= "\n\n";
		
		return $r;
	}

	public function logReport($message=false) {
		$data = "\n".$this->getTextReport();
		if($message){
			$data.="\n";
			for ($i=0; $i<80; $i++) { $data .= '-'; }
			$data.="\n".$message."\n";
			for ($i=0; $i<80; $i++) { $data .= '-'; }
		}
		$fh = fopen($this->log_file, 'a') or die("can't open file");
		fwrite($fh, $data);
		fclose($fh);
	}

	public function processIpn($post_data=null) {

		if (in_array($_SERVER['REMOTE_ADDR'], $this->ipn_addresses) === false) {
			throw new Exception("Notification server address invalid - ".$_SERVER['REMOTE_ADDR']);
		}

		$encoded_data = 'cmd=_notify-validate';
		
		if ($post_data === null) { 
			// use raw POST data 
			if (!empty($_POST)) {
				$this->post_data = $_POST;
				$encoded_data .= '&'.file_get_contents('php://input');
			} else {
				throw new Exception("No POST data found.");
			}
		} else { 
			// use provided data array
			$this->post_data = $post_data;
			
			foreach ($this->post_data as $key => $value) {
				$encoded_data .= "&$key=".urlencode($value);
			}
		}

		if ($this->use_curl) $this->curlPost($encoded_data); 
		else $this->fsockPost($encoded_data);
		
		if (strpos($this->response_status, '200') === false) {
			throw new Exception("Invalid response status: ".$this->response_status);
		}
		
		if (strpos($this->response, "VERIFIED") !== false) {
			return true;
		} elseif (strpos($this->response, "INVALID") !== false) {
			return false;
		} else {
			throw new Exception("Unexpected response from PayPal.");
		}
	}
	 
	public function requirePostMethod() {
		// require POST requests
		if ($_SERVER['REQUEST_METHOD'] && $_SERVER['REQUEST_METHOD'] != 'POST') {
			header('Allow: POST', true, 405);
			throw new Exception("Invalid HTTP request method.");
		}
	}
}