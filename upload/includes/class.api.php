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


##[[ API CLASS ]]##

class StoreAPI {
	private $store;

	function __construct(Store $store) {$this->store = $store;}

	function output($action,$data){return json_encode(array('action'=>$action,'data'=>$data)); }
	function error($str){ return $this->output('error',(string) $str); }
	function callApi($action,$apikey,$input=null){
		if($apikey != 'client'){
			$server = $this->store->selectServers(array(),array('apikey'=>$apikey));
			if($server===false) return $this->error('mysql_error');
			if(!count($server)) return $this->error('invalid_apikey');
			$_action = trim(strtolower($action));
			$methods = get_class_methods($this);
			if(!in_array('sapi_'.$_action,$methods)) return $this->error('invalid_method');
			return $this->{'sapi_'.$action}($server['0'],$input);
		}else{
			$_action = trim(strtolower($action));
			$methods = get_class_methods($this);
			if(!in_array('capi_'.$_action,$methods)) return $this->error('invalid_method');
			return $this->{'capi_'.$action}($input);
		}
	}
	function sendHeaders(){header('Content-type: application/json');}

	function sapi_getqueue($server,$input){
		if(is_null($input)) {
			$query = $this->store->selectQueue(array(),array('sid'=>$server['sid']));
		} else {
			$qids = explode(",",$input);
			array_walk($qids,function(&$k,&$v){$v = trim($v);});
			$query = $this->store->selectQueue(array(),array('qid'=>$qids,'sid'=>$server['sid']));
		}
		if($query===false) return $this->error('mysql_error');
		return $this->output('getqueue',$query);
	}
	function sapi_doqueue($server,$input){
		if(is_null($input)) {
			return $this->error('invalid_qid');
		} else {
			$qids = explode(",",$input);
			array_walk($qids,function(&$k,&$v){$v = trim($v);});
			$query = $this->store->deleteQueue(array('qid'=>$qids,'sid'=>$server['sid']));
		}
		if($query===false) return $this->error('mysql_error');
		return $this->output('doqueue','success');
	}
	function sapi_getpackages($server,$input){
		if(is_null($input)) {
			$query = $this->store->selectPackages(array(),array('sid'=>$server['sid']));
		} else {
			$pids = explode(",",$input);
			array_walk($qids,function(&$k,&$v){$v = trim($v);});
			$query = $this->store->selectPackages(array(),array('pid'=>$pids,'sid'=>$server['sid']));
		}
		if($query===false) return $this->error('mysql_error');
		return $this->output('getpackages',$query);
	}
	function capi_getpackages($input){
		$query = $this->store->selectPackages();
		if($query===false) return $this->error('mysql_error');
		return $this->output('getpackages',$query);
	}
}
