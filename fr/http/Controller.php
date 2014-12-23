<?php

namespace lessp\fr\http;

/**
 *  
 * @file        Controller.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 控制器
 * @example     
 */

abstract class Controller
{
	/**
	 * Request
	 * @var Request
	 */
	var $request = null;
	/**
	 * Response
	 * @var Response
	 */
	var $response = null;
	var $debug = null;
	var $encoding = null;
	
	/**
	 * action调用之前调用的方法
	 * @param string $method
	 * @param array $args
	 */
	function _before($method, $args)
	{
		
	}
	
	/**
	 * action调用之后调用的方法
	 */
	function _after()
	{
		
	}
	
	/**
	 * 获取多个请求参数
	 * @return array
	 * <code>array(
	 *  $arg1 => $value1,
	 *  $arg2 => $value2,
	 * )</code>
	 * @example
	 *   $this->gets('username', 'password');
	 * 返回
	 *   array('username' => 'xxx', 'password' => 'xxx')
	 */
	function gets()
	{
		$keys = func_get_args();
		$ret = array();
		foreach($keys as $key) {
			$ret[$key] = $this->get($key);
		}
		return $ret;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Request::get
	 */
	function get($key, $default=null)
	{
		return $this->request->get($key, $default);
	}
	
	 /**
	 * (non-PHPdoc)
	 * @see Request::getu
	 */
	function getu($key,$default=null)
	{
		return $this->request->getu($key, $default);
	}
	
	/**
	 * 
	 * (non-PHPdoc)
	 * @see Response::set
	 */
	function set($key, $value)
	{
		return $this->response->set($key, $value);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Response::rmset
	 */
	function rmset()
	{
		return call_user_func_array(array($this->response, 'rmset'), func_get_args());
	}
	
	/**
	 * (non-PHPdoc)
	 * @see Response::setView
	 */
	function setView($template, $data = array())
	{
		return $this->response->setView($template, $data);
	}
	
	/**
	 * 跳转页面请求
	 * @param string $url
	 */
	function forward($url)
	{
		$dispatcher = new UrlDispatcher(NULL);
		$dispatcher->dispatch($url);
	}
}