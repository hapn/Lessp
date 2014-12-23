<?php

namespace nhap\fr\api;

require_once __DIR__.'/BaseProxy.php';
use lessp\fr\api\BaseProxy;
use lessp\fr\conf\Conf;
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

class PHPProxy extends BaseProxy
{
	private $srcCaller = null;
	
	/**
	 * (non-PHPdoc)
	 * @see \lessp\fr\api\BaseProxy::init()
	 */
	function init($conf, $params)
	{
		$confroot = $conf['conf_path'];
		$apiroot = $conf['api_path'];
		$apins = $conf['api_ns'];
		
		$mod = $this->getMod();
		//支持多级的子模块
		$modseg = explode('/', $mod);
		$nsprefix = implode('\\', $modseg);
		//自动加载app mod conf
		Conf::load($confroot.$modseg[0].'.conf.php');
		$modseg =  array_map('ucfirst',$modseg);
		//类名以每一级单词大写开始
		$class = implode('', $modseg).'Export';
		$path = $apiroot.$mod.'/'.$modseg[count($modseg)-1].'Export.php';
		require_once $path;
		
		$class = $apins.$nsprefix.'\\'.$class;
		$this->srcCaller = new $class($params);
	}
	
	function call($name,$args)
	{
		$ret = call_user_func_array(array($this->srcCaller,$name),$args);
		return $ret;
	}
}