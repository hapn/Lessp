<?php
namespace lessp\lib\calendar\test;

use lessp\fr\lib\calendar\Lunar;
use lessp\fr\lib\calendar\Calendar;
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			Calendar.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014年12月26日上午11:01:18
 * @version		    1.0
 */

class TestCalendar extends \PHPUnit_Framework_TestCase
{
	function testGet()
	{
// 		require_once dirname(__DIR__).'/Lunar.php';
// 		$lunar = new Lunar();
// 		$obj = $lunar->convertSolarToLunar(date('Y'), 11, 1);
// 		print_r($obj);
		
// 		$days = $lunar->getSolarMonthDays(date('Y'), date('m'));
// 		print_r($days);
		
// 		$days = $lunar->convertSolarMonthToLunar(date('Y'), date('m'));
// 		print_r($days);
		
		require_once dirname(__DIR__).'/Calendar.php';
		$calendar = new Calendar(date('Y'), date('m'));
// 		$grid = $calendar->getMonthGrid();
// 		print_r($grid);

		$html = $calendar->render();
		var_dump($html);
	}
}
