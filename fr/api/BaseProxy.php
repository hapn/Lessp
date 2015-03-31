<?php

/**
 * 接口代理类
 * @example

```php
final class LogArgumentIntercepter implements IIntercepter
{
    function before(IProxy $proxy,$name,$args)
    {   
        Logger::trace('Api call:%s->%s args:%s',$proxy->getMod(),$name,serialize($args));
    }   

    function after(IProxy $proxy,$name,$args,$ret)
    {   
        Logger::trace('Api call:%s->%s ret:%s',$proxy->getMod(), $name, serialize($ret));
    }   

    function exception(IProxy $proxy,$name,$args)
    {   
    }   
}
```

 *
 */
interface IIntercepter
{
	/**
	 * 调用接口之前执行
	 * @param IProxy $proxy
	 * @param string $name 接口的名称
	 * @param array $args 传入的参数
	 */
	function before(IProxy $proxy, $name, $args);
	/**
	 * 调用接口之后执行
	 * @param IProxy $proxy
	 * @param string $name 接口的名称
	 * @param array $args 传入的参数
	 */
	function after(IProxy $proxy, $name, $args, $ret);
	/**
	 * 发生异常时调用
	 * @param IProxy $proxy
	 * @param string $name
	 * @param array $args
	 */
	function exception(IProxy $proxy, $name, $args);
}

/**
 * 代理类的抽象接口
 * @package lib\Api
 */
interface IProxy
{
	/**
	 * 获取模块
	 */
	function getMod();
	/**
	 * 初始化接口
	 * @param array $conf
	 * @param array $params 目标类构造函数参数，对HTTPRPC/PHP有效
	 */
	function init($conf, $params);
	/**
	 * 调用方法
	 * @param string $name
	 * @param array $args 参数
	 */
	function call($name, $args);
	
	/**
	 * 是否可缓存
	 */
	function cacheable();
}

/**
 *  
 * @filesource  BaseProxy.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 
 * @example    
 */

abstract class BaseProxy implements IProxy
{
	private $mod = null;
	
	function __construct($mod)
	{
		$this->mod = $mod;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IProxy::init()
	 */
	function init($conf, $params) {}
	
	/**
	 * (non-PHPdoc)
	 * @see IProxy::call()
	 */
	function call($name, $args) {
		return null;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IProxy::getMod()
	 */
	function getMod()
	{
		return $this->mod;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see IProxy::cacheable()
	 */
	function cacheable()
	{
		return true;
	}
}