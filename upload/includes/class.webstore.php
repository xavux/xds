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

if(!defined('STORE_ROOT_PATH')) die('Store root path is not defined');
if(!defined('STORE_INCLUDES_PATH')) define('STORE_INCLUDES_PATH',dirname(__FILE__));
if(!defined('STORE_ACTIONS_PATH')) define('STORE_ACTIONS_PATH',STORE_INCLUDES_PATH . '/actions');
if(!defined('STORE_REQUEST_PATH')) define('STORE_REQUEST_PATH',(!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\'));

##[[ WEBSTORE CLASS ]]##

class WebStore {
	private $store;

	private $vars_global = array();
	private $vars_local = array();
	private $templates = array();
	private $opentag = '{';
	private $closetag = '}';
	private $variable = '$';
	private $template = '#';

	public $user = false;

	function __construct(Store $store) {
		if(session_id() == '') session_start();

		$this->store = $store;

		if(!empty($_SESSION['store_uid'])){
			$query = $this->store->selectUsers(array(),array('uid'=>$_SESSION['store_uid']));
			if($query===false or !count($query)){
				session_destroy();
				session_start();
				$this->redirectError('Invalid user - please, re-authenticate');
			}
			$this->user = $query['0'];
		}
	}

	//Action handling
	function loadAction($action){
		if(strpos($action,'\\') or strpos($action,'/')) return false;
		foreach($GLOBALS as $key=>$value){${$key}=$value;} //VERY hacky way
		$file = include(STORE_ACTIONS_PATH . "/{$action}.php");
		if($file==false) return false;
		return true;
	}

	//Miscellaneous element generation
	function link(string $url, array $params = array()) {
		$query = http_build_query($params);
		return $url . '/?' . $query;
	}
	function linkStore(string $action, array $params = array()){
		$params['action'] = $action;
		return $this->link(STORE_REQUEST_PATH, $params);
	}
	function title(string $text, $icon = "resource/icons/page.png") {
		$code = "<div class=\"title\"><img src=\"{$icon}\" alt=\"\">{$text}</div>";
		return $code;
	}
	function label(string $name, $text = ""){
		$code = "<label for=\"{$name}\">{$text}</label>";
		return $code;
	}
	function textEntry(string $name, $label = "", $value = "", $additional = ""){
		$label = htmlentities($label,ENT_QUOTES);
		$code = $this->label($name,$label);
		$code.= "<input placeholder=\"{$label}\" type=\"text\" name=\"{$name}\" value=\"".htmlentities($value,ENT_QUOTES)."\" {$additional}/>";
		return $code;
	}
	function select(string $name, $text="", array $values = array(), $selected = null, $additional = ""){
		$code = $this->label($name, $text);
		$code.= "<select name=\"{$name}\" {$additional}>";
		$isarray = is_array($selected);
		foreach($values as $value=>$name){
			$sel = "";
			if(($isarray and in_array($value,$selected)) or $value==$selected){$sel = "selected";}
			$code.= "<option {$sel} value=\"".htmlentities($value,ENT_QUOTES)."\">{$name}</option>";
		}
		$code.= "</select>";
		return $code;
	}

	//Button generation
	function buttonLink(string $link, string $text, $type = "yellow", $right = true, $additional = "") {
		$align = (is_null($right)) ? '' : (($right) ? "right" : "left");
		$code = "<a class=\"button {$type} {$align}\" href=\"{$link}\" {$additional}>{$text}</a>";
		return $code;
	}
	function buttonAction(string $action, string $text, $type = "yellow", array $params = array(), $right = true, $additional = "") {
		$params['action'] = $action;
		return $this->buttonLink($this->link(STORE_REQUEST_PATH,$params), $text, $type, $right, $additional);
	}
	function buttonSubmit(string $action, string $text, $type = "green", array $params = array(), $right = true, $additional = "") {
		$align = (is_null($right)) ? '' : (($right) ? "right" : "left");
		$code = "<button class=\"button {$type} {$align}\" name=\"action\" value=\"{$action}\" {$additional}>{$text}</button>";
		foreach($params as $name=>$value){$code.= "<input type=\"hidden\" name=\"{$name}\" value=\"".htmlentities($value,ENT_QUOTES)."\"/>";}
		return $code;
	}
	function buttonReturn($action = "home", $text = "Home", $type = "grey", $params = array(), $right = false, $additional = "") {
		return $this->buttonAction($action, $text, $type, $params, $right, $additional);
	}
	function buttonBack($text = "Back", $type = "grey", $params = array(), $right = false, $additional = "") {
		return $this->buttonLink("javascript:history.go(-1)", $text,$type,$right, $additional);
	}

	//Utility functions
	function isAdmin($uid = null) {
		if(is_null($uid)){
			if($this->user) return true;
			else return false;
		}
		return in_array($uid,$this->store->conf['site']['admins']);
	}
	function redirectLink($url) {
		header('Location: '.$url);
		exit;
	}
	function redirectAction(string $action, array $params=array()) {
		$this->redirectLink($this->linkStore($action, $params));
	}
	function redirectError($errorstr) {
		$_SESSION['error'] = $errorstr;
		$this->redirectAction('error');
	}
	function requireLogin($no_redirect = false){
		if($this->user) return true;
		if(!$no_redirect){
			$goto = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$_SESSION['store_goto'] = $goto;
			$this->redirectAction('login');
		}
		return false;
	}
	function returnError(){
		$error = empty($_SESSION['error']) ? "Unknown error" : $_SESSION['error'];
		unset($_SESSION['error']);
		return $error;
	}

	//Template engine
	private function includeTemplate($file_id, $filename){
		if(file_exists($filename)){
			$include = fread($fp = fopen($filename, 'r'), filesize($filename));
			fclose($fp);
		}else $include = '[ERROR: "'.$filename.'" does not exist.]';

		$search = $this->opentag.$this->template.$filename.$this->closetag;
		$tag = substr($this->templates[$file_id], strpos(strtolower($this->templates[$file_id]), $search), strlen($search));
		$this->templates[$file_id] = str_replace($tag, $include, $this->templates[$file_id]);
	}
	function loadTemplate($file_id, $filename){
		$this->templates[$file_id] = fread($fp = fopen($filename, 'r'), filesize($filename));
		fclose($fp);
	}
	function registerVariable($var_name,$var_value,$file_id=true){
		if(is_string($var_name)){
			if($file_id===true) {
				$this->vars_global[$var_name] = $var_value;
			} else {
				$this->vars_local[$file_id] = isset($this->vars_local[$file_id]) ? $this->vars_local[$file_id] : array();
				$this->vars_local[$file_id][$var_name] = $var_value;
			}
		}
	}
	function parseTemplate($file_id){
		$file_ids = explode(',', $file_id);
		for(reset($file_ids); $file_id = trim(current($file_ids)); next($file_ids)){
			while(is_long($pos = strpos(strtolower($this->templates[$file_id]), $this->opentag.$this->template))){
				$pos += 19;
				$endpos = strpos($this->templates[$file_id], $this->closetag, $pos);
				$filename = substr($this->templates[$file_id], $pos, $endpos-$pos);
				$this->includeTemplate($file_id, $filename);
			}

			$vars = array_merge($this->vars_global,isset($this->vars_local[$file_id]) ? $this->vars_local[$file_id] : array());
			foreach($vars as $name=>$value){
				$this->templates[$file_id] = str_replace($this->opentag.$this->variable.$name.$this->closetag, $value, $this->templates[$file_id],$matches);
			}
		}
	}
	function printTemplate($file_id){
		if(is_long(strpos($file_id, ',')) == TRUE){
			$file_id = explode(',', $file_id);
			for(reset($file_id); $current = current($file_id); next($file_id)) echo $this->templates[trim($current)];
		}else{
			echo $this->templates[$file_id];
		}
	}
	function getTemplate($file_id){
		$ret = '';
		if(is_long(strpos($file_id, ',')) == TRUE){
			$file_id = explode(',', $file_id);
			for(reset($file_id); $current = current($file_id); next($file_id)) $ret .= $this->templates[trim($current)];
		}else{
			$ret .= $this->templates[$file_id];
		}
		return $ret;
	}
}