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
		global $argv;
		self::$processName = "php " . implode(' ', $argv);
		
		swoole_set_process_name(self::$processName . ': manager');
		register_shutdown_function('SwooleApp::onShutdownHandler');
		
		// 初始化com组件
		$hosts = array( 
			'host' => '0.0.0.0',
			'port' => 9500,
			'setting' => array( 
				'worker_num' => 5, // worker进程数量
				'daemonize' => false, // 守护进程设置成true
				'max_request' => 10000, // 最大请求次数，当请求大于它时，将会自动重启该worker
				'dispatch_mode' => 1
			)
		);
		
		$http = new swoole_http_server($hosts['host'], $hosts['port']);
		$http->set($hosts['setting']);
		$http->on('WorkerStart', 'SwooleApp::onWorkerStart');
		$http->on('start', 'SwooleApp::onStart');
		$http->on('request', 
				function  ( $req, $res ) {
					
					$pid = posix_getpid();
					$index = array_search($pid, self::$workers);
					
					$time = gettimeofday();
					
					printf("[%s.%06d]#%d request\n", date('Y/m/d H:i:s', $time['sec']), $time['usec'], $index);
					$GLOBALS['SWOOLE_REQUEST'] = $req;
					$GLOBALS['SWOOLE_RESPONSE'] = $res;
					
					// 做一些全局兼容的变量
					$_SERVER = array();
					foreach ( $req->server as $key => $value ) {
						$_SERVER[strtoupper($key)] = $value;
					}
					$_GET = isset($req->get) ? $req->get : array();
					$_POST = isset($req->post) ? $req->post : array();
					$_COOKIE = isset($req->cookie) ? $req->cookie : array();
					
					$GLOBALS['WEBAPP'] = $app = new SwooleApp();
					$app->mode = 'swoole';
					$app->run();
					
					$stop = gettimeofday();
					$cost = ($stop['sec'] * 1000 + ($stop['usec'] / 1000)) -
							 ($time['sec'] * 1000 + ($time['usec'] / 1000));
					printf("[%s.%06d]#%d:%d cost:%.3fms %s %s\n", date('Y/m/d H:i:s', $stop['sec']), $stop['usec'], 
							$index, $app->appId, $cost, $req->server['request_method'], 
							$req->server['request_uri']);
				});
		$http->start();
	}

	/**
	 * server start的时候调用
	 * @param swoole_http_server $serv
	 */
	public static function onStart ( $serv )
	{
		global $argv;
		printf("swoole version %s\n", swoole_version());
		swoole_set_process_name(self::$processName . ': master');
	}

	/**
	 * worker start时调用
	 * @param swoole_http_server $serv
	 * @param int $worker_id
	 */
	public static function onWorkerStart ( $serv, $worker_id )
	{
		global $argv;
		if ( $worker_id >= $serv->setting['worker_num'] ) {
			swoole_set_process_name(self::$processName . ': task');
		} else {
			swoole_set_process_name(self::$processName . ': worker');
		}
		self::$workers[$worker_id] = $serv->worker_pid;
		printf("master_id: %d,manager_pid=%d,worker_id=%d,worker_pid=%d\n", $serv->master_pid, $serv->manager_pid, 
				$serv->worker_id, $serv->worker_pid);
	}

	static function onShutdownHandler ( )
	{
		$error = error_get_last();
		if ( $error ) {
			call_user_func_array(array( 
				$GLOBALS['WEBAPP'],
				'errorHandler'
			), $error);
		}
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
		$this->filterExecutor = new FilterExecutor($this);
	}

	/**
	 * (non-PHPdoc)
	 * @see BaseApp::genAppId()
	 */
	function genAppId ( )
	{
		$header = $GLOBALS['SWOOLE_REQUEST']->header;
		if ( isset($header['clientappid']) ) {
			return intval($header['clientappid']);
		}
		$server = $GLOBALS['SWOOLE_REQUEST']->server;
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
	 * 处理web请求
	 */
	function run ( )
	{
		parent::run();
		$this->shutdownHandler();
		$GLOBALS['SWOOLE_RESPONSE']->end();
	}
}