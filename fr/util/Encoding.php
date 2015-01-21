<?php

namespace lessp\fr\util;

/**
 *  
 * @filesource        Encoding.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 
 * @example     
 */

final class Encoding
{
	/**
	 * 将值进行编码转换
	 * @param string $value
	 * @param string $to
	 * @param string $from
	 * @return string
	 */
	static function convert($value,$to,$from)
	{
		if ($value == null) {
			return $value;
		}
		//有ccode库
		$isccode = function_exists('is_gbk');
		//专门有处理gbk/utf8转码的扩展，解决一些badcase
		if ($to === 'GBK' && ($from === 'UTF-8' || $from === 'UTF8') && $isccode) {
			$v = utf8_to_gbk($value, strlen($value), UCONV_INVCHAR_REPLACE);
			if ($v !== false) {
				return $v;
			} else {
				Logger::warning('utf8_to_gbk fail str=%s', bin2hex($value));
			}
		}
		if (($to === 'UTF-8' || $to === 'UTF8') && $from === 'GBK' && $isccode) {
			$v = gbk_to_utf8($value, strlen($value), UCONV_INVCHAR_REPLACE);
			if ($v !== false) {
				return $v;
			} else {
				Logger::warning('gbk_to_utf8 fail str=%s', bin2hex($value));
			}
		}
		//mb_convert会由于字符编码问题出fatal，改成iconv //ignore模式
		return iconv($from, $to.'//ignore', $value);
	}
	
	/**
	 * 将数组进行编码转换
	 * @param array $arr
	 * @param string $to
	 * @param string $from
	 */
	static function convertArray(array &$arr, $to, $from)
	{
		foreach($arr as $key=>&$value) {
			if (is_array($value)) {
				self::convertArray($value, $to, $from);
			} elseif(is_string($value)) {
				$value = self::convert($value, $to, $from);
			}
		}
	}
}