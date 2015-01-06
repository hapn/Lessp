<?php
namespace lessp\fr\util;

/**
 * lessp错误
 * @copyright 		Copyright (C) Jiehun.com.cn 2015 All rights reserved.
 * @file			LesspException.php
 * @author			ronnie<dengxiaolong@jiehun.com.cn>
 * @date			Jan 6, 2015 10:02:29 AM
 * @version		    1.0
 */

const MSG_NOT_FOUND = 'lessp.u_notfound';
const MSG_NOT_LOGIN = 'lessp.u_login';
const MSG_NO_POWER 	= 'lessp.u_power';
const MSG_FATAL		= 'lessp.fatal';
const MSG_ARGS		= 'lessp.u_args';
const MSG_INPUT		= 'lessp.u_inputs';


const EXCEPTION_TYPE_USER = 1;
const EXCEPTION_TYPE_SYSTEM = 2;

final class Exception
{
	/**
	 * 模块
	 * @var string
	 */
	private $module;
	
	/**
	 * 默认异常类型
	 * @var int
	 */
	private $defaultExceptionType;
	
	/**
	 * 初始化模块
	 * @param string 	$module
	 * @param int 		[$exType=EXCEPTION_TYPE_USER]  
	 * @return \lessp\fr\app\Exception
	 */
	function __construct($module, $exType = EXCEPTION_TYPE_USER)
	{
		$this->module = $module;
		$this->defaultExceptionType = $exType;
	}
	
	/**
	 * 抛出异常
	 * @param string $msg 错误代码
	 * @param array  $args 参数
	 * @param int 	 $type 默认为用户自定义类型
	 * @param array  $args
	 */
	function newthrow($msg, $args = array(),  $type = 0)
	{
		if (!$type) {
			$type = $this->defaultExceptionType;
		}
		$msg = $this->module.'.'.($type == EXCEPTION_TYPE_USER ? 'u_' : '').$msg;
		throw new \Exception($msg. self::getArgsStr($args));
	}
	
	/**
	 * 获取参数字符串
	 * @param array $args
	 * @return string
	 */
	static function getArgsStr($args)
	{
		if (is_array($args)) {
			$argsStr = implode(' ', array_map(function($k, $v) {
				return $k.'='.$v;
			}, $args));
		}
		if ($argsStr) {
			$argsStr = ' '.$argsStr;
		}
		return $argsStr;
	}
	
	
	/**
	 * 找不到页面的报错
	 * @param array $args
	 * @return \lessp\fr\app\LesspException
	 */
	static function notfound($args = array())
	{
		return new \Exception(MSG_NOT_FOUND.self::getArgsStr($args), 404);
	}
	
	/**
	 * 没有登录
	 * @return \lessp\fr\app\LesspException
	 */
	static function notlogin()
	{
		return new \Exception(MSG_NOT_LOGIN, 401);
	}
	
	/**
	 * 没有权限
	 * @return \lessp\fr\app\LesspException
	 */
	static function nopower()
	{
		return new \Exception(MSG_NO_POWER, 403);
	}
	
	/**
	 * 缺少参数
	 * @return \lessp\fr\app\LesspException
	 */
	static function args()
	{
		return new \Exception(MSG_ARGS);
	}
	
	/**
	 * 输入有误
	 * @return \lessp\fr\app\LesspException
	 */
	static function input()
	{
		return new \Exception(MSG_INPUT);
	}
}