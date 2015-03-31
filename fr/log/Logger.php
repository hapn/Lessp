<?php

/**
 *  
 * @filesource        Logger.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 日志
 * @example     
 */

$__log = null;

//逻辑日志级别
const LOG_LEVEL_FATAL 	= 1;
const LOG_LEVEL_WARNING = 2;
const LOG_LEVEL_NOTICE 	= 4;
const LOG_LEVEL_TRACE 	= 8;
const LOG_LEVEL_DEBUG 	= 16;

// 日志切分方式
const LOG_ROLLING_NONE 	= 0;
const LOG_ROLLING_HOUR 	= 1;
const LOG_ROLLING_DAY 	= 2;
const LOG_ROLLING_MONTH = 3;

$__log = null;

class Logger
{
	private static function __log__($type, $arr, $sms_monitor = false) {
		global $__log;
		$format = $arr [0];
		array_shift ( $arr );
		
		$pid = posix_getpid ();
		
		$bt = debug_backtrace ();
		if (isset ( $bt [1] ) && isset ( $bt [1] ['file'] )) {
			$c = $bt [1];
		} else if (isset ( $bt [2] ) && isset ( $bt [2] ['file'] )) { //为了兼容回调函数使用log
			$c = $bt [2];
		} else if (isset ( $bt [0] ) && isset ( $bt [0] ['file'] )) {
			$c = $bt [0];
		} else {
			$c = array ('file' => 'faint', 'line' => 'faint' );
		}
		$line_no = '[' . $c ['file'] . ':' . $c ['line'] . '] ';
		
		if (! empty ( $__log [$pid] )) {
			$log = $__log [$pid];
			$log->writeLog ( $type, $format, $line_no, $arr, $sms_monitor );
		}
	}
	
	/**
	 * 初始化
	 * @param string $dir
	 * @param string $file
	 * @param int $level
	 * @param array $info
	 * @param int $roll
	 * @param boolean $flush
	 * @return boolean
	 */
	static function init($dir, $file, $level = LOG_LEVEL_DEBUG, $info = array(), $roll = LOG_ROLLING_NONE, $flush = false) {
		global $__log;
		
		$pid = posix_getpid ();
		
		if (! empty ( $__log [$pid] )) {
			unset ( $__log [$pid] );
		}
		
		$__log [posix_getpid ()] = new Log();
		$log = $__log [posix_getpid ()];
		if ($log->init ( $dir, $file, $level, $info, $roll, $flush )) {
			return true;
		} else {
			unset ( $__log [$pid] );
			return false;
		}
	}
	
	/**
	 * debug
	 */
	static function debug() {
		$arg = func_get_args ();
		Logger::__log__ ( LOG_LEVEL_DEBUG, $arg );
	}
	
	/**
	 * trace
	 */
	static function trace() {
		$arg = func_get_args ();
		Logger::__log__ ( LOG_LEVEL_TRACE, $arg );
	}
	
	/**
	 * notice
	 */
	static function notice() {
		$arg = func_get_args ();
		Logger::__log__ ( LOG_LEVEL_NOTICE, $arg );
	}
	
	/**
	 * warning
	 */
	static function warning() {
		$arg = func_get_args ();
		Logger::__log__ ( LOG_LEVEL_WARNING, $arg );
	}
	
	/**
	 * fatal
	 */
	static function fatal() {
		$arg = func_get_args ();
		Logger::__log__ ( LOG_LEVEL_FATAL, $arg );
	}
	
	/**
	 * 发送通知
	 */
	static function pushNotice() {
		global $__log;
		$arr = func_get_args ();
		
		$pid = posix_getpid ();
		
		if (! empty ( $__log [$pid] )) {
			$log = $__log [$pid];
			$format = $arr [0];
			/* shift $type and $format, arr_data left */
			array_shift ( $arr );
			$log->pushNotice ( $format, $arr );
		} else {
			/* nothing to do */
		}
	}
	
	/**
	 * 清除通知
	 */
	static function clearNotice() {
		global $__log;
		$pid = posix_getpid ();
		
		if (! empty ( $__log [$pid] )) {
			$log = $__log [$pid];
			$log->clearNotice ();
		} else {
			/* nothing to do */
		}
	}
	
	/**
	 * 增加输出的基本信息
	 * @param array $arr_basic
	 */
	static function addBasic($arr_basic) {
		global $__log;
		$pid = posix_getpid ();
		
		if (! empty ( $__log [$pid] )) {
			$log = $__log [$pid];
			$log->addBasicInfo ( $arr_basic );
		} else {
			/* nothing to do */
		}
	}
	
	/**
	 * 报错
	 * @param Exception $e
	 */
	static function exception($e) {
		Logger::warning ( 'caught exception [%s]', $e );
	}
	
	/**
	 * 输出
	 */
	static function flush() {
		global $__log;
		$pid = posix_getpid ();
		if (! empty ( $__log [$pid] )) {
			$log = $__log [$pid];
			$log->checkFlushLog ( true );
		} else {
			/* nothing to do */
		}
	}
}