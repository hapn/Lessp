<?php
require_once __DIR__ . '/BaseApp.php';

/**
 * @filesource 		WebApp.php
 * @author 		ronnie<comdeng@live.com>
 * @since 		2014-12-21
 * @version 	1.0
 * @copyright 	Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc web的基本app
 * @example
 *
 */
require_once __DIR__ . '/WebApp.php';

class SwooleApp extends WebApp
{

	static $processName;

	static $workers;

	/**
	 * 启动
	 */
	static function bootstrap ( )
	{
		$opts = getopt('d', array( 
			'host:',
			'port:'
		));
		global $argv;
		self::$processName = "php " . implode(' ', $argv);
		
		if ( ! isset($opts['port']) ) {
			$opts['port'] = 9500;
		}
		if ( ! isset($opts['host']) ) {
			$opts['host'] = '0.0.0.0';
		}
		
		// 初始化com组件
		$hosts = array( 
			'host' => $opts['host'],
			'port' => $opts['port'],
			'setting' => array( 
				'worker_num' => 5, // worker进程数量
				'daemonize' => isset($opts['d']), // 守护进程设置成true
				'max_request' => 10000, // 最大请求次数，当请求大于它时，将会自动重启该worker
				'dispatch_mode' => 1
			)
		);
		
		$http = new swoole_http_server($hosts['host'], $hosts['port']);
		$http->set($hosts['setting']);
		$http->on('WorkerStart', 'SwooleApp::onWorkerStart');
		$http->on('ManagerStart', 'SwooleApp::onManagerStart');
		$http->on('start', 'SwooleApp::onStart');
		$http->on('request', 'SwooleApp::onRequest');
		echo "listen at:{$opts['host']}:{$opts['port']}\n";
		$http->start();
	}
					
	/**
	 * 响应请求
	 * @param swoole_http_request $req
	 * @param swoole_http_response $res
	 */
	public static function onRequest ( $req, $res )
	{
					// 做一些全局兼容的变量
					$_SERVER = array();
					foreach ( $req->server as $key => $value ) {
						$_SERVER[strtoupper($key)] = $value;
					}
					$_GET = isset($req->get) ? $req->get : array();
					$_POST = isset($req->post) ? $req->post : array();
					$_COOKIE = isset($req->cookie) ? $req->cookie : array();
					
		$app = new SwooleApp();
		$app->swooleRequest = $req;
		$app->swooleResponse = $res;
					$app->mode = 'swoole';
					$app->run();
					
	}

	/**
	 * server start的时候调用
	 * @param swoole_http_server $serv
	 */
	public static function onStart ( $serv )
	{
		printf("swoole version %s\n", swoole_version());
		swoole_set_process_name(self::$processName . ': master');
	}

	public static function onManagerStart ( $serv )
	{
		swoole_set_process_name(self::$processName . ': manager');
	}

	/**
	 * worker start时调用
	 * @param swoole_http_server $serv
	 * @param int $worker_id
	 */
	public static function onWorkerStart ( $serv, $worker_id )
	{
		if ( $worker_id >= $serv->setting['worker_num'] ) {
			swoole_set_process_name(self::$processName . ': task');
		} else {
			swoole_set_process_name(self::$processName . ': worker');
		}
		self::$workers[$worker_id] = $serv->worker_pid;
		printf("master_id: %d,manager_pid=%d,worker_id=%d,worker_pid=%d\n", $serv->master_pid, $serv->manager_pid, 
				$serv->worker_id, $serv->worker_pid);
	}

	var $swooleResponse;

	var $swooleRequest;

	/**
	 * (non-PHPdoc)
	 * @see WebApp::shutdownHandler()
	 */
	function shutdownHandler ( )
	{
		$error = error_get_last();
		if ( $error ) {
			$errmsg = sprintf('caught error, errno:%d,errmsg:%s,file:%s,line:%d', $error['type'], $error['message'], 
					$error['file'], $error['line']);
			Logger::fatal($errmsg);
			
			$errorCode = 'hapn.fatal';
			$this->endStatus = $errorCode;
			
			if ( true === $this->debug ) {
				$this->swooleResponse->write("<pre>" . print_r($error, true) . "</pre>");
			}
			$this->response->setHapNHeader($errorCode);
			
			$this->_setHeader($errorCode);
			
			$this->response->setError($error);
			$this->response->send();
		}
		
		parent::shutdownHandler();
		
		$this->swooleResponse->end();
		}

	/**
	 * @ignore
	 */
	protected function _initEnv ( )
	{
		mb_internal_encoding($this->encoding);
		iconv_set_encoding("internal_encoding", $this->encoding);
		error_reporting(E_ALL | E_STRICT | E_NOTICE);
		//
		// set_error_handler(array($this,'errorHandler'));
		// set_exception_handler(array($this,'exceptionHandler'));
		register_shutdown_function(array( 
			$this,
			'shutdownHandler'
		));
	}

	/**
	 * 初始化web对象
	 */
	protected function _initWebObject ( )
	{
		require_once FR_ROOT . 'http/HttpRequest.php';
		require_once FR_ROOT . 'http/SwooleResponse.php';
		require_once FR_ROOT . 'filter/FilterExecutor.php';
		
		$this->request = new HttpRequest($this);
		$this->response = new SwooleResponse($this);
		$this->response->swooleResponse = $this->swooleResponse;
		$this->filterExecutor = new FilterExecutor($this);
	}

	/**
	 * (non-PHPdoc)
	 * @see BaseApp::genAppId()
	 */
	function genAppId ( )
	{
		$header = $this->swooleRequest->header;
		if ( isset($header['clientappid']) ) {
			return intval($header['clientappid']);
		}
		$server = $this->swooleRequest->server;
		$reqip = '127.0.0.1';
		if ( isset($server['clientip']) ) {
			$reqip = $server['clientip'];
		} elseif ( isset($server['remote_addr']) ) {
			$reqip = $server['remote_addr'];
		}
		$time = gettimeofday();
		$time = $time['sec'] * 100 + $time['usec'];
		$ip = ip2long($reqip);
		$id = ($time ^ $ip) & 0xFFFFFFFF;
		return floor($id / 100) * 100;
	}

	/**
	 * (non-PHPdoc)
	 * @see BaseApp::run()
	 */
	function run ( )
	{
		$pid = posix_getpid();
		$index = array_search($pid, self::$workers);
		
		$time = gettimeofday();
		printf("[%s.%06d]#%d request\n", date('Y/m/d H:i:s', $time['sec']), $time['usec'], $index);
		
		parent::run();
		
		$this->shutdownHandler();
		
		$stop = gettimeofday();
		$cost = ($stop['sec'] * 1000 + ($stop['usec'] / 1000)) - ($time['sec'] * 1000 + ($time['usec'] / 1000));
		$url = $this->swooleRequest->server['request_uri'];
		if ( $_GET ) {
			$url .= '?' . http_build_query($_GET);
		}
		printf("[%s.%06d]#%d:%d cost:%.3fms %s %s\n", date('Y/m/d H:i:s', $stop['sec']), $stop['usec'], $index,
				$this->appId, $cost, $this->swooleRequest->server['request_method'], $url);
	}
}
