<?php

/**
 *  
 * @filesource  HttpResponse.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc 
 * @example     
 */
require_once __DIR__.'/HttpResponse.php';
class SwooleResponse extends HttpResponse
{
	/**
	 * 发送header头信息
	 */
	function sendHeaders()
	{
		$headers = $this->headers;
		$res = $this->app->swooleResponse;
		if ($this->cookies) {
			//echo cookie
			foreach($this->cookies as $cookie) {
				call_user_func_array(array($res, 'cookie'), $cookie);
			}
		}
		if ($headers) {
			foreach($headers as $header) {
				if ( ($pos = strpos($header, ':')) === FALSE) {
					if (preg_match('http/1.[01] (\d+) ', $header, $ms)) {
						$res->status($ms[1]);
					}
					continue;
				} 
				list($key, $vallue) = explode($header, ':', 2);		
				$res->header($key, ltrim($value));
			}
		}
	}
	
	/**
	 * 发送内容
	 * @param boolean $inner 是否为内部输出，如果是内部输出，则不用设置header等。
	 */
	function send($inner = false)
	{
		$ret = parent::send(true);
		if (!is_string($ret)) {
			$ret = $ret . '';
		}
		if ($inner) {
			return $ret;
		} else {
			$this->app->swooleResponse->write($ret);
		}
	}
}
