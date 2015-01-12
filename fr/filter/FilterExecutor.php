<?php

namespace lessp\fr\filter;
use \lessp\fr\app\WebApp;
use \lessp\fr\log\Logger;

/**
 * 
 * @file 		IFilter.php
 * @author 		ronnie
 * @date 		2014-12-21
 * @version 	1.0
 * @description 过滤器的接口定义
 *
 **/
interface IFilter
{
	function execute(WebApp $app);
}

/**
 *  
 * @file        FilterExcutor.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 
 * @example     
 */
class FilterExecutor
{
	private $impFilters = array();
	/**
	 * @var WebApp
	 */
	private $app = null;
	
	function __construct(WebApp $app)
	{
		$this->app = $app;
	}
	
	/**
	 * 载入过滤器
	 * @param array $filters
	 * @throws \Exception lessp.errpath 错误的路径
	 */
	function loadFilters($filters)
	{
		foreach($filters as $key => $classes) {
			foreach($classes as $classname) {
				if (strpos($classname,'.') !== false) {
					//避免引用到其他目录
					throw new \Exception('lessp.errpath');
				}
				if (!is_readable(__DIR__.'/'.$classname.'.php')) {
					// 支持扩展的一种方式
					if (!is_readable(PLUGIN_ROOT.'filter/'.$classname.'.php')) {
						throw new \Exception('lessp.errclass '.$classname);
					}
					require_once PLUGIN_ROOT.'filter/'.$classname.'.php';
					$classname = $this->app->ns.'filter\\'.$classname;
				} else {
					require_once FR_ROOT.'filter/'.$classname.'.php';
					$classname = '\\lessp\\fr\\filter\\'.$classname;
				}
				if (!class_exists($classname)) {
					throw new \Exception('lessp.errclass class='.$classname);
				}
				// Logger::debug('load filter %s.%s', $key, $classname);
				$this->impFilters[$key][] = new $classname();
			}
		}
	}
	
	/**
	 * 执行过滤器
	 * @param string $filtername
	 * @return boolean 执行是否成功
	 */
	function executeFilter($filtername)
	{
		$timerKey = 'f_'.$filtername;
		if (!isset($this->impFilters[$filtername])) {
			// Logger::debug('miss filter %s',$filtername);
			return true;
		}
		$this->app->timer->begin($timerKey);
		$filters = $this->impFilters[$filtername];
		foreach($filters as $filter) {
			if ($filter->execute($this->app) === false) {
				Logger::debug('call filter %s.%s=false',$filtername, get_class($filter));
				$this->app->timer->end('f_'.$filtername);
				return false;
			}
			// Logger::debug('call filter %s.%s=true',$filtername, get_class($filter));
		}
		$this->app->timer->end($timerKey);
		return true;
	}
}