<?php

namespace lessp\fr\api;

use lessp\fr\db\Db;
/**
 * 基本的PHP接口基类
 * @file        BaseExport.php
 * @author      ronnie<comdeng@live.com>
 * @date        2015年1月6日
 * @version     1.0
 * @copyright   Copyright (C) cc.nhap 2014 All rights reserved.
 * @description 
 * @example     
 */

abstract class PHPBaseExport
{
	protected $defaultDbName;
	protected $defaultTbName;
	protected $priKey;
	
	/**
	 * 
	 * @var \lessp\fr\db\DbImpl
	 */
	private $defaultDb;
	
	/**
	 * 获取默认的数据库
	 * @param string $tbName 如果传入NULL，则不会调用table方法。传入空字符串，则返回默认的table
	 * @return \lessp\fr\db\DbImpl
	 */
	protected function _getDefaultDb($tbName = '')
	{
		if (!$this->defaultDb) {
			$this->defaultDb = Db::get($this->defaultDbName);
		}
		if ($tbName === NULL) {
			return $this->defaultDb;
		}
		if ($tbName === '') {
			$tbName = $this->defaultTbName;
		}
		return $this->defaultDb->table($tbName);
	}
	
	/**
	 * 通过主键获取对象
	 * @param int|array $primaryId
	 * @param array $causes
	 * @param array $fields
	 * @return array|false
	 * 
	 * 如果提供的主键是单一的，则返回array或false（没有找到时）
	 * 如果提供的主键是一个数组，则返回array
	 */
	protected function _getById($primaryId, array $causes = array(), array $fields = array())
	{
		return $this->_getBy($this->priKey, $primaryId, $causes, $fields);	
	}
	
	/**
	 * 通过指定字段找到数据
	 * @param string $key
	 * @param mixed|array $value
	 * @param array $causes
	 * @param array $fields
	 * @param string $tbName
	 * @return array|false
	 * 
	 * 如果提供的$value是单一的，则返回array或false（没有找到时）
	 * 如果提供的$value是一个数组，则返回array
	 */
	protected function _getBy($key, $value, $causes = array(), $fields = array(), $tbName = '')
	{
		$db = $this->_getDefaultDb($tbName);
		$onlyOne = true;
		if (is_array($value)) {
			$db->in($key, $value);
			$onlyOne = false;
		} else {
			$db->where(array($key => $value))->limit(0, 1);
		}
		if (!empty($causes)) {
			$db->where($causes);
		}
		
		if (!empty($fields)) {
			$db->field($fields);
		}
		
		$rows = $db->get();
		
		if ($onlyOne) {
			if (!empty($rows)) {
				return $rows[0];
			}
			return false;
		}
		return $rows;
	}
}