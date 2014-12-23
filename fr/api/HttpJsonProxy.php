<?php

namespace lessp\fr\api;

/**
 *  
 * @file        HttpJsonProxy.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 使用HttpJson作为协议的代理类
 * @example     
 */
require_once __DIR__.'/HttpJson.php';

class HttpJsonProxy extends BaseProxy
{
	private $srcCaller = null;
	private $params = null;
	
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\api\BaseProxy::init()
	 */
	function init($conf,$params)
	{
	
		$this->params = $params;
		$encoding = isset($conf['encoding'])?$conf['encoding']:'GBK';
		$ctimeout = isset($conf['connect_timeout'])?$conf['connect_timeout']:3000;
		$rtimeout = isset($conf['read_timeout'])?$conf['read_timeout']:3000;
		$wtimeout = isset($conf['write_timeout'])?$conf['write_timeout']:3000;
		$rpc = new HttpJson($conf['servers'],$encoding,$ctimeout,$rtimeout,$wtimeout);
		$this->srcCaller = $rpc;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\api\BaseProxy::call()
	 */
	function call($name,$args)
	{
		if ($name == '_fetch') {
			if (!isset($args[0])) {
				throw new \Exception('Api.fetch missing fetch url');
			}
			$url = $args[0];
			$input = isset($args[1]) ? $args[1] : array();
			$ret = $this->srcCaller->rpcCall($url, $input);
			return $ret;
		} else {
			return $this->callMethod($name, $args);
		}
	}
	
	/**
	 * 调用方法
	 * @param string $name
	 * @param array $args
	 * @throws \Exception
	 */
	private function callMethod($name,$args)
	{
		$try = 0;
		if (isset($this->params['_try'])) {
			//处理尝试次数
			$try = intval($this->params['_try']);
			unset($this->params['_try']);
		}
		$method = $this->getMod().'.'.$name;
		$url = '/_private/rpc/'.$method.'?_if=json&_of=json&_enc=0&_try='.($try+1);
		if (isset($args[0])) {
			$input = array('rpcinput' => $args);
		} else {
			$input = array('rpcinput' => array());
		}
		$input['rpcinit'] = $this->params;
		$ret = $this->srcCaller->rpcCall($url, $input);
		if (isset($ret['err']) && ($ret['err'] == 'ok' || $ret['err'] == 'lessp.ok') && isset($ret['data'])){
			//如果函数什么都不返回，rpcret就是null
			//这时候isset判断会得到false
			return $ret['data']['rpcret'];
		}
		throw new \Exception('Api.httpjson.invalidret '.print_r($ret, true));
	}
}