<?php

namespace lessp\fr\http;

require_once __DIR__.'/Controller.php';

use \lessp\fr\app\WebApp;
use \lessp\fr\conf\Conf;
use \lessp\fr\log\Logger;
use \lessp\fr\util\Exception;
/**
 *  
 * @file        UrlDispatcher.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 网址分配器
 * @example     
 */

// 正常模式
const DISPATCH_MODE_NORMAL 	= 'normal';
// 重定向模式
const DISPATCH_MODE_FORWARD = 'forward';
// 获取模式
const DISPATCH_MODE_PARTIAL	= 'partial';
class UrlDispatcher
{
	/**
	 * WebApp
	 * @var WebApp
	 */
	private $app = null;
	/**
	 * Controller的名称
	 * @var string
	 */
	private $ctlName;
	/**
	 * 方法的后缀
	 * @var string
	 */
	private $methodExt;
	
	//不允许用户直接访问的url
	private static $protected = array('_before', '_after');
	
	private static $lastApp;
	
	
	/**
	 * 
	 * @var \lessp\fr\util\Exception
	 */
	private $_ex;
	
	private $mode;

	/**
	 * 构建函数
	 * @param WebApp $app
	 * @param string 	$mode 模式 normal:正常 forward:重定向 fetch:内部抓取
	 */
	function __construct($app, $mode = DISPATCH_MODE_NORMAL) 
	{
		$this->_ex = new Exception(__CLASS__, \lessp\fr\util\EXCEPTION_TYPE_SYSTEM);
		if ($app === NULL) {
			if (!self::$lastApp) {
				$this->_ex->newthrow('app_required');
			}
			$this->app = self::$lastApp;
		} else {
			if (!($app instanceof WebApp)) {
				$this->_ex->newthrow('app_must_be_WebApp');
			}
			self::$lastApp = $this->app = $app;
		}
		
		$this->ctlName = Conf::get('lessp.controlleName', 'ActionController');
		$this->methodExt = Conf::get('lessp.methodExt', '_action');
		$this->mode = $mode;
	}

	/**
	 * 分配网址
	 * @param string $url
	 * @throws \Exception
	 * 
	 * @return string|null|\lessp\fr\http\Response
	 * 
	 * normal模式：null
	 * forward模式：null
	 * partial模式：string
	 */
	function dispatch($url, $inputArgs = array())
	{
		$args = array();
		$pathseg = array();
		//URL全部转换为小写，变得地址栏里大小写URL表现不一致
		$url = explode('/', trim($url, '/'));
		if ($this->app->request->method == 'GET') {
			//GET请求才有这种灵活策略
			//其他请求严格一点，安全限制要求
			foreach($url as $seg) {
				if (preg_match('/^([a-z0-9]{24,24}|[0-9]+)$/i',$seg)) {
					$args[] = $seg;
				} else {
					$pathseg[] = $seg;
				}
			}
		} else {
			$pathseg = $url;
		}
		$count = count($pathseg);
		if ($count > 0 && substr($pathseg[$count - 1], 0, 1) == '~') {
			array_pop($pathseg);
			$count--;
		}
		
		$fullpath = rtrim(PAGE_ROOT.implode('/', $pathseg),'/');
		$userfunc = '';
		$ctlName = $this->ctlName.'.php';
		
		if (is_readable($fullpath.'/'.$ctlName)) {
			$path = $fullpath.'/'.$ctlName;
			$func = 'index';
			if (empty($args)) {
				$args[] = '';
			}
			$nsseg = array_diff($pathseg, array(''));
			if (empty($nsseg)) {
				$ctlNs = $this->app->ns.'page\\';
			} else {
				$ctlNs = $this->app->ns.'page\\'.implode('\\', $nsseg).'\\';
			}
		} elseif (is_readable($fullpath.'/index/'.$ctlName)) {
			$path = $fullpath.'/index/'.$ctlName;
			$func = 'index';
			if (empty($args)) {
				$args[] = '';
			}
			$nsseg = array_diff($pathseg, array(''));
			if (empty($nsseg)) {
				$ctlNs = $this->app->ns.'page\\index\\';
			} else {
				$ctlNs = $this->app->ns.'page\\'.implode('\\', $nsseg).'\\index\\';
			}
		} else {
			if ($count > 0) {
				//至少有一级
				$rootseg = array_slice($pathseg, 0, $count-1);
				$path = rtrim(PAGE_ROOT.implode('/', $rootseg),'/').'/'.$ctlName; 
				$func = $pathseg[$count-1];
				$userfunc = $func;
				
				$nsseg = array_diff($rootseg, array(''));
				if (empty($nsseg)) {
					$ctlNs = $this->app->ns.'page\\';
				} else {
					$ctlNs = $this->app->ns.'page\\'.implode('\\', $nsseg).'\\';
				}
			} else {
				$path = rtrim(PAGE_ROOT, '/').'/'.$ctlName;
				$func = 'index';
				
				$ctlNs = $this->app->ns.'page\\';
			}
		}	
		
		$className = $ctlNs.$this->ctlName;
		
		if (is_readable($path)) {
			require_once $path;
			if (!class_exists($className)) {
				throw Exception::notfound(array('class' => $className));
			}
		} else {
			throw Exception::notfound(array('path' => $path));
		}
		$path = str_replace(PAGE_ROOT, '', $path);
		$relpath = trim(dirname($path), '/');
		Logger::debug("hit ActionController[%s] %s:%s", $this->mode, $path, $func);
		
		
		$controller = new $className();
		$controller->relpath = $relpath;
		
		//把当前处理请求和相应的对象设置好
		if ($this->mode == DISPATCH_MODE_PARTIAL) {
			$controller->request = clone($this->app->request);
			$controller->response = clone($this->app->response);
		} else {
			$controller->request = $this->app->request;
			$controller->response = $this->app->response;
		}
		$controller->response->setView(NULL);
		if (!empty($inputArgs)) {
			foreach($inputArgs as $_k => $_v) {
				$controller->request->set($_k, $_v);
			}
		}
		$controller->encoding = $this->app->encoding;
		$controller->debug = $this->app->debug;
		$controller->appId = $this->app->appId;
		$controller->mode = $this->mode;
		

		if ($func != 'index') {
			//index函数允许有一个空串参数
			$args = array_diff($args, array(''));
		}
		
		// 根据请求方法对func进行封装和解析
		$funcs = array();
		$method = strtolower($this->app->request->method);
		switch ($method) {
			case 'get':
				$funcs = array($func);
				
				// 如果不是正常的模式，允许尝试追加模式对应的url。
				// 也就是说，如果想让一个url只支持通过partial或者forward的模式访问，可以只定义特定的网址
				if ($this->mode !== DISPATCH_MODE_NORMAL) {
					$funcs[] = $func.'_'.$this->mode;
				}
				
				break;
			case 'post':
				if ($func{0} != '_') {
					throw Exception::notfound();
				}
			case 'put':
			case 'delete':
				if ($func{0} == '_') {
					$func = substr($func, 1);
				}
				if (strpos($func, strtolower($method).'_') === 0) {
					$func = substr($func, strlen($method) + 1);
				}
				$funcs = array($method.'_'.$func, '_'.$func);
				break;
			case 'option':
				$funcs = array('option_'.$func);
				break;
			case 'head':
				$funcs = array('head_'.$func, $func);
				break;
		}
		
		$mainMethod = $this->_loadMethod($controller, $funcs, $args);
		if (!$mainMethod) {
			if ($func != 'index' && $method == 'GET') {
				//$args[] = $func;
				array_unshift($args, $func);
				$mainMethod = $this->_loadMethod($controller, 'index', $args);
				if (!$mainMethod) {
					throw Exception::notfound();
				}
			} else {
				throw Exception::notfound(array('func' => $func.$this->methodExt));
			}
		}
		
		// 确保只有在主体方法有效时才会执行_before和_after
		// 主要方法在执行失败时是不会再执行_after的
		if (is_callable(array($controller, '_before'))) {
			call_user_func_array(array($controller, '_before'), array($func, $args));
		}
		
		try {
			$mainMethod->invokeArgs($controller, $args);
		} catch (\Exception $ex) {
			if (is_callable(array($controller, '_after'))) {
				call_user_func(array($controller, '_after'));
			}
			throw $ex;
		}
		
		if (is_callable(array($controller, '_after'))) {
			call_user_func(array($controller, '_after'));
		}
		if ($this->mode == DISPATCH_MODE_PARTIAL) {
			return $controller->response->send(true);
		}
	}
	
	/**
	 * 载入反射方法
	 * @param mixed $controller
	 * @param string | array $methods
	 * @param array $args
	 */
	private function _loadMethod($controller, $methods, $args = array())
	{
		if (is_string($methods)) {
			$methods = array($methods);
		}
		
		foreach($methods as $method) {
			if (!in_array($method, self::$protected)) {
				$method .= $this->methodExt;
			}
			if (is_callable(array($controller, $method))) {
				$reflection = new \ReflectionMethod($controller, $method);
				$argnum = $reflection->getNumberOfParameters();
				if ($argnum > count($args)) {
					throw Exception::notfound('args not match');
				}
				return $reflection;
			}
		}
		return false;
	}
}