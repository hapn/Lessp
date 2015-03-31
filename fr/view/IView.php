<?php
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			IView.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version		    1.0
 */

interface IView
{
	/**
	 * 初始化配置信息
	 * @param array $conf
	 */
	function init(array $conf = array());
	
	/**
	 * 设置变量数组
	 * @param array $arr
	 */
	function sets(array $arr);
	
	/**
	 * 分配变量
	 * @param string $key
	 * @param mixed $value
	 */
	function set($key, $value);
	
	/**
	 * 设置模板路径
	 * @param string $layout
	 * @param array $args 参数
	 */
	function setLayout($layout, $args = array());
	
	/**
	 * 渲染模板并输出 
	 * @param string $template
	 */
	function render($template);
	
	/**
	 * 渲染模板并返回
	 * @param string $template
	 */
	function fetch($template);
	
	/**
	 * 清空设置的变量
	 */
	function clear();
}