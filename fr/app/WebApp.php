<?php

require_once __DIR__ . '/BaseApp.php';

/**
 * @filesource 		WebApp.php
 * @author 		ronnie<comdeng@live.com>
 * @since 		2014-12-21
 * @version 	1.0
 * @copyright 	Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc web的基本app
 * @example
 *
 */
class WebApp extends BaseApp {
	public $filterExecutor = null;
	/**
	 * Http请求类
	 * @var HttpRequest
	 */
	public $request = null;
	
	/**
	 * Http响应类
	 * @var HttpResponse
	 */
	public $response = null;
	// 是否为后台运行的模式
	// 如果处于这种模式，会先调用fastcgi_finish_request直接返回，然后再执行相关内容，这种模式下不会有response过程，否则会引起失败
	/**
	 * 是否处于任务模式 如果处于这种模式，会先调用fastcgi_finish_request直接返回，然后再执行相关内容，这种模式下不会有response过程，否则会引起失败
	 * 
	 * @var boolean
	 */
	public $isTask = false;
	
	function __construct() {
		parent::__construct ();
		$this->mode = APP_MODE_WEB;
	}
	
	/**
	 * 处理web请求
	 */
	function process() {
		if (false === $this->filterExecutor->executeFilter ( 'init' )) {
			return;
		}
		
		if (false === $this->filterExecutor->executeFilter ( 'input' )) {
			return;
		}
		
		if (false === $this->filterExecutor->executeFilter ( 'url' )) {
			return;
		}
		
		if (false === $this->filterExecutor->executeFilter ( 'output' )) {
			return;
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::genAppId()
	 */
	function genAppId() {
		if (isset ( $_SERVER ['HTTP_CLIENTAPPID'] )) {
			return intval ( $_SERVER ['HTTP_CLIENTAPPID'] );
		}
		$reqip = '127.0.0.1';
		if (isset ( $_SERVER ['HTTP_CLIENTIP'] )) {
			$reqip = $_SERVER ['HTTP_CLIENTIP'];
		} elseif (isset ( $_SERVER ['REMOTE_ADDR'] )) {
			$reqip = $_SERVER ['REMOTE_ADDR'];
		}
		$time = gettimeofday ();
		$time = $time ['sec'] * 100 + $time ['usec'];
		$ip = ip2long ( $reqip );
		$id = ($time ^ $ip) & 0xFFFFFFFF;
		return floor ( $id / 100 ) * 100;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::init()
	 */
	function init() {
		$this->_initWebObject ();
		parent::init ();
		$this->_initFilter ();
	}
	
	/**
	 * 初始化web对象
	 */
	private function _initWebObject() {
		require_once FR_ROOT . 'http/HttpRequest.php';
		require_once FR_ROOT . 'http/HttpResponse.php';
		require_once FR_ROOT . 'filter/FilterExecutor.php';
		
		$this->request = new HttpRequest ( $this );
		$this->response = new HttpResponse ( $this );
		$this->filterExecutor = new FilterExecutor($this );
	}
	
	/**
	 * 获取过滤器
	 * @param string $name
	 * @param array $defaults
	 * @param boolean $cover
	 * @return array
	 */
	private function _getFilter($name, $defaults, $cover = false) {
		$filters = Conf::get ( $name, array () );
		if (is_string ( $filters )) {
			$filters = array (
				$filters 
			);
		}
		if ($cover) {
			if ($filters) {
				return $filters;
			} else {
				return $defaults;
			}
		} else {
			$defaults = array_diff ( $defaults, $filters );
			return array_merge ( $defaults, $filters );
		}
	}
	
	/**
	 * 初始化过滤器
	 */
	private function _initFilter() {
		$filters ['init'] = $this->_getFilter ( 'hapn.filter.init', array ( 'InitFilter' ) );
		$filters ['input'] 	= $this->_getFilter ( 'hapn.filter.input', array () );
		$filters ['url'] 	= $this->_getFilter ( 'hapn.filter.url', array ( 'UrlFilter'), true );
		$filters ['output'] = $this->_getFilter ( 'hapn.filter.output', array ( 'OutputFilter' ) );
		$filters ['clean'] 	= $this->_getFilter ( 'hapn.filter.clean', array () );
		
		$this->filterExecutor->loadFilters ( $filters );
	}
	
	private function _setHeader($errcode)
	{
		switch($errcode) {
			case MSG_NOT_FOUND:
				$this->response->setHeader ( 'HTTP/1.1 404 Not Found' );
				break;
			case MSG_NO_POWER:
				$this->response->setHeader('HTTP/1.1 401 Unauthorized');
				break;
			case MSG_FATAL:
				if ( !($this->isUserErr($errcode)) ) {
					$this->response->setHeader('HTTP/1.1 500 Internal Server Error');
				}
				break;
		}
	}
	
	/**
	 * 转到错误页面
	 * @param string $errcode
	 */
	function _goErrorPage($errcode) {
		$conf = Conf::get ( 'hapn.error.redirect', array () );
		$host = $this->request->host;
		if (! empty ( $conf [$host] )) {
			$conf = $conf [$host];
		}
		$url = '/';
		if (isset ( $conf [$errcode] )) {
			$url = $conf [$errcode];
		} elseif (isset ( $conf ['hapn.error'] )) {
			$url = $conf ['hapn.error'];
		}
		$domain = $this->request->host;
		if (strncmp ( $domain, 'http', 4 ) !== 0) {
			$domain = 'http://' . $domain;
		}
		$url = str_replace ( '[url]', urlencode ( $domain . $this->request->rawUri ), $url );
		if (! $this->isUserErr ( $errcode ) && ! is_file ( $url )) {
			$di = base64_encode ( 'ip=' . $this->request->userip . ':time=' . $this->request->now . ':id=' . $this->appId );
			if (strpos ( $url, '?' ) !== false) {
				$url .= '&di=' . $di;
			} else {
				$url .= '?di=' . $di;
			}
		}
		
		if (true === $this->debug) {
			$this->response->sendHeaders ();
			echo "<br/>Redirect: <a href='$url'>$url</a><br/>";
		} else {
			$redirect = TRUE;
			if ($url[0] == '!') {
				$redirect = FALSE;
				$url = substr($url, 1);
			}
			$info = @parse_url($url);
			if ( $info && ($redirect || !empty($info['host'])) ) {
				// 如果设置的文件是一个实际的路径，则直接输出内容，不跳转
				$this->response->setHeader ( 'Location: ' . $url );
				// 设置正常结束状态
				$this->response->sendHeaders ();
				exit ();
			} else {
				Conf::set('hapn.view.root', PAGE_ROOT);
				if (isset($info['query'])) {
					parse_str($info['query'], $args);
				} else {
					$args = array();
				}
				ob_clean();
				$this->response->forward($info['path'], $args);
				$this->response->send();
				exit();
			}
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::errorHandler()
	 */
	function errorHandler() {
		$error = func_get_args ();
		if (false === parent::errorHandler ( $error [0], $error [1], $error [2], $error [3] )) {
			return;
		}
		if ($this->isTask) {
			exit ();
		}
		if (true === $this->debug) {
			unset ( $error [4] );
			echo "<pre>";
			print_r ( $error );
			echo "</pre>";
		}
		$errcode = 'hapn.fatal';
		$this->endStatus = $errcode;
		$this->response->setLesspHeader ( $errcode );
		
		$this->_setHeader( $errcode );
		if ($this->request->needErrorPage ()) {
			$this->_goErrorPage ( $errcode );
		} else {
			$this->response->setError ( $error );
			$this->response->send ();
		}
		exit ();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::exceptionHandler()
	 */
	function exceptionHandler($ex) {
		parent::exceptionHandler ( $ex );
		$errcode = $ex->getMessage ();
		if (($pos = strpos ( $errcode, ' ' )) > 0) {
			$errcode = substr ( $errcode, 0, $pos );
		}
		$this->endStatus = $errcode;
		if ($this->isTask) {
			exit ();
		}
		if (true === $this->debug) {
			echo "<pre>";
			print_r ( $ex->__toString () );
			echo "</pre>";
		}
		if ($this->request->method == 'GET') {
			$retrycode = Conf::get ( 'hapn.error.retrycode', '/\.net_/' );
			$retrynum = $this->request->get ( '_retry', 0 );
			$retrymax = Conf::get ( 'hapn.error.retrymax', 1 );
			if ($retrycode && $retrynum < $retrymax && preg_match ( $retrycode, $errcode ) > 0) {
				$retrynum ++;
				$gets = array_merge ( $_GET, array ( '_retry' => $retrynum ) );
				$url = $this->request->url . '?' . http_build_query ( $gets );
				$this->response->setHeader ( 'X-Rewrite-URI: ' . $url );
				$this->response->send ();
				exit ();
			}
		}
		$this->response->setLesspHeader ( $errcode );
		
		$this->_setHeader($errcode);
		
		if ($this->request->needErrorPage ()) {
			$this->_goErrorPage ( $errcode );
			exit ();
		}
		$this->response->setException ( $ex );
		$this->response->send ();
		exit ();
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::shutdownHandler()
	 */
	function shutdownHandler() {
		$this->filterExecutor->executeFilter ( 'clean' );
		global $_HapN_appid;
		
		$r = $this->request;
		$ip = ($r->userip === $r->clientip) ? $r->userip : ($r->userip . '-' . $r->clientip);
		
		$basic = array (
			'ip' => $ip,
			'uri' => $this->request->rawUri,
			'logid' => $this->appId . '-' . ($_HapN_appid - $this->appId) 
		);
		
		if ($this->request->method != 'GET') {
			$basic ['m'] = $this->request->method;
		}
		
		Logger::addBasic ( $basic );
		parent::shutdownHandler ();
	}
}
