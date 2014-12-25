<?php

namespace fillp\fr\util;

/**
 * 用来对php类的注释进行分析 
 * @file		PhpDocs.php
 * @author		ronnie<dengxiaolong@jiehun.com.cn>
 * @date		2014/12/24 23:12:35
 * @version		1.0
 * @copyright	Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 */

class PhpDoc
{
	/**
	 * 分析方法
	 * @param \ReflectionMethod $method
	 */
	static function anaMethod( \ReflectionMethon $method )
	{
		$result = self::anaComment($method->getDocComment());

		if (!isset($result['name'])) {
			$result['name'] = $name;
		}
		
		if (isset($result['desc'])) {
			$result['desc'] = implode("", $result['desc']);
		} else {
			$result['desc'] = '';
		}
		
		if (!empty($result['param'])) {
			$params = $result['param'];
			$result['param'] = array();
			$lastKey = '';
			foreach($params as $param) {
				if (!$param) {
					continue;
				}
				// 以\0开头的表示是对上一条参数的注释
				if ($param[0] == "\0") {
					if ($lastKey != '') {
						$result['param'][$lastKey]['desc'][] = mb_substr($param, 1);
					}
				} else {
					$arr = preg_split("/[\s \t\r\n]+/", trim($param, ' '));
					if (count($arr) < 2) {
						continue;
					}
					$type = array_shift($arr);
					$lastKey = array_shift($arr);
					
					$result['param'][$lastKey] = array(
						'desc' => array(implode(' ', $arr)),
						'type' => $type,
						'name' => $lastKey,
					);
				}
			}
			
			foreach($result['param'] as $key => $value) {
				$result['param'][$key]['desc'] = implode("", $value['desc']);
			}
		} else {
			$result['param'] = array();
		}
		
		if (!empty($result['return'])) {
			$first = array_shift($result['return']);
			$return = $result['return'];
			$arr = preg_split("/\s+/", trim($first));
			if (count($arr) >= 1) {
				$type = array_shift($arr);
				$result['return'] = array(
					'type' => $type,
					'desc' => array(implode(' ', $arr)),
				);
				
				
				foreach($return as $value) {
					$result['return']['desc'][] = substr($value, 1);
				}
				$result['return']['desc'] = implode("", $result['return']['desc']);
			} else {
				$result['return'] = array();
			}
		}
	}

	/**
	 * 分析评论
	 * @param string $comment
	 * @return array
	 */
	static function anaComment($comment)
	{
		if (!$comment) {
			return array();
		}
		$prefix = substr($comment, 0, 2);
		if ($prefix == '//') {
			$ret = self::_anaShortComment(substr($comment, 2));
		} else if ($prefix == '/*') {
			$ret = self::_anaLongComment(substr($comment, 2, -2));
		} else {
			throw new Exception('phpdoc.illegalComment');
		}
		if (!isset($ret['desc'])) {
			if (isset($ret['description'])) {
				$ret['desc'] = $ret['description'];
				unset($ret['description']);
			} else {
				$ret['desc'] = '';
			}
		}
		return $ret;
	}
	
	/**
	 * 分析短的评论
	 * @param string $comment
	 */
	private static function _anaShortComment($comment)
	{
		return array(
			'desc' => $comment,
		);
	}
	
	/**
	 * 分析长的评论
	 * @param string $comment
	 */
	private static function _anaLongComment($comment)
	{
		$cs = explode('*', preg_replace('#\t#m', '    ', $comment));
		$cs = array_diff($cs, array(''));
		$ret = array();
		$lastKey = 'desc';
		foreach($cs as $line) {
			if (preg_match('#^\s*@([a-z][a-zA-Z0-9]+)\s*(.*)#', $line, $matches)) {
				$key = $matches[1];
				$value = $matches[2];
				$ret[$key][] = $value;
				$lastKey = $key;
			} else {
				$ret[$lastKey][] = "\0".$line;
			}
		}
		
		return $ret;
	}
}
