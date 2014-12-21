<?php

namespace lessp\fr\filter;

use lessp\fr\app\WebApp;
use lessp\fr\http\UrlDispatcher;
use lessp\fr\log\Logger;
/**
 *  
 * @file        UrlFilter.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 
 * @example     
 */

final class UrlFilter implements IFilter
{
	function execute(WebApp $app)
	{
		$url = strtolower($app->request->url);
		
		require_once FR_ROOT.'http/UrlDispatcher.php';
		$dispatcher = new UrlDispatcher($app);
		
		Logger::debug('load url dispatcher:DefaultURLDispatch,'.$url);
		$dispatcher->dispatch($url);
		return true;
	}
}