<?php

/**
 *  
 * @filesource        HttpJson.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc HttpJson请求类
 * @example
 * 请求的Url格式为http://{host:port}/{urlPrefix}/{mod}?{urlSuffix}
 * 具体要求如下：
 * __construct函数里要求arrServer参数里是一个host:port的数组，多个会自动负载均衡
 * rpcCall函数里要求输入urlPrefix，这是一个url前缀，youa的默认实现是_private/rpc
 * rpcCall函数里要求输入urlSuffix，这是一个url后缀，youa的默认实现是_if=json&_of=json，分别表示输入输出格式为json
 * rpcCall函数里要求输入mod，对应mod.method，是要调用的函数
 * 
 * 举例
 * http://127.0.0.1:8888/_private/rpc/hapn.getSubCategories?_if=json&_of=json
 * 表示调用hapn模块的getSubCategories方法，参数在POST请求的rpcinput参数里
 * 
 * 如果不遵守这个规范，可以在rpcCall里按需求调整urlPrefix, mod或者urlSuffix来拼装成自己的url
 * 
 */

class HttpJson
{
	/**
	 * content-type
	 * @var string
	 */
	const HTTP_RPC_CONTENT_TYPE = 'text/plain';
	
	/**
	 * 日志id
	 * @var string
	 */
	const APPID = 'CLIENTAPPID';
	
	const MAX_STEP = 90;
	
	/**
	 * 调用的目标url
	 * @var string
	 */
	protected $mod;
	
	/**
	 * curl的读超时时间,单位ms
	 * @var int
	 */
	protected $readTimeout;
	
	/**
	 * curl的写超时时间，单位ms
	 * @var int
	 */
	protected $writeTimeout;
	
	/**
	 * curl的连接超时时间，单位ms
	 * @var int
	 */
	protected $connTimeout;
	
	/**
	 * curl的handle
	 * @var handle
	 */
	protected $curl_handle;
	
	/**
	 * 所有的header列表
	 * @var array
	 */
	protected $arrHeader;
	
	/**
	 * 编码方式
	 * @var string
	 */
	protected $encoding;
	
	/**
	 * 原始响应http消息体
	 * @var string
	 */
	protected $rawResponseBody;
	
	/**
	 * 原始响应http消息头
	 * @var string
	 */
	protected $rawResponseHeader;
	
	/**
	 * 服务器列表
	 * @var array
	 */
	protected $arrServer;
	
	/**
	 * 请求url地址
	 * @var array
	 */
	protected $arrUrl;
	
	/**
	 * 构造函数，设置初始化参数
	 *
	 * @param array $arrServer 服务器列表
	 * @param string $encoding 默认编码方式
	 * @param int $conn_timeout curl的连接超时时间，单位ms
	 * @param int $read_timeout curl的读超时时间,单位ms
	 * @param int $write_timeout curl的写超时时间，单位ms
	 */
	function __construct($arrServer, $encoding = 'UTF-8', $connTimeout = 1000, $readTimeout = 1000, $writeTimeout = 1000)
	{
		$this->arrServer = $arrServer;
		$this->connTimeout = $connTimeout;
		$this->readTimeout = $readTimeout;
		$this->writeTimeout = $writeTimeout;
		$this->arrHeader = array();
		$this->encoding = $encoding;
	}
	
	protected function getLogid()
	{
		global $__HapN_appid;
		$logid = $__HapN_appid;
		$__HapN_appid ++;
		return $logid;
	}
	
	/**
	 * 生成url列表
	 */
	protected function buildUrlArray($url)
	{
	 	$url = ltrim($url, '/');
        foreach ( $this->arrServer as $server ) { 
            if (!is_array($server)) {
                $server = array($server, true);
            }   
            $completeUrl = sprintf ( 'http://%s/%s', $server[0], $url );
            $this->arrUrl [$completeUrl] = $server[1];
        }
	}
	
	/**
	 * 随机生成一个url供服务器调用
	 * @return string
	 */
	protected function pickOneUrl()
	{
	
		$arrUrl = array();
        foreach($this->arrUrl as $host => $name) {
            if ($name) {
                $arrUrl[] = array($host, $name);
            }
        }

        if (count($arrUrl) == 0)
        {
            return false;
        }
        $url = $arrUrl [mt_rand ( 0, count( $arrUrl ) - 1 )];
        $this->arrUrl [$url[0]] = false;
        return $url;
	}
	
	/**
	 * 添加一个头信息
	 * @param string $header
	 */
	public function addHeader($header)
	{
		$this->arrHeader [] = $header;
	}
	
	/**
	 * 发送数据
	 * @param array $arr_data
	 */
	protected function send($arr_data)
	{
	
		if ($this->encoding !== 'UTF-8')
		{
			mb_convert_variables('UTF-8', $this->encoding, $arr_data);
		}
		$json = json_encode ( $arr_data, JSON_UNESCAPED_UNICODE );
		if (empty ( $json ))
		{
			throw new \Exception ( 'api.httpjson encode failed' );
		}
		curl_setopt ( $this->curl_handle, CURLOPT_CONNECTTIMEOUT, $this->connTimeout);
		curl_setopt ( $this->curl_handle, CURLOPT_TIMEOUT, $this->connTimeout+$this->readTimeout + $this->writeTimeout);
		$this->error ( curl_setopt ( $this->curl_handle, CURLOPT_POSTFIELDS, $json ) );
		while ( true )
		{
			$url = $this->pickOneUrl ();
			Logger::trace('api.fetchurl url='.$url);
			if (false === $url)
			{
				throw new \Exception ( 'api.httpjson call all url failed' );
			}
				
			curl_setopt ( $this->curl_handle, CURLOPT_URL, $url[0] );
			if ($url[1] !== true) {
				curl_setopt ( $this->curl_handle, CURLOPT_HTTPHEADER, array('Host: '.$url[1]) );
			}
			if (false === curl_exec ( $this->curl_handle ))
			{
				continue;
			}
			else
			{
				$httpCode = curl_getinfo ( $this->curl_handle, CURLINFO_HTTP_CODE );
				if (200 == $httpCode)
				{
					return;
				}
				else
				{
					continue;
				}
			}
		}
	}
	
	/**
	 * 将数组按UTF-8进行编码
	 * @param array $arr_data
	 * @return array 经过utf8编码的数组
	 */
	protected function convertEncoding($arr_data, $from, $to, $step=1)
	{
		if ($step > self::MAX_STEP) {
			throw new \Exception('api.recursion');
		}
		if (is_string ( $arr_data ))
		{
			return mb_convert_encoding ( $arr_data, $to, $from );
		}
		else if (! is_array ( $arr_data ))
		{
			return $arr_data;
		}
		else
		{
				
			$arr_ret = array ();
			foreach ( $arr_data as $key => $value )
			{
				$key = $this->convertEncoding ( $key, $from, $to,$step+1 );
				$value = $this->convertEncoding ( $value, $from, $to,$step+1);
				$arr_ret [$key] = $value;
			}
			return $arr_ret;
		}
	}
	
	/**
	 * 回调函数，读取到的响应消息体
	 * @param resource $handle
	 * @param string $data
	 */
	protected function readBody($handle, $data)
	{
	
		$this->rawResponseBody .= $data;
		return strlen ( $data );
	}
	
	/**
	 * 回调函数，读取到的响应消息头
	 * @param resource $handle
	 * @param string $data
	 */
	protected function readHeader($handle, $data)
	{
	
		$this->rawResponseHeader .= $data;
		return strlen ( $data );
	}
	
	/**
	 * 实际调用
	 * @param string $mod 格式为mod.method，调用mod模块的method方法
	 * @param array $arrParamArgs 请求参数
	 * @param array $arrInitArgs 初始化参数
	 * @param string $urlPrefix url前缀
	 * @param string $urlSuffix url后缀
	 * @return array 执行结果
	 */
	public function rpcCall($url,$arrInputs)
	{
	
		$this->arrHeader = array ();
		$this->buildUrlArray ($url);
		$this->init ();
		$this->send ( $arrInputs );
	
		$arrRet = json_decode ( $this->rawResponseBody, true );
		if (false === $arrRet)
		{
			throw new \Exception ( "api.httpjson json_decode:$this->rawResponseBody failed" );
		}
		if ($this->encoding !== 'UTF-8')
		{
			mb_convert_variables($this->encoding, 'UTF-8', $arrRet);
		}
		return $arrRet;
	}
	
	/**
	 * 初始化，设置一些选项
	 */
	protected function init()
	{
	
		$this->rawResponseBody = '';
		$this->rawResponseHeader = '';
	
		$this->curl_handle = curl_init ();
		$this->error ( $this->curl_handle );
		$this->error ( curl_setopt ( $this->curl_handle, CURLOPT_WRITEFUNCTION, array ($this, 'readBody' ) ) );
		$this->error ( curl_setopt ( $this->curl_handle, CURLOPT_HEADERFUNCTION, array ($this, 'readHeader' ) ) );
		$this->error ( curl_setopt ( $this->curl_handle, CURLOPT_POST, true ) );
	
		$this->arrHeader [] = sprintf ( '%s:%s', self::APPID, $this->getLogid() );
		$this->arrHeader [] = sprintf ( 'Content-Type: %s', self::HTTP_RPC_CONTENT_TYPE );
		$this->arrHeader [] = sprintf ( 'Connection: close' );
		$this->arrHeader = array_unique ( $this->arrHeader );
		$this->error ( curl_setopt ( $this->curl_handle, CURLOPT_HTTPHEADER, $this->arrHeader ) );
	}
	
	/**
	 * 测试是否有错误
	 * @param int $ret
	 */
	protected function error($ret)
	{
	
		if (false === $ret)
		{
			$errno = curl_errno ( $this->curl_handle );
			if (0 !== $errno)
			{
				$error = curl_error ( $this->curl_handle );
				throw new \Exception ( "api.httpjson curl_error:$error" );
			}
		}
	}
	
	/**
	 * 关闭连接
	 */
	protected function close()
	{
		curl_close ( $this->curl_handle );
		$this->curl_handle = false;
	}
	
	/**
	 * 析构函数
	 */
	function __destruct()
	{
	
		if (! empty ( $this->curl_handle ))
		{
			curl_close ( $this->curl_handle );
		}
	}
}
