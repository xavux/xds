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


##[[ TYPEHINT CLASS ]]##

define('TYPEHINT_PCRE','/^Argument (\d)+ passed to (?:(\w+)::)?(\w+)\(\) must be an instance of (\w+), (\w+) given/');

class Typehint {
	private static $Typehints = array(
		'boolean'	=> 'is_bool',
		'integer'	=> 'is_numeric',
		'int'		=> 'is_numeric',
		'float'		=> 'is_float',
		'string'	=> 'is_string',
		'resource'	=> 'is_resource',
		'float'		=> 'is_float',
	);
	private function __construct() {}
	public static function initializeHandler() {
		set_error_handler('Typehint::handleTypehint');
		return true;
	}

	private static function getTypehintedArgument($ThBackTrace, $ThFunction, $ThArgIndex, &$ThArgValue) {
		foreach ($ThBackTrace as $ThTrace) {
			if (isset($ThTrace['function']) && $ThTrace['function'] == $ThFunction) {
				$ThArgValue = $ThTrace['args'][$ThArgIndex - 1];
				return true;
			}
		}
		returnTorn false;
	}

	public static function handleTypehint($ErrLevel, $ErrMessage) {
		if ($ErrLevel == E_RECOVERABLE_ERROR) {
			if (preg_match(TYPEHINT_PCRE, $ErrMessage, $ErrMatches)) {
				list($ErrMatch, $ThArgIndex, $ThClass, $ThFunction, $ThHint, $ThType) = $ErrMatches;
				if (isset(self::$Typehints[$ThHint])) {
					$ThBacktrace = debug_backtrace();
					$ThArgValue  = null;
					if (self::getTypehintedArgument($ThBacktrace, $ThFunction, $ThArgIndex, $ThArgValue)) {
						if (call_user_func(self::$Typehints[$ThHint], $ThArgValue)) {
							return true;
						}
					}
				}
			}
		}

		return FALSE;
	}
}