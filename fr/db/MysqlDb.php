<?php

namespace lessp\fr\db;

/**
 * ription
 * 
 * @copyright Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 *            @file			MysqlDb.php
 * @author ronnie<dengxiaolong@jiehun.com.cn>
 *         @date			2014-12-23
 * @version 1.0
 */
final class MysqlDb {
	private $master;
	private $slave;
	private $dbname;
	private $forceMaster = false;
	private $mdb;
	
	// 事务
	private $tx = false;
	private static $handleCache = array ();
	
	
	function __construct($dbname, $conf) {
		/**
		 * @poolconf 格式参考DB::init函数的 pool格式
		 * array(
		 * 'master'=>@poolconf,
		 * 'slave'=>array(@poolconf,....)
		 * )
		 */
		if (empty ( $conf ['master'] )) {
			throw new \Exception ( 'db.DbConfError missing master ' . $dbname );
		}
		$this->dbname = $dbname;
		$this->master = $conf ['master'];
		$this->slave = empty ( $conf ['slave'] ) ? array () : $conf ['slave'];
	}
	
	/**
	 * 创建连接
	 * @param array $conf
	 * @throws Exception
	 * @return 
	 */
	private function createConnection($conf) {
		$handle = mysqli_init ();
		$ret = mysqli_real_connect ( $handle, $conf ['ip'], $conf ['user'], $conf ['pass'], $this->dbname, $conf ['port'] );
		if (! $ret) {
			mysqli_close ( $handle );
			throw new \Exception ( 'db.ConnectError ' . mysqli_error ( $handle ) );
		}
		mysqli_set_charset ( $handle, $conf ['charset'] );
		return $handle;
	}
	
	/**
	 * 获取DB处理器
	 * @param boolean $isRead
	 * @param string $hash
	 * @return 
	 */
	function getDbHandle($isRead, $hash) {
		if ($this->isTxBegin () && $this->mdb) {
			// 事务状态下直接返回事务连接
			return $this->mdb;
		}
		if (! $isRead || 		// 写操作
		empty ( $this->slave ) || 		// 没有从库
		$this->isTxBegin () || 		// 事务操作
		$this->forceMaster) { // 强制为Master
			
			$key = $this->getCacheKey ( $this->master );
			if (isset ( self::$handleCache [$key] )) {
				// 如果已经建立了连接,则复用
				$handle = self::$handleCache [$key];
			} else {
				$handle = $this->createConnection ( $this->master );
			}
			if (! $this->isTxBegin ()) {
				// 没有开启事务的状态下缓存连接，便于后续复用。
				self::$handleCache [$key] = $handle;
			} else {
				// 暂时保存事务连接，并且从cache中清除，不要让别的查询进入本事务。
				$this->mdb = $handle;
				unset ( self::$handleCache [$key] );
			}
			return $handle;
		} else {
			// 第一级cache，根据db复用
			$key = 'slave_' . $this->dbname;
			if (isset ( self::$handleCache [$key] )) {
				return self::$handleCache [$key];
			}
			
			// 一致性hash算法选择一个从库
			$index = floor ( ($hash % 360) / (360 / count ( $this->slave )) );
			$slave = $this->slave [$index];
			
			// 第二级cache根据ip.user.port复用
			$key = $this->getCacheKey ( $slave );
			if (isset ( self::$handleCache [$key] )) {
				return self::$handleCache [$key];
			}
			$handle = $this->createConnection ( $slave );
			self::$handleCache [$key] = $handle;
			
			return $handle;
		}
	}
	
	/**
	 * 获取缓存key
	 * @param string $conf
	 * @return string
	 */
	function getCacheKey($conf) {
		return $conf ['ip'] . $conf ['user'] . $conf ['port'];
	}
	
	/**
	 * 查询结果
	 * @param string $db
	 * @param array $sqls
	 * @param boolean $multi
	 * @throws Exception
	 * @return 
	 */
	function queryResult($db, $sqls, $multi) {
		$sql = implode ( $sqls, ';' );
		if ($multi) {
			if (! mysqli_multi_query ( $db, $sql )) {
				throw new \Exception ( 'db.QueryError ' . mysqli_error ( $db ) );
			}
			$results = array ();
			do {
				if (mysqli_field_count ( $db )) {
					
					if (false === ($rhandle = mysqli_store_result ( $db ))) {
						throw new \Exception ( 'db.QueryError ' . mysqli_error ( $db ) );
					}
					
					$rows = array ();
					while ( $row = mysqli_fetch_row ( $rhandle ) ) {
						$rows [] = $row;
					}
					$results [] = array (
							'fields' => $rows 
					);
					mysqli_free_result ( $rhandle );
				} else {
					// 如果是更新的语句，返回受影响行数就可以了
					$results [] = array (
							'affacted_rows' => mysqli_affected_rows ( $db ),
							'fields' => array () 
					);
				}
			} while ( mysqli_more_results ( $db ) && mysqli_next_result ( $db ) );
			
			return $results;
		} else {
			if (! ($rhandle = mysqli_query ( $db, $sql ))) {
				throw new \Exception ( 'db.QueryError ' . mysqli_error ( $db ) );
			}
			if (mysqli_field_count ( $db )) {
				$rows = array ();
				// 循环获取数据
				while ( ($row = mysqli_fetch_assoc ( $rhandle )) ) {
					$rows [] = $row;
				}
				mysqli_free_result ( $rhandle );
				$ret = array (
						'fields' => $rows 
				);
				// 看看select後面是否跟著SQL_CALC_FOUND_ROWS
				if (strtoupper ( substr ( $sql, 7, 19 ) ) === 'SQL_CALC_FOUND_ROWS') {
					$foundRow = $this->query ( 'SELECT FOUND_ROWS() as  _row' );
					$ret ['found_rows'] = $foundRow [0] ['fields'] [0] ['_row'];
				}
				return array (
						$ret 
				);
			} else {
				
				$num = mysqli_affected_rows ( $db );
				return array (
						array (
								'affected_rows' => $num,
								'fields' => array () 
						) 
				);
			}
		}
		return $ret;
	}
	
	/**
	 * 强制使用主库
	 * @param string $force
	 */
	function forceMaster($force = true) {
		$this->forceMaster = $force;
	}
	
	/**
	 * 执行一个查询
	 * 参数为执行查询需要的sql 值等信息，格式如下
	 * query('select');
	 * query('select', $arg,$arg);
	 * query(array(
	 * array('select')
	 * ));
	 * query(array(
	 * array('select1', arg,$arg),
	 * array('select2', arg,$arg),
	 * ));
	 */
	function query($sqlInfo) {
		if (empty ( $sqlInfo )) {
			throw new \Exception ( 'db.NotAllowEmptyQuery' );
		}
		if ((isset ( TxScope::$txDbs [$this->dbname] ) || TxScope::$tx) && ! $this->tx) {
			// 如果开启了全局事务并且当前还没有执行过事务
			$this->beginTx ();
		}
		$isMulti = is_array ( $sqlInfo [0] );
		$sqls = $this->buildQuery ( $sqlInfo );
		// LOG SQL
		if (DbContext::$logFunc) {
			$log = '[DB:' . $this->dbname . '][SQL:' . implode ( ';', $sqls ) . ']';
			call_user_func ( DbContext::$logFunc, $log );
		}
		if (DbContext::$testMode) {
			// 测试模式，直接返回空结果
			return array (
					array (
							'fields' => array (),
							'affected_rows' => 0,
							'found_rows' => 0 
					) 
			);
		}
		global $__Lessp_appid;
		if ($__Lessp_appid) {
			// 在HapN框架的一个特殊支持，不用再HapN下也无影响
			$__Lessp_appid ++;
		}
		$isRead = $this->isRead ( $sqls );
		$db = $this->getDbHandle ( $isRead, crc32 ( $sqls [0] ) );
		$results = $this->queryResult ( $db, $sqls, $isMulti );
		return $results;
	}
	
	/**
	 * 开始查询
	 * @param string $queryInfo
	 * @throws Exception
	 * @return 
	 */
	private function buildQuery($queryInfo) {
		if (is_string ( $queryInfo )) {
			$querys = array (
					array (
							$queryInfo 
					) 
			);
		} elseif (is_array ( $queryInfo [0] )) {
			$querys = $queryInfo [0];
		} else {
			$querys = array (
					$queryInfo 
			);
		}
		$ret = array ();
		foreach ( $querys as $query ) {
			$sql = array_shift ( $query );
			if (! $sql) {
				throw new \Exception ( 'db.NotAllowEmptyQuery' );
			}
			if (isset ( $query [0] ) && is_array ( $query [0] )) {
				// 如果第一个是数组，则认为所有的参数都在这个数组里面
				$query = $query [0];
			}
			$argnum = count ( $query );
			if (substr_count ( $sql, '?' ) > $argnum) {
				throw new \Exception ( "db.MysqlSqlParam:$sql Error" );
			}
			if ($argnum > 0) {
				$format_sql = str_replace ( '?', '%s', $sql );
				$ret [] = vsprintf ( $format_sql, $this->escapeValues ( $query ) );
			} else {
				$ret [] = $sql;
			}
		}
		return $ret;
	}
	
	/**
	 * 将值的数组进行编码
	 * @param array $arr
	 * @return array
	 */
	function escapeValues(array $arr) {
		foreach ( $arr as &$v ) {
			$v = $this->escapeValue ( $v );
		}
		return $arr;
	}
	
	/**
	 * 将值进行编码
	 * @param string $value
	 * @throws Exception
	 * @return 
	 */
	function escapeValue($value) {
		if (is_int ( $value )) {
			return $value;
		} elseif (is_string ( $value )) {
			$hex_value = bin2hex ( $value );
			return "unhex('$hex_value')";
		} elseif (is_numeric ( $value )) {
			if (0 == $value) {
				return "'0'";
			}
			return $value;
		} elseif (is_null ( $value )) {
			return 'NULL';
		} elseif (is_bool ( $value )) {
			// 布尔值返回1/0
			return $value ? 1 : "'0'";
		} elseif (is_array ( $value ) && isset ( $value ['exp'] ) && is_string ( $value ['exp'] )) {
			// 支持字段值为exp表达式
			return $value ['exp'];
		} else {
			throw new \Exception ( 'db.EscapeValue not support type' );
		}
	}
	
	/**
	 * 是否为读的sql
	 * @param string $sql
	 * @return boolean
	 */
	private function isRead($sql) {
		if (is_array ( $sql )) {
			foreach ( $sql as $s ) {
				if (! $this->isRead ( $s )) {
					return false;
				}
			}
			return true;
		}
		
		/* 判断该sql语句的前六个字符是否是 SELECT */
		$sql = strtoupper ( $sql );
		if (strncmp ( 'SELECT', $sql, 6 ) === 0 || strncmp ( 'DESC', $sql, 4 ) === 0) {
			return true;
		}
		return false;
	}
	
	/**
	 * 启动事务
	 * @throws Exception
	 * @return 
	 */
	function beginTx() {
		if ($this->tx) {
			throw new \Exception ( 'db.Transaction already begins' );
		}
		$this->tx = true;
		try {
			return $this->query ( 'START TRANSACTION' );
		} catch ( Exception $ex ) {
			$this->tx = false;
			throw $ex;
		}
	}
	
	/**
	 * 停止事务
	 * @param string $sql
	 * @return 
	 */
	private function stopTrans($sql) {
		if (! $this->tx) {
			return;
		}
		
		$ret = $this->query ( $sql );
		$this->tx = false;
		
		// 释放事务连接
		$key = $this->getCacheKey ( $this->master );
		self::$handleCache [$key] = $this->mdb;
		$this->mdb = null;
		
		return $ret;
	}
	/**
	 * 提交
	 * @return 
	 */
	function commit() {
		return $this->stopTrans ( 'COMMIT' );
	}
	/**
	 * 回滚
	 * @return 
	 */
	function rollback() {
		return $this->stopTrans ( 'ROLLBACK' );
	}
	
	/**
	 * 是否启动了事务
	 * @return boolean
	 */
	function isTxBegin() {
		return $this->tx;
	}
	
	/**
	 * 删除缓存，用来重新请求mysql资源
	 */
	static function destroyHandleCache() {
		self::$handleCache = array ();
	}
}