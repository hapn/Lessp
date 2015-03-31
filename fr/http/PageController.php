<?php

/**
 *  
 * @filesource  PageController.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 控制器
 * @example     
 */

abstract class PageController
{
	/**
	 * Request
	 * @var HttpRequest
	 */
	var $request = null;
	/**
	 * Response
	 * @var HttpResponse
	 */
	var $response = null;
	var $debug = null;
	var $encoding = null;
	
	/**
	 * 模式 normal：正常 forward：跳转 partial：包含
	 * @var string
	 */
	var $mode = DISPATCH_MODE_NORMAL;
	
	/**
	 * 相对于PAGE_ROOT的位置
	 * @var string
	 */
	var $relpath;
	
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
	 * @see HttpRequest::get
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
	 * @see HttpResponse::set
	 * @return HttpResponse
	 */
	function set($key, $value)
	{
		$this->response->set($key, $value);
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see HttpResponse::sets
	 * @return HttpResponse
	 */
	function sets($kvs)
	{
		$this->response->sets($kvs);
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see HttpResponse::rmset
	 * @return HttpResponse
	 */
	function rmset()
	{
		call_user_func_array(array($this->response, 'rmset'), func_get_args());
		return $this;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see HttpResponse::setView
	 */
	function setView($template, $data = array())
	{
		if ($template[0] != '/') {
			$template = $this->relpath.'/'.$template;
		}
		$this->response->setView($template, $data);
		return $this;
	}
	
	/**
	 * 跳转页面请求
	 * @param string $url
	 * @param array $args
	 * @see HttpResponse::forward
	 */
	function forward($url, $args = array())
	{
		$this->response->forward($url, $args);
	}
}