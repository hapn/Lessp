<?php
/**
 *  
 * @file        Timer.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 计时器工具
 * @example     
 */

namespace lessp\fr\util;

final class Timer
{
	private $stats = array();

	function __construct()
	{
		$this->start = microtime(true);
	}

	/**
	 * 开始一个或多个计数器，允许传入多个计数器的名称。
	 * @example 
	 * ```php
	 * $timer = new lessp\util\Timer();
	 * $timer->begin($name1, $name2, $name3);
	 * ```
	 */
	function begin()
	{
		$phases = func_get_args();
		$now = microtime(true);
		foreach($phases as $phase) {
			if (!isset($this->stats[$phase])) {
				$this->stats[$phase] = array($now, 0);
			}
		}
	}

	/**
	 * 结束计数器的统计，允许传入多个计数器的名称。
	 * @example 
	 * ```php
	 * // someting
	 * $timer->end($name1, $name2, $name3);
	 * ```
	 */
	function end()
	{
		$phases = func_get_args();
		$now = microtime(true);
		foreach($phases as $phase) {
			if (isset($this->stats[$phase])) {
				$this->stats[$phase][1] = $now;
			}
		}
	}

	/**
	 * 结束所有计数器的统计
	 */
	function endAll()
	{
		$now = microtime(true);
		foreach($this->stats as $phase=>&$stat) {
			if ($stat[1] === 0) {
				$stat[1] = $now;
			}
		}
	}

	/**
	 * 获取所有计数器的耗时
	 * 
	 * @param boolean $end 是否结束所有的计时器
	 * @return array 
	 * ```php
	 * array(
	 *   $name => $cost,  // $name: 计时器名称，$cost: 消耗时间（飞秒）
	 * )
	 * ```
	 */
	function getCosts($end = true)
	{
		if ($end) {
			$this->endAll();
		}
		$result = array();
		foreach($this->stats as $phase=>$stat) {
			if ($stat[1]) {
				$result[$phase] = intval(($stat[1]-$stat[0]) * 1000000);
			}
		}
		return $result;
	}
}