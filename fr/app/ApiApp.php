<?php

/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2015 All rights reserved.
 * @filesource		ApiApp.php
 * @author			ronnie<dengxiaolong@hunbasha.com>
 * @since			2015年4月28日 下午7:31:22
 * @version		    1.0
 * @desc 			
 */
final class ApiApp
{

	/**
	 * @var swoole_http_request
	 */
	private $sreq;

	/**
	 *
	 * @var swoole_http_response
	 */
	private $sres;

	public static $encoding = 'utf-8';

	var $appId;

	var $uri;

	var $timer;

	var $endStatus = 'hapn.ok';

	var $debug;

	var $headers = array();

	var $outputs = array();
	
	static $processName;
	static $workers;

	/**
	 * 启动
	 */
	static function bootstrap ( )
	{
		global $argv;
		self::$processName = "php ".implode(' ', $argv);
		
		swoole_set_process_name(self::$processName.': manager');
		register_shutdown_function('ApiApp::shutdownHandler');
		mb_internal_encoding(self::$encoding);
		iconv_set_encoding('internal_encoding', self::$encoding);
		
		// 使用很严格的错误报告机制
		error_reporting(E_ALL | E_NOTICE);
		
		require_once FR_ROOT . 'conf/Conf.php';
		// 初始化配置
		$confs = array( 
			CONF_ROOT . 'hapn.conf.php'
		);
		Conf::load($confs);
		$debug = Conf::get('hapn.debug', false);
		self::$encoding = strtoupper(Conf::get('hapn.encoding', self::$encoding));
		
		if ( true !== Conf::get('hapn.disable_api') ) {
			// 没有强制关闭
			self::initApi();
		}
		
		if ( true !== Conf::get('hapn.disable_db') ) {
			// 没有强制关闭
			self::initDB();
		}
		
		// 初始化com组件
		$hosts = Conf::get('hapn.api.server', 
				array( 
					'host' => '0.0.0.0',
					'port' => 9500,
					'setting' => array( 
						'worker_num' => 8, // worker进程数量
						'daemonize' => false, // 守护进程设置成true
						'max_request' => 10000, // 最大请求次数，当请求大于它时，将会自动重启该worker
						'dispatch_mode' => 1
					)
				));
		
		$http = new swoole_http_server($hosts['host'], $hosts['port']);
		$http->set($hosts['setting']);
		$http->on('WorkerStart', 'ApiApp::onWorkerStart');
		$http->on('start', 'ApiApp::onStart');
		$http->on('request', 
				function  ( $req, $res ) {
					$webApp = new ApiApp($req, $res);
					$GLOBALS['WEBAPP'] = $webApp;
					$webApp->run();
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
		swoole_set_process_name(self::$processName.': master');
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
			swoole_set_process_name(self::$processName.': task');
		} else {
			swoole_set_process_name(self::$processName.': worker');
		}
		self::$workers[$worker_id] = $serv->worker_pid;
		printf("master_id: %d,manager_pid=%d,worker_id=%d,worker_pid=%d\n", $serv->master_pid, $serv->manager_pid, 
				$serv->worker_id, $serv->worker_pid);
	}

	/**
	 * 初始化Api
	 */
	static function initApi ( )
	{
		$servers = Conf::get('api.servers', array());
		$modmap = Conf::get('api.mod', array());
		$autogen = Conf::get('api.autodsl_root', array());
		
		require_once FR_ROOT . 'api/Api.php';
		Api::init(
				array( 
					'servers' => $servers,
					'mod' => $modmap,
					'encoding' => self::$encoding,
					'autodsl_root' => $autogen
				), array( 
					'api_root' => API_ROOT,
					'conf_root' => CONF_ROOT . 'api/'
				));
		$intercepterclasses = Conf::get('api.intercepters', array());
		$intercepters = array();
		foreach ( $intercepterclasses as $class ) {
			require_once PLUGIN_ROOT . 'intercepters/' . $class . '.php';
			$intercepters[] = new $class();
		}
		Api::setGlobalIntercepters($intercepters);
	}

	static function initDb ( )
	{
		require_once FR_ROOT . 'db/Db.php';
		
		$conf = Conf::get('db.conf');
		$readonly = Conf::get('db.readonly', false);
		Db::init($conf);
		Db::setReadOnly(! ! $readonly);
	}

	function __construct ( swoole_http_request $req, swoole_http_response $res )
	{
		$this->sreq = $req;
		$this->sres = $res;
		
		$GLOBALS['RESPONSE'] = $res;
		
		require_once FR_ROOT . 'util/Timer.php';
		$this->timer = new Timer();
	}

	function run ( )
	{
		$this->now = $this->sreq->server['request_time'];
		$this->appId = $this->genAppId();
		$this->uri = $this->sreq->server['request_uri'];
		
		$this->log('received request');
		
		$this->timer->begin('total', 'init');
		$this->init();
		$this->timer->end('init');
		
		$this->timer->begin('process');
		$ret = $this->process();
		$this->timer->end('process');
		if ( $ret === false ) {
			return $this->end();
		}
		
		$this->end();
// 		$this->sres->end(json_encode(array(
// 			'err' => 'hapn.ok',
// 			'data' => array(
// 				'rpcret' => array(),
// 			),
// 		)));
	}

	function init ( )
	{
		
		if ($this->get('_if') == 'json') {
			$raw = $this->sreq->rawContent();
			$post = @json_decode($raw, true);
			if ($post === false) {
				$post = array();
			}
			$this->sreq->post = $post;
		}
		
		// set_error_handler(array(
		// $this,
		// 'errorHandler'
		// ));
		// set_exception_handler(array(
		// $this,
		// 'exceptionHandler'
		// ));
		
		// 初始化日志
		require_once FR_ROOT . 'log/Log.php';
		
		$logFile = Conf::get('hapn.log.file', 'HapN');
		$logLevel = Conf::get('hapn.log.level', $this->debug ? LOG_LEVEL_DEBUG : LOG_LEVEL_TRACE);
		if ( $this->debug === true ) {
			// DEBUG模式下log级别直接为debug
			$logLevel = LOG_LEVEL_DEBUG;
		}
		$roll = Conf::get('hapn.log.roll', LOG_ROLLING_NONE);
		Logger::init(LOG_ROOT, $logFile, $logLevel, array(), $roll);
		
		$basic = array( 
			'appid' => $this->appId,
			'uri' => $this->uri,
			'pid' => posix_getpid(),
		);
		Logger::addBasic($basic);
		
		$requestfile = Conf::get('hapn.log.request');
		if ($requestfile) {
			$this->_logRequest($requestfile);
		}
	}
	
	private function _logRequest($requestfile)
	{
		$headers = $this->sreq->header;
		$file = LOG_ROOT.$requestfile;
		$get = $this->sreq->get;
		$out = array(
			'info' 		=> $this->appId.':'.$this->uri.':'.date('Y-m-d H:i:s', $this->now),
			'cookie'	=> isset($this->sreq->cookie) ? $this->sreq->cookie : array(),
			'header'	=> $this->sreq->header,
			'server'	=> $this->sreq->server,
			'get'		=> isset($this->sreq->get) ? $this->sreq->get : array(),
			'post'		=> isset($this->sreq->post) ? $this->sreq->post : array(),
		);
		file_put_contents($file, print_r($out, true), FILE_APPEND);
	}

	function process ( )
	{
		$uri = trim($this->uri, '/');
		if ( strpos($uri, '_private/rpc/') === 0 ) {
			$method = substr($uri, strlen('_private/rpc/'));
			
			$try = intval($this->get('_try', 1));
			if ( $try <= 0 ) {
				$try = 1;
			}
			
			if ( $try > Conf::get('hapn.rpctry', 3) ) {
				// 默认最大尝试3次
				throw new Exception("hapn.rpccall recursion");
			}
			list( $mod, $method ) = explode('.', $method);
			if ( ! $mod || ! $method ) {
				throw new Exception('hapn.errrpccall');
			}
			$args = $this->form('rpcinput', array());
			$params = $this->form('rpcinit', array());
			
			// 为了支持多级模块，把:转换成/，因为掉会用时把/当作:传入
			$mod = str_replace(':', '/', $mod);
			$params['_try'] = $try;
			
			// $filter = Conf::get('hapn.filter.init');
			// if ( $filter ) {
			// foreach($filter as $f) {
			// $path = PLUGIN_ROOT . '/filter/' . $f . '.php';
			// if ( ! is_readable($path) ) {
			// trigger_error('apiapp.filter_not_found', E_USER_WARNING);
			// } else {
			// require_once $path;
			// if ( ! class_exists($f) ) {
			// trigger_error('apiapp.filter_not_defined', E_USER_WARNING);
			// } else {
			// $this->timer->begin('filter.init');
			// $fil = new $f();
			// $ret = $fil->execute($this);
			// $this->timer->end('filter.end');
			// if ( $ret === false ) {
			// return false;
			// }
			// }
			// }
			// }
			// }
			$this->timer->begin('api');
			$proxy = Api::get($mod, $params);
			$ret = call_user_func_array(array( 
				$proxy,
				$method
			), $args);
			$this->timer->end('api');
			
			foreach ( $this->headers as $header ) {
				$this->sres->header($header[0], $header[1]);
			}
			$this->set('rpcret', $ret);
		} else {
			$this->sres->status(404);
			$this->endStatus = 'hapn.u_notfound';
		}
	}

	function end ( )
	{
		$result = $this->timer->getCosts();
		$str[] = '[time:';
		foreach ( $result as $key => $time ) {
			$str[] = ' ' . $key . '=' . $time;
		}
		$str[] = ']';
		Logger::notice(implode('', $str) . ' status=' . $this->endStatus);
		Logger::flush();
		
		if ( true !== Conf::get('hapn.disable_db') ) {
			// 做一些清理
			require_once FR_ROOT . 'db/TxScope.php';
			TxScope::rollbackAll();
			Db::close();
		}
		
		$ret = array( 
			'err' => $this->endStatus,
			'data' => $this->outputs
		);
		$this->sres->write(json_encode($ret, JSON_UNESCAPED_UNICODE));
		
		$this->log('request finish');
		$this->sres->end();
	}

	/**
	 * (non-PHPdoc)
	 * @see BaseApp::genAppId()
	 */
	function genAppId ( )
	{
		$header = $this->sreq->header;
		if ( isset($header['clientappid']) ) {
			return intval($header['clientappid']);
		}
		$server = $this->sreq->server;
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

	function log ( $msg )
	{
		$time = gettimeofday();
		$pid = posix_getpid();
		$index = array_search($pid, self::$workers);
		$msg = sprintf('[%s.%06d][#%d:%d][appid:%d][%s %s] %s', date('Y/m/d H:i:s', $time['sec']), $time['usec'], 
				$index, $pid, $this->appId, $this->sreq->server['request_method'], $this->sreq->server['request_uri'], 
				$msg);
		echo $msg . "\n";
	}

	function get ( $key, $def = NULL )
	{
		if ( isset($this->sreq->get[$key]) ) {
			return $this->sreq->get[$key];
		}
		return $def;
	}
	
	function form ($key, $def = NULL)
	{
		if ( isset($this->sreq->post[$key]) ) {
			return $this->sreq->post[$key];
		}
		return $def;
	}

	function set ( $key, $value )
	{
		$this->outputs[$key] = $value;
	}

	/**
	 * 请求的id设置到response header里
	 * @param string $errcode
	 */
	public function setHapNHeader ( $errcode = 'suc' )
	{
		global $__HapN_appid;
		$header = sprintf('id=%s,%s', $this->appId, $__HapN_appid);
		
		if ( $errcode != 'suc' ) {
			$method = 'r';
			$urls = explode('/', $this->uri);
			if ( $urls && strncmp($urls[count($urls) - 1], '_', 1) === 0 ) {
				// 最后一节是否以下划线开头的
				$method = 'w';
			}
			$header .= sprintf(',e=%s,m=%s', $errcode, $method);
			if ( ($retry = $this->get('retry')) ) {
				$header .= ',r=' . intval($retry);
			}
		}
		array_push($this->headers, array( 
			'HapN',
			$header
		));
	}

	/**
	 * 错误处理函数
	 * @return boolean 如果返回false，标准错误处理处理程序将会继续调用
	 */
	function errorHandler ( )
	{
		$error = func_get_args();
		restore_error_handler();
		if ( ! ($error[0] & error_reporting()) ) {
			Logger::debug('caught info, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], $error[3]);
			set_error_handler(array( 
				$this,
				'errorHandler'
			));
			return false;
		} elseif ( $error[0] === E_USER_NOTICE ) {
			Logger::trace('caught trace, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], 
					$error[3]);
			set_error_handler(array( 
				$this,
				'errorHandler'
			));
			return false;
		} elseif ( $error[0] === E_USER_WARNING ) {
			Logger::warning('caught warning, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], 
					$error[3]);
			set_error_handler(array( 
				$this,
				'errorHandler'
			));
			return false;
		} elseif ( $error[0] === E_USER_ERROR ) {
			Logger::fatal('caught error, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], 
					$error[3]);
			set_error_handler(array( 
				$this,
				'errorHandler'
			));
			return false;
		} else {
			
			$errmsg = sprintf('caught error, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], 
					$error[3]);
			Logger::fatal($errmsg);
			
			if ( true === $this->debug ) {
				unset($error[4]);
				echo "<pre>";
				print_r($error);
				echo "</pre>";
			}
			$errcode = 'hapn.fatal';
			$this->endStatus = $errcode;
			$this->setHapNHeader($errcode);
			
			$this->end();
		}
	}

	static function shutdownHandler ( )
	{
		$error = error_get_last();
		if ( $error ) {
			call_user_func_array(array( 
				$GLOBALS['WEBAPP'],
				'errorHandler'
			), $error);
		}
	}
}
