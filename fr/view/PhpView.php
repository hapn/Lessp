<?php

require_once __DIR__.'/IView.php';
require_once __DIR__.'/ViewHelper.php';
require_once FR_ROOT.'http/UrlDispatcher.php';

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
	var $_request;
	
	private $_viewRoot = '';
	private $_helperRoot = '';
	private $_cacheRoot = '';
	static $_helperCache = array();
	private $_tplExt;
	// 是否在渲染模板
	private $_renderLayout;
	
	/**
	 * 模板位置
	 * @var string
	 */
	private $_layout;
	
	private $_layoutArgs;
	
	/**
	 * (non-PHPdoc)
	 * @see IView::init()
	 */
	function init(array $conf = array())
	{
		if (!isset($conf['request']) || !($conf['request'] instanceof HttpRequest)) {
			throw new \Exception('zendview.requestIllegal');
		}
		$this->_request = $conf['request'];
		
		$this->_viewRoot = isset($conf['viewRoot']) ? $conf['viewRoot'] : PAGE_ROOT;
		$this->_helperRoot = isset($conf['helperRoot']) ? $conf['helperRoot'] : PLUGIN_ROOT.'/helper/';
		$this->_cacheRoot = isset($conf['cacheRoot']) ? $conf['cacheRoot'] : TMP_ROOT.'zendview/';
		$this->_tplExt = isset($conf['tplExt']) ? $conf['tplExt'] : 'phtml';
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::sets()
	 */
	function sets(array $arr)
	{
		$this->_v = array_merge($this->_v, $arr);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::set()
	 */
	function set($key, $value)
	{
		$this->_v[$key] = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::setLayout()
	 */
	function setLayout($layout, $args = array())
	{
		$this->_layout = $layout;
		$this->_layoutArgs = $args;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::clear()
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
		if ($this->_renderLayout) {
			if (array_key_exists($name, $this->_layoutArgs)) {
				return $this->_layoutArgs[$name];
			}
		}
		
		if (array_key_exists($name, $this->_v)) {
			return $this->_v[$name];
		}
		return NULL;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::render()
	 */
	function fetch($template)
	{
		if (strpos($template,'..') !== false ||
		strpos($template,'`') !== false ||
		strpos($template,'!') !== false) {
			//这样操作避免开发人员不正确编码，包含这些字符可能带来安全性问题，
			throw new \Exception('hapn.notpl not suport "..|`|!"');
		}
		$this->file = $this->_viewRoot.'/'.ltrim($template,'/');
		if (is_readable($this->file)) {
			unset($template);
			ob_start();
			include $this->file;
			$body = ob_get_clean();
			$this->_blockes['__BODY__'] = $body;
			
			// 检查是否有layout
			if ($this->_layout && !$this->_renderLayout) {
				$this->_renderLayout = true;
				$body = $this->fetch($this->_layout);
				$this->_renderLayout = false;
			}
			return $body;
		}
		throw new \Exception('lessp.notpl '.$this->file.' no such file or directory');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IView::fetch()
	 */
	function render($template)
	{
		echo $this->fetch($template);
	}
	
	/**
	 * 局部模块 直接嵌入另一个ActionController的action方法
	 * @param string $template 模板地址
	 * @param array $args 变量
	 */
	function partial($template, $args = array())
	{
		$template = trim(strtolower($template));
		
		// 检测是partial的phtml还是Pagelet
		$isPagelet = substr($template, - (strlen($this->_tplExt) + 1)) != '.'.$this->_tplExt;
		
		$dispatcher = new UrlDispatcher(NULL, DISPATCH_MODE_PARTIAL);
		return $dispatcher->dispatch($template, $args);
	}
	
	private $_startBlockName = array();
	private $_blockes = array();
	
	/**
	 * 启动/停止一个块的解释
	 * @param string $name
	 * @param string $value 指定block的值
	 */
	function block($name = NULL, $value = NULL)
	{
		// 只有启用了模板的时候，才会将其内容缓存
		if (!$this->_layout) {
			return;
		}
		if ($name) {
			$this->_startBlockName[] = $name;
			
			if ($value !== NULL) {
				$this->_blockes[$name] = $value;
				return;
			}
			
			ob_start();
		} else {
			if (empty($this->_startBlockName)) {
				throw new \Exception('view.noBlockStart');
			}
			$lastBlockName = array_pop($this->_startBlockName);
			$result = ob_get_clean();
			$this->_blockes[$lastBlockName] = $result;
		}
	}
	
	/**
	 * 获取指定的block块的内容
	 * @param string $name
	 */
	function getBlock($name)
	{
		if (array_key_exists($name, $this->_blockes)) {
			return $this->_blockes[$name];
		}
		return '';
	}
	
	//通过魔术函数，调用helper功能
	function __call($name,$args)
	{
		if (isset(self::$_helperCache[$name])) {
			return self::$_helperCache[$name];
		}
		
		$found = false;
		if ($this->_helperRoot) {
			$file = $this->_helperRoot.$name.'.php';
			if (is_readable($file)) {
				$found = true;
				$className = 'ViewHelper_'.$name;
			}
		} 
		if (!$found) {
			$file = __DIR__.'/helper/'.$name.'.php';
			if (is_readable($file)) {
				$found = true;
				$className = $className;
			}
		}
		if (!$found) {
			throw new \Exception('lessp.phpview_nohelper helper='.$name);
		}
		
		require_once $file;
		if (!class_exists($className)) {
			throw new \Exception('lessp.phpview_noclass class='.$className);
		}
		$helper = new $className($this);

		return self::$_helperCache[$name] = $helper;
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
		if ($this->_helperRoot) {
			$file = $this->_helperRoot.$name.'.php';
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
