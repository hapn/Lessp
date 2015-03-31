<?php

/**
 *  
 * @filesource  HttpResponse.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 
 * @example     
 */

class HttpResponse
{
	private $app = null;
	
	var $template = null;
	var $exception = null;
	var $error = null;
	var $callback = null;
	
	var $headers = array();
	var $outputs = array();
	var $cookies = array();
	var $rawData = null;
	var $customErr = null;
	// 是否需要强制指定内容类型
	var $needSetContentType = true;
	private $results = array();
	var $tasks = array();
	
	
	function __construct(WebApp $app)
	{
		$this->app = $app;
	}
	
	/**
	 * 设置响应头
	 * @param string $header
	 * @return HttpResponse
	 */
	function setHeader($header)
	{
		if ($this->needSetContentType && strpos(strtolower($header), 'content-type:') === 0 ) {
			$this->needSetContentType = false;
		}
		$this->headers[] = $header;
	}
	
	/**
	 * 设置cookie
	 * @param string $key 名称
	 * @param string $value 值
	 * @param string $expires 过期时间
	 * @param string $path cookie的路径
	 * @param string $domain cookie的于明年
	 * @param string $secure
	 * @param string $httponly
	 * 
	 * @return HttpResponse
	 */
	function setCookie($key,$value,$expires=null,$path='/',$domain=null,$secure=false,$httponly=false)
	{
		$this->cookies[] = array($key,$value,$expires,$path,$domain,$secure,$httponly);
	}
	
	/**
	 * 删除cookie
	 * @param string $key
	 * @param string $value
	 * @param number $expires
	 * @param string $path
	 * @param string $domain
	 * @param string $secure
	 * @param string $httponly
	 * 
	 * @return HttpResponse
	 */
	function delCookie($key,$value='',$expires=1,$path='/',$domain=null,$secure=false,$httponly=false)
	{
		$this->cookies[] = array($key,$value,$expires,$path,$domain,$secure,$httponly);
	}
	
	/**
	 * 设置变量
	 * @param string $key
	 * @param mixed $value
	 * @return HttpResponse
	 */
	function set($key, $value=null)
	{
		$this->outputs[$key] = $value;
		return $this;
	}
	
	/**
	 * 批量设置变量
	 * @param array $kvs
	 * @return HttpResponse
	 */
	function sets(array $kvs)
	{
		foreach($kvs as $k => $v) {
			$this->outputs[$k] = $v;
		}
		return $this;
	}
	
	/**
	 * 清理指定的键，使其不输出
	 * @param string $key 如果不传入任何值，则清除掉所有的设置。 如果传入内容，可以是单个key，或者数组，或者多参数传入
	 * @return HttpResponse
	 */
	function rmset()
	{
		if (func_num_args() == 0) {
			$this->outputs = array();
			return;
		}
		$key = func_get_arg(0);
		if (!is_array($key)) {
			$args = func_get_args();
		} else {
			$args = $key;
		}
		foreach($args as $k) {
			unset($this->outputs[$k]);
		}
		return $this;
	}
	
	/**
	 * 设置原始输出数据。比如要输出图片，或者指定的文字
	 * @param string $data
	 * @return HttpResponse
	 */
	function setRaw($data)
	{
		$this->rawData = $data;
		
		return $this;
	}
	
	/**
	 * 设置异常
	 * @param Exception $ex
	 * @return HttpResponse
	 */
	function setException($ex)
	{
		$this->exception = $ex;
		
		return $this;
	}
	
	/**
	 * 设置错误
	 * @param string $err
	 * @return HttpResponse
	 */
	function setError($err)
	{
		$this->error = $err;
		
		return $this;
	}
	
	/**
	 * 设置jsonp回调函数名称
	 * @param string $func
	 * @throws \Exception lessp.errcallback 函数名称格式不正确
	 * @return HttpResponse
	 */
	function setCallback($func)
	{
		if (!preg_match('/^[a-zA-Z_][a-zA-Z_0-9.]{0,128}$/',$func)) {
			throw new \Exception('lessp.errcallback');
		}
		$this->callback = $func;
		//设置callback时需要修改输出格式和编码
		$this->app->request->of = 'json';
		$this->app->request->oe = 'utf-8';
		
		return $this;
	}
	
	/**
	 * 设置模板
	 * @param string $path 模板路径，相对于PAGE_ROOT的相对路径
	 * @param array $arr 变量
	 * @return HttpResponse
	 */
	function setView($path, $arr=array())
	{
		if ($arr) {
			$this->outputs = array_merge($this->outputs,$arr);
		}
		$this->template = $path;
		$this->app->request->of = 'html';
		
		return $this;
	}
	
	/**
	 * 重定向到另外一个页面
	 * @param string $url
	 * @param boolean $permanent 是否永久重定向
	 */
	function redirect($url, $permanent = false)
	{
		if ($permanent === true) {
			$this->setHeader('HTTP/1.1 301 Moved Permanently');
		}
		$this->setHeader('Location: '.$url);
		$this->setLesspHeader();
		$this->sendHeaders();
		//设置正常结束状态
		$this->app->endStatus = 'ok';
		exit;
	}
	
	/**
	 * 获取模板编译后的输出结果
	 * @param string $template 模板路径
	 * @param array $userData 模板变量
	 * @param boolean $output 是否输出
	 * @throws \Exception lessp.errclass 模板类不存在
	 * @return string
	 */
	function buildView($template, $userData, $output = false)
	{
		$engine = Conf::get('lessp.view', 'PhpView');
		$viewRoot = Conf::get('lessp.view.root', PAGE_ROOT);
		$clsName = $engine;
		
		$this->app->timer->begin($engine);
		
		if (!class_exists($clsName)) {
			$file = FR_ROOT.'view/'.$engine.'.php';
			require_once $file;
		}
		if (!class_exists($clsName)) {
			throw new \Exception("lessp.errclass view $engine not exist");
		}
		$view = new $clsName();
		$view->init(array(
			'request' 	=> $this->app->request,
			'viewRoot'	=> $viewRoot,
		));
		$view->sets($userData);
		if (!$output) {
			$result = $view->fetch($template);
		} else {
			$result = $view->render($template);
		}
		$this->app->timer->end($engine);
		return $result;
	}
	
	/**
	 * 输出结束
	 */
	function end()
	{
		$this->app->filterExecutor->executeFilter('output');
		//设置结束状态
		$this->app->endStatus = 'ok';
		exit();
	}
	
	/**
	 * 设置页面不缓存
	 */
	function setNoCache()
	{
		$this->setHeader('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		$this->setHeader('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		$this->setHeader('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		$this->setHeader('Pragma: no-cache');
	}
	
	/**
	 * 设置自定义错误码
	 * @param string $errcode
	 */
	function customError($errcode)
	{
		$this->customErr = $errcode;
	}
	
	private function _buildContentType($of,$encoding)
	{
		if (!$this->needSetContentType) {
			return;
		}
		switch($of) {
			case 'json':
				$this->headers = array_merge(array('Content-Type: application/json; charset='.$encoding), $this->headers);
				break;
			case 'html':
				$this->headers = array_merge(array('Content-Type: text/html; charset='.$encoding), $this->headers);
				break;
			case 'xml':
				$this->headers = array_merge(array('Content-Type: text/xml; charset='.$encoding), $this->headers);
				break;
			case 'jpg':
			case 'png':
			case 'gif':
				$this->headers = array_merge(array('Content-Type: image/'.$of), $this->headers);
				break;
			default:
				$this->headers = array_merge(array('Content-Type: text/plain; charset='.$encoding), $this->headers);
		}
	
		$this->needSetContentType = false;
	}
	
	/**
	 * 请求的id设置到response header里
	 * @param string $errcode
	 */
	public function setLesspHeader($errcode='suc')
	{
		global $_LessP_appid;
		$header = sprintf('lessp: id=%s,%s',$this->app->appId,$_LessP_appid);
	
		if ($errcode != 'suc') {
			$method = 'r';
			$urls = explode('/',$this->app->request->url);
			if ($urls && strncmp($urls[count($urls)-1],'_',1) === 0) {
				//最后一节是否以下划线开头的
				$method = 'w';
			}
			$header .= sprintf(',e=%s,m=%s', $errcode, $method);
			if (($retry = $this->app->request->get('retry'))) {
				$header .= ',r='.intval($retry);
			}
		}
		$this->setHeader($header);
	}
	
	/**
	 * 给lessp返回的结果中额外增加一些变量
	 * @param string $key
	 * @param string $value
	 */
	public function setLesspResult($key, $value)
	{
		if (!$key) return;
		$this->results[$key] = $value;
	}
	
	private function _getResult()
	{
		if ($this->exception) {
			$errcode = $this->exception->getMessage();
			if (($pos = strpos($errcode,' '))) {
				$errcode = substr($errcode,0,$pos);
			}
			if (!preg_match('/^[a-zA-Z0-9\.\-_]{1,50}$/',$errcode)) {
				//普通的错误信息不能传到前端
				$errcode = 'lessp.fatal';
			}
			$result = array('err'=>$errcode);
			if ($errcode == 'lessp.u_input') {
				//输入check错误时，可以带些数据
				$result['data'] = $this->outputs;
				Logger::debug('input data error:%s',print_r($result['data'],true));
			}
				
			if($errcode=="lessp.u_not_modified")
			{
				$result['data'] = null;
			}
		} elseif ($this->error) {
			$result = array('err'=>'lessp.fatal');
		} else {
			$result = array('err'=>'lessp.ok','data'=>$this->outputs);
		}
		foreach($this->results as $key => $value) {
			if (!isset($result[$key])) {
				$result[$key] = $value;
			}
		}
		return $result;
	}
	
	private function _formatResponse()
	{
		$result = $this->_getResult();
		$of = $this->app->request->of;
		$this->_buildContentType($of, $this->app->request->oe);
		if (!is_null($this->rawData)) {
			return $this->rawData;
		} elseif ($this->template) {
			return $this->buildView($this->template,$this->outputs);
		} else {
			//formatter
			if ($of == 'json') {
				if ($this->callback) {
					return $this->callback.'('.json_encode($result, JSON_UNESCAPED_UNICODE).')';
				} else {
					return json_encode($result, JSON_UNESCAPED_UNICODE);
				}
			} elseif ($of == 'xml') {
				require_once FR_ROOT.'util/XmlUtil.php';
				return XmlUtil::array2xml($result,$this->app->request->oe);
			} else {
				return print_r($result,true);
			}
		}
	}
	
	/**
	 * 发送header头信息
	 */
	function sendHeaders()
	{
		$headers = $this->headers;
		if ($this->cookies) {
			//echo cookie
			foreach($this->cookies as $cookie) {
				call_user_func_array('setcookie',$cookie);
			}
		}
		if ($headers) {
			
// 			var_dump(headers_list(), $this->template, $headers);
			//echo header
			foreach($headers as $header) {
				header($header);
			}
		}
	}
	
	/**
	 * 发送内容
	 * @param boolean $inner 是否为内部输出，如果是内部输出，则不用设置header等。
	 */
	function send($inner = false)
	{
		if ($inner) {
			$data = $this->_formatResponse();
		} else {
			$this->setLesspHeader();
			$data = $this->_formatResponse();
			$this->sendHeaders();
			
			//获取缓冲数据
			$ob = ini_get('output_buffering');
			if ($ob && strtolower($ob) !== 'off') {
				$str = ob_get_clean();
				//忽略前后空白
				$data = trim($str).$data;
			}
		}
		
		if ($data) {
			$outhandler = Conf::get('lessp.outputhandler',array());
			if ($outhandler && !is_array($outhandler)) {
				//也支持配置一个字符串
				$outhandler = array($outhandler);
			}
			if ($outhandler) {
				//调用全局输出处理器
				foreach($outhandler as $handler) {
					if (is_callable($handler)) {
						$data = call_user_func($handler,$data);
					} else {
						Logger::warning("outouthandler:%s can't call",is_array($handler)?$handler[1]:$handler);
					}
				}
			}
			if ($inner) {
				return $data;
			} else {
				echo $data;
			}
		}
	}
	
	/**
	 * 添加后台任务
	 * 此回调内容在服务器响应完用户请求后执行
	 * @param callable $callback 要求是一个可调用的方法，或者 array($obj, $method)
	 * @param array $args 传入的参数
	 */
	function addTask($callback, $args = array())
	{
		$this->tasks[] = array($callback, $args);
	}
	
	/**
	 * 跳转页面请求
	 * @param string $url
	 * @param array $args
	 */
	function forward($url, $args = array())
	{
		$dispatcher = new UrlDispatcher(NULL, DISPATCH_MODE_FORWARD);
		return $dispatcher->dispatch($url, $args);
	}
	
}