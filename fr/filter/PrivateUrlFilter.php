<?php

/**
 *  
 * @filesource        PrivateUrlFilter.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc 私有网址的过滤器
 * @example     
 */

final class PrivateUrlFilter implements IFilter
{
	function execute(WebApp $app)
	{
		if ($app->request->isPrivate) {
			$ip = $app->request->userip;
			if ($ip !== '127.0.0.1' &&
				//如果是内部ip则检查范围，否则直接不让通过
				!($this->isLocalIp($ip) && $this->inRange($ip))) 
			{
				throw new \Exception('hapn.ipauthfail');
			}
		}
	}
	
	/**
	 * 是否为本地Ip
	 * @param string $ip
	 * @return boolean
	 */
	private function isLocalIp($ip)
	{
		if (0 == strncmp($ip, '10.', 3) ||
			0 == strncmp($ip, '172.', 4) ||
			0 == strncmp($ip, '127.', 4) ||
			0 == strncmp($ip, '192.', 4) ) 
		{
			return true;
		}
		return false;
	}
	
	/**
	 * 是否在指定范围
	 * @param string $ip
	 * @return boolean
	 */
	private function inRange($ip)
	{
		$range = Conf::get('hapn.privateip');
		if (!$range) {
			return true;
		}
		foreach ($range as $key) {
			if ( 0 == strncmp($key, $ip, strlen($key)) ) {
				return true;
			}
		}
		return false;
	}
}