<?php

namespace lessp\fr\db;

require_once __DIR__.'/TxScope.php';
require_once __DIR__.'/DbImpl.php';

/**
 * ription 数据库类
 * 
 * @copyright   Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file        Db.php
 * @author      ronnie<dengxiaolong@jiehun.com.cn>
 * @date        2014-12-23
 * @version 1.0
 */
/**
 * mysql的压缩算法模拟实现
 * 
 * @param string $data        	
 * @return string
 */
function mysql_compress($data) {
	$lenbin = pack ( 'L', strlen ( $data ) );
	return $lenbin . gzcompress ( $data, 7 );
}
/**
 * mysql的解压缩算法的模拟实现
 * 
 * @param string $data        	
 * @return string
 */
function mysql_uncompress($data) {
	$len = unpack ( 'L', substr ( $data, 0, 4 ) );
	return gzuncompress ( substr ( $data, 4 ), $len [1] );
}
final class DbContext {
	static $db_pool;
	static $dbconf;
	// 分表设置，配置格式为
	// array(table=>array(field,array(method=>arg)));
	//
	static $splits;
	static $textDB;
	static $textTable;
	static $textDbOld;
	static $compressLen;
	static $maxTextLen;
	static $guidDB;
	static $guidTable;
	static $readOnly = false;
	static $logFunc;
	static $testMode = false;
	static $defaultDB;
}
// 分库或分表方法
// ID取模
const SPLIT_MOD = 1 ;
// ID区间分表
const SPLIT_DIV = 2 ;
// 按月分表
const SPLIT_MONTH = 4 ;
// 按年分表
const SPLIT_YEAR = 8 ;
// 按填分表
const SPLIT_DAY = 16 ;

/**
 * 将一段文本转化为64位整数
 * 
 * @param string $s        	
 * @return int
 */
function create_sign64($s) {
	$hash = md5 ( $s, true );
	$high = substr ( $hash, 0, 8 );
	$low = substr ( $hash, 8, 8 );
	$sign = $high ^ $low;
	$sign1 = hexdec ( bin2hex ( substr ( $sign, 0, 4 ) ) );
	$sign2 = hexdec ( bin2hex ( substr ( $sign, 4, 4 ) ) );
	return ($sign1 << 32) | $sign2;
}
final class Db {
	static $dbs = array ();
	static $txDbs = array ();
	
	/**
	 * 设置整个DB库的只读模式。
	 * 
	 * @param s $readonly
	 *        	boolean 如果设置为true，则只能查询，不能提交
	 */
	static function setReadOnly($readonly = false) {
		DbContext::$readOnly = $readonly;
	}
	
	/**
	 * 配置初始化函数
	 *
	 * @todo 修改配置项说明
	 * @param s $conf
	 *        	array 配置项如下：
	 *        	text_db 必填
	 *        	text_table 必填
	 *        	text_compress_len 可选
	 *        	guid_db 可选
	 *        	guid_table 可选
	 *        	max_text_len 可选
	 *        	splits 可选
	 *        	log_func 可选
	 *        	test_mode 可选
	 *        	db_pool=>array(
	 *        	'db11'=>array(
	 *        	'ip'=>'ip',
	 *        	'port'=>3306,
	 *        	'user'=>'user',
	 *        	'pass'=>'pass',
	 *        	'charset'=>'charset'
	 *        	),
	 *        	'db2'=>xxx
	 *        	....
	 *        	),
	 *        	'dbs'=>array(
	 *        	'dbname'=>'db1',
	 *        	'dbname'=>array('master'=>'db1','slave'=>array('db2','db3'))
	 *        	)
	 */
	static function init($conf) {
		// check db conf format
		foreach ( $conf ['dbs'] as $db => $dbconf ) {
			if (is_string ( $dbconf )) {
				if (! isset ( $conf ['db_pool'] [$dbconf] )) {
					throw new \Exception ( 'db.ConfError ' . $dbconf . ' no such pool in db_pool' );
				}
			} else {
				if (! isset ( $dbconf ['master'] ) || ! isset ( $dbconf ['slave'] )) {
					throw new \Exception ( 'db.ConfError missing master|slave conf ' . $db );
				}
				$master = $dbconf ['master'];
				$slaves = $dbconf ['slave'];
				if (! isset ( $conf ['db_pool'] [$master] )) {
					throw new \Exception ( 'db.ConfError ' . $master . ' no such pool in db_pool' );
				}
				foreach ( $slaves as $slave ) {
					if (! isset ( $conf ['db_pool'] [$slave] )) {
						throw new \Exception ( 'db.ConfError ' . $slave . ' no such pool in db_pool' );
					}
				}
			}
		}
		
		DbContext::$db_pool = $conf ['db_pool'];
		DBContext::$dbconf = $conf ['dbs'];
		
		DbContext::$textDB = $conf ['text_db'];
		DbContext::$textTable = $conf ['text_table'];
		
		DbContext::$textDbOld = ! empty ( $conf ['text_db_old'] ) ? $conf ['text_db_old'] : "";
		
		DbContext::$compressLen = empty ( $conf ['text_compress_len'] ) ? 1024 : intval ( $conf ['text_compress_len'] );
		DbContext::$maxTextLen = empty ( $conf ['max_text_len'] ) ? 65535 : intval ( $conf ['max_text_len'] );
		
		DbContext::$guidDB = empty ( $conf ['guid_db'] ) ? NULL : $conf ['guid_db'];
		DbContext::$guidTable = empty ( $conf ['guid_table'] ) ? NULL : $conf ['guid_table'];
		
		DbContext::$defaultDB = empty ( $conf ['default_db'] ) ? NULL : $conf ['default_db'];
		
		// 转换成小写的，免得因为大小写问题比较不成功
		DbContext::$splits = empty ( $conf ['splits'] ) ? array () : $conf ['splits'];
		// 文本库分表规则只能是 不分表.按10取模，按100取模，其他不支持
		if (! empty ( DbContext::$splits [DbContext::$textTable] )) {
			$splits = DbContext::$splits [DbContext::$textTable];
			$modv = $splits [1] [SPLIT_MOD];
			if ($modv !== 10 && $modv !== 100) {
				throw new \Exception ( 'db.TextTableSplitError' );
			}
		}
		
		DbContext::$logFunc = empty ( $conf ['log_func'] ) ? null : $conf ['log_func'];
		DbContext::$testMode = ! empty ( $conf ['test_mode'] );
	}
	
	/**
	 * 获取一个DB实例对象,目前实现为DbImpl
	 * 没法实现复用db，外面get的使用自己节约点用
	 * 别任意浪费
	 * 
	 * @param s $db_name
	 *        	string
	 */
	static function get($db_name = null) {
		if (empty ( $db_name )) {
			$db_name = DbContext::$defaultDB;
		}
		$db_name = strtolower ( $db_name );
		if (! empty ( TxScope::$txDbs [$db_name] ) && ! empty ( self::$txDbs [$db_name] )) {
			// 如果对db_name启用了事务，则复用db对象
			return self::$txDbs [$db_name];
		}
		if (! isset ( DBContext::$dbconf [$db_name] )) {
			throw new \Exception ( 'db.ConfError no db conf ' . $db_name );
		}
		$conf = array ();
		if (is_string ( DBContext::$dbconf [$db_name] )) {
			// db_name只配了一个地址
			$poolname = DBContext::$dbconf [$db_name];
			// 从db_pool里把ip/port/user/pass这些取出来
			$conf ['master'] = DBContext::$db_pool [$poolname];
		} else {
			// db_name配置了主从结构
			$poolconf = DBContext::$dbconf [$db_name];
			$mastername = $poolconf ['master'];
			$conf ['master'] = DBContext::$db_pool [$mastername];
			foreach ( $poolconf ['slave'] as $slave ) {
				// 从db_pool里把ip/port/user/pass这些取出来
				$conf ['slave'] [] = DBContext::$db_pool [$slave];
			}
		}
		$db = new DbImpl ( $db_name, $conf );
		self::$dbs [$db_name] [] = $db;
		
		if (! empty ( TxScope::$txDbs [$db_name] )) {
			// 启用db_name事务的话就保留一下连接
			self::$txDbs [$db_name] = $db;
		}
		
		return $db;
	}
	static function close() {
		foreach ( self::$txDbs as $dbname => $db ) {
			$db->rollback ();
		}
		self::$txDbs = array ();
		foreach ( self::$dbs as $dbname => $arrdb ) {
			foreach ( $arrdb as $db ) {
				$db->rollback ();
			}
		}
		self::$dbs = array ();
	}
	
	/**
	 * 新分配一个全局id，返回分配到的id
	 * 
	 * @param s $name
	 *        	string，全局id名称，这个名称必须在全局数据库中的表种已经创建好
	 * @param s $count
	 *        	int,分配id的个数。如果count>1，则会返回最后一个id
	 */
	static function newGUID($name, $count = 1) {
		if (empty ( DbContext::$guidDB )) {
			throw new \Exception ( 'db.GUIDError not support' );
		}
		// guid分配和db无关，这里借用一下textdb
		$db = self::get ( DbContext::$guidDB );
		$count = intval ( $count );
		if ($count < 1) {
			throw new \Exception ( "db.guid error count" );
		}
		$req = array (
				'name' => $name,
				'count' => $count 
		);
		if (DbContext::$logFunc) {
			$log = '[GUID][NAME:' . $name . '][COUNT:' . $count . ']';
			call_user_func ( DbContext::$logFunc, $log );
		}
		if (DbContext::$testMode) {
			return 1;
		}
		$db->forceMaster ( true );
		$changeRows = $db->queryBySql ( 'UPDATE ' . DbContext::$guidDB . '.' . DbContext::$guidTable . ' set guid_value = LAST_INSERT_ID(guid_value+?) where guid_name = ?', $count, $name );
		if (! $changeRows) {
			throw new \Exception ( 'db.newGuid error guid_name:' . $name );
		}
		$res = $db->queryBySql ( 'SELECT LAST_INSERT_ID() as ID' );
		$lastId = intval ( $res [0] ['ID'] );
		return $lastId - $count + 1;
	}
	
	/**
	 * 向文本库中提交一批文本数据
	 * 
	 * @param s $textdata
	 *        	mixed 可以是单个string或者string数组
	 * @return 返回单个id或者是id数组
	 */
	static function commitText($textdata) {
		$db = self::get ( DbContext::$textDB );
		$arr = is_array ( $textdata ) ? $textdata : array (
				$textdata 
		);
		$ret = array ();
		foreach ( $arr as $text ) {
			if (! $text) {
				$ret [] = 0;
				continue;
			}
			
			$id = self::create_sign64 ( $text );
			if ($id < 0) {
				$id = sprintf ( '%u', $id );
			}
			$compressed = 0;
			$len = strlen ( $text );
			if ($len > 256 * 1024) {
				// 肯定不存储超过256k的内容
				throw new \Exception ( 'db.TextTooLong ' . $len );
			}
			$_text = $text;
			if ($len > DbContext::$compressLen) {
				$text = mysql_compress ( $text );
				$len = strlen ( $text );
				if ($len == 0) {
					throw new \Exception ( "db.TextCompressFail" );
				}
				$compressed = 1;
			}
			if ($len > DbContext::$maxTextLen) {
				throw new \Exception ( 'db.TextTooLong ' . $len );
			}
			$db->table ( DbContext::$textTable )->saveBody ( array (
					'text_id' => $id,
					'text_content' => $text,
					'text_zip_flag' => $compressed 
			) );
			if (! empty ( DbContext::$splits [DbContext::$textTable] )) {
				$splits = DbContext::$splits [DbContext::$textTable] [1];
				$db->tsplit ( $id, $splits );
			}
			$db->insertIgnore ();
			
			// 通过memcached缓存text
			if (($cacheTime = intval ( Conf::get ( 'hapn.rtext_cache_time', 0 ) )) > 0) {
				Com::get ( 'cache' )->set ( 'rtext.' . $id, $_text, $cacheTime );
			}
			
			$ret [] = $id;
		}
		return is_array ( $textdata ) ? $ret : $ret [0];
	}
	
	/**
	 * 查询文本数据.
	 * 
	 * @param s $textId
	 *        	string|array 要查询的文本id
	 * @return 如果传入的文本id为单个id，则返回单个文本或者null。如果传入的为array，则返回结果array
	 */
	static function queryText($textId) {
		$arrId = is_array ( $textId ) ? $textId : array (
				$textId 
		);
		$db = self::get ( DbContext::$textDB );
		// 先根据分表把id归组
		$db->table ( DbContext::$textTable );
		if (empty ( DbContext::$splits [DbContext::$textTable] )) {
			// 如果没有分表，则全部归为一个组，一次查询出来
			$arrTable = array (
					DbContext::$textTable => $arrid 
			);
		} else {
			$arrTable = array ();
			foreach ( $arrId as $id ) {
				$table = $db->getSplitTable ( $id, DbContext::$splits [DbContext::$textTable] [1] );
				$arrTable [$table] [] = $id;
			}
		}
		$arrRet = array ();
		
		$cacheTime = intval ( Conf::get ( 'hapn.rtext_cache_time', 0 ) );
		if ($cacheTime > 0) {
			$cache = Com::get ( 'cache' );
		}
		// 规矩归组的id执行查询
		foreach ( $arrTable as $table => $batchid ) {
			$arr = array ();
			// 通过memcached缓存text
			if ($cacheTime > 0) {
				$cacheTextIds = array ();
				foreach ( $batchid as $key => $id ) {
					$cacheTextIds [] = 'rtext.' . $id;
				}
				$result = $cache->get ( array_unique ( $cacheTextIds ) );
				if ($result) {
					foreach ( $batchid as $key => $id ) {
						if (isset ( $result ['rtext.' . $id] )) {
							$arr [] = array (
									'text_id' => $id,
									'text_zip_flag' => 0,
									'text_content' => $result ['rtext.' . $id] 
							);
							unset ( $batchid [$key] );
						}
					}
				}
			}
			
			$cacheIds = array ();
			$notExistedIds = array ();
			if (! empty ( $batchid )) {
				$_arr = $db->table ( $table )->field ( 'text_id', 'text_content', 'text_zip_flag' )->in ( 'text_id', $batchid )->get ();
				$cacheIds = LibUtil::load ( 'EXArray' )->extractList ( $_arr, 'text_id' );
				// 求出没有找到的id
				$notExistedIds = array_diff ( $batchid, $cacheIds );
				
				$arr = array_merge ( $arr, $_arr );
			}
			
			// 将没有找到的rtext字段从旧库中获取
			if ($notExistedIds && DbContext::$textDbOld != '') {
				$_arr = self::get ( DbContext::$textDbOld )->table ( $table )->field ( 'text_id', 'text_content', 'text_zip_flag' )->in ( 'text_id', $notExistedIds )->get ();
				$cacheIds = array_merge ( $cacheIds, LibUtil::load ( 'EXArray' )->extractList ( $_arr, 'text_id' ) );
				// 将通过旧库找到的textId插入到新库
				foreach ( $_arr as $_row ) {
					$db->table ( DbContext::$textTable )->saveBody ( $_row );
					if (! empty ( DbContext::$splits [DbContext::$textTable] )) {
						$splits = DbContext::$splits [DbContext::$textTable] [1];
						$db->tsplit ( $_row ['text_id'], $splits );
					}
					$db->insertIgnore ();
				}
				
				$arr = array_merge ( $arr, $_arr );
			}
			
			foreach ( $arr as $row ) {
				if (1 == $row ['text_zip_flag']) {
					// 压缩了，需要解压
					$text = mysql_uncompress ( $row ['text_content'] );
				} else {
					$text = $row ['text_content'];
				}
				$arrRet [$row ['text_id']] = $text;
				if ($cacheTime > 0 && ! empty ( $cacheIds ) && in_array ( $row ['text_id'], $cacheIds )) {
					$cache->set ( 'rtext.' . $row ['text_id'], $text, $cacheTime );
				}
			}
		}
		// 重新组织文本的排序
		$ret = array ();
		foreach ( $arrId as $id ) {
			if (isset ( $arrRet [$id] )) {
				$ret [$id] = $arrRet [$id];
			}
		}
		if (is_array ( $textId ))
			return $ret;
		if (isset ( $ret [$textId] ))
			return $ret [$textId];
		return null;
	}
}