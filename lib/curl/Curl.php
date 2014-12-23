<?php
namespace lessp\lib\curl;

use lessp\fr\conf\Conf;
/**
 * Curl库
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			Curl.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version		    1.0
 */

final class CurlResponse
{
	/**
	 * 状态码
	 * @var int
	 */
	var $code;
	
	/**
	 * 状态描述
	 * @var string
	 */
	var $status;
	
	/**
	 * cookie
	 * @var array
	 */
	var $cookie;
	
	/**
	 * header
	 * @var array
	 */
	var $header;
	
	/**
	 * html代码
	 * @var string
	 */
	var $content;
	
	/**
	 * 协议名称
	 * @var string
	 */
	var $protocol;
}

final class Curl
{
	private $options;
	
	private static $defaultOpts = array(
		CURLOPT_RETURNTRANSFER	=> 1,
		CURLOPT_HEADER			=> 1,
		CURLOPT_FOLLOWLOCATION	=> 3,
		CURLOPT_ENCODING		=> '',
		CURLOPT_USERAGENT		=> 'Lessp',
		CURLOPT_AUTOREFERER		=> 1,
		CURLOPT_CONNECTTIMEOUT	=> 2,
		CURLOPT_TIMEOUT			=> 5,
		CURLOPT_MAXREDIRS		=> 3,
		//	CURLOPT_VERBOSE		=> true
	);
	private static $fixedOpts = array(CURLOPT_RETURNTRANSFER, CURLOPT_HEADER);
	
	function __construct($confs = null)
	{
		if ($confs === null) {
			$confs = Conf::get('curl.options', array());
		}
	
		$this->options = self::$defaultOpts;
	
		foreach($confs as $key => $value) {
			$this->options[$key] = $value;
		}
	}
	
	private function getOptions($opt)
	{
		foreach(self::$fixedOpts as $key) {
			unset($opt[$key]);
		}
		//数字下标的array不能merge，否则下标会从0开始计
		$ret = self::$defaultOpts;
		foreach($opt as $key=>$value) {
			$ret[$key] = $value;
		}
		return $ret;
	}
	
	/**
	 * GET请求
	 * @param string $url
	 * @param array $opt
	 * @param string $postData
	 * @param string $buildQuery
	 * @return CurlResponse
	 */
	function get($url, $opt = array(), $postData = null, $buildQuery = false)
	{
		$opt[CURLOPT_HTTPGET] = true;
		return $this->_doreq($url, $postData, $opt, $buildQuery);
	}
	
	/**
	 * 进行实际的请求
	 * @param string $url
	 * @param array $postData
	 * @param array $opt
	 * @param boolean $buildQuery
	 * @return CurlResponse
	 */
	private function _doreq($url, $postData, $opt, $buildQuery)
	{
		if ($postData) {
			if ($buildQuery) {
				$opt[CURLOPT_POSTFIELDS] = http_build_query($postData);
			} else {
				$opt[CURLOPT_POSTFIELDS] = $postData;
			}
		}
		$ret = $this->request($url,$opt);
		return $this->returnData($ret);
	}
	
	/**
	 * POST请求
	 * @param string $url
	 * @param array $postData
	 * @param array $opt
	 * @param string $buildQuery
	 * @return CurlResponse
	 */
	function post($url, $postData=array(), $opt=array(), $buildQuery = true)
	{
		$opt[CURLOPT_POST] = true;
		return $this->_doreq($url, $postData, $opt, $buildQuery);
	}
	
	/**
	 * PUT请求
	 * @param string $url
	 * @param array $postData
	 * @param array $opt
	 * @param string $buildQuery
	 * @return CurlResponse
	 */
	function put($url, $postData=array(),$opt=array(), $buildQuery = true)
	{
		$opt[CURLOPT_CUSTOMREQUEST] = 'PUT';
		return $this->post($url, $postData, $opt, $buildQuery);
	}
	
	/**
	 * DELETE请求
	 * @param string $url
	 * @param array $postData
	 * @param array $opt
	 * @param string $buildQuery
	 * @return CurlResponse
	 */
	function delete($url, $postData=array(), $opt=array(), $buildQuery = true)
	{
		$opt[CURLOPT_CUSTOMREQUEST] = 'DELETE';
		return $this->post($url, $postData, $opt, $buildQuery);
	}
	
	/**
	 * 请求
	 * @param string $url
	 * @param array $opts
	 * @throws Exception
	 * @return array
	 * <code>array($err, $errmsg, $data);
	 */
	private function request($url, $opts)
	{
		$opts = $this->getOptions($opts);
		$ch = curl_init($url);
		curl_setopt_array($ch,$opts);
		$data = curl_exec($ch);
		if ($data === false) {
			$info = curl_getinfo($ch);
			if ($info['http_code'] == 301 ||
			$info['http_code'] == 302) {
				throw new \Exception('mcutil.curlerr redirect occurred:'.$info['url']);
			}
		}
		$err = curl_errno($ch);
		$errmsg = curl_error($ch);
		curl_close( $ch );
		return array($err, $errmsg, $data);
	}
	
	/**
	 * 解析返回内容
	 * @param string $ret
	 * @throws Exception
	 * @return CurlResponse
	 */
	private function parse($ret)
	{
		while(true) {
			$pos = strpos($ret,"\r\n\r\n");
			if (!$pos) {
				throw new \Exception('mcutil.curlerr redirect occurred:'.$ret);
			}
			$header = substr($ret,0, $pos);
			// check the status whether is 10x
			list($_proto, $_status) = explode(' ', $header);
	
			if ($_status >= 100 && $_status < 200) {
				$ret = substr($ret, $pos + 4);
				continue;
			}
			break;
		}
		$body = substr($ret,$pos+4);
		$headerLines = explode("\r\n",$header);
		$head = array_shift($headerLines);
		$cookies = array();
		$headers = array();
		$codes = explode(' ', $head);
		$protocol = array_shift($codes);
		$code = array_shift($codes);
		$status = implode(' ', $codes);
		foreach($headerLines as $line) {
			list($k,$v) = explode(":",$line);
			$k = trim($k);
			$v = trim($v);
			if ($k == 'Set-Cookie') {
				list($ck, $cv) = explode("=",$v);
				$pos = strpos($cv, ';');
				if ($pos === false) {
					$cookies[trim($ck)] = trim($cv);
				} else {
					$cookies[trim($ck)] = trim(substr($cv, 0, $pos));
				}
			} else {
				$headers[$k] = $v;
			}
		}
		$cr = new CurlResponse();
		$cr->header = $headers;
		$cr->protocol = $protocol;
		$cr->code = intval($code);
		$cr->status = $status;
		$cr->cookie = $cookies;
		$cr->content = $body;
		return $cr;
	}
	
	/**
	 * 返回数据
	 * @param string $ret
	 * @throws Exception
	 * @return 
	 */
	private function returnData($ret)
	{
		list($err, $errmsg, $data) = $ret;
		if ($err) {
			throw new \Exception('mcutil.curlerr '.$errmsg);
		}
		return $this->parse($data);
	}
	
	/**
	 * 异步get请求，不等待返回
	 * @param string $url
	 * @param string $host
	 * @param string $cookie
	 */
	public function asyncGet($url, $host = null, $cookie = null)
	{
		$info = parse_url($url);
		if (!$host) {
			$host = $info['host'];
		}
		$fp = @fsockopen($info['host'], isset($info['port']) ? $info['port'] : 80, $errno, $errmsg, 5);
		if (!$fp) {
			throw new \Exception('mcutil.u_curlerr '.$errmsg);
		}
		stream_set_blocking($fp, 0);
		$path = $info['path'] . (isset($info['query']) ? '?'.$info['query'] : '');
		$out = "GET ".$path." HTTP/1.1\r\n";
		$out .= "Host: ".$host."\r\n";
		$out .= "Connection: Close\r\n";
		if ($cookie) {
			$out .= "Cookie: ".$cookie."\r\n";
		}
		$out .= "\r\n";
		fwrite($fp, $out);
		 
		fclose($fp);
	}
	
	/**
	 * 异步post请求，不等待即返回
	 * @param string $url
	 * @param array  $data
	 * @param string $host
	 * @param string $cookie
	 */
	public function asyncPost($url, $data = array(), $host = null, $cookie = null)
	{
		$info = parse_url($url);
		if (!$host) {
			$host = $info['host'];
		}
		$fp = @fsockopen($info['host'], isset($info['port']) ? $info['port'] : 80, $errno, $errmsg, 5);
		if (!$fp) {
			throw new \Exception('mcutil.u_curlerr '.$errmsg);
		}
		stream_set_blocking($fp, 0);
		$path = $info['path'] . (isset($info['query']) ? '?'.$info['query'] : '');
		$params = http_build_query($data);
		$out = "POST ".$path." HTTP/1.1\r\n";
		$out .= "Host: ".$host."\r\n";
		$out.="Content-type: application/x-www-form-urlencoded\r\n";
		$out.="Content-length: ".strlen($params)."\r\n";
		$out .= "Connection: Close\r\n";
		if ($cookie) {
			$out .= "Cookie: ".$cookie."\r\n";
		}
		$out .= "\r\n".$params;
		fwrite($fp, $out);
		fclose($fp);
	}
}