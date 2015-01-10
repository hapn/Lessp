<?php

namespace lessp\fr\conf;

/**
 * 配置文件
 * 
 * @filesource 	Conf.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @example     
 */


class Conf
{
	private static $isLoaded = array();
	private static $confData = array();
	
	/**
	 * 载入配置文件
	 * @param string|array $paths 路径，可以是一个路径，或者路径数组
	 */
	static function load($paths)
	{
		if (is_string($paths)) {
			$paths = array($paths);
		}
		foreach($paths as $path) {
			if (isset(self::$isLoaded[$path])) {
				continue;
			}
			if (is_readable($path)) {
				require_once $path;
				self::$isLoaded[$path] = true;
			} else {
		// 有人说这条信息太多，建议不要了
		//		trigger_error("$path no such file or directory",E_USER_NOTICE);
			}
		}
	}

	/**
	 * 获取配置项
	 * @param string $key
	 * @param string $default
	 * @return mixed
	 */
	static function get($key, $default=null)
	{
		if (isset(self::$confData[$key])) {
			return self::$confData[$key];
		}
		return $default;
	}

	/**
	 * 设置配置项
	 * @param string $key
	 * @param mixed $value
	 */
	static function set($key, $value)
	{
		self::$confData[$key] = $value;
	}

	/**
	 * 是否有指定的配置项
	 * @param string $key
	 */
	static function has($key)
	{
		return array_key_exists($key, self::$confData[$key]);
	}

	/**
	 * 清除所有配置信息
	 */
	static function clear()
	{
		self::$isLoaded = array();
		self::$confData = array();
	}
}