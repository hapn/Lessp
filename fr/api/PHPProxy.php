<?php

namespace nhap\fr\api;

use lessp\fr\conf\Conf;
use lessp\fr\api\BaseProxy;
/**
 *  
 * @file        PhpProxy.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.nhap 2014 All rights reserved.
 * @description 
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
		$apins = $conf['api_ns'];
		
		$mod = $this->getMod();
		//支持多级的子模块
		$modseg = explode('/', $mod);
		//自动加载app mod conf
		Conf::load($confroot.$modseg[0].'.conf.php');
		
		$class = $apins.implode('\\', $modseg).'\\'. $modseg[count($modseg) - 1]. 'Export';
		
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