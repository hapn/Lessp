<?php
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2015 All rights reserved.
 * @filesource		Cache.php
 * @author			ronnie<dengxiaolong@hunbasha.com>
 * @since			2015/3/25
 * @version		    1.0
 * @desc 			
 */
//
// +---------------------------------------------------------------------------+
// | memcached client, PHP                                                     |
// +---------------------------------------------------------------------------+
// | Copyright (c) 2003 Ryan T. Dean <rtdean@cytherianage.net>                 |
// | All rights reserved.                                                      |
// |                                                                           |
// | Redistribution and use in source and binary forms, with or without        |
// | modification, are permitted provided that the following conditions        |
// | are met:                                                                  |
// |                                                                           |
// | 1. Redistributions of source code must retain the above copyright         |
// |    notice, this list of conditions and the following disclaimer.          |
// | 2. Redistributions in binary form must reproduce the above copyright      |
// |    notice, this list of conditions and the following disclaimer in the    |
// |    documentation and/or other materials provided with the distribution.   |
// |                                                                           |
// | THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR      |
// | IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES |
// | OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.   |
// | IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,          |
// | INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT  |
// | NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY     |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT       |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF  |
// | THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.         |
// +---------------------------------------------------------------------------+
// | Author: Ryan T. Dean <rtdean@cytherianage.net>                            |
// | Heavily influenced by the Perl memcached client by Brad Fitzpatrick.      |
// |   Permission granted by Brad Fitzpatrick for relicense of ported Perl     |
// |   client logic under 2-clause BSD license.                                |
// +---------------------------------------------------------------------------+
//

/**
 * memcached client class implemented using (p)fsockopen()
 * 
 * @package lib\cache
 * @author  Ryan T. Dean <rtdean@cytherianage.net>
 * @package memcached-client
 */
final class Memcached
{

	//压缩
	CONST FLAG_ZIP = 0x01;
	//序列化
	CONST FLAG_SERIALIZE = 0x02;
	/**
	 * Cached Sockets that are connected
	 *
	 * @var     array
	 */
	static $_sock = array();
	 
	/**
	 * Do we want to use compression?
	 *
	 * @var     boolean
	 * @access  private
	 */
	private $_compress_enable;
	 
	/**
	 * At how many bytes should we compress?
	 *
	 * @var     interger
	 * @access  private
	 */
	private $_compress_threshold;
	 
	/**
	 * Array containing ip:port or array(ip:port, weight)
	 *
	 * @var     array
	 * @access  private
	 */
	private $_servers;
	 
	/**
	 * dump log function
	 *
	 * @var method
	 */
	private $_func;
	 
	/**
	 * 重试次数
	 *
	 * @var int
	 */
	private $_retry;

	private $_connect_timeout;
	private $_rw_timeout;
	/**
	 * Memcache initializer
	 *
	 * @param   array    $args    Associative array of settings
	 *
	 * @return  mixed
	 * @access  public
	 */
	private function __construct($args)
	{
		$this->_servers = $args['servers'];
		$this->_func = !empty($args['log_func'])?$args['log_func']:null;
		$this->_compress_threshold = $args['zip_threshold'];
		$this->_compress_enable = function_exists("gzcompress");

		$this->_retry = isset($args['retry'])?intval($args['retry']):3;
		$this->_connect_timeout = $args['connect_timeout'];
		$this->_rw_timeout = $args['rw_timeout'];
	}
	
	/**
	 * 获取缓存
	 * @param string $conf
	 * @throws Exception
	 * @return Jh_Cache
	 */
	static function load($conf = NULL)
	{
		if (!$conf) {
			$conf = Conf::get('cache');
		}
		if (empty($conf)) {
			throw new Exception('cache.noConfFound');
		}
		
		return new Memcached($conf);
	}

	function log($line,$type) 
	{
		if ($this->_func) {
			$log = "Cache: $type:".$line;
			call_user_func($this->_func, $log);
		}
	}
	
	/**
	 * Adds a key/value to the memcache server if one isn't already set with
	 * that key
	 *
	 * @param   string   $key     Key to set with data
	 * @param   mixed    $val     Value to store
	 * @param   interger $exp     (optional) Time to expire data at
	 *
	 * @return  boolean
	 * @access  public
	 */
	function add ($key, $val, $exp = 0)
	{
		return $this->_set('add', $key, $val, $exp);
	}

	/**
	 * Decriment a value stored on the memcache server
	 *
	 * @param   string   $key     Key to decriment
	 * @param   interger $amt     (optional) Amount to decriment
	 *
	 * @return  mixed    FALSE on failure, value on success
	 * @access  public
	 */
	function decr($key, $amt=1)
	{
		return $this->_incrdecr('decr', $key, $amt);
	}
	private function fireError($sock,$code)
	{
		$err = socket_last_error($sock);
                throw new Exception("$code $err:".socket_strerror($err));
	}

	/**
	 * Deletes a key from the server, optionally after $time
	 *
	 * @param   string   $key     Key to delete
	 * @param   interger $time    (optional) How long to wait before deleting
	 *
	 * @return  boolean  TRUE on success, FALSE on failure
	 * @access  public
	 */
	function delete($key, $time = 0)
	{
		$sock = $this->get_sock($key);

		$cmd = "delete $key $time\r\n";
		$this->log($cmd,'cmd');
		if(!fwrite($sock, $cmd, strlen($cmd))) {
			$this->fireError($sock,'Cache.TransferFail');
		}
		$res = trim(fgets($sock));

		if ($this->_func) {
			call_user_func($this->_func,sprintf("Cache: delete %s", $key));
		}

//		if ($res != "DELETED") {
//			throw new Exception('Cache.DeleteFail '.$res);
//		}
	}

	/**
	 * Retrieves the value associated with the key from the memcache server
	 *
	 * @param  string   $key     Key to retrieve
	 *
	 * @return  mixed
	 * @access  public
	 */
	function get ($key)
	{
		if (!($sock = $this->get_sock($key))) {
			return null;
		}

		$cmd = "get $key\r\n";
		$this->log($cmd,'cmd');
		if (!fwrite($sock, $cmd, strlen($cmd))) {
			$this->fireError($sock,'Cache.TransferFail');
		}

		$val = array();
		$this->_load_items($sock, $val);
		
		if (isset($val[$key])) {
			return $val[$key];
		} else {
			return null;
		}
	}

	/**
	 * Get multiple keys from the server(s)
	 *
	 * @param   array    $keys    Keys to retrieve
	 *
	 * @return  array
	 * @access  public
	 */
	function get_multi (Array $keys)
	{
		$sock_keys = array();
		foreach ($keys as $key) {
			$host = $this->get_sock($key,true);
			$sock_keys[$host][] = $key;
		}

		// Parse responses
		$val = array();
		// Send out the requests
		foreach ($sock_keys as $host => $keys) {
			$cmd = 'get '.implode(' ',$keys)."\r\n";
			if (!($sock = self::$_sock[$host])) {
				return $val;
			}
			
			$this->log($cmd,'cmd');
		
			if (!fwrite($sock, $cmd, strlen($cmd))){
				$this->fireError($sock,'Cache.TransferFail');
			}
			$this->_load_items($sock, $val);
		}
		return $val;
	}

	/**
	 * Increments $key (optionally) by $amt
	 *
	 * @param   string   $key     Key to increment
	 * @param   interger $amt     (optional) amount to increment
	 *
	 * @return  interger New key value?
	 * @access  public
	 */
	function incr ($key, $amt=1)
	{
		return $this->_incrdecr('incr', $key, $amt);
	}

	/**
	 * Overwrites an existing value for key; only works if key is already set
	 *
	 * @param   string   $key     Key to set value as
	 * @param   mixed    $value   Value to store
	 * @param   interger $exp     (optional) Experiation time
	 *
	 * @return  boolean
	 * @access  public
	 */
	function replace ($key, $value, $exp=0)
	{
		return $this->_set('replace', $key, $value, $exp);
	}

	/**
	 * Unconditionally sets a key to a given value in the memcache.  Returns true
	 * if set successfully.
	 *
	 * @param   string   $key     Key to set value as
	 * @param   mixed    $value   Value to set
	 * @param   interger $exp     (optional) Experiation time
	 *
	 * @return  boolean  TRUE on success
	 * @access  public
	 */
	function set ($key, $value, $exp=0)
	{
		return $this->_set('set', $key, $value, $exp);
	}

	/**
	 * Perform increment/decriment on $key
	 *
	 * @param   string   $cmd     Command to perform
	 * @param   string   $key     Key to perform it on
	 * @param   interger $amt     Amount to adjust
	 *
	 * @return  interger    New value of $key
	 * @access  private
	 */
	function _incrdecr ($cmd, $key, $amt=1)
	{
		if (!($sock = $this->get_sock($key))) {
			return null;
		}
		$this->log($cmd,'cmd');
		
		if (!fwrite($sock, "$cmd $key $amt\r\n")) {
			$this->fireError($sock,'Cache.TransferFail');
		}
		 
		$line = fgets($sock);
		// 如果没有找到，则默认初始值为0
		if (strncmp($line, 'NOT_FOUND', 9) === 0) {
			$this->_set('set', $key, $amt, 0);
			return $amt;
		}
		if (!preg_match('/^(\d+)/', $line, $match))
			return null;
		return $match[1];
	}

	/**
	 * Load items into $ret from $sock
	 *
	 * @param   resource $sock    Socket to read from
	 * @param   array    $ret     Returned values
	 *
	 * @access  private
	 */
	function _load_items ($sock, &$ret)
	{
		while(($decl = fgets($sock))) {
			$this->log($decl,'recv');
			if (strncmp($decl,"END\r\n",5) == 0){
				break;
			} elseif (preg_match('/^VALUE (\S+) (\d+) (\d+)\r\n$/', $decl, $match)) {
				list($rkey, $flags, $len) = array($match[1],intval($match[2]), $match[3]);
				$bneed = $len+2;
				$offset = 0;
				$tmp = '';
				while ($bneed > 0) {
					$data = fread($sock, $bneed);
					if (!$data) {
						break;
					}
					$n = strlen($data);
					$offset += $n;
					$bneed -= $n;
					$tmp .= $data;
					
					$this->log($data,'recv');
				}

				if ($offset != $len+2){
					$log = sprintf("Cache: transfer is broken!  key %s expecting %d got %d length\n", $rkey, $len+2, $offset);
					throw new Exception('Cache.TransferFail '.$log);
				}

				if (($flags & self::FLAG_ZIP) > 0) {
					$tmp = gzuncompress($tmp);
				}
				//主要是去掉\r\n
				$tmp = rtrim($tmp);
				if (($flags & self::FLAG_SERIALIZE) > 0) {
					$tmp = json_decode($tmp,true);
				}
				$ret[$rkey] = $tmp;
			} else {
				throw new Exception('Cache.Error parsing memcached response');
			}
		}
	}

	/**
	 * Performs the requested storage operation to the memcache server
	 *
	 * @param   string   $cmd     Command to perform
	 * @param   string   $key     Key to act on
	 * @param   mixed    $val     What we need to store
	 * @param   interger $exp     When it should expire
	 *
	 * @return  boolean
	 * @access  private
	 */
	function _set ($cmd, $key, $val, $exp)
	{
		if (!($sock = $this->get_sock($key))) {
			return null;
		}
		$flags = 0;

		if (!is_scalar($val)) {
			$val = json_encode($val, JSON_UNESCAPED_UNICODE);
			$flags |= self::FLAG_SERIALIZE;
		}

		$len = strlen($val);
		if ($this->_compress_enable &&
		$this->_compress_threshold &&
		$len >= $this->_compress_threshold) {
			$val = gzcompress($val, 9);
			$len = strlen($val);
			$flags |= self::FLAG_ZIP;
		}
		if (!fwrite($sock, "$cmd $key $flags $exp $len\r\n$val\r\n")) {
			$this->fireError($sock,'Cache.TransferFail');
		}
		 
		$line = trim(fgets($sock));

		if ($this->_func) {
			if (($flags & self::FLAG_ZIP) > 0) {
				$val = 'compressed data';
			}
			call_user_func($this->_func,sprintf("Cache: %s %s => %s (%s)\n", $cmd, $key, $val, $line));
		}
		if ($line !== "STORED" && $line !== "NOT_STORED") {
			throw new Exception('Cache.SetFail '.$line);
		}
	}

	/**
	 * Connects $sock to $host, timing out after $timeout
	 *
	 * @param   interger $sock    Socket to connect
	 * @param   string   $host    Host:IP to connect to
	 * @param   float    $timeout (optional) Timeout value, defaults to 0.25s
	 *
	 * @return  boolean
	 * @access  private
	 */
	function _connect_sock ($host)
	{
		list ($ip, $port) = explode(":", $host);
		$count = 0;
		do {
			$sock = @fsockopen($ip, $port, $errno, $errstr, $this->_connect_timeout);
			if ($sock) {
				$sec = floor($this->_rw_timeout/1000000);
				$msec = $this->_rw_timeout%1000000;
				stream_set_timeout($sock,$sec,$msec);
				// Do not buffer writes
				stream_set_write_buffer($sock, 0);
				return $sock;
			}
			$count++;
		} while($count < $this->_retry);
		trigger_error("no cacheserver or cacheservers were down!",E_USER_ERROR);
		return null;
	}


	/**
	 * get_sock
	 *
	 * @param   string   $key     Key to retrieve value for;
	 *
	 * @return  mixed    resource on success, false on failure
	 */
	function get_sock($key,$rethost=false)
	{
		if (empty($this->_servers)) {
			return null;
		}
		
		//一致性hash选后端的memcached实例
		$count = count($this->_servers);
		if ($count == 1) {
			$index = 0;
		} else {
			$hash = abs(crc32($key));
			$index = floor( ($hash%360) / (360/$count) );
		}
		
		$host = $this->_servers[$index];
		if (array_key_exists($host, self::$_sock)) {
			$sock = self::$_sock[$host];
		} else {
			$sock = $this->_connect_sock($host);
			self::$_sock[$host] = $sock;
		}
		return $rethost ? $host : $sock;
		
	}
}
