<?php

/**
 *  
 * @filesource  HttpRequest.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc 
 * @example     
 */

class HttpRequest
{
	/**
	 * 请求参数
	 * @var array
	 */
	var $inputs = array();
	/**
	 * 请求的cookie
	 * @var array
	*/
	var $cookies = array();
	/**
	 * 请求的文件
	 * @var array
	*/
	var $files = array();
	/**
	 * 请求的头
	 * @var array
	*/
	var $headers = array();
	/**
	 * 请求方法，可选值为 [GET|POST|OPTION|PUT|HEAD]
	 * @var string
	*/
	var $method = 'GET';
	/**
	 * 主机名
	 * @var string
	 */
	var $host = '';
	/**
	 * 用户真实ip
	 * @var string
	 */
	var $userip = 'undefined';
	/**
	 * 客户端ip，有可能是上一个网络节点的ip
	 * @var string
	 */
	var $clientip = 'undefined';
	/**
	 * 访问网址。不带查询字符串
	 * @var string
	 */
	var $url = '';
	/**
	 * 访问网址，带查询字符串
	 * @var string
	 */
	var $uri = '';
	
	/**
	 * 原始查询网址，通过$_SERVER['REQUEST_URI']获取，解决通过rewrite指定网址后跳转的不对应问题
	 * @var string
	 * @example
	 * 
	 * 比如，在nginx中重写  rewrite /files/share(/.*)$ /index.php?route=/func/share/raw/&url=$1 last;
	 * 
	 * 当访问/files/share/index.html时，Request获取到的uri是/func/share/raw/?url=/index.html，而不是/files/share/index.html。
	 * 
	 * 因此，跳转时应该通过rawUri来拿原始网址
	 */
	var $rawUri = '';
	
	/**
	 * 服务变量
	 * @var array
	 */
	var $serverEnvs = array();
	/**
	 * 当前访问时间
	 * @var int
	*/
	var $now = 0;
	/**
	 * 是否为私有请求
	 * @var boolean
	 */
	var $isPrivate = false;
	/**
	 * 是否为ajax请求
	 * @var boolean
	 */
	var $isAjax = false;
	/**
	 * 用户数据
	 * @var array
	 */
	var $userData = array();
	/**
	 * app请求id
	 * @var int
	*/
	var $appId = 0;
	
	//输入输出格式和编码
	var $of = 'default';
	var $oe = 'UTF-8';
	var $if = 'default';
	var $ie = 'UTF-8';
	var $ep = false;
	
	
	private $app = null;
	
	function __construct(WebApp $app)
	{
		$this->app = $app;
		$this->now = time();
	}
	
	/**
	 * 获取原始的(没有经过各种过滤处理）的参数数据
	 * @param string $key 键
	 * @param string $default 默认值
	 * @return  multitype:string|array(string)
	 */
	function getu($key, $default=null)
	{
		if (isset($_GET[$key])) {
			return $_GET[$key];
		}
		if (isset($_POST[$key])) {
			return $_POST[$key];
		}
		return $default;
	}
	
	/**
	 * 获取访问参数，包含GET、POST参数
	 * @param string $key 键
	 * @param string $default 默认值
	 * @return multitype:string|array(string)
	 */
	function get($key, $default=null)
	{
		if (is_string($key)) {
			if (isset($this->inputs[$key])) {
				return $this->inputs[$key];
			}
			return $default;
		} else {
			$ret = array();
			foreach($key as $k) {
				if (isset($this->inputs[$k])) {
					$ret[] = $this->inputs[$k];
				} else {
					$ret[] = $default;
				}
			}
			return $ret;
		}
	}
	
	
	/**
	 * 设置访问参数
	 * @param string $key 键
	 * @param mixed $value 值
	 */
	function set($key, $value)
	{
		$this->inputs[$key] = $value;
	}
	
	/**
	 * 获取cookie值
	 * @param string $key 键
	 * @param string $default 默认值
	 * @return string
	 */
	function getCookie($key, $default=null)
	{
		if (isset($this->cookies[$key])) {
			return $this->cookies[$key];
		}
		return $default;
	}
	
	//是否需要跳到错误页
	function needErrorPage()
	{
		//get请求html或者default输出，或者强制_e=1跳转
		if ($this->ep) {
			return true;
		}
		if ($this->method === 'GET' &&
		($this->of == 'html' || $this->of == 'default')) {
			return true;
		}
		return false;
	}
	
	/**
	 * 获取指定的header值，会将$key大写，并且加上前缀HTTP_
	 * @param string $key 键
	 * @param string $default 默认值
	 * @return string
	 */
	function getHeader($key,$default=null)
	{
		$name = 'HTTP_'.strtoupper($key);
		if (isset($this->serverEnvs[$name])) {
			return $this->serverEnvs[$name];
		}
		return $default;
	}
	
	/**
	 * 获取指定的上传文件
	 * @param string $key 名称
	 * @return array | null
	 */
	function getFile($key)
	{
		if (isset($this->files[$key])) {
			return $this->files[$key];
		}
		return null;
	}
}