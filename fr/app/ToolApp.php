<?php
namespace lessp\fr\app;

use lessp\fr\log\Logger;
use lessp\fr\conf\Conf;
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			ToolApp.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-24
 * @version		    1.0
 */

const TOOL_ERROR_CODE = 201;
const TOOL_EXCEPTION_CODE = 202;

require_once __DIR__.'/BaseApp.php';

final class ToolApp extends \BaseApp
{
	private $url;
	private $args;
	private $showHelp = FALSE;
	
	private $_opts;
	
	function __construct()
	{
		parent::__construct();
		$this->mode = APP_MODE_TOOL;
		
		$this->_opts = getopt('dhi:u:', array('url:', 'debug', 'help', 'input:'));

		if (isset($this->_opts['u'])) {
			$this->url = $this->_opts['u'];
		} else if (isset($this->_opts['url'])) {
			$this->url = $this->_opts['url'];
		}
		
		if (!$this->url) {
			$this->showAppHelp();
			return;
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::init()
	 */
	function init()
	{
		parent::init();
		
		$this->initArgs();
	}
	
	/**
	 * 获取帮助内容
	 * @param \ReflectionClass $clz 
	 * @param string $method 
	 */
	private function showHelp($clz, $method)
	{
		if ( !$clz->hasMethod($method) ) {
			throw new \Exception('toolapp.methodNotFound method='.$method);
		}

		// 获取类的说明
		$helpTitle = '';
		$comment = $cls->getDocComment();
		
		require_once FR_ROOT.'/util/PhpDoc.php';
		$cinfo = \lessp\fr\util\PhpDoc::anaComment($clz->getDocComment());
		
		$mtd = $clz->getMethod($method);
		$minfo = \lessp\fr\util\PhpDoc::anaMethod($mtd);
		$params = isset($minfo['param']) ? $minfo['param'] : array();

		
		echo "============={$clz->getName()}===============\n";
		echo $cinfo['desc']."\n";
		echo "\n";
		unset($info['desc']);
		foreach($cinfo as $key => $value) {
			echo "$key: $value\n";
		}
		foreach($params as $param) {
			printf("-%s: %s %s\n", $param['name'], $param['type'], $param['desc']);
		}
	} 

	private function showAppHelp()
	{
		echo <<<HELP
tool 
========
				
options
--------
		
* -d  --debug  表示开启调试
* -h  --help   表示显示帮助
* -i  --input  传入的参数，可以多次使用。可以传入键值对或者json格式的数据。

example
-------- 

* php runroot/index.php /foo/bar?foo=bar
* php runroot/index.php /foo/bar/ToolController.php -foo bar
* php runroot/index.php TOOL_ROOT./foo/bar/ToolController.php -foo bar

> 将调用TOOL_ROOT.'/foo/bar/ToolController.php'::execute()

HELP;
		exit();
	}
	
	private function initArgs()
	{
		$opts = $this->_opts;
		
		$ret = array();
		if (isset($opts['d']) || isset($opts['debug'])) {
			$this->debug = true;
		} 
		if (isset($opts['h']) || isset($opts['help'])) {
			$this->showHelp = true;
		}
		
		// 初始化输入参数
		$args = array();
		if (isset($opts['i'])) {
			$args = $this->parseParam($opts['i']);
		}
		
		if (isset($opts['input'])) {
			$_data = $this->parseParam($opts['input']);
			
			foreach($_data as $key => $value) {
				if (isset($args[$key])) {
					if (is_string($args[$key])) {
						$args[$key] = array($args[$key]);
					}
					$args[$key][] = $value;
				} else {
					$args[$key] = $value;
				}
			}
		}
		
		
		$info = @parse_url($this->url);
		if ($info === FALSE) {
			throw new Exception('toolapp.urlIllegal url='.$this->url);
		}
		
		$this->url = $info['path'];
		
		if (isset($info['query'])) {
			parse_str($info['query'], $args);
				
			foreach($args as $k => $v) {
				if (isset($args[$k])) {
					if (is_string($args[$k])) {
						$args[$k] = array($args[$k]);
					}
					$args[$k][] = $v;
				} else {
					$args[$k] = $v;
				}
			}
		}
		$this->args = $args;
	}
	
	/**
	 * 解析参数
	 * @param string|array $param
	 * @return array
	 */
	private function parseParam($param)
	{
		if (is_string($param)) {
			if ($param[0] == '{') {// JSON格式
				$args = @json_decode($param, true);
			} else {
				parse_str($param, $args);	
			}
			return $args;
		} else if (is_array($param)) {
			$args = array();
			foreach($param as $p) {
				$args = array_merge($args, $this->parseParam($p));
			}
			return $args;
		}
		return array();
	}
	
	/**
	 * 处理
	 * @throws Exception
	 */
	function process()
	{
		$arr = explode('/', trim($this->url, '/'));
		
		$method = array_pop($arr);
		if (!$arr) {
			$clsName = $this->ns.'tool\\ToolController';
		} else {
			$clsName = $this->ns.'tool\\'.implode('\\', $arr).'\\ToolController';
		}
		$path = TOOL_ROOT.implode('/', $arr).'/ToolController.php';
		
		if (!is_readable($path)) {
			throw new \Exception('toolapp.pathNotFound path='.$path);
		}
		
		require_once $path;
		if (!class_exists($clsName)) {
			throw new \Exception('toolapp.classNotFound class='.$clsName);
		}
		$cls = new \ReflectionClass($clsName);
		$ctl = $cls->newInstance($this);
		
		if ($this->showHelp) {
			if ( is_callable( array($ctl, 'help') ) ) {
				$ctl->help($method);
			} else {
				$this->showHelp($ctl, $method.'Action');
			}
			return;
		}
		
		// 处理之前统一调用的方法
		if (is_callable(array($ctl, '_before'))) {
			$ctl->_before($method, $this->args);
		}
		
		$_args = $this->getFuncArgs($ctl, $method.'Action', $this->args);		
		try {
			call_user_func_array( array($ctl, $method.'Action'), $_args);
		} catch(\Exception $ex) {
			if ( is_callable( array($ctl, '_after') ) ) {
				$ctl->_after($method, $this->args);
			}
			throw $ex;
		}
		
		// 处理完后统一调用的方法
		if ( is_callable( array($ctl, '_after') ) ) {
			$ctl->_after($method, $this->args);
		}
	}

	
	private function getFuncArgs($ctl, $method, $args)
	{
		if (!is_callable(array($ctl, $method))) {
			throw new \Exception('toolapp.methodNotFound method='.$method);
		}
		$method = new \ReflectionMethod($ctl, $method);
		$_params = $method->getParameters();
		$_args = array();
		foreach($_params as $param) {
			$key = $param->getName();
			if (array_key_exists($key, $args)) {
				$_args[] = $args[$key];
			} else if ($param->isDefaultValueAvailable()) {
				$_args[] = $param->getDefaultValue();
			} else {
				throw new \Exception('toolapp.argNotFound arg='.$key);
			}
		}
		return $_args;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::errorHandler()
	 */
	function errorHandler()
	{
		$error = func_get_args();
		if (false === parent::errorHandler($error[0], $error[1], $error[2], $error[3])) {
			return;
		}
		if (true === $this->debug) {
			unset($error[4]);
			print_r($error);
		}
		exit(TOOL_ERROR_CODE);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::exceptionHandler()
	 */
	function exceptionHandler($ex)
	{
		parent::exceptionHandler($ex);
		if (true === $this->debug) {
			print_r($ex->__toString());
		}
		exit(TOOL_EXCEPTION_CODE);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see BaseApp::shutdownHandler()
	 */
	function shutdownHandler()
	{
		global $__Lessp_appid;
		$basic = array(
			'logid' => $this->appId.'-'.($__Lessp_appid - $this->appId)
		);
		Logger::addBasic($basic);
		parent::shutdownHandler();
	}
}


abstract class Tool
{
	/**
     *
     * @var \ToolApp
     */
	protected $app;
	
	function __construct(ToolApp $app)
	{
		$this->app = $app;
	}

	function help()
	{

	}
}
