<?php

class XfAddOns_Logger_Log
{

	/**
	 * Separator for the log line
	 * @var unknown
	 */
	const LOG_SEPARATOR = ', ';
	
	/**
	 * Separator for a complex message
	 * @var unknown
	 */
	const DECORATE_SEPARATOR = "======================================================="; 
	
	/**
	 * The log file to append the message to
	 * @var unknown
	 */
	const LOG_FILE = './internal_data/xenforo.log';

	/**
	 * @var true
	 */
	const WARN_ENABLED = true;
	
	/**
	 * @var true
	 */
	const INFO_ENABLED = true;
	
	/**
	 * @var false
	 */
	const DEBUG_ENABLED = true;
	
	/**
	 * @var false
	 */
	const TRACE_ENABLED = false;
	
	/**
	 * Logs a message that can be used for debugging
	 * @param String $msg
	 */
	public static function log($msg, $level = 'INFO')
	{
		$logMsg = "";
		$logMsg .= $level;
		$logMsg .= " [" . date("Y-m-d H:i:s") . "]";

		$ref = 1;
		$trace = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) : debug_backtrace(FALSE);
		$class = (count($trace) >= ($ref + 2)) && isset($trace[$ref + 1]['class']) ? $trace[$ref + 1]['class'] : '';
		$function = (count($trace) >= ($ref + 2)) && isset($trace[$ref + 1]['function']) ? $trace[$ref + 1]['function'] : '';
		$line = (count($trace) >= ($ref + 1)) && isset($trace[$ref]['line']) ? $trace[$ref]['line'] : '';
		if ($class && $line)
		{
			$logMsg .= self::LOG_SEPARATOR;
			$logMsg .= $class;
			$logMsg .= ':';
			$logMsg .= $function . "()";
			$logMsg .= ':';
			$logMsg .= $line;
		}
		
		$logMsg .= "\r\n";

		// message contents
		$msgContents = null;
		if (!is_string($msg))
		{
			$msgContents = var_export($msg, true);
		}
		else
		{
			$msgContents = $msg;
		}
		
		$logMsg .= $msgContents;
		$logMsg .= "\r\n";
		file_put_contents(self::LOG_FILE, $logMsg . "\r\n", FILE_APPEND);
	}
	
	/**
	 * Statement used for tracing the progress
	 * @param string $msg
	 */
	public static function trace($msg)
	{
		if (self::TRACE_ENABLED)
		{
			self::log($msg, 'TRACE');
		}
	}
	
	/**
	 * Statement used for debugging the progress
	 * @param string $msg
	 */
	public static function debug($msg)
	{
		if (self::DEBUG_ENABLED)
		{
			self::log($msg, 'DEBUG');
		}
	}

	/**
	 * Statement used for debugging the progress
	 * @param string $msg
	 */
	public static function info($msg)
	{
		if (self::INFO_ENABLED)
		{
			self::log($msg, 'INFO');
		}
	}
	
	/**
	 * Warn statements for serious errors
	 * @param string $msg
	 */
	public static function warn($msg)
	{
		if (self::WARN_ENABLED)
		{
			self::log($msg, 'WARN');
		}		
	}
	
	/**
	 * Decorates a message with separators so it can be identified more easily
	 * @return string	The decorated message
	 */
	public static function decorate($message)
	{
		$ret = '';
		$ret .= self::DECORATE_SEPARATOR;
		$ret .= "\r\n";
		$ret .= $message;
		$ret .= "\r\n";
		$ret .= self::DECORATE_SEPARATOR;
		return $ret;
	}
	
	
	
}