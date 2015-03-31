<?php

namespace lessp\fr\lib\calendar;


const CALENDAR_STYLE_DEFAULT = 0;

// 上个月
const CALENDAR_MONTH_LAST = -1;
// 当前月
const CALENDAR_MONTH_CURRENT = 0;
// 下个月
const CALENDAR_MONTH_NEXT = 1;

require __DIR__.'/Lunar.php';
/**
 *
 * @copyright 	Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @filesource 		newfile.php
 * @author 		ronnie<comdeng@live.com>
 * @since 		2014年12月26日上午10:32:20
 * @version 	1.0
 */
final class Calendar 
{
	private $year;
	private $month;
	private $grid;
	
	private $today;
	private $conf;
	
	private $styles = array(
		CALENDAR_STYLE_DEFAULT => 'default',
	);
	
	/**
	 * 
	 * @var Lunar
	 */
	private $lunar;
	
	/**
	 * 构造函数
	 * @param int $year
	 * @param int $month
	 * @param array $conf 其他配置
	 * <code>array(
	 * 	'changeUrl', 	// 更换年份/月份的网址：包含{year}、{month}三个可替换变量
	 *  'dayUrl', 		// 点击日期的网址：包含{year}、{month}、{day}三个可替换变量
	 * )</code> 
	 */
	function __construct($year, $month, $conf = array())
	{
		$this->year 	= $year;
		$this->month 	= $month;
		
		$now = time();
		$this->today = array(date('Y', $now), date('m', $now), date('d', $now));
		
		$this->lunar = new Lunar();
		$this->conf = $conf;
	}
	
	/**
	 * 渲染
	 * @param int|string $style 使用哪种风格 也可以传入模板的路径
	 * @return string
	 */
	function render($style = CALENDAR_STYLE_DEFAULT)
	{
		if (is_int($style)) {
			if (!isset($this->styles[$style])) {
				throw new \Exception('calendar.styleNotFound style='.$style);
			}
			$path = __DIR__.'/tmpl/'.$this->styles[$style].'.phtml';
		} else {
			if (!is_readable($style)) {
				throw new \Exception('calendar.styleNotRedeable file='.$style);
			}
			$path = $style;
		}
		
		
		$render = new CalendarRender();
		$render->year = $this->year;
		$render->month = $this->month;
		$render->grids = $this->getGrids();
		
		$render->weekdays = array(
			'一',
			'二',
			'三',
			'四',
			'五',
			'六',
			'日',
		);
		$render->conf = $this->conf;
		
		return $render->render($path);
	}
		
	/**
	 * 获取该月的格子
	 * @return
	 */
	function getGrids()
	{
		// 获取第1天是第几天
		$firstDay = mktime(0, 0, 0, $this->month, 1, $this->year);
		
		$info = date('w,z,t', $firstDay);
		//   一周的第几天,一年的第几天,一年的第几周,   当月有多少天
		list($numOfWeek, $dayOfYear, $dayNumOfMonth) = explode(',', $info);
		
		$days = array();
		
		$lunarDays = $this->lunar->convertSolarMonthToLunar($this->year, $this->month);
		$weekNum = 0;
		$days[$weekNum] = array(
			'list'	=> array(),
		);
		if ($numOfWeek > 1) {
			for($i = 1; $i < $numOfWeek; $i++) {
				$date = $firstDay - 24 * 3600 * ($dayNumOfMonth - $i);
				$day = date('d', $date);
				list($year, $month, $day) = explode(',', date('Y,m,d', $date));
				$linfo = $this->lunar->convertSolarToLunar($year, $month, $day);
				
				$isToday = $year == $this->today[0] && $month == $this->today[1] && $day == $this->today[2];
				
				$days[$weekNum]['list'][] = array(
					'type'	=> CALENDAR_MONTH_LAST,
					'stamp'	=> $date, 
					'isToday' => $isToday,
					'solar' => array(
						'year' 	=> date('Y'), 
						'month' => date('m'),
						'day' 	=> $day,
					),
					'lunar'	 => array(
						'year'	=> $linfo[0],
						'month'	=> $linfo[1],
						'day'	=> $linfo[2],
						'firstOfMonth' => $linfo[5] == 1
					),
				);
			}
		}
		// 计算所有日期
		for($i = 1; $i <= $dayNumOfMonth; $i++) {
			if (count($days[$weekNum]['list']) >= 7) {
				$weekNum++;
				$days[$weekNum] = array(
					'list'	=> array(),
				);
			}
			
			$linfo = $lunarDays[$i];
			$isToday = $this->year == $this->today[0] && $this->month == $this->today[1] && $i == $this->today[2];
			$days[$weekNum]['list'][] = array(
				'type'	=> CALENDAR_MONTH_CURRENT,
				'stamp' => mktime(0, 0, 0, $this->month, $i, $this->year),
				'isToday' => $isToday,
				'solar' => array(
					'year' 	=> $this->year,
					'month'	=> $this->month,
					'day'	=> $i,
				),
				'lunar'	 => array(
					'year'	=> $linfo[0],
					'month'	=> $linfo[1],
					'day'	=> $linfo[2],
					'firstOfMonth' => $linfo[5] == 1
				),	
			);
		}
		
		// 如果最后一周的日期未填满
		if ( ( $num = count($days[$weekNum]['list']) ) < 7 ) {
			$lastDay = $days[$weekNum]['list'][$num - 1]['stamp'];
			for($i = $num; $i < 7; $i++) {
				$date = $lastDay + ($i - $num + 1) * 24 * 3600;
				list($year, $month, $day) = explode(',', date('Y,m,d', $date));
				$linfo = $this->lunar->convertSolarToLunar($year, $month, $day);
				
				$isToday = $year == $this->today[0] && $month == $this->today[1] && $day == $this->today[2];
				
				$days[$weekNum]['list'][] = array(
					'type'	=> CALENDAR_MONTH_NEXT,
					'stamp'	=> $date,
					'isToday' => $isToday,
					'solar' => array(
						'year' 	=> $year,
						'month' => $month,
						'day' 	=> $day,
					),
					'lunar'	 => array(
						'year'	=> $linfo[0],
						'month'	=> $linfo[1],
						'day'	=> $linfo[2],
						'firstOfMonth' => $linfo[5] == 1
					),
				);
			}
		}
		
		// 计算出每周的周次
		foreach($days as $key => &$week) {
			$fdOfWeek = $week['list'][0]['stamp'];
			$week['weekOfYear'] = intval(date('W', $fdOfWeek));
		}
		
		return $days;
	}
}

final class CalendarRender
{
	var $year;
	var $month;
	var $grids;
	var $weekdays;
	var $conf;
	
	function render($path)
	{
		ob_start();
		include $path;
		return ob_get_clean();
	}
}
