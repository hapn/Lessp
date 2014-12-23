<?php

namespace lessp\fr\view;

use lessp\fr\app\WebApp;
require_once __DIR__.'/IView.php';
require_once __DIR__.'/Helper.php';

/**
 * 
 * 类似Zend Framework的模板，遵循php的基本语法
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			ZendView.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version			1.0
 */

final class PhpView implements IView
{
	private $_v = array();
	
	/**
	 * 请求
	 * @var \fillp\fr\http\Request
	 */
	var $request;
	
	private $app = null;
	private $viewRoot = '';
	private $helperRoot = '';
	private $cacheRoot = '';
	static $HelperCache = array();
	private $tplExt;
	
	/**
	 * partial的级别，为0表示最外层的view
	 * @var int
	 */
	private $partialLevel = 0;
	
	/**
	 * 命名空间
	 * @var string
	 */
	private $viewNs;
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::init()
	 */
	function init(array $conf = array())
	{
		if (!isset($conf['viewNs'])) {
			throw new \Exception('zendview.viewNsRequired');
		}
		$this->viewNs = rtrim($conf['viewNs'], '\\');
		if (!isset($conf['request']) || !($conf['request'] instanceof \lessp\fr\http\Request)) {
			throw new \Exception('zendview.requestIllegal');
		}
		$this->request = $conf['request'];
		
		$this->viewRoot = isset($conf['viewRoot']) ? $conf['viewRoot'] : PAGE_ROOT;
		$this->helperRoot = isset($conf['viewRoot']) ? $conf['viewRoot'] : PLUGIN_ROOT.'helper/';
		$this->cacheRoot = isset($conf['cacheRoot']) ? $conf['cacheRoot'] : TMP_ROOT.'zendview/';
		$this->tplExt = isset($conf['tplExt']) ? $conf['tplExt'] : 'phtml';
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::sets()
	 */
	function sets(array $arr)
	{
		$level = $this->partialLevel;
		if (isset($this->_v[$level])) {
			$this->_v[$level] = array_merge($this->_v[$level], $arr);
		} else {
			$this->_v[$level] = $arr;
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::set()
	 */
	function set($key, $value)
	{
		$this->_v[$this->partialLevel][$key] = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::clear()
	 */
	function clear()
	{
		$this->_v = array();
	}
	
	/**
	 * 获取变量
	 * 去过之后会把属性值设置上
	 * @param string $name
	 */
	function __get($name)
	{
		for($i = $this->partialLevel; $i >= max($i - 1, 0); $i--) {
			if (array_key_exists($name, $this->_v[$i])) {
				return $this->$name = $this->_v[$i][$name];
			}
		}
		return NULL;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::render()
	 */
	function fetch($template)
	{
		if (strpos($template,'..') !== false ||
		strpos($template,'`') !== false ||
		strpos($template,'!') !== false) {
			//这样操作避免开发人员不正确编码，包含这些字符可能带来安全性问题，
			throw new \Exception('hapn.notpl not suport "..|`|!"');
		}
		$this->file = $this->viewRoot.'/'.ltrim($template,'/');
		if (is_readable($this->file)) {
			unset($template);
			ob_start();
			include $this->file;
			return ob_get_clean();
		}
		throw new \Exception('lessp.notpl '.$this->file.' no such file or directory');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\view\IView::fetch()
	 */
	function render($template)
	{
		echo $this->fetch($template);
	}
	
	private function enterPartialStatus()
	{
		$this->partialLevel++;
		
		if ($this->partialLevel > 2) {
			throw new \Exception('phpview.partialLevelTooBig');
		}
	}
	
	/**
	 * 离开partial状态
	 */
	private function leavePartialStatus()
	{
		if (isset($this->_v[$this->partialLevel])) {	
			foreach($this->_v[$this->partialLevel] as $key => $value) {
				unset($this->$key);
			}

			unset($this->_v[$this->partialLevel]);
		}
		$this->partialLevel--;
	}
	
	
	/**
	 * 局部模块 支持对Pagelet和phtml文件两种形式
	 * @param string $template 模板地址
	 * @param array $args 变量
	 * @param int $cacheExpire
	 * @param string $cacheKey
	 */
	function partial($template, $args = array(), $cacheExpire = 0, $cacheKey = '')
	{
		$template = trim(strtolower($template));
		if (is_int($args) && func_arg_nums() == 2) {
			$cacheExpire = $args;
			$args = array();
		}
		$this->sets($args);
		 
		// 检测是partial的phtml还是Pagelet
		$isPagelet = substr($template, - (strlen($this->tplExt) + 1)) != '.'.$this->tplExt;
		if ($cacheExpire > 0) {
			if (!$cacheKey) {
				$cacheKey = hash('sha1',  $template.(!empty($args) ? json_encode($args, JSON_UNESCAPED_UNICODE) : ''));
			}
			$cacheFile = sprintf('%s/%2s/%s.html', $this->cacheRoot, substr($cacheKey, 0, 2), $cacheKey);
			if (is_readable($cacheFile) && filemtime($cacheFile) + $cacheExpire > time()) {
				return file_get_contents($cacheFile);
			}
			
			$this->enterPartialStatus();
	
			if ($isPagelet) {
				$html = $this->_partial($template, $args);
			} else {
				$html = $this->fetch($template);
			}
			
			$this->leavePartialStatus();
			
			if (!is_dir(dirname($cacheFile))) {
				mkdir(dirname($cacheFile), 0755, true);
			}
			file_put_contents($cacheFile, $html);
			return $html;
		}
		$this->enterPartialStatus();
		if ($isPagelet) {
			$html =  $this->_partial($template, $args);
		} else {
			$html = $this->render($template);
		}
		$this->leavePartialStatus();
		return $html;
	}
	
	/**
	 * 执行partial的方法，返回代码
	 * @param string $path
	 * @param array $args
	 * @throws \Exception
	 * 
	 * @return string
	 */
	private function _partial($path)
	{
		$arr = explode('/', trim($path, '/'));
		if (count($arr) < 2) {
			throw new \Exception('zenview.partialTplPathIllegal');
		}
		$methodName = array_pop($arr).'Action';
		$clsName = $this->viewNs.'\\partial\\'.implode('\\', $arr).'\\PartialController';
		
		$ptPath = $this->viewRoot.'/'.implode('/', $arr).'/PartialController.php';
		if (!is_readable($ptPath)) {
			throw new \Exception('zendview.partialNotFound path='.$ptPath);
		}
		
		require_once __DIR__.'/Partial.php';
		require_once $ptPath;
		
		if (!class_exists($clsName)) {
			throw new \Exception('zendview.classNotFound class='. $clsName);
		}
		
		$ctl = new $clsName();
		if (!is_callable(array($ctl, $methodName))) {
			throw new \Exception('zendview.methodNotFound method='.$methodName);
		}
		$ctl->view = $this;
		return call_user_func(array($ctl, $methodName));
	}
	
	
	/**
	 * 使用pagelet技术获取html片段
	 * @param string $uri
	 * @param array $args
	 * 
	 * @todo 等待实现
	 */
	function pagelet($uri, $args = array())
	{
	}
	
	//通过魔术函数，调用helper功能
	function __call($name,$args)
	{
		if (isset(self::$HelperCache[$name])) {
			$helper = self::$HelperCache[$name];
		} else {
			$helperFile = $this->_getHelper($name);
			require_once $helperFile;
			$class = 'ZendView'.ucfirst($name).'Helper';
			$helper = new $class();
			$helper->setView($this);
	
			self::$HelperCache[$name] = $helper;
		}
		return call_user_func_array(array($helper,$name),$args);
	}
	
	/**
	 * 获取扩展
	 * 先查找扩展目录，再查找系统目录
	 * @param unknown $name
	 * @throws Exception
	 * @return string
	 */
	function _getHelper($name)
	{
		if ($this->helperRoot) {
			$file = $this->helperRoot.$name.'.php';
			if (is_readable($file)) {
				return $file;
			}
		}
		$file = FR_ROOT.'view/helper/'.$name.'.php';
		if (is_readable($file)) {
			return $file;
		}
		throw new \Exception('lessp.zendview.nohelper helper='.$name);
	}
}
