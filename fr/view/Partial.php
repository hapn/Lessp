<?php
namespace lessp\fr\view;

/**
 * 帮助器
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			helper.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version		    1.0
 */

class Partial
{
	/**
	 * 视图
	 * @var \lessp\fr\view\IView
	 */
	var $view;
	
	/**
	 * 设置变量
	 * @param string $key
	 * @param mixed $value
	 */
	function set($key, $value)
	{
		$this->view->set($key, $value);
	}
	
	/**
	 * 批量设置变量
	 * @param array $values
	 */
	function sets($values)
	{
		$this->view->sets($values);
	}
	
	/**
	 * 获取设置的变量
	 * @param string $key
	 */
	function get($key)
	{
		return $this->view->$key;
	}

	/**
	 * 返回渲染模板的html代码
	 * @param string $template
	 */
	function display($template, $args = array())
	{
		//if (!empty($args)) {
			$this->view->sets($args);
		//}
		return $this->view->fetch($template);
	}
}