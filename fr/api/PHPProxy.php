<?php

/**
 *  
 * @filesource        PhpProxy.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.nhap 2014 All rights reserved.
 * @desc 
 * @example     
 */

require_once __DIR__.'/BaseProxy.php';

class PHPProxy extends BaseProxy
{
	private $srcCaller = null;

	function __construct($mod)
	{
		parent::__construct($mod);
	}

	function init($conf,$params)
	{
		$confroot = $conf['conf_path'];
		$apiroot = $conf['api_path'];
		
		$mod = $this->getMod();
		//支持多级的子模块
		$modseg = explode('/', $mod);
		//自动加载app mod conf
		Conf::load($confroot.$modseg[0].'.conf.php');
		
		$class = implode('', array_map('ucfirst', $modseg)). 'Export';
		
		//类名以每一级单词大写开始
		$path = $apiroot.$mod.'/'.ucfirst($modseg[count($modseg)-1]).'Export.php';
		require_once $path;
		$this->srcCaller = new $class($params);
	}

	function call($name,$args)
	{
		$ret = call_user_func_array(array($this->srcCaller,$name),$args);
		return $ret;
	}
}