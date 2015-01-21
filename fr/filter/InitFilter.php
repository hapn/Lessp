<?php

namespace lessp\fr\filter;

use \lessp\fr\util\Encoding;
use \lessp\fr\app\WebApp;
use \lessp\fr\conf\Conf;
use lessp\fr\util\Exception;
/**
 *  
 * @filesource        InitFilter.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 初始化的过滤器
 * @example     
 */

final class InitFilter
{
	static $reqVars = array(
		'_if',	// 输入格式
		'_ie',	// 输入编码
		'_d',	// 是否调试
		'_of',	// 输出格式
		'_oe',	// 输出编码
		'route',// 原始URL
		'_e',	// 编码
		'_try', // 尝试次数
		'_ep'	// 是否跳转到错误页面
	);
	
	/**
	 * 
	 * @param WebApp $app
	 * @return boolean
	 */
	function execute(WebApp $app)
	{
		$this->_parseInternalVar($app);
		$this->_parseParams($app);
		$this->_parseCommon($app);
		$this->_transEncoding($app);
	
		$requestfile = Conf::get('lessp.log.request');
		if ($requestfile) {
			$this->_logRequest($app,$requestfile);
		}
	
		return true;
	}
	
	private function _logRequest($app, $requestfile)
	{
		$headers = array();
		foreach($app->request->serverEnvs as $key=>$value) {
			if (strncmp('HTTP_', $key, 5) === 0) {
				$headers[$key] = $value;
			}
		}
		$file = LOG_ROOT.$requestfile;
		$data = $app->request->inputs;
		$keyarr = Conf::get('lessp.log.requestfilter', array());
		foreach($keyarr as $key) {
			if (isset($data[$key])) {
				$data[$key] = '[removed]';
			}
		}
		$out = array(
			'info'=>$app->appId.':'.$app->request->url.':'.date('Y-m-d H:i:s',$app->request->now),
			'cookie'=>$app->request->cookies,
			'header'=>$headers,
			'data'=> $data
		);
		$dump = print_r($out,true);
		file_put_contents($file,$dump,FILE_APPEND);
	}
	
	private function _parseInternalVar($app)
	{
		$app->request->method = $_SERVER['REQUEST_METHOD'];
		$app->request->appId = $app->appId;
		
		$arr = array_merge($_REQUEST,$_GET,$_POST);
		$app->request->ep 		= empty($arr['_ep']) ? false : true;
		if ( !empty($arr['_if']) ) {
			$app->request->if = $arr['_if'];
		}
		if ($app->request->if === 'json') {
			//如果输入是json，那么输入编码也是UTF-8
			$app->request->ie = 'UTF-8';
		} elseif ( !empty($arr['_ie']) ) {
			$app->request->ie = $arr['_ie'];
		} else {
			$app->request->ie = Conf::get('lessp.ie', $app->encoding);
		}
	
		if (!empty($arr['_of'])) {
			$app->request->of = $arr['_of'];
		} else {
			$method = $app->request->method;
			if ( in_array($method, array('POST', 'PUT', 'DELETE') )) {
				//非GET请求默认都按照JSON返回了
				$app->request->of = 'json';
			} else {
				$app->request->of = 'default';
			}
		}
		if (!empty($arr['_oe'])) {
			$app->request->oe = $arr['_oe'];
		} else {
			$app->request->oe = Conf::get('lessp.oe', $app->encoding);
		}
		if ($app->request->of === 'json') {
			//JSON只能UTF-8
			$app->request->oe = 'UTF-8';
		}
	
		if ($app->debug === 'manual') {
			if (isset($arr['_d'])) {
				$app->debug = !!$arr['_d'];
			} else {
				$app->debug = false;
			}
		}
		//全变成大写，方便内部判断
		$app->request->ie = strtoupper($app->request->ie);
		$app->request->oe = strtoupper($app->request->oe);
	}
	
	private function _parseParams($app)
	{
		$arr = array_merge($_GET,$_POST);
		if (count($arr) != count($_GET) + count($_POST)) {
			//有某些变量被覆盖了
			//打一个日志警告下
			$keys = array_intersect(array_keys($_GET), array_keys($_POST));
			Logger::warning('$_GET & $_POST have same variables:%s', implode(',', $keys));
		}
		foreach(self::$reqVars as $key) {
			//系统参数都删除
			unset($arr[$key]);
		}
		$puts = file_get_contents('php://input');
		if ($puts) {
			if ('json' === $app->request->if) {
				$json = json_decode($puts, true);
				$arr = $arr ? array_merge($arr, $json) : $json;
			}
		}
		$app->request->inputs = $arr;
	}
	
	private function _parseCommon($app)
	{
		$app->request->cookies = $_COOKIE;
		$app->request->files = $_FILES;
		$app->request->method = $_SERVER['REQUEST_METHOD'];
		if ( !empty($_SERVER['HTTP_X_REAL_IP']) ) {
			$app->request->userip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( !empty($_SERVER['REMOTE_ADDR']) ) {
			$app->request->userip = $_SERVER['REMOTE_ADDR'];
		}
		$app->request->clientip = $_SERVER['REMOTE_ADDR'];
		$app->request->rawUri = $_SERVER['REQUEST_URI'];
		if ( isset($_GET['route']) ) {
			$app->request->url = $_GET['route'];
		} elseif (isset($_SERVER['REQUEST_URI'])) {
			if ( ($pos = strpos($app->request->rawUri, '?')) !== false ) {
				$app->request->url = substr($app->request->rawUri, 0, $pos);
			} else {
				$app->request->url = $app->request->rawUri;
			}
		} else {
			Exception::notfound();
		}
		$app->request->url = '/'.ltrim($app->request->url,'/');
		unset($_GET['route']);
		$query = http_build_query($_GET);
		$app->request->uri = $app->request->url.($query? '?'.$query : '');
		$app->request->host = strtolower(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : Conf::get('lessp.host', ''));
		$app->request->serverEnvs = $_SERVER;
	
		if (strncmp($app->request->url,'/_private/', 10) === 0) {
			$app->request->isPrivate = true;
		}
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
			$app->request->isAjax = true;
		}
	}
	
	private function _transEncoding($app)
	{
		$ie = $app->request->ie;
		$to = $app->encoding;
		if ($ie === $to) {
			return;
		}
		require_once FR_ROOT.'util/Encoding.php';
		
		//url上可能也有汉字啥的
		$app->request->url = Encoding::convert($app->request->url, $to, $ie);
		$app->request->uri = Encoding::convert($app->request->uri, $to, $ie);
	
		Encoding::convertArray($app->request->inputs, $to, $ie);
		//经过转码后把转码过的数据写会，$_GET/$_POST也可能被访问
		foreach($_GET as $key=>$value) {
			if (isset($app->request->inputs[$key])) {
				$_GET[$key] = $app->request->inputs[$key];
			}
		}
		foreach($_POST as $key=>$value) {
			if (isset($app->request->inputs[$key])) {
				$_POST[$key] = $app->request->inputs[$key];
			}
		}
	}
}