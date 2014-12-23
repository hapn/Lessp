<?php

namespace lessp\fr\db;

/**
*   @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
*   @file			DbImpl.php
*   @author			ronnie<dengxiaolong@jiehun.com.cn>
*   @date			2014-12-23
*   @version		1.0
*   @description 	数据库实现类
*/

class DbImpl
{
	private $dbimpl;
	
	private $dbname;
	private $foundRows = 0;
	private $table = NULL;
	private $object = NULL;
	private $where = NULL;
	private $between = NULL;
	private $like = NULL;
	private $field = NULL;
	private $order = NULL;
	private $group = NULL;
	private $in = NULL;
	private $in_v = NULL;
	private $asc = true;
	private $start = -1;
	private $limit = 0;
	private $save = NULL;
	private $unique_f = NULL;
	private $uniqId = NULL;
	private $time = NULL;
	private $t_split = NULL;
	private $t_split_method = NULL;
	
	private $followedSql = NULL;
	private $followedResult = NULL;
	
	//是否需要getLastInsertId
	private $autoinc = false;
	
	function __construct($dbname,$gdpconf)
	{
		$this->dbname = $dbname;
		require_once __DIR__.'/MysqlDb.php';
		$this->dbimpl = new MysqlDb($dbname,$gdpconf);
	}
	
	private function initDbBase() {
		$this->table = NULL;
		$this->object = NULL;
		$this->where = NULL;
		$this->between = NULL;
		$this->like = NULL;
		$this->field = NULL;
		$this->order = NULL;
		$this->in = NULL;
		$this->in_v = NULL;
		$this->asc = true;
		$this->start = -1;
		$this->limit = 0;
		$this->save = NULL;
		$this->unique_f = NULL;
		$this->t_split = NULL;
		$this->t_split_method = NULL;
		$this->group = NULL;
	
		$this->followedSql = NULL;
	}
	
	private function checkDbTable() {
		if(empty($this->table)) {
			throw new \Exception( "db.LibDbTableEmpty" );
		}
	}
	
	/**
	 * 设置是否强制查询主库.
	 * @params $force boolean
	 */
	public function forceMaster($force=true)
	{
		$this->dbimpl->forceMaster($force);
		return $this;
	}
	
	/**
	 * 根据分表规则获取分表名称
	 * @params mixed $value 分表字段值
	 * @params array $split_method 分表方式 array(split_mod=>value);
	 */
	public function getSplitTable($value, $split_method)
	{
		if(empty($value)) {
			return $this->table;
		}
		if(empty($split_method) || !is_array($split_method)) {
			throw new \Exception('db.SplitMethodNotSet');
		}
		foreach($split_method as $split => $split_value) {
			break;
		}
		switch($split){
			case DbContext::MOD_SPLIT:
				if ($split_value == 10 || $split_value == 100 || $split_value == 100) {
					//防止value过大没法除
					$v = substr(''.$value, -(strlen($split_value)-1));
					if ($v === false) {
						$subfix = intval($value);
					} else {
						$subfix = intval($v);
					}
				} else {
					$subfix = $value % $split_value;
				}
				break;
			case DbContext::DIV_SPLIT:
				$subfix = round($value / $split_value);
				break;
			case DbContext::YEAR_SPLIT:
				$subfix = date('Y', $value);
				break;
			case DbContext::MONTH_SPLIT:
				$subfix = date('Ym', $value);
				break;
			case DbContext::DAY_SPLIT:
				$subfix = date('Ymd', $value);
				break;
			default:
				throw new \Exception('db.NotSupportTableSplit');
		}
		return $this->table.$subfix;
	}
	
	/**
	 * 根据当前的DB设置获取查询的SQL语句
	 * @params boolean $is_calc_found_rows 否则计算所有影响行数。default: false
	 * @return SQL语句
	 */
	public function getPrepare($is_calc_found_rows = false)
	{
		$table = $this->getSplitTable($this->t_split, $this->t_split_method);
		if((empty( $this->in ) || empty( $this->in_v )) &&
		empty( $this->where ) && empty($this->between) &&
		empty( $this->like) && $this->start == -1) {
			//不支持不设置in where between like limit的查询，方式代码的失误导致查询了一个很大的表
			throw new \Exception('db.MysqlGetBothInAndWhereNotExist');
		}
		if(empty( $this->field )) {
			$fields = '*';
		} else {
			$fields = implode( ',', $this->field);
		}
		if(!$is_calc_found_rows) {
			$sql = 'SELECT ' .$fields. ' FROM ' . $this->dbname. '.' . $table;
		} else {
			$sql = 'SELECT SQL_CALC_FOUND_ROWS ' .$fields. ' FROM ' . $this->dbname. '.' . $table;
		}
		$sql .= $this->buildWhere(true);
		return $sql;
	}
	
	/**
	 * 获取更新操作需要的SQL语句
	 * @params string $action 更新操作。目前支持：insert/update/delete/insertIgnore/insertOrUpdate
	 * @return SQL语句
	 */
	public function genUpdateSql($action)
	{
		$sql = '';
		$table = $this->getSplitTable($this->t_split, $this->t_split_method);
		if(strncmp($action,'insert',6) == 0) {
			//insert系列的
			if (empty($this->save)) {
				//如果都没数据可以插入的，那还能怎样？
				throw new \Exception('db.EmptySaveBody');
			}
			$sql .= $action==='insertIgnore'?'INSERT IGNORE INTO ':'INSERT INTO ';
			$sql .= $this->dbname . "." . $table;
			if ($action === 'insertBatch') {
				$arr_values = array_values( $this->save );
				$arr_fields = array_keys( $arr_values[0] );
				$arr_escape_values = array();
				foreach($arr_values as $key => $values) {
					$arr_escape_values[] = $this->dbimpl->escapeValues($values);
				}
			} else {
				$arr_fields = array_keys( $this->save );
				$arr_escape_values = $arr_values = array_values( $this->save );
				foreach( $arr_escape_values as &$value ) {
					if( !$this->rawData($value) ) {
						$value = $this->dbimpl->escapeValue($value);
					}
				}
			}
			$sql .= '(' . implode( ',', $arr_fields ) . ') ';
			if ($action === 'insertBatch') {
				$sql .= ' VALUES';
				$valuesArr = array();
				foreach($arr_escape_values as $key => $values) {
					$valuesArr[] = '('. implode(',', $values).')';
				}
				$sql .= implode(',', $valuesArr);
			} else {
				$sql .= ' VALUES(' . implode( ',', $arr_escape_values ) . ')';
			}
				
			if($action === 'insertOrUpdate') {
				//拼上update部分
				$str = '';
				for($i=0;isset($arr_fields[$i]); $i++) {
					$str .= $arr_fields[$i] . '=' . $arr_escape_values[$i] . ',';
				}
				//删除最后一个逗号,
				$str = substr( $str, 0, -1 );
				$sql .= ' ON DUPLICATE KEY UPDATE ' . $str;
			}
			return $sql;
		} elseif($action === 'update') {
			if (empty($this->save)) {
				//没有数据更新，也没法干了
				throw new \Exception('db.EmptySaveBody');
			}
			$sql .= 'UPDATE ' . $this->dbname . "." . $table. ' SET ';
			/* 设置需要更新的字段 */
			foreach( $this->save as $field => $value ) {
				if( !$this->rawData($value) ) {
					$sql .= $field . '=' . $this->dbimpl->escapeValue( $value ) . ',';
				} else {
					$sql .= $field . '=' . $value . ',';
				}
			}
			if(!empty($this->save)) {
				$sql = substr( $sql, 0,  -1);
			}
			$where = $this->buildWhere(false);
			if (!$where) {
				throw new \Exception('db.UpdateAllTableError');
			}
			return $sql.$where;
		} elseif($action === 'delete') {
			$sql .= 'DELETE FROM ' . $this->dbname . "." . $table;
			$where = $this->buildWhere(false);
			if (!$where) {
				throw new \Exception('db.DeleteAllTableError');
			}
			return $sql.$where;
		} else {
			throw new \Exception('db.UnknownUpdateMethod');
		}
	}
	
	private function rawData(&$value)
	{
		if( !is_string($value) ) return false;
		$prefix = substr($value, 0, 2);
		$useRaw = false;
		switch($prefix) {
			case '0b':
				if (preg_match('{^0b[01]+$}', $value)) {
					$useRaw = true;
				}
				break;
			case '0x':
				if (preg_match('{^0x[0-9a-fA-F]+$}', $value)) {
					$useRaw = true;
					$value = strtoupper($value);
				}
				break;
		}
		return $useRaw;
	}
	
	/**
	 * 构建查询语句
	 * @param 是否用来select查询 $forselect
	 * @param 是否用来计数 $forCount
	 * @throws Exception
	 */
	private function buildWhere($forselect=true, $forCount=false)
	{
		$sql = '';
		if(!empty($this->in ) && !empty( $this->in_v )) {
			$in_v = $this->dbimpl->escapeValues($this->in_v);
			$sql .= $this->in.' IN (' . implode( ',', $in_v ) . ')';
		}
		if(!empty( $this->where ) && is_array( $this->where )) {
			if(!empty($sql)) {
				$sql .= ' AND ';
			}
			$cw_cnt = count( $this->where );
			$cnt = 0;
			foreach( $this->where as $key => $value ) {
				if(is_array( $value )) {
					/* range > and < */
					if(2 != count( $value )) {
						//where条件里也支持exp表达式
						$sql .= " $key=".$this->dbimpl->escapeValue( $value ) . ' AND ';
						continue;
					}
					if(is_null($value[0]) && is_null($value[1])){//如果范围条件全是null
						throw new \Exception("db.ArgWhereRangeBothNull");
					}
					if(isset( $value [0] )) {
						$sql .= " $key>" . $this->dbimpl->escapeValue( $value [0] );
					}
					if(isset( $value [1] )) {
						$sql .= isset( $value [0] ) ? ' AND ' : '';
						$sql .= " $key<" . $this->dbimpl->escapeValue( $value [1] );
					}
				} else {
					if(is_null($value)){
						$sql .= " $key is NULL";
					}else{
						if (is_int($key)) {
							$sql .= $value;
						} else {
							$sql .= " $key=" . $this->dbimpl->escapeValue( $value );
						}
					}
				}
				$sql .= ' AND ';
			} //end of foreach
			//去掉最后的 AND
			$sql = substr($sql, 0, -4);
		} elseif(! empty( $this->where )) {
			if(!empty($sql)) {
				$sql .= ' AND ';
			}
			$sql .= $this->where;
		}
		if (!empty($this->between)) {
			if(!empty($sql)) {
				$sql .= ' AND ';
			}
			$sql .= ' '.$this->between[0].' between ';
			$sql .= $this->dbimpl->escapeValue($this->between[1]).' and ';
			$sql .= $this->dbimpl->escapeValue($this->between[2]);
		}
		if (!empty($this->like)) {
			if(!empty($sql)) {
				$sql .= ' AND ';
			}
			$sql .= ' '.$this->like[0].' like '.$this->dbimpl->escapeValue($this->like[1]);
		}
		if($sql) {
			$sql = ' WHERE '.$sql;
		}
		if (!$forCount) {
			if(!empty($this->group)){
				$sql .= ' GROUP BY '.$this->group;
			}
			if(!empty($this->order) && ($forselect || $this->limit > 0)) {
				$sql .= " ORDER BY {$this->order}";
				//如果指定asc为null，则可以自己指定排序
				$sql = $sql . (($this->asc === null)?'':($this->asc?' ASC' : ' DESC'));
			}
			if($forselect) {
				if( $this->start >= 0){
					$sql .= ' LIMIT '.$this->start.', '.$this->limit;
				}
			} else {
				if( $this->limit > 0){
					$sql .= ' LIMIT '.$this->limit;
				}
			}
		}
		return $sql;
	}
	private function prepareText(&$arr_body)
	{
		if (!$arr_body) return;
		$arr_text = array();
		foreach( $arr_body as $key => $value ) {
			if('r' == $key [0] && '_' == $key [2]) {
				if(is_int( $value )) {
					continue;
				}
				/* v字段的处理 只提交非数值型的请求 */
				/* 判断是否为空,需注意'0'的情况 */
				if((empty( $value ) && '0' !== $value) || "\0" === $value) {
					/* 如果为空串,msg_id置为0 */
					$arr_body[$key] = 0;
				} else {
					if('a' == $key [1]) {
						/* ra_ means store array,indeed a pack will be stored */
						if(!is_array($value )) {
							throw new \Exception( "db.SystaVaField: ra_ field NotArray " );
						}
						$pack = json_encode($value);
						$arr_text [$key] = $pack;
					} else {
						$arr_text [$key] = $value;
					} // if ra
				} //if is_string
			}
		} /* foreach */
		foreach( $arr_text as $key => $value ) {
			$ret_id = Db::commitText( $value );
			$arr_body [$key] = $ret_id;
		}
	}
	
	private function prepareGuid(&$arr_body)
	{
		if(empty($this->unique_f)) return;
		foreach($this->unique_f as $uniq) {
			if (!empty($arr_body[$uniq])) {
				//有id就不生成了
				continue;
			}
			$guid = Db::newGUID($uniq);
			$arr_body[$uniq] = $guid;
			//保留最后那个
			$this->uniqId = $guid;
		}
	}
	
	/**
	 * 检查是否是只读模式
	 */
	public function checkReadOnly()
	{
		if(DbContext::$readOnly) {
			throw new \Exception( "db.DbAllowReadonly" );
		}
	}
	
	/**
	 * 提交文本。详情参考: Db::commitText($texts);
	 */
	public function commitText($arr_text)
	{
		return Db::commitText($arr_text);
	}
	/**
	 * 滩檠谋尽Ｏ昵椴慰� Db::queryText($textid);
	 */
	public function queryText($arr_signid)
	{
		return Db::queryText($arr_signid);
	}
	
	/**
	 * 在执行各种DB操作(insert/update/delete等)后，需要尾随执行的一个SQL语句
	 * 比如执行select found_rows(); select last_insert_id()等等。
	 * 通常这个功能用于：执行一次数据操作，然后需要调用一个查询马上获取这个操作的结果。并且这两次查询需要在同一连接中
	 * @params string $sql sql语句
	 */
	public function follow($sql)
	{
		$this->followedSql = $sql;
		return $this;
	}
	/**
	 * 设置auto increament字段。
	 * 设置此字段后，在insert之后可以通过调用getLastInsertId接口获取字段值
	 * 此接口不同于unique接口功能，unique是通过guid分配一个全局id，而此接口是使用数据库自增列.
	 * @params string $field  字段名称
	 */
	public function autoInc($field)
	{
		$this->follow('SELECT LAST_INSERT_ID() as LID');
		$this->autoinc = true;
		return $this;
	}
	
	/**
	 * 获取最后生成的guid值。
	 * 这个值为提交数据前为unique字段分配的值，跟数据库中的auto_increament是不一样的含义.
	 * 获取一次后值会被清空，不能多次获取.
	 * 如果没有设置unique而使用了autoInc，则会返回autoInc值
	 */
	public function getLastInsertId()
	{
		if ($this->uniqId) {
			$unique = $this->uniqId;
			$this->uniqId = NULL;
			return $unique;
		} elseif ($this->autoinc) {
			$ret = $this->followedResult;
			$this->followedResult = NULL;
			$this->autoinc = false;
			$vs = array_values($ret['fields'][0]);
			return intval($vs[0]);
		}
	}
	
	/**
	 * 获取查询语句受影响的函数。
	 * 这个函数结合is_calc_found_rows模式来使用，只用当使用了这种模式来查询
	 * 才能通过此函数获取到相应的值
	 */
	public function getFoundRows()
	{
		$rows = $this->foundRows;
		$this->foundRows = 0;
		return $rows;
	}
	
	function beginTx() { $this->dbimpl->beginTx(); }
	function commit() { $this->dbimpl->commit(); }
	function rollback() { $this->dbimpl->rollback(); }
	
	/*
	 * 设置表名
	*/
	public function table($table) {
		$this->initDbBase();
		$this->table = $table;
		return $this;
	}
	
	/*
	 * 设置对象名
	*/
	public function object($class) {
		$this->initDbBase();
		$this->object = new $class();
		$this->table = $this->object->table;
		return $this;
	}
	/*
	 * 设置where条件
	* @params $arr_where array(key=>value)或者"key=value"
	*/
	public function where($arr_where) {
		$this->checkDbTable();
		if ($this->where) {
			if (is_string($this->where)) {
				$this->where = array($this->where);
			}
			if (is_array($arr_where)) {
				foreach($arr_where as $key=>$value) {
					if (is_int($key)) {
						$this->where[] = $value;
					} else {
						$this->where[$key] = $value;
					}
				}
			} else if (is_string($arr_where)) {
				$this->where[] = $arr_where;
			}
		} else {
			$this->where = $arr_where;
		}
		return $this;
	}
	
	/**
	 * 设置sql的between子句
	 */
	public function between($field, $min, $max)
	{
		$this->checkDbTable();
		$this->between = array($field,$min,$max);
		return $this;
	}
	
	/**
	 * 设置sql的like子句, like '%value%'
	 */
	public function like($field, $value)
	{
		$this->like = array($field,$value);
		return $this;
	}
	
	/*
	 * 设置in条件，前一个参数为in字段名，仅支持一个字符串；后一个字段为取值数组
	*/
	public function in($in, $arr_in_value) {
		$this->checkDbTable();
		if(empty($in) || empty( $arr_in_value ) || !is_array( $arr_in_value )) {
			throw new \Exception( "db.InParam:$in " . print_r( $arr_in_value, true ) . "Error" );
		}
		$this->in = $in;
		$this->in_v = $arr_in_value;
		return $this;
	}
	/*
	 * 设置查询order条件。
	* @params $order 排序设置，可以是字段名，可以可以是整个order子句
	*   例如: "id asc,time desc"
	* @params $asc 升序还是降序 true/false，如果设置为null,是认为$order参数为排序子句
	*/
	public function order($order, $asc = true) {
		$this->checkDbTable();
		$this->order = $order;
		$this->asc = $asc;
		return $this;
	}
	/**
	 * [group 设置查询的group by条件]
	 * @param  [string] $fields [字段名，可以是多个]
	 * @return [object]         [DB对象]
	 */
	public function group($fields) {
		$this->checkDbTable();
		$this->group = $fields;
		return $this;
	}
	/*
	 * 设置查询field，支持数组或字符串
	*/
	public function field($arr_field) {
		$this->checkDbTable();
		if(! is_array( $arr_field )) {
			$arr_field = func_get_args();
		}
		$this->field = $arr_field;
		return $this;
	}
	/*
	 * 设置查询limit,必须整数
	*/
	public function limit($start, $limit) {
		$this->checkDbTable();
		$start = intval($start);
		$limit = intval($limit);
		if(-1 >= $start && 0 >= $limit)
			throw new \Exception( "db.LimitParam:$start Error" );
		$this->start = $start;
		$this->limit = $limit;
		return $this;
	}
	
	/**
	 * @deprecated This function is deprecated, pls use saveBody instead.
	 */
	public function save($arr_save) {
		return $this->saveBody($arr_save);
	}
	
	/**
	 * 设置更新字段数组
	 * @params $arr_save array  key=>value格式
	 */
	public function saveBody($arr_save){
		$this->checkDbTable();
		if(! is_array( $arr_save )) {
			throw new \Exception( "db.SaveBodyParam:$arr_save NotArray" );
		}
		$this->save = $arr_save;
		return $this;
	}
	private function parseField($fields)
	{
		if (is_string($fields)) {
			return array($fields);
		}
	
		if (is_array($fields)) {
			//有点坑爹，外面可能传field=>0的方式，也可能传array(field1,field2)的方式
			//都是为了兼容性啊
			if (isset($fields[0])) {
				return $fields;
			} else {
				return array_keys($fields);
			}
		}
		throw new \Exception('db.FieldNotSupport');
	}
	/**
	 * 设置需要分配guid的字段
	 */
	public function unique($field) {
		$this->checkDbTable();
		$this->unique_f = $this->parseField($field);
		return $this;
	}
	
	/*
	 * 设置分表属性和分库方法
	* @params $split_value mixed 分表字段值
	* @params $split 分表设置
	*/
	public function tsplit($split_value, $split){
		$this->checkDbTable();
		if(!empty($split_value)){
			if(is_array($split_value))
				throw new \Exception("db.MysqlTableSplitValueMustNotBeArray");
			if(is_null($split) || !is_array($split) || 0 == count($split))
				throw new \Exception("db.MysqlTableSplitMethodNotSet");
			$this->t_split = $split_value;
			$this->t_split_method = $split;
		}
		return $this;
	}
	
	
	private function executeUpdate($action)
	{
		$this->checkReadOnly();
		//主要处理文本字段，全局id和时间字段
		$this->prepareGuid($this->save);
		$this->prepareText($this->save);
		$sql = $this->genUpdateSql($action);
		if ($this->followedSql) {
			$sql = array(array(
					array($sql),
					array($this->followedSql)
			));
		}
		$res = $this->dbimpl->query($sql);
		if ($this->followedSql) {
			//保存第二条SQL的结果
			$this->followedResult = $res[1];
		}
		//这个格式的返回值主要为了兼容以前systa的返回值
		$ret = array();
		if ($this->time) {
			foreach($this->time as $key) {
				$ret[$key] = $this->save[$key];
			}
		}
		if ($this->unique_f) {
			foreach($this->unique_f as $key) {
				$ret[$key] = $this->save[$key];
			}
		}
		if (isset($res[0]['affected_rows'])) {
			$ret['affected_rows'] = $res[0]['affected_rows'];
		}
	
		$this->initDbBase();
		return $ret;
	}
	
	/**
	 * 执行插入
	 */
	public function insert() {
		return $this->executeUpdate('insert');
	}
	/**
	 * 执行更新
	 */
	public function update() {
		return $this->executeUpdate('update');
	}
	/**
	 * 执行插入。INSERT IGNORE INTO模式
	 */
	public function insertIgnore() {
		return $this->executeUpdate('insertIgnore');
	}
	
	/**
	 * 执行插入。INSERT ON DUPLICATE KEY UPDATE 模式
	 */
	public function insertOrUpdate() {
		return $this->executeUpdate('insertOrUpdate');
	}
	
	/**
	 * 执行批量插入。
	 * 要求saveBody中的数据多一个维度
	 */
	public function insertBatch()
	{
		return $this->executeUpdate('insertBatch');
	}
	
	/**
	 * @deprected 和update函数一样的功能
	 */
	public function updateBatch() {
		return $this->executeUpdate('update');
	}
	
	/**
	 * 执行删除功能
	 */
	public function delete() {
		return $this->executeUpdate('delete');
		//$this->initDbBase();
		//throw new \Exception( "db.SystaNotSupportDeleteAction" );
	}
	
	/**
	 * 执行sql查询，支持批量的sql
	 * @params $sql string|array, 查询信息，支持如下几种格式的查询:
	 *  queryBySql('select...');
	 *  queryBySql('select...? ?',$arg1,$arg2);
	 *  queryBySql(array(
	 *      array('select...? ?',$arg1,$arg2),
	 *      array('select ..?',$arg1)
	 *  ));
	 *  @return 如果执行的是批量查询 则返回多个结果记录，否则返回单个结果集.
	 */
	public function queryBySql($sql) {
		$results = $this->dbimpl->query(func_get_args());
		$this->formatData($results);
		if(is_array($sql)) {
			//返回一个结果集列表
			return $results;
		} elseif (empty($results[0])) {
			//没有查询到结果
			return array();
		}
		$result = $results[0];
		if(isset($result['found_rows'])) {
			$this->foundRows = intval($result['found_rows']);
		} elseif(isset($result['affected_rows'])) {
			//更新操作返回影响行数就可以了
			return intval($result['affected_rows']);
		}
		return $result['fields'];
	}
	
	/**
	 * select for update功能
	 */
	public function getForUpdate($calc_found_rows=false)
	{
		return $this->get($calc_found_rows,true);
	}
	
	/**
	 * 执行一个常规的查询操作.查询参数就是通过where in limit order等接口所设置的
	 * @params boolean | int $calc_found_rows 是否统计受影响行数，默认通过另外查询一次计算得出，要使用SQL_CALC_FOUND_ROWS得明确使用2
	 */
	public function get($calc_found_rows=false,$forupdate=false) {
		$this->foundRows = 0;
		$use_sql_calc = $calc_found_rows === 2;
		$sql = $this->getPrepare($use_sql_calc);
		if ($forupdate) {
			$sql .= ' FOR UPDATE';
		}
		$results = $this->dbimpl->query($sql);
		$this->formatData($results);
	
		if ($use_sql_calc) {
			$this->foundRows = $results[0]['found_rows'];
		} else if($calc_found_rows) {
			// 有结果或者不是从第一个记录查起，才需要获取数目
			if (!empty($results) || $this->limit > 0) {
				$this->foundRows = $this->getCount();
			}
		}
		$result = $results[0]['fields'];
		if(empty( $this->object )) {
			$this->initDbBase();
			return $result;
		} else {
			$arr_object = array();
			foreach( $result as $arr_res_item ) {
				$object_res = clone $this->object;
				$object_res->arrValue = $arr_res_item;
				$object_res->__hit__ = true;
				$arr_object [] = $object_res;
			}
			$this->initDbBase();
			return $arr_object;
		}
	}
	
	private function formatData(&$ret)
	{
		$arrId = array();
		foreach($ret as &$result) {
			foreach($result['fields'] as &$row) {
				$hasv = false;
				foreach($row as $key=>$tid) {
					if (isset($key[2]) && 'r' == $key[0] && '_' == $key[2]) {
						if ($tid) {
							if (is_numeric($tid) ) {
								//如果id>0需要去查询内容
								$arrId[] = $tid;
							}
						} else {
							$row[$key] = '';
							$row['_'.$key] = 0;
						}
						$hasv = true;
					}
				}
				//记录里面没有v字段，只需要扫描一行记录就知道是否包含v字段，如果第一行没有其他行肯定没有了
				if (!$hasv) break;
			}
		}
		if (!$arrId) {
			return;
		}
		$arrText = Db::queryText($arrId);
		foreach($ret as &$result) {
			foreach($result['fields'] as &$row) {
				$hasv = false;
				foreach($row as $key=>$tid) {
					if (isset($key[2]) && 'r' == $key[0] && '_' == $key[2]) {
						if (is_numeric($tid) && $tid) {
							//r字段
							if (isset($arrText[$tid])) {
								$tmpv = $arrText[$tid];
								if ('a' == $key[1]) {
									//ra_字段
									$row[$key] = json_decode($tmpv,true);
								} else {
									$row[$key] = $tmpv;
								}
							} else {
								//存在文本id但是没有查询到内容，可能把文本数据丢了
								$row[$key] = '';
								//打一个日志
								if (DbContext::$logFunc) {
									call_user_func(DbContext::$logFunc,'db.MissTextData text_id='.$tid);
								}
							}
							//把id保存下来
							$row['_'.$key] = $tid;
						} //if ($tid)
						$hasv = true;
					}
				} //foreach($row as $key=>$tid)
					
				//记录里面没有v字段
				if (!$hasv) break;
			}//foreach($result['fields'] as &$row)
		} //foreach($ret as &$result)
	
	}
	
	/**
	 * 查询出结果总数。
	 */
	public function getCount() {
		$table = $this->getSplitTable($this->t_split,$this->t_split_method);
		$sql = 'SELECT COUNT(1) AS CNT FROM '.$this->dbname.'.'.$table;
		//为了别拼limit语句了
		$this->start = -1;
		$sql = $sql . $this->buildWhere(true, true);
		$ret = $this->dbimpl->query($sql);
		$this->initDbBase();
		return intval($ret[0]['fields'][0]['CNT']);
	}
	
	/**
	 * 检查是否存在
	 */
	public function getExist() {
		$table = $this->getSplitTable($this->t_split,$this->t_split_method);
		$sql = 'SELECT 1 FROM '.$this->dbname.'.'.$table;
		$this->start = 0;
		$this->limit = 1;
		$sql = $sql . $this->buildWhere(true, true);
		$ret = $this->dbimpl->query($sql);
		$this->initDbBase();
		return !empty($ret[0]['fields'][0]);
	}
	
	private function prepareOSplit($object,$genguid=false)
	{
		if(empty($object->tbSpProperty)){
			return;
		}
		$key = $object->tbSpProperty;
		if(is_null($object->$key)) {
			//是否可以自动生成一个guid
			if ($genguid && in_array($key,$this->unique_f)) {
				$this->prepareGuid($this->save);
				//生成guid后再把数据写回object，方便后面使用
				$object->$key = $this->save[$key];
			} else {
				throw new \Exception( "db.TableSplitPropertyNotSet" );
			}
		}
		$this->tsplit($object->$key,$object->tbSpMethod);
	}
}
