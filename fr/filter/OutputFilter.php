<?php

/**
 *  
 * @filesource        OutputFilter.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 输出处理的过滤器
 * @example     
 */

final class OutputFilter implements IFilter
{
	/**
	 * (non-PHPdoc)
	 * @see IFilter::execute()
	 */
	function execute(WebApp $app)
	{
		$to = $app->request->oe;
		if ($app->encoding !== $to) {
			require_once FR_ROOT.'util/Encoding.php';
			Encoding::convertArray($app->response->outputs, $to, $app->encoding);
			Encoding::convertArray($app->response->headers, $to, $app->encoding);
		}
		$app->response->send();
	
		$enableTask = Conf::get('lessp.task_enable', true);
		if ($enableTask) {
			// 将后续请求转入后台处理
			fastcgi_finish_request();
			$app->isTask = true;
			ini_set('max_execution_time', intval(Conf::get('lessp.task_timeout', 60 * 10)));
		}
		
		// 如果有定义的需要在后台处理的内容，则在此处理
		if ($app->response->tasks) {
			// 添加时已经强制检查过是否可以调用
			foreach($app->response->tasks as $callback) {
				call_user_func_array($callback[0], $callback[1]);
			}
		}
	}
}